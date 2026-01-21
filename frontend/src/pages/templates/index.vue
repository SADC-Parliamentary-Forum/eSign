<script setup>
import { useTemplateStore } from '@/stores/templates'
import TemplateCard from '@/components/templates/TemplateCard.vue'

const router = useRouter()
const templateStore = useTemplateStore()

const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref('ALL')
const viewMode = ref('grid')

// Delete dialog state
const deleteDialog = ref(false)
const templateToDelete = ref(null)
const deleting = ref(false)

// Snackbar state
const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('success')

const statusOptions = [
  { value: 'ALL', title: 'All Templates' },
  { value: 'DRAFT', title: 'Drafts' },
  { value: 'REVIEW', title: 'In Review' },
  { value: 'APPROVED', title: 'Approved' },
  { value: 'ACTIVE', title: 'Active' },
  { value: 'ARCHIVED', title: 'Archived' },
]

onMounted(async () => {
  await loadTemplates()
})

const loadTemplates = async () => {
  loading.value = true
  try {
    await templateStore.fetchTemplates()
  }
  catch (error) {
    console.error('Failed to load templates:', error)
    showSnackbar('Failed to load templates', 'error')
  }
  finally {
    loading.value = false
  }
}

const filteredTemplates = computed(() => {
  let templates = [...templateStore.templates]
  
  if (statusFilter.value !== 'ALL') {
    templates = templates.filter(t => t.status === statusFilter.value)
  }
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    templates = templates.filter(t =>
      t.name.toLowerCase().includes(query) ||
      t.description?.toLowerCase().includes(query),
    )
  }
  
  return templates
})

const handleEdit = (template) => {
  router.push(`/templates/${template.id}`)
}

const handleActivate = async template => {
  try {
    await templateStore.activateTemplate(template.id)
    showSnackbar(`Template "${template.name}" activated successfully`, 'success')
  }
  catch (error) {
    console.error('Failed to activate template:', error)
    showSnackbar('Failed to activate template', 'error')
  }
}

const openDeleteDialog = (template) => {
  templateToDelete.value = template
  deleteDialog.value = true
}

const confirmDelete = async () => {
  if (!templateToDelete.value) return
  
  deleting.value = true
  try {
    await templateStore.deleteTemplate(templateToDelete.value.id)
    showSnackbar(`Template "${templateToDelete.value.name}" deleted successfully`, 'success')
    deleteDialog.value = false
    templateToDelete.value = null
  }
  catch (error) {
    console.error('Failed to delete template:', error)
    showSnackbar('Failed to delete template. Please try again.', 'error')
  }
  finally {
    deleting.value = false
  }
}

const cancelDelete = () => {
  deleteDialog.value = false
  templateToDelete.value = null
}

const showSnackbar = (message, color = 'success') => {
  snackbarMessage.value = message
  snackbarColor.value = color
  snackbar.value = true
}

function getStatusColor(status) {
  const colors = {
    DRAFT: 'grey',
    REVIEW: 'info',
    APPROVED: 'success',
    ACTIVE: 'primary',
    ARCHIVED: 'error',
  }
  return colors[status] || 'grey'
}

function canEdit(status) {
  return ['DRAFT', 'REVIEW'].includes(status)
}

