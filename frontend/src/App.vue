<script setup>
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import GlobalSnackbar from '@/components/GlobalSnackbar.vue'
import initCore from '@core/initCore'
import {
  initConfigStore,
  useConfigStore,
} from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { useAppStore } from '@/stores/app'
import { onMounted } from 'vue'

import { useAuthStore } from '@/stores/auth'

const { global } = useTheme()
const appStore = useAppStore()
const authStore = useAuthStore()

onMounted(async () => {
  // Reset navigation state to ensure menu is visible
  configStore.isVerticalNavCollapsed = false
  configStore.isVerticalNavHidden = false

  // Refresh user data to ensure roles/permissions are up to date
  if (authStore.isAuthenticated) {
    await authStore.fetchUser()
  }

  // Only fetch admin settings if user is logged in and is an Admin
  if (authStore.isAuthenticated && (authStore.role === 'admin' || authStore.role === 'Admin')) {
    appStore.fetchSettings()
  }
})

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <RouterView />
      <GlobalSnackbar />

      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
