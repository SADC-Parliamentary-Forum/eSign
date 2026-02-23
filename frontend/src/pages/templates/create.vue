<script setup>
import { useTemplateStore } from '@/stores/templates'
import VuePdfEmbed from 'vue-pdf-embed'

const templateStore = useTemplateStore()
const router = useRouter()

const currentStep = ref(1)
const loading = ref(false)

const templateForm = ref({
  name: '',
  description: '',
  category: 'Contract',
  workflow_type: 'SEQUENTIAL',
  amount_required: false,
  file: null,
  required_signature_level: 'SIMPLE',
  is_bulk_enabled: false,
  is_field_locked: false,
})

const categories = computed(() => templateStore.categories.filter(c => c !== 'All'))

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

// Watch for file changes and automatically create PDF blob URL
watch(
  () => templateForm.value.file,
  (newFile, oldFile) => {
    // Revoke old blob URL to prevent memory leak
    if (pdfPreviewUrl.value && pdfPreviewUrl.value.startsWith('blob:')) {
      URL.revokeObjectURL(pdfPreviewUrl.value)
    }
    
    if (newFile && newFile instanceof File && newFile.type === 'application/pdf') {
      pdfPreviewUrl.value = URL.createObjectURL(newFile)
      pageCount.value = 0
      console.log('PDF watcher: Created blob URL for', newFile.name)
    } else {
      pdfPreviewUrl.value = null
      pageCount.value = 0
    }
  },
  { immediate: true }
)

// Handlers
const handleFileSelect = eventOrFiles => {
  // Vuetify 3 VFileInput passes files directly as array, not as event
  let file = null
  
  if (Array.isArray(eventOrFiles)) {
    // Vuetify 3 format: array of File objects
    file = eventOrFiles[0]
  } else if (eventOrFiles?.target?.files) {
    // Native input event format
    file = eventOrFiles.target.files[0]
  } else if (eventOrFiles instanceof File) {
    // Direct file object
    file = eventOrFiles
  }
  
  if (file && file.type === 'application/pdf') {
    templateForm.value.file = file
    pdfPreviewUrl.value = URL.createObjectURL(file)
    pageCount.value = 0
    console.log('PDF loaded:', file.name, 'URL:', pdfPreviewUrl.value)
  }
  else if (file) {
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
  
  // Apply 45% size increase for INITIALS
  let finalWidth = pendingField.value.width
  let finalHeight = pendingField.value.height
  
  if (type === 'INITIALS') {
    finalWidth *= 1.45
    finalHeight *= 1.45
  }

  const newField = {
    id: crypto.randomUUID(),
    type,
    role_name: selectedRoleForMapping.value,
    page_number: pendingField.value.page,
    x: pendingField.value.x,
    y: pendingField.value.y,
    width: finalWidth,
    height: finalHeight,
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

  // Default sizes
  let finalWidth = 15
  let finalHeight = 5

  if (type === 'INITIALS') {
    finalWidth *= 1.45
    finalHeight *= 1.45
  }

  fieldMappings.value.push({
    id: crypto.randomUUID(),
    type,
    role_name: selectedRoleForMapping.value,
    page_number: pageNumber,
    x: x - (finalWidth / 2), 
    y: y - (finalHeight / 2),
    width: finalWidth,
    height: finalHeight,
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
    formData.append('category', templateForm.value.category)
    formData.append('workflow_type', templateForm.value.workflow_type)
    formData.append('amount_required', templateForm.value.amount_required ? '1' : '0')
    formData.append('is_bulk_enabled', templateForm.value.is_bulk_enabled ? '1' : '0')
    formData.append('is_field_locked', templateForm.value.is_field_locked ? '1' : '0')
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

    // Activate template immediately (skip review workflow)
    await templateStore.activateTemplate(template.id)

    router.push('/templates')
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
  templateStore.fetchCategories()
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

              <VCombobox
                 v-model="templateForm.category"
                 :items="categories"
                 label="Category"
                 placeholder="Select or type a new category"
                 variant="outlined"
                 class="mt-4"
                 :return-object="false"
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
                label="Upload Template File"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                variant="outlined"
                prepend-icon=""
                prepend-inner-icon="mdi-file-pdf-box"
                class="mt-4"
                @update:model-value="handleFileSelect"
              />
              <VCheckbox
                v-model="templateForm.amount_required"
                label="This template requires financial amount"
                hint="Enable financial threshold rules"
                persistent-hint
              />

               <div class="d-flex gap-4 mt-2">
                 <VCheckbox
                   v-model="templateForm.is_bulk_enabled"
                   label="Enable Bulk Sending"
                   hint="Allow sending to multiple recipients via CSV"
                   persistent-hint
                 />
                 <VCheckbox
                   v-model="templateForm.is_field_locked"
                   label="Lock Form Fields"
                   hint="Prevent senders from modifying fields"
                   persistent-hint
                 />
               </div>
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
              
              <!-- 3-Panel Layout Container -->
              <div class="field-mapping-container">
                
                <!-- Left Sidebar: Role & Field Types -->
                <div class="left-sidebar-panel">
                  <div class="sidebar-content">
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
                </div>
                
                <!-- Center: PDF Preview Area -->
                <div class="pdf-preview-area">
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
                        :class="{ 
                          'draw-cursor': selectedRoleForMapping && !isDragging && !isResizing,
                          'grabbing': isDragging,
                          'resizing': isResizing
                        }"
                        style="top: 0; left: 0; right: 0; bottom: 0;"
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
                          :class="{ 
                            'selected': selectedFieldIndex === fieldMappings.indexOf(field),
                            'is-interacting': activeFieldId === field.id
                          }"
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
                          
                          <!-- Action Toolbar -->
                          <div v-if="selectedFieldIndex === fieldMappings.indexOf(field)" class="field-action-toolbar">
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
                
                <!-- Right: Field Properties (shows when field is selected) -->
                <div 
                  v-if="selectedFieldIndex !== null && fieldMappings[selectedFieldIndex]" 
                  class="field-properties-panel"
                >
                  <div class="properties-content">
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

/* Field Overlay - Core container for field interactions */
.field-overlay {
  z-index: 10;
}

.field-overlay.draw-cursor {
  cursor: crosshair !important;
}

.field-overlay.grabbing {
  cursor: grabbing !important;
}

.field-overlay.resizing {
  cursor: nwse-resize !important;
}

/* Field Marker - Placed fields on PDF */
.field-marker {
  transition: box-shadow 0.15s ease, transform 0.1s ease;
  user-select: none;
  z-index: 1;
}

.field-marker:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
  transform: scale(1.01);
}

.field-marker.selected {
  box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.5) !important;
  z-index: 10 !important;
}

.field-marker.is-interacting {
  transition: none !important;
  z-index: 100 !important;
}

/* Drawing Preview */
.drawing-rect {
  pointer-events: none;
  z-index: 5;
}

/* Resize Handle */
.resize-handle {
  opacity: 1;
  z-index: 20;
  transition: transform 0.1s, background-color 0.1s;
}

.resize-handle:hover {
  transform: scale(1.3);
  background-color: #1976D2 !important;
}

/* Field Toolbar - Appears above selected field */
.field-action-toolbar {
  position: absolute;
  top: -32px;
  right: -2px;
  display: flex;
  gap: 4px;
  background: white;
  padding: 4px;
  border-radius: 6px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.15);
  z-index: 100;
  animation: fadeIn 0.15s ease-out;
}

