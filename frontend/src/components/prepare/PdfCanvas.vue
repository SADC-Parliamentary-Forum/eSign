<script setup>
/**
 * PdfCanvas.vue - PDF Viewer with Field Annotation
 * Supports draw-to-place field creation and field manipulation
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed'

const props = defineProps({
  pdfSource: {
    type: String,
    required: true
  },
  fields: {
    type: Array,
    default: () => []
  },
  selectedSigner: {
    type: Object,
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  },
  signerColors: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:fields', 'fieldCreated', 'fieldDeleted', 'pageCountChanged'])

// State
const pageCount = ref(0)
const pdfContainer = ref(null)
const isDrawing = ref(false)
const drawStart = ref({ x: 0, y: 0, page: 1 })
const drawCurrent = ref({ x: 0, y: 0 })
const selectedFieldId = ref(null)

// Field type popup state
const showFieldTypePopup = ref(false)
const popupPosition = ref({ x: 0, y: 0 })
const pendingField = ref(null)

// Field types for icons
const fieldTypeIcons = {
  'SIGNATURE': 'mdi-draw',
  'INITIALS': 'mdi-alphabetical-variant',
  'DATE': 'mdi-calendar',
  'TEXT': 'mdi-form-textbox',
  'CHECKBOX': 'mdi-checkbox-marked-outline'
}

function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
  emit('pageCountChanged', pdf.numPages)
}

// Drawing handlers
function startDrawing(e, page) {
  if (props.disabled || !props.selectedSigner) return
  
  const rect = e.currentTarget.getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100
  
  isDrawing.value = true
  drawStart.value = { x, y, page }
  drawCurrent.value = { x, y }
}

function onDrawing(e, page) {
  if (!isDrawing.value || page !== drawStart.value.page) return
  
  const rect = e.currentTarget.getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100
  
  drawCurrent.value = { x, y }
}

function endDrawing(e) {
  if (!isDrawing.value) return
  
  isDrawing.value = false
  
  // Calculate rectangle dimensions
  const minX = Math.min(drawStart.value.x, drawCurrent.value.x)
  const minY = Math.min(drawStart.value.y, drawCurrent.value.y)
  const width = Math.abs(drawCurrent.value.x - drawStart.value.x)
  const height = Math.abs(drawCurrent.value.y - drawStart.value.y)
  
  // Minimum size check (at least 3% width and 2% height)
  if (width < 3 || height < 2) {
    return
  }
  
  // Store pending field and show type selector
  pendingField.value = {
    x: minX,
    y: minY,
    width,
    height,
    page: drawStart.value.page
  }
  
  // Position popup near the drawn area
  popupPosition.value = {
    x: e.clientX,
    y: e.clientY
  }
  
  showFieldTypePopup.value = true
}

function onFieldTypeSelected(type) {
  if (!pendingField.value || !props.selectedSigner) return
  
  const newField = {
    id: crypto.randomUUID(),
    document_id: null, // Will be set by parent
    type,
    page_number: pendingField.value.page,
    x: pendingField.value.x,
    y: pendingField.value.y,
    width: pendingField.value.width,
    height: pendingField.value.height,
    signer_email: props.selectedSigner.email,
    document_signer_id: props.selectedSigner.id,
    signer_color: props.selectedSigner.color,
    required: true,
    label: null
  }
  
  const updatedFields = [...props.fields, newField]
  emit('update:fields', updatedFields)
  emit('fieldCreated', newField)
  
  pendingField.value = null
  showFieldTypePopup.value = false
}

function onFieldTypeCancelled() {
  pendingField.value = null
  showFieldTypePopup.value = false
}

function selectField(field) {
  selectedFieldId.value = field.id
}

function deleteField(fieldId) {
  const updatedFields = props.fields.filter(f => f.id !== fieldId)
  emit('update:fields', updatedFields)
  emit('fieldDeleted', fieldId)
  selectedFieldId.value = null
}

function getFieldsByPage(page) {
  return props.fields.filter(f => f.page_number === page)
}

function getFieldColor(field) {
  // Get color from signer or use default
  if (field.signer_color) {
    return field.signer_color
  }
  return { bg: '#FFF9C4', border: '#FBC02D', text: '#F57F17' }
}

// Handle keyboard delete
function handleKeydown(e) {
  if (e.key === 'Delete' && selectedFieldId.value) {
    deleteField(selectedFieldId.value)
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

// Computed for drawing rectangle preview
const drawingRect = computed(() => {
  if (!isDrawing.value) return null
  
  const minX = Math.min(drawStart.value.x, drawCurrent.value.x)
  const minY = Math.min(drawStart.value.y, drawCurrent.value.y)
  const width = Math.abs(drawCurrent.value.x - drawStart.value.x)
  const height = Math.abs(drawCurrent.value.y - drawStart.value.y)
  
  return {
    left: minX + '%',
    top: minY + '%',
    width: width + '%',
    height: height + '%',
    page: drawStart.value.page
  }
})
</script>

<template>
  <div class="pdf-canvas" ref="pdfContainer">
    <!-- PDF Pages -->
    <div 
      v-for="page in pageCount" 
      :key="page" 
      class="pdf-page mb-4 elevation-3 position-relative bg-white"
    >
      <!-- PDF Render Layer -->
      <VuePdfEmbed 
        :source="pdfSource" 
        :page="page"
        width="800"
        @loaded="handleDocumentLoad"
        class="pdf-layer"
      />
      
      <!-- Interactive Overlay Layer -->
      <div 
        class="field-overlay position-absolute"
        :class="{ 'drawing-mode': !disabled && selectedSigner }"
        @mousedown="startDrawing($event, page)"
        @mousemove="onDrawing($event, page)"
        @mouseup="endDrawing"
        @mouseleave="isDrawing = false"
      >
        <!-- Existing Fields -->
        <div
          v-for="field in getFieldsByPage(page)"
          :key="field.id"
          class="field-box position-absolute"
          :class="{ 
            'field-selected': selectedFieldId === field.id,
            'field-interactive': !disabled
          }"
          :style="{
            left: field.x + '%',
            top: field.y + '%',
            width: field.width + '%',
            height: field.height + '%',
            backgroundColor: getFieldColor(field).bg,
            borderColor: getFieldColor(field).border,
            color: getFieldColor(field).text
          }"
          @click.stop="selectField(field)"
        >
          <div class="field-content d-flex align-center justify-center h-100">
            <v-icon :icon="fieldTypeIcons[field.type]" size="16" class="mr-1" />
            <span class="field-label text-caption font-weight-medium">
              {{ field.type }}
            </span>
          </div>
          
          <!-- Delete Button -->
          <v-btn
            v-if="selectedFieldId === field.id && !disabled"
            icon="mdi-delete"
            size="x-small"
            color="error"
            variant="flat"
            class="delete-btn"
            @click.stop="deleteField(field.id)"
          />
        </div>
        
        <!-- Drawing Preview Rectangle -->
        <div
          v-if="drawingRect && drawingRect.page === page"
          class="drawing-preview position-absolute"
          :style="{
            left: drawingRect.left,
            top: drawingRect.top,
            width: drawingRect.width,
            height: drawingRect.height,
            borderColor: selectedSigner?.color?.border || '#1976D2'
          }"
        />
      </div>

      <!-- Page Number -->
      <div class="page-number text-caption text-medium-emphasis">
        Page {{ page }} of {{ pageCount }}
      </div>
    </div>

    <!-- Field Type Selector Popup -->
    <v-dialog
      v-model="showFieldTypePopup"
      max-width="280"
      :style="{ position: 'fixed', left: popupPosition.x + 'px', top: popupPosition.y + 'px' }"
    >
      <v-card class="field-type-dialog">
        <v-card-title class="text-subtitle-1 pb-1 d-flex align-center">
          <v-icon icon="mdi-form-select" class="mr-2" size="20" />
          Select Field Type
        </v-card-title>
        
        <v-divider />

        <v-list density="compact">
          <v-list-item
            v-for="(icon, type) in fieldTypeIcons"
            :key="type"
            :prepend-icon="icon"
            @click="onFieldTypeSelected(type)"
            class="field-type-option"
          >
            <v-list-item-title>{{ type }}</v-list-item-title>
          </v-list-item>
        </v-list>

        <v-divider />

        <v-card-actions>
          <v-btn block variant="text" @click="onFieldTypeCancelled">
            Cancel
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- No Signer Selected Hint -->
    <div 
      v-if="!disabled && !selectedSigner && pageCount > 0"
      class="no-signer-hint"
    >
      <v-alert type="info" variant="tonal" density="compact">
        <v-icon icon="mdi-information" class="mr-2" />
        Select a signer from the left panel to start placing signature fields
      </v-alert>
    </div>
  </div>
</template>

<style scoped>
.pdf-canvas {
  display: inline-block;
}

.pdf-page {
  width: 800px;
  min-height: 1000px;
  position: relative;
  border-radius: 4px;
  overflow: hidden;
}

.pdf-layer {
  display: block;
}

.field-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 10;
}

.field-overlay.drawing-mode {
  cursor: crosshair;
}

.field-box {
  border: 2px solid;
  border-radius: 4px;
  transition: all 0.15s ease;
  cursor: pointer;
  overflow: hidden;
}

.field-box.field-interactive:hover {
  transform: scale(1.02);
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.field-box.field-selected {
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.3);
}

.field-content {
  padding: 2px 4px;
  text-align: center;
}

.field-label {
  text-transform: capitalize;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.delete-btn {
  position: absolute;
  top: -8px;
  right: -8px;
  z-index: 20;
}

.drawing-preview {
  border: 2px dashed;
  background-color: rgba(25, 118, 210, 0.1);
  pointer-events: none;
  border-radius: 4px;
}

.page-number {
  position: absolute;
  bottom: 8px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255,255,255,0.9);
  padding: 2px 12px;
  border-radius: 12px;
  z-index: 5;
}

.no-signer-hint {
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 100;
  max-width: 400px;
}

.field-type-dialog {
  border-radius: 12px;
}

.field-type-option {
  border-radius: 8px;
  margin: 2px 4px;
}

.field-type-option:hover {
  background-color: rgba(var(--v-theme-primary), 0.1);
}
</style>
