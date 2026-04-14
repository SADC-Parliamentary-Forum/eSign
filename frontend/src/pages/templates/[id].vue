<script setup>
/**
 * Template Editor - Prepare-style UI
 * Uses same drag/drop field editing as Prepare page
 * No tabs, no review workflow, no thresholds
 */
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed/dist/index.essential.mjs'
import { useRoute, useRouter } from 'vue-router'
import { useTemplateStore } from '@/stores/templates'
import { useOrganizationStore } from '@/stores/organization'
import { useDisplay } from 'vuetify'

const route = useRoute()
const router = useRouter()
const templateStore = useTemplateStore()
const organizationStore = useOrganizationStore()
const { mobile, smAndDown, mdAndDown } = useDisplay()

// Use blank layout for full-screen editor
definePage({
  meta: { layout: 'blank' },
})

// Template state
const template = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

// PDF state
const pdfSource = ref(null)
const pageCount = ref(0)

// Roles state (organizational roles instead of email signers)
const templateRoles = ref([]) // Roles assigned to this template
const selectedRole = ref(null) // Currently selected role for field placement

// Fields state
const fields = ref([])
const selectedFieldId = ref(null)

// Drawing state
const isDrawing = ref(false)
const drawStart = ref({ x: 0, y: 0, page: 1 })
const drawCurrent = ref({ x: 0, y: 0 })

// Field type popup
const showFieldTypePopup = ref(false)
const pendingField = ref(null)

// Drag & Resize State
const isDragging = ref(false)
const isResizing = ref(false)
const dragOffset = ref({ x: 0, y: 0 })
const activeInteractionFieldId = ref(null)

// Mobile drawer states
const showLeftDrawer = ref(false)
const showRightDrawer = ref(false)

// Add role dialog
const showAddRoleDialog = ref(false)
const selectedOrgRole = ref(null)

// Delete dialog
const showDeleteDialog = ref(false)
const deleting = ref(false)

// Available org roles (filtered to exclude already added)
const availableRoles = computed(() => {
  const addedRoleIds = templateRoles.value.map(r => r.organizational_role_id)
  return organizationStore.roles.filter(r => !addedRoleIds.includes(r.id))
})

// Color palette for signers
const signerColors = [
  { bg: '#E3F2FD', border: '#1976D2', text: '#1976D2' },
  { bg: '#F3E5F5', border: '#7B1FA2', text: '#7B1FA2' },
  { bg: '#E8F5E9', border: '#388E3C', text: '#388E3C' },
  { bg: '#FFF3E0', border: '#F57C00', text: '#F57C00' },
  { bg: '#FCE4EC', border: '#C2185B', text: '#C2185B' },
  { bg: '#E0F7FA', border: '#0097A7', text: '#0097A7' },
]

// Field types - matching Prepare page
const fieldTypes = [
  { type: 'SIGNATURE', icon: 'ri-pen-nib-line', label: 'Signature', desc: 'Full signature' },
  { type: 'INITIALS', icon: 'ri-font-size-2', label: 'Initials', desc: 'Quick initials' },
  { type: 'DATE', icon: 'ri-calendar-line', label: 'Date', desc: 'Auto-fill date' },
  { type: 'TEXT', icon: 'ri-text', label: 'Text', desc: 'Custom text' },
  { type: 'CHECKBOX', icon: 'ri-checkbox-line', label: 'Checkbox', desc: 'Yes/No option' },
]

// Responsive PDF width
const pdfWidth = computed(() => {
  if (mobile.value) return 350
  if (smAndDown.value) return 500
  if (mdAndDown.value) return 600
  return 700
})

// Progress indicator
const progressSteps = computed(() => [
  { label: 'Add Roles', done: templateRoles.value.length > 0, icon: 'ri-user-settings-line' },
  { label: 'Place Fields', done: fields.value.length > 0, icon: 'ri-pen-nib-line' },
  { label: 'Save', done: false, icon: 'ri-save-line' },
])

onMounted(async () => {
  await organizationStore.fetchRoles()
  await fetchTemplate()
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  if (pdfSource.value?.url?.startsWith('blob:')) {
    URL.revokeObjectURL(pdfSource.value.url)
  }
})

