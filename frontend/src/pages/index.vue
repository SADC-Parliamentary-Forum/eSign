<script setup>
import { computed } from 'vue'
import InitiatorDashboard from '@/components/dashboards/InitiatorDashboard.vue'
import SignerDashboard from '@/components/dashboards/SignerDashboard.vue'
import ExecutiveDashboard from '@/components/dashboards/ExecutiveDashboard.vue'

// Get user from store or localStorage
const user = computed(() => {
  // This would come from your auth store
  const storedUser = localStorage.getItem('user')
  return storedUser ? JSON.parse(storedUser) : null
})

// Determine dashboard type based on user role
const dashboardType = computed(() => {
  if (!user.value) return 'initiator'
  
  const role = user.value.role?.name?.toLowerCase() || ''
  
  // Executive/Read-only roles (SG, CEO, etc.)
  if (role.includes('sg') || role.includes('executive') || role.includes('ceo')) {
    return 'executive'
  }
  
  // Signer/Approver roles (those who primarily sign documents)
  if (role.includes('signer') || role.includes('approver') || role.includes('finance')) {
    return 'signer'
  }
  
  // Default to initiator (document creators)
  return 'initiator'
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h2 class="text-h4 font-weight-bold">
          Dashboard
        </h2>
        <div class="text-body-1 text-medium-emphasis">
          Welcome back, {{ user?.name || 'User' }}
        </div>
      </div>
      
      <v-btn
        v-if="dashboardType !== 'executive'"
        prepend-icon="mdi-upload"
        color="primary"
        to="/upload"
      >
        New Document
      </v-btn>
    </div>

    <!-- Role-based Dashboard -->
    <initiator-dashboard v-if="dashboardType === 'initiator'" />
    <signer-dashboard v-else-if="dashboardType === 'signer'" />
    <executive-dashboard v-else-if="dashboardType === 'executive'" />
  </div>
</template>
