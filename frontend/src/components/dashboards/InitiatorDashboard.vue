<script setup>
import { $api } from '@/utils/api'
import { useWorkflowStore } from '@/stores/workflows'

const workflowStore = useWorkflowStore()

const loading = ref(false)
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
})

onMounted(async () => {
  await Promise.all([
    loadStats(),
    loadDocuments(),
    workflowStore.fetchUserPendingSteps(),
  ])
})

const loadStats = async () => {
  loading.value = true
  try {
    // These endpoints would need to be added to backend
    const response = await $api('/documents/stats')
    stats.value = response
  }
  catch (error) {
    console.error('Failed to load stats:', error)
  }
  finally {
    loading.value = false
  }
}

const loadDocuments = async () => {
  try {
    const [drafts, awaiting, completed] = await Promise.all([
      $api('/documents?status=DRAFT&limit=5'),
      $api('/documents?status=IN_PROGRESS&limit=5'),
      $api('/documents?status=COMPLETED&limit=5'),
    ])
    
    documents.value = { drafts, awaiting, completed }
  }
  catch (error) {
    console.error('Failed to load documents:', error)
  }
}

const getStatusColor = status => {
  switch (status) {
    case 'DRAFT': return 'grey'
    case 'IN_PROGRESS': return 'info'
    case 'COMPLETED': return 'success'
    case 'DECLINED': return 'error'
    default: return 'grey'
  }
}
</script>

<template>
  <v-container fluid>
    <!-- KPI Cards -->
    <v-row>
      <v-col cols="12" sm="6" md="3">
        <v-card color="grey-lighten-4">
          <v-card-text>
            <div class="text-overline text-medium-emphasis">
              Draft Documents
            </div>
            <div class="text-h4 font-weight-bold">
              {{ stats.drafts }}
            </div>
            <v-progress-linear
              :model-value="30"
              color="grey"
              height="4"
              class="mt-2"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card color="info-lighten-4">
          <v-card-text>
            <div class="text-overline text-medium-emphasis">
              Awaiting Signatures
            </div>
            <div class="text-h4 font-weight-bold">
              {{ stats.awaitingSignatures }}
            </div>
            <v-progress-linear
              :model-value="65"
              color="info"
              height="4"
              class="mt-2"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card color="success-lighten-4">
          <v-card-text>
            <div class="text-overline text-medium-emphasis">
              Completed
            </div>
            <div class="text-h4 font-weight-bold">
              {{ stats.completed }}
            </div>
            <v-progress-linear
              :model-value="stats.completionRate"
              color="success"
              height="4"
              class="mt-2"
            />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card color="primary-lighten-4">
          <v-card-text>
            <div class="text-overline text-medium-emphasis">
              Avg. Signing Time
            </div>
            <div class="text-h4 font-weight-bold">
              {{ stats.avgSigningTime }}
            </div>
            <div class="text-caption text-medium-emphasis mt-2">
              {{ stats.completionRate }}% completion rate
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Main Content -->
    <v-row class="mt-2">
      <!-- Documents Awaiting Signatures -->
      <v-col cols="12" md="8">
        <dashboard-widget
          title="Documents Awaiting Signatures"
          icon="mdi-file-clock"
          color="info"
          :loading="loading"
          :action="{ label: 'View All', to: '/documents' }"
        >
          <v-list v-if="documents.awaiting.length > 0">
            <v-list-item
              v-for="doc in documents.awaiting"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <v-avatar color="info">
                  <v-icon>mdi-file-document</v-icon>
                </v-avatar>
              </template>

              <v-list-item-title>{{ doc.title }}</v-list-item-title>
              <v-list-item-subtitle>
                {{ doc.signers?.length || 0 }} signers • Updated {{ formatDate(doc.updated_at) }}
              </v-list-item-subtitle>

              <template #append>
                <v-chip
                  :color="getStatusColor(doc.status)"
                  size="small"
                >
                  {{ doc.status }}
                </v-chip>
              </template>
            </v-list-item>
          </v-list>
          
          <div v-else class="text-center py-8 text-medium-emphasis">
            <v-icon icon="mdi-check-circle" size="48" class="mb-2" />
            <div>All documents signed!</div>
          </div>
        </dashboard-widget>
      </v-col>

      <!-- Quick Actions & AI Usage -->
      <v-col cols="12" md="4">
        <v-row>
          <v-col cols="12">
            <dashboard-widget
              title="Quick Actions"
              icon="mdi-lightning-bolt"
              color="primary"
            >
              <v-list>
                <v-list-item to="/upload">
                  <template #prepend>
                    <v-icon>mdi-upload</v-icon>
                  </template>
                  <v-list-item-title>Upload Document</v-list-item-title>
                </v-list-item>

                <v-list-item to="/templates/create">
                  <template #prepend>
                    <v-icon>mdi-file-plus</v-icon>
                  </template>
                  <v-list-item-title>Create Template</v-list-item-title>
                </v-list-item>

                <v-list-item to="/documents">
                  <template #prepend>
                    <v-icon>mdi-folder</v-icon>
                  </template>
                  <v-list-item-title>View All Documents</v-list-item-title>
                </v-list-item>
              </v-list>
            </dashboard-widget>
          </v-col>

          <v-col cols="12">
            <dashboard-widget
              title="AI Template Usage"
              icon="mdi-robot"
              color="purple"
            >
              <div class="text-center">
                <v-progress-circular
                  :model-value="75"
                  :size="100"
                  :width="10"
                  color="purple"
                >
                  <span class="text-h6">75%</span>
                </v-progress-circular>
                <div class="text-caption mt-2">
                  Documents using AI suggestions
                </div>
              </div>
            </dashboard-widget>
          </v-col>
        </v-row>
      </v-col>
    </v-row>

    <!-- Draft Documents -->
    <v-row class="mt-2">
      <v-col cols="12">
        <dashboard-widget
          title="Draft Documents"
          icon="mdi-file-edit"
          color="grey"
          :action="{ label: 'View All', to: '/documents?status=DRAFT' }"
        >
          <v-row v-if="documents.drafts.length > 0">
            <v-col
              v-for="doc in documents.drafts"
              :key="doc.id"
              cols="12"
              sm="6"
              md="4"
            >
              <v-card variant="outlined">
                <v-card-title class="text-subtitle-1">
                  {{ doc.title }}
                </v-card-title>
                <v-card-subtitle>
                  Created {{ formatDate(doc.created_at) }}
                </v-card-subtitle>
                <v-card-actions>
                  <v-btn
                    size="small"
                    :to="`/upload?documentId=${doc.id}`"
                  >
                    Continue
                  </v-btn>
                  <v-btn
                    size="small"
                    variant="text"
                  >
                    Delete
                  </v-btn>
                </v-card-actions>
              </v-card>
            </v-col>
          </v-row>
          
          <div v-else class="text-center py-4 text-medium-emphasis">
            No draft documents
          </div>
        </dashboard-widget>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
function formatDate(date) {
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
</script>