async function fetchTemplate() {
  try {
    loading.value = true
    template.value = await templateStore.fetchTemplate(route.params.id)
    
    // Load PDF
    const token = localStorage.getItem('token')
    const response = await fetch(`/api/templates/${route.params.id}/pdf`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    
    if (response.ok) {
      const blob = await response.blob()
      pdfSource.value = {
        url: URL.createObjectURL(blob),
      }
    }
    
    // Load existing template roles
    if (template.value.template_roles?.length > 0) {
      templateRoles.value = template.value.template_roles.map((tr, i) => ({
        ...tr,
        id: tr.id || crypto.randomUUID(),
        orgRole: organizationStore.roleById(tr.organizational_role_id),
        color: signerColors[i % signerColors.length]
      }))
      
      if (templateRoles.value.length > 0) {
        selectedRole.value = templateRoles.value[0]
      }
    }
    
    // Load existing fields
    if (template.value.fields?.length > 0) {
      fields.value = template.value.fields.map(f => ({
        ...f,
        id: f.id || crypto.randomUUID(),
        type: f.type?.toUpperCase() || 'SIGNATURE',
        x: Number(f.x_position || f.x || 0),
        y: Number(f.y_position || f.y || 0),
        width: Number(f.width || 15),
        height: Number(f.height || 5),
        organizational_role_id: f.organizational_role_id,
        role_color: templateRoles.value.find(r => r.organizational_role_id === f.organizational_role_id)?.color
      }))
    }
  } catch (e) {
    error.value = 'Failed to load template: ' + (e.message || 'Unknown error')
    console.error('Failed to load template', e)
  } finally {
    loading.value = false
  }
}

function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
}

// Role management
function addRole() {
  if (!selectedOrgRole.value) return
  
  // Use loose equality to handle potential string/number mismatch from v-select
  const orgRole = organizationStore.roles.find(r => r.id == selectedOrgRole.value)
  if (!orgRole) return
  
  const newRole = {
    id: crypto.randomUUID(),
    organizational_role_id: orgRole.id,
    orgRole: orgRole,
    signing_order: templateRoles.value.length + 1,
    is_required: true,
    color: signerColors[templateRoles.value.length % signerColors.length]
  }
  
  templateRoles.value.push(newRole)
  selectedRole.value = newRole
  selectedOrgRole.value = null
  showAddRoleDialog.value = false
}

function removeRole(index) {
  const removed = templateRoles.value.splice(index, 1)[0]
  fields.value = fields.value.filter(f => f.organizational_role_id !== removed.organizational_role_id)
  
  // Update signing order for remaining roles
  templateRoles.value.forEach((r, i) => r.signing_order = i + 1)
  
  if (selectedRole.value?.id === removed.id) {
    selectedRole.value = templateRoles.value[0] || null
  }
}

function selectRole(role) {
  selectedRole.value = role
}

// Drawing handlers
function startDrawing(e, page) {
  if (!selectedRole.value || isDragging.value || isResizing.value) return
  
  const target = e.currentTarget
  const rect = target.getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100
  
  isDrawing.value = true
  drawStart.value = { x, y, page }
  drawCurrent.value = { x, y }
}

function onDrawing(e, page) {
  if (isDragging.value || isResizing.value) {
    onInteractionMove(e, page)
    return
  }
  if (!isDrawing.value || page !== drawStart.value.page) return
  
  const target = e.currentTarget
  const rect = target.getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100
  
  drawCurrent.value = { x, y }
}

function endDrawing() {
  if (isDragging.value || isResizing.value) {
    endInteraction()
    return
  }
  if (!isDrawing.value) return
  
  isDrawing.value = false
  
  const minX = Math.min(drawStart.value.x, drawCurrent.value.x)
  const minY = Math.min(drawStart.value.y, drawCurrent.value.y)
  const width = Math.abs(drawCurrent.value.x - drawStart.value.x)
  const height = Math.abs(drawCurrent.value.y - drawStart.value.y)
  
  if (width < 3 || height < 2) return
  
  pendingField.value = { x: minX, y: minY, width, height, page: drawStart.value.page }
  showFieldTypePopup.value = true
}

