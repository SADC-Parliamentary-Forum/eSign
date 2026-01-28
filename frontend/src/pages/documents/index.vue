<script setup>
import { $api } from '@/utils/api'
import { useRouter, useRoute } from 'vue-router'
import { formatDateTime } from '@/utils/formatters'

const router = useRouter()
const route = useRoute()

const loading = ref(true)
const documents = ref([])
const totalDocuments = ref(0)
const deleteLoading = ref(null) // ID of doc being deleted
const downloadLoading = ref(null) // ID of doc being downloaded
const bulkDeleteLoading = ref(false)
const bulkDownloadLoading = ref(false)
const selected = ref([])

// Filters
const searchQuery = ref('')
const statusFilter = ref('')
const sortBy = ref('updated_at')
const sortDesc = ref(true)
const page = ref(1)
const itemsPerPage = ref(10)
const currentFolderId = ref(null)
const folders = ref([])
const folderBreadcrumbs = ref([])
const showCreateFolderDialog = ref(false)
const folderForm = ref({ name: '', color: '#6366f1' })
const folderLoading = ref(false)

// Move Dialog State
const showMoveDialog = ref(false)
const moveDialogLoading = ref(false)
const moveDialogFolderId = ref(null) // Current folder we are looking at in the dialog
const moveDialogFolders = ref([])
const moveDialogBreadcrumbs = ref([])
const isMoving = ref(false)
const currentFolder = ref(null) // Full details of current folder

// Edit/Delete Folder State
const showEditFolderDialog = ref(false)
const folderMenu = ref({
    show: false,
    folder: null,
    activator: null
})

function openFolderMenu(event, folder) {
    folderMenu.value = {
        show: true,
        folder: folder,
        activator: event.currentTarget
    }
}

// Load Folders
async function loadFolders() {
  try {
    const params = new URLSearchParams()
    if (currentFolderId.value) params.append('parent_id', currentFolderId.value)
    
    // If we're inside a folder, we fetch the folder details to get breadcrumbs
    if (currentFolderId.value) {
        const res = await $api(`/folders/${currentFolderId.value}`)
        currentFolder.value = res.folder // Assuming API returns { folder: ..., breadcrumbs: ... }
        folderBreadcrumbs.value = res.breadcrumbs || []
        
        const listRes = await $api(`/folders?${params.toString()}`)
        folders.value = listRes.folders
    } else {
        currentFolder.value = null
        folderBreadcrumbs.value = []
        const res = await $api('/folders')
        folders.value = res.folders
    }
  } catch (e) {
    console.error('Failed to load folders', e)
  }
}
const editFolderForm = ref({ id: null, name: '', color: '' })
const editFolderLoading = ref(false)
const showDeleteFolderDialog = ref(false)
const folderToDelete = ref(null)
const deleteFolderLoading = ref(false)

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
  if (route.query.folder) currentFolderId.value = route.query.folder
  
  await Promise.all([loadDocuments(), loadFolders()])
})

// Sync URL with filters
watch([statusFilter, sortBy, sortDesc, page, searchQuery, currentFolderId], () => {
  const query = {
    ...route.query,
    status: statusFilter.value || undefined,
    search: searchQuery.value || undefined,
    sort: sortBy.value,
    order: sortDesc.value ? 'desc' : 'asc',
    page: page.value,
    folder: currentFolderId.value || undefined,
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
    if (currentFolderId.value) params.append('folder_id', currentFolderId.value)
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

// Computed: get only completed documents from selection
const selectedCompletedDocs = computed(() => {
  return documents.value.filter(d => selected.value.includes(d.id) && d.status === 'COMPLETED')
})

async function bulkDownload() {
  if (selectedCompletedDocs.value.length === 0) {
    showSnackbar('Please select at least one completed document to download', 'warning')
    return
  }

  bulkDownloadLoading.value = true
  try {
    const token = localStorage.getItem('token')
    const ids = selectedCompletedDocs.value.map(d => d.id)

    // Use proxy in development to avoid CORS issues
    const apiUrl = import.meta.env.DEV 
      ? '/api' 
      : (import.meta.env.VITE_API_URL || '/api')

    const response = await fetch(`${apiUrl}/documents/bulk-download`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ ids }),
      // Increase timeout for bulk downloads
      signal: AbortSignal.timeout(300000), // 5 minutes
    })
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({}))
      throw new Error(error.message || 'Download failed')
    }
    
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `SignedDocuments_${new Date().toISOString().split('T')[0]}.zip`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
    
    showSnackbar(`Downloaded ${ids.length} document(s) with audit trail`)
  } catch (e) {
    console.error('Bulk download failed:', e)
    showSnackbar('Failed to download documents: ' + (e.message || 'Unknown error'), 'error')
  } finally {
    bulkDownloadLoading.value = false
  }
}

