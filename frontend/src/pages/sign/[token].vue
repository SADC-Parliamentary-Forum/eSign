<script setup>
/**
 * Public Signing Page
 * 
 * Enhanced with clear View / Approve / Reject actions
 * Supports saved signatures from user profiles
 * Embedded PDF preview for document viewing before signing
 */
import VuePdfEmbed from 'vue-pdf-embed'
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth' // Import auth store

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore() // Initialize auth store

const token = computed(() => route.params.token)

// Page state
const currentView = ref('landing') // 'landing', 'preview', 'sign'
const document = ref(null)
const signer = ref(null)
const fields = ref([])
const requiresAccount = ref(false)
const loading = ref(true)
const error = ref('')
const submitting = ref(false)

// PDF state
const pdfSource = ref(null)
const pageCount = ref(0)

// Saved signatures
const savedSignatures = ref([])
const selectedSignatureId = ref(null)
const useSavedSignature = ref(false)
const loadingSignatures = ref(false)

// Signature canvas
const canvas = ref(null)
let ctx = null
let isDrawing = false

// Decline dialog
const showDeclineDialog = ref(false)
const declineReason = ref('')

// Registration form
const showRegister = ref(false)

const registerForm = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const registering = ref(false)

// Quick sign mode (use saved signature directly)
const quickSignMode = ref(false)

// Signature Mode
const signatureMode = ref('draw') // 'draw' | 'upload'
const uploadedSignature = ref(null)
const saveToProfile = ref(false)

function handleFileUpload(event) {
  const file = event.target.files[0]
  if (!file) return
  
  const reader = new FileReader()

  reader.onload = e => {
    uploadedSignature.value = e.target.result
  }
  reader.readAsDataURL(file)
}

onMounted(async () => {
  await fetchDocument()
  await fetchSavedSignatures()
})

async function fetchDocument() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`/api/sign/${token.value}`)
    const data = await res.json()
    
    if (!res.ok) {
      error.value = data.message || 'Failed to load document'
      
      return
    }
    
    document.value = data.document
    signer.value = data.signer
    fields.value = data.fields || []
    requiresAccount.value = data.requires_account

    const requiresVerification = data.requires_verification

    // Enforce Authentication
    if (requiresAccount.value) {
      if (!authStore.isAuthenticated) {
        // Redirect to login with return URL
        const returnUrl = encodeURIComponent(route.fullPath)

        window.location.href = `/auth/login?returnUrl=${returnUrl}`
        
        return
      }

      // Enforce Email Match
      if (authStore.user?.email !== signer.value.email) {
        error.value = `You are logged in as ${authStore.user?.email}, but this document was sent to ${signer.value.email}. Please log out and log in with the correct account.`
        
        return
      }

      // Enforce Email Verification
      // Note: We need to rely on the backend provided user object or fetch it fresh
      if (requiresVerification && !authStore.user?.email_verified_at) {
        // Try fetching fresh user data to be sure
        await authStore.fetchUser()
        if (!authStore.user?.email_verified_at) {
          // Redirect to verification page (assuming /auth/verify-email exists or similar)
          // For now, let's show an error instructing them to verify
          error.value = 'Your email address is not verified. Please check your email inbox for a verification link.'

          // Ideally: router.push('/auth/verify-email')
          return
        }
      }
    }

    pdfSource.value = data.pdf_url || `/storage/${data.document.file_path}`

    // Mark as viewed
    await fetch(`/api/sign/${token.value}/view`, { method: 'POST' })
  } catch (e) {
    error.value = 'Failed to load document'
  } finally {
    loading.value = false
  }
}

async function fetchSavedSignatures() {
  const authToken = localStorage.getItem('token')
  if (!authToken) return

  loadingSignatures.value = true
  try {
    const res = await fetch('/api/signatures/mine', {
      headers: { 'Authorization': `Bearer ${authToken}` },
    })

    if (res.ok) {
      const data = await res.json()

      savedSignatures.value = (Array.isArray(data) ? data : data.data || [])
        .filter(s => s.type === 'signature')
      
      // Auto-select default signature
      if (savedSignatures.value.length > 0) {
        const defaultSig = savedSignatures.value.find(s => s.is_default)

        selectedSignatureId.value = defaultSig?.id || savedSignatures.value[0].id
        useSavedSignature.value = true
      }
    }
  } catch (e) {
    console.error('Failed to load saved signatures:', e)
  } finally {
    loadingSignatures.value = false
  }
}

