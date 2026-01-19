<script setup>
import { useTemplateStore } from '@/stores/templates'
import TemplateCard from '@/components/templates/TemplateCard.vue'

const templateStore = useTemplateStore()

const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref('ALL')
const viewMode = ref('grid') // 'grid' or 'list'

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
  }
  finally {
    loading.value = false
  }
}

const filteredTemplates = computed(() => {
  let templates = [...templateStore.templates]
  
  // Filter by status
  if (statusFilter.value !== 'ALL') {
    templates = templates.filter(t => t.status === statusFilter.value)
  }
  
  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()

    templates = templates.filter(t =>
      t.name.toLowerCase().includes(query) ||
      t.description?.toLowerCase().includes(query),
    )
  }
  
  return templates
})

const handleActivate = async template => {
  try {
    await templateStore.activateTemplate(template.id)

    // Show success message
  }
  catch (error) {
    console.error('Failed to activate template:', error)
  }
}

const handleDelete = async template => {
  // Show confirmation dialog
  const confirmed = confirm(`Are you sure you want to delete "${template.name}"?`)
  if (!confirmed) return
  
  try {
    await templateStore.archiveTemplate(template.id)
  }
  catch (error) {
    console.error('Failed to delete template:', error)
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h2 class="text-h4 font-weight-bold">
          Templates
        </h2>
        <div class="text-body-1 text-medium-emphasis">
          Manage document templates and workflows
        </div>
      </div>

      <VBtn
        prepend-icon="mdi-plus"
        color="primary"
        to="/templates/create"
      >
        Create Template
      </VBtn>
    </div>

    <!-- Filters & Search -->
    <VCard class="mb-4">
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="6"
          >
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

          <VCol
            cols="12"
            md="4"
          >
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

          <VCol
            cols="12"
            md="2"
            class="d-flex justify-end"
          >
            <VBtnToggle
              v-model="viewMode"
              mandatory
              variant="outlined"
              density="compact"
            >
              <VBtn
                value="grid"
                icon="mdi-view-grid"
              />
              <VBtn
                value="list"
                icon="mdi-view-list"
              />
            </VBtnToggle>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Templates Grid/List -->
    <VProgressLinear
      v-if="loading"
      indeterminate
    />

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
            @edit="$router.push(`/templates/${template.id}/edit`)"
            @activate="handleActivate"
            @delete="handleDelete"
          />
        </VCol>
      </VRow>

      <!-- List View -->
      <VCard v-else-if="viewMode === 'list' && filteredTemplates.length > 0">
        <VList>
          <VListItem
            v-for="template in filteredTemplates"
            :key="template.id"
            :to="`/templates/${template.id}`"
          >
            <template #prepend>
              <VAvatar :color="getStatusColor(template.status)">
                <VIcon>mdi-file-document</VIcon>
              </VAvatar>
            </template>

            <VListItemTitle>{{ template.name }}</VListItemTitle>
            <VListItemSubtitle>
              {{ template.description || 'No description' }}
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center ga-2">
                <VChip
                  :color="getStatusColor(template.status)"
                  size="small"
                >
                  {{ template.status }}
                </VChip>
                
                <VBtn
                  icon="mdi-chevron-right"
                  variant="text"
                  size="small"
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
          <VBtn
            color="primary"
            to="/templates/create"
          >
            Create Template
          </VBtn>
        </template>
      </VEmptyState>
    </template>

    <!-- Summary Stats -->
    <VRow
      v-if="!loading"
      class="mt-4"
    >
      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.activeTemplates.length }}
            </div>
            <div class="text-caption">
              Active Templates
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.draftTemplates.length }}
            </div>
            <div class="text-caption">
              Drafts
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.pendingReviewTemplates.length }}
            </div>
            <div class="text-caption">
              Pending Review
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VCard variant="tonal">
          <VCardText class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ filteredTemplates.length }}
            </div>
            <div class="text-caption">
              Total Showing
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script>
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
</script>