// Drag & Resize
function startDrag(e, field) {
  if (isResizing.value) return
  e.stopPropagation()
  
  isDragging.value = true
  activeInteractionFieldId.value = field.id
  selectedFieldId.value = field.id
  
  const parent = e.target.closest('.field-overlay')
  const rect = parent.getBoundingClientRect()
  const mouseX = ((e.clientX - rect.left) / rect.width) * 100
  const mouseY = ((e.clientY - rect.top) / rect.height) * 100
  
  dragOffset.value = { x: mouseX - field.x, y: mouseY - field.y }
}

function startResize(e, field) {
  e.stopPropagation()
  isResizing.value = true
  activeInteractionFieldId.value = field.id
  selectedFieldId.value = field.id
}

function onInteractionMove(e, page) {
  const field = fields.value.find(f => f.id === activeInteractionFieldId.value)
  if (!field) return

  const target = e.currentTarget
  const rect = target.getBoundingClientRect()
  const mouseX = ((e.clientX - rect.left) / rect.width) * 100
  const mouseY = ((e.clientY - rect.top) / rect.height) * 100

  if (isDragging.value) {
    let newX = mouseX - dragOffset.value.x
    let newY = mouseY - dragOffset.value.y
    newX = Math.max(0, Math.min(100 - field.width, newX))
    newY = Math.max(0, Math.min(100 - field.height, newY))
    field.x = newX
    field.y = newY
  } else if (isResizing.value) {
    let newW = mouseX - field.x
    let newH = mouseY - field.y
    newW = Math.max(5, Math.min(100 - field.x, newW))
    newH = Math.max(3, Math.min(100 - field.y, newH))
    field.width = newW
    field.height = newH
  }
}

function endInteraction() {
  isDragging.value = false
  isResizing.value = false
  activeInteractionFieldId.value = null
}

function duplicateFieldToAllPages(field) {
  if (!field) return
  
  if (!field.group_id) {
    field.group_id = crypto.randomUUID()
  }
  const groupId = field.group_id
  
  const newFields = []
  for (let p = 1; p <= pageCount.value; p++) {
    if (p === field.page_number) continue
    newFields.push({
      ...field,
      id: crypto.randomUUID(),
      group_id: groupId,
      page_number: p
    })
  }
  
  fields.value.push(...newFields)
}

function selectFieldType(type) {
  if (!pendingField.value || !selectedRole.value) return
  
  // Apply 45% size increase for INITIALS as requested by user
  let finalWidth = pendingField.value.width
  let finalHeight = pendingField.value.height
  
  if (type === 'INITIALS') {
    finalWidth *= 1.45
    finalHeight *= 1.45
  }

  const newField = {
    id: crypto.randomUUID(),
    template_id: template.value?.id,
    type,
    page_number: pendingField.value.page,
    x: pendingField.value.x,
    y: pendingField.value.y,
    width: finalWidth,
    height: finalHeight,
    organizational_role_id: selectedRole.value.organizational_role_id,
    role_color: selectedRole.value.color,
    fill_mode: ['SIGNATURE', 'INITIALS', 'CHECKBOX'].includes(type) ? 'SIGNER_FILL' : 'PRE_FILL',
    required: true,
    label: type
  }
  
  fields.value.push(newField)
  pendingField.value = null
  showFieldTypePopup.value = false
}

function cancelFieldType() {
  pendingField.value = null
  showFieldTypePopup.value = false
}

function selectField(field) {
  selectedFieldId.value = field.id
}

function deleteField(fieldId) {
  fields.value = fields.value.filter(f => f.id !== fieldId)
  selectedFieldId.value = null
}

function getFieldsByPage(page) {
  return fields.value.filter(f => f.page_number === page)
}

function getFieldColor(field) {
  return field.role_color || { bg: '#FFF9C4', border: '#FBC02D', text: '#F57F17' }
}

