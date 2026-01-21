<script setup>
import { useTemplateStore } from '@/stores/templates'
import VuePdfEmbed from 'vue-pdf-embed'

const templateStore = useTemplateStore()
const router = useRouter()

const currentStep = ref(1)
const loading = ref(false)

// Step 1: Basic Info
const templateForm = ref({
  name: '',
  description: '',
  workflow_type: 'SEQUENTIAL',
  amount_required: false,
  file: null,
  required_signature_level: 'SIMPLE',
})

// Step 2: Roles
const roles = ref([])

// Step 3: Field Mappings
const fieldMappings = ref([])
const pdfPreviewUrl = ref(null)
const pageCount = ref(0)
const selectedRoleForMapping = ref(null)
const selectedFieldIndex = ref(null)

// Drawing state
const isDrawingField = ref(false)
const drawingPage = ref(null)
const drawStart = ref({ x: 0, y: 0 })
const drawCurrent = ref({ x: 0, y: 0 })

// Drag/Resize state
const isDragging = ref(false)
const isResizing = ref(false)
const activeFieldId = ref(null)
const dragOffset = ref({ x: 0, y: 0 })

// Field type popup
const showFieldTypePopup = ref(false)
const pendingField = ref(null)

// Step 4: Financial Thresholds
const thresholds = ref([])

// Constants
const workflowTypes = [
  { value: 'SEQUENTIAL', title: 'Sequential - One signer at a time' },
  { value: 'PARALLEL', title: 'Parallel - All signers at once' },
  { value: 'MIXED', title: 'Mixed - Custom order' },
]

const availableRoles = ['FINANCE', 'HOD', 'SG', 'LEGAL', 'HR', 'PROCUREMENT']

const fieldTypes = [
  { type: 'SIGNATURE', label: 'Signature', icon: 'mdi-draw' },
  { type: 'INITIALS', label: 'Initials', icon: 'mdi-format-letter-case' },
  { type: 'DATE', label: 'Date', icon: 'mdi-calendar' },
  { type: 'TEXT', label: 'Text Box', icon: 'mdi-form-textbox' },
  { type: 'CHECKBOX', label: 'Checkbox', icon: 'mdi-checkbox-marked' },
]

// Role color helpers
const roleColors = {
  FINANCE: { bg: 'rgba(33, 150, 243, 0.25)', border: '#2196F3' },
  HOD: { bg: 'rgba(156, 39, 176, 0.25)', border: '#9C27B0' },
  SG: { bg: 'rgba(76, 175, 80, 0.25)', border: '#4CAF50' },
  LEGAL: { bg: 'rgba(255, 152, 0, 0.25)', border: '#FF9800' },
  HR: { bg: 'rgba(233, 30, 99, 0.25)', border: '#E91E63' },
  PROCUREMENT: { bg: 'rgba(0, 188, 212, 0.25)', border: '#00BCD4' },
}

function getRoleColor(roleName) {
  return roleColors[roleName]?.bg || 'rgba(158, 158, 158, 0.25)'
}

function getRoleBorderColor(roleName) {
  return roleColors[roleName]?.border || '#9E9E9E'
}

function getFieldIcon(type) {
  return fieldTypes.find(f => f.type === type)?.icon || 'mdi-help'
}

// Drawing rect computed
const drawingRect = computed(() => {
  if (!isDrawingField.value) return { left: 0, top: 0, width: 0, height: 0 }
  
  const minX = Math.min(drawStart.value.x, drawCurrent.value.x)
  const minY = Math.min(drawStart.value.y, drawCurrent.value.y)
  const width = Math.abs(drawCurrent.value.x - drawStart.value.x)
  const height = Math.abs(drawCurrent.value.y - drawStart.value.y)
  
  return { left: minX, top: minY, width, height }
})

// Handlers
const handleFileSelect = event => {
  const file = event.target.files?.[0]
  if (file && file.type === 'application/pdf') {
    templateForm.value.file = file
    pdfPreviewUrl.value = URL.createObjectURL(file)
    pageCount.value = 0
  }
  else {
    alert('Please select a PDF file')
  }
}

