<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { VSnackbar } from 'vuetify/components'

const route = useRoute()
const router = useRouter()

const document = ref({})
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const canvas = ref(null)
let ctx = null
let isDrawing = false

// Saved signatures
const savedSignatures = ref([])
const selectedSignatureId = ref(null)
const useSaved = ref(false)

const risks = ref([])
const analyzing = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' })

// Check if current user can sign
const canSign = computed(() => {
  if (!document.value.signers) return false
  const currentSigner = document.value.signers.find(s => s.can_sign)
  return !!currentSigner
})

onMounted(async () => {
  await Promise.all([fetchDocument(), fetchSavedSignatures()])
  initCanvas()
})

async function fetchDocument() {
  try {
    const res = await $api(`/documents/${route.params.id}`)
    document.value = res
  } catch (e) {
    error.value = 'Failed to load document'
  } finally {
    loading.value = false
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
  } catch (e) {
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
  const rect = canvas.value.getBoundingClientRect()
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
  } catch (e) {
    showSnackbar('Analysis failed: ' + (e.message || 'Unknown error'), 'error')
  } finally {
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
    } catch (e) {
      error.value = 'Failed to load saved signature'
      submitting.value = false
      return
    }
  } else {
    signatureData = canvas.value.toDataURL('image/png')
  }
  
  try {
    await $api(`/documents/${document.value.id}/sign`, {
      method: 'POST',
      body: { 
        signature_data: signatureData,
        user_signature_id: useSaved.value ? selectedSignatureId.value : null
      }
    })
    
    showSnackbar('Document signed successfully!', 'success')
    setTimeout(() => router.push('/'), 1500)
  } catch (e) {
    error.value = e.message || 'Signature failed'
  } finally {
    submitting.value = false
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
    declined: 'error'
  }
  return colors[status] || 'grey'
}
</script>

<template>
  <VRow justify="center" v-if="!loading">
     <VCol cols="12" md="8">
        <!-- Header -->
        <VCard class="mb-6">
           <VCardItem>
              <template #append>
                 <VBtn variant="text" icon="ri-close-line" @click="$router.back()" />
              </template>
              <VCardTitle class="text-h5">{{ document.title }}</VCardTitle>
              <VCardSubtitle>
                <VChip size="small" :color="document.status === 'completed' ? 'success' : 'warning'" class="me-2">
                  {{ document.status }}
                </VChip>
                Hash: {{ document.file_hash?.substring(0, 12) }}...
              </VCardSubtitle>
           </VCardItem>
           
           <VCardText class="d-flex gap-4 justify-center py-6">
              <VBtn 
                prepend-icon="ri-download-line" 
                variant="tonal" 
                :href="'/storage/' + document.file_path" 
                target="_blank"
              >
                Download PDF
              </VBtn>
              
              <VBtn 
                prepend-icon="ri-magic-line" 
                color="primary" 
                variant="outlined"
                :loading="analyzing" 
                @click="analyzeDocument"
              >
                AI Risk Scan
              </VBtn>
           </VCardText>
        </VCard>

        <!-- Signers -->
        <VCard v-if="document.signers?.length" class="mb-6" title="Signers">
          <VList density="compact">
            <VListItem v-for="signer in document.signers" :key="signer.id">
              <template #prepend>
                <VAvatar :color="getSignerStatusColor(signer.status)" variant="tonal">
                  <VIcon v-if="signer.status === 'signed'" icon="ri-check-line" />
                  <VIcon v-else-if="signer.status === 'declined'" icon="ri-close-line" />
                  <span v-else>{{ signer.signing_order }}</span>
                </VAvatar>
              </template>
              <VListItemTitle>{{ signer.name }}</VListItemTitle>
              <VListItemSubtitle>{{ signer.email }}</VListItemSubtitle>
              <template #append>
                <VChip size="small" :color="getSignerStatusColor(signer.status)">
                  {{ signer.status }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </VCard>

        <!-- AI Risks -->
        <VAlert 
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
        </VAlert>

        <!-- Signature Pad -->
        <VCard v-if="canSign" title="Your Signature">
           <VCardText>
              <!-- Use Saved Signature Option -->
              <div v-if="savedSignatures.length > 0" class="mb-4">
                <VSwitch v-model="useSaved" label="Use saved signature" color="primary" />
                <VSelect 
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
                <p class="text-body-2 mb-4 text-medium-emphasis">Please sign within the box below.</p>
                <div class="canvas-wrapper">
                   <canvas ref="canvas" width="500" height="200" 
                      @mousedown="startDrawing" 
                      @mousemove="draw" 
                      @mouseup="stopDrawing" 
                      @mouseleave="stopDrawing">
                   </canvas>
                </div>
              </div>
           </VCardText>
           
           <VCardActions class="justify-end px-4 pb-4">
              <VBtn v-if="!useSaved" variant="outlined" color="secondary" @click="clearCanvas">Clear</VBtn>
              <VBtn color="success" :loading="submitting" @click="submitSignature">Confirm Signature</VBtn>
           </VCardActions>
        </VCard>

        <VAlert v-else-if="document.status !== 'completed'" type="info" variant="tonal" class="mb-6">
          Waiting for other signers or this document doesn't require your signature.
        </VAlert>

        <VAlert v-if="error" type="error" variant="tonal" class="mt-4">{{ error }}</VAlert>
     </VCol>

     <VSnackbar v-model="snackbar.show" :color="snackbar.color">
       {{ snackbar.text }}
       <template #actions>
         <VBtn variant="text" @click="snackbar.show = false">Close</VBtn>
       </template>
     </VSnackbar>
  </VRow>
  <div v-else class="text-center pa-10">
     <VProgressCircular indeterminate color="primary" size="64" />
     <div class="mt-4 text-body-1 text-disabled">Loading Document...</div>
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

