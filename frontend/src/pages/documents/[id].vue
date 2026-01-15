<script setup>
import { useRoute, useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import WorkflowTimeline from '@/components/workflows/WorkflowTimeline.vue'
import TrustScoreIndicator from '@/components/common/TrustScoreIndicator.vue'
import EvidencePackageViewer from '@/components/evidence/EvidencePackageViewer.vue'

const route = useRoute()
const router = useRouter()
const workflowStore = useWorkflowStore()

const document = ref({})
const workflow = ref(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const canvas = ref(null)
let ctx = null
let isDrawing = false

// Workflow cancellation
const showCancelDialog = ref(false)
const cancelReason = ref('')
const canceling = ref(false)

// Saved signatures
const savedSignatures = ref([])
const selectedSignatureId = ref(null)
const useSaved = ref(false)

const risks = ref([])
const analyzing = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' })

// Check if current user can sign
const canSign = computed(() => {
  if (!workflow.value) return false
  return workflow.value.canUserSign || false
})

// Check if user can cancel workflow
const canCancelWorkflow = computed(() => {
  if (!document.value || !workflow.value) return false
  // Document owner or admin can cancel
  return document.value.can_cancel || false
})

onMounted(async () => {
  await Promise.all([
    fetchDocument(),
    fetchWorkflow(),
    fetchSavedSignatures(),
  ])
  initCanvas()
})

async function fetchDocument() {
  try {
    const res = await $api(`/documents/${route.params.id}`)
    document.value = res
  }
  catch (e) {
    error.value = 'Failed to load document'
  }
  finally {
    loading.value = false
  }
}

async function fetchWorkflow() {
  try {
    await workflowStore.fetchDocumentWorkflow(route.params.id)
    workflow.value = workflowStore.activeWorkflow
  }
  catch (e) {
    console.error('Failed to load workflow:', e)
    // Workflow may not exist yet
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
  }
  catch (e) {
    // Ignore - user may not have saved signatures
  }
}

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
  const rect = canvas.value.getBoundingClientRect  ()
  ctx.beginPath()
  ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top)
}

function draw(e) {
  if (!isDrawing) return
  const rect = canvas.value.getBoundingClientRect()
  ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top)
  ctx.stroke()
}

function stopDrawing() {
  isDrawing = false
  ctx?.closePath()
}

function clearCanvas() {
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.value.width, canvas.value.height)
  ctx.strokeStyle = '#000'
}

async function analyzeDocument() {
  analyzing.value = true
  try {
    const data = await $api(`/documents/${document.value.id}/analyze`, { method: 'POST' })
    risks.value = data.risks || []
    if (risks.value.length === 0) {
      showSnackbar('No significant risks detected.', 'success')
    }
  }
  catch (e) {
    showSnackbar(`Analysis failed: ${e.message || 'Unknown error'}`, 'error')
  }
  finally {
    analyzing.value = false
  }
}

async function submitSignature() {
  submitting.value = true
  error.value = ''

  let signatureData
  if (useSaved.value && selectedSignatureId.value) {
    // Get saved signature image
    try {
      const sig = await $api(`/signatures/mine/${selectedSignatureId.value}`)
      signatureData = sig.image_data
    }
    catch (e) {
      error.value = 'Failed to load saved signature'
      submitting.value = false
      return
    }
  }
  else {
    signatureData = canvas.value.toDataURL('image/png')
  }

  try {
    await $api(`/documents/${document.value.id}/sign`, {
      method: 'POST',
      body: {
        signature_data: signatureData,
        user_signature_id: useSaved.value ? selectedSignatureId.value : null,
      },
    })

    showSnackbar('Document signed successfully!', 'success')
    
    // Refresh workflow to show updated status
    await fetchWorkflow()
    await fetchDocument()
    
    setTimeout(() => router.push('/'), 1500)
  }
  catch (e) {
    error.value = e.message || 'Signature failed'
  }
  finally {
    submitting.value = false
  }
}

async function cancelWorkflow() {
  if (!cancelReason.value.trim()) {
    showSnackbar('Please provide a reason for cancellation', 'error')
    return
  }

  canceling.value = true
  try {
    await workflowStore.cancelWorkflow(workflow.value.id, cancelReason.value)
    showSnackbar('Workflow cancelled successfully', 'success')
    showCancelDialog.value = false
    
    // Refresh data
    await Promise.all([fetchDocument(), fetchWorkflow()])
  }
  catch (e) {
    showSnackbar(`Failed to cancel workflow: ${e.message}`, 'error')
  }
  finally {
    canceling.value = false
  }
}

function showSnackbar(text, color) {
  snackbar.value = { show: true, text, color }
}

function getSignerStatusColor(status) {
  const colors = {
    pending: 'grey',
    notified: 'info',
    viewed: 'warning',
    signed: 'success',
    declined: 'error',
  }
  return colors[status] || 'grey'
}

function getWorkflowStatusColor(status) {
  const colors = {
    PENDING: 'grey',
    IN_PROGRESS: 'info',
    COMPLETED: 'success',
    DECLINED: 'error',
    CANCELLED: 'warning',
  }
  return colors[status] || 'grey'
}
</script>

