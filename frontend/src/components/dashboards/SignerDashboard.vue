<script setup>
import { useWorkflowStore } from '@/stores/workflows'
import { $api } from '@/utils/api'

const workflowStore = useWorkflowStore()

const loading = ref(false)
const pendingDocuments = ref([])
const overdueDocuments = ref([])
const recentlyCompleted = ref([])

onMounted(async () => {
  await Promise.all([
    loadPendingDocuments(),
    loadOverdueDocuments(),
    loadRecentlyCompleted(),
    workflowStore.fetchUserPendingSteps(),
  ])
})

const loadPendingDocuments = async () => {
  loading.value = true
  try {
    // Get documents where user needs to sign
    const steps = await workflowStore.fetchUserPendingSteps()
    pendingDocuments.value = steps.map(step => ({
      ...step.workflow.document,
      workflowStep: step,
    }))
  }
  catch (error) {
    console.error('Failed to load pending documents:', error)
  }
  finally {
    loading.value = false
  }
}

const loadOverdueDocuments = async () => {
  try {
    // Filter expired documents
    const now = new Date()
    overdueDocuments.value = pendingDocuments.value.filter(doc => {
      return doc.expires_at && new Date(doc.expires_at) < now
    })
  }
  catch (error) {
    console.error('Failed to load overdue documents:', error)
  }
}

const loadRecentlyCompleted = async () => {
  try {
    const response = await $api('/documents?status=COMPLETED&limit=5')
    recentlyCompleted.value = response
  }
  catch (error) {
    console.error('Failed to load completed documents:', error)
  }
}

const formatDueDate = date => {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diff = d - now
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(hours / 24)
  
  if (diff < 0) return 'Overdue'
  if (days > 0) return `Due in ${days}d`
  if (hours > 0) return `Due in ${hours}h`
  return 'Due soon'
}

const getDueDateColor = date => {
  if (!date) return 'grey'
  const d = new Date(date)
  const now = new Date()
  const diff = d - now
  const hours = Math.floor(diff / 3600000)
  
  if (diff < 0) return 'error'
  if (hours < 24) return 'warning'
  return 'info'
}
</script>

<template>
  <v-container fluid>
    <!-- Summary Cards -->
    <v-row>
      <v-col cols="12" md="4">
        <v-card color="warning-lighten-4">
          <v-card-text>
            <div class="d-flex align-center">
              <v-avatar color="warning" size="48">
                <v-icon>mdi-clock-alert</v-icon>
              </v-avatar>
              <div class="ml-4">
                <div class="text-overline">Pending Signatures</div>
                <div class="text-h3 font-weight-bold">
                  {{ workflowStore.pendingStepsCount }}
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card color="error-lighten-4">
          <v-card-text>
            <div class="d-flex align-center">
              <v-avatar color="error" size="48">
                <v-icon>mdi-alert</v-icon>
              </v-avatar>
              <div class="ml-4">
                <div class="text-overline">Overdue</div>
                <div class="text-h3 font-weight-bold">
                  {{ overdueDocuments.length }}
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card color="success-lighten-4">
          <v-card-text>
            <div class="d-flex align-center">
              <v-avatar color="success" size="48">
                <v-icon>mdi-check-circle</v-icon>
              </v-avatar>
              <div class="ml-4">
                <div class="text-overline">Completed</div>
                <div class="text-h3 font-weight-bold">
                  {{ recentlyCompleted.length }}
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Primary CTA: Documents Requiring Action -->
    <v-row class="mt-4">
      <v-col cols="12">
        <dashboard-widget
          title="Documents Requiring Your Signature"
          icon="mdi-pen"
          color="primary"
          :loading="loading"
        >
          <v-list v-if="pendingDocuments.length > 0">
            <v-list-item
              v-for="doc in pendingDocuments"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
              class="mb-2"
            >
              <template #prepend>
                <v-avatar :color="getDueDateColor(doc.expires_at)">
                  <v-icon>mdi-file-document</v-icon>
                </v-avatar>
              </template>

              <v-list-item-title class="font-weight-medium">
                {{ doc.title }}
              </v-list-item-title>
              
              <v-list-item-subtitle>
                Role: <strong>{{ doc.workflowStep.role }}</strong>
                <span v-if="doc.amount">
                  • Amount: ${{ doc.amount.toLocaleString() }}
                </span>
              </v-list-item-subtitle>

              <template #append>
                <div class="d-flex flex-column align-end">
                  <v-chip
                    :color="getDueDateColor(doc.expires_at)"
                    size="small"
                    class="mb-1"
                  >
                    {{ formatDueDate(doc.expires_at) }}
                  </v-chip>
                  
                  <v-btn
                    color="primary"
                    size="small"
                    :to="`/documents/${doc.id}`"
                  >
                    <v-icon start>
                      mdi-pen
                    </v-icon>
                    Review & Sign
                  </v-btn>
                </div>
              </template>
            </v-list-item>
          </v-list>

          <v-empty-state
            v-else
            icon="mdi-check-all"
            title="All caught up!"
            text="You have no pending signatures"
          />
        </dashboard-widget>
      </v-col>
    </v-row>

    <!-- Overdue Signatures (if any) -->
    <v-row v-if="overdueDocuments.length > 0" class="mt-2">
      <v-col cols="12">
        <dashboard-widget
          title="Overdue Signatures"
          icon="mdi-alert"
          color="error"
        >
          <v-alert
            type="error"
            variant="tonal"
            class="mb-4"
          >
            <strong>{{ overdueDocuments.length }}</strong> document(s) require urgent attention
          </v-alert>

          <v-list>
            <v-list-item
              v-for="doc in overdueDocuments"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <v-icon color="error">
                  mdi-alert-circle
                </v-icon>
              </template>

              <v-list-item-title>{{ doc.title }}</v-list-item-title>
              <v-list-item-subtitle>
                Expired {{ formatDueDate(doc.expires_at) }}
              </v-list-item-subtitle>

              <template #append>
                <v-btn
                  color="error"
                  variant="outlined"
                  size="small"
                >
                  Sign Now
                </v-btn>
              </template>
            </v-list-item>
          </v-list>
        </dashboard-widget>
      </v-col>
    </v-row>

    <!-- Recently Completed -->
    <v-row class="mt-2">
      <v-col cols="12">
        <dashboard-widget
          title="Recently Completed"
          icon="mdi-check-circle"
          color="success"
          :action="{ label: 'View All', to: '/documents?status=COMPLETED' }"
        >
          <v-list v-if="recentlyCompleted.length > 0">
            <v-list-item
              v-for="doc in recentlyCompleted"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <v-icon color="success">
                  mdi-check-circle
                </v-icon>
              </template>

              <v-list-item-title>{{ doc.title }}</v-list-item-title>
              <v-list-item-subtitle>
                Completed {{ formatDueDate(doc.completed_at) }}
              </v-list-item-subtitle>

              <template #append>
                <v-btn
                  icon="mdi-download"
                  variant="text"
                  size="small"
                />
              </template>
            </v-list-item>
          </v-list>

          <div v-else class="text-center py-4 text-medium-emphasis">
            No completed documents yet
          </div>
        </dashboard-widget>
      </v-col>
    </v-row>
  </v-container>
</template>