function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
}

// View Actions
function startViewing() {
  currentView.value = 'preview'
}

function startSigning() {
  currentView.value = 'sign'
  setTimeout(initCanvas, 100)
}

// Quick approve with saved signature
async function quickApprove() {
  if (savedSignatures.value.length === 0) {
    // No saved signature, go to regular signing
    startSigning()
    
    return
  }

  quickSignMode.value = true
  await submitSignature()
}

function backToLanding() {
  currentView.value = 'landing'
}

// Canvas methods
function initCanvas() {
  if (!canvas.value) return
  ctx = canvas.value.getContext('2d')
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.strokeStyle = '#000'
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.value.width, canvas.value.height)
}

function startDrawing(e) {
  isDrawing = true

  const rect = canvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top

  ctx.beginPath()
  ctx.moveTo(x, y)
}

function draw(e) {
  if (!isDrawing) return
  e.preventDefault()

  const rect = canvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top

  ctx.lineTo(x, y)
  ctx.stroke()
}

function stopDrawing() {
  isDrawing = false
  ctx?.closePath()
}

function clearCanvas() {
  if (!ctx) return
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.value.width, canvas.value.height)
  ctx.strokeStyle = '#000'
}

// Registration
async function register() {
  registering.value = true
  error.value = ''
  try {
    const res = await fetch('/api/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(registerForm.value),
    })

    const data = await res.json()
    
    if (!res.ok) {
      error.value = data.message || 'Registration failed'
      
      return
    }
    
    localStorage.setItem('token', data.access_token)
    showRegister.value = false
    requiresAccount.value = false
    
    // After registration, submit signature
    await submitSignature()
  } catch (e) {
    error.value = 'Registration failed'
  } finally {
    registering.value = false
  }
}

// Submit signature
// Submit signature
async function submitSignature() {
  if (requiresAccount.value && !authStore.isAuthenticated) {
    showRegister.value = true
    
    return
  }
  
  submitting.value = true
  error.value = ''
  
  let signatureData = null
  let userSignatureId = null
  
  // Determine signature source
  if (useSavedSignature.value && selectedSignatureId.value) {
    // Using saved signature
    userSignatureId = selectedSignatureId.value
    
    // Fetch the signature data if needed
    const authToken = localStorage.getItem('token')
    if (authToken) {
      try {
        const res = await fetch(`/api/signatures/mine/${selectedSignatureId.value}`, {
          headers: { 'Authorization': `Bearer ${authToken}` },
        })

        if (res.ok) {
          const sig = await res.json()

          signatureData = sig.image_data
        }
      } catch (e) {
        error.value = 'Failed to load saved signature'
        submitting.value = false
        quickSignMode.value = false
        
        return
      }
    }
  } else if (signatureMode.value === 'upload' && uploadedSignature.value) {
    // Using uploaded signature
    signatureData = uploadedSignature.value
  } else if (canvas.value) {
    // Using drawn signature
    signatureData = canvas.value.toDataURL('image/png')
  }
  
  if (!signatureData && !userSignatureId) {
    error.value = 'Please draw a signature or select a saved signature'
    submitting.value = false
    quickSignMode.value = false
    
    return
  }
  
  const authToken = localStorage.getItem('token')
  
  try {
    const res = await fetch(`/api/sign/${token.value}/sign`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(authToken ? { 'Authorization': `Bearer ${authToken}` } : {}),
      },
      body: JSON.stringify({ 
        signature_data: signatureData,
        user_signature_id: userSignatureId,
        save_to_profile: saveToProfile.value,
      }),
    })

    const data = await res.json()
    
    if (!res.ok) {
      if (data.requires_registration) {
        showRegister.value = true
        
        return
      }
      error.value = data.message || 'Signing failed'
      
      return
    }
    
    router.push('/sign/success')
  } catch (e) {
    error.value = 'Signing failed'
  } finally {
    submitting.value = false
    quickSignMode.value = false
  }
}

