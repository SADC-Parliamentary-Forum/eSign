<script setup>
import VuePdfEmbed from 'vue-pdf-embed'
import { useRoute, useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import WorkflowTimeline from '@/components/workflows/WorkflowTimeline.vue'
import { ref, computed, onMounted, nextTick } from 'vue'

const route = useRoute()
const router = useRouter()
const workflowStore = useWorkflowStore()

// --- State ---
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

// Signature Dialog & Canvas
const showSignatureDialog = ref(false)
const activeFieldId = ref(null)
const signatureCanvas = ref(null)
let ctx = null
let isDrawing = false

// Saved Signatures State
const savedSignatures = ref([])
const selectedSignatureId = ref(null)
const useSaved = ref(false)

// Workflow Cancellation
const showCancelDialog = ref(false)
const cancelReason = ref('')
const canceling = ref(false)

// AI Analysis
const risks = ref([])
const analyzing = ref(false)

// User Info
const currentUserEmail = ref(null)
const currentUser = ref(null)

// Helpers
const snackbar = ref({ show: false, text: '', color: 'success' })

// --- Computed ---
const myFields = computed(() => {
  if (!currentUser.value) return []
  return fields.value.filter(f => 
    f.signer_email === currentUser.value.email || 
    f.document_signer_id === currentUser.value.id ||
    (f.role && f.role === 'signer')
  )
})

const canSign = computed(() => {
  if (document.value?.status !== 'IN_PROGRESS') return false
  
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

const mySigner = computed(() => {
  return signers.value.find(s => s.email === currentUser.value?.email) || 
         signers.value.find(s => s.user_id === currentUser.value?.id)
})

const canCancelWorkflow = computed(() => {
  if (!document.value || !workflow.value) return false
  return document.value.can_cancel || false
})

// --- Hooks ---
onMounted(async () => {
  // Mock current user - replace with actual Auth Store usage
  try {
    const authRes = await $api('/auth/me')
    currentUser.value = authRes
    currentUserEmail.value = authRes?.email
  } catch (e) {
    // Fallback or redirect to login
    currentUser.value = { email: 'demo@example.com', id: 'uuid' }
  }

  await Promise.all([
    fetchDocument(),
    fetchFields(),
    fetchWorkflow(),
    fetchSavedSignatures(),
  ])
  loading.value = false
})

// --- Methods ---

async function fetchDocument() {
  try {
    const res = await $api(`/documents/${route.params.id}`)
    document.value = res
    pdfSource.value = `/storage/${res.file_path}` 
    signers.value = res.signers || []
  } catch (e) {
    console.error('Failed to load document', e)
    showSnackbar('Failed to load document', 'error')
  }
}

async function fetchFields() {
  try {
    const res = await $api(`/documents/${route.params.id}/fields`)
    fields.value = res
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

async function fetchSavedSignatures() {
  try {
    const res = await $api('/signatures/mine')
    savedSignatures.value = (Array.isArray(res) ? res : res.data || [])
      .filter(s => s.type === 'signature')
    
    if (savedSignatures.value.length > 0) {
      const defaultSig = savedSignatures.value.find(s => s.is_default)
      selectedSignatureId.value = defaultSig?.id || savedSignatures.value[0].id
    }
  } catch (e) { /* ignore */ }
}

function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
}

// --- Interaction ---

function onFieldClick(field) {
  if (!canSign.value) return
  
  const isMine = field.signer_email === currentUser.value?.email 
               || field.document_signer_id === currentUser.value?.id

  if (!isMine) return

  activeFieldId.value = field.id

  if (field.type === 'SIGNATURE' || field.type === 'INITIALS') {
    showSignatureDialog.value = true
    nextTick(() => {
      // Only init canvas if not using saved signature
      if (!useSaved.value) initCanvas()
    })
  } else if (field.type === 'CHECKBOX') {
    const current = fieldValues.value[field.id]?.value
    fieldValues.value[field.id] = { value: !current, type: field.type }
  }
}

function initCanvas() {
  if (!signatureCanvas.value) return
  ctx = signatureCanvas.value.getContext('2d')
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.strokeStyle = '#000'
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height)
}

function startDrawing(e) {
  isDrawing = true
  const rect = signatureCanvas.value.getBoundingClientRect()
  ctx.beginPath()
  ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top)
}

function draw(e) {
  if (!isDrawing) return
  const rect = signatureCanvas.value.getBoundingClientRect()
  ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top)
  ctx.stroke()
}

function stopDrawing() {
  isDrawing = false
  ctx?.closePath()
}

function clearCanvas() {
  initCanvas()
}

async function saveSignature() {
  let signatureValue

  if (useSaved.value && selectedSignatureId.value) {
     try {
       const sig = await $api(`/signatures/mine/${selectedSignatureId.value}`)
       signatureValue = sig.image_data
     } catch(e) {
       showSnackbar('Failed to load saved signature', 'error')
       return
     }
  } else {
     signatureValue = signatureCanvas.value.toDataURL('image/png')
  }

  fieldValues.value[activeFieldId.value] = { value: signatureValue, type: 'SIGNATURE' }
  showSignatureDialog.value = false
}

// --- Verification ---
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
        showSnackbar('Identity Verified Successfully', 'success')
        showVerificationDialog.value = false
        fetchDocument()
    } catch (e) {
        showSnackbar('Verification Failed: ' + e.message, 'error')
    } finally {
        verifying.value = false
    }
}

