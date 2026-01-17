<script setup>
import { useRouter } from 'vue-router'
import { useResponsive } from '@/composables/useResponsive'
import InitiatorDashboard from '@/components/dashboards/InitiatorDashboard.vue'
import SignerDashboard from '@/components/dashboards/SignerDashboard.vue'
import ExecutiveDashboard from '@/components/dashboards/ExecutiveDashboard.vue'

const router = useRouter()
const { isMobile, spacing } = useResponsive()

const user = computed(() => ({
  name: localStorage.getItem('user_name') || 'User',
  email: localStorage.getItem('user_email') || 'user@example.com',
  role: localStorage.getItem('user_role') || 'initiator',
}))

const showDashboard = computed(() => {
  const role = user.value.role.toLowerCase()
  if (role === 'sg' || role === 'executive') return 'executive'
  if (role === 'finance' || role === 'approver' || role === 'signer') return 'signer'
  return 'initiator'
})

const dashboardComponent = computed(() => {
  const dashboardMap = {
    executive: ExecutiveDashboard,
    signer: SignerDashboard,
    initiator: InitiatorDashboard,
  }
  return dashboardMap[showDashboard.value] || InitiatorDashboard
})
</script>

<template>
  <div :class="{ 'pa-2': isMobile, 'pa-4': !isMobile }">
    <!-- Mobile Header -->
    <v-card v-if="isMobile" class="mb-4" variant="tonal" color="primary">
      <v-card-text>
        <div class="text-h6 font-weight-bold">
          Welcome back, {{ user.name }}! 👋
        </div>
        <div class="text-caption mt-1 text-medium-emphasis">
          Securely sign, send, and manage documents with SADC PF eSign
        </div>
      </v-card-text>
    </v-card>

    <!-- Desktop Welcome Bar -->
    <v-alert v-else type="info" variant="tonal" class="mb-6">
      <div class="d-flex align-center justify-space-between">
        <div>
          <div class="text-h6 font-weight-bold">
            Welcome back, {{ user.name }}! 👋
          </div>
          <div class="text-caption mt-1 text-medium-emphasis">
            Securely sign, send, and manage documents with SADC PF eSign
          </div>
        </div>
        <v-btn
          prepend-icon="mdi-plus"
          color="primary"
          to="/upload"
        >
          New Document
        </v-btn>
      </div>
    </v-alert>

    <!-- Role-based Dashboard -->
    <component :is="dashboardComponent" />

    <!-- Mobile FAB -->
    <v-btn
      v-if="isMobile"
      icon="mdi-plus"
      color="primary"
      size="large"
      position="fixed"
      location="bottom end"
      class="mb-4 mr-4"
      @click="router.push('/upload')"
    />
  </div>
</template>
