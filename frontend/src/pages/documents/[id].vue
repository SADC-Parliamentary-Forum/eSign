<script setup>
import VuePdfEmbed from 'vue-pdf-embed'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useWorkflowStore } from '@/stores/workflows'
import WorkflowTimeline from '@/components/workflows/WorkflowTimeline.vue'
import { ref, computed, onMounted, nextTick } from 'vue'

const route = useRoute()
const router = useRouter()
const workflowStore = useWorkflowStore()
const authStore = useAuthStore()

// --- State ---
const document = ref(null)
const signers = ref([])
const fields = ref([])
const loading = ref(true)
const pdfSource = ref(null) // Will be a Blob URL
const pageCount = ref(0)
const workflow = ref(null)

// Field values
const fieldValues = ref({}) 

// ... (keep signature dialog state)
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

// Helpers
const snackbar = ref({ show: false, text: '', color: 'success' })

// --- Computed ---
const currentUser = computed(() => authStore.user)
const currentUserEmail = computed(() => authStore.user?.email)

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
  if (!authStore.isAuthenticated) {
     // Try to restore session or redirect
     // For now, assume auth middleware handles this or layout redirects
  }

  await Promise.all([
    fetchDocument(),
    fetchFields(),
    fetchWorkflow(),
    fetchSavedSignatures(),
  ])

  // Redirect to prepare if draft and owner
  if (document.value?.status === 'DRAFT' && document.value?.user_id === currentUser.value?.id) {
     router.replace(`/prepare/${document.value.id}`)
     return
  }

  loading.value = false
})

// --- Methods ---

async function fetchDocument() {
  try {
    // 1. Fetch metadata
    const res = await $api(`/documents/${route.params.id}`)
    document.value = res
    signers.value = res.signers || []

    // 2. Fetch PDF securely as Blob
    await fetchPdfBlob(route.params.id)

  } catch (e) {
    console.error('Failed to load document', e)
    showSnackbar('Failed to load document', 'error')
  }
}

async function fetchPdfBlob(id) {
    try {
        const token = localStorage.getItem('token')
        const response = await fetch(`${import.meta.env.VITE_API_URL || '/api'}/documents/${id}/pdf`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/pdf'
            }
        })
        
        if (!response.ok) throw new Error('Failed to fetch PDF')
        
        const blob = await response.blob()
        pdfSource.value = URL.createObjectURL(blob)
    } catch (e) {
        console.error('PDF Load Error', e)
        showSnackbar('Could not load PDF file', 'error')
    }
}

async function fetchFields() {
  try {
    const res = await $api(`/documents/${route.params.id}/fields`)
    fields.value = res
    
    // Populate existing values
    res.forEach(f => {
       if (f.signature && f.signature.signature_data) {
           fieldValues.value[f.id] = { 
               value: f.signature.signature_data, 
               type: 'SIGNATURE' // or INITIALS
           }
       } else if (f.text_value) {
           fieldValues.value[f.id] = {
               value: f.text_value,
               type: f.type
           }
       }
    })

  } catch (e) {
    console.error('Failed to load fields', e)
  }
}