/* Field Mapping Container Layout - 3 Panel Design */
.field-mapping-container {
  display: flex;
  gap: 16px;
  height: 700px; /* Fixed height for the workspace */
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background-color: rgb(var(--v-theme-surface));
}

/* Left Sidebar Panel - Role selection and field types */
.left-sidebar-panel {
  width: 250px; /* Slightly wider for better content fit */
  min-width: 250px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: rgb(var(--v-theme-surface));
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.sidebar-content {
  padding: 16px;
  overflow-y: auto;
  flex: 1;
}

/* Center PDF Preview Area */
.pdf-preview-area {
  flex: 1;
  min-width: 0; /* Important for flex child to shrink below content size */
  background-color: #f5f5f5; /* Distinct background for workspace */
  overflow: auto; /* Enable scrolling for PDF content */
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 24px;
  position: relative;
}

/* Right Field Properties Panel */
.field-properties-panel {
  width: 250px;
  min-width: 250px;
  flex-shrink: 0;
  background: rgb(var(--v-theme-surface));
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  flex-direction: column;
}

.properties-content {
  padding: 16px;
  overflow-y: auto;
  flex: 1;
}

.pdf-page-wrapper {
  position: relative;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  margin-bottom: 24px;
  background: white;
  /* Ensure it doesn't overflow horizontally without scroll */
  max-width: 100%; 
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}

.toolbar-btn {
  min-width: 26px !important;
  width: 26px !important;
  height: 26px !important;
  padding: 0 !important;
}

/* Field List Item in sidebar */
.field-item {
  transition: all 0.15s ease;
  border: 1px solid transparent;
}

.field-item:hover {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.field-item.border-primary {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>
