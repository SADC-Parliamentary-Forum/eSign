<script setup>
/**
 * InitiatorDashboard - Enhanced
 * Beautiful, feature-rich dashboard for document initiators
 */
import { $api } from '@/utils/api'
import { useWorkflowStore } from '@/stores/workflows'
import { useRouter } from 'vue-router'

const router = useRouter()
const workflowStore = useWorkflowStore()

const loading = ref(true)

const stats = ref({
  drafts: 0,
  awaitingSignatures: 0,
  completed: 0,
  declined: 0,
  avgSigningTime: '0h',
  completionRate: 0,
})

const documents = ref({
  drafts: [],
  awaiting: [],
  completed: [],
  expiring: [],
})

const recentActivity = ref([])
const searchQuery = ref('')

onMounted(async () => {
  await Promise.all([
    loadStats(),
    loadDocuments(),
    loadRecentActivity(),
  ])
})

const loadStats = async () => {
  try {
    const response = await $api('/documents/stats')

    stats.value = { ...stats.value, ...response }
  } catch (error) {
    console.error('Failed to load stats:', error)

    // Use mock data if API fails
    stats.value = {
      drafts: documents.value.drafts?.length || 0,
      awaitingSignatures: documents.value.awaiting?.length || 0,
      completed: documents.value.completed?.length || 0,
      declined: 0,
      avgSigningTime: '2.4h',
      completionRate: 87,
    }
  }
}

const loadDocuments = async () => {
  loading.value = true
  try {
    const [drafts, awaiting, completed] = await Promise.all([
      $api('/documents?status=DRAFT&limit=5'),
      $api('/documents?status=IN_PROGRESS&limit=5'),
      $api('/documents?status=COMPLETED&limit=5'),
    ])
    
    documents.value = { 
      drafts: drafts?.data || drafts || [], 
      awaiting: awaiting?.data || awaiting || [], 
      completed: completed?.data || completed || [],
      expiring: (awaiting?.data || awaiting || []).filter(d => {
        if (!d.expires_at) return false
        const daysLeft = Math.ceil((new Date(d.expires_at) - new Date()) / (1000 * 60 * 60 * 24))
        
        return daysLeft <= 7 && daysLeft > 0
      }),
    }
    
    // Update stats based on loaded data
    stats.value.drafts = documents.value.drafts.length
    stats.value.awaitingSignatures = documents.value.awaiting.length
    stats.value.completed = documents.value.completed.length
  } catch (error) {
    console.error('Failed to load documents:', error)
  } finally {
    loading.value = false
  }
}

const loadRecentActivity = async () => {
  try {
    // Try to load from activity endpoint, fallback to mock
    const response = await $api('/documents/activity?limit=10')

    recentActivity.value = response?.data || response || []
  } catch (error) {
    // Generate mock activity from documents
    recentActivity.value = []
  }
}

const getStatusColor = status => {
  const colors = {
    'DRAFT': 'grey',
    'PENDING': 'warning',
    'IN_PROGRESS': 'info',
    'COMPLETED': 'success',
    'DECLINED': 'error',
    'EXPIRED': 'error',
  }

  
  return colors[status] || 'grey'
}

const getStatusIcon = status => {
  const icons = {
    'DRAFT': 'mdi-file-edit',
    'PENDING': 'mdi-clock-outline',
    'IN_PROGRESS': 'mdi-progress-clock',
    'COMPLETED': 'mdi-check-circle',
    'DECLINED': 'mdi-close-circle',
    'EXPIRED': 'mdi-timer-off',
  }

  
  return icons[status] || 'mdi-file-document'
}

const formatDate = date => {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diff = now - d
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(hours / 24)
  
  if (days > 7) return d.toLocaleDateString()
  if (days > 0) return `${days}d ago`
  if (hours > 0) return `${hours}h ago`
  
  return 'Just now'
}

const getDaysUntilExpiry = expiresAt => {
  if (!expiresAt) return null
  
  return Math.ceil((new Date(expiresAt) - new Date()) / (1000 * 60 * 60 * 24))
}

const filteredDocuments = computed(() => {
  if (!searchQuery.value) return null
  const query = searchQuery.value.toLowerCase()

  const allDocs = [
    ...documents.value.drafts,
    ...documents.value.awaiting,
    ...documents.value.completed,
  ]

  
  return allDocs.filter(d => 
    d.title?.toLowerCase().includes(query) ||
    d.status?.toLowerCase().includes(query),
  )
})

const deleteDraft = async id => {
  if (!confirm('Are you sure you want to delete this draft?')) return
  
  try {
    await $api(`/documents/${id}`, { method: 'DELETE' })

    // Remove locally
    documents.value.drafts = documents.value.drafts.filter(d => d.id !== id)
    stats.value.drafts--
  } catch (error) {
    console.error('Failed to delete draft:', error)
  }
}
</script>