// --- Actions ---

async function finishSigning() {
  const missing = myFields.value.filter(f => f.required && !fieldValues.value[f.id])
  if (missing.length > 0) {
    showSnackbar(`Please fill in all required fields (${missing.length} remaining).`, 'error')
    return
  }

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
    showSnackbar('Document signed successfully!', 'success')
    // Wait a bit then redirect
    setTimeout(() => router.push('/'), 1500)
  } catch(e) {
    if (e.response?.status === 403 && e.response?.data?.requires_verification) {
         showVerificationDialog.value = true
    } else {
         showSnackbar('Failed to sign: ' + e.message, 'error')
    }
  }
}

async function analyzeDocument() {
  analyzing.value = true
  try {
    const data = await $api(`/documents/${document.value.id}/analyze`, { method: 'POST' })
    risks.value = data.risks || []
    if (risks.value.length === 0) {
      showSnackbar('No significant risks detected.', 'success')
    } else {
      showSnackbar(`${risks.value.length} risks detected`, 'warning')
    }
  } catch (e) {
    showSnackbar(`Analysis failed: ${e.message}`, 'error')
  } finally {
    analyzing.value = false
  }
}

async function cancelWorkflow() {
  if (!cancelReason.value.trim()) return

  canceling.value = true
  try {
    await workflowStore.cancelWorkflow(workflow.value.id, cancelReason.value)
    showSnackbar('Workflow cancelled successfully', 'success')
    showCancelDialog.value = false
    await Promise.all([fetchDocument(), fetchWorkflow()])
  } catch (e) {
    showSnackbar(`Failed to cancel workflow: ${e.message}`, 'error')
  } finally {
    canceling.value = false
  }
}

