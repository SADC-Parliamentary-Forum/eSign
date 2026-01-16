<script setup>
import { VuePdfEmbed } from 'vue-pdf-embed'
import { useDraggable } from '@vueuse/core'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

// State
const document = ref(null)
const signers = ref([])
const fields = ref([])
const loading = ref(true)
const saving = ref(false)
const pdfSource = ref(null)
const pageCount = ref(0)
const selectedSigner = ref(null)

// Dragging state
const draggingType = ref(null)

const fieldTypes = [
  { type: 'SIGNATURE', icon: 'mdi-draw', label: 'Signature' },
  { type: 'INITIALS', icon: 'mdi-alphabetical-variant', label: 'Initials' },
  { type: 'DATE', icon: 'mdi-calendar', label: 'Date Signed' },
  { type: 'TEXT', icon: 'mdi-form-textbox', label: 'Text Box' },
  { type: 'CHECKBOX', icon: 'mdi-checkbox-marked', label: 'Checkbox' },
]

onMounted(async () => {
  await Promise.all([
    fetchDocument(),
    fetchSigners(),
    fetchFields(),
  ])
  if (signers.value.length > 0) {
    selectedSigner.value = signers.value[0]
  }
})

async function fetchDocument() {
  try {
    const res = await $api(`/documents/${route.params.id}`)
    document.value = res
    pdfSource.value = `/storage/${res.file_path}` // Ensure this is accessible
  } catch (e) {
    console.error('Failed to load document', e)
  }
}

async function fetchSigners() {
  try {
    const res = await $api(`/documents/${route.params.id}`)
    // document endpoint returns signers relationship usually
    if (res.signers) {
      signers.value = res.signers
    }
  } catch (e) {
    console.error('Failed to load signers', e)
  }
}

async function fetchFields() {
  try {
    const res = await $api(`/documents/${route.params.id}/fields`)
    fields.value = res
  } catch (e) {
    console.error('Failed to load fields', e)
  } finally {
    loading.value = false
  }
}

// PDF events
function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
}

// Drag & Drop
function onDragStart(event, type) {
  if (!selectedSigner.value) {
    event.preventDefault()
    alert('Please select a signer first')
    return
  }
  draggingType.value = type
  event.dataTransfer.effectAllowed = 'copy'
}

function onDrop(event, pageNumber) {
  event.preventDefault()
  const type = draggingType.value
  if (!type) return

  // Calculate coordinates
  // The drop target is the overlay div, which matches PDF page size
  const rect = event.target.getBoundingClientRect()
  const x = event.clientX - rect.left
  const y = event.clientY - rect.top
  
  // Convert to percentage
  const xPercent = (x / rect.width) * 100
  const yPercent = (y / rect.height) * 100

  // Add field
  fields.value.push({
    id: crypto.randomUUID(), // Temp ID
    document_id: document.value.id,
    type,
    page_number: pageNumber,
    x: xPercent,
    y: yPercent,
    width: 20, // Default width %
    height: 5,  // Default height %
    signer_email: selectedSigner.value.email,
    document_signer_id: selectedSigner.value.id,
    required: true,
  })
  
  draggingType.value = null
}

function removeField(index) {
  fields.value.splice(index, 1)
}