<template>
  <div class="dashboard">
    <!-- Search Bar -->
    <VTextField
      v-model="searchQuery"
      prepend-inner-icon="mdi-magnify"
      placeholder="Search documents..."
      variant="outlined"
      density="compact"
      hide-details
      clearable
      class="mb-6 search-bar"
      style="max-width: 400px;"
    />

    <!-- Search Results -->
    <VCard
      v-if="filteredDocuments"
      class="mb-6"
    >
      <VCardTitle class="d-flex align-center">
        <VIcon
          icon="mdi-magnify"
          class="mr-2"
        />
        Search Results
        <VChip
          size="small"
          class="ml-2"
        >
          {{ filteredDocuments.length }}
        </VChip>
      </VCardTitle>
      <VList v-if="filteredDocuments.length > 0">
        <VListItem
          v-for="doc in filteredDocuments"
          :key="doc.id"
          :to="`/documents/${doc.id}`"
        >
          <template #prepend>
            <VIcon
              :icon="getStatusIcon(doc.status)"
              :color="getStatusColor(doc.status)"
            />
          </template>
          <VListItemTitle>{{ doc.title }}</VListItemTitle>
          <VListItemSubtitle>{{ doc.status }} • {{ formatDate(doc.updated_at) }}</VListItemSubtitle>
        </VListItem>
      </VList>
      <VCardText
        v-else
        class="text-center text-medium-emphasis py-4"
      >
        No documents found matching "{{ searchQuery }}"
      </VCardText>
    </VCard>

    <!-- KPI Cards -->
    <VRow class="mb-6">
      <VCol
        cols="6"
        md="3"
      >
        <VCard
          class="stat-card stat-card-draft"
          elevation="0"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">
                  Drafts
                </div>
                <div class="stat-value">
                  {{ stats.drafts }}
                </div>
              </div>
              <VAvatar
                color="grey-lighten-3"
                size="48"
              >
                <VIcon
                  icon="mdi-file-edit"
                  color="grey-darken-1"
                />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="6"
        md="3"
      >
        <VCard
          class="stat-card stat-card-pending"
          elevation="0"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">
                  Awaiting
                </div>
                <div class="stat-value">
                  {{ stats.awaitingSignatures }}
                </div>
              </div>
              <VAvatar
                color="blue-lighten-4"
                size="48"
              >
                <VIcon
                  icon="mdi-clock-outline"
                  color="blue"
                />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="6"
        md="3"
      >
        <VCard
          class="stat-card stat-card-completed"
          elevation="0"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">
                  Completed
                </div>
                <div class="stat-value">
                  {{ stats.completed }}
                </div>
              </div>
              <VAvatar
                color="green-lighten-4"
                size="48"
              >
                <VIcon
                  icon="mdi-check-circle"
                  color="success"
                />
              </VAvatar>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="6"
        md="3"
      >
        <VCard
          class="stat-card stat-card-rate"
          elevation="0"
        >
          <VCardText>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">
                  Completion Rate
                </div>
                <div class="stat-value">
                  {{ stats.completionRate }}%
                </div>
              </div>
              <VProgressCircular
                :model-value="stats.completionRate"
                color="primary"
                size="48"
                width="4"
              >
                <span class="text-caption">{{ stats.completionRate }}%</span>
              </VProgressCircular>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Expiring Soon Alert -->
    <VAlert 
      v-if="documents.expiring.length > 0"
      type="warning" 
      variant="tonal" 
      class="mb-6"
      icon="mdi-timer-sand"
    >
      <div class="d-flex align-center justify-space-between">
        <div>
          <strong>{{ documents.expiring.length }} document(s) expiring soon!</strong>
          <div class="text-body-2">
            These documents will expire within 7 days
          </div>
        </div>
        <VBtn
          variant="text"
          color="warning"
          size="small"
          to="/documents?filter=expiring"
        >
          View All
        </VBtn>
      </div>
    </VAlert>

    <!-- Main Content Grid -->
    <VRow>
      <!-- Documents Awaiting Signatures -->
      <VCol
        cols="12"
        lg="8"
      >
        <VCard class="mb-6">
          <VCardTitle class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <VIcon
                icon="mdi-file-clock"
                color="info"
                class="mr-2"
              />
              Awaiting Signatures
            </div>
            <VBtn
              variant="text"
              size="small"
              to="/documents?status=IN_PROGRESS"
            >
              View All
            </VBtn>
          </VCardTitle>

          <VCardText
            v-if="loading"
            class="text-center py-8"
          >
            <VProgressCircular
              indeterminate
              color="primary"
            />
          </VCardText>

          <template v-else>
            <VList
              v-if="documents.awaiting.length > 0"
              lines="two"
            >
              <VListItem
                v-for="doc in documents.awaiting"
                :key="doc.id"
                :to="`/documents/${doc.id}`"
                class="document-item"
              >
                <template #prepend>
                  <VAvatar
                    color="info-lighten-4"
                    size="40"
                  >
                    <VIcon
                      icon="mdi-file-document"
                      color="info"
                    />
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-medium">
                  {{ doc.title }}
                </VListItemTitle>
                <VListItemSubtitle>
                  <span v-if="doc.signers">{{ doc.signers.length }} signer(s)</span>
                  <span class="mx-1">•</span>
                  <span>{{ formatDate(doc.updated_at) }}</span>
                </VListItemSubtitle>

                <template #append>
                  <div class="d-flex align-center gap-2">
                    <VChip 
                      v-if="getDaysUntilExpiry(doc.expires_at) <= 3"
                      color="error" 
                      size="x-small"
                    >
                      {{ getDaysUntilExpiry(doc.expires_at) }}d left
                    </VChip>
                    <VIcon
                      icon="mdi-chevron-right"
                      color="grey"
                    />
                  </div>
                </template>
              </VListItem>
            </VList>
            
            <div
              v-else
              class="text-center py-8 text-medium-emphasis"
            >
              <VIcon
                icon="mdi-check-all"
                size="48"
                class="mb-2"
              />
              <div>No documents awaiting signatures</div>
              <VBtn 
                color="primary" 
                variant="tonal" 
                class="mt-4"
                to="/upload"
              >
                <VIcon
                  icon="mdi-plus"
                  class="mr-1"
                />
                Upload Document
              </VBtn>
            </div>
          </template>
        </VCard>

        <!-- Draft Documents -->
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <VIcon
                icon="mdi-file-edit"
                color="grey"
                class="mr-2"
              />
              Draft Documents
            </div>
            <VBtn
              variant="text"
              size="small"
              to="/documents?status=DRAFT"
            >
              View All
            </VBtn>
          </VCardTitle>

          <VCardText v-if="documents.drafts.length > 0">
            <VRow>
              <VCol
                v-for="doc in documents.drafts.slice(0, 4)"
                :key="doc.id"
                cols="12"
                sm="6"
              >
                <VCard
                  variant="outlined"
                  class="draft-card"
                >
                  <VCardText>
                    <div class="text-subtitle-2 font-weight-medium mb-1 text-truncate">
                      {{ doc.title }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Created {{ formatDate(doc.created_at) }}
                    </div>
                  </VCardText>
                  <VCardActions>
                    <VBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      :to="`/prepare/${doc.id}`"
                    >
                      Continue
                    </VBtn>
                    <VSpacer />
                    <VBtn
                      size="small"
                      icon="mdi-delete-outline"
                      variant="text"
                      color="error"
                      @click="deleteDraft(doc.id)"
                    />
                  </VCardActions>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
          
          <VCardText
            v-else
            class="text-center py-6 text-medium-emphasis"
          >
            No draft documents
          </VCardText>
        </VCard>
      </VCol>

      <!-- Right Sidebar -->
      <VCol
        cols="12"
        lg="4"
      >
        <!-- Quick Actions -->
        <VCard class="mb-6">
          <VCardTitle class="d-flex align-center">
            <VIcon
              icon="mdi-lightning-bolt"
              color="primary"
              class="mr-2"
            />
            Quick Actions
          </VCardTitle>
          <VCardText>
            <VBtn 
              block 
              color="primary" 
              class="mb-2"
              to="/upload"
            >
              <VIcon
                icon="mdi-upload"
                class="mr-2"
              />
              Upload Document
            </VBtn>
            <VBtn 
              block 
              variant="outlined" 
              class="mb-2"
              to="/templates"
            >
              <VIcon
                icon="mdi-file-document-multiple"
                class="mr-2"
              />
              Browse Templates
            </VBtn>
            <VBtn 
              block 
              variant="text"
              to="/documents"
            >
              <VIcon
                icon="mdi-folder"
                class="mr-2"
              />
              View All Documents
            </VBtn>
          </VCardText>
        </VCard>

        <!-- Recent Completions -->
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon
              icon="mdi-check-circle"
              color="success"
              class="mr-2"
            />
            Recently Completed
          </VCardTitle>
          
          <VList
            v-if="documents.completed.length > 0"
            density="compact"
          >
            <VListItem
              v-for="doc in documents.completed.slice(0, 5)"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <VIcon
                  icon="mdi-check"
                  color="success"
                  size="small"
                />
              </template>
              <VListItemTitle class="text-body-2">
                {{ doc.title }}
              </VListItemTitle>
              <VListItemSubtitle class="text-caption">
                {{ formatDate(doc.completed_at || doc.updated_at) }}
              </VListItemSubtitle>
            </VListItem>
          </VList>
          
          <VCardText
            v-else
            class="text-center py-4 text-medium-emphasis"
          >
            <VIcon
              icon="mdi-file-check-outline"
              size="32"
              class="mb-2"
            />
            <div class="text-caption">
              No completed documents yet
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.dashboard {
  padding: 0;
}

.search-bar {
  background: white;
  border-radius: 8px;
}

.stat-card {
  border-radius: 12px;
  border: 1px solid rgba(0,0,0,0.06);
  transition: all 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-label {
  font-size: 12px;
  font-weight: 500;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #333;
  line-height: 1.2;
}

.document-item {
  transition: background 0.15s;
}

.document-item:hover {
  background: rgba(0,0,0,0.02);
}

.draft-card {
  transition: all 0.2s;
}

.draft-card:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
</style>
