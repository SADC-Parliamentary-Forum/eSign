<script setup>
import { $api } from '@/utils/api'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const loading = ref(true)
const documents = ref([])
const totalDocuments = ref(0)
const deleteLoading = ref(null) // ID of doc being deleted
const downloadLoading = ref(null) // ID of doc being downloaded
const bulkDeleteLoading = ref(false)
const selected = ref([])

// Filters
const searchQuery = ref('')
const statusFilter = ref('')
const sortBy = ref('updated_at')
const sortDesc = ref(true)
const page = ref(1)
const itemsPerPage = ref(10)

const statuses = [
  { title: 'All Statuses', value: '' },
  { title: 'Draft', value: 'DRAFT' },
  { title: 'In Progress', value: 'IN_PROGRESS' },
  { title: 'Completed', value: 'COMPLETED' },
  { title: 'Declined', value: 'DECLINED' },
  { title: 'Expired', value: 'EXPIRED' },
]

const sortOptions = [
  { title: 'Last Updated', value: 'updated_at' },
  { title: 'Created Date', value: 'created_at' },
  { title: 'Title', value: 'title' },
]

onMounted(async () => {
  // Initialize filters from URL
  if (route.query.status) statusFilter.value = route.query.status
  if (route.query.search) searchQuery.value = route.query.search
  if (route.query.sort) sortBy.value = route.query.sort
  if (route.query.order) sortDesc.value = route.query.order === 'desc'
  if (route.query.page) page.value = parseInt(route.query.page)
  
  await loadDocuments()
})

// Sync URL with filters
watch([statusFilter, sortBy, sortDesc, page, searchQuery], () => {
  const query = {
    ...route.query,
    status: statusFilter.value || undefined,
    search: searchQuery.value || undefined,
    sort: sortBy.value,
    order: sortDesc.value ? 'desc' : 'asc',
    page: page.value,
  }
  
  // Remove undefined keys
  Object.keys(query).forEach(key => query[key] === undefined && delete query[key])
  
  router.replace({ query })
  
  // Debounce load if it's search, otherwise load immediately
  if (searchQuery.value) {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => loadDocuments(), 300)
  } else {
    loadDocuments()
  }
})

// Separate debounce just for search input typing to avoid double loads
let searchTimeout

async function loadDocuments() {
  loading.value = true
  selected.value = [] // Clear selection on reload
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) params.append('search', searchQuery.value)
    if (statusFilter.value) params.append('status', statusFilter.value)
    params.append('sort', sortBy.value)
    params.append('order', sortDesc.value ? 'desc' : 'asc')
    params.append('page', page.value)
    params.append('limit', itemsPerPage.value)
    
    const response = await $api(`/documents?${params.toString()}`)

    documents.value = response?.data || response || []
    totalDocuments.value = response?.total || documents.value.length
  } catch (error) {
    console.error('Failed to load documents:', error)
    documents.value = []
  } finally {
    loading.value = false
  }
}

// UI State
const confirmDialog = ref({
  show: false,
  title: '',
  message: '',
  confirmText: 'Delete',
  confirmColor: 'error',
  onConfirm: null,
})

const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

function showSnackbar(text, color = 'success') {
  snackbar.value = { show: true, text, color }
}

function deleteDocument(id) {
  confirmDialog.value = {
    show: true,
    title: 'Delete Document',
    message: 'Are you sure you want to delete this document? This action cannot be undone.',
    confirmText: 'Delete',
    confirmColor: 'error',
    onConfirm: () => performDeleteDocument(id),
  }
}

async function performDeleteDocument(id) {
  confirmDialog.value.show = false
  deleteLoading.value = id
  try {
    await $api(`/documents/${id}`, { method: 'DELETE' })

    // Remove from local list to avoid full reload flicker
    documents.value = documents.value.filter(d => d.id !== id)
    totalDocuments.value--
    selected.value = selected.value.filter(sid => sid !== id)
    showSnackbar('Document deleted successfully')
  } catch (e) {
    console.error('Delete failed:', e)
    showSnackbar('Failed to delete document: ' + (e.message || 'Unknown error'), 'error')
  } finally {
    deleteLoading.value = null
  }
}

function bulkDelete() {
  if (selected.value.length === 0) return
  
  confirmDialog.value = {
    show: true,
    title: 'Delete Documents',
    message: `Are you sure you want to delete ${selected.value.length} document(s)?`,
    confirmText: 'Delete All',
    confirmColor: 'error',
    onConfirm: () => performBulkDelete(),
  }
}