async function fetchWorkflow() {
  try {
    await workflowStore.fetchDocumentWorkflow(route.params.id)
    workflow.value = workflowStore.activeWorkflow
  } catch (e) {
    // If workflow not found (404), construct virtual workflow from signers
    if (document.value && document.value.signers) {
        console.log('Constructing virtual workflow from signers')
        workflow.value = {
            id: 'virtual',
            steps: document.value.signers.map(s => ({
                id: s.id,
                role: s.role || 'Signer',
                status: (s.status || 'PENDING').toUpperCase(),
                assignedUser: {
                    name: s.name,
                    email: s.email
                },
                signed_at: s.signed_at,
                declined_at: s.declined_at,
                created_at: s.created_at || document.value.created_at
            }))
        }
    }
  }
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

const statusColor = computed(() => {
  const map = {
    'DRAFT': 'grey',
    'IN_PROGRESS': 'info',
    'COMPLETED': 'success',
    'ARCHIVED': 'secondary'
  }
  return map[document.value?.status] || 'primary'
})

function isMyField(field) {
  return canSign.value && (
    field.signer_email === currentUser.value?.email || 
    field.document_signer_id === currentUser.value?.id
  )
}

function getFieldColor(field) {
  const isMine = isMyField(field)
  const isFilled = !!fieldValues.value[field.id]
  
  if (isFilled) return 'rgba(76, 175, 80, 0.1)' // Green tint
  if (isMine) return 'rgba(255, 193, 7, 0.15)' // Amber tint for action
  return 'transparent'
}

function getFieldBorder(field) {
  const isMine = isMyField(field)
  const isFilled = !!fieldValues.value[field.id]

  if (isFilled) return '1px solid #4CAF50'
  if (isMine) return '1px dashed #FFC107'
  return 'none'
}

function getFieldValueModel(field) {
  // Ensure we have a reactive object for v-model
  if (!fieldValues.value[field.id]) {
     fieldValues.value[field.id] = { value: '', type: 'TEXT' }
  }
  return fieldValues.value[field.id]
}
</script>

<template>
  <div class="h-100 d-flex flex-column bg-background">
    <!-- Premium Header -->
    <v-toolbar color="surface" elevation="1" height="72" class="border-b">
      <div class="px-6 w-100 d-flex align-center fill-height">
        <!-- Back Button -->
        <v-btn icon="mdi-arrow-left" variant="text" class="me-2" @click="$router.push('/')" />
        
        <!-- Title & Info Section -->
        <div class="d-flex flex-column justify-center overflow-hidden" style="max-width: 50%;">
          <div class="d-flex align-center">
             <span class="text-h6 font-weight-bold text-high-emphasis text-truncate me-3" :title="document?.title">
               {{ document?.title || 'Loading...' }}
             </span>
             <v-chip 
               v-if="document?.status"
               size="small" 
               :color="statusColor" 
               variant="flat" 
               class="font-weight-bold text-uppercase px-2"
               style="height: 20px; font-size: 10px;"
             >
               {{ document?.status?.replace('_', ' ') }}
             </v-chip>
          </div>
          <div class="d-flex align-center text-caption text-medium-emphasis mt-1">
              <v-icon size="14" start icon="mdi-account-group-outline" class="me-1" />
              {{ signers.length }} Participants
              <span class="mx-2">•</span>
              Created {{ new Date(document?.created_at).toLocaleDateString() }}
          </div>
        </div>

        <v-spacer />

        <!-- Actions Section -->
        <div v-if="canSign" class="d-flex align-center">
           <!-- Progress Indicator -->
           <div class="d-none d-md-flex align-center me-6 bg-grey-lighten-5 px-3 py-1 rounded">
              <div class="d-flex flex-column align-end me-3">
                 <span class="text-[10px] text-uppercase font-weight-bold text-medium-emphasis">Your Progress</span>
                 <span class="text-caption font-weight-bold text-primary">{{ completionPercentage }}% Complete</span>
              </div>
              <v-progress-circular
                 :model-value="completionPercentage"
                 color="primary"
                 size="28"
                 width="4"
                 bg-color="grey-lighten-2"
              >
                 <v-icon v-if="completionPercentage === 100" icon="mdi-check" size="14" color="primary" />
              </v-progress-circular>
           </div>
           
           <!-- Primary Action -->
           <v-btn 
              color="primary" 
              variant="flat"
              height="44"
              class="px-6 font-weight-bold text-none"
              elevation="2"
              prepend-icon="mdi-fountain-pen-tip"
              @click="finishSigning" 
           >
              Finish Signing
           </v-btn>
        </div>
      </div>
    </v-toolbar>

    <!-- Main Workspace -->
    <div class="d-flex flex-grow-1 overflow-hidden positions-relative">
      
      <!-- Document Canvas (The Desk) -->
      <div class="flex-grow-1 bg-grey-lighten-4 overflow-auto d-flex justify-center pa-8 position-relative">
        
        <!-- Loading State -->
        <div v-if="loading" class="d-flex flex-column align-center justify-center h-50">
           <v-progress-circular indeterminate color="primary" size="64" />
           <span class="mt-4 text-subtitle-2 text-medium-emphasis">Loading secure document...</span>
        </div>

        <!-- The Paper -->
        <div v-else-if="pdfSource" class="pdf-container d-flex flex-column align-center gap-4 pb-16">
           <!-- Hidden Loader to get Page Count -->
           <VuePdfEmbed
              v-if="pageCount === 0"
              :source="pdfSource"
              class="d-none"
              @loaded="handleDocumentLoad"
           />

           <div 
             v-for="page in pageCount" 
             :key="page" 
             class="pdf-page elevation-4 position-relative bg-white rounded-lg overflow-hidden"
             :style="{ width: '650px', minHeight: '840px' }"
           >
              <VuePdfEmbed 
                :source="pdfSource" 
                :page="page"
                width="650"
              />
              
              <!-- Fields Overlay layer -->
              <div class="fields-overlay position-absolute top-0 left-0 w-100 h-100">
                 <div
                   v-for="field in fields.filter(f => f.page_number === page)"
                   :key="field.id"
                   class="field-marker position-absolute d-flex align-center justify-center rounded transition-swing"
                   :class="{ 
                      'interactive elevation-2': canSign && (field.signer_email === currentUser?.email),
                      'readonly': !(canSign && (field.signer_email === currentUser?.email)),
                      'filled': fieldValues[field.id]
                   }"
                   :style="{
                      left: field.x + '%',
                      top: field.y + '%',
                      width: field.width + '%',
                      height: field.height + '%',
                      backgroundColor: getFieldColor(field),
                      border: getFieldBorder(field)
                   }"
                   @click="onFieldClick(field)"
                 >
                    <!-- Field Content (Signature, Text, Date) -->
                    <template v-if="field.type === 'SIGNATURE' || field.type === 'INITIALS'">
                        <div v-if="fieldValues[field.id]" class="w-100 h-100 p-1">
                            <img :src="fieldValues[field.id].value" class="w-100 h-100 object-contain" />
                        </div>
                        <div v-else class="text-center">
                            <v-icon :icon="field.type === 'INITIALS' ? 'mdi-alphabetical' : 'mdi-draw'" size="small" />
                            <div class="text-[10px] font-weight-bold text-uppercase mt-1">{{ field.type }}</div>
                        </div>
                    </template>

                    <!-- Text Input -->
                    <input 
                       v-else-if="field.type === 'TEXT'"
                       v-model="getFieldValueModel(field).value"
                       class="w-100 h-100 px-2 text-body-2 bg-transparent outline-none"
                       :disabled="!isMyField(field)"
                       placeholder="Enter text..."
                    />

                    <!-- Date -->
                    <div v-else-if="field.type === 'DATE'" class="text-body-2 font-weight-medium">
                       {{ fieldValues[field.id]?.value || 'Date' }}
                    </div>
                 </div>
              </div>
           </div>
        </div>
      </div>

      <!-- Right Sidebar (Info & Tools) -->
      <div 
        class="sidebar bg-surface border-s d-none d-md-flex flex-column" 
        style="width: 260px; min-width: 260px;"
      >
         <div class="pa-4 flex-grow-1 overflow-y-auto">
            <div class="text-overline mb-2 text-medium-emphasis">Workflow Timeline</div>
            <workflow-timeline v-if="workflow" :steps="workflow.steps" class="mb-6" />
            
            <div v-if="risks.length > 0">
               <v-divider class="my-4" />
               <div class="text-overline mb-2 text-warning">Risk Analysis</div>
               <v-alert
                  v-for="(risk, i) in risks" :key="i"
                  type="warning" variant="tonal" density="compact" class="mb-2 text-caption"
                  :icon="false"
               >
                  <strong>{{ risk.term }}</strong>: {{ risk.message }}
               </v-alert>
            </div>
         </div>

         <!-- Sidebar Footer Actions -->
         <div class="pa-4 bg-grey-lighten-5 border-t">
              <v-btn
                block
                variant="outlined"
                color="primary"
                class="mb-2"
                prepend-icon="mdi-robot"
                :loading="analyzing"
                @click="analyzeDocument"
              >
                Run AI Analysis
              </v-btn>

              <v-btn
                v-if="document?.status === 'COMPLETED'"
                block
                variant="tonal"
                color="secondary"
                prepend-icon="mdi-download"
                :href="`/api/documents/${document.id}/evidence`"
                target="_blank"
              >
                Download Evidence
              </v-btn>

               <v-btn
                 v-if="canCancelWorkflow && workflow?.status !== 'COMPLETED'"
                 block
                 variant="text"
                 color="error"
                 class="mt-2"
                 @click="showCancelDialog = true"
               >
                 Cancel Workflow
               </v-btn>
         </div>
      </div>
    </div>

    <!-- Dialogs (Keep existing) -->
    <v-dialog v-model="showSignatureDialog" max-width="500">
       <v-card class="rounded-lg">
          <v-card-title class="d-flex justify-space-between align-center p-4 border-b">
             <span>Adopt Your Signature</span>
             <v-btn icon="mdi-close" variant="text" size="small" @click="showSignatureDialog = false" />
          </v-card-title>
          
          <v-card-text class="pa-4">
             <v-tabs v-model="useSaved" density="compact" color="primary" class="mb-4">
                <v-tab :value="false">Draw</v-tab>
                <v-tab :value="true" v-if="savedSignatures.length > 0">Saved</v-tab>
             </v-tabs>

             <v-window v-model="useSaved">
                <v-window-item :value="false">
                   <div class="border rounded bg-grey-lighten-5 pa-2 mb-2 d-flex justify-center position-relative">
                      <canvas 
                         ref="signatureCanvas" 
                         width="400" 
                         height="200" 
                         class="cursor-crosshair bg-white elevation-1 rounded"
                         @mousedown="startDrawing"
                         @mousemove="draw"
                         @mouseup="stopDrawing"
                         @mouseleave="stopDrawing"
                      />
                      <div class="position-absolute bottom-0 right-0 ma-4">
                         <v-btn size="x-small" variant="text" color="error" @click="clearCanvas">Clear</v-btn>
                      </div>
                   </div>
                   <div class="text-caption text-center text-medium-emphasis">Draw your signature above</div>
                </v-window-item>

                <v-window-item :value="true">
                   <v-list selectable bg-color="transparent">
                      <v-list-item
                        v-for="sig in savedSignatures"
                        :key="sig.id"
                        :value="sig.id"
                        color="primary"
                        class="border mb-2 rounded bg-white"
                        @click="selectedSignatureId = sig.id"
                        :variant="selectedSignatureId === sig.id ? 'tonal' : 'text'"
                      >
                         <img :src="sig.image_data" height="60" class="d-block mx-auto" />
                      </v-list-item>
                   </v-list>
                </v-window-item>
             </v-window>
          </v-card-text>

          <v-card-actions class="pa-4 border-t bg-grey-lighten-5">
             <v-spacer />
             <v-btn variant="text" @click="showSignatureDialog = false">Cancel</v-btn>
             <v-btn color="primary" variant="flat" class="px-6" @click="saveSignature">
                Sign Document
             </v-btn>
          </v-card-actions>
       </v-card>
    </v-dialog>

    <!-- Keeps other dialogs same structure but clean up classes if needed -->
    <v-dialog v-model="showVerificationDialog" max-width="400">
        <!-- ... (Keep logic, just verify classes) -->
        <v-card class="rounded-lg">
           <v-card-title class="bg-primary text-white text-center py-4">Verify Identity</v-card-title>
           <v-card-text class="text-center pa-6">
              <div v-if="verificationStep === 'INIT'">
                 <v-avatar color="primary-lighten-4" size="80" class="mb-4">
                    <v-icon color="primary" size="40">mdi-shield-lock</v-icon>
                 </v-avatar>
                 <p class="text-body-1 mb-6">We need to send a verification code to<br/><strong>{{ currentUserEmail }}</strong></p>
                 <v-btn block color="primary" size="large" @click="sendOtp" :loading="verifying">Send Code</v-btn>
              </div>
              <div v-else>
                 <v-otp-input v-model="otpCode" length="6" class="mb-6 justify-center" />
                 <v-btn block color="primary" size="large" @click="verifyOtp" :loading="verifying" :disabled="otpCode.length < 6">Verify</v-btn>
                 <v-btn variant="text" size="small" class="mt-4" @click="verificationStep = 'INIT'">Resend</v-btn>
              </div>
           </v-card-text>
        </v-card>
    </v-dialog>

    <v-dialog v-model="showCancelDialog" max-width="500">
        <v-card class="rounded-lg">
            <v-card-title class="pa-4 border-b">Cancel Workflow</v-card-title>
            <v-card-text class="pa-4">
                <v-alert type="warning" variant="tonal" class="mb-4" icon="mdi-alert">
                    This action is permanent. All progress will be lost.
                </v-alert>
                <v-textarea v-model="cancelReason" label="Reason" variant="outlined" auto-grow rows="3" />
            </v-card-text>
            <v-card-actions class="pa-4 border-t bg-grey-lighten-5">
                <v-spacer/>
                <v-btn variant="text" @click="showCancelDialog = false">Keep Workflow</v-btn>
                <v-btn color="error" variant="flat" @click="cancelWorkflow" :loading="canceling">Confirm Cancel</v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
    
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" location="top center">
       {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<style scoped>
.interactive:hover {
  background-color: rgba(var(--v-theme-primary), 0.1) !important;
  border-color: rgb(var(--v-theme-primary)) !important;
  transform: translateY(-1px);
}
.cursor-crosshair {
    cursor: crosshair;
}
/* Hide scrollbar for sidebar but allow scroll */
.sidebar ::-webkit-scrollbar {
  width: 4px;
}
.sidebar ::-webkit-scrollbar-thumb {
  background: #e0e0e0;
  border-radius: 4px;
}
</style>
