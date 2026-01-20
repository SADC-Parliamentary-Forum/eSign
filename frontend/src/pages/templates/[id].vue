<script setup>
import { useTemplateStore } from '@/stores/templates'
import VuePdfEmbed from 'vue-pdf-embed'
import { useDisplay } from 'vuetify'

const route = useRoute()
const router = useRouter()
const templateStore = useTemplateStore()
const { mobile } = useDisplay()

const loading = ref(true)
const saving = ref(false)
const template = ref(null)
const activeTab = ref('overview')

// PDF & Field Editor State
const pdfSource = ref(null)
const pageCount = ref(0)
const fields = ref([])
const roles = ref([])
const thresholds = ref([])

// Editor UI State
const selectedRole = ref(null)
const selectedFieldId = ref(null)
const isDrawing = ref(false)
const drawStart = ref({ x: 0, y: 0, page: 1 })
const drawCurrent = ref({ x: 0, y: 0 })
const isDragging = ref(false)
const isResizing = ref(false)
const activeInteractionFieldId = ref(null)
const dragOffset = ref({ x: 0, y: 0 })

// Field Types
const fieldTypes = [
  { type: 'SIGNATURE', icon: 'mdi-draw', label: 'Signature' },
  { type: 'INITIALS', icon: 'mdi-format-letter-case', label: 'Initials' },
  { type: 'DATE', icon: 'mdi-calendar', label: 'Date' },
  { type: 'TEXT', icon: 'mdi-form-textbox', label: 'Text Box' },
  { type: 'CHECKBOX', icon: 'mdi-checkbox-marked', label: 'Checkbox' },
]

onMounted(async () => {
  await loadTemplate()
})

onUnmounted(() => {
  if (pdfSource.value && pdfSource.value.startsWith('blob:')) {
    URL.revokeObjectURL(pdfSource.value)
  }
})

async function loadTemplate() {
  loading.value = true
  try {
    const res = await templateStore.fetchTemplate(route.params.id)
    template.value = res
    roles.value = res.roles || []
    thresholds.value = res.thresholds || []
    
    // Map backend fields to frontend format
    // Backend: TemplateField model (x_position, y_position, width, height, signer_role)
    // Frontend editor: x, y, width, height, role_name
    if (res.fields) {
      fields.value = res.fields.map(f => ({
        id: f.id || crypto.randomUUID(),
        type: f.type.toUpperCase(),
        page_number: f.page_number,
        x: Number(f.x_position),
        y: Number(f.y_position),
        width: Number(f.width),
        height: Number(f.height),
        role_name: f.signer_role,
        required: f.required
      }))
    }

    // Set initial selected role if available
    if (roles.value.length > 0) {
      selectedRole.value = roles.value[0].role
    }

    // Load PDF
    const token = localStorage.getItem('token')
    const response = await fetch(`${import.meta.env.VITE_API_URL || '/api'}/templates/${route.params.id}/pdf`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })
    
    if (response.ok) {
      const blob = await response.blob()
      pdfSource.value = URL.createObjectURL(blob)
    } else {
      console.error('Failed to load PDF')
    }
  } catch (e) {
    console.error('Failed to load template:', e)
  } finally {
    loading.value = false
  }
}

async function saveChanges() {
  if (!template.value) return
  saving.value = true
  try {
    // Save roles
    if (roles.value.length > 0) {
      await templateStore.addRoles(template.value.id, roles.value)
    }
    
    // Save fields
    // Convert back to backend format
    const fieldPayload = fields.value.map(f => ({
      type: f.type.toLowerCase(),
      signer_role: f.role_name,
      page_number: f.page_number,
      x_position: f.x,
      y_position: f.y,
      width: f.width,
      height: f.height,
      required: f.required
    }))
    
    await templateStore.saveFields(template.value.id, fieldPayload)
    
    // Save thresholds
    if (thresholds.value.length > 0) {
      await templateStore.addThresholds(template.value.id, thresholds.value)
    }
    
    // Reload
    await loadTemplate()
    // Show success snackbar (if we had one)
  } catch (e) {
    console.error('Failed to save changes:', e)
  } finally {
    saving.value = false
  }
}