// Decline
async function openDeclineDialog() {
  showDeclineDialog.value = true
}

async function confirmDecline() {
  if (!declineReason.value.trim()) {
    error.value = 'Please provide a reason for declining'
    
    return
  }
  
  submitting.value = true
  try {
    const res = await fetch(`/api/sign/${token.value}/decline`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reason: declineReason.value }),
    })
    
    if (res.ok) {
      router.push('/sign/declined')
    }
  } catch (e) {
    error.value = 'Failed to decline'
  } finally {
    submitting.value = false
  }
}

// Get my fields for the current page
function getMyFieldsForPage(page) {
  return fields.value.filter(f => 
    f.page_number === page && 
    (f.signer_email === signer.value?.email || f.document_signer_id === signer.value?.id),
  )
}

// Get selected signature preview
const selectedSignaturePreview = computed(() => {
  if (!selectedSignatureId.value) return null
  const sig = savedSignatures.value.find(s => s.id === selectedSignatureId.value)
  
  return sig?.image_data || null
})

// Check if user has saved signatures
const hasSavedSignatures = computed(() => savedSignatures.value.length > 0)
</script>

<template>
  <div class="sign-page">
    <!-- Loading -->
    <div
      v-if="loading"
      class="loading-state"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
      <div class="text-body-1 text-medium-emphasis mt-4">
        Loading document...
      </div>
    </div>

    <!-- Error -->
    <VCard
      v-else-if="error && !document"
      class="error-card mx-auto my-8"
      max-width="500"
    >
      <VCardText class="text-center py-10">
        <VIcon
          icon="mdi-alert-circle"
          size="64"
          color="error"
          class="mb-4"
        />
        <h3 class="text-h6 mb-2">
          Unable to Load Document
        </h3>
        <p class="text-body-2 text-medium-emphasis">
          {{ error }}
        </p>
      </VCardText>
    </VCard>

    <!-- Landing View: Three Actions -->
    <div
      v-else-if="currentView === 'landing'"
      class="landing-view"
    >
      <VCard
        class="mx-auto"
        max-width="600"
      >
        <!-- Header -->
        <VCardItem class="bg-primary text-white">
          <template #prepend>
            <VAvatar color="primary-darken-1">
              <VIcon icon="mdi-file-sign" />
            </VAvatar>
          </template>
          <VCardTitle class="text-h5">
            Document Signing Request
          </VCardTitle>
          <VCardSubtitle class="text-white">
            You've been asked to sign a document
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pa-6">
          <!-- Document Info -->
          <div class="document-info mb-6">
            <div class="text-h6 mb-1">
              {{ document.title }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              From: {{ document.user?.name || 'Document Owner' }}
            </div>
          </div>

          <!-- Signer Info -->
          <div class="signer-info d-flex align-center mb-6 pa-4 bg-grey-lighten-4 rounded-lg">
            <VAvatar
              color="primary"
              class="mr-3"
            >
              <span class="text-white font-weight-bold">
                {{ signer.name.charAt(0).toUpperCase() }}
              </span>
            </VAvatar>
            <div class="flex-grow-1">
              <div class="font-weight-medium">
                {{ signer.name }}
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ signer.email }}
              </div>
            </div>
            <VChip
              :color="signer.can_sign ? 'success' : 'warning'"
              size="small"
            >
              {{ signer.can_sign ? 'Ready to Sign' : 'Waiting' }}
            </VChip>
          </div>

          <!-- Saved Signature Notice -->
          <VAlert 
            v-if="hasSavedSignatures" 
            type="success" 
            variant="tonal" 
            class="mb-4"
          >
            <div class="d-flex align-center">
              <VIcon
                icon="mdi-check-circle"
                class="mr-2"
              />
              <div class="flex-grow-1">
                <div class="font-weight-medium">
                  Saved Signature Available
                </div>
                <div class="text-body-2">
                  Click "Approve" to sign instantly with your saved signature
                </div>
              </div>
            </div>
          </VAlert>

          <!-- Action Buttons - Three Clear Options -->
          <div class="actions-grid">
            <!-- View Document -->
            <VCard 
              class="action-card pa-4 text-center cursor-pointer"
              variant="outlined"
              @click="startViewing"
            >
              <VIcon
                icon="mdi-file-eye"
                size="48"
                color="info"
                class="mb-3"
              />
              <div class="text-h6 mb-1">
                View
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Read the document before deciding
              </div>
            </VCard>

            <!-- Approve / Sign -->
            <VCard 
              v-if="signer.can_sign"
              class="action-card pa-4 text-center cursor-pointer"
              variant="outlined"
              color="success"
              @click="hasSavedSignatures ? quickApprove() : startSigning()"
            >
              <VIcon
                icon="mdi-check-circle"
                size="48"
                color="success"
                class="mb-3"
              />
              <div class="text-h6 mb-1">
                Approve
              </div>
              <div class="text-body-2 text-medium-emphasis">
                {{ hasSavedSignatures ? 'Sign with saved signature' : 'Sign the document now' }}
              </div>
              <VProgressCircular 
                v-if="quickSignMode && submitting" 
                indeterminate 
                size="20" 
                class="mt-2" 
              />
            </VCard>

            <!-- Reject -->
            <VCard 
              class="action-card pa-4 text-center cursor-pointer"
              variant="outlined"
              color="error"
              @click="openDeclineDialog"
            >
              <VIcon
                icon="mdi-close-circle"
                size="48"
                color="error"
                class="mb-3"
              />
              <div class="text-h6 mb-1">
                Reject
              </div>
              <div class="text-body-2 text-medium-emphasis">
                Decline to sign
              </div>
            </VCard>
          </div>

          <!-- Not Your Turn Alert -->
          <VAlert
            v-if="!signer.can_sign"
            type="info"
            variant="tonal"
            class="mt-4"
          >
            <VIcon
              icon="mdi-clock"
              class="mr-2"
            />
            It's not your turn to sign yet. You'll be notified when it's your turn.
          </VAlert>

          <!-- Error Alert -->
          <VAlert
            v-if="error"
            type="error"
            variant="tonal"
            class="mt-4"
            closable
            @click:close="error = ''"
          >
            {{ error }}
          </VAlert>
        </VCardText>
      </VCard>
    </div>

    <!-- Preview View: Document Viewer -->
    <div
      v-else-if="currentView === 'preview'"
      class="preview-view"
    >
      <VToolbar
        color="surface"
        elevation="1"
        density="compact"
      >
        <VBtn
          icon="mdi-arrow-left"
          @click="backToLanding"
        />
        <VToolbarTitle class="text-subtitle-1">
          {{ document.title }}
        </VToolbarTitle>
        <VSpacer />
        <VBtn 
          v-if="signer.can_sign"
          color="success" 
          variant="elevated"
          :loading="quickSignMode && submitting"
          @click="hasSavedSignatures ? quickApprove() : startSigning()"
        >
          <VIcon
            icon="mdi-check"
            class="mr-2"
          />
          {{ hasSavedSignatures ? 'Approve with Saved Signature' : 'Proceed to Sign' }}
        </VBtn>
      </VToolbar>

      <div class="pdf-viewer-container pa-8 text-center bg-grey-lighten-3">
        <div 
          v-for="page in pageCount" 
          :key="page" 
          class="pdf-page mb-4 elevation-3 position-relative d-inline-block bg-white"
        >
          <VuePdfEmbed 
            :source="pdfSource" 
            :page="page"
            width="700"
            @loaded="handleDocumentLoad"
          />

          <!-- Highlight My Fields -->
          <div 
            v-for="field in getMyFieldsForPage(page)"
            :key="field.id"
            class="my-field position-absolute"
            :style="{
              left: field.x + '%',
              top: field.y + '%',
              width: field.width + '%',
              height: field.height + '%'
            }"
          >
            <span class="field-badge">{{ field.type }}</span>
          </div>

          <div class="page-number">
            Page {{ page }} of {{ pageCount }}
          </div>
        </div>
      </div>
    </div>

    <!-- Sign View: Signature Capture -->
    <div
      v-else-if="currentView === 'sign'"
      class="sign-view"
    >
      <VCard
        class="mx-auto"
        max-width="600"
      >
        <VCardItem>
          <template #prepend>
            <VBtn
              icon="mdi-arrow-left"
              variant="text"
              @click="currentView = 'preview'"
            />
          </template>
          <VCardTitle>Sign Document</VCardTitle>
          <VCardSubtitle>{{ document.title }}</VCardSubtitle>
        </VCardItem>

        <VDivider />

        <VCardText>
          <!-- Consent Notice -->
          <VAlert
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            <VIcon
              icon="mdi-information"
              class="mr-2"
            />
            By signing, you agree to be legally bound by this document.
          </VAlert>

          <!-- Saved Signatures Option -->
          <div
            v-if="hasSavedSignatures"
            class="mb-6"
          >
            <VSwitch
              v-model="useSavedSignature"
              label="Use my saved signature"
              color="primary"
              hide-details
              class="mb-3"
            />

            <div
              v-if="useSavedSignature"
              class="saved-signatures-section"
            >
              <VSelect
                v-model="selectedSignatureId"
                :items="savedSignatures"
                item-title="name"
                item-value="id"
                label="Select signature"
                variant="outlined"
                density="compact"
                class="mb-3"
              />

              <!-- Preview selected signature -->
              <div
                v-if="selectedSignaturePreview"
                class="signature-preview pa-3 rounded border"
              >
                <div class="text-caption text-medium-emphasis mb-2">
                  Preview:
                </div>
                <img 
                  :src="selectedSignaturePreview" 
                  alt="Signature preview" 
                  class="signature-image"
                >
              </div>
            </div>
          </div>

          <!-- Draw/Upload Signature (if not using saved) -->
          <div v-if="!useSavedSignature">
            <VTabs
              v-model="signatureMode"
              density="compact"
              color="primary"
              class="mb-4"
            >
              <VTab value="draw">
                Draw
              </VTab>
              <VTab value="upload">
                Upload Image
              </VTab>
            </VTabs>

            <div v-if="signatureMode === 'draw'">
              <p class="text-body-2 text-medium-emphasis mb-3">
                Sign in the box below using your mouse or finger
              </p>
              
              <div class="canvas-wrapper mb-4">
                <canvas 
                  ref="canvas" 
                  width="540" 
                  height="150"
                  @mousedown="startDrawing"
                  @mousemove="draw"
                  @mouseup="stopDrawing"
                  @mouseleave="stopDrawing"
                  @touchstart="startDrawing"
                  @touchmove="draw"
                  @touchend="stopDrawing"
                />
              </div>
            </div>

            <div
              v-else
              class="upload-section pa-4 border rounded mb-4 text-center"
            >
              <VFileInput
                label="Upload Signature Image"
                accept="image/*"
                prepend-icon="mdi-camera"
                variant="outlined"
                @change="handleFileUpload"
              />
              <div
                v-if="uploadedSignature"
                class="mt-4"
              >
                <img
                  :src="uploadedSignature"
                  alt="Uploaded Signature"
                  style="max-height: 100px; max-width: 100%; border: 1px dashed #ccc; padding: 5px;"
                >
              </div>
            </div>

            <VCheckbox
              v-if="authStore.isAuthenticated"
              v-model="saveToProfile"
              label="Save this signature to my profile for future use"
              density="compact"
              color="primary"
              hide-details
              class="mb-4"
            />
          </div>

          <VAlert
            v-if="error"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ error }}
          </VAlert>

          <div class="d-flex gap-2">
            <VBtn
              v-if="!useSavedSignature"
              variant="text"
              @click="clearCanvas"
            >
              <VIcon
                icon="mdi-eraser"
                class="mr-1"
              />
              Clear
            </VBtn>
            <VSpacer />
            <VBtn
              variant="outlined"
              @click="currentView = 'preview'"
            >
              Back
            </VBtn>
            <VBtn 
              color="success" 
              size="large"
              :loading="submitting" 
              @click="submitSignature"
            >
              <VIcon
                icon="mdi-check"
                class="mr-2"
              />
              Complete Signing
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </div>

    <!-- Decline Dialog -->
    <VDialog
      v-model="showDeclineDialog"
      max-width="450"
      persistent
    >
      <VCard>
        <VCardTitle class="text-h6 bg-error text-white">
          <VIcon
            icon="mdi-close-circle"
            class="mr-2"
          />
          Decline to Sign
        </VCardTitle>
        <VCardText class="pt-4">
          <p class="text-body-1 mb-4">
            Please provide a reason for declining this document. The sender will be notified.
          </p>
          <VTextarea
            v-model="declineReason"
            label="Reason for declining"
            variant="outlined"
            rows="3"
            placeholder="Enter your reason..."
          />
          <VAlert
            v-if="error"
            type="error"
            variant="tonal"
            class="mt-3"
          >
            {{ error }}
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4">
          <VBtn
            variant="text"
            @click="showDeclineDialog = false"
          >
            Cancel
          </VBtn>
          <VSpacer />
          <VBtn 
            color="error" 
            :loading="submitting"
            :disabled="!declineReason.trim()"
            @click="confirmDecline"
          >
            Confirm Decline
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Registration Dialog -->
    <VDialog
      v-model="showRegister"
      max-width="400"
      persistent
    >
      <VCard>
        <VCardTitle class="pt-4">
          Create Account to Sign
        </VCardTitle>
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Create an account to sign this document. Your signature will be saved for future use.
          </p>
          <VTextField
            v-model="registerForm.name"
            label="Full Name"
            class="mb-3"
          />
          <VTextField
            v-model="registerForm.email"
            label="Email"
            type="email"
            class="mb-3"
          />
          <VTextField
            v-model="registerForm.password"
            label="Password"
            type="password"
            class="mb-3"
          />
          <VTextField
            v-model="registerForm.password_confirmation"
            label="Confirm Password"
            type="password"
          />
          <VAlert
            v-if="error"
            type="error"
            variant="tonal"
            class="mt-3"
          >
            {{ error }}
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4">
          <VBtn
            variant="text"
            @click="showRegister = false"
          >
            Cancel
          </VBtn>
          <VSpacer />
          <VBtn
            color="primary"
            :loading="registering"
            @click="register"
          >
            Create Account
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.sign-page {
  min-height: 100vh;
  background: rgb(var(--v-theme-background));
  padding: 16px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
}

