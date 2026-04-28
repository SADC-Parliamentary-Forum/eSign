<script setup>
/**
 * Public Signing Page
 *
 * Enhanced with clear View / Approve / Reject actions
 * Supports saved signatures from user profiles
 * Embedded PDF preview for document viewing before signing
 */
definePage({
  meta: {
    public: true,
  },
})

import VuePdfEmbed from 'vue-pdf-embed/dist/index.essential.mjs'
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth' // Import auth store
import { apiFetch } from '@/utils/http'
import { useProgressivePdfRender } from '@/composables/useProgressivePdfRender'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore() // Initialize auth store

const token = computed(() => route.params.token)

// Page state
const currentView = ref('landing') // 'landing', 'preview', 'sign'
const doc = ref(null)
const signer = ref(null)
const fields = ref([])
const requiresAccount = ref(false)
const loading = ref(true)
const error = ref('')
const submitting = ref(false)

// PDF state
const {
  pdfSource,
  pageCount,
  visiblePages,
  renderProgress,
  renderError,
  loadPdfFromResponse,
  markPageRendered,
} = useProgressivePdfRender()

// Saved signatures & initials
const savedSignatures = ref([])
const savedInitials = ref([])
const selectedSignatureId = ref(null)
const selectedInitialsId = ref(null)
const useSavedSignature = ref(false)
const useSavedInitials = ref(false)
const loadingSignatures = ref(false)

// Signature & Initials canvas
const canvas = ref(null)
const initialsCanvas = ref(null)
let ctx = null
let initialsCtx = null
let isDrawing = false
let isDrawingInitials = false

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

// Signature & Initials Mode
const signatureMode = ref('draw') // 'draw' | 'upload' | 'type'
const initialsMode = ref('draw') // 'draw' | 'upload' | 'type'
const uploadedSignature = ref(null)
const uploadedInitials = ref(null)
const typedName = ref('')
const typedInitials = ref('')
const selectedFont = ref('Dancing Script')
const saveToProfile = ref(false)

const signatureFonts = [
  'Dancing Script',
  'Pacifico',
  'Pinyon Script',
  'Great Vibes',
  'Satisfy',
]

function handleFileUpload(event) {
  const file = event.target.files[0]
  if (!file) return
  
  const reader = new FileReader()

  reader.onload = e => {
    uploadedSignature.value = e.target.result
  }
  reader.readAsDataURL(file)
}

function handleInitialsUpload(event) {
  const file = event.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = e => {
    uploadedInitials.value = e.target.result
  }
  reader.readAsDataURL(file)
}

onMounted(async () => {
  await fetchDocument()
  await fetchSavedSignatures()
})

async function ensureAuthenticatedSession() {
  if (authStore.isAuthenticated)
    return true

  const authToken = localStorage.getItem('token')
  if (!authToken)
    return false

  try {
    await authStore.fetchUser()
  } catch (e) {
    console.warn('Unable to restore authenticated session:', e)
  }

  return authStore.isAuthenticated
}

async function fetchDocument() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch(`/sign/${token.value}`)
    const data = await res.json()
    
    if (!res.ok) {
      error.value = data.message || 'Failed to load document'
      
      return
    }
    
    doc.value = data.document
    signer.value = data.signer
    fields.value = data.fields || []
    requiresAccount.value = data.requires_account

    // Pre-fill typed name from signer
    if (signer.value?.name) {
      typedName.value = signer.value.name
      typedInitials.value = signer.value.name.split(' ').map(n => n[0]).join('').toUpperCase()
    }

    const requiresVerification = data.requires_verification

    // Enforce Authentication
    if (requiresAccount.value) {
      const isAuthenticated = await ensureAuthenticatedSession()
      if (!isAuthenticated) {
        // Redirect to login with return URL
        const returnUrl = encodeURIComponent(route.fullPath)

        window.location.href = `/login?returnUrl=${returnUrl}`
        
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

    await loadPublicPdf(data.pdf_url || `/storage/${data.document.file_path}`)

    // Mark as viewed
    await apiFetch(`/sign/${token.value}/view`, { method: 'POST' })
  } catch (e) {
    error.value = 'Failed to load document'
  } finally {
    loading.value = false
  }
}

