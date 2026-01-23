<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTemplateStore } from '@/stores/templates'
import { useOrganizationStore } from '@/stores/organization'
import { useDisplay } from 'vuetify'

const route = useRoute()
const router = useRouter()
const templateStore = useTemplateStore()
const organizationStore = useOrganizationStore()
const { mobile } = useDisplay()

definePage({
  meta: {
    layout: 'default',
    title: 'Use Template'
  }
})

// State
const step = ref(1)
const loading = ref(true)
const sending = ref(false)
const template = ref(null)
const error = ref(null)

// Assignment State
const roleAssignments = ref({}) // { role_id: user_object }
const userSearchHelper = ref({}) // { role_id: search_query }

// Data Filling State
const fieldValues = ref({}) // { field_id: value }

// Mock User Search (Replace with actual API later)
const searchUsers = async (query) => {
  // In a real app, this would be an API call
  // For now, returning mock users
  if (!query) return []
  await new Promise(r => setTimeout(r, 300))
  return [
    { id: 1, name: 'John Doe', email: 'john.doe@example.com', role: 'Director Finance' },
    { id: 2, name: 'Jane Smith', email: 'jane.smith@example.com', role: 'Secretary General', has_delegate: true, delegate_name: 'Bob Jones' },
    { id: 3, name: 'Bob Jones', email: 'bob.jones@example.com', role: 'Deputy SG' },
  ].filter(u => u.name.toLowerCase().includes(query.toLowerCase()) || u.email.toLowerCase().includes(query.toLowerCase()))
}

// Steps configuration
const steps = [
  { title: 'Assign Roles', subtitle: 'Who needs to sign?' },
  { title: 'Fill Details', subtitle: 'Complete document info' },
  { title: 'Review & Send', subtitle: 'Final check' }
]

// Computed
const sortedRoles = computed(() => {
  if (!template.value?.roles) return []
  return [...template.value.roles].sort((a, b) => a.signing_order - b.signing_order)
})

const preFillFields = computed(() => {
  if (!template.value?.fields) return []
  return template.value.fields.filter(f => f.fill_mode === 'PRE_FILL')
})

const canProceedFromStep1 = computed(() => {
  // Check if all required roles are assigned
  if (!template.value?.roles) return false
  return template.value.roles.every(role => {
    if (!role.is_required) return true
    return !!roleAssignments.value[role.id]
  })
})

const canProceedFromStep2 = computed(() => {
  // Check if all required pre-fill fields are filled
  return preFillFields.value.every(field => {
    if (!field.required) return true
    return !!fieldValues.value[field.id]
  })
})

// Methods
onMounted(async () => {
  try {
    loading.value = true
    await organizationStore.fetchRoles() // Ensure roles are loaded
    template.value = await templateStore.fetchTemplate(route.params.id)
    
    // Initialize assignments with empty values
    if (template.value?.roles) {
      template.value.roles.forEach(role => {
        roleAssignments.value[role.id] = null
      })
    }
    
    // Initialize field values
    if (template.value?.fields) {
      template.value.fields.forEach(field => {
        if (field.fill_mode === 'PRE_FILL') {
          fieldValues.value[field.id] = ''
        }
      })
    }
  } catch (e) {
    error.value = e.message || 'Failed to load template'
  } finally {
    loading.value = false
  }
})

async function onUserSearch(roleId, query) {
  // Logic handled by v-autocomplete items
}

async function handleNext() {
  if (step.value === 1 && canProceedFromStep1.value) {
    step.value = 2
  } else if (step.value === 2 && canProceedFromStep2.value) {
    step.value = 3
  }
}