.landing-view {
  padding-top: 24px;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 16px;
}

.action-card {
  transition: all 0.2s ease;
  cursor: pointer;
}

.action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.cursor-pointer {
  cursor: pointer;
}

.preview-view {
  height: 100vh;
  display: flex;
  flex-direction: column;
}

.pdf-viewer-container {
  flex: 1;
  overflow-y: auto;
}

.pdf-page {
  width: 700px;
  min-height: 900px;
  border-radius: 4px;
  overflow: hidden;
}

.my-field {
  border: 3px solid #4CAF50;
  background-color: rgba(76, 175, 80, 0.15);
  border-radius: 4px;
  animation: pulse 2s infinite;
}

.field-badge {
  position: absolute;
  top: -12px;
  left: 4px;
  background: #4CAF50;
  color: white;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

.page-number {
  position: absolute;
  bottom: 8px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255,255,255,0.9);
  padding: 2px 12px;
  border-radius: 12px;
  font-size: 12px;
}

.sign-view {
  padding-top: 24px;
}

.canvas-wrapper {
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: white;
  overflow: hidden;
}

canvas {
  display: block;
  width: 100%;
  cursor: crosshair;
  touch-action: none;
}

.signature-preview {
  background: white;
  text-align: center;
}

.signature-image {
  max-width: 100%;
  max-height: 100px;
  object-fit: contain;
}

.saved-signatures-section {
  padding: 16px;
  background: rgba(var(--v-theme-success), 0.05);
  border-radius: 8px;
  border: 1px solid rgba(var(--v-theme-success), 0.2);
}
</style>