const handlePdfLoad = pdf => {
  pageCount.value = pdf.numPages
}

// --- Drawing Handlers ---
function startFieldDraw(event, page) {
  if (!selectedRoleForMapping.value || isDragging.value || isResizing.value) return
  
  const rect = event.currentTarget.getBoundingClientRect()
  const x = ((event.clientX - rect.left) / rect.width) * 100
  const y = ((event.clientY - rect.top) / rect.height) * 100
  
  isDrawingField.value = true
  drawingPage.value = page
  drawStart.value = { x, y }
  drawCurrent.value = { x, y }
}

function onFieldDraw(event, page) {
  // Check if we're dragging/resizing instead
  if (isDragging.value || isResizing.value) {
    onInteractionMove(event)
    return
  }
  
  if (!isDrawingField.value || page !== drawingPage.value) return
  
  const rect = event.currentTarget.getBoundingClientRect()
  const x = ((event.clientX - rect.left) / rect.width) * 100
  const y = ((event.clientY - rect.top) / rect.height) * 100
  
  drawCurrent.value = { x, y }
}

function endFieldDraw() {
  if (isDragging.value || isResizing.value) {
    endInteraction()
    return
  }
  
  if (!isDrawingField.value) return
  
  isDrawingField.value = false
  
  const minX = Math.min(drawStart.value.x, drawCurrent.value.x)
  const minY = Math.min(drawStart.value.y, drawCurrent.value.y)
  const width = Math.abs(drawCurrent.value.x - drawStart.value.x)
  const height = Math.abs(drawCurrent.value.y - drawStart.value.y)
  
  // Minimum size check
  if (width < 3 || height < 2) {
    drawingPage.value = null
    return
  }
  
  // Show field type popup
  pendingField.value = {
    x: minX,
    y: minY,
    width,
    height,
    page: drawingPage.value
  }
  showFieldTypePopup.value = true
  drawingPage.value = null
}

// --- Field Type Selection ---
function selectFieldType(type) {
  if (!pendingField.value || !selectedRoleForMapping.value) return
  
  const newField = {
    id: crypto.randomUUID(),
    type,
    role_name: selectedRoleForMapping.value,
    page_number: pendingField.value.page,
    x: pendingField.value.x,
    y: pendingField.value.y,
    width: pendingField.value.width,
    height: pendingField.value.height,
    required: true,
  }
  
  fieldMappings.value.push(newField)
  pendingField.value = null
  showFieldTypePopup.value = false
}

function cancelFieldType() {
  pendingField.value = null
  showFieldTypePopup.value = false
}

// --- Drag Handlers ---
function startDrag(event, field) {
  if (isResizing.value) return
  event.stopPropagation()
  
  isDragging.value = true
  activeFieldId.value = field.id
  selectedFieldIndex.value = fieldMappings.value.findIndex(f => f.id === field.id)
  
  const parent = event.target.closest('.field-overlay')
  const rect = parent.getBoundingClientRect()
  const mouseX = ((event.clientX - rect.left) / rect.width) * 100
  const mouseY = ((event.clientY - rect.top) / rect.height) * 100
  
  dragOffset.value = {
    x: mouseX - field.x,
    y: mouseY - field.y
  }
}

// --- Resize Handlers ---
function startResize(event, field) {
  event.stopPropagation()
  isResizing.value = true
  activeFieldId.value = field.id
  selectedFieldIndex.value = fieldMappings.value.findIndex(f => f.id === field.id)
}