async function performBulkDelete() {
  confirmDialog.value.show = false
  bulkDeleteLoading.value = true
  try {
    await $api('/documents/bulk-delete', {
      method: 'POST',
      body: { ids: selected.value },
    })
    
    // Reload to refresh state
    await loadDocuments()
    selected.value = []
    showSnackbar('Documents deleted successfully')
  } catch (e) {
    console.error('Bulk delete failed:', e)
    showSnackbar('Failed to delete documents: ' + (e.message || 'Unknown error'), 'error')
  } finally {
    bulkDeleteLoading.value = false
  }
}

async function downloadEvidence(doc) {
  downloadLoading.value = doc.id
  try {
    const token = localStorage.getItem('token')

    const response = await fetch(`${import.meta.env.VITE_API_URL || '/api'}/documents/${doc.id}/evidence`, {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    })
    
    if (!response.ok) throw new Error('Download failed')
    
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')

    a.href = url
    a.download = `Evidence-${doc.id}.zip` // Default filename, browser might override from Content-Disposition
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
    showSnackbar('Download started')
  } catch (e) {
    console.error('Failed to download evidence:', e)
    showSnackbar('Failed to download evidence', 'error')
  } finally {
    downloadLoading.value = null
  }
}

function toggleSelectAll() {
  if (selected.value.length === documents.value.length) {
    selected.value = []
  } else {
    selected.value = documents.value.map(d => d.id)
  }
}

const pageCount = computed(() => Math.ceil(totalDocuments.value / itemsPerPage.value))

function getStatusColor(status) {
  switch (status) {
  case 'COMPLETED': return 'success'
  case 'IN_PROGRESS': return 'info'
  case 'DRAFT': return 'grey'
  case 'DECLINED': return 'error'
  case 'EXPIRED': return 'error'
  default: return 'grey'
  }
}

function getStatusIcon(status) {
  switch (status) {
  case 'COMPLETED': return 'ri-checkbox-circle-line'
  case 'IN_PROGRESS': return 'ri-time-line'
  case 'DRAFT': return 'ri-pencil-line'
  case 'DECLINED': return 'ri-close-circle-line'
  case 'EXPIRED': return 'ri-error-warning-line'
  default: return 'ri-file-line'
  }
}

function formatDate(dateString) {
  if (!dateString) return ''
  
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatRelativeDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now - date) / 1000)
  
  if (diffInSeconds < 60) return 'just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`
  
  return formatDate(dateString)
}
</script>