async function handleSend() {
  try {
    sending.value = true
    
    // Construct payload
    const payload = {
      template_id: template.value.id,
      title: `${template.value.name} - ${new Date().toLocaleDateString()}`,
      assignments: Object.entries(roleAssignments.value).map(([roleId, user]) => ({
        template_role_id: roleId,
        user_id: user.id,
        email: user.email,
        name: user.name
      })),
      field_values: fieldValues.value
    }
    
    // Call API to create document from template
    const response = await $api.post(`/templates/${template.value.id}/apply`, payload)
    
    // Redirect to the new document
    const documentId = response.document.id
    router.push(`/documents/${documentId}`)
  } catch (e) {
    error.value = e.message || 'Failed to send document'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="usage-page pa-6">
    <v-card max-width="1000" class="mx-auto" :loading="loading">
      <v-toolbar color="primary" class="px-4">
        <v-btn icon="ri-arrow-left-line" variant="text" @click="router.back()" />
        <v-toolbar-title>Use Template: {{ template?.name }}</v-toolbar-title>
      </v-toolbar>

      <v-card-text v-if="loading" class="text-center py-10">
        <v-progress-circular indeterminate color="primary" />
        <div class="mt-4">Loading template details...</div>
      </v-card-text>

      <v-card-text v-else>
        <!-- Stepper Header -->
        <v-stepper v-model="step" class="mb-8" flat>
          <v-stepper-header>
            <template v-for="(s, i) in steps" :key="i">
              <v-stepper-item
                :value="i + 1"
                :complete="step > i + 1"
                :title="s.title"
                :subtitle="s.subtitle"
              />
              <v-divider v-if="i < steps.length - 1" />
            </template>
          </v-stepper-header>
        </v-stepper>

        <!-- Step 1: Assign Roles -->
        <div v-if="step === 1" class="step-content">
          <div class="text-h6 mb-4">Assign People to Roles</div>
          <p class="text-body-2 text-medium-emphasis mb-6">
            Please select who will fulfill each role for this document.
          </p>

          <v-list class="role-list">
            <v-list-item
              v-for="role in sortedRoles"
              :key="role.id"
              class="role-item mb-4 border rounded-lg pa-4"
              lines="two"
            >
              <template v-slot:prepend>
                <div class="role-badge mr-4">
                  <v-avatar color="primary" variant="tonal" size="48">
                    <span class="text-h6">{{ role.signing_order }}</span>
                  </v-avatar>
                </div>
              </template>

              <v-list-item-title class="text-h6 mb-2">
                {{ role.orgRole?.name || 'Unknown Role' }}
                <v-chip v-if="role.is_required" size="x-small" color="error" class="ml-2">Required</v-chip>
              </v-list-item-title>

              <v-list-item-subtitle>
                <v-autocomplete
                  v-model="roleAssignments[role.id]"
                  :items="[]" 
                  label="Search for a user..."
                  variant="outlined"
                  density="compact"
                  prepend-inner-icon="ri-search-line"
                  return-object
                  item-title="name"
                  hide-no-data
                  hide-details
                  class="mt-2"
                  placeholder="Type name or email"
                >
                  <!-- Mocking items for now as search logic needs real backend -->
                </v-autocomplete>
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </div>

        <!-- Step 2: Fill Data -->
        <div v-if="step === 2" class="step-content">
          <div class="text-h6 mb-4">Complete Document Details</div>
          <p class="text-body-2 text-medium-emphasis mb-6">
            Fill in the required information before sending.
          </p>

          <div v-if="preFillFields.length === 0" class="empty-fields text-center py-8 bg-grey-lighten-4 rounded">
            <v-icon icon="ri-checkbox-circle-line" size="40" color="success" class="mb-2" />
            <div>No fields require pre-filling. You can proceed.</div>
          </div>

          <v-form v-else>
            <v-row>
              <v-col v-for="field in preFillFields" :key="field.id" cols="12" md="6">
                <v-text-field
                  v-model="fieldValues[field.id]"
                  :label="field.label || 'Untitled Field'"
                  :required="field.required"
                  :rules="field.required ? [v => !!v || 'This field is required'] : []"
                  variant="outlined"
                />
              </v-col>
            </v-row>
          </v-form>
        </div>

        <!-- Step 3: Review -->
        <div v-if="step === 3" class="step-content">
          <div class="text-h6 mb-4">Review & Send</div>
          
          <v-card variant="outlined" class="mb-6">
            <v-card-title class="text-subtitle-1 bg-grey-lighten-4 py-2">Recipients</v-card-title>
            <v-divider />
            <v-list density="compact">
              <v-list-item v-for="role in sortedRoles" :key="role.id">
                <template v-slot:prepend>
                  <v-icon icon="ri-user-line" size="small" class="mr-2" />
                </template>
                <v-list-item-title>
                  {{ roleAssignments[role.id]?.name || 'Not assigned' }} 
                  <span class="text-caption text-medium-emphasis">({{ role.orgRole?.name }})</span>
                </v-list-item-title>
                <v-list-item-subtitle>{{ roleAssignments[role.id]?.email }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card>

          <v-card variant="outlined">
            <v-card-title class="text-subtitle-1 bg-grey-lighten-4 py-2">Document Details</v-card-title>
            <v-divider />
            <v-list density="compact">
              <v-list-item v-for="field in preFillFields" :key="field.id">
                <v-list-item-title>{{ field.label }}</v-list-item-title>
                <v-list-item-subtitle class="text-high-emphasis font-weight-medium">
                  {{ fieldValues[field.id] || '(Empty)' }}
                </v-list-item-subtitle>
              </v-list-item>
              <v-list-item v-if="preFillFields.length === 0">
                <v-list-item-title class="text-caption text-medium-emphasis">No pre-filled data</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card>
        </div>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-btn v-if="step > 1" variant="text" @click="step--">Back</v-btn>
        <v-spacer />
        <v-btn 
          v-if="step < 3" 
          color="primary" 
          @click="handleNext" 
          :disabled="step === 1 ? !canProceedFromStep1 : !canProceedFromStep2"
        >
          Next Step
        </v-btn>
        <v-btn 
          v-else 
          color="success" 
          @click="handleSend" 
          :loading="sending"
          prepend-icon="ri-send-plane-fill"
        >
          Send Document
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<style scoped>
.role-badge {
  width: 48px;
  display: flex;
  justify-content: center;
}
</style>
