<script setup>
/**
 * Templates Gallery - Simplified
 * Shows templates with Edit and Use actions
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTemplateStore } from '@/stores/templates'

const router = useRouter()
const templateStore = useTemplateStore()

const loading = ref(true)
const searchQuery = ref('')
const selectedCategory = ref('All')
const showDeleteDialog = ref(false)
const templateToDelete = ref(null)

const categories = computed(() => ['All', ...templateStore.categories])

const filteredTemplates = computed(() => {
  let result = templateStore.templates || []
  
  if (selectedCategory.value !== 'All') {
    result = result.filter(t => t.category === selectedCategory.value)
  }
  
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(t => 
      t.name?.toLowerCase().includes(q) || 
      t.description?.toLowerCase().includes(q)
    )
  }
  
  return result
})

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([
      templateStore.fetchTemplates(),
      templateStore.fetchCategories(),
      templateStore.fetchMostUsed()
    ])
  } catch (e) {
    console.error('Failed to fetch templates:', e)
  } finally {
    loading.value = false
  }
})

function editTemplate(template) {
  router.push(`/templates/${template.id}/edit`)
}

function viewTemplate(template) {
  router.push(`/templates/${template.id}`)
}

async function useTemplate(template) {
  loading.value = true
  try {
    const docRes = await templateStore.createDocumentFromTemplate({
      template_id: template.id,
      title: template.name // Default title
    })
    router.push(`/documents/${docRes.id}`)
  } catch (e) {
    console.error('Failed to use template:', e)
  } finally {
    loading.value = false
  }
}

function confirmDelete(template) {
  templateToDelete.value = template
  showDeleteDialog.value = true
}

async function deleteTemplate() {
  if (!templateToDelete.value) return
  
  try {
    await templateStore.deleteTemplate(templateToDelete.value.id)
    showDeleteDialog.value = false
    templateToDelete.value = null
  } catch (e) {
    console.error('Failed to delete:', e)
  }
}

function getStatusColor(status) {
  const colors = {
    ACTIVE: 'success',
    DRAFT: 'warning',
    ARCHIVED: 'grey'
  }
  return colors[status] || 'primary'
}

function clearFilters() {
  searchQuery.value = ''
  selectedCategory.value = 'All'
}
</script>

<template>
  <div>
    <!-- Dynamic Header -->
    <div class="mb-8">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
        <div>
          <h1 class="text-h4 font-weight-bold text-primary mb-1">
            Templates
          </h1>
          <p class="text-body-1 text-medium-emphasis">
            Manage your reuseable signing blueprints efficiently.
          </p>
        </div>
        
        <div class="d-flex gap-2">
           <VBtn 
            secondary
            variant="outlined"
            prepend-icon="mdi-history"
            disabled
          >
            History
          </VBtn>
          <VBtn 
            color="primary" 
            prepend-icon="mdi-plus"
            elevation="2"
            @click="router.push('/templates/new')"
          >
            New Template
          </VBtn>
        </div>
      </div>

      <!-- Stats Cards -->
      <VRow class="mb-6">
        <VCol cols="12" sm="6" md="3">
          <VCard variant="tonal" color="primary" class="stats-card">
            <VCardText class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption font-weight-medium text-uppercase text-medium-emphasis mb-1">Total Templates</div>
                <div class="text-h4 font-weight-bold">{{ templateStore.templates.length }}</div>
              </div>
              <VIcon size="40" icon="mdi-file-document-multiple-outline" class="text-medium-emphasis opacity-50" />
            </VCardText>
          </VCard>
        </VCol>
        
        <VCol cols="12" sm="6" md="3">
          <VCard variant="tonal" color="success" class="stats-card">
            <VCardText class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption font-weight-medium text-uppercase text-medium-emphasis mb-1">Active</div>
                <div class="text-h4 font-weight-bold">{{ templateStore.activeTemplates.length }}</div>
              </div>
              <VIcon size="40" icon="mdi-check-circle-outline" class="text-medium-emphasis opacity-50" />
            </VCardText>
          </VCard>
        </VCol>
        
        <VCol cols="12" sm="6" md="3">
          <VCard variant="tonal" color="warning" class="stats-card">
            <VCardText class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption font-weight-medium text-uppercase text-medium-emphasis mb-1">Drafts</div>
                <div class="text-h4 font-weight-bold">{{ templateStore.draftTemplates.length }}</div>
              </div>
              <VIcon size="40" icon="mdi-pencil-circle-outline" class="text-medium-emphasis opacity-50" />
            </VCardText>
          </VCard>
        </VCol>
        
        <VCol cols="12" sm="6" md="3">
           <VCard variant="tonal" color="info" class="stats-card">
            <VCardText class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption font-weight-medium text-uppercase text-medium-emphasis mb-1">In Review</div>
                <div class="text-h4 font-weight-bold">{{ templateStore.pendingReviewTemplates.length }}</div>
              </div>
              <VIcon size="40" icon="mdi-clipboard-check-outline" class="text-medium-emphasis opacity-50" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Advanced Toolbar -->
      <VCard elevation="0" border class="pa-2 rounded-lg">
        <div class="d-flex flex-column flex-md-row align-center gap-4">
          <div class="flex-grow-1 w-100" style="max-width: 400px;">
            <VTextField
              v-model="searchQuery"
              placeholder="Search templates..."
              prepend-inner-icon="mdi-magnify"
              variant="plain"
              density="compact"
              hide-details
              class="search-field"
            />
          </div>
          
          <VDivider vertical class="hidden-sm-and-down mx-2" />
          
          <div class="d-flex align-center flex-wrap gap-2 w-100 overflow-x-auto">
            <span class="text-caption text-medium-emphasis text-no-wrap mr-2">Filters:</span>
            <VChipGroup v-model="selectedCategory" mandatory selected-class="text-primary" class="d-inline-flex">
              <VChip 
                v-for="cat in categories" 
                :key="cat"
                :value="cat"
                filter
                variant="text"
                size="small"
                class="filter-chip"
              >
                {{ cat }}
              </VChip>
            </VChipGroup>
          </div>
        </div>
      </VCard>
    </div>

    <!-- Most Used Section -->
    <div v-if="!searchQuery && selectedCategory === 'All' && templateStore.mostUsedTemplates.length > 0" class="mb-8">
      <h2 class="text-h6 font-weight-bold mb-4 d-flex align-center">
        <VIcon icon="mdi-fire" color="warning" class="mr-2" />
        Popular Templates
      </h2>
      <VRow>
        <VCol 
          v-for="template in templateStore.mostUsedTemplates.slice(0, 4)" 
          :key="'popular-' + template.id"
          cols="12" 
          sm="6" 
          md="4"
          lg="3"
        >
          <VCard border hover class="template-card h-100 d-flex flex-column rounded-lg overflow-hidden position-relative group">
           <!-- Reusing card structure broadly, simplified for "Use" acton focus -->
           <VCardItem class="pt-4 pb-2">
             <template #prepend>
               <VAvatar color="primary" variant="tonal" rounded size="32" class="mr-2">
                 <VIcon size="18">mdi-star</VIcon>
               </VAvatar>
             </template>
             <VCardTitle class="text-body-1 font-weight-bold pt-1 text-truncate">
               {{ template.name }}
             </VCardTitle>
             <VCardSubtitle class="text-caption mt-1">
               Used {{ template.usage_count }} times
             </VCardSubtitle>
           </VCardItem>
           <VCardText class="pb-2">
             <VBtn block color="primary" variant="flat" size="small" prepend-icon="mdi-play" @click="useTemplate(template)">
               Use Pattern
             </VBtn>
           </VCardText>
          </VCard>
        </VCol>
      </VRow>
      <VDivider class="my-6" />
    </div>

    <!-- Loading -->
    <VRow v-if="loading">
      <VCol v-for="n in 8" :key="n" cols="12" sm="6" md="4" lg="3">
        <VCard border class="rounded-lg h-100">
          <VSkeletonLoader type="image, article, actions" />
        </VCard>
      </VCol>
    </VRow>

    <!-- Empty State -->
    <VCard 
      v-else-if="filteredTemplates.length === 0" 
      class="py-16 text-center rounded-lg border-dashed bg-transparent"
      elevation="0"
      border
    >
      <VAvatar color="surface-variant" size="80" class="mb-4">
        <VIcon size="40" color="medium-emphasis">mdi-file-document-outline</VIcon>
      </VAvatar>
      <h3 class="text-h6 font-weight-bold mb-2">
        {{ searchQuery || selectedCategory !== 'All' ? 'No templates found' : 'No templates yet' }}
      </h3>
      <p class="text-body-2 text-medium-emphasis mb-6 max-w-sm mx-auto">
        {{ searchQuery || selectedCategory !== 'All' ? 'Try adjusting your search or filters to find what you are looking for.' : 'Create your first template to start processing documents faster.' }}
      </p>
      <VBtn 
        v-if="!searchQuery && selectedCategory === 'All'"
        color="primary" 
        prepend-icon="mdi-plus"
        height="44"
        class="px-6"
        @click="router.push('/templates/new')"
      >
        Create Template
      </VBtn>
      <VBtn
        v-else
        variant="text"
        color="primary"
        @click="clearFilters"
      >
        Clear Filters
      </VBtn>
    </VCard>

    <!-- Templates Grid -->
    <VRow v-else>
      <VCol 
        v-for="template in filteredTemplates" 
        :key="template.id"
        cols="12" 
        sm="6" 
        md="4"
        lg="3"
      >
        <VCard border hover class="template-card h-100 d-flex flex-column rounded-lg overflow-hidden position-relative group">
           <!-- Card Top / Preview -->
           <div class="card-preview bg-grey-lighten-4 d-flex align-center justify-center position-relative pa-4" style="height: 140px; transition: background 0.3s;">
             <VIcon size="64" color="grey-lighten-1" class="preview-icon">mdi-file-document-outline</VIcon>
             
             <!-- Overlay Actions -->
             <div class="overlay-actions position-absolute w-100 h-100 d-flex align-center justify-center gap-2" 
                  style="background: rgba(255,255,255,0.9); opacity: 0; transition: opacity 0.2s;">
                <VBtn color="primary" variant="flat" size="small" prepend-icon="mdi-pencil" @click="editTemplate(template)">
                  Edit
                </VBtn>
                <VBtn color="success" variant="flat" size="small" prepend-icon="mdi-play" @click="useTemplate(template)">
                  Use
                </VBtn>
             </div>
           </div>
           
           <!-- Card Content -->
           <VCardItem class="flex-grow-1 pt-4">
             <template #prepend>
               <VAvatar color="primary" variant="tonal" rounded size="32" class="mr-2">
                 <VIcon size="18">mdi-file-document-edit-outline</VIcon>
               </VAvatar>
             </template>
             <VCardTitle class="text-body-1 font-weight-bold pt-1 text-truncate">
               {{ template.name }}
             </VCardTitle>
             <VCardSubtitle class="d-flex align-center mt-1">
                <VChip :color="getStatusColor(template.status)" size="x-small" label class="font-weight-medium mr-2">
                  {{ template.status }}
                </VChip>
                <div class="text-caption text-medium-emphasis text-truncate" style="max-width: 120px;">
                  {{ template.category || 'Uncategorized' }}
                </div>
             </VCardSubtitle>
           </VCardItem>
           
           <VCardText class="pb-2 pt-0">
             <p v-if="template.description" class="text-caption text-medium-emphasis mb-3 text-truncate-2">
               {{ template.description }}
             </p>
             <VDivider class="mb-3" />
             <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis">
               <div class="d-flex align-center" title="Fields">
                 <VIcon size="14" class="mr-1">mdi-form-select</VIcon>
                 {{ template.fields_count || 0 }}
               </div>
               <div class="d-flex align-center" title="Usage count">
                 <VIcon size="14" class="mr-1">mdi-refresh</VIcon>
                 {{ template.usage_count || 0 }}x
               </div>
               <div class="d-flex align-center" title="Last used">
                 <VIcon size="14" class="mr-1">mdi-clock-outline</VIcon>
                 <span class="d-inline-block text-truncate" style="max-width: 80px;">
                    {{ template.updated_at ? new Date(template.updated_at).toLocaleDateString() : 'N/A' }}
                 </span>
               </div>
             </div>
           </VCardText>
           
           <!-- Actions Footer -->
           <div class="px-2 pb-2 d-flex justify-end border-t pt-2 bg-grey-lighten-5">
             <VBtn 
                variant="text" 
                size="small" 
                color="info" 
                prepend-icon="mdi-eye" 
                class="flex-grow-1 mr-1" 
                @click="viewTemplate(template)"
             >
               View Details
             </VBtn>
             
             <VMenu location="bottom end">
               <template v-slot:activator="{ props }">
                  <VBtn icon v-bind="props" variant="text" size="small" color="medium-emphasis">
                    <VIcon>mdi-dots-vertical</VIcon>
                  </VBtn>
               </template>
               <VList density="compact">
                 <VListItem prepend-icon="mdi-content-copy" title="Clone" value="clone" @click="" />
                 <VListItem prepend-icon="mdi-archive" title="Archive" value="archive" @click="" />
                 <VDivider class="my-1" />
                 <VListItem prepend-icon="mdi-delete" title="Delete" value="delete" base-color="error" @click="confirmDelete(template)" />
               </VList>
             </VMenu>
           </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Delete Dialog -->
    <VDialog v-model="showDeleteDialog" max-width="400">
      <VCard>
        <VCardTitle>Delete Template?</VCardTitle>
        <VCardText>
          Are you sure you want to delete <strong>{{ templateToDelete?.name }}</strong>?
          This cannot be undone.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showDeleteDialog = false">Cancel</VBtn>
          <VBtn color="error" variant="flat" @click="deleteTemplate">Delete</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.template-card {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid rgba(var(--v-border-color), 0.5);
  background: rgb(var(--v-theme-surface));
}

.template-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 12px 32px -8px rgba(var(--v-theme-primary), 0.15) !important;
  border-color: rgba(var(--v-theme-primary), 0.3);
}

.card-preview {
  overflow: hidden;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.1);
}

.template-card:hover .card-preview {
  background-color: rgb(var(--v-theme-surface-variant)) !important;
}

.template-card:hover .overlay-actions {
  opacity: 1 !important;
  backdrop-filter: blur(2px);
}

.template-card:hover .preview-icon {
  transform: scale(1.1);
  opacity: 0.5;
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.text-truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  height: 40px; /* Approximate height for 2 lines */
}

.stats-card {
  transition: transform 0.2s;
}
.stats-card:hover {
  transform: translateY(-2px);
}

.search-field :deep(.v-field__input) {
  padding-top: 10px;
  padding-bottom: 10px;
}

.filter-chip {
  font-weight: 500;
}
</style>
