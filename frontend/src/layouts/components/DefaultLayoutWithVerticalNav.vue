<script setup>
import navItems from '@/navigation/vertical'
import { useConfigStore } from '@core/stores/config'
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@/stores/auth'
import { useRealTimeNotifications } from '@/composables/useRealTimeNotifications'
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'


// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const configStore = useConfigStore()
const authStore = useAuthStore()

// Real-time notifications
const { setupListeners, disconnect } = useRealTimeNotifications()

onMounted(() => {
  if (authStore.isAuthenticated) {
    setupListeners()
  }
})

onUnmounted(() => {
  disconnect()
})

// Filter nav items based on role
const filteredNavItems = computed(() => {
  const role = authStore.role
  if (role === 'admin') return navItems
  
  // Exclude Admin Console for non-admins
  return navItems.filter(item => {
    // Check if the item is 'Admin Console' or has 'admin' in title/path
    if (item.title === 'Admin Console') return false
    
    return true
  })
})

// ℹ️ Provide animation name for vertical nav collapse icon.
const verticalNavHeaderActionAnimationName = ref(null)

watch([
  () => configStore.isVerticalNavCollapsed,
  () => configStore.isAppRTL,
], val => {
  if (configStore.isAppRTL)
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-back-180' : 'rotate-180'
  else
    verticalNavHeaderActionAnimationName.value = val[0] ? 'rotate-180' : 'rotate-back-180'
}, { immediate: true })

const toggleNav = (toggleOverlayNav) => {
  if (configStore.isLessThanOverlayNavBreakpoint) {
    toggleOverlayNav(true)
  } else {
    configStore.isVerticalNavHidden = !configStore.isVerticalNavHidden
  }
}
</script>

<template>
  <VerticalNavLayout :nav-items="filteredNavItems">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n2"
          @click="toggleNav(toggleVerticalOverlayNavActive)"
        >
          <VIcon icon="ri-menu-line" />
        </IconBtn>

        <NavbarThemeSwitcher />

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />

        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
</template>

<style lang="scss">
@keyframes rotate-180 {
  from { transform: rotate(0deg); }
  to { transform: rotate(180deg); }
}

@keyframes rotate-back-180 {
  from { transform: rotate(180deg); }
  to { transform: rotate(0deg); }
}

.layout-vertical-nav {
  .nav-header {
    .header-action {
      animation-duration: 0.35s;
      animation-fill-mode: forwards;
      animation-name: v-bind(verticalNavHeaderActionAnimationName);
      transform: rotate(0deg);
    }
  }
}
</style>
