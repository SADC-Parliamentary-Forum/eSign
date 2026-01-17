<script setup>
import { useTemplateStore } from '@/stores/templates'
import VuePdfEmbed from 'vue-pdf-embed'
import { useDropZone } from '@vueuse/core'

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

// Handlers
const handleFileSelect = event => {
  const file = event.target.files?.[0]
  if (file && file.type === 'application/pdf') {
    templateForm.value.file = file
    pdfPreviewUrl.value = URL.createObjectURL(file)
  }
  else {
    alert('Please select a PDF file')
  }
}

const handlePdfLoad = (pdf) => {
   pageCount.value = pdf.numPages
}

const onDragStart = (event, type) => {
   event.dataTransfer.setData('fieldType', type)
}

const onDrop = (event, pageNumber) => {
   const type = event.dataTransfer.getData('fieldType')
   if (!type) return
   
   if (!selectedRoleForMapping.value) {
      alert('Please select a role to assign this field to first.')
      return
   }

   const rect = event.target.getBoundingClientRect()
   const x = event.clientX - rect.left
   const y = event.clientY - rect.top
   
   // Convert to percentage
   const xPercent = (x / rect.width) * 100
   const yPercent = (y / rect.height) * 100

   fieldMappings.value.push({
      type,
      role_name: selectedRoleForMapping.value, // We use role_name to map to roles
      page_number: pageNumber,
      x: xPercent,
      y: yPercent,
      width: 15, // Default width %
      height: 5, // Default height %
      required: true
   })
}

const removeFieldMapping = (field) => {
   const idx = fieldMappings.value.indexOf(field)
   if (idx !== -1) fieldMappings.value.splice(idx, 1)
}

const getRoleColor = (roleName) => {
   // Generate consistent color from string
   let hash = 0;
   for (let i = 0; i < roleName.length; i++) {
       hash = roleName.charCodeAt(i) + ((hash << 5) - hash);
   }
   const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
   return '#' + '00000'.substring(0, 6 - c.length) + c + '40'; // 40 for transparency
}

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
  // Reorder remaining roles
  roles.value.forEach((role, idx) => {
    role.signing_order = idx + 1
  })
}

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