function getFieldTypeIcon(type) {
  return fieldTypes.find(t => t.type === type)?.icon || 'ri-question-line'
}

function getFieldTypeLabel(type) {
  const labels = {
    'SIGNATURE': 'Signature',
    'INITIALS': 'Initials',
    'DATE': 'Date',
    'TEXT': 'A',
    'CHECKBOX': '☐'
  }
  return labels[type] || type
}

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

// Save template - direct save, no review
async function saveTemplate() {
  saving.value = true
  error.value = ''
  
  try {
    // Save template roles first
    const rolesPayload = templateRoles.value.map(r => ({
      organizational_role_id: r.organizational_role_id,
      signing_order: r.signing_order,
      is_required: r.is_required,
      role: r.orgRole?.name || 'Signer', // Fallback for legacy database constraint
      action: 'SIGN' // Default action key
    }))
    
    await $api(`/templates/${template.value.id}/roles`, {
      method: 'POST',
      body: { roles: rolesPayload }
    })
    
    // Save fields with organizational role references
    const fieldPayload = fields.value.map(f => ({
      type: f.type.toLowerCase(),
      organizational_role_id: f.organizational_role_id,
      page_number: f.page_number,
      x_position: Number(f.x),
      y_position: Number(f.y),
      width: Number(f.width),
      height: Number(f.height),
      fill_mode: f.fill_mode || 'SIGNER_FILL',
      required: f.required,
      label: f.label
    }))
    
    await templateStore.saveFields(template.value.id, fieldPayload)
    
    // Activate template immediately - no review workflow
    await templateStore.activateTemplate(template.value.id)
    
    router.push('/templates')
  } catch (e) {
    error.value = e.message || 'Failed to save template'
    console.error('Save error:', e)
  } finally {
    saving.value = false
  }
}

// Delete template
async function deleteTemplate() {
  deleting.value = true
  try {
    await templateStore.deleteTemplate(template.value.id)
    router.push('/templates')
  } catch (e) {
    error.value = e.message || 'Failed to delete template'
  } finally {
    deleting.value = false
    showDeleteDialog.value = false
  }
}

function handleKeydown(e) {
  if (e.key === 'Delete' && selectedFieldId.value) {
    deleteField(selectedFieldId.value)
  }
  if (e.key === 'Escape') {
    if (showFieldTypePopup.value) {
      cancelFieldType()
    } else {
      selectedFieldId.value = null
    }
  }
}
</script>

