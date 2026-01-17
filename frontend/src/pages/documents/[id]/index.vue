<script setup>
import VuePdfEmbed from 'vue-pdf-embed'
import { useRoute, useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import WorkflowTimeline from '@/components/workflows/WorkflowTimeline.vue'

const route = useRoute()
const router = useRouter()
const workflowStore = useWorkflowStore()

// State
const document = ref(null)
const signers = ref([])
const fields = ref([])
const loading = ref(true)
const pdfSource = ref(null)
const pageCount = ref(0)
const workflow = ref(null)

// Field values (user input)
const fieldValues = ref({}) 
// Format: { [fieldId]: { value: '...', type: '...' } }

// Signature Dialog
const showSignatureDialog = ref(false)
const activeFieldId = ref(null)
const signatureCanvas = ref(null)
let ctx = null
let isDrawing = false

const currentUserEmail = ref(null) // Should get from auth store
const currentUser = ref(null)

// Computed
const myFields = computed(() => {
  if (!currentUser.value) return []
  return fields.value.filter(f => 
    f.signer_email === currentUser.value.email || 
    f.document_signer_id === currentUser.value.id ||
    // For MVP, if no signer assigned, maybe anyone? No, strict.
    (f.role && f.role === 'signer') // simple check
  )
})

const canSign = computed(() => {
  // Check if document is in progress and user is a signer
  if (document.value?.status !== 'IN_PROGRESS') return false
  
  // Check if it's my turn (if sequential)
  if (document.value.sequential_signing) {
     const mySigner = signers.value.find(s => s.email === currentUser.value.email)
     if (mySigner && mySigner.signing_order !== document.value.current_signing_order) {
       return false
     }
  }
  return true
})

const completionPercentage = computed(() => {
  if (myFields.value.length === 0) return 0
  const filled = myFields.value.filter(f => fieldValues.value[f.id]).length
  return Math.round((filled / myFields.value.length) * 100)
})

onMounted(async () => {
  // Mock current user - replace with actual Auth Store usage
  const authRes = await $api('/auth/me').catch(() => ({ email: 'demo@example.com', id: 'uuid' })) // Fallback
  currentUser.value = authRes
  currentUserEmail.value = authRes?.email

  await Promise.all([
    fetchDocument(),
    fetchFields(),
    fetchWorkflow(),
  ])
  loading.value = false
})

async function fetchDocument() {
  try {
    const res = await $api(`/documents/${route.params.id}`)
    document.value = res
    pdfSource.value = `/storage/${res.file_path}` 
    signers.value = res.signers || []
  } catch (e) {
    console.error('Failed to load document', e)
  }
}

async function fetchFields() {
  try {
    const res = await $api(`/documents/${route.params.id}/fields`)
    fields.value = res
    
    // Initialize fieldValues from previously saved if any?
    // For now, start empty or checks stored values (not yet implemented backend retrieval of values)
  } catch (e) {
    console.error('Failed to load fields', e)
  }
}

async function fetchWorkflow() {
  try {
    await workflowStore.fetchDocumentWorkflow(route.params.id)
    workflow.value = workflowStore.activeWorkflow
  } catch (e) { /* ignore */ }
}

function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
}

// Interactions
function onFieldClick(field) {
  if (!canSign.value) return
  // Check ownership
  const isMine = field.signer_email === currentUser.value?.email 
               || field.document_signer_id === currentUser.value?.id // Simplified check

  if (!isMine) return

  activeFieldId.value = field.id

  if (field.type === 'SIGNATURE' || field.type === 'INITIALS') {
    showSignatureDialog.value = true
    nextTick(() => initCanvas())
  } else if (field.type === 'CHECKBOX') {
    const current = fieldValues.value[field.id]?.value
    fieldValues.value[field.id] = { value: !current, type: field.type }
  }
  // Text and Date inputs handled directly in template via v-model binding to fieldValues
}

// Signature Pad
function initCanvas() {
  if (!signatureCanvas.value) return
  ctx = signatureCanvas.value.getContext('2d')
  ctx.lineWidth = 2
  ctx.strokeByte = '#000'
  // ... (canvas init code from old file)
}
// ... (draw functions)

function saveSignature() {
  const dataUrl = signatureCanvas.value.toDataURL()
  fieldValues.value[activeFieldId.value] = { value: dataUrl, type: 'SIGNATURE' }
  showSignatureDialog.value = false
}

const mySigner = computed(() => {
  return signers.value.find(s => s.email === currentUser.value?.email) || 
         signers.value.find(s => s.user_id === currentUser.value?.id)
})