function canDelete(status) {
  return ['DRAFT', 'REVIEW', 'ARCHIVED'].includes(status)
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h2 class="text-h4 font-weight-bold">Templates</h2>
        <div class="text-body-1 text-medium-emphasis">
          Manage document templates and workflows
        </div>
      </div>

      <VBtn prepend-icon="mdi-plus" color="primary" to="/templates/create">
        Create Template
      </VBtn>
    </div>

    <!-- Filters & Search -->
    <VCard class="mb-4">
      <VCardText>
        <VRow align="center">
          <VCol cols="12" md="6">
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="mdi-magnify"
              placeholder="Search templates..."
              variant="outlined"
              density="compact"
              hide-details
              clearable
            />
          </VCol>

          <VCol cols="12" md="4">
            <VSelect
              v-model="statusFilter"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>

          <VCol cols="12" md="2" class="d-flex justify-end">
            <VBtnToggle v-model="viewMode" mandatory variant="outlined" density="compact">
              <VBtn value="grid" icon="mdi-view-grid" />
              <VBtn value="list" icon="mdi-view-list" />
            </VBtnToggle>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Templates Grid/List -->
    <VProgressLinear v-if="loading" indeterminate />

    <template v-else>
      <!-- Grid View -->
      <VRow v-if="viewMode === 'grid' && filteredTemplates.length > 0">
        <VCol
          v-for="template in filteredTemplates"
          :key="template.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <TemplateCard
            :template="template"
            @edit="handleEdit"
            @activate="handleActivate"
            @delete="openDeleteDialog"
          />
        </VCol>
      </VRow>

      <!-- List View -->
      <VCard v-else-if="viewMode === 'list' && filteredTemplates.length > 0">
        <VList>
          <VListItem
            v-for="template in filteredTemplates"
            :key="template.id"
            class="py-3"
          >
            <template #prepend>
              <VAvatar :color="getStatusColor(template.status)">
                <VIcon>mdi-file-document</VIcon>
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium">
              {{ template.name }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ template.description || 'No description' }}
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center ga-2">
                <VChip :color="getStatusColor(template.status)" size="small">
                  {{ template.status }}
                </VChip>
                
                <VBtn
                  icon="mdi-eye"
                  variant="text"
                  size="small"
                  color="default"
                  :to="`/templates/${template.id}`"
                />
                
                <VBtn
                  v-if="canEdit(template.status)"
                  icon="mdi-pencil"
                  variant="text"
                  size="small"
                  color="primary"
                  @click="handleEdit(template)"
                />
                
                <VBtn
                  v-if="canDelete(template.status)"
                  icon="mdi-delete"
                  variant="text"
                  size="small"
                  color="error"
                  @click="openDeleteDialog(template)"
                />
              </div>
            </template>
          </VListItem>
        </VList>
      </VCard>

      <!-- Empty State -->
      <VEmptyState
        v-else
        icon="mdi-file-document-outline"
        title="No templates found"
        text="Create your first template to streamline document signing"
      >
        <template #actions>
          <VBtn color="primary" to="/templates/create">
            Create Template
          </VBtn>
        </template>
      </VEmptyState>
    </template>

    <!-- Summary Stats -->
    <VRow v-if="!loading" class="mt-4">
      <VCol cols="12" sm="6" md="3">
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.activeTemplates.length }}
            </div>
            <div class="text-caption">Active Templates</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.draftTemplates.length }}
            </div>
            <div class="text-caption">Drafts</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.pendingReviewTemplates.length }}
            </div>
            <div class="text-caption">Pending Review</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ filteredTemplates.length }}
            </div>
            <div class="text-caption">Total Showing</div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Delete Confirmation Dialog -->
    <VDialog v-model="deleteDialog" max-width="450" persistent>
      <VCard>
        <VCardTitle class="text-h5 d-flex align-center gap-2">
          <VIcon color="error">mdi-alert-circle</VIcon>
          Delete Template
        </VCardTitle>
        
        <VCardText>
          <p class="text-body-1 mb-2">
            Are you sure you want to delete <strong>"{{ templateToDelete?.name }}"</strong>?
          </p>
          <VAlert type="warning" variant="tonal" density="compact">
            This action cannot be undone. All associated fields, roles, and configurations will be permanently removed.
          </VAlert>
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            :disabled="deleting"
            @click="cancelDelete"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            variant="flat"
            :loading="deleting"
            @click="confirmDelete"
          >
            Delete Template
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar for notifications -->
    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      :timeout="4000"
      location="bottom end"
    >
      {{ snackbarMessage }}
      <template #actions>
        <VBtn variant="text" @click="snackbar = false">
          Close
        </VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

