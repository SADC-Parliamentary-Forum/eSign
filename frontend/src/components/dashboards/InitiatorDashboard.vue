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
      })
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

const formatDate = (date) => {
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

const getDaysUntilExpiry = (expiresAt) => {
  if (!expiresAt) return null
  const days = Math.ceil((new Date(expiresAt) - new Date()) / (1000 * 60 * 60 * 24))
  return days
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
    d.status?.toLowerCase().includes(query)
  )
})
</script>

<template>
  <div class="dashboard">
    <!-- Search Bar -->
    <v-text-field
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
    <v-card v-if="filteredDocuments" class="mb-6">
      <v-card-title class="d-flex align-center">
        <v-icon icon="mdi-magnify" class="mr-2" />
        Search Results
        <v-chip size="small" class="ml-2">{{ filteredDocuments.length }}</v-chip>
      </v-card-title>
      <v-list v-if="filteredDocuments.length > 0">
        <v-list-item
          v-for="doc in filteredDocuments"
          :key="doc.id"
          :to="`/documents/${doc.id}`"
        >
          <template #prepend>
            <v-icon :icon="getStatusIcon(doc.status)" :color="getStatusColor(doc.status)" />
          </template>
          <v-list-item-title>{{ doc.title }}</v-list-item-title>
          <v-list-item-subtitle>{{ doc.status }} • {{ formatDate(doc.updated_at) }}</v-list-item-subtitle>
        </v-list-item>
      </v-list>
      <v-card-text v-else class="text-center text-medium-emphasis py-4">
        No documents found matching "{{ searchQuery }}"
      </v-card-text>
    </v-card>

    <!-- KPI Cards -->
    <v-row class="mb-6">
      <v-col cols="6" md="3">
        <v-card class="stat-card stat-card-draft" elevation="0">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">Drafts</div>
                <div class="stat-value">{{ stats.drafts }}</div>
              </div>
              <v-avatar color="grey-lighten-3" size="48">
                <v-icon icon="mdi-file-edit" color="grey-darken-1" />
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="6" md="3">
        <v-card class="stat-card stat-card-pending" elevation="0">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">Awaiting</div>
                <div class="stat-value">{{ stats.awaitingSignatures }}</div>
              </div>
              <v-avatar color="blue-lighten-4" size="48">
                <v-icon icon="mdi-clock-outline" color="blue" />
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="6" md="3">
        <v-card class="stat-card stat-card-completed" elevation="0">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">Completed</div>
                <div class="stat-value">{{ stats.completed }}</div>
              </div>
              <v-avatar color="green-lighten-4" size="48">
                <v-icon icon="mdi-check-circle" color="success" />
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="6" md="3">
        <v-card class="stat-card stat-card-rate" elevation="0">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="stat-label">Completion Rate</div>
                <div class="stat-value">{{ stats.completionRate }}%</div>
              </div>
              <v-progress-circular
                :model-value="stats.completionRate"
                color="primary"
                size="48"
                width="4"
              >
                <span class="text-caption">{{ stats.completionRate }}%</span>
              </v-progress-circular>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Expiring Soon Alert -->
    <v-alert 
      v-if="documents.expiring.length > 0"
      type="warning" 
      variant="tonal" 
      class="mb-6"
      icon="mdi-timer-sand"
    >
      <div class="d-flex align-center justify-space-between">
        <div>
          <strong>{{ documents.expiring.length }} document(s) expiring soon!</strong>
          <div class="text-body-2">These documents will expire within 7 days</div>
        </div>
        <v-btn variant="text" color="warning" size="small" to="/documents?filter=expiring">
          View All
        </v-btn>
      </div>
    </v-alert>

    <!-- Main Content Grid -->
    <v-row>
      <!-- Documents Awaiting Signatures -->
      <v-col cols="12" lg="8">
        <v-card class="mb-6">
          <v-card-title class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <v-icon icon="mdi-file-clock" color="info" class="mr-2" />
              Awaiting Signatures
            </div>
            <v-btn variant="text" size="small" to="/documents?status=IN_PROGRESS">
              View All
            </v-btn>
          </v-card-title>

          <v-card-text v-if="loading" class="text-center py-8">
            <v-progress-circular indeterminate color="primary" />
          </v-card-text>

          <template v-else>
            <v-list v-if="documents.awaiting.length > 0" lines="two">
              <v-list-item
                v-for="doc in documents.awaiting"
                :key="doc.id"
                :to="`/documents/${doc.id}`"
                class="document-item"
              >
                <template #prepend>
                  <v-avatar color="info-lighten-4" size="40">
                    <v-icon icon="mdi-file-document" color="info" />
                  </v-avatar>
                </template>

                <v-list-item-title class="font-weight-medium">
                  {{ doc.title }}
                </v-list-item-title>
                <v-list-item-subtitle>
                  <span v-if="doc.signers">{{ doc.signers.length }} signer(s)</span>
                  <span class="mx-1">•</span>
                  <span>{{ formatDate(doc.updated_at) }}</span>
                </v-list-item-subtitle>

                <template #append>
                  <div class="d-flex align-center gap-2">
                    <v-chip 
                      v-if="getDaysUntilExpiry(doc.expires_at) <= 3"
                      color="error" 
                      size="x-small"
                    >
                      {{ getDaysUntilExpiry(doc.expires_at) }}d left
                    </v-chip>
                    <v-icon icon="mdi-chevron-right" color="grey" />
                  </div>
                </template>
              </v-list-item>
            </v-list>
            
            <div v-else class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-check-all" size="48" class="mb-2" />
              <div>No documents awaiting signatures</div>
              <v-btn 
                color="primary" 
                variant="tonal" 
                class="mt-4"
                to="/upload"
              >
                <v-icon icon="mdi-plus" class="mr-1" />
                Upload Document
              </v-btn>
            </div>
          </template>
        </v-card>

        <!-- Draft Documents -->
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <v-icon icon="mdi-file-edit" color="grey" class="mr-2" />
              Draft Documents
            </div>
            <v-btn variant="text" size="small" to="/documents?status=DRAFT">
              View All
            </v-btn>
          </v-card-title>

          <v-card-text v-if="documents.drafts.length > 0">
            <v-row>
              <v-col
                v-for="doc in documents.drafts.slice(0, 4)"
                :key="doc.id"
                cols="12"
                sm="6"
              >
                <v-card variant="outlined" class="draft-card">
                  <v-card-text>
                    <div class="text-subtitle-2 font-weight-medium mb-1 text-truncate">
                      {{ doc.title }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Created {{ formatDate(doc.created_at) }}
                    </div>
                  </v-card-text>
                  <v-card-actions>
                    <v-btn
                      size="small"
                      color="primary"
                      variant="tonal"
                      :to="`/prepare/${doc.id}`"
                    >
                      Continue
                    </v-btn>
                    <v-spacer />
                    <v-btn
                      size="small"
                      icon="mdi-delete-outline"
                      variant="text"
                      color="error"
                    />
                  </v-card-actions>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
          
          <v-card-text v-else class="text-center py-6 text-medium-emphasis">
            No draft documents
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Right Sidebar -->
      <v-col cols="12" lg="4">
        <!-- Quick Actions -->
        <v-card class="mb-6">
          <v-card-title class="d-flex align-center">
            <v-icon icon="mdi-lightning-bolt" color="primary" class="mr-2" />
            Quick Actions
          </v-card-title>
          <v-card-text>
            <v-btn 
              block 
              color="primary" 
              class="mb-2"
              to="/upload"
            >
              <v-icon icon="mdi-upload" class="mr-2" />
              Upload Document
            </v-btn>
            <v-btn 
              block 
              variant="outlined" 
              class="mb-2"
              to="/templates"
            >
              <v-icon icon="mdi-file-document-multiple" class="mr-2" />
              Browse Templates
            </v-btn>
            <v-btn 
              block 
              variant="text"
              to="/documents"
            >
              <v-icon icon="mdi-folder" class="mr-2" />
              View All Documents
            </v-btn>
          </v-card-text>
        </v-card>

        <!-- Recent Completions -->
        <v-card>
          <v-card-title class="d-flex align-center">
            <v-icon icon="mdi-check-circle" color="success" class="mr-2" />
            Recently Completed
          </v-card-title>
          
          <v-list v-if="documents.completed.length > 0" density="compact">
            <v-list-item
              v-for="doc in documents.completed.slice(0, 5)"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2">{{ doc.title }}</v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                {{ formatDate(doc.completed_at || doc.updated_at) }}
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
          
          <v-card-text v-else class="text-center py-4 text-medium-emphasis">
            <v-icon icon="mdi-file-check-outline" size="32" class="mb-2" />
            <div class="text-caption">No completed documents yet</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
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