// Verification State
const showVerificationDialog = ref(false)
const verificationStep = ref('INIT')
const otpCode = ref('')
const verifying = ref(false)

function isAdvancedAndNotVerified() {
    const level = document.value?.signature_level
    if (level === 'ADVANCED' || level === 'QUALIFIED') {
        const verified = mySigner.value?.verified_at
        return !verified
    }
    return false
}

async function sendOtp() {
    verifying.value = true
    try {
        await $api(`/verification/signers/${mySigner.value.id}/otp`, { method: 'POST' })
        verificationStep.value = 'OTP'
    } catch (e) {
        alert('Failed to send OTP: ' + e.message)
    } finally {
        verifying.value = false
    }
}

async function verifyOtp() {
    verifying.value = true
    try {
        await $api(`/verification/signers/${mySigner.value.id}/otp/verify`, {
            method: 'POST',
            body: { code: otpCode.value }
        })
        alert('Identity Verified Successfully')
        showVerificationDialog.value = false
        // Refresh document to get updated verified_at status
        fetchDocument()
    } catch (e) {
        alert('Verification Failed: ' + e.message)
    } finally {
        verifying.value = false
    }
}

async function finishSigning() {
  // Validate all required fields
  const missing = myFields.value.filter(f => f.required && !fieldValues.value[f.id])
  if (missing.length > 0) {
    alert(`Please fill in all required fields (${missing.length} remaining).`)
    return
  }

  // Final check for verification (redundant but safe)
  if (isAdvancedAndNotVerified()) {
    showVerificationDialog.value = true
    return
  }

  try {
    await $api(`/documents/${document.value.id}/sign`, {
      method: 'POST',
      body: {
        fields: Object.keys(fieldValues.value).map(id => ({
          field_id: id,
          value: fieldValues.value[id].value
        }))
      }
    })
    router.push('/')
  } catch(e) {
    if (e.response?.status === 403 && e.response?.data?.requires_verification) {
         showVerificationDialog.value = true
    } else {
         alert('Failed to sign: ' + e.message)
    }
  }
}
</script>