<template>
  <VContainer class="py-6">
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-bold">
          Documents
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Manage and track all your documents
        </p>
      </div>
      <VBtn
        color="primary"
        to="/upload"
        prepend-icon="ri-add-line"
      >
        New Document
      </VBtn>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <VCardText>
        <VRow align="center">
          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="ri-search-line"
              placeholder="Search documents..."
              variant="outlined"
              density="compact"
              hide-details
              clearable
            />
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <VSelect
              v-model="statusFilter"
              :items="statuses"
              label="Status"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <VSelect
              v-model="sortBy"
              :items="sortOptions"
              label="Sort By"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            md="2"
          >
            <VBtnToggle
              v-model="sortDesc"
              mandatory
              density="compact"
              class="w-100"
            >
              <VBtn
                :value="true"
                size="small"
                class="flex-grow-1"
              >
                <VIcon icon="ri-sort-desc" />
              </VBtn>
              <VBtn
                :value="false"
                size="small"
                class="flex-grow-1"
              >
                <VIcon icon="ri-sort-asc" />
              </VBtn>
            </VBtnToggle>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Results Count -->
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center">
        <VCheckbox
          :model-value="selected.length > 0 && selected.length === documents.length"
          :indeterminate="selected.length > 0 && selected.length < documents.length"
          hide-details
          density="compact"
          class="mr-2 ma-0"
          @update:model-value="toggleSelectAll"
        />
        <div class="text-body-2 text-medium-emphasis">
          <span v-if="selected.length > 0">{{ selected.length }} selected</span>
          <span v-else-if="!loading">{{ totalDocuments }} document(s)</span>
          <span v-else>Loading...</span>
        </div>
      </div>
      
      <VBtn
        v-if="selected.length > 0"
        color="error"
        prepend-icon="ri-delete-bin-line"
        variant="tonal"
        size="small"
        :loading="bulkDeleteLoading"
        @click="bulkDelete"
      >
        Delete Selected
      </VBtn>
    </div>

    <!-- Loading State -->
    <div
      v-if="loading"
      class="text-center py-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
      <div class="text-body-2 text-medium-emphasis mt-4">
        Loading documents...
      </div>
    </div>

    <!-- Documents List -->
    <template v-else>
      <VCard v-if="documents.length > 0">
        <VList
          lines="two"
          select-strategy="classic"
        >
          <VListItem
            v-for="doc in documents"
            :key="doc.id"
            :value="doc.id"
            class="document-item py-4"
            @click="router.push(`/documents/${doc.id}`)"
          >
            <template #prepend>
              <VCheckbox
                v-model="selected"
                :value="doc.id"
                density="compact"
                hide-details
                class="mr-4"
                @click.stop
              />
              <VAvatar
                :color="`${getStatusColor(doc.status)}-lighten-4`"
                size="48"
              >
                <VIcon
                  :icon="getStatusIcon(doc.status)"
                  :color="getStatusColor(doc.status)"
                />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium text-body-1">
              {{ doc.title }}
            </VListItemTitle>
            
            <VListItemSubtitle class="mt-1">
              <VChip 
                :color="getStatusColor(doc.status)" 
                size="x-small" 
                variant="tonal"
                class="mr-2"
              >
                {{ doc.status?.replace('_', ' ') }}
              </VChip>
              <span class="text-caption">
                <span v-if="doc.signers?.length">{{ doc.signers.length }} signer(s) • </span>
                Updated {{ formatRelativeDate(doc.updated_at) }}
              </span>
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center">
                <div class="d-flex flex-column align-end mr-4">
                  <div class="text-caption text-medium-emphasis">
                    {{ formatDate(doc.created_at) }}
                  </div>
                </div>

                <!-- Actions -->
                <div
                  class="d-flex"
                  @click.stop
                >
                  <VBtn 
                    v-if="doc.status === 'DRAFT'"
                    icon="ri-pencil-line" 
                    variant="text" 
                    size="small" 
                    color="primary"
                    :to="`/prepare/${doc.id}`"
                    title="Edit Document"
                  />

                  <VBtn 
                    v-if="doc.status === 'COMPLETED'"
                    icon="ri-download-line" 
                    variant="text" 
                    size="small" 
                    color="secondary"
                    :loading="downloadLoading === doc.id"
                    title="Download Signed Document"
                    @click="downloadEvidence(doc)"
                  />
                  
                  <VBtn 
                    icon="ri-delete-bin-line" 
                    variant="text" 
                    size="small" 
                    color="error"
                    :loading="deleteLoading === doc.id"
                    title="Delete Document"
                    @click="deleteDocument(doc.id)"
                  />
                  
                  <VBtn 
                    v-if="doc.status !== 'DRAFT'"
                    icon="ri-arrow-right-s-line" 
                    variant="text" 
                    size="small" 
                    @click="router.push(`/documents/${doc.id}`)"
                  />
                </div>
              </div>
            </template>
          </VListItem>
        </VList>
      </VCard>

      <!-- Empty State -->
      <VCard
        v-else
        class="text-center py-12"
      >
        <VIcon
          icon="ri-file-text-line"
          size="64"
          color="grey-lighten-1"
        />
        <div class="text-h6 mt-4">
          No documents found
        </div>
        <div class="text-body-2 text-medium-emphasis mb-4">
          {{ searchQuery || statusFilter ? 'Try adjusting your filters' : 'Get started by uploading your first document' }}
        </div>
        <VBtn
          v-if="!searchQuery && !statusFilter"
          color="primary"
          to="/upload"
          prepend-icon="ri-upload-cloud-2-line"
        >
          Upload Document
        </VBtn>
        <VBtn
          v-else
          variant="outlined"
          @click="searchQuery = ''; statusFilter = ''"
        >
          Clear Filters
        </VBtn>
      </VCard>

      <!-- Pagination -->
      <div
        v-if="pageCount > 1"
        class="d-flex justify-center mt-6"
      >
        <VPagination
          v-model="page"
          :length="pageCount"
          :total-visible="5"
          rounded
        />
      </div>
    </template>
    
    <!-- Confirm Dialog -->
    <VDialog
      v-model="confirmDialog.show"
      max-width="400"
    >
      <VCard>
        <VCardTitle class="text-h6 pt-4 px-4">
          {{ confirmDialog.title }}
        </VCardTitle>
        <VCardText class="px-4 py-2">
          {{ confirmDialog.message }}
        </VCardText>
        <VCardActions class="px-4 pb-4">
          <VSpacer />
          <VBtn
            variant="text"
            @click="confirmDialog.show = false"
          >
            Cancel
          </VBtn>
          <VBtn
            :color="confirmDialog.confirmColor"
            variant="elevated"
            @click="confirmDialog.onConfirm()"
          >
            {{ confirmDialog.confirmText }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="3000"
      location="top"
    >
      {{ snackbar.text }}
      <template #actions>
        <VBtn
          variant="text"
          icon="ri-close-line"
          @click="snackbar.show = false"
        />
      </template>
    </VSnackbar>
  </VContainer>
</template>

<style scoped>
.document-item {
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  transition: background 0.15s;
}

.document-item:hover {
  background: rgba(0, 0, 0, 0.02);
}

.document-item:last-child {
  border-bottom: none;
}
</style>