<template>
  <v-row justify="center" v-if="!loading">
    <v-col cols="12" md="8">
      <!-- Header -->
      <v-card class="mb-6">
        <v-card-item>
          <template #append>
            <v-btn variant="text" icon="mdi-close" @click="$router.back()" />
          </template>
          <v-card-title class="text-h5">
            {{ document.title }}
          </v-card-title>
          <v-card-subtitle>
            <v-chip size="small" :color="document.status === 'completed' ? 'success' : 'warning'" class="me-2">
              {{ document.status }}
            </v-chip>
            Hash: {{ document.file_hash?.substring(0, 12) }}...
          </v-card-subtitle>
        </v-card-item>

        <v-card-text class="d-flex gap-4 justify-center py-6">
          <v-btn
            prepend-icon="mdi-download"
            variant="tonal"
            :href="'/storage/' + document.file_path"
            target="_blank"
          >
            Download PDF
          </v-btn>

          <v-btn
            prepend-icon="mdi-robot"
            color="primary"
            variant="outlined"
            :loading="analyzing"
            @click="analyzeDocument"
          >
            AI Risk Scan
          </v-btn>

          <v-btn
            v-if="canCancelWorkflow && workflow?.status !== 'COMPLETED'"
            prepend-icon="mdi-cancel"
            color="error"
            variant="outlined"
            @click="showCancelDialog = true"
          >
            Cancel Workflow
          </v-btn>
        </v-card-text>
      </v-card>

      <!-- Workflow Timeline -->
      <v-card v-if="workflow && workflow.steps?.length > 0" class="mb-6">
        <v-card-item>
          <template #prepend>
            <v-avatar :color="getWorkflowStatusColor(workflow.status)">
              <v-icon>mdi-workflow</v-icon>
            </v-avatar>
          </template>

          <v-card-title>Workflow Progress</v-card-title>
          <v-card-subtitle>
            Type: {{ workflow.type }}
            <v-chip :color="getWorkflowStatusColor(workflow.status)" size="small" class="ml-2">
              {{ workflow.status }}
            </v-chip>
          </v-card-subtitle>
        </v-card-item>

        <v-card-text>
          <workflow-timeline :steps="workflow.steps" />
        </v-card-text>
      </v-card>

      <!-- Signers (Legacy fallback if no workflow) -->
      <v-card v-else-if="document.signers?.length" class="mb-6" title="Signers">
        <v-list density="compact">
          <v-list-item v-for="signer in document.signers" :key="signer.id">
            <template #prepend>
              <v-avatar :color="getSignerStatusColor(signer.status)" variant="tonal">
                <v-icon v-if="signer.status === 'signed'" icon="mdi-check" />
                <v-icon v-else-if="signer.status === 'declined'" icon="mdi-close" />
                <span v-else>{{ signer.signing_order }}</span>
              </v-avatar>
            </template>
            <v-list-item-title>{{ signer.name }}</v-list-item-title>
            <v-list-item-subtitle>{{ signer.email }}</v-list-item-subtitle>
            <template #append>
              <v-chip size="small" :color="getSignerStatusColor(signer.status)">
                {{ signer.status }}
              </v-chip>
            </template>
          </v-list-item>
        </v-list>
      </v-card>

      <!-- AI Risks -->
      <v-alert
        v-if="risks.length > 0"
        type="warning"
        variant="tonal"
        title="AI Risk Findings"
        class="mb-6"
        closable
      >
        <ul class="ms-4">
          <li v-for="(risk, i) in risks" :key="i">
            <strong>{{ risk.term }}:</strong> {{ risk.message }}
          </li>
        </ul>
      </v-alert>

      <!-- Signature Pad -->
      <v-card v-if="canSign" title="Your Signature">
        <v-card-text>
          <!-- Use Saved Signature Option -->
          <div v-if="savedSignatures.length > 0" class="mb-4">
            <v-switch v-model="useSaved" label="Use saved signature" color="primary" />
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

          <!-- Draw Signature -->
          <div v-if="!useSaved">
            <p class="text-body-2 mb-4 text-medium-emphasis">
              Please sign within the box below.
            </p>
            <div class="canvas-wrapper">
              <canvas
                ref="canvas"
                width="500"
                height="200"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseleave="stopDrawing"
              />
            </div>
          </div>
        </v-card-text>

        <v-card-actions class="justify-end px-4 pb-4">
          <v-btn v-if="!useSaved" variant="outlined" color="secondary" @click="clearCanvas">
            Clear
          </v-btn>
          <v-btn color="success" :loading="submitting" @click="submitSignature">
            Confirm Signature
          </v-btn>
        </v-card-actions>
      </v-card>

      <v-alert v-else-if="document.status !== 'completed'" type="info" variant="tonal" class="mb-6">
        Waiting for other signers or this document doesn't require your signature.
      </v-alert>

      <v-alert v-if="error" type="error" variant="tonal" class="mt-4">
        {{ error }}
      </v-alert>
    </v-col>

    <!-- Workflow Cancellation Dialog -->
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
            Cancel
          </v-btn>
          <v-btn
            color="error"
            :loading="canceling"
            :disabled="!cancelReason.trim()"
            @click="cancelWorkflow"
          >
            Confirm Cancellation
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
  </v-row>
  <div v-else class="text-center pa-10">
    <v-progress-circular indeterminate color="primary" size="64" />
    <div class="mt-4 text-body-1 text-medium-emphasis">
      Loading Document...
    </div>
  </div>
</template>

<style scoped>
.canvas-wrapper {
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  display: flex;
  justify-content: center;
  background: rgb(var(--v-theme-background));
  overflow: hidden;
}
canvas {
  background: white;
  cursor: crosshair;
}
</style>