const canProceed = computed(() => {
  switch (currentStep.value) {
    case 1:
      return templateForm.value.name && templateForm.value.file
    case 2:
      return roles.value.length > 0 && roles.value.every(r => r.role)
    case 3:
      // Optional, but ideally check if all SIGN roles have at least one field? 
      // For now, allow proceeding even without fields (maybe they add them later or use auto-placement)
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

const handleCreate = async () => {
  loading.value = true
  try {
    // Create FormData for file upload
    const formData = new FormData()
    formData.append('name', templateForm.value.name)
    formData.append('description', templateForm.value.description)
    formData.append('workflow_type', templateForm.value.workflow_type)
    formData.append('amount_required', templateForm.value.amount_required ? '1' : '0')
    formData.append('required_signature_level', templateForm.value.required_signature_level)
    formData.append('file', templateForm.value.file)

    // Create template
    const template = await templateStore.createTemplate(formData)

    // Add roles
    if (roles.value.length > 0) {
      await templateStore.addRoles(template.id, roles.value)
    }

    // Add field mappings (saveFields)
    if (fieldMappings.value.length > 0) {
      const fieldsPayload = fieldMappings.value.map(f => ({
          type: f.type.toLowerCase(), // backend expects lowercase? Checking validation... 'in:signature,initials,date,text'
          signer_role: f.role_name,
          page_number: f.page_number,
          x_position: Number(f.x),
          y_position: Number(f.y),
          width: Number(f.width),
          height: Number(f.height),
          required: f.required,
          label: f.type // Default label
      }))
      await templateStore.saveFields(template.id, fieldsPayload)
    }

    // Add thresholds
    if (thresholds.value.length > 0) {
      await templateStore.addThresholds(template.id, thresholds.value)
    }

    // Navigate to template detail
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
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-h4 font-weight-bold">
        Create New Template
      </h2>
      <div class="text-body-1 text-medium-emphasis">
        Set up a reusable template for document signing
      </div>
    </div>

    <!-- Stepper -->
    <v-stepper
      v-model="currentStep"
      alt-labels
    >
      <v-stepper-header>
        <v-stepper-item
          :value="1"
          title="Basic Info"
          icon="mdi-file-document"
        />
        <v-divider />
        
        <v-stepper-item
          :value="2"
          title="Roles"
          icon="mdi-account-group"
        />
        <v-divider />
        
        <v-stepper-item
          :value="3"
          title="Field Mapping"
          icon="mdi-crosshairs-gps"
        />
        <v-divider />
        
        <v-stepper-item
          :value="4"
          title="Thresholds"
          icon="mdi-currency-usd"
        />
        <v-divider />
        
        <v-stepper-item
          :value="5"
          title="Review"
          icon="mdi-check"
        />
      </v-stepper-header>

      <v-stepper-window>
        <!-- Step 1: Basic Info -->
        <v-stepper-window-item :value="1">
          <v-card>
            <v-card-text>
              <v-text-field
                v-model="templateForm.name"
                label="Template Name"
                placeholder="e.g., Standard Purchase Order"
                variant="outlined"
                :rules="[v => !!v || 'Name is required']"
              />

              <v-textarea
                v-model="templateForm.description"
                label="Description"
                placeholder="Describe when this template should be used..."
                variant="outlined"
                rows="3"
                class="mt-4"
              />

              <!-- Workflow Type Selection -->
              <v-select
                v-model="templateForm.workflow_type"
                :items="workflowTypes"
                item-title="title"
                item-value="value"
                label="Workflow Type"
                variant="outlined"
                class="mt-4"
              />

              <v-select
                v-model="templateForm.required_signature_level"
                :items="['SIMPLE', 'ADVANCED', 'QUALIFIED']"
                label="Required Signature Level"
                hint="Level of assurance required for signers"
                persistent-hint
                variant="outlined"
                class="mt-4"
              />

              <v-file-input
                :model-value="templateForm.file ? [templateForm.file] : []"
                label="Upload PDF Template"
                accept="application/pdf"
                variant="outlined"
                prepend-icon=""
                prepend-inner-icon="mdi-file-pdf-box"
                class="mt-4"
                :rules="[v => !!v || 'PDF file is required']"
                @change="handleFileSelect"
              />

              <v-checkbox
                v-model="templateForm.amount_required"
                label="This template requires financial amount"
                hint="Enable financial threshold rules"
                persistent-hint
              />
            </v-card-text>
          </v-card>
        </v-stepper-window-item>

        <!-- Step 2: Roles -->
        <v-stepper-window-item :value="2">
          <v-card>
            <v-card-text>
              <div class="d-flex justify-space-between align-center mb-4">
                <div>
                  <div class="text-h6">
                    Define Signing Roles
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Specify who needs to sign this document
                  </div>
                </div>
                <v-btn
                  prepend-icon="mdi-plus"
                  @click="addRole"
                >
                  Add Role
                </v-btn>
              </div>

              <v-table v-if="roles.length > 0">
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
                      <v-select
                        v-model="role.role"
                        :items="availableRoles"
                        variant="outlined"
                        density="compact"
                        hide-details
                      />
                    </td>
                    <td>
                      <v-select
                        v-model="role.action"
                        :items="['SIGN', 'APPROVE', 'ACKNOWLEDGE']"
                        variant="outlined"
                        density="compact"
                        hide-details
                      />
                    </td>
                    <td>
                      <v-checkbox
                        v-model="role.required"
                        hide-details
                      />
                    </td>
                    <td>
                      <v-btn
                        icon="mdi-delete"
                        size="small"
                        variant="text"
                        color="error"
                        @click="removeRole(index)"
                      />
                    </td>
                  </tr>
                </tbody>
              </v-table>

              <v-empty-state
                v-else
                icon="mdi-account-group-outline"
                title="No roles defined"
                text="Add at least one signing role"
              />
            </v-card-text>
          </v-card>
        </v-stepper-window-item>

        <!-- Step 3: Field Mapping -->
        <v-stepper-window-item :value="3">
          <v-card>
            <v-card-text>
              <v-alert type="info" variant="tonal" class="mb-4">
                Field mapping allows you to specify exact signature placement coordinates.
                This step is optional and can be configured later.
              </v-alert>
              
              <div class="text-center py-8">
                <v-icon icon="mdi-map-marker" size="64" color="grey" />
                <div class="mt-4">
                  Field mapping will be implemented using PDF viewer with drag-and-drop
                </div>
                <div class="text-caption text-medium-emphasis">
                  Skip for now and configure later in template settings
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-stepper-window-item>

        <!-- Step 4: Thresholds -->
        <v-stepper-window-item :value="4">
          <v-card>
            <v-card-text>
              <template v-if="templateForm.amount_required">
                <div class="d-flex justify-space-between align-center mb-4">
                  <div>
                    <div class="text-h6">
                      Financial Thresholds
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Define approval requirements based on amount
                    </div>
                  </div>
                  <v-btn
                    prepend-icon="mdi-plus"
                    @click="addThreshold"
                  >
                    Add Threshold
                  </v-btn>
                </div>

                <v-table v-if="thresholds.length > 0">
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
                      <td>
                        <v-text-field
                          v-model.number="threshold.min_amount"
                          type="number"
                          prefix="$"
                          variant="outlined"
                          density="compact"
                          hide-details
                        />
                      </td>
                      <td>
                        <v-text-field
                          v-model.number="threshold.max_amount"
                          type="number"
                          prefix="$"
                          variant="outlined"
                          density="compact"
                          hide-details
                          placeholder="No limit"
                        />
                      </td>
                      <td>
                        <v-select
                          v-model="threshold.required_roles"
                          :items="roles.map(r => r.role)"
                          multiple
                          chips
                          variant="outlined"
                          density="compact"
                          hide-details
                        />
                      </td>
                      <td>
                        <v-btn
                          icon="mdi-delete"
                          size="small"
                          variant="text"
                          color="error"
                          @click="removeThreshold(index)"
                        />
                      </td>
                    </tr>
                  </tbody>
                </v-table>

                <v-empty-state
                  v-else
                  icon="mdi-currency-usd"
                  title="No thresholds defined"
                  text="Add financial threshold rules"
                />
              </template>

              <v-alert v-else type="info" variant="tonal">
                Financial thresholds are disabled. 
                Go back to Step 1 to enable amount-based rules.
              </v-alert>
            </v-card-text>
          </v-card>
        </v-stepper-window-item>

        <!-- Step 5: Review -->
        <v-stepper-window-item :value="5">
          <v-card>
            <v-card-text>
              <div class="text-h6 mb-4">
                Review Template Configuration
              </div>

              <v-list>
                <v-list-subheader>Basic Information</v-list-subheader>
                <v-list-item>
                  <v-list-item-title>Name</v-list-item-title>
                  <v-list-item-subtitle>{{ templateForm.name }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Workflow Type</v-list-item-title>
                  <v-list-item-subtitle>{{ templateForm.workflow_type }}</v-list-item-subtitle>
                </v-list-item>

                <v-divider class="my-4" />

                <v-list-subheader>Roles ({{ roles.length }})</v-list-subheader>
                <v-list-item v-for="role in roles" :key="role.signing_order">
                  <v-list-item-title>
                    {{ role.signing_order }}. {{ role.role }} - {{ role.action }}
                  </v-list-item-title>
                </v-list-item>

                <template v-if="templateForm.amount_required && thresholds.length > 0">
                  <v-divider class="my-4" />
                  <v-list-subheader>Financial Thresholds ({{ thresholds.length }})</v-list-subheader>
                  <v-list-item v-for="(threshold, index) in thresholds" :key="index">
                    <v-list-item-title>
                      ${{ threshold.min_amount }} - ${{ threshold.max_amount || '∞' }}
                    </v-list-item-title>
                    <v-list-item-subtitle>
                      Requires: {{ threshold.required_roles.join(', ') }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-list>
            </v-card-text>
          </v-card>
        </v-stepper-window-item>
      </v-stepper-window>

      <!-- Navigation -->
      <v-card-actions class="mt-4">
        <v-btn
          v-if="currentStep > 1"
          @click="previousStep"
        >
          Back
        </v-btn>

        <v-spacer />

        <v-btn
          v-if="currentStep < 5"
          color="primary"
          :disabled="!canProceed"
          @click="nextStep"
        >
          Next
        </v-btn>

        <v-btn
          v-else
          color="primary"
          :loading="loading"
          @click="handleCreate"
        >
          Create Template
        </v-btn>
      </v-card-actions>
    </v-stepper>
  </div>
</template>
