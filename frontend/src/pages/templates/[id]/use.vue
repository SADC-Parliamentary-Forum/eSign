<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTemplateStore } from '@/stores/templates'
import { useOrganizationStore } from '@/stores/organization'
import { $api } from '@/utils/api'

const route = useRoute()
const router = useRouter()
const templateStore = useTemplateStore()
const organizationStore = useOrganizationStore()

const loading = ref(true)
const processing = ref(false)
const template = ref(null)
const error = ref('')

// Form Data
const documentTitle = ref('')
const roleAssignments = ref({}) // Map role.id -> { name, email }

onMounted(async () => {
  try {
    loading.value = true
    await Promise.all([
      organizationStore.fetchRoles(),
      fetchTemplate()
    ])
  } catch (e) {
    error.value = e.message || 'Failed to load template'
  } finally {
    loading.value = false
  }
})

async function fetchTemplate() {
  const data = await templateStore.fetchTemplate(route.params.id)
  template.value = data
  documentTitle.value = data.name
  
  // Initialize assignments for each role
  if (data.template_roles) {
    data.template_roles.forEach(role => {
      roleAssignments.value[role.id] = {
        name: '',
        email: '',
        roleId: role.id,
        orgRoleId: role.organizational_role_id,
        roleName: getRoleName(role.organizational_role_id),
        order: role.signing_order
      }
    })
  }
}

function getRoleName(orgRoleId) {
  const r = organizationStore.roles.find(r => r.id === orgRoleId)
  return r ? r.name : 'Signer'
}

const isValid = computed(() => {
  if (!documentTitle.value) return false
  
  // Check if all template roles are assigned
  const assignments = Object.values(roleAssignments.value)
  if (assignments.length === 0) return true
  
  return assignments.every(a => a.name && a.email)
})

async function createDocument() {
  if (!isValid.value) return
  
  processing.value = true
  error.value = ''
  
  try {
    // 1. Create Document
    const docRes = await $api('/documents', {
      method: 'POST',
      body: {
        title: documentTitle.value,
        template_id: template.value.id,
      }
    })
    
    // 2. Add Signers if there are roles
    const signersList = Object.values(roleAssignments.value).map(a => ({
      name: a.name,
      email: a.email,
      organizational_role_id: a.orgRoleId,
      order: a.order
    }))
    
    if (signersList.length > 0) {
      // Sort signers by order locally just in case, though backend should handle it
      signersList.sort((a, b) => a.order - b.order)
      
      await $api(`/documents/${docRes.id}/signers`, {
        method: 'POST',
        body: {
          signers: signersList,
          sequential: template.value.workflow_type === 'SEQUENTIAL'
        }
      })
    }
    
    // 3. Redirect to Document Prepare/Edit
    router.push(`/documents/${docRes.id}`)
    
  } catch (e) {
    console.error(e)
    error.value = e.message || 'Failed to create document'
  } finally {
    processing.value = false
  }
}
</script>

<template>
  <div class="fill-height bg-grey-lighten-5 py-8">
    <VContainer max-width="800">
      <div v-if="loading" class="d-flex justify-center align-center py-12">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div v-else-if="error" class="text-center py-12">
        <VIcon icon="ri-error-warning-line" size="48" color="error" class="mb-4" />
        <h3 class="text-h6 text-error mb-2">Error Loading Template</h3>
        <p class="text-body-2 text-medium-emphasis mb-6">{{ error }}</p>
        <VBtn variant="outlined" to="/templates">Back to Templates</VBtn>
      </div>

      <template v-else>
        <!-- Header -->
        <div class="mb-6 d-flex align-center justify-space-between">
          <div>
            <VBtn 
              variant="text" 
              prepend-icon="ri-arrow-left-line" 
              class="px-0 mb-2" 
              to="/templates"
            >
              Back to Templates
            </VBtn>
            <h1 class="text-h4 font-weight-bold">Use Template</h1>
            <p class="text-medium-emphasis">Configure and send document from template</p>
          </div>
        </div>

        <VCard border :loading="processing">
          <VCardTitle class="px-6 pt-6">
            Document Details
          </VCardTitle>
          
          <VCardText class="px-6 pb-6">
            <VTextField
              v-model="documentTitle"
              label="Document Title"
              variant="outlined"
              hint="Give your document a clear name"
              persistent-hint
              class="mb-6"
            />
            
            <div v-if="Object.keys(roleAssignments).length > 0">
              <div class="d-flex align-center justify-space-between mb-4">
                <h3 class="text-h6">Recipients</h3>
                <span class="text-caption text-medium-emphasis">Assign people to roles</span>
              </div>
              
              <div class="d-flex flex-column gap-4">
                <div 
                  v-for="(assignment, id) in roleAssignments" 
                  :key="id" 
                  class="role-card pa-4 rounded-lg border bg-surface"
                >
                  <div class="d-flex align-center mb-3">
                    <VAvatar color="primary" variant="tonal" size="32" class="mr-3">
                      <span class="text-caption font-weight-bold">{{ assignment.order }}</span>
                    </VAvatar>
                    <div>
                      <div class="font-weight-bold">{{ assignment.roleName }}</div>
                      <div class="text-caption text-medium-emphasis">Signing Order: {{ assignment.order }}</div>
                    </div>
                  </div>
                  
                  <div class="d-flex gap-4">
                    <VTextField
                      v-model="assignment.name"
                      label="Full Name"
                      density="compact"
                      variant="outlined"
                      hide-details
                      class="flex-grow-1"
                    />
                    <VTextField
                      v-model="assignment.email"
                      label="Email Address"
                      type="email"
                      density="compact"
                      variant="outlined"
                      hide-details
                      class="flex-grow-1"
                    />
                  </div>
                </div>
              </div>
            </div>
            
            <VAlert
              v-else
              type="info"
              variant="tonal"
              class="mb-4"
            >
              This template has no defined roles. You can add signers later.
            </VAlert>

            <VAlert v-if="error" type="error" variant="tonal" class="mt-4">
              {{ error }}
            </VAlert>
          </VCardText>
          
          <VDivider />
          
          <VCardActions class="pa-4">
            <VSpacer />
            <VBtn variant="text" to="/templates" :disabled="processing">Cancel</VBtn>
            <VBtn 
              color="primary" 
              @click="createDocument" 
              :loading="processing"
              :disabled="!isValid"
              prepend-icon="ri-article-line"
            >
              Create & Prepare
            </VBtn>
          </VCardActions>
        </VCard>
      </template>
    </VContainer>
  </div>
</template>

<style scoped>
.gap-4 { gap: 16px; }
.role-card {
  transition: all 0.2s;
}
.role-card:focus-within {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
</style>
