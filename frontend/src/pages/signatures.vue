<script setup>
import { onMounted, ref, watch, nextTick } from 'vue'

const signatures = ref([])
const loading = ref(true)
const showDialog = ref(false)
const saving = ref(false)
const canvas = ref(null)
const activeTab = ref('draw')
const typeText = ref('')
const selectedFont = ref('Dancing Script')
const uploadedImage = ref(null)

const fonts = ['Dancing Script', 'Great Vibes', 'Sacramento', 'Parisienne', 'Allura']

let ctx = null
let isDrawing = false

const newSignature = ref({
  type: 'signature',
  name: '',
})

onMounted(async () => {
  await fetchSignatures()
})

async function fetchSignatures() {
  loading.value = true
  try {
    const res = await $api('/signatures/mine')

    signatures.value = Array.isArray(res) ? res : (res.data || [])
  } catch (e) {
    console.error('Failed to fetch signatures', e)
  } finally {
    loading.value = false
  }
}

const editingId = ref(null)
const currentSignatureImage = ref(null)

function openCreateDialog(type = 'signature') {
  editingId.value = null
  currentSignatureImage.value = null
  newSignature.value = { type, name: type === 'signature' ? 'My Signature' : 'My Initials' }
  typeText.value = ''
  uploadedImage.value = null
  activeTab.value = 'draw'
  showDialog.value = true
  nextTick(() => {
    if (activeTab.value === 'draw') initCanvas()
  })
}

function openEditDialog(sig) {
  editingId.value = sig.id
  currentSignatureImage.value = sig.image_data // Store for preview
  newSignature.value = { ...sig } // Clone data
  
  // Try to determine the initial tab based on method
  if (sig.method === 'UPLOADED') {
    activeTab.value = 'upload'
    uploadedImage.value = sig.image_data
  } else if (sig.method === 'TYPED') {
    activeTab.value = 'type'

    // We don't have the original text, so leave blank or try to infer?
    // Just leave blank for now, user can re-type.
    typeText.value = sig.name !== 'My Signature' && sig.name !== 'My Initials' ? sig.name : ''
  } else {
    activeTab.value = 'draw'

    // Can't put image back on canvas easily for editing
  }

  showDialog.value = true
  nextTick(() => {
    if (activeTab.value === 'draw') initCanvas()
  })
}

// Watch tab change to init canvas
watch(activeTab, val => {
  if (val === 'draw') {
    nextTick(initCanvas)
  }
})

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
  ctx.closePath()
}

function clearCanvas() {
  if (ctx) {
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, canvas.value.width, canvas.value.height)
    ctx.strokeStyle = '#000'
  }
}

function handleFileUpload(e) {
  const file = e.target.files[0]
  if (!file) return

  const reader = new FileReader()

  reader.onload = e => {
    uploadedImage.value = e.target.result
  }
  reader.readAsDataURL(file)
}

function generateTypeSignature() {
  const tCanvas = document.createElement('canvas')

  tCanvas.width = 450
  tCanvas.height = 150

  const tCtx = tCanvas.getContext('2d')
  
  // White background
  tCtx.fillStyle = '#fff'
  tCtx.fillRect(0, 0, tCanvas.width, tCanvas.height)
  
  // Text
  tCtx.font = `48px "${selectedFont.value}"`
  tCtx.fillStyle = '#000'
  tCtx.textAlign = 'center'
  tCtx.textBaseline = 'middle'
  tCtx.fillText(typeText.value || newSignature.value.name, tCanvas.width / 2, tCanvas.height / 2)
  
  return tCanvas.toDataURL('image/png')
}

async function saveSignature() {
  saving.value = true
  try {
    let imageData = null
    let method = 'DRAWN'

    // Determine data based on active tab
    if (activeTab.value === 'draw') {
      // If editing and canvas is empty (user didn't redraw), keep existing?
      // But canvas init clears it. So user MUST redraw if they are in Draw tab.
      // We can check if canvas is blank? Hard to tell reliably. 
      // Assume if they are in Draw tab, they want to save what's on canvas.
      imageData = canvas.value.toDataURL('image/png')
      method = 'DRAWN'
    } else if (activeTab.value === 'upload') {
      if (!uploadedImage.value) {
        if (!editingId.value) {
          alert('Please upload an image first')
          saving.value = false
          
          return
        }

        // If editing and no new upload, keep existing? 
        // But uploadedImage variable holds the preview. 
        // Logic in openEditDialog sets uploadedImage = sig.image_data.
        // So uploadedImage should be set.
      }
      imageData = uploadedImage.value
      method = 'UPLOADED'
    } else if (activeTab.value === 'type') {
      imageData = generateTypeSignature()
      method = 'TYPED'
    }

    const payload = {
      name: newSignature.value.name,
      image_data: imageData,
      method: method,
    }

    if (!editingId.value) {
      // CREATE
      payload.type = newSignature.value.type
      payload.is_default = signatures.value.filter(s => s.type === newSignature.value.type).length === 0
        
      await $api('/signatures/mine', {
        method: 'POST',
        body: payload,
      })
    } else {
      // UPDATE
      await $api(`/signatures/mine/${editingId.value}`, {
        method: 'PUT',
        body: payload,
      })
    }

    showDialog.value = false
    await fetchSignatures()
  } catch (e) {
    console.error('Failed to save signature', e)
    alert('Failed to save: ' + (e.message || 'Unknown error'))
  } finally {
    saving.value = false
  }
}

