<script setup>
import { $api } from '@/utils/api'

const loading = ref(false)

const stats = ref({
  highValuePending: 0,
  bottlenecks: [],
  complianceAlerts: [],
  avgTimePerStep: {},
})

const highValueDocuments = ref([])
const bottleneckDocuments = ref([])

const THRESHOLD_AMOUNT = 20000 // Documents > $20k

onMounted(async () => {
  await loadDashboardData()
})

const loadDashboardData = async () => {
  loading.value = true
  try {
    // Load high-value pending documents
    const documents = await $api('/documents?status=IN_PROGRESS')
    
    highValueDocuments.value = documents
      .filter(doc => doc.amount && doc.amount > THRESHOLD_AMOUNT)
      .sort((a, b) => b.amount - a.amount)
      .slice(0, 10)
    
    stats.value.highValuePending = highValueDocuments.value.length
    
    // Identify bottlenecks (documents stuck > 48 hours)
    const now = new Date()

    bottleneckDocuments.value = documents.filter(doc => {
      const updated = new Date(doc.updated_at)
      const hoursDiff = (now - updated) / 3600000
      
      return hoursDiff > 48
    })
    
    stats.value.bottlenecks = bottleneckDocuments.value
  }
  catch (error) {
    console.error('Failed to load dashboard data:', error)
  }
  finally {
    loading.value = false
  }
}

const formatCurrency = amount => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

const getBottleneckDuration = doc => {
  const now = new Date()
  const updated = new Date(doc.updated_at)
  const hours = Math.floor((now - updated) / 3600000)
  const days = Math.floor(hours / 24)
  
  if (days > 0) return `${days}d`
  
  return `${hours}h`
}
</script>

<template>
  <VContainer fluid>
    <!-- Executive Summary Cards -->
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
                size="56"
              >
                <VIcon size="32">
                  mdi-currency-usd
                </VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline">
                  High-Value Pending
                </div>
                <div class="text-h3 font-weight-bold">
                  {{ stats.highValuePending }}
                </div>
                <div class="text-caption">
                  > ${{ THRESHOLD_AMOUNT.toLocaleString() }}
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
                size="56"
              >
                <VIcon size="32">
                  mdi-alert
                </VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline">
                  Workflow Bottlenecks
                </div>
                <div class="text-h3 font-weight-bold">
                  {{ bottleneckDocuments.length }}
                </div>
                <div class="text-caption">
                  Stuck > 48 hours
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
        <VCard color="info-lighten-4">
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="info"
                size="56"
              >
                <VIcon size="32">
                  mdi-shield-check
                </VIcon>
              </VAvatar>
              <div class="ml-4">
                <div class="text-overline">
                  Compliance Alerts
                </div>
                <div class="text-h3 font-weight-bold">
                  0
                </div>
                <div class="text-caption">
                  All systems normal
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- High-Value Documents Pending Approval -->
    <VRow class="mt-4">
      <VCol cols="12">
        <DashboardWidget
          title="High-Value Documents Pending Approval"
          icon="mdi-currency-usd"
          color="warning"
          :loading="loading"
        >
          <VTable v-if="highValueDocuments.length > 0">
            <thead>
              <tr>
                <th>Document</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Current Step</th>
                <th>Age</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="doc in highValueDocuments"
                :key="doc.id"
              >
                <td>
                  <div class="font-weight-medium">
                    {{ doc.title }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    ID: {{ doc.id.substring(0, 8) }}
                  </div>
                </td>
                <td>
                  <strong>{{ formatCurrency(doc.amount) }}</strong>
                </td>
                <td>
                  <VChip
                    size="small"
                    color="info"
                  >
                    {{ doc.status }}
                  </VChip>
                </td>
                <td>
                  <span v-if="doc.workflow">
                    {{ doc.workflow.currentStep?.role || 'N/A' }}
                  </span>
                  <span
                    v-else
                    class="text-medium-emphasis"
                  >
                    No workflow
                  </span>
                </td>
                <td>
                  {{ getBottleneckDuration(doc) }}
                </td>
                <td>
                  <VBtn
                    size="small"
                    variant="text"
                    icon="mdi-eye"
                    :to="`/documents/${doc.id}`"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>

          <VEmptyState
            v-else
            icon="mdi-check-circle"
            title="No high-value documents pending"
            text="All high-value documents have been processed"
          />
        </DashboardWidget>
      </VCol>
    </VRow>

    <!-- Workflow Bottlenecks -->
    <VRow
      v-if="bottleneckDocuments.length > 0"
      class="mt-2"
    >
      <VCol cols="12">
        <DashboardWidget
          title="Workflow Bottlenecks"
          icon="mdi-alert"
          color="error"
        >
          <VAlert
            type="error"
            variant="tonal"
            class="mb-4"
          >
            <strong>{{ bottleneckDocuments.length }}</strong> document(s) are experiencing delays
          </VAlert>

          <VList>
            <VListItem
              v-for="doc in bottleneckDocuments"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <VIcon color="error">
                  mdi-clock-alert
                </VIcon>
              </template>

              <VListItemTitle>{{ doc.title }}</VListItemTitle>
              <VListItemSubtitle>
                Stuck at step: <strong>{{ doc.workflow?.currentStep?.role || 'Unknown' }}</strong>
                • {{ getBottleneckDuration(doc) }} without progress
              </VListItemSubtitle>

              <template #append>
                <VChip
                  size="small"
                  color="error"
                >
                  Delayed {{ getBottleneckDuration(doc) }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </DashboardWidget>
      </VCol>
    </VRow>

    <!-- Average Time Per Step -->
    <VRow class="mt-2">
      <VCol
        cols="12"
        md="6"
      >
        <DashboardWidget
          title="Average Time Per Step"
          icon="mdi-timer"
          color="primary"
        >
          <VList>
            <VListItem>
              <VListItemTitle>Finance Review</VListItemTitle>
              <template #append>
                <span class="text-h6">2.5h</span>
              </template>
            </VListItem>
            <VListItem>
              <VListItemTitle>SG Approval</VListItemTitle>
              <template #append>
                <span class="text-h6">4.2h</span>
              </template>
            </VListItem>
            <VListItem>
              <VListItemTitle>Legal Review</VListItemTitle>
              <template #append>
                <span class="text-h6">6.8h</span>
              </template>
            </VListItem>
          </VList>
        </DashboardWidget>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <DashboardWidget
          title="Document Trends"
          icon="mdi-chart-line"
          color="success"
        >
          <div class="text-center py-8">
            <VIcon
              icon="mdi-chart-line"
              size="64"
              color="success"
            />
            <div class="mt-4 text-h6">
              15% increase in completion rate
            </div>
            <div class="text-caption text-medium-emphasis">
              vs. last month
            </div>
          </div>
        </DashboardWidget>
      </VCol>
    </VRow>
  </VContainer>
</template>