async function loadPublicPdf(pdfUrl) {
  const response = await fetch(pdfUrl, {
    headers: {
      Accept: 'application/pdf',
    },
  })
  await loadPdfFromResponse(response, { initialVisiblePages: 2 })
}

async function fetchSavedSignatures() {
  const authToken = localStorage.getItem('token')
  if (!authToken) return

  loadingSignatures.value = true
  try {
    const res = await apiFetch('/signatures/mine', {
      headers: { 'Authorization': `Bearer ${authToken}` },
    })

    if (res.ok) {
      const data = await res.json()
      const allSigs = Array.isArray(data) ? data : data.data || []

      savedSignatures.value = allSigs.filter(s => s.type === 'signature')
      savedInitials.value = allSigs.filter(s => s.type === 'initials')
      
      // Auto-select defaults
      if (savedSignatures.value.length > 0) {
        const defaultSig = savedSignatures.value.find(s => s.is_default)
        selectedSignatureId.value = defaultSig?.id || savedSignatures.value[0].id
        useSavedSignature.value = true
      }
      
      if (savedInitials.value.length > 0) {
        const defaultInit = savedInitials.value.find(s => s.is_default)
        selectedInitialsId.value = defaultInit?.id || savedInitials.value[0].id
        useSavedInitials.value = true
      }
    }
  } catch (e) {
    console.error('Failed to load saved signatures:', e)
  } finally {
    loadingSignatures.value = false
  }
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
  if (canvas.value) {
    ctx = canvas.value.getContext('2d')
    ctx.lineWidth = 2
    ctx.lineCap = 'round'
    ctx.strokeStyle = '#000'
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
  }
  
  if (initialsCanvas.value) {
    initialsCtx = initialsCanvas.value.getContext('2d')
    initialsCtx.lineWidth = 2
    initialsCtx.lineCap = 'round'
    initialsCtx.strokeStyle = '#000'
    initialsCtx.clearRect(0, 0, initialsCanvas.value.width, initialsCanvas.value.height)
  }
}

function startDrawing(e) {
  isDrawing = true
  const rect = canvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  ctx.beginPath()
  ctx.moveTo(x, y)
}

function startDrawingInitials(e) {
  isDrawingInitials = true
  const rect = initialsCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  initialsCtx.beginPath()
  initialsCtx.moveTo(x, y)
}

function draw(e) {
  if (!isDrawing) return
  if (e.cancelable) e.preventDefault()
  const rect = canvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  ctx.lineTo(x, y)
  ctx.stroke()
}

function drawInitials(e) {
  if (!isDrawingInitials) return
  if (e.cancelable) e.preventDefault()
  const rect = initialsCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  initialsCtx.lineTo(x, y)
  initialsCtx.stroke()
}

function stopDrawing() {
  isDrawing = false
  isDrawingInitials = false
  ctx?.closePath()
  initialsCtx?.closePath()
}

function clearCanvas() {
  if (ctx) {
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
    ctx.strokeStyle = '#000'
  }
}

function clearInitialsCanvas() {
  if (initialsCtx) {
    initialsCtx.clearRect(0, 0, initialsCanvas.value.width, initialsCanvas.value.height)
    initialsCtx.strokeStyle = '#000'
  }
}

function generateTypedImage(text, font, width = 540, height = 120) {
  const offscreen = window.document.createElement('canvas')
  offscreen.width = width
  offscreen.height = height
  const ctx = offscreen.getContext('2d')
  
  // Clear for transparency
  ctx.clearRect(0, 0, width, height)
  
  ctx.fillStyle = '#000'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  
  const fontSize = height * 0.6
  ctx.font = `${fontSize}px "${font}", cursive`
  
  ctx.fillText(text, width / 2, height / 2)
  
  return offscreen.toDataURL('image/png')
}