async function saveAndSend() {
  saving.value = true
  try {
    // 1. Save Fields
    await $api(`/documents/${document.value.id}/fields`, {
      method: 'POST',
      body: { 
        fields: fields.value.map(f => ({
          ...f,
          // Ensure numbers
          x: Number(f.x),
          y: Number(f.y),
          width: Number(f.width),
          height: Number(f.height),
        }))   
      }
    })

    // 2. Send (if not already sent, but usually 'Send' logic is separate)
    // The previous flow had "Send" as separate step. Here we just Save.
    // If the user came from upload, they probably want to "Send" now.
    
    // We can call the send endpoint if this is the final step
    await $api(`/documents/${document.value.id}/send`, {
      method: 'POST',
      body: {
        sequential: document.value.sequential_signing || false, // Should be part of doc state or form
        expires_in_days: 30
      }
    })

    router.push('/')
  } catch (e) {
    console.error('Failed to save', e)
    alert(e.message || 'Failed to save configuration')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="h-100 d-flex flex-column">
    <!-- Header -->
    <v-toolbar density="compact" color="surface" elevation="1" class="px-4">
      <v-btn icon="mdi-arrow-left" @click="$router.back()" />
      <v-toolbar-title class="text-subtitle-1 font-weight-bold">
        Prepare: {{ document?.title }}
      </v-toolbar-title>
      <v-spacer />
      <v-btn
        color="primary"
        variant="elevated"
        :loading="saving"
        @click="saveAndSend"
      >
        Send Document
      </v-btn>
    </v-toolbar>

    <div class="d-flex flex-grow-1 overflow-hidden">
      <!-- Sidebar -->
      <div class="sidebar border-e bg-surface">
        <div class="pa-4">
          <div class="text-overline mb-2">Signers</div>
          <v-select
            v-model="selectedSigner"
            :items="signers"
            item-title="name"
            item-value="id"
            return-object
            variant="outlined"
            density="compact"
            hide-details
            class="mb-4"
          >
            <template #item="{ props, item }">
               <v-list-item v-bind="props" :subtitle="item.raw.role || 'Signer'" />
            </template>
          </v-select>

          <v-divider class="mb-4" />
          
          <div class="text-overline mb-2">Fields</div>
          <div class="d-flex flex-column gap-2">
            <div
              v-for="type in fieldTypes"
              :key="type.type"
              class="field-item pa-3 border rounded cursor-move bg-surface-variant"
              draggable="true"
              @dragstart="onDragStart($event, type.type)"
            >
              <v-icon :icon="type.icon" class="mr-2" />
              {{ type.label }}
            </div>
          </div>
        </div>
      </div>

      <!-- PDF Canvas -->
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
              <!-- PDF Layer -->
              <VuePdfEmbed 
                :source="pdfSource" 
                :page="page"
                width="800"
                @loaded="handleDocumentLoad"
              />
              
              <!-- Drop Zone Layer -->
              <div 
                class="drop-zone position-absolute top-0 left-0 w-100 h-100"
                @drop="onDrop($event, page)"
                @dragover.prevent
              >
                 <!-- Placed Fields -->
                 <div
                   v-for="(field, idx) in fields.filter(f => f.page_number === page)"
                   :key="field.id || idx"
                   class="placed-field position-absolute"
                   :style="{
                      left: field.x + '%',
                      top: field.y + '%',
                      width: field.width + '%',
                      height: field.height + '%',
                      backgroundColor: 'rgba(255, 255, 0, 0.3)',
                      border: '2px solid orange'
                   }"
                 >
                    <div class="d-flex w-100 h-100 align-center justify-center text-caption font-weight-bold">
                       <v-icon size="small" class="mr-1">
                          {{ fieldTypes.find(t => t.type === field.type)?.icon }}
                       </v-icon>
                       {{ field.type }}
                       <v-btn 
                          icon="mdi-close" 
                          size="x-small" 
                          variant="flat" 
                          color="error"
                          class="position-absolute top-0 right-0 ma-n2"
                          @click.stop="removeField(fields.indexOf(field))"
                       />
                    </div>
                 </div>
              </div>
           </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sidebar {
  width: 300px;
  flex-shrink: 0;
  z-index: 10;
}

.field-item {
  cursor: grab;
  transition: all 0.2s;
}
.field-item:hover {
  background-color: rgb(var(--v-theme-primary));
  color: white;
}

.placed-field {
  cursor: pointer;
  z-index: 5;
}
.placed-field:hover {
  background-color: rgba(255, 255, 0, 0.5) !important;
}
</style>