async function downloadEvidence(doc) {
  downloadLoading.value = doc.id
  try {
    const token = localStorage.getItem('token')

    // Use proxy in development to avoid CORS issues
    const apiUrl = import.meta.env.DEV 
      ? '/api' 
      : (import.meta.env.VITE_API_URL || '/api')

    const response = await fetch(`${apiUrl}/documents/${doc.id}/evidence`, {
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
  return formatDateTime(dateString)
}

function formatRelativeDate(dateString) {
  return formatDateTime(dateString)
}



async function createFolder() {
  if (!folderForm.value.name) return
  folderLoading.value = true
  try {
    await $api('/folders', {
        method: 'POST',
        body: {
            name: folderForm.value.name,
            color: folderForm.value.color,
            parent_id: currentFolderId.value
        }
    })
    showCreateFolderDialog.value = false
    folderForm.value = { name: '', color: '#6366f1' }
    showSnackbar('Folder created')
    loadFolders()
  } catch (e) {
    showSnackbar(e.message, 'error')
  } finally {
    folderLoading.value = false
  }
}



// Watch move dialog to reset state
watch(showMoveDialog, (val) => {
    if (val) {
        moveDialogFolderId.value = null // Start at root
        loadMoveDialogFolders()
    }
})

// Load folders for Move Dialog
async function loadMoveDialogFolders() {
    moveDialogLoading.value = true
    try {
        const params = new URLSearchParams()
        if (moveDialogFolderId.value) params.append('parent_id', moveDialogFolderId.value)
        
        let path = []
        if (moveDialogFolderId.value) {
            const res = await $api(`/folders/${moveDialogFolderId.value}`)
            path = res.breadcrumbs || []
            const listRes = await $api(`/folders?${params.toString()}`)
            moveDialogFolders.value = listRes.folders
        } else {
            const res = await $api('/folders')
            moveDialogFolders.value = res.folders
        }
        moveDialogBreadcrumbs.value = path
    } catch (e) {
        console.error(e)
    } finally {
        moveDialogLoading.value = false
    }
}

async function moveSelectedDocuments() {
  isMoving.value = true
  try {
     const targetId = moveDialogFolderId.value // Move to CURRENTLY OPEN folder in dialog
     await $api(`/folders/${targetId || 'root'}/move-documents`, {
         method: 'POST',
         body: { document_ids: selected.value }
     })
     showMoveDialog.value = false
     selected.value = []
     showSnackbar('Documents moved successfully')
     loadDocuments()
     loadFolders() // Refresh sidebar
  } catch (e) {
     showSnackbar(e.message, 'error')
  } finally {
     isMoving.value = false
  }
}

async function startDownLoadFolder(folder) {
    try {
        const blob = await $api(`/folders/${folder.id}/download`, {
            responseType: 'blob'
        })
        
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `${folder.name}.zip`
        document.body.appendChild(a)
        a.click()
        window.URL.revokeObjectURL(url)
        document.body.removeChild(a)
    } catch (e) {
        showSnackbar('Failed to download folder', 'error')
    }
}

function openEditFolder(folder) {
    editFolderForm.value = { id: folder.id, name: folder.name, color: folder.color }
    showEditFolderDialog.value = true
}

async function updateFolder() {
    if (!editFolderForm.value.name) return
    editFolderLoading.value = true
    try {
        await $api(`/folders/${editFolderForm.value.id}`, {
            method: 'PUT',
            body: {
                name: editFolderForm.value.name,
                color: editFolderForm.value.color
            }
        })
        showEditFolderDialog.value = false
        showSnackbar('Folder updated')
        loadFolders()
    } catch (e) {
        showSnackbar(e.message, 'error')
    } finally {
        editFolderLoading.value = false
    }
}

function openDeleteFolder(folder) {
    folderToDelete.value = folder
    showDeleteFolderDialog.value = true
}

async function deleteFolder() {
    if (!folderToDelete.value) return
    deleteFolderLoading.value = true
    try {
        await $api(`/folders/${folderToDelete.value.id}`, { method: 'DELETE' })
        showDeleteFolderDialog.value = false
        showSnackbar('Folder deleted')
        if (currentFolderId.value === folderToDelete.value.id) {
            currentFolderId.value = folderToDelete.value.parent_id // Go up
        } else {
            loadFolders()
        }
        folderToDelete.value = null
    } catch (e) {
        showSnackbar(e.message, 'error')
    } finally {
        deleteFolderLoading.value = false
    }
}

import BulkSignDialog from '@/components/documents/BulkSignDialog.vue'

const showBulkSignDialog = ref(false)
const selectedSignableDocs = computed(() => {
    return documents.value.filter(d => selected.value.includes(d.id) && d.status === 'IN_PROGRESS')
})

function onBulkSigned(results) {
    loadDocuments()
    selected.value = []
    showSnackbar(`Signed ${results.signed.length} documents successfully`)
}

</script>

<template>
  <BulkSignDialog 
    v-model="showBulkSignDialog"
    :document-ids="selectedSignableDocs.map(d => d.id)"
    @signed="onBulkSigned"
  />
  <VContainer class="py-6" fluid>
    <VRow>
      <!-- Folders Sidebar (Left) -->
      <VCol cols="12" md="3">
        <VCard class="mb-4">
            <div class="pa-4">
                <VBtn
                    block
                    color="primary"
                    prepend-icon="mdi-folder-plus-outline"
                    class="text-none"
                    @click="showCreateFolderDialog = true"
                >
                    Create New Folder
                </VBtn>
            </div>
            <VDivider />
            <div class="px-4 py-2 text-overline text-medium-emphasis">
                Folders
            </div>
            <VList density="compact" nav>
                <VListItem
                    prepend-icon="mdi-folder-home-outline"
                    title="All Documents"
                    :active="!currentFolderId"
                    color="primary"
                    @click="currentFolderId = null"
                />
            </VList>
        </VCard>
      </VCol>
      
      <!-- Main Content (Right) -->
      <VCol cols="12" md="9">
      
      <!-- Breadcrumbs & Header -->
      <div v-if="currentFolderId" class="mb-4">
          <VBreadcrumbs :items="[{ title: 'All Documents', disabled: false, id: null }, ...folderBreadcrumbs.map(b => ({ title: b.name, disabled: false, id: b.id }))]" class="pa-0 mb-2">
            <template #title="{ item }">
                 <span class="cursor-pointer text-primary text-decoration-underline" @click="currentFolderId = item.id">{{ item.title }}</span>
            </template>
            <template #divider> <VIcon icon="mdi-chevron-right" /> </template>
          </VBreadcrumbs>
          <div class="d-flex align-center gap-2">
            <VIcon v-if="currentFolder" icon="mdi-folder" :color="currentFolder.color" size="large" />
            <h2 class="text-h5 font-weight-bold">{{ folderBreadcrumbs.length ? folderBreadcrumbs[folderBreadcrumbs.length-1].name : 'Folder' }}</h2>
            
            <!-- Context Menu for Current Folder -->
             <VBtn icon="mdi-dots-vertical" variant="text" density="compact" @click.stop="openFolderMenu($event, currentFolder)" />
          </div>
      </div>
      
      <!-- Folders Grid (Main View) -->
      <div v-if="folders.length > 0" class="mb-6">
        <h3 class="text-subtitle-2 text-medium-emphasis mb-3 text-uppercase">Folders</h3>
        <VRow>
            <VCol v-for="folder in folders" :key="folder.id" cols="12" sm="6" md="4" lg="3">
                <VCard 
                    variant="outlined" 
                    class="folder-card" 
                    @click="currentFolderId = folder.id"
                    :style="{ borderLeft: `4px solid ${folder.color || '#6366f1'}` }"
                >
                    <div class="d-flex align-center pa-3">
                        <VIcon icon="mdi-folder" :color="folder.color || 'primary'" size="large" class="mr-3" />
                        <div class="text-truncate flex-grow-1 font-weight-medium">
                            {{ folder.name }}
                        </div>
                        <div class="d-inline-flex">
                            <VBtn icon="mdi-dots-vertical" variant="text" size="x-small" @click.stop="openFolderMenu($event, folder)" />
                        </div>
                    </div>
                    <div class="px-3 pb-3 text-caption text-medium-emphasis">
                        {{ folder.documents_count }} items
                    </div>
                </VCard>
            </VCol>
        </VRow>
      </div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-bold">
          Documents
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ currentFolderId ? 'Items in this folder' : 'All your documents and folders' }}
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
      
      <div class="d-flex gap-2">
        <VBtn
          v-if="selectedSignableDocs.length > 0"
          color="primary"
          prepend-icon="mdi-fountain-pen-tip"
          variant="flat"
          size="small"
          class="mr-2"
          @click="showBulkSignDialog = true"
        >
          Sign Selected ({{ selectedSignableDocs.length }})
        </VBtn>

        <VBtn
          v-if="selected.length > 0"
          color="primary"
          prepend-icon="mdi-folder-move-outline"
          variant="tonal"
          size="small"
          class="mr-2"
          @click="showMoveDialog = true"
        >
          Move to
        </VBtn>

        <VBtn
          v-if="selectedCompletedDocs.length > 0"
          color="success"
          prepend-icon="ri-download-line"
          variant="tonal"
          size="small"
          :loading="bulkDownloadLoading"
          @click="bulkDownload"
        >
          Download ({{ selectedCompletedDocs.length }})
        </VBtn>
        
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
                v-if="doc.is_self_sign"
                size="x-small"
                color="secondary"
                variant="flat"
                class="mr-2 font-weight-bold text-uppercase px-2"
              >
                Self-Signed
              </VChip>
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
    
      </VCol>
    </VRow>
    
    <!-- Create Folder Dialog -->
    <VDialog v-model="showCreateFolderDialog" max-width="400">
        <VCard>
            <VCardTitle class="px-4 pt-4">Create New Folder</VCardTitle>
            <VCardText class="px-4 pb-2">
                <VTextField v-model="folderForm.name" label="Folder Name" variant="outlined" autofocus class="mb-2" />
                <div class="d-flex align-center gap-2 mb-2">
                    <span>Color:</span>
                    <VBtn v-for="color in ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']"
                          :key="color"
                          :color="color"
                          size="x-small"
                          variant="flat"
                          icon
                          :style="{ border: folderForm.color === color ? '2px solid black' : 'none' }"
                          @click="folderForm.color = color"
                    />
                </div>
            </VCardText>
            <VCardActions class="px-4 pb-4">
                <VSpacer />
                <VBtn variant="text" @click="showCreateFolderDialog = false">Cancel</VBtn>
                <VBtn color="primary" variant="elevated" :loading="folderLoading" @click="createFolder">Create</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>

    <!-- Move Documents Dialog -->
    <VDialog v-model="showMoveDialog" max-width="500">
        <VCard>
            <VCardTitle class="px-4 pt-4 d-flex align-center justify-space-between">
                <span>Move {{ selected.length }} Documents</span>
                <VBtn icon="mdi-close" variant="text" size="small" @click="showMoveDialog = false" />
            </VCardTitle>
            
            <VDivider />
            
            <div class="px-4 py-2 bg-grey-lighten-4 d-flex align-center gap-2 text-caption">
                <VIcon icon="mdi-folder-open-outline" size="small" />
                <span class="font-weight-bold">Current Location:</span>
                <VBreadcrumbs :items="[{ title: 'Root', disabled: false, id: null }, ...moveDialogBreadcrumbs.map(b => ({ title: b.name, disabled: false, id: b.id }))]" density="compact" class="pa-0">
                    <template #title="{ item }">
                         <span class="cursor-pointer text-primary" @click="moveDialogFolderId = item.id; loadMoveDialogFolders()">{{ item.title }}</span>
                    </template>
                    <template #divider> / </template>
                </VBreadcrumbs>
            </div>
            
            <VCardText class="px-0 pb-2" style="height: 300px; overflow-y: auto;">
                <div v-if="moveDialogLoading" class="d-flex justify-center align-center h-100">
                    <VProgressCircular indeterminate color="primary" />
                </div>
                <VList v-else density="compact" nav lines="one">
                    <VListItem
                        v-if="moveDialogFolderId"
                        prepend-icon="mdi-arrow-up"
                        title=".. (Go Up)"
                        @click="moveDialogFolderId = moveDialogBreadcrumbs[moveDialogBreadcrumbs.length - 2]?.id || null; loadMoveDialogFolders()"
                        class="mb-1"
                    />

                    <VListItem
                        v-for="folder in moveDialogFolders"
                        :key="folder.id"
                        :value="folder.id"
                        color="primary"
                        @click="moveDialogFolderId = folder.id; loadMoveDialogFolders()"
                    >
                        <template #prepend>
                            <VIcon icon="mdi-folder" :color="folder.color || 'primary'" />
                        </template>
                        <VListItemTitle>{{ folder.name }}</VListItemTitle>
                        <template #append>
                            <VIcon icon="mdi-chevron-right" size="small" color="medium-emphasis" />
                        </template>
                    </VListItem>
                    
                    <div v-if="moveDialogFolders.length === 0" class="text-center pa-8 text-medium-emphasis">
                        <VIcon icon="mdi-folder-outline" size="large" class="mb-2" />
                        <div>No subfolders</div>
                    </div>
                </VList>
            </VCardText>
            
            <VDivider />
            
            <VCardActions class="px-4 pb-4 pt-3 bg-grey-lighten-5">
                <div class="text-caption text-medium-emphasis">
                    Moving to: <strong>{{ moveDialogBreadcrumbs.length ? moveDialogBreadcrumbs[moveDialogBreadcrumbs.length-1].name : 'Root' }}</strong>
                </div>
                <VSpacer />
                <VBtn variant="text" @click="showMoveDialog = false">Cancel</VBtn>
                <VBtn color="primary" variant="elevated" :loading="isMoving" @click="moveSelectedDocuments">
                    Move Here
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>

    <!-- Edit Folder Dialog -->
    <VDialog v-model="showEditFolderDialog" max-width="400">
        <VCard>
            <VCardTitle class="px-4 pt-4">Rename Folder</VCardTitle>
            <VCardText class="px-4 pb-2">
                <VTextField v-model="editFolderForm.name" label="Folder Name" variant="outlined" autofocus class="mb-2" />
                <div class="d-flex align-center gap-2 mb-2">
                    <span>Color:</span>
                    <VBtn v-for="color in ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']"
                          :key="color"
                          :color="color"
                          size="x-small"
                          variant="flat"
                          icon
                          :class="{ 'ring-2 ring-black': editFolderForm.color === color }"
                          :style="{ border: editFolderForm.color === color ? '2px solid black' : 'none' }"
                          @click="editFolderForm.color = color"
                    />
                </div>
            </VCardText>
            <VCardActions class="px-4 pb-4">
                <VSpacer />
                <VBtn variant="text" @click="showEditFolderDialog = false">Cancel</VBtn>
                <VBtn color="primary" variant="elevated" :loading="editFolderLoading" @click="updateFolder">Save</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>

    <!-- Delete Folder Dialog -->
    <VDialog v-model="showDeleteFolderDialog" max-width="400">
        <VCard>
            <VCardTitle class="px-4 pt-4 text-error">Delete Folder?</VCardTitle>
            <VCardText class="px-4 pb-2">
                <p class="mb-2">Are you sure you want to delete <strong>{{ folderToDelete?.name }}</strong>?</p>
                <VAlert type="info" variant="tonal" density="compact" class="text-caption">
                    Any documents inside this folder will be moved to the parent folder (or root). They will NOT be deleted.
                </VAlert>
                <div v-if="folderToDelete?.children_count > 0" class="mt-2 text-caption text-warning">
                    {{ folderToDelete.children_count }} subfolder(s) will also be moved up.
                </div>
            </VCardText>
            <VCardActions class="px-4 pb-4">
                <VSpacer />
                <VBtn variant="text" @click="showDeleteFolderDialog = false">Cancel</VBtn>
                <VBtn color="error" variant="elevated" :loading="deleteFolderLoading" @click="deleteFolder">Delete Folder</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>

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
    <!-- Global Folder Menu -->
    <VMenu v-model="folderMenu.show" :activator="folderMenu.activator" location="bottom end">
         <VList density="compact">
             <VListItem prepend-icon="mdi-pencil" title="Rename" @click="openEditFolder(folderMenu.folder)" />
             <VListItem prepend-icon="mdi-delete" title="Delete" color="error" @click="openDeleteFolder(folderMenu.folder)" />
             <VDivider />
             <VListItem prepend-icon="mdi-download" title="Download ZIP" @click="startDownLoadFolder(folderMenu.folder)" />
         </VList>
     </VMenu>
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

.folder-card {
    transition: all 0.2s;
    cursor: pointer;
}
.folder-card:hover {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgba(var(--v-theme-primary), 0.04);
}
</style>