// Registration
async function register() {
  registering.value = true
  error.value = ''
  try {
    const res = await apiFetch('/auth/register', {
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
async function submitSignature() {
  if (requiresAccount.value && !authStore.isAuthenticated) {
    showRegister.value = true
    return
  }
  
  submitting.value = true
  error.value = ''
  
  let signatureData = null
  let initialsData = null
  let userSignatureId = null
  
  // 1. Determine Signature
  if (useSavedSignature.value && selectedSignatureId.value) {
    userSignatureId = selectedSignatureId.value
    const sig = savedSignatures.value.find(s => s.id === userSignatureId)
    signatureData = sig?.image_data
  } else if (signatureMode.value === 'upload' && uploadedSignature.value) {
    signatureData = uploadedSignature.value
  } else if (signatureMode.value === 'type' && typedName.value) {
    signatureData = generateTypedImage(typedName.value, selectedFont.value, 540, 120)
  } else if (canvas.value) {
    signatureData = canvas.value.toDataURL('image/png')
  }
  
  // 2. Determine Initials
  if (useSavedInitials.value && selectedInitialsId.value) {
    const ini = savedInitials.value.find(s => s.id === selectedInitialsId.value)
    initialsData = ini?.image_data
  } else if (initialsMode.value === 'upload' && uploadedInitials.value) {
    initialsData = uploadedInitials.value
  } else if (initialsMode.value === 'type' && typedInitials.value) {
    initialsData = generateTypedImage(typedInitials.value, selectedFont.value, 540, 80)
  } else if (initialsCanvas.value) {
    initialsData = initialsCanvas.value.toDataURL('image/png')
  }
  
  if (!signatureData) {
    error.value = 'Please provide a signature'
    submitting.value = false
    quickSignMode.value = false
    return
  }
  
  const authToken = localStorage.getItem('token')
  
  try {
    const res = await apiFetch(`/sign/${token.value}/sign`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(authToken ? { 'Authorization': `Bearer ${authToken}` } : {}),
      },
      body: JSON.stringify({ 
        signature_data: signatureData,
        initials_data: initialsData,
        user_signature_id: userSignatureId,
        user_initials_id: selectedInitialsId.value,
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
    console.error('Signing failed:', e)
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
    const authToken = localStorage.getItem('token')
    const res = await apiFetch(`/sign/${token.value}/decline`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(authToken ? { 'Authorization': `Bearer ${authToken}` } : {}),
      },
      body: JSON.stringify({ reason: declineReason.value }),
    })
    
    if (res.ok) {
      router.push('/sign/declined')
      return
    }

    const data = await res.json()
    error.value = data.message || 'Failed to decline'
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

const selectedInitialsPreview = computed(() => {
  if (!selectedInitialsId.value) return null
  const ini = savedInitials.value.find(s => s.id === selectedInitialsId.value)
  return ini?.image_data || null
})

// Check if user has saved signatures
const hasSavedSignatures = computed(() => savedSignatures.value.length > 0)
const hasSavedInitials = computed(() => savedInitials.value.length > 0)
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
      <VProgressLinear
        indeterminate
        color="primary"
        height="6"
        rounded
        class="mt-4"
        style="width: 320px;"
      />
    </div>

    <!-- Error -->
    <VCard
      v-else-if="(error || renderError) && !doc"
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
          {{ error || renderError }}
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
              {{ doc.title }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              From: {{ doc.user?.name || 'Document Owner' }}
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
          {{ doc.title }}
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
        <div class="mb-3 text-start">
          <VProgressLinear :model-value="renderProgress" color="secondary" height="6" rounded />
          <div class="text-caption text-medium-emphasis mt-1">
            Rendering pages... {{ renderProgress }}%
          </div>
        </div>
        <div 
          v-for="page in visiblePages"
          :key="page" 
          class="pdf-page mb-4 elevation-3 position-relative d-inline-block bg-white"
        >
          <VuePdfEmbed 
            :source="pdfSource" 
            :page="page"
            width="700"
            @loaded="markPageRendered(page)"
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
          <VCardSubtitle>{{ doc.title }}</VCardSubtitle>
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

          <!-- Signature Section -->
          <div class="mb-8">
            <div class="d-flex align-center justify-space-between mb-2">
              <h3 class="text-subtitle-1 font-weight-bold">Signature</h3>
              <VCheckbox
                v-if="hasSavedSignatures"
                v-model="useSavedSignature"
                label="Use saved signature"
                density="compact"
                hide-details
                color="primary"
              />
            </div>

            <div v-if="useSavedSignature && hasSavedSignatures" class="saved-capture-area">
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
              <div v-if="selectedSignaturePreview" class="signature-preview border rounded pa-2 text-center bg-grey-lighten-5">
                <img :src="selectedSignaturePreview" alt="Signature preview" style="max-height: 80px; max-width: 100%;">
              </div>
            </div>

            <div v-else>
              <VTabs v-model="signatureMode" density="compact" color="primary" class="mb-4">
                <VTab value="draw">Draw</VTab>
                <VTab value="type">Type</VTab>
                <VTab value="upload">Upload</VTab>
              </VTabs>

              <div v-if="signatureMode === 'draw'">
                <div class="canvas-wrapper border rounded mb-2">
                  <canvas 
                    ref="canvas" 
                    width="540" 
                    height="120"
                    style="width: 100%; height: 120px; touch-action: none;"
                    @mousedown="startDrawing"
                    @mousemove="draw"
                    @mouseup="stopDrawing"
                    @mouseleave="stopDrawing"
                    @touchstart="startDrawing"
                    @touchmove="draw"
                    @touchend="stopDrawing"
                  />
                </div>
                <div class="d-flex justify-end">
                  <VBtn size="x-small" variant="text" @click="clearCanvas">Clear</VBtn>
                </div>
              </div>

              <div v-else-if="signatureMode === 'type'">
                <VTextField
                  v-model="typedName"
                  label="Type your name"
                  variant="outlined"
                  class="mb-4"
                  @input="e => typedInitials = e.target.value.split(' ').map(n => n[0]).join('').toUpperCase()"
                />
                <div class="font-selection-grid mb-4">
                  <VCard
                    v-for="font in signatureFonts"
                    :key="font"
                    :class="['font-card', { 'selected': selectedFont === font }]"
                    variant="outlined"
                    @click="selectedFont = font"
                  >
                    <div :style="{ fontFamily: font, fontSize: '24px' }" class="pa-2">
                      {{ typedName || 'Signature' }}
                    </div>
                  </VCard>
                </div>
              </div>

              <div v-else class="upload-area pa-4 border rounded text-center">
                <VFileInput
                  label="Upload Signature Image"
                  accept="image/*"
                  density="compact"
                  variant="outlined"
                  @change="handleFileUpload"
                />
                <div v-if="uploadedSignature" class="mt-2 border rounded pa-2 text-center bg-grey-lighten-5">
                  <img :src="uploadedSignature" style="max-height: 80px; max-width: 100%;">
                </div>
              </div>
            </div>
          </div>

          <!-- Initials Section -->
          <div class="mb-8">
            <div class="d-flex align-center justify-space-between mb-2">
              <h3 class="text-subtitle-1 font-weight-bold">Initials</h3>
              <VCheckbox
                v-if="hasSavedInitials"
                v-model="useSavedInitials"
                label="Use saved initials"
                density="compact"
                hide-details
                color="primary"
              />
            </div>

            <div v-if="useSavedInitials && hasSavedInitials" class="saved-capture-area">
              <VSelect
                v-model="selectedInitialsId"
                :items="savedInitials"
                item-title="name"
                item-value="id"
                label="Select initials"
                variant="outlined"
                density="compact"
                class="mb-3"
              />
              <div v-if="selectedInitialsPreview" class="signature-preview border rounded pa-2 text-center bg-grey-lighten-5">
                <img :src="selectedInitialsPreview" alt="Initials preview" style="max-height: 50px; max-width: 100%;">
              </div>
            </div>

            <div v-else>
              <VTabs v-model="initialsMode" density="compact" color="primary" class="mb-4">
                <VTab value="draw">Draw</VTab>
                <VTab value="type">Type</VTab>
                <VTab value="upload">Upload</VTab>
              </VTabs>

              <div v-if="initialsMode === 'draw'">
                <div class="canvas-wrapper border rounded mb-2">
                  <canvas 
                    ref="initialsCanvas" 
                    width="540" 
                    height="80"
                    style="width: 100%; height: 80px; touch-action: none;"
                    @mousedown="startDrawingInitials"
                    @mousemove="drawInitials"
                    @mouseup="stopDrawing"
                    @mouseleave="stopDrawing"
                    @touchstart="startDrawingInitials"
                    @touchmove="drawInitials"
                    @touchend="stopDrawing"
                  />
                </div>
                <div class="d-flex justify-end">
                  <VBtn size="x-small" variant="text" @click="clearInitialsCanvas">Clear</VBtn>
                </div>
              </div>

              <div v-else-if="initialsMode === 'type'">
                <VTextField
                  v-model="typedInitials"
                  label="Your Initials"
                  variant="outlined"
                  class="mb-4"
                />
                <div class="initials-preview text-center pa-2 border rounded bg-grey-lighten-5" :style="{ fontFamily: selectedFont, fontSize: '32px' }">
                  {{ typedInitials || 'Init' }}
                </div>
              </div>

              <div v-else class="upload-area pa-4 border rounded text-center">
                <VFileInput
                  label="Upload Initials Image"
                  accept="image/*"
                  density="compact"
                  variant="outlined"
                  @change="handleInitialsUpload"
                />
                <div v-if="uploadedInitials" class="mt-2 border rounded pa-2 text-center bg-grey-lighten-5">
                  <img :src="uploadedInitials" style="max-height: 50px; max-width: 100%;">
                </div>
              </div>
            </div>
          </div>

          <VCheckbox
            v-if="authStore.isAuthenticated"
            v-model="saveToProfile"
            label="Save these to my profile for future use"
            density="compact"
            color="primary"
            hide-details
            class="mb-4"
          />

          <VAlert
            v-if="error"
            type="error"
            variant="tonal"
            class="mb-4"
            closable
            @click:close="error = ''"
          >
            {{ error }}
          </VAlert>

          <div class="d-flex gap-2 justify-end">
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
          <VTextField
            v-model="registerForm.name"
            label="Full Name"
            class="mb-3"
          />
          <VTextField
            v-model="registerForm.email"
            label="Email Address"
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
            class="mb-4"
          />
          <VBtn
            block
            color="primary"
            size="large"
            :loading="registering"
            @click="register"
          >
            Create Account & Sign
          </VBtn>
        </VCardText>
        <VCardActions>
          <VBtn block variant="text" @click="showRegister = false">Cancel</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script&family=Pacifico&family=Pinyon+Script&family=Great+Vibes&family=Satisfy&display=swap');

.sign-page {
  min-height: 100vh;
  background: #f5f7fa;
  padding: 40px 20px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}

.landing-view, .sign-view {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 16px;
}

.action-card {
  transition: all 0.2s ease;
}

.action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.pdf-viewer-container {
  min-height: 600px;
}

.pdf-page {
  background: white;
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.my-field {
  border: 2px solid #ff5252;
  background: rgba(255, 82, 82, 0.1);
  pointer-events: none;
}

.field-badge {
  position: absolute;
  top: -20px;
  left: 0;
  font-size: 10px;
  background: #ff5252;
  color: white;
  padding: 2px 4px;
  border-radius: 2px;
}

.page-number {
  position: absolute;
  bottom: 10px;
  right: 10px;
  font-size: 12px;
  color: #888;
  background: rgba(255,255,255,0.8);
  padding: 2px 8px;
  border-radius: 10px;
}

.canvas-wrapper {
  background: white;
  overflow: hidden;
}

.signature-preview {
  min-height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.font-selection-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
}

.font-card {
  cursor: pointer;
  transition: all 0.2s ease;
  overflow: hidden;
  text-align: center;
}

.font-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.font-card.selected {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.initials-preview {
  min-height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>

