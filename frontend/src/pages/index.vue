<script setup>
import { useRouter } from 'vue-router'
import { useResponsive } from '@/composables/useResponsive'
import InitiatorDashboard from '@/components/dashboards/InitiatorDashboard.vue'
import SignerDashboard from '@/components/dashboards/SignerDashboard.vue'
import ExecutiveDashboard from '@/components/dashboards/ExecutiveDashboard.vue'

import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const { isMobile, spacing } = useResponsive()
const authStore = useAuthStore()

const user = computed(() => ({
  name: authStore.user?.name || 'User',
  email: authStore.user?.email || 'user@example.com',
  role: authStore.user?.role?.name || 'initiator',
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
    <VCard
      v-if="isMobile"
      class="mb-4"
      variant="tonal"
      color="primary"
    >
      <VCardText>
        <div class="text-h6 font-weight-bold">
          Welcome back, {{ user.name }}! 👋
        </div>
        <div class="text-caption mt-1 text-medium-emphasis">
          Securely sign, send, and manage documents with SADC PF eSign
        </div>
      </VCardText>
    </VCard>

    <!-- Desktop Welcome Bar -->
    <VAlert
      v-else
      type="info"
      variant="tonal"
      class="mb-6"
    >
      <div class="d-flex align-center justify-space-between">
        <div>
          <div class="text-h6 font-weight-bold">
            Welcome back, {{ user.name }}! 👋
          </div>
          <div class="text-caption mt-1 text-medium-emphasis">
            Securely sign, send, and manage documents with SADC PF eSign
          </div>
        </div>
        <VBtn
          prepend-icon="mdi-plus"
          color="primary"
          to="/upload"
        >
          New Document
        </VBtn>
      </div>
    </VAlert>

    <!-- Role-based Dashboard -->
    <component :is="dashboardComponent" />

    <!-- Mobile FAB -->
    <VBtn
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