<template>
  <div class="prepare-page">
    <!-- Top Header Bar -->
    <header class="header-bar">
      <div class="header-left">
        <v-btn 
          icon="ri-arrow-left-line" 
          variant="text" 
          size="small"
          @click="router.push('/templates')"
          title="Back to Templates"
        />
        <v-divider vertical class="mx-2" />
        <div class="document-info">
          <span v-if="template" class="document-title">{{ template.name }}</span>
          <v-skeleton-loader v-else type="text" width="150" />
        </div>
      </div>
      
      <div class="header-center d-none d-md-flex">
        <div class="progress-steps">
          <div 
            v-for="(step, i) in progressSteps" 
            :key="i"
            class="progress-step"
            :class="{ 'step-done': step.done }"
          >
            <v-icon :icon="step.icon" size="18" />
            <span class="step-label">{{ step.label }}</span>
            <v-icon v-if="i < progressSteps.length - 1" icon="ri-arrow-right-s-line" size="16" class="step-arrow" />
          </div>
        </div>
      </div>
      
      <div class="header-right">
        <v-btn 
          v-if="smAndDown"
          icon="ri-group-line" 
          variant="text"
          size="small"
          @click="showLeftDrawer = true"
        />
        
        <v-btn
          variant="text"
          color="error"
          size="small"
          @click="showDeleteDialog = true"
        >
          <v-icon icon="ri-delete-bin-line" size="18" />
          <span class="d-none d-sm-inline ml-1">Delete</span>
        </v-btn>
        
        <v-btn
          color="primary"
          variant="elevated"
          size="small"
          :loading="saving"
          :disabled="fields.length === 0"
          @click="saveTemplate"
          class="submit-btn"
        >
          <v-icon icon="ri-save-line" class="mr-1" size="18" />
          <span class="d-none d-sm-inline">Save Template</span>
        </v-btn>
      </div>
    </header>

    <!-- Error Alert -->
    <v-alert v-if="error" type="error" variant="tonal" closable class="mx-4 mt-2" @click:close="error = ''">
      {{ error }}
    </v-alert>

    <!-- Main Content Area -->
    <div class="main-content">
      <!-- Left Sidebar: Roles -->
      <aside v-if="!smAndDown" class="left-sidebar">
        <div class="sidebar-header">
          <span class="sidebar-title">Roles</span>
          <v-chip size="x-small" color="primary" variant="flat">{{ templateRoles.length }}</v-chip>
        </div>
        
        <v-btn 
          v-if="!showAddRoleDialog"
          block 
          color="primary" 
          variant="tonal"
          size="small"
          prepend-icon="ri-user-settings-line"
          class="mb-3"
          @click="showAddRoleDialog = true"
        >
          Add Role
        </v-btn>
        
        <v-expand-transition>
          <div v-if="showAddRoleDialog" class="add-signer-form mb-3">
            <v-select
              v-model="selectedOrgRole"
              :items="availableRoles"
              item-title="name"
              item-value="id"
              label="Select Role"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-3"
              autofocus
              placeholder="Start typing..."
              no-data-text="No roles available"
            >
              <template v-slot:item="{ props, item }">
                <v-list-item v-bind="props" :subtitle="item.raw.description"></v-list-item>
              </template>
            </v-select>
            
            <div class="d-flex gap-2">
              <v-btn size="small" variant="text" @click="showAddRoleDialog = false">Cancel</v-btn>
              <v-btn size="small" color="primary" @click="addRole" :disabled="!selectedOrgRole">Add</v-btn>
            </div>
          </div>
        </v-expand-transition>
        
        <div class="signers-list">
          <!-- Draggable list could be added here for reordering -->
          <div
            v-for="(role, index) in templateRoles"
            :key="role.id"
            class="signer-item"
            :class="{ 'signer-selected': selectedRole?.id === role.id }"
            :style="{ borderLeftColor: role.color.border }"
            @click="selectRole(role)"
          >
            <v-avatar size="28" :color="role.color.border" class="mr-2">
              <span class="text-white text-caption">{{ index + 1 }}</span>
            </v-avatar>
            <div class="signer-info">
              <div class="signer-name">{{ role.orgRole?.name || 'Unknown Role' }}</div>
              <div class="signer-email text-caption">Signing Order: {{ role.signing_order }}</div>
            </div>
            <v-btn 
              icon="ri-close-line" 
              size="x-small" 
              variant="text" 
              @click.stop="removeRole(index)"
            />
          </div>
          
          <div v-if="templateRoles.length === 0" class="empty-state">
            <v-icon icon="ri-user-settings-line" size="32" class="mb-2" />
            <div class="text-caption">Add roles from the list above</div>
          </div>
        </div>
      </aside>

      <!-- Center: PDF Canvas -->
      <main class="pdf-area">
        <div v-if="loading" class="loading-state">
          <v-progress-circular indeterminate size="48" color="primary" />
          <div class="text-caption mt-3">Loading template...</div>
        </div>

        <div v-else-if="pdfSource" class="pdf-scroll">
          <div style="display: none;">
            <VuePdfEmbed
              v-if="pageCount === 0"
              :source="pdfSource"
              @loaded="handleDocumentLoad"
            />
          </div>

          <div 
            v-for="page in pageCount" 
            :key="page" 
            class="pdf-page-wrapper"
          >
            <div class="pdf-page" :style="{ width: pdfWidth + 'px' }">
              <VuePdfEmbed 
                :source="pdfSource" 
                :page="page"
                :width="pdfWidth"
              />
              
              <div 
                class="field-overlay"
                :class="{ 
                  'draw-cursor': selectedRole && !isDragging && !isResizing,
                  'grabbing': isDragging,
                  'resizing': isResizing
                }"
                @mousedown="startDrawing($event, page)"
                @mousemove="onDrawing($event, page)"
                @mouseup="endDrawing"
                @mouseleave="endDrawing"
              >
                <div
                  v-for="field in getFieldsByPage(page)"
                  :key="field.id"
                  class="field-box"
                  :class="{ 
                    'field-selected': selectedFieldId === field.id,
                    'is-interacting': activeInteractionFieldId === field.id
                  }"
                  :style="{
                    left: field.x + '%',
                    top: field.y + '%',
                    width: field.width + '%',
                    height: field.height + '%',
                    backgroundColor: getFieldColor(field).bg,
                    borderColor: getFieldColor(field).border,
                    color: getFieldColor(field).text,
                    zIndex: selectedFieldId === field.id ? 10 : 1
                  }"
                  @mousedown="startDrag($event, field)"
                  @click.stop="selectField(field)"
                >
                  <div class="field-content">
                    <v-icon :icon="getFieldTypeIcon(field.type)" size="16" />
                    <span class="field-label">{{ getFieldTypeLabel(field.type) }}</span>
                  </div>
                  
                  <div v-if="selectedFieldId === field.id" class="field-toolbar">
                    <v-btn
                      icon="ri-file-copy-line"
                      size="x-small"
                      color="secondary"
                      variant="flat"
                      title="Duplicate to all pages"
                      @click.stop="duplicateFieldToAllPages(field)"
                    />
                    <v-btn
                      icon="ri-delete-bin-line"
                      size="x-small"
                      color="error"
                      variant="flat"
                      @click.stop="deleteField(field.id)"
                    />
                  </div>

                  <div 
                    v-if="selectedFieldId === field.id"
                    class="resize-handle"
                    @mousedown="startResize($event, field)"
                  />
                </div>
                
                <div
                  v-if="drawingRect && drawingRect.page === page"
                  class="drawing-preview"
                  :style="{
                    left: drawingRect.left,
                    top: drawingRect.top,
                    width: drawingRect.width,
                    height: drawingRect.height,
                    borderColor: selectedRole?.color?.border || '#1976D2'
                  }"
                />
              </div>
            </div>
            <div class="page-indicator">{{ page }} / {{ pageCount }}</div>
          </div>
        </div>
        
        <v-fade-transition>
          <div v-if="!selectedRole && templateRoles.length === 0 && !loading && pdfSource" class="hint-overlay">
            <v-card class="hint-card" max-width="300">
              <v-card-text class="text-center">
                <v-icon icon="ri-user-settings-line" size="48" color="primary" class="mb-3" />
                <div class="text-h6 mb-2">Define Roles First</div>
                <div class="text-body-2 text-medium-emphasis">
                  Add organizational roles (e.g. Director) first, then assign fields to them.
                </div>
              </v-card-text>
            </v-card>
          </div>
        </v-fade-transition>
      </main>

      <!-- Right Sidebar: Field Types -->
      <aside v-if="!smAndDown" class="right-sidebar">
        <div class="sidebar-header">
          <span class="sidebar-title">Field Types</span>
        </div>
        
        <div class="field-types-hint">
          {{ selectedRole ? `Draw on PDF to add fields for ${selectedRole.orgRole?.name}` : 'Select a role first' }}
        </div>
        
        <div class="field-types-list">
          <div
            v-for="type in fieldTypes"
            :key="type.type"
            class="field-type-item"
            :class="{ 'disabled': !selectedRole }"
          >
            <v-icon :icon="type.icon" size="20" class="field-type-icon" />
            <div class="field-type-info">
              <div class="field-type-label">{{ type.label }}</div>
              <div class="field-type-desc">{{ type.desc }}</div>
            </div>
          </div>
        </div>
        
        <v-divider class="my-3" />
        
        <div class="summary-section">
          <div class="summary-title">Summary</div>
          <div class="summary-item">
            <v-icon icon="ri-group-line" size="16" />
            <span>{{ templateRoles.length }} role(s)</span>
          </div>
          <div class="summary-item">
            <v-icon icon="ri-pen-nib-line" size="16" />
            <span>{{ fields.length }} field(s)</span>
          </div>
          <div class="summary-item">
            <v-icon icon="ri-pages-line" size="16" />
            <span>{{ pageCount }} page(s)</span>
          </div>
        </div>
      </aside>
    </div>

    <!-- Mobile Left Drawer -->
    <v-navigation-drawer v-model="showLeftDrawer" temporary location="left" width="280">
      <div class="pa-4">
        <div class="sidebar-header mb-3">
          <span class="sidebar-title">Roles</span>
          <v-chip size="x-small" color="primary" variant="flat">{{ templateRoles.length }}</v-chip>
        </div>
        
        <v-btn 
          v-if="!showAddRoleDialog"
          block 
          color="primary" 
          variant="tonal"
          prepend-icon="ri-user-settings-line"
          class="mb-3"
          @click="showAddRoleDialog = true"
        >
          Add Role
        </v-btn>
        
        <v-expand-transition>
          <div v-if="showAddRoleDialog" class="add-signer-form mb-3">
             <v-select
              v-model="selectedOrgRole"
              :items="availableRoles"
              item-title="name"
              item-value="id"
              label="Select Role"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-3"
              return-object
            />
            <div class="d-flex gap-2">
              <v-btn size="small" variant="text" @click="showAddRoleDialog = false">Cancel</v-btn>
              <v-btn size="small" color="primary" @click="addRole" :disabled="!selectedOrgRole">Add</v-btn>
            </div>
          </div>
        </v-expand-transition>
        
        <div class="signers-list">
          <div
            v-for="(role, index) in templateRoles"
            :key="role.id"
            class="signer-item"
            :class="{ 'signer-selected': selectedRole?.id === role.id }"
            :style="{ borderLeftColor: role.color.border }"
            @click="selectRole(role); showLeftDrawer = false"
          >
            <v-avatar size="28" :color="role.color.border" class="mr-2">
              <span class="text-white text-caption">{{ index + 1 }}</span>
            </v-avatar>
            <div class="signer-info">
              <div class="signer-name">{{ role.orgRole?.name || 'Unknown Role' }}</div>
            </div>
            <v-btn 
              icon="ri-close-line" 
              size="x-small" 
              variant="text" 
              @click.stop="removeRole(index)"
            />
          </div>
        </div>
      </div>
    </v-navigation-drawer>

    <!-- Field Type Selection Dialog -->
    <v-dialog v-model="showFieldTypePopup" max-width="320" persistent>
      <v-card rounded="lg">
        <v-card-title class="d-flex align-center pa-4">
          <v-icon icon="ri-shape-2-line" class="mr-2" color="primary" />
          Choose Field Type
        </v-card-title>
        
        <v-divider />

        <v-list density="compact">
          <v-list-item
            v-for="type in fieldTypes"
            :key="type.type"
            :prepend-icon="type.icon"
            :title="type.label"
            :subtitle="type.desc"
            @click="selectFieldType(type.type)"
          />
        </v-list>

        <v-divider />

        <v-card-actions>
          <v-btn block variant="text" @click="cancelFieldType">Cancel</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="400">
      <v-card>
        <v-card-title>Delete Template?</v-card-title>
        <v-card-text>
          Are you sure you want to delete <strong>{{ template?.name }}</strong>?
          This cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" :loading="deleting" @click="deleteTemplate">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.prepare-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
}