async function deleteSignature(id) {
  if (!confirm('Delete this signature?')) return
  try {
    await $api(`/signatures/mine/${id}`, { method: 'DELETE' })
    await fetchSignatures()
  } catch (e) {
    console.error('Failed to delete', e)
  }
}

async function setDefault(id) {
  try {
    await $api(`/signatures/mine/${id}/default`, { method: 'PATCH' })
    await fetchSignatures()
  } catch (e) {
    console.error('Failed to set default', e)
  }
}

async function viewSignature(id) {
  // Now redundant as clicking card opens edit, but we can keep it
  const sig = signatures.value.find(s => s.id === id)
  if (sig) openEditDialog(sig)
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h2 class="text-h4 font-weight-bold">
          My Signatures
        </h2>
        <div class="text-body-1 text-disabled">
          Manage your saved signatures and initials
        </div>
      </div>
      <div class="d-flex gap-2">
        <VBtn
          variant="outlined"
          prepend-icon="ri-pen-nib-line"
          @click="openCreateDialog('initials')"
        >
          Add Initials
        </VBtn>
        <VBtn
          prepend-icon="ri-edit-line"
          @click="openCreateDialog('signature')"
        >
          Add Signature
        </VBtn>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="loading"
      class="text-center py-10"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>

    <!-- Empty State -->
    <VCard
      v-else-if="signatures.length === 0"
      class="text-center py-10"
    >
      <VCardText>
        <VIcon
          icon="ri-edit-2-line"
          size="64"
          color="disabled"
          class="mb-4"
        />
        <h3 class="text-h6 mb-2">
          No Saved Signatures
        </h3>
        <p class="text-body-2 text-disabled mb-4">
          Create your first signature to speed up document signing.
        </p>
        <VBtn @click="openCreateDialog('signature')">
          Create Signature
        </VBtn>
      </VCardText>
    </VCard>

    <!-- Signature List -->
    <VRow v-else>
      <VCol
        v-for="sig in signatures"
        :key="sig.id"
        cols="12"
        md="6"
        lg="4"
      >
        <VCard
          class="cursor-pointer hover-card"
          @click="openEditDialog(sig)"
        >
          <VCardItem>
            <template #prepend>
              <div
                class="d-flex align-center justify-center rounded bg-grey-lighten-4 pa-2 me-4"
                style="width: 80px; height: 50px; background-color: #f5f5f5;"
              >
                <img
                  :src="sig.image_data"
                  style="max-width: 100%; max-height: 100%; object-fit: contain;"
                >
              </div>
            </template>
            <div class="d-flex flex-column">
              <VCardTitle class="pb-1">
                {{ sig.name }}
              </VCardTitle>
              <VCardSubtitle>
                <VChip
                  size="x-small"
                  :color="sig.type === 'signature' ? 'primary' : 'secondary'"
                  class="me-2"
                >
                  {{ sig.type }}
                </VChip>
                <VChip
                  v-if="sig.is_default"
                  size="x-small"
                  color="success"
                >
                  Default
                </VChip>
              </VCardSubtitle>
            </div>
            <template #append>
              <VMenu>
                <template #activator="{ props }">
                  <VBtn
                    icon="ri-more-2-line"
                    variant="text"
                    v-bind="props"
                    @click.stop
                  />
                </template>
                <VList density="compact">
                  <VListItem
                    prepend-icon="ri-pencil-line"
                    @click="openEditDialog(sig)"
                  >
                    Edit
                  </VListItem>
                  <VListItem
                    v-if="!sig.is_default"
                    prepend-icon="ri-star-line"
                    @click="setDefault(sig.id)"
                  >
                    Set as Default
                  </VListItem>
                  <VListItem
                    prepend-icon="ri-delete-bin-line"
                    class="text-error"
                    @click="deleteSignature(sig.id)"
                  >
                    Delete
                  </VListItem>
                </VList>
              </VMenu>
            </template>
          </VCardItem>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create/Edit Dialog -->
    <VDialog
      v-model="showDialog"
      max-width="600"
    >
      <VCard>
        <VCardTitle class="pt-4 d-flex justify-space-between align-center">
          <span>{{ editingId ? 'Edit' : 'Create' }} {{ newSignature.type === 'signature' ? 'Signature' : 'Initials' }}</span>
          <VBtn
            icon="ri-close-line"
            variant="text"
            @click="showDialog = false"
          />
        </VCardTitle>
        
        <VCardText>
          <VTextField
            v-model="newSignature.name"
            label="Name / Label"
            variant="outlined"
            class="mb-4"
          />

          <div
            v-if="editingId && currentSignatureImage"
            class="mb-4"
          >
            <div class="text-caption text-medium-emphasis mb-1">
              Current Signature:
            </div>
            <div class="border rounded bg-grey-lighten-5 pa-4 d-flex justify-center align-center">
              <img
                :src="currentSignatureImage"
                style="max-height: 100px; max-width: 100%; object-fit: contain;"
              >
            </div>
          </div>

          <VTabs
            v-model="activeTab"
            grow
            color="primary"
            class="mb-4"
          >
            <VTab value="draw">
              Draw
            </VTab>
            <VTab value="type">
              Type
            </VTab>
            <VTab value="upload">
              Upload
            </VTab>
          </VTabs>

          <VWindow v-model="activeTab">
            <!-- DRAW TAB -->
            <VWindowItem value="draw">
              <div class="canvas-wrapper">
                <canvas 
                  ref="canvas" 
                  width="550" 
                  height="200"
                  @mousedown="startDrawing"
                  @mousemove="draw"
                  @mouseup="stopDrawing"
                  @mouseleave="stopDrawing"
                  @touchstart="startDrawing"
                  @touchmove="draw"
                  @touchend="stopDrawing"
                />
              </div>
              <div class="d-flex justify-between align-center mt-2">
                <span
                  v-if="editingId"
                  class="text-caption text-warning"
                >Drawing will replace existing image</span>
                <VBtn
                  size="small"
                  variant="text"
                  color="error"
                  class="ms-auto"
                  @click="clearCanvas"
                >
                  Clear
                </VBtn>
              </div>
            </VWindowItem>

            <!-- TYPE TAB -->
            <VWindowItem value="type">
              <VTextField 
                v-model="typeText" 
                label="Type your name" 
                variant="outlined" 
                class="mb-4" 
                placeholder="John Doe"
              />
              <div class="mb-2 text-subtitle-2">
                Select Style:
              </div>
              <div class="d-flex flex-wrap gap-2 mb-4">
                <VChip 
                  v-for="font in fonts" 
                  :key="font"
                  :color="selectedFont === font ? 'primary' : undefined"
                  variant="outlined"
                  label
                  @click="selectedFont = font"
                >
                  {{ font }}
                </VChip>
              </div>
              
              <div class="preview-box d-flex align-center justify-center">
                <span :style="{ fontFamily: selectedFont, fontSize: '48px' }">
                  {{ typeText || 'Preview Signature' }}
                </span>
              </div>
            </VWindowItem>

            <!-- UPLOAD TAB -->
            <VWindowItem value="upload">
              <VFileInput 
                accept="image/*" 
                label="Upload Image" 
                prepend-icon="ri-upload-cloud-line"
                @change="handleFileUpload"
              />
              <div
                v-if="uploadedImage"
                class="mt-4 preview-box d-flex align-center justify-center"
              >
                <img
                  :src="uploadedImage"
                  alt="Preview"
                  style="max-height: 150px; max-width: 100%;"
                >
              </div>
            </VWindowItem>
          </VWindow>
        </VCardText>
        <VCardActions class="justify-end pa-4">
          <VBtn
            variant="outlined"
            @click="showDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="saveSignature"
          >
            {{ editingId ? 'Update' : 'Save' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.canvas-wrapper {
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: white;
  overflow: hidden;
}
canvas {
  display: block;
  cursor: crosshair;
  touch-action: none;
}
.preview-box {
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  min-height: 150px;
  padding: 1rem;
  background: #f9f9f9;
}
.hover-card:hover {
  background-color: rgb(var(--v-theme-surface-variant), 0.1);
}
</style>
