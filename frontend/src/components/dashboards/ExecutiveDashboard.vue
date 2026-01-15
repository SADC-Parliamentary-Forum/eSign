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
  <v-container fluid>
    <!-- Executive Summary Cards -->
    <v-row>
      <v-col cols="12" md="4">
        <v-card color="warning-lighten-4">
          <v-card-text>
            <div class="d-flex align-center">
              <v-avatar color="warning" size="56">
                <v-icon size="32">
                  mdi-currency-usd
                </v-icon>
              </v-avatar>
              <div class="ml-4">
                <div class="text-overline">High-Value Pending</div>
                <div class="text-h3 font-weight-bold">
                  {{ stats.highValuePending }}
                </div>
                <div class="text-caption">
                  > ${{ THRESHOLD_AMOUNT.toLocaleString() }}
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
              <v-avatar color="error" size="56">
                <v-icon size="32">
                  mdi-alert
                </v-icon>
              </v-avatar>
              <div class="ml-4">
                <div class="text-overline">Workflow Bottlenecks</div>
                <div class="text-h3 font-weight-bold">
                  {{ bottleneckDocuments.length }}
                </div>
                <div class="text-caption">
                  Stuck > 48 hours
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card color="info-lighten-4">
          <v-card-text>
            <div class="d-flex align-center">
              <v-avatar color="info" size="56">
                <v-icon size="32">
                  mdi-shield-check
                </v-icon>
              </v-avatar>
              <div class="ml-4">
                <div class="text-overline">Compliance Alerts</div>
                <div class="text-h3 font-weight-bold">
                  0
                </div>
                <div class="text-caption">
                  All systems normal
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- High-Value Documents Pending Approval -->
    <v-row class="mt-4">
      <v-col cols="12">
        <dashboard-widget
          title="High-Value Documents Pending Approval"
          icon="mdi-currency-usd"
          color="warning"
          :loading="loading"
        >
          <v-table v-if="highValueDocuments.length > 0">
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
                  <v-chip size="small" color="info">
                    {{ doc.status }}
                  </v-chip>
                </td>
                <td>
                  <span v-if="doc.workflow">
                    {{ doc.workflow.currentStep?.role || 'N/A' }}
                  </span>
                  <span v-else class="text-medium-emphasis">
                    No workflow
                  </span>
                </td>
                <td>
                  {{ getBottleneckDuration(doc) }}
                </td>
                <td>
                  <v-btn
                    size="small"
                    variant="text"
                    icon="mdi-eye"
                    :to="`/documents/${doc.id}`"
                  />
                </td>
              </tr>
            </tbody>
          </v-table>

          <v-empty-state
            v-else
            icon="mdi-check-circle"
            title="No high-value documents pending"
            text="All high-value documents have been processed"
          />
        </dashboard-widget>
      </v-col>
    </v-row>

    <!-- Workflow Bottlenecks -->
    <v-row v-if="bottleneckDocuments.length > 0" class="mt-2">
      <v-col cols="12">
        <dashboard-widget
          title="Workflow Bottlenecks"
          icon="mdi-alert"
          color="error"
        >
          <v-alert
            type="error"
            variant="tonal"
            class="mb-4"
          >
            <strong>{{ bottleneckDocuments.length }}</strong> document(s) are experiencing delays
          </v-alert>

          <v-list>
            <v-list-item
              v-for="doc in bottleneckDocuments"
              :key="doc.id"
              :to="`/documents/${doc.id}`"
            >
              <template #prepend>
                <v-icon color="error">
                  mdi-clock-alert
                </v-icon>
              </template>

              <v-list-item-title>{{ doc.title }}</v-list-item-title>
              <v-list-item-subtitle>
                Stuck at step: <strong>{{ doc.workflow?.currentStep?.role || 'Unknown' }}</strong>
                • {{ getBottleneckDuration(doc) }} without progress
              </v-list-item-subtitle>

              <template #append>
                <v-chip size="small" color="error">
                  Delayed {{ getBottleneckDuration(doc) }}
                </v-chip>
              </template>
            </v-list-item>
          </v-list>
        </dashboard-widget>
      </v-col>
    </v-row>

    <!-- Average Time Per Step -->
    <v-row class="mt-2">
      <v-col cols="12" md="6">
        <dashboard-widget
          title="Average Time Per Step"
          icon="mdi-timer"
          color="primary"
        >
          <v-list>
            <v-list-item>
              <v-list-item-title>Finance Review</v-list-item-title>
              <template #append>
                <span class="text-h6">2.5h</span>
              </template>
            </v-list-item>
            <v-list-item>
              <v-list-item-title>SG Approval</v-list-item-title>
              <template #append>
                <span class="text-h6">4.2h</span>
              </template>
            </v-list-item>
            <v-list-item>
              <v-list-item-title>Legal Review</v-list-item-title>
              <template #append>
                <span class="text-h6">6.8h</span>
              </template>
            </v-list-item>
          </v-list>
        </dashboard-widget>
      </v-col>

      <v-col cols="12" md="6">
        <dashboard-widget
          title="Document Trends"
          icon="mdi-chart-line"
          color="success"
        >
          <div class="text-center py-8">
            <v-icon icon="mdi-chart-line" size="64" color="success" />
            <div class="mt-4 text-h6">
              15% increase in completion rate
            </div>
            <div class="text-caption text-medium-emphasis">
              vs. last month
            </div>
          </div>
        </dashboard-widget>
      </v-col>
    </v-row>
  </v-container>
</template>