.header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: white;
  border-bottom: 1px solid rgba(0,0,0,0.08);
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  z-index: 100;
}

.header-left { display: flex; align-items: center; gap: 8px; }
.document-info { display: flex; flex-direction: column; }
.document-title { font-weight: 600; font-size: 14px; color: #333; }
.header-center { flex: 1; display: flex; justify-content: center; }
.header-right { display: flex; align-items: center; gap: 8px; }

.progress-steps { display: flex; align-items: center; gap: 4px; }
.progress-step {
  display: flex; align-items: center; gap: 4px;
  padding: 4px 10px; border-radius: 16px; font-size: 12px;
  color: #666; background: #f0f0f0; transition: all 0.2s;
}
.progress-step.step-done { background: #e8f5e9; color: #2e7d32; }
.step-arrow { opacity: 0.4; margin: 0 2px; }

.main-content { display: flex; flex: 1; overflow: hidden; }

.left-sidebar, .right-sidebar {
  width: 200px; flex-shrink: 0; background: white;
  border-right: 1px solid rgba(0,0,0,0.06);
  padding: 12px; overflow-y: auto;
}
.right-sidebar { border-right: none; border-left: 1px solid rgba(0,0,0,0.06); width: 180px; }
.sidebar-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.sidebar-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #666; }

.add-signer-form { background: #f8f9fa; padding: 12px; border-radius: 8px; }

.signers-list { display: flex; flex-direction: column; gap: 6px; }
.signer-item {
  display: flex; align-items: center; padding: 8px; border-radius: 8px;
  border-left: 3px solid transparent; cursor: pointer; transition: all 0.15s;
}
.signer-item:hover { background: #f5f5f5; }
.signer-item.signer-selected { background: #e3f2fd; }
.signer-info { flex: 1; min-width: 0; }
.signer-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.signer-email { font-size: 11px; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.empty-state { text-align: center; padding: 24px 8px; color: #999; }

.field-types-hint { font-size: 11px; color: #888; margin-bottom: 12px; line-height: 1.4; }
.field-types-list { display: flex; flex-direction: column; gap: 4px; }
.field-type-item { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 6px; transition: all 0.15s; }
.field-type-item:not(.disabled):hover { background: #e3f2fd; }
.field-type-item.disabled { opacity: 0.4; }
.field-type-icon { color: #1976d2; }
.field-type-info { flex: 1; }
.field-type-label { font-size: 12px; font-weight: 500; }
.field-type-desc { font-size: 10px; color: #888; }

.summary-section { display: flex; flex-direction: column; gap: 6px; }
.summary-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 4px; }
.summary-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #666; }

.pdf-area { flex: 1; overflow: hidden; display: flex; flex-direction: column; position: relative; }
.loading-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.pdf-scroll { flex: 1; overflow: auto; padding: 20px; display: flex; flex-direction: column; align-items: center; gap: 16px; }
.pdf-page-wrapper { display: flex; flex-direction: column; align-items: center; }
.pdf-page { background: white; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.12); overflow: hidden; position: relative; }
.page-indicator { margin-top: 8px; font-size: 11px; color: #888; background: rgba(255,255,255,0.9); padding: 2px 12px; border-radius: 10px; }

.field-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; }
.field-overlay.draw-cursor { cursor: crosshair; }
.field-overlay.grabbing { cursor: grabbing; }
.field-overlay.resizing { cursor: nwse-resize; }

.field-box {
  position: absolute; border: 2px solid; border-radius: 6px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: all 0.15s;
  font-size: 12px; font-weight: 500; user-select: none;
}
.field-box:hover { transform: scale(1.01); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.field-box.field-selected { box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.4); }
.field-box.is-interacting { transition: none; z-index: 100 !important; }

.field-content { display: flex; align-items: center; gap: 4px; padding: 2px 6px; }
.field-label { font-size: 11px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80px; }

.field-toolbar {
  position: absolute; top: -36px; right: -2px; display: flex; gap: 4px;
  background: white; padding: 4px; border-radius: 6px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.15); z-index: 100;
}

.resize-handle {
  position: absolute; bottom: -5px; right: -5px; width: 12px; height: 12px;
  background: white; border: 2px solid #1976D2; border-radius: 50%;
  cursor: nwse-resize; z-index: 20; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.resize-handle:hover { background: #1976D2; transform: scale(1.2); }

.drawing-preview { position: absolute; border: 2px dashed; background: rgba(25, 118, 210, 0.1); pointer-events: none; border-radius: 4px; }

.hint-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.03); pointer-events: none; }
.hint-card { pointer-events: auto; }

@media (max-width: 960px) { .left-sidebar, .right-sidebar { display: none; } }
@media (max-width: 600px) { .header-bar { padding: 6px 12px; } .pdf-scroll { padding: 12px; } }
</style>


