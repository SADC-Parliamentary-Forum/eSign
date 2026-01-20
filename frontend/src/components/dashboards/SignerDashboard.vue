<script setup>
/**
 * SignerDashboard - Enhanced
 * Dashboard for users who primarily sign documents
 * Focuses on pending actions and urgency indicators
 */
import { useWorkflowStore } from '@/stores/workflows'
import { $api } from '@/utils/api'
import { useResponsive } from '@/composables/useResponsive'
import DashboardWidget from './DashboardWidget.vue'

const workflowStore = useWorkflowStore()
const { isMobile } = useResponsive()

const loading = ref(false)
const pendingDocuments = ref([])
const overdueDocuments = ref([])
const dueSoonDocuments = ref([])
const recentlyCompleted = ref([])

onMounted(async () => {
  await Promise.all([
    loadPendingDocuments(),
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
      ...step.workflow?.document,
      workflowStep: step,
    })).filter(doc => doc.id)
    
    // Categorize by urgency
    const now = new Date()
    overdueDocuments.value = pendingDocuments.value.filter(doc => {
      return doc.expires_at && new Date(doc.expires_at) < now
    })
    
    dueSoonDocuments.value = pendingDocuments.value.filter(doc => {
      if (!doc.expires_at) return false
      const expiresAt = new Date(doc.expires_at)
      const hoursLeft = (expiresAt - now) / 3600000
      return hoursLeft > 0 && hoursLeft <= 24
    })
  } catch (error) {
    console.error('Failed to load pending documents:', error)
  } finally {
    loading.value = false
  }
}

const loadRecentlyCompleted = async () => {
  try {
    const response = await $api('/documents?status=COMPLETED&limit=5&signed_by_me=true')
    recentlyCompleted.value = response?.data || response || []
  } catch (error) {
    console.error('Failed to load completed documents:', error)
  }
}

const formatDueDate = date => {
  if (!date) return 'No deadline'
  const d = new Date(date)
  const now = new Date()
  const diff = d - now
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(hours / 24)
  
  if (diff < 0) return 'Overdue'
  if (hours < 1) return 'Due very soon'
  if (hours < 24) return `Due in ${hours}h`
  if (days === 1) return 'Due tomorrow'
  if (days < 7) return `Due in ${days} days`
  return d.toLocaleDateString()
}

const getDueDateColor = date => {
  if (!date) return 'grey'
  const d = new Date(date)
  const now = new Date()
  const diff = d - now
  const hours = Math.floor(diff / 3600000)
  
  if (diff < 0) return 'error'
  if (hours < 4) return 'error'
  if (hours < 24) return 'warning'
  return 'info'
}

const getUrgencyLevel = doc => {
  if (!doc.expires_at) return 'normal'
  const now = new Date()
  const expiresAt = new Date(doc.expires_at)
  const hoursLeft = (expiresAt - now) / 3600000
  
  if (hoursLeft < 0) return 'critical'
  if (hoursLeft < 4) return 'critical'
  if (hoursLeft < 24) return 'urgent'
  if (hoursLeft < 72) return 'soon'
  return 'normal'
}