function showSnackbar(text, color) {
  snackbar.value = { show: true, text, color }
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
      <!-- PDF Content Area -->
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
                    <!-- Field Content -->
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

      <!-- Right Sidebar -->
      <div class="sidebar border-s bg-surface" style="width: 320px; overflow-y: auto;">
         <div class="pa-4">
            <h3 class="text-h6 mb-4">Document Info</h3>
            <v-chip size="small" :color="document?.status === 'COMPLETED' ? 'success' : 'info'" class="mb-4">
               {{ document?.status }}
            </v-chip>
            
            <workflow-timeline v-if="workflow" :steps="workflow.steps" />
            
            <v-divider class="my-4" />
            
            <!-- Actions -->
            <div class="d-flex flex-column gap-2">
              <!-- Evidence Download -->
              <v-btn
                v-if="document?.status === 'COMPLETED' || document?.status === 'ARCHIVED'"
                block
                prepend-icon="mdi-shield-check"
                color="secondary"
                variant="flat"
                :href="`/api/documents/${document.id}/evidence`"
                target="_blank"
                class="mb-2"
              >
                Download Evidence
              </v-btn>

              <!-- AI Scan -->
               <v-btn
                 block
                 prepend-icon="mdi-robot"
                 color="primary"
                 variant="outlined"
                 :loading="analyzing"
                 @click="analyzeDocument"
                 class="mb-2"
               >
                 AI Risk Scan
               </v-btn>

               <!-- Cancel Workflow -->
               <v-btn
                 v-if="canCancelWorkflow && workflow?.status !== 'COMPLETED'"
                 block
                 prepend-icon="mdi-cancel"
                 color="error"
                 variant="outlined"
                 @click="showCancelDialog = true"
               >
                 Cancel Workflow
               </v-btn>
            </div>
            
            <!-- AI Risks Display -->
            <v-expand-transition>
               <v-alert v-if="risks.length > 0" type="warning" variant="tonal" class="mt-4" closable >
                 <div class="text-subtitle-2 mb-1">Risk Analysis:</div>
                 <ul class="text-caption ms-4">
                   <li v-for="(risk, i) in risks" :key="i">
                     <strong>{{ risk.term }}:</strong> {{ risk.message }}
                   </li>
                 </ul>
               </v-alert>
            </v-expand-transition>

         </div>
      </div>
    </div>

    <!-- Sign Dialog -->
    <v-dialog v-model="showSignatureDialog" max-width="500">
       <v-card title="Sign Document">
          <v-card-text>
             <!-- Saved Signature Toggle -->
             <div v-if="savedSignatures.length > 0" class="mb-4">
                 <v-switch v-model="useSaved" label="Use saved signature" color="primary" hide-details />
                 <v-select
                   v-if="useSaved"
                   v-model="selectedSignatureId"
                   :items="savedSignatures"
                   item-title="name"
                   item-value="id"
                   label="Select signature"
                   variant="outlined"
                   density="compact"
                   class="mt-2"
                 />
             </div>

             <!-- Canvas -->
             <div v-if="!useSaved" class="border rounded pa-2 bg-white">
                <canvas 
                   ref="signatureCanvas" 
                   width="450" 
                   height="200" 
                   class="canvas"
                   @mousedown="startDrawing"
                   @mousemove="draw"
                   @mouseup="stopDrawing"
                   @mouseleave="stopDrawing"
                />
             </div>
             
             <div v-if="!useSaved" class="d-flex justify-end mt-2">
                 <v-btn size="small" variant="text" @click="clearCanvas">Clear</v-btn>
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
    
    <!-- Cancellation Dialog -->
    <v-dialog v-model="showCancelDialog" max-width="500">
      <v-card>
        <v-card-title>Cancel Workflow</v-card-title>
        <v-card-text>
          <v-alert type="warning" variant="tonal" class="mb-4">
            This will cancel the entire workflow. This action cannot be undone.
          </v-alert>

          <v-textarea
            v-model="cancelReason"
            label="Reason for cancellation"
            placeholder="Please provide a reason..."
            variant="outlined"
            rows="3"
            required
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showCancelDialog = false">
            Close
          </v-btn>
          <v-btn
            color="error"
            :loading="canceling"
            :disabled="!cancelReason.trim()"
            @click="cancelWorkflow"
          >
            Confirm
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color">
      {{ snackbar.text }}
      <template #actions>
        <v-btn variant="text" @click="snackbar.show = false">
          Close
        </v-btn>
      </template>
    </v-snackbar>
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
/* Ensure PDF wrapper scales nicely */
.pdf-page canvas {
  display: block;
}
</style>
