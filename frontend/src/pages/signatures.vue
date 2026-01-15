<script setup>
import { onMounted, ref } from 'vue'

const signatures = ref([])
const loading = ref(true)
const showDialog = ref(false)
const saving = ref(false)
const canvas = ref(null)
let ctx = null
let isDrawing = false

const newSignature = ref({
  type: 'signature',
  name: ''
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

function openCreateDialog(type = 'signature') {
  newSignature.value = { type, name: type === 'signature' ? 'My Signature' : 'My Initials' }
  showDialog.value = true
  setTimeout(initCanvas, 100)
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
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.value.width, canvas.value.height)
  ctx.strokeStyle = '#000'
}

async function saveSignature() {
  saving.value = true
  try {
    const imageData = canvas.value.toDataURL('image/png')
    await $api('/signatures/mine', {
      method: 'POST',
      body: {
        type: newSignature.value.type,
        name: newSignature.value.name,
        image_data: imageData,
        is_default: signatures.value.filter(s => s.type === newSignature.value.type).length === 0
      }
    })
    showDialog.value = false
    await fetchSignatures()
  } catch (e) {
    console.error('Failed to save signature', e)
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
  try {
    const res = await $api(`/signatures/mine/${id}`)
    // Could show in a dialog
    window.open(res.image_data, '_blank')
  } catch (e) {
    console.error('Failed to view', e)
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h2 class="text-h4 font-weight-bold">My Signatures</h2>
        <div class="text-body-1 text-disabled">Manage your saved signatures and initials</div>
      </div>
      <div class="d-flex gap-2">
        <VBtn variant="outlined" prepend-icon="ri-pen-nib-line" @click="openCreateDialog('initials')">
          Add Initials
        </VBtn>
        <VBtn prepend-icon="ri-edit-line" @click="openCreateDialog('signature')">
          Add Signature
        </VBtn>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-10">
      <VProgressCircular indeterminate color="primary" />
    </div>

    <!-- Empty State -->
    <VCard v-else-if="signatures.length === 0" class="text-center py-10">
      <VCardText>
        <VIcon icon="ri-edit-2-line" size="64" color="disabled" class="mb-4" />
        <h3 class="text-h6 mb-2">No Saved Signatures</h3>
        <p class="text-body-2 text-disabled mb-4">Create your first signature to speed up document signing.</p>
        <VBtn @click="openCreateDialog('signature')">Create Signature</VBtn>
      </VCardText>
    </VCard>

    <!-- Signature List -->
    <VRow v-else>
      <VCol v-for="sig in signatures" :key="sig.id" cols="12" md="6" lg="4">
        <VCard>
          <VCardItem>
            <template #prepend>
              <VAvatar :color="sig.type === 'signature' ? 'primary' : 'secondary'" variant="tonal">
                <VIcon :icon="sig.type === 'signature' ? 'ri-edit-line' : 'ri-pen-nib-line'" />
              </VAvatar>
            </template>
            <VCardTitle>{{ sig.name }}</VCardTitle>
            <VCardSubtitle>
              <VChip size="x-small" :color="sig.type === 'signature' ? 'primary' : 'secondary'" class="me-2">
                {{ sig.type }}
              </VChip>
              <VChip v-if="sig.is_default" size="x-small" color="success">Default</VChip>
            </VCardSubtitle>
            <template #append>
              <VMenu>
                <template #activator="{ props }">
                  <VBtn icon="ri-more-2-line" variant="text" v-bind="props" />
                </template>
                <VList density="compact">
                  <VListItem prepend-icon="ri-eye-line" @click="viewSignature(sig.id)">View</VListItem>
                  <VListItem v-if="!sig.is_default" prepend-icon="ri-star-line" @click="setDefault(sig.id)">
                    Set as Default
                  </VListItem>
                  <VListItem prepend-icon="ri-delete-bin-line" class="text-error" @click="deleteSignature(sig.id)">
                    Delete
                  </VListItem>
                </VList>
              </VMenu>
            </template>
          </VCardItem>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create Dialog -->
    <VDialog v-model="showDialog" max-width="500">
      <VCard>
        <VCardTitle class="pt-4">
          Create {{ newSignature.type === 'signature' ? 'Signature' : 'Initials' }}
        </VCardTitle>
        <VCardText>
          <VTextField
            v-model="newSignature.name"
            label="Name"
            variant="outlined"
            class="mb-4"
          />
          <p class="text-body-2 text-disabled mb-2">Draw your {{ newSignature.type }} below:</p>
          <div class="canvas-wrapper">
            <canvas 
              ref="canvas" 
              width="450" 
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
        </VCardText>
        <VCardActions class="justify-end pa-4">
          <VBtn variant="text" @click="clearCanvas">Clear</VBtn>
          <VBtn variant="outlined" @click="showDialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="saving" @click="saveSignature">Save</VBtn>
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
</style>