function onInteractionMove(event) {
  const field = fieldMappings.value.find(f => f.id === activeFieldId.value)
  if (!field) return
  
  const target = event.currentTarget
  const rect = target.getBoundingClientRect()
  const mouseX = ((event.clientX - rect.left) / rect.width) * 100
  const mouseY = ((event.clientY - rect.top) / rect.height) * 100
  
  if (isDragging.value) {
    let newX = mouseX - dragOffset.value.x
    let newY = mouseY - dragOffset.value.y
    
    // Bounds check
    newX = Math.max(0, Math.min(100 - field.width, newX))
    newY = Math.max(0, Math.min(100 - field.height, newY))
    
    field.x = newX
    field.y = newY
  } else if (isResizing.value) {
    let newW = mouseX - field.x
    let newH = mouseY - field.y
    
    // Minimum size
    newW = Math.max(5, Math.min(100 - field.x, newW))
    newH = Math.max(3, Math.min(100 - field.y, newH))
    
    field.width = newW
    field.height = newH
  }
}

function endInteraction() {
  isDragging.value = false
  isResizing.value = false
  activeFieldId.value = null
}

// --- Field Management ---
function selectField(idx) {
  selectedFieldIndex.value = idx
}

function removeFieldMapping(field) {
  const idx = fieldMappings.value.indexOf(field)
  if (idx !== -1) {
    fieldMappings.value.splice(idx, 1)
    if (selectedFieldIndex.value === idx) {
      selectedFieldIndex.value = null
    }
  }
}

// Field Actions
function deleteField(field) {
  const index = fieldMappings.value.indexOf(field)
  if (index !== -1) {
    if (selectedFieldIndex.value === index) {
      selectedFieldIndex.value = null
    } else if (selectedFieldIndex.value > index) {
      selectedFieldIndex.value--
    }
    fieldMappings.value.splice(index, 1)
  }
}

function duplicateField(field) {
  const newField = {
    ...field,
    x: field.x + 2, // Slight offset
    y: field.y + 2,
    id: crypto.randomUUID()
  }
  // Ensure it stays within bounds
  if (newField.x + newField.width > 100) newField.x = 100 - newField.width
  if (newField.y + newField.height > 100) newField.y = 100 - newField.height
  
  fieldMappings.value.push(newField)
  // Select the new field
  selectedFieldIndex.value = fieldMappings.value.length - 1
}

function deleteSelectedField() {
  if (selectedFieldIndex.value !== null) {
    fieldMappings.value.splice(selectedFieldIndex.value, 1)
    selectedFieldIndex.value = null
  }
}

// --- Drag and Drop from toolbar ---
const onDragStart = (event, type) => {
  if (!selectedRoleForMapping.value) return
  event.dataTransfer.setData('fieldType', type)
}

const onDrop = (event, pageNumber) => {
  const type = event.dataTransfer.getData('fieldType')
  if (!type || !selectedRoleForMapping.value) return
   
  const rect = event.target.getBoundingClientRect()
  const x = ((event.clientX - rect.left) / rect.width) * 100
  const y = ((event.clientY - rect.top) / rect.height) * 100

  fieldMappings.value.push({
    id: crypto.randomUUID(),
    type,
    role_name: selectedRoleForMapping.value,
    page_number: pageNumber,
    x: x - 7.5, // Center the field
    y: y - 2.5,
    width: 15,
    height: 5,
    required: true,
  })
}

// --- Role Management ---
const addRole = () => {
  roles.value.push({
    role: '',
    action: 'SIGN',
    required: true,
    signing_order: roles.value.length + 1,
  })
}

const removeRole = index => {
  roles.value.splice(index, 1)
  roles.value.forEach((role, idx) => {
    role.signing_order = idx + 1
  })
}

// --- Threshold Management ---
const addThreshold = () => {
  thresholds.value.push({
    min_amount: 0,
    max_amount: null,
    required_roles: [],
  })
}

const removeThreshold = index => {
  thresholds.value.splice(index, 1)
}

// --- Navigation ---
const canProceed = computed(() => {
  switch (currentStep.value) {
  case 1:
    return templateForm.value.name && templateForm.value.file
  case 2:
    return roles.value.length > 0 && roles.value.every(r => r.role)
  case 3:
    return true 
  case 4:
    return !templateForm.value.amount_required || thresholds.value.length > 0
  default:
    return false
  }
})

const nextStep = () => {
  if (currentStep.value < 5) {
    currentStep.value++
  }
}

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