const pendingCount = computed(() => pendingDocuments.value.length)
const urgentCount = computed(() => 
  pendingDocuments.value.filter(d => ['critical', 'urgent'].includes(getUrgencyLevel(d))).length
)
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Urgent Alert Banner -->
    <VAlert 
      v-if="overdueDocuments.length > 0 || dueSoonDocuments.length > 0"
      :type="overdueDocuments.length > 0 ? 'error' : 'warning'"
      variant="tonal"
      class="mb-4"
      icon="mdi-alert-circle"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div>
          <strong v-if="overdueDocuments.length">
            {{ overdueDocuments.length }} overdue signature{{ overdueDocuments.length > 1 ? 's' : '' }}!
          </strong>
          <strong v-else>
            {{ dueSoonDocuments.length }} signature{{ dueSoonDocuments.length > 1 ? 's' : '' }} due soon!
          </strong>
          <div class="text-body-2">
            Please review and sign these documents as soon as possible.
          </div>
        </div>
      </div>
    </VAlert>

    <!-- Summary Cards -->
    <VRow class="mb-4">
      <VCol cols="12" sm="4">
        <VCard :color="urgentCount > 0 ? 'warning-lighten-4' : 'primary-lighten-4'" elevation="0">
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar :color="urgentCount > 0 ? 'warning' : 'primary'" size="56">
                <VIcon size="28">mdi-pen</VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline text-medium-emphasis">Pending Signatures</div>
                <div class="text-h3 font-weight-bold">
                  {{ pendingCount }}
                </div>
                <div v-if="urgentCount > 0" class="text-caption text-warning">
                  {{ urgentCount }} urgent
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard color="error-lighten-4" elevation="0">
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="error" size="56">
                <VIcon size="28">mdi-clock-alert</VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline text-medium-emphasis">Overdue</div>
                <div class="text-h3 font-weight-bold">
                  {{ overdueDocuments.length }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard color="success-lighten-4" elevation="0">
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar color="success" size="56">
                <VIcon size="28">mdi-check-circle</VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline text-medium-emphasis">Completed</div>
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
    <VCard class="mb-4">
      <VCardTitle class="d-flex align-center py-3">
        <VIcon icon="mdi-pen" color="primary" class="mr-2" />
        Documents Requiring Your Signature
        <VChip v-if="pendingCount" size="small" color="primary" class="ml-2">
          {{ pendingCount }}
        </VChip>
      </VCardTitle>

      <VDivider />

      <VCardText v-if="loading" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
        <div class="mt-2 text-medium-emphasis">Loading your documents...</div>
      </VCardText>

      <template v-else>
        <VList v-if="pendingDocuments.length > 0" lines="three">
          <VListItem
            v-for="doc in pendingDocuments"
            :key="doc.id"
            :to="`/documents/${doc.id}`"
            class="py-3"
          >
            <template #prepend>
              <VAvatar 
                :color="`${getDueDateColor(doc.expires_at)}-lighten-4`" 
                size="48"
              >
                <VIcon :color="getDueDateColor(doc.expires_at)">
                  {{ getUrgencyLevel(doc) === 'critical' ? 'mdi-alert' : 'mdi-file-document' }}
                </VIcon>
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-bold text-body-1">
              {{ doc.title }}
            </VListItemTitle>
            
            <VListItemSubtitle>
              <span v-if="doc.workflowStep?.role">
                <strong>Your role:</strong> {{ doc.workflowStep.role }}
              </span>
              <span v-if="doc.amount" class="ml-2">
                • Amount: ${{ doc.amount.toLocaleString() }}
              </span>
            </VListItemSubtitle>

            <VListItemSubtitle class="mt-1">
              <VChip
                :color="getDueDateColor(doc.expires_at)"
                size="small"
                variant="tonal"
              >
                <VIcon 
                  :icon="getUrgencyLevel(doc) === 'critical' ? 'mdi-alert' : 'mdi-clock'" 
                  size="14" 
                  class="mr-1" 
                />
                {{ formatDueDate(doc.expires_at) }}
              </VChip>
            </VListItemSubtitle>

            <template #append>
              <VBtn
                color="primary"
                size="small"
                :to="`/documents/${doc.id}`"
                class="ml-2"
              >
                <VIcon start size="18">mdi-pen</VIcon>
                Review & Sign
              </VBtn>
            </template>
          </VListItem>
        </VList>

        <div v-else class="text-center py-12">
          <VIcon icon="mdi-check-all" size="72" color="success" class="mb-4" />
          <div class="text-h6 mb-2">All caught up!</div>
          <div class="text-medium-emphasis">
            You have no pending signatures. Great work!
          </div>
        </div>
      </template>
    </VCard>

    <!-- Recently Completed -->
    <VCard>
      <VCardTitle class="d-flex align-center py-3">
        <VIcon icon="mdi-check-circle" color="success" class="mr-2" />
        Recently Completed
        <VSpacer />
        <VBtn variant="text" size="small" to="/documents?status=COMPLETED">
          View All
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VList v-if="recentlyCompleted.length > 0" density="compact">
        <VListItem
          v-for="doc in recentlyCompleted"
          :key="doc.id"
          :to="`/documents/${doc.id}`"
        >
          <template #prepend>
            <VIcon color="success" class="mr-3">mdi-check-circle</VIcon>
          </template>

          <VListItemTitle>{{ doc.title }}</VListItemTitle>
          <VListItemSubtitle>
            Signed {{ formatDueDate(doc.completed_at) }}
          </VListItemSubtitle>

          <template #append>
            <VBtn icon="mdi-download" variant="text" size="small" />
          </template>
        </VListItem>
      </VList>

      <VCardText v-else class="text-center py-6 text-medium-emphasis">
        <VIcon icon="mdi-file-check-outline" size="40" class="mb-2" />
        <div>No completed documents yet</div>
      </VCardText>
    </VCard>
  </VContainer>
</template>

<style scoped>
.v-list-item {
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.v-list-item:last-child {
  border-bottom: none;
}

.v-list-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.02);
}
</style>
