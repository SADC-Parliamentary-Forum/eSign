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

      <v-btn
        prepend-icon="mdi-plus"
        color="primary"
        to="/templates/create"
      >
        Create Template
      </v-btn>
    </div>

    <!-- Filters & Search -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="searchQuery"
              prepend-inner-icon="mdi-magnify"
              placeholder="Search templates..."
              variant="outlined"
              density="compact"
              hide-details
              clearable
            />
          </v-col>

          <v-col cols="12" md="4">
            <v-select
              v-model="statusFilter"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="compact"
              hide-details
            />
          </v-col>

          <v-col cols="12" md="2" class="d-flex justify-end">
            <v-btn-toggle
              v-model="viewMode"
              mandatory
              variant="outlined"
              density="compact"
            >
              <v-btn value="grid" icon="mdi-view-grid" />
              <v-btn value="list" icon="mdi-view-list" />
            </v-btn-toggle>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Templates Grid/List -->
    <v-progress-linear v-if="loading" indeterminate />

    <template v-else>
      <!-- Grid View -->
      <v-row v-if="viewMode === 'grid' && filteredTemplates.length > 0">
        <v-col
          v-for="template in filteredTemplates"
          :key="template.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <template-card
            :template="template"
            @edit="$router.push(`/templates/${template.id}/edit`)"
            @activate="handleActivate"
            @delete="handleDelete"
          />
        </v-col>
      </v-row>

      <!-- List View -->
      <v-card v-else-if="viewMode === 'list' && filteredTemplates.length > 0">
        <v-list>
          <v-list-item
            v-for="template in filteredTemplates"
            :key="template.id"
            :to="`/templates/${template.id}`"
          >
            <template #prepend>
              <v-avatar :color="getStatusColor(template.status)">
                <v-icon>mdi-file-document</v-icon>
              </v-avatar>
            </template>

            <v-list-item-title>{{ template.name }}</v-list-item-title>
            <v-list-item-subtitle>
              {{ template.description || 'No description' }}
            </v-list-item-subtitle>

            <template #append>
              <div class="d-flex align-center ga-2">
                <v-chip :color="getStatusColor(template.status)" size="small">
                  {{ template.status }}
                </v-chip>
                
                <v-btn
                  icon="mdi-chevron-right"
                  variant="text"
                  size="small"
                />
              </div>
            </template>
          </v-list-item>
        </v-list>
      </v-card>

      <!-- Empty State -->
      <v-empty-state
        v-else
        icon="mdi-file-document-outline"
        title="No templates found"
        text="Create your first template to streamline document signing"
      >
        <template #actions>
          <v-btn
            color="primary"
            to="/templates/create"
          >
            Create Template
          </v-btn>
        </template>
      </v-empty-state>
    </template>

    <!-- Summary Stats -->
    <v-row v-if="!loading" class="mt-4">
      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal">
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.activeTemplates.length }}
            </div>
            <div class="text-caption">Active Templates</div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal">
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.draftTemplates.length }}
            </div>
            <div class="text-caption">Drafts</div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal">
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ templateStore.pendingReviewTemplates.length }}
            </div>
            <div class="text-caption">Pending Review</div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card variant="tonal">
          <v-card-text class="text-center">
            <div class="text-h4 font-weight-bold">
              {{ filteredTemplates.length }}
            </div>
            <div class="text-caption">Total Showing</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
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