// --- Create Template ---
const handleCreate = async () => {
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('name', templateForm.value.name)
    formData.append('description', templateForm.value.description)
    formData.append('workflow_type', templateForm.value.workflow_type)
    formData.append('amount_required', templateForm.value.amount_required ? '1' : '0')
    formData.append('required_signature_level', templateForm.value.required_signature_level)
    formData.append('file', templateForm.value.file)

    const template = await templateStore.createTemplate(formData)

    if (roles.value.length > 0) {
      await templateStore.addRoles(template.id, roles.value)
    }

    if (fieldMappings.value.length > 0) {
      const fieldsPayload = fieldMappings.value.map(f => ({
        type: f.type.toLowerCase(),
        signer_role: f.role_name,
        page_number: f.page_number,
        x_position: Number(f.x),
        y_position: Number(f.y),
        width: Number(f.width),
        height: Number(f.height),
        required: f.required,
        label: f.type,
      }))
      await templateStore.saveFields(template.id, fieldsPayload)
    }

    if (thresholds.value.length > 0) {
      await templateStore.addThresholds(template.id, thresholds.value)
    }

    router.push(`/templates/${template.id}`)
  }
  catch (error) {
    console.error('Failed to create template:', error)
    alert('Failed to create template. Please try again.')
  }
  finally {
    loading.value = false
  }
}