// --- Interaction Handlers ---

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

function startDrawing(e, page) {
  if (isDragging.value || isResizing.value) return
  if (!selectedRole.value) {
    // Show snackbar or alert 'Select a role first'
    alert('Please select a role first')
    return
  }
  
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

const showFieldTypePopup = ref(false)
const pendingField = ref(null)

function endDrawing(e) {
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
  
  // Minimum size check (prevent accidental clicks)
  if (width < 3 || height < 2) return
  
  pendingField.value = {
    x: minX,
    y: minY,
    width,
    height,
    page: drawStart.value.page
  }
  
  showFieldTypePopup.value = true
}

function selectFieldType(type) {
  if (!pendingField.value || !selectedRole.value) return
  
  const newField = {
    id: crypto.randomUUID(),
    type,
    page_number: pendingField.value.page,
    x: pendingField.value.x,
    y: pendingField.value.y,
    width: pendingField.value.width,
    height: pendingField.value.height,
    role_name: selectedRole.value,
    required: true
  }
  
  fields.value.push(newField)
  pendingField.value = null
  showFieldTypePopup.value = false
}

// --- Drag & Resize ---

function startDrag(e, field) {
    if (isResizing.value) return
    e.stopPropagation()
    
    isDragging.value = true
    activeInteractionFieldId.value = field.id
    selectedFieldId.value = field.id
    
    // Parent rect logic
    const parent = e.target.closest('.field-overlay')
    const rect = parent.getBoundingClientRect()
    
    const mouseX = ((e.clientX - rect.left) / rect.width) * 100
    const mouseY = ((e.clientY - rect.top) / rect.height) * 100
    
    dragOffset.value = {
        x: mouseX - field.x,
        y: mouseY - field.y
    }
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

// --- Helpers ---
function getFieldsByPage(page) {
  return fields.value.filter(f => f.page_number === page)
}

function getRoleColor(roleName, alpha = 1) {
    if (!roleName) return `rgba(158, 158, 158, ${alpha})`
    let hash = 0
    for (let i = 0; i < roleName.length; i++) {
        hash = roleName.charCodeAt(i) + ((hash << 5) - hash)
    }
    const c = (hash & 0x00FFFFFF).toString(16).toUpperCase()
    const color = '00000'.substring(0, 6 - c.length) + c
    
    // Parse hex to rgb
    const r = parseInt(color.substring(0,2), 16)
    const g = parseInt(color.substring(2,4), 16)
    const b = parseInt(color.substring(4,6), 16)
    
    return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function getFieldTypeIcon(type) {
  const t = fieldTypes.find(f => f.type === type)
  return t ? t.icon : 'mdi-help'
}

</script>

<template>
  <div v-if="loading" class="d-flex justify-center align-center h-screen">
    <VProgressCircular indeterminate />
  </div>
  
  <div v-else-if="template">
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <div class="d-flex align-center gap-2">
          <VBtn icon="mdi-arrow-left" variant="text" to="/templates" />
          <h2 class="text-h4 font-weight-bold">{{ template.name }}</h2>
          <VChip size="small" :color="template.status === 'ACTIVE' ? 'primary' : 'grey'">
            {{ template.status }}
          </VChip>
        </div>
        <div class="ml-12 text-body-1 text-medium-emphasis">
          {{ template.description }}
        </div>
      </div>
      
      <div class="d-flex gap-2">
        <VBtn
          v-if="['DRAFT', 'REVIEW'].includes(template.status)"
          color="primary"
          :loading="saving"
          @click="saveChanges"
        >
          Save Changes
        </VBtn>
        <!-- Additional actions: Submit for Review, Activate, etc. -->
      </div>
    </div>

    <VTabs v-model="activeTab" class="mb-4">
      <VTab value="overview">Overview</VTab>
      <VTab value="roles">Roles</VTab>
      <VTab value="fields">Fields</VTab>
      <VTab value="thresholds">Thresholds</VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <!-- Overview Tab -->
      <VWindowItem value="overview">
        <VCard>
          <VCardText>
            <VRow>
              <VCol cols="12" md="6">
                <VTextField 
                  v-model="template.name" 
                  label="Template Name" 
                  variant="outlined" 
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect 
                  v-model="template.required_signature_level"
                  :items="['SIMPLE', 'ADVANCED', 'QUALIFIED']"
                  label="Signature Level"
                  variant="outlined"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea 
                  v-model="template.description" 
                  label="Description" 
                  variant="outlined"
                  rows="3" 
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Roles Tab -->
      <VWindowItem value="roles">
        <VCard>
          <VCardText>
            <div class="d-flex justify-space-between mb-4">
              <h3 class="text-h6">Signing Roles</h3>
              <VBtn size="small" prepend-icon="mdi-plus" @click="roles.push({ role: 'New Role', action: 'SIGN', signing_order: roles.length + 1, required: true })">
                Add Role
              </VBtn>
            </div>
            
            <VTable>
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Role Name</th>
                  <th>Action</th>
                  <th>Required</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(role, idx) in roles" :key="idx">
                  <td><VTextField v-model.number="role.signing_order" type="number" density="compact" hide-details variant="outlined" style="width: 80px" /></td>
                  <td><VTextField v-model="role.role" density="compact" hide-details variant="outlined" /></td>
                  <td>
                    <VSelect 
                      v-model="role.action" 
                      :items="['SIGN', 'APPROVE', 'ACKNOWLEDGE']" 
                      density="compact" 
                      hide-details 
                      variant="outlined" 
                      style="width: 150px"
                    />
                  </td>
                  <td><VCheckbox v-model="role.required" hide-details /></td>
                  <td><VBtn icon="mdi-delete" color="error" variant="text" size="small" @click="roles.splice(idx, 1)" /></td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Fields Tab -->
      <VWindowItem value="fields">
        <VCard height="800" class="d-flex flex-column bg-grey-lighten-4 overflow-hidden">
          <!-- Toolbar -->
          <div class="d-flex align-center px-4 py-2 bg-surface border-b gap-4">
            <div class="text-subtitle-2">Selected Role for New Fields:</div>
            <VSelect
              v-model="selectedRole"
              :items="roles"
              item-title="role"
              item-value="role"
              density="compact"
              hide-details
              variant="outlined"
              style="width: 200px"
              placeholder="Select Role"
            >
              <template #selection="{ item }">
                <div class="d-flex align-center gap-2">
                  <VBadge dot :color="getRoleColor(item.raw.role)" inline />
                  {{ item.raw.role }}
                </div>
              </template>
            </VSelect>
            <VSpacer />
            <div class="text-caption text-medium-emphasis">
              Draw fields on the document to assign them to roles.
            </div>
          </div>

          <!-- PDF Editor Area -->
          <div class="flex-grow-1 position-relative overflow-auto d-flex justify-center" style="background: #525659">
            <div v-if="loading" class="d-flex justify-center align-center h-100 text-white">
              <VProgressCircular indeterminate color="white" />
            </div>

            <div v-else-if="pdfSource" class="pdf-container my-8" style="width: 800px; position: relative;">
               <!-- Iterate pages -->
               <div 
                 v-for="page in pageCount" 
                 :key="page" 
                 class="pdf-page mb-4 position-relative bg-white elevation-3"
                 style="width: 100%; aspect-ratio: 1/1.4142;"
                 @mousedown="e => startDrawing(e, page)"
                 @mousemove="e => onDrawing(e, page)"
                 @mouseup="endDrawing"
                 @mouseleave="endDrawing"
               >
                 <VuePdfEmbed 
                   :source="pdfSource" 
                   :page="page"
                   :width="800"
                   @loaded="pdf => pageCount = pdf.numPages"
                 />
                 
                 <!-- Fields Overlay -->
                 <div class="field-overlay position-absolute top-0 left-0 w-100 h-100" style="pointer-events: none;">
                   <!-- Existing Fields -->
                   <div
                     v-for="field in getFieldsByPage(page)"
                     :key="field.id"
                     class="field-item position-absolute d-flex align-center justify-center border rounded cursor-move"
                     :class="{ 'field-selected': selectedFieldId === field.id }"
                     :style="{
                       left: field.x + '%',
                       top: field.y + '%',
                       width: field.width + '%',
                       height: field.height + '%',
                       backgroundColor: getRoleColor(field.role_name, 0.2),
                       borderColor: getRoleColor(field.role_name, 1),
                       pointerEvents: 'all'
                     }"
                     @mousedown.stop="e => startDrag(e, field)"
                     @click.stop="selectedFieldId = field.id"
                   >
                     <!-- Resize Handles (only if selected) -->
                     <template v-if="selectedFieldId === field.id">
                        <div class="resize-handle bottom-right" @mousedown.stop="e => startResize(e, field)" />
                        <!-- Delete Button -->
                        <div class="delete-btn position-absolute" style="top: -10px; right: -10px; z-index: 10;" @mousedown.stop>
                           <VBtn icon="mdi-close" size="x-small" color="error" variant="elevated" @click.stop="fields = fields.filter(f => f.id !== field.id)" />
                        </div>
                     </template>

                     <div class="d-flex flex-column align-center text-center overflow-hidden w-100">
                        <VIcon size="small" :color="getRoleColor(field.role_name, 1)">{{ getFieldTypeIcon(field.type) }}</VIcon>
                        <span class="text-caption font-weight-bold text-truncate w-100 px-1" :style="{ color: getRoleColor(field.role_name, 1) }">
                          {{ field.role_name }}
                        </span>
                     </div>
                   </div>

                   <!-- Drawing Preview -->
                   <div 
                     v-if="isDrawing && drawingRect && drawingRect.page === page"
                     class="drawing-preview position-absolute border border-primary border-dashed bg-primary-lighten-4 opacity-50"
                     :style="{
                       left: drawingRect.left,
                       top: drawingRect.top,
                       width: drawingRect.width,
                       height: drawingRect.height
                     }"
                   />
                 </div>
               </div>
            </div>
            
            <div v-else class="text-white d-flex align-center h-100">
               PDF could not be loaded.
            </div>
          </div>
        </VCard>

        <!-- Field Type Popup -->
        <VDialog v-model="showFieldTypePopup" max-width="300" persistent>
          <VCard title="Select Field Type">
            <VCardText>
               <VList>
                 <VListItem 
                   v-for="type in fieldTypes" 
                   :key="type.type"
                   :prepend-icon="type.icon"
                   :title="type.label"
                   @click="selectFieldType(type.type)"
                   class="mb-1 rounded"
                   hover
                 />
               </VList>
            </VCardText>
            <VCardActions>
              <VSpacer />
              <VBtn variant="text" @click="pendingField = null; showFieldTypePopup = false">Cancel</VBtn>
            </VCardActions>
          </VCard>
        </VDialog>
      </VWindowItem>
      
      <!-- Thresholds Tab -->
      <VWindowItem value="thresholds">
         <VCard>
          <VCardText>
             <div class="d-flex justify-space-between mb-4">
              <h3 class="text-h6">Financial Thresholds</h3>
              <VBtn size="small" prepend-icon="mdi-plus" @click="thresholds.push({ min_amount: 0, max_amount: null, required_roles: [] })">
                Add Threshold
              </VBtn>
            </div>
             <VTable>
              <thead>
                <tr>
                  <th>Min Amount</th>
                  <th>Max Amount</th>
                  <th>Required Roles</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(t, idx) in thresholds" :key="idx">
                    <td><VTextField v-model.number="t.min_amount" prefix="$" density="compact" hide-details variant="outlined" /></td>
                    <td><VTextField v-model.number="t.max_amount" prefix="$" placeholder="No Limit" density="compact" hide-details variant="outlined" /></td>
                    <td>
                      <VSelect 
                        v-model="t.required_roles" 
                        :items="roles.map(r => r.role)" 
                        multiple 
                        chips 
                        density="compact" 
                        hide-details 
                        variant="outlined" 
                      />
                    </td>
                    <td><VBtn icon="mdi-delete" color="error" variant="text" size="small" @click="thresholds.splice(idx, 1)" /></td>
                </tr>
              </tbody>
             </VTable>
          </VCardText>
         </VCard>
      </VWindowItem>
    </VWindow>
  </div>
</template>