<template>
  <div class="h-100 d-flex flex-column">
    <!-- Header -->
    <v-toolbar density="compact" color="surface" elevation="1" class="px-4">
      <v-btn icon="mdi-arrow-left" @click="$router.push('/')" />
      <v-toolbar-title class="text-subtitle-1 font-weight-bold">
        {{ document?.title }}
      </v-toolbar-title>
      <v-spacer />
      <div v-if="canSign" class="d-flex align-center">
         <span class="text-caption mr-2">{{ completionPercentage }}% Completed</span>
         <v-progress-linear :model-value="completionPercentage" color="success" width="100" class="mr-4" />
         <v-btn color="success" @click="finishSigning" :disabled="completionPercentage < 100">
            Finish Signing
         </v-btn>
      </div>
    </v-toolbar>

    <div class="d-flex flex-grow-1 overflow-hidden">
      <!-- PDF Content -->
      <div class="main-content flex-grow-1 bg-grey-lighten-3 overflow-auto pa-8 text-center">
        <div v-if="loading" class="d-flex justify-center mt-10">
           <v-progress-circular indeterminate />
        </div>

        <div v-else-if="pdfSource" class="pdf-wrapper d-inline-block">
           <div 
             v-for="page in pageCount" 
             :key="page" 
             class="pdf-page mb-4 elevation-2 position-relative bg-white"
             style="width: 800px; min-height: 1000px;"
           >
              <VuePdfEmbed 
                :source="pdfSource" 
                :page="page"
                width="800"
                @loaded="handleDocumentLoad"
              />
              
              <!-- Fields Overlay -->
              <div class="fields-overlay position-absolute top-0 left-0 w-100 h-100">
                 <div
                   v-for="field in fields.filter(f => f.page_number === page)"
                   :key="field.id"
                   class="field-marker position-absolute d-flex align-center justify-center"
                   :class="{ 
                      'interactive': canSign && (field.signer_email === currentUser?.email),
                      'filled': fieldValues[field.id]
                   }"
                   :style="{
                      left: field.x + '%',
                      top: field.y + '%',
                      width: field.width + '%',
                      height: field.height + '%',
                      backgroundColor: fieldValues[field.id] ? 'rgba(0, 255, 0, 0.1)' : 'rgba(255, 235, 59, 0.2)',
                      border: '1px solid ' + (fieldValues[field.id] ? 'green' : 'orange')
                   }"
                   @click="onFieldClick(field)"
                 >
                    <!-- Content -->
                    <div v-if="field.type === 'SIGNATURE'" class="w-100 h-100 d-flex align-center justify-center">
                       <img v-if="fieldValues[field.id]" :src="fieldValues[field.id].value" class="w-100 h-100 object-contain" />
                       <span v-else class="text-caption text-uppercase">Sign Here</span>
                    </div>

                    <div v-else-if="field.type === 'TEXT'" class="w-100 h-100">
                       <input 
                         v-if="canSign && field.signer_email === currentUser?.email"
                         :value="fieldValues[field.id]?.value"
                         @input="e => fieldValues[field.id] = { value: e.target.value, type: 'TEXT' }"
                         class="w-100 h-100 px-1 text-body-2"
                         style="background: transparent; outline: none; border: none;"
                         placeholder="Text..."
                       />
                       <span v-else class="text-body-2">{{ fieldValues[field.id]?.value }}</span>
                    </div>

                    <div v-else-if="field.type === 'DATE'" class="w-100 h-100 d-flex align-center justify-center text-caption">
                       {{ fieldValues[field.id]?.value || 'Date' }}
                    </div>
                 </div>
              </div>
           </div>
        </div>
      </div>

      <!-- Right Sidebar (Timeline) -->
      <div class="sidebar border-s bg-surface" style="width: 300px;">
         <div class="pa-4">
            <h3 class="text-h6 mb-4">Document Info</h3>
            <v-chip size="small" :color="document?.status === 'COMPLETED' ? 'success' : 'info'" class="mb-4">
               {{ document?.status }}
            </v-chip>
            
            <workflow-timeline v-if="workflow" :steps="workflow.steps" />
            
            <v-divider class="my-4" />
            
            <div v-if="document?.status === 'COMPLETED' || document?.status === 'ARCHIVED'">
              <v-btn
                block
                prepend-icon="mdi-shield-check"
                color="secondary"
                variant="flat"
                :href="`/api/documents/${document.id}/evidence`"
                target="_blank"
              >
                Download Evidence
              </v-btn>
              <div class="text-caption text-medium-emphasis mt-2 text-center">
                Download zip bundle with signed document and forensic audit trail
              </div>
            </div>
         </div>
      </div>
    </div>

    <!-- Sign Dialog -->
    <v-dialog v-model="showSignatureDialog" max-width="500">
       <v-card title="Sign Document">
          <v-card-text>
             <div class="border rounded pa-2 bg-white">
                <canvas ref="signatureCanvas" width="450" height="200" class="canvas" />
             </div>
          </v-card-text>
          <v-card-actions>
             <v-spacer />
             <v-btn text @click="showSignatureDialog = false">Cancel</v-btn>
             <v-btn color="primary" @click="saveSignature">Apply</v-btn>
          </v-card-actions>
       </v-card>
    </v-dialog>

    <!-- Identity Verification Dialog -->
    <v-dialog v-model="showVerificationDialog" max-width="450" persistent>
       <v-card>
          <v-card-title class="text-h6 bg-primary text-white">
             Identity Verification Required
          </v-card-title>
          <v-card-text class="pt-4">
             <div class="mb-4">
                This document requires <strong>{{ document?.signature_level }}</strong> signature assurance.
                Please verify your identity via email OTP before proceeding.
             </div>

             <div v-if="verificationStep === 'INIT'" class="text-center">
                <v-icon size="64" color="primary" class="mb-4">mdi-email-check</v-icon>
                <p>We will send a one-time code to <strong>{{ currentUserEmail }}</strong>.</p>
                <v-btn color="primary" class="mt-4" @click="sendOtp" :loading="verifying">
                   Send Verification Code
                </v-btn>
             </div>

             <div v-else-if="verificationStep === 'OTP'">
                <p class="mb-2">Enter the 6-digit code sent to your email:</p>
                <v-otp-input v-model="otpCode" length="6" class="mb-4" />
                
                <div class="d-flex justify-center">
                   <v-btn color="primary" @click="verifyOtp" :loading="verifying" :disabled="otpCode.length < 6">
                      Verify Identity
                   </v-btn>
                </div>
                <div class="text-center mt-4">
                   <v-btn variant="text" size="small" @click="verificationStep = 'INIT'">Resend Code</v-btn>
                </div>
             </div>
          </v-card-text>
       </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.field-marker.interactive {
  cursor: pointer;
}
.field-marker.interactive:hover {
  background-color: rgba(255, 235, 59, 0.4) !important;
}
.canvas {
  cursor: crosshair;
}
</style>