// Keyboard shortcuts
function handleKeydown(e) {
  if (e.key === 'Delete' && selectedFieldIndex.value !== null) {
    deleteSelectedField()
  }
  if (e.key === 'Escape') {
    if (showFieldTypePopup.value) {
      cancelFieldType()
    } else {
      selectedFieldIndex.value = null
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  if (pdfPreviewUrl.value) {
    URL.revokeObjectURL(pdfPreviewUrl.value)
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-h4 font-weight-bold">Create New Template</h2>
      <div class="text-body-1 text-medium-emphasis">
        Set up a reusable template for document signing
      </div>
    </div>

    <!-- Stepper -->
    <VStepper v-model="currentStep" alt-labels>
      <VStepperHeader>
        <VStepperItem :value="1" title="Basic Info" icon="mdi-file-document" />
        <VDivider />
        <VStepperItem :value="2" title="Roles" icon="mdi-account-group" />
        <VDivider />
        <VStepperItem :value="3" title="Field Mapping" icon="mdi-crosshairs-gps" />
        <VDivider />
        <VStepperItem :value="4" title="Thresholds" icon="mdi-currency-usd" />
        <VDivider />
        <VStepperItem :value="5" title="Review" icon="mdi-check" />
      </VStepperHeader>

      <VStepperWindow>
        <!-- Step 1: Basic Info -->
        <VStepperWindowItem :value="1">
          <VCard>
            <VCardText>
              <VTextField
                v-model="templateForm.name"
                label="Template Name"
                placeholder="e.g., Standard Purchase Order"
                variant="outlined"
                :rules="[v => !!v || 'Name is required']"
              />
              <VTextarea
                v-model="templateForm.description"
                label="Description"
                placeholder="Describe when this template should be used..."
                variant="outlined"
                rows="3"
                class="mt-4"
              />
              <VSelect
                v-model="templateForm.workflow_type"
                :items="workflowTypes"
                item-title="title"
                item-value="value"
                label="Workflow Type"
                variant="outlined"
                class="mt-4"
              />
              <VSelect
                v-model="templateForm.required_signature_level"
                :items="['SIMPLE', 'ADVANCED', 'QUALIFIED']"
                label="Required Signature Level"
                hint="Level of assurance required for signers"
                persistent-hint
                variant="outlined"
                class="mt-4"
              />
              <VFileInput
                :model-value="templateForm.file ? [templateForm.file] : []"
                label="Upload PDF Template"
                accept="application/pdf"
                variant="outlined"
                prepend-icon=""
                prepend-inner-icon="mdi-file-pdf-box"
                class="mt-4"
                @change="handleFileSelect"
              />
              <VCheckbox
                v-model="templateForm.amount_required"
                label="This template requires financial amount"
                hint="Enable financial threshold rules"
                persistent-hint
              />
            </VCardText>
          </VCard>
        </VStepperWindowItem>

        <!-- Step 2: Roles -->
        <VStepperWindowItem :value="2">
          <VCard>
            <VCardText>
              <div class="d-flex justify-space-between align-center mb-4">
                <div>
                  <div class="text-h6">Define Signing Roles</div>
                  <div class="text-caption text-medium-emphasis">Specify who needs to sign this document</div>
                </div>
                <VBtn prepend-icon="mdi-plus" @click="addRole">Add Role</VBtn>
              </div>

              <VTable v-if="roles.length > 0">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Required</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(role, index) in roles" :key="index">
                    <td>{{ role.signing_order }}</td>
                    <td>
                      <VSelect v-model="role.role" :items="availableRoles" variant="outlined" density="compact" hide-details />
                    </td>
                    <td>
                      <VSelect v-model="role.action" :items="['SIGN', 'APPROVE', 'ACKNOWLEDGE']" variant="outlined" density="compact" hide-details />
                    </td>
                    <td><VCheckbox v-model="role.required" hide-details /></td>
                    <td><VBtn icon="mdi-delete" size="small" variant="text" color="error" @click="removeRole(index)" /></td>
                  </tr>
                </tbody>
              </VTable>

              <VEmptyState v-else icon="mdi-account-group-outline" title="No roles defined" text="Add at least one signing role" />
            </VCardText>
          </VCard>
        </VStepperWindowItem>

        <!-- Step 3: Field Mapping -->
        <VStepperWindowItem :value="3">
          <VCard>
            <VCardText>
              <VAlert type="info" variant="tonal" class="mb-4" closable>
                <strong>Draw fields on the PDF.</strong> Select a role first, then draw rectangles. Fields can be moved, resized, and deleted.
              </VAlert>
              
              <div class="field-mapping-container d-flex gap-4" style="min-height: 650px;">
                
                <!-- Left: Field Toolbar -->
                <div class="field-toolbar pa-3 rounded border" style="width: 220px; flex-shrink: 0;">
                  <div class="text-subtitle-2 mb-3">1. Select Role</div>
                  <VSelect
                    v-model="selectedRoleForMapping"
                    :items="roles.map(r => r.role)"
                    label="Assign to Role"
                    variant="outlined"
                    density="compact"
                    class="mb-4"
                    :disabled="roles.length === 0"
                  />
                  
                  <VDivider class="mb-3" />
                  
                  <div class="text-subtitle-2 mb-2">2. Draw or Drag Field Types</div>
                  <div class="text-caption text-medium-emphasis mb-3">
                    Draw on PDF or drag field below
                  </div>
                  
                  <div class="d-flex flex-column gap-2">
                    <div
                      v-for="ft in fieldTypes"
                      :key="ft.type"
                      class="field-type-chip pa-2 rounded border d-flex align-center gap-2"
                      draggable="true"
                      @dragstart="onDragStart($event, ft.type)"
                      :style="{ 
                        cursor: selectedRoleForMapping ? 'grab' : 'not-allowed',
                        opacity: selectedRoleForMapping ? 1 : 0.5
                      }"
                    >
                      <VIcon :icon="ft.icon" size="18" />
                      <span class="text-body-2">{{ ft.label }}</span>
                    </div>
                  </div>
                  
                  <VDivider class="my-4" />
                  
                  <div class="text-subtitle-2 mb-2">Fields ({{ fieldMappings.length }})</div>
                  <div v-if="fieldMappings.length === 0" class="text-caption text-medium-emphasis">
                    No fields added yet
                  </div>
                  <div v-else class="field-list" style="max-height: 180px; overflow-y: auto;">
                    <div
                      v-for="(field, idx) in fieldMappings"
                      :key="field.id"
                      class="field-item pa-2 rounded mb-1 d-flex justify-space-between align-center"
                      :class="{ 'border-primary': selectedFieldIndex === idx }"
                      :style="{ backgroundColor: getRoleColor(field.role_name), cursor: 'pointer' }"
                      @click="selectField(idx)"
                    >
                      <div class="text-caption">
                        <VIcon :icon="getFieldIcon(field.type)" size="14" class="mr-1" />
                        <strong>{{ field.type }}</strong>
                        <div class="text-disabled">{{ field.role_name }} • Page {{ field.page_number }}</div>
                      </div>
                      <VBtn icon="mdi-close" size="x-small" variant="text" @click.stop="removeFieldMapping(field)" />
                    </div>
                  </div>
                </div>
                
                <!-- Center: PDF Preview -->
                <div class="pdf-preview-area flex-grow-1 bg-grey-lighten-4 rounded pa-4 overflow-auto">
                  <template v-if="pdfPreviewUrl">
                    <VuePdfEmbed
                      v-if="pageCount === 0"
                      :source="pdfPreviewUrl"
                      class="d-none"
                      @loaded="handlePdfLoad"
                    />
                    
                    <div
                      v-for="page in pageCount"
                      :key="page"
                      class="pdf-page-wrapper position-relative mb-4 mx-auto elevation-3 rounded"
                      style="width: 600px; background: white;"
                    >
                      <VuePdfEmbed :source="pdfPreviewUrl" :page="page" :width="600" />
                      
                      <!-- Field Overlay Layer -->
                      <div
                        class="field-overlay position-absolute"
                        style="top: 0; left: 0; right: 0; bottom: 0;"
                        :style="{ cursor: selectedRoleForMapping && !isDragging && !isResizing ? 'crosshair' : 'default' }"
                        @mousedown="startFieldDraw($event, page)"
                        @mousemove="onFieldDraw($event, page)"
                        @mouseup="endFieldDraw"
                        @mouseleave="endFieldDraw"
                        @dragover.prevent
                        @drop="onDrop($event, page)"
                      >
                        <!-- Drawing Preview -->
                        <div
                          v-if="isDrawingField && drawingPage === page"
                          class="drawing-rect position-absolute border-2 border-dashed rounded"
                          :style="{
                            left: drawingRect.left + '%',
                            top: drawingRect.top + '%',
                            width: drawingRect.width + '%',
                            height: drawingRect.height + '%',
                            borderColor: getRoleBorderColor(selectedRoleForMapping),
                            backgroundColor: getRoleColor(selectedRoleForMapping)
                          }"
                        />
                        
                        <!-- Existing Fields -->
                        <div
                          v-for="field in fieldMappings.filter(f => f.page_number === page)"
                          :key="field.id"
                          class="field-marker position-absolute rounded d-flex align-center justify-center"
                          :class="{ 'selected': selectedFieldIndex === fieldMappings.indexOf(field) }"
                          :style="{
                            left: field.x + '%',
                            top: field.y + '%',
                            width: field.width + '%',
                            height: field.height + '%',
                            backgroundColor: getRoleColor(field.role_name),
                            border: '2px solid ' + getRoleBorderColor(field.role_name),
                            cursor: 'move',
                            zIndex: selectedFieldIndex === fieldMappings.indexOf(field) ? 10 : 1
                          }"
                          @mousedown="startDrag($event, field)"
                          @click.stop="selectField(fieldMappings.indexOf(field))"
                        >
                          <VIcon :icon="getFieldIcon(field.type)" size="14" class="mr-1" />
                          <span class="text-caption font-weight-medium">{{ field.type }}</span>
                          
                          <!-- Toolbar -->
                          <div v-if="selectedFieldIndex === fieldMappings.indexOf(field)" class="field-toolbar">
                              <VBtn
                                icon="mdi-content-copy"
                                size="x-small"
                                color="secondary"
                                variant="flat"
                                class="toolbar-btn text-white"
                                title="Duplicate"
                                @click.stop="duplicateField(field)"
                              >
                                <VIcon size="14">mdi-content-copy</VIcon>
                              </VBtn>
                              <VBtn
                                icon="mdi-delete"
                                size="x-small"
                                color="error"
                                variant="flat"
                                class="toolbar-btn text-white"
                                title="Delete"
                                @click.stop="deleteField(field)"
                              >
                                 <VIcon size="14">mdi-delete</VIcon>
                              </VBtn>
                          </div>

                          <!-- Resize Handle -->
                          <div
                            v-if="selectedFieldIndex === fieldMappings.indexOf(field)"
                            class="resize-handle position-absolute"
                            style="right: -4px; bottom: -4px; width: 12px; height: 12px; background: white; border: 2px solid #666; border-radius: 2px; cursor: se-resize;"
                            @mousedown.stop="startResize($event, field)"
                          />
                        </div>
                      </div>
                    </div>
                  </template>
                  
                  <div v-else class="text-center py-16 text-medium-emphasis">
                    <VIcon icon="mdi-file-pdf-box" size="64" class="mb-4" />
                    <div>Upload a PDF in Step 1 to map fields</div>
                  </div>
                </div>
                
                <!-- Right: Field Properties -->
                <div v-if="selectedFieldIndex !== null && fieldMappings[selectedFieldIndex]" class="field-properties pa-3 rounded border" style="width: 220px; flex-shrink: 0;">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <span class="text-subtitle-2">Field Properties</span>
                    <VBtn icon="mdi-close" size="x-small" variant="text" @click="selectedFieldIndex = null" />
                  </div>
                  
                  <VSelect
                    v-model="fieldMappings[selectedFieldIndex].type"
                    :items="fieldTypes.map(f => f.type)"
                    label="Type"
                    variant="outlined"
                    density="compact"
                    class="mb-3"
                  />
                  
                  <VSelect
                    v-model="fieldMappings[selectedFieldIndex].role_name"
                    :items="roles.map(r => r.role)"
                    label="Assigned Role"
                    variant="outlined"
                    density="compact"
                    class="mb-3"
                  />
                  
                  <VCheckbox
                    v-model="fieldMappings[selectedFieldIndex].required"
                    label="Required"
                    density="compact"
                    class="mb-3"
                  />
                  
                  <VBtn block color="error" variant="tonal" size="small" @click="deleteSelectedField">
                    <VIcon icon="mdi-delete" class="mr-1" /> Delete Field
                  </VBtn>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VStepperWindowItem>

        <!-- Step 4: Thresholds -->
        <VStepperWindowItem :value="4">
          <VCard>
            <VCardText>
              <template v-if="templateForm.amount_required">
                <div class="d-flex justify-space-between align-center mb-4">
                  <div>
                    <div class="text-h6">Financial Thresholds</div>
                    <div class="text-caption text-medium-emphasis">Define approval requirements based on amount</div>
                  </div>
                  <VBtn prepend-icon="mdi-plus" @click="addThreshold">Add Threshold</VBtn>
                </div>

                <VTable v-if="thresholds.length > 0">
                  <thead>
                    <tr>
                      <th>Min Amount</th>
                      <th>Max Amount</th>
                      <th>Required Roles</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(threshold, index) in thresholds" :key="index">
                      <td><VTextField v-model.number="threshold.min_amount" type="number" prefix="$" variant="outlined" density="compact" hide-details /></td>
                      <td><VTextField v-model.number="threshold.max_amount" type="number" prefix="$" variant="outlined" density="compact" hide-details placeholder="No limit" /></td>
                      <td><VSelect v-model="threshold.required_roles" :items="roles.map(r => r.role)" multiple chips variant="outlined" density="compact" hide-details /></td>
                      <td><VBtn icon="mdi-delete" size="small" variant="text" color="error" @click="removeThreshold(index)" /></td>
                    </tr>
                  </tbody>
                </VTable>

                <VEmptyState v-else icon="mdi-currency-usd" title="No thresholds defined" text="Add financial threshold rules" />
              </template>

              <VAlert v-else type="info" variant="tonal">
                Financial thresholds are disabled. Go back to Step 1 to enable amount-based rules.
              </VAlert>
            </VCardText>
          </VCard>
        </VStepperWindowItem>

        <!-- Step 5: Review -->
        <VStepperWindowItem :value="5">
          <VCard>
            <VCardText>
              <div class="text-h6 mb-4">Review Template Configuration</div>

              <VList>
                <VListSubheader>Basic Information</VListSubheader>
                <VListItem>
                  <VListItemTitle>Name</VListItemTitle>
                  <VListItemSubtitle>{{ templateForm.name }}</VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Workflow Type</VListItemTitle>
                  <VListItemSubtitle>{{ templateForm.workflow_type }}</VListItemSubtitle>
                </VListItem>

                <VDivider class="my-4" />

                <VListSubheader>Roles ({{ roles.length }})</VListSubheader>
                <VListItem v-for="role in roles" :key="role.signing_order">
                  <VListItemTitle>{{ role.signing_order }}. {{ role.role }} - {{ role.action }}</VListItemTitle>
                </VListItem>

                <VDivider class="my-4" />

                <VListSubheader>Field Mappings ({{ fieldMappings.length }})</VListSubheader>
                <VListItem v-if="fieldMappings.length === 0">
                  <VListItemTitle class="text-medium-emphasis">No fields mapped</VListItemTitle>
                </VListItem>
                <VListItem v-for="field in fieldMappings" :key="field.id">
                  <VListItemTitle>{{ field.type }} - {{ field.role_name }} (Page {{ field.page_number }})</VListItemTitle>
                </VListItem>

                <template v-if="templateForm.amount_required && thresholds.length > 0">
                  <VDivider class="my-4" />
                  <VListSubheader>Financial Thresholds ({{ thresholds.length }})</VListSubheader>
                  <VListItem v-for="(threshold, index) in thresholds" :key="index">
                    <VListItemTitle>${{ threshold.min_amount }} - ${{ threshold.max_amount || '∞' }}</VListItemTitle>
                    <VListItemSubtitle>Requires: {{ threshold.required_roles.join(', ') }}</VListItemSubtitle>
                  </VListItem>
                </template>
              </VList>
            </VCardText>
          </VCard>
        </VStepperWindowItem>
      </VStepperWindow>

      <!-- Navigation -->
      <VCardActions class="mt-4">
        <VBtn v-if="currentStep > 1" @click="previousStep">Back</VBtn>
        <VSpacer />
        <VBtn v-if="currentStep < 5" color="primary" :disabled="!canProceed" @click="nextStep">Next</VBtn>
        <VBtn v-else color="primary" :loading="loading" @click="handleCreate">Create Template</VBtn>
      </VCardActions>
    </VStepper>

    <!-- Field Type Selection Popup -->
    <VDialog v-model="showFieldTypePopup" max-width="400" persistent>
      <VCard>
        <VCardTitle>Select Field Type</VCardTitle>
        <VCardText>
          <div class="d-flex flex-wrap gap-2">
            <VBtn
              v-for="ft in fieldTypes"
              :key="ft.type"
              variant="outlined"
              @click="selectFieldType(ft.type)"
            >
              <VIcon :icon="ft.icon" class="mr-2" />
              {{ ft.label }}
            </VBtn>
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="cancelFieldType">Cancel</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.field-type-chip {
  transition: all 0.15s ease;
}
.field-type-chip:hover {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.field-marker {
  transition: box-shadow 0.15s ease;
  user-select: none;
}
.field-marker:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.field-marker.selected {
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.5);
}

.drawing-rect {
  pointer-events: none;
}

.resize-handle {
  opacity: 1;
  z-index: 20;
}

.field-toolbar {
  position: absolute;
  top: -28px;
  right: 0;
  display: flex;
  gap: 4px;
  background: white;
  padding: 2px;
  border-radius: 4px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  z-index: 100;
}

.toolbar-btn {
  min-width: 24px !important;
  width: 24px !important;
  height: 24px !important;
  padding: 0 !important;
}

.field-marker {
  transition: box-shadow 0.15s ease;
  user-select: none;
}
.field-marker:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  z-index: 5;
}
.field-marker.selected {
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.8) !important;
  z-index: 10;
}
</style>
