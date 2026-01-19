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
  <VContainer fluid>
    <!-- Summary Cards -->
    <VRow>
      <VCol
        cols="12"
        md="4"
      >
        <VCard color="warning-lighten-4">
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="warning"
                size="48"
              >
                <VIcon>mdi-clock-alert</VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline">
                  Pending Signatures
                </div>
                <div class="text-h3 font-weight-bold">
                  {{ workflowStore.pendingStepsCount }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard color="error-lighten-4">
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="error"
                size="48"
              >
                <VIcon>mdi-alert</VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline">
                  Overdue
                </div>
                <div class="text-h3 font-weight-bold">
                  {{ overdueDocuments.length }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard color="success-lighten-4">
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="success"
                size="48"
              >
                <VIcon>mdi-check-circle</VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline">
                  Completed
                </div>
                <div class="text-h3 font-weight-bold">
                  {{ recentlyCompleted.length }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Primary CTA: Documents Requiring Action -->
    <VRow class="mt-4">
      <VCol cols="12">
        <DashboardWidget
          title="Documents Requiring Your Signature"
          icon="mdi-pen"
          color="primary"
          :loading="loading"
        >
          <VList v-if="pendingDocuments.length > 0">
            <VListItem
              v-for="doc in pendingDocuments"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
              class="mb-2"
            >
              <template #prepend>
                <VAvatar :color="getDueDateColor(doc.expires_at)">
                  <VIcon>mdi-file-document</VIcon>
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">
                {{ doc.title }}
              </VListItemTitle>
              
              <VListItemSubtitle>
                Role: <strong>{{ doc.workflowStep.role }}</strong>
                <span v-if="doc.amount">
                  • Amount: ${{ doc.amount.toLocaleString() }}
                </span>
              </VListItemSubtitle>

              <template #append>
                <div class="d-flex flex-column align-end">
                  <VChip
                    :color="getDueDateColor(doc.expires_at)"
                    size="small"
                    class="mb-1"
                  >
                    {{ formatDueDate(doc.expires_at) }}
                  </VChip>
                  
                  <VBtn
                    color="primary"
                    size="small"
                    :to="`/documents/${doc.id}`"
                  >
                    <VIcon start>
                      mdi-pen
                    </VIcon>
                    Review & Sign
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </VList>

          <VEmptyState
            v-else
            icon="mdi-check-all"
            title="All caught up!"
            text="You have no pending signatures"
          />
        </DashboardWidget>
      </VCol>
    </VRow>

    <!-- Overdue Signatures (if any) -->
    <VRow
      v-if="overdueDocuments.length > 0"
      class="mt-2"
    >
      <VCol cols="12">
        <DashboardWidget
          title="Overdue Signatures"
          icon="mdi-alert"
          color="error"
        >
          <VAlert
            type="error"
            variant="tonal"
            class="mb-4"
          >
            <strong>{{ overdueDocuments.length }}</strong> document(s) require urgent attention
          </VAlert>

          <VList>
            <VListItem
              v-for="doc in overdueDocuments"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <VIcon color="error">
                  mdi-alert-circle
                </VIcon>
              </template>

              <VListItemTitle>{{ doc.title }}</VListItemTitle>
              <VListItemSubtitle>
                Expired {{ formatDueDate(doc.expires_at) }}
              </VListItemSubtitle>

              <template #append>
                <VBtn
                  color="error"
                  variant="outlined"
                  size="small"
                >
                  Sign Now
                </VBtn>
              </template>
            </VListItem>
          </VList>
        </DashboardWidget>
      </VCol>
    </VRow>

    <!-- Recently Completed -->
    <VRow class="mt-2">
      <VCol cols="12">
        <DashboardWidget
          title="Recently Completed"
          icon="mdi-check-circle"
          color="success"
          :action="{ label: 'View All', to: '/documents?status=COMPLETED' }"
        >
          <VList v-if="recentlyCompleted.length > 0">
            <VListItem
              v-for="doc in recentlyCompleted"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <VIcon color="success">
                  mdi-check-circle
                </VIcon>
              </template>

              <VListItemTitle>{{ doc.title }}</VListItemTitle>
              <VListItemSubtitle>
                Completed {{ formatDueDate(doc.completed_at) }}
              </VListItemSubtitle>

              <template #append>
                <VBtn
                  icon="mdi-download"
                  variant="text"
                  size="small"
                />
              </template>
            </VListItem>
          </VList>

          <div
            v-else
            class="text-center py-4 text-medium-emphasis"
          >
            No completed documents yet
          </div>
        </DashboardWidget>
      </VCol>
    </VRow>
  </VContainer>
</template>
