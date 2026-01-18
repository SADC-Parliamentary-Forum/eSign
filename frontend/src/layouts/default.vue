<script setup>
import { useConfigStore } from '@core/stores/config'
import { AppContentLayoutNav } from '@layouts/enums'
import { switchToVerticalNavOnLtOverlayNavBreakpoint } from '@layouts/utils'
import { useAuthStore } from '@/stores/auth'
import { ref, defineAsyncComponent, watch } from 'vue'

const DefaultLayoutWithHorizontalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithHorizontalNav.vue'))
const DefaultLayoutWithVerticalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithVerticalNav.vue'))
const configStore = useConfigStore()
const authStore = useAuthStore()

// ℹ️ This will switch to vertical nav when define breakpoint is reached when in horizontal nav layout

// Remove below composable usage if you are not using horizontal nav layout in your app
switchToVerticalNavOnLtOverlayNavBreakpoint()

// Ensure user data is fresh on mount (handles verification updates)
onMounted(() => {
  if (authStore.isAuthenticated) {
    authStore.fetchUser()
  }
})

const { layoutAttrs, injectSkinClasses } = useSkins()

injectSkinClasses()

// SECTION: Loading Indicator
const isFallbackStateActive = ref(false)
const refLoadingIndicator = ref(null)

watch([
  isFallbackStateActive,
  refLoadingIndicator,
], () => {
  if (isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.fallbackHandle()
  if (!isFallbackStateActive.value && refLoadingIndicator.value)
    refLoadingIndicator.value.resolveHandle()
}, { immediate: true })

const resending = ref(false)
const verificationSent = ref(false)

const resendVerification = async () => {
  resending.value = true
  try {
    const res = await fetch('/api/email/verification-notification', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      }
    })
    
    if (res.ok) {
      verificationSent.value = true
    } else if (res.status === 429) {
      // Handle rate limit
      alert('Too many requests. Please wait a moment before trying again.')
    } else {
      console.error('Failed to resend verification')
    }
  } catch (e) {
    console.error('Failed to resend verification', e)
  } finally {
    resending.value = false
  }
}
// !SECTION
</script>

<template>
  <Component
    v-bind="layoutAttrs"
    :is="configStore.appContentLayoutNav === AppContentLayoutNav.Vertical ? DefaultLayoutWithVerticalNav : DefaultLayoutWithHorizontalNav"
  >
    <AppLoadingIndicator ref="refLoadingIndicator" />

    <!-- Verification Banner -->
    <v-alert
      v-if="authStore.isAuthenticated && !authStore.user?.email_verified_at"
      color="warning"
      variant="tonal"
      class="mb-0 rounded-0"
      closable
    >
      <div class="d-flex flex-wrap align-center justify-space-between w-100 gap-2">
        <div class="d-flex align-center">
          <v-icon icon="mdi-alert" class="mr-2" size="20" />
          <span class="text-body-2">
            Your email address is not verified. Please check your email inbox for a verification link.
          </span>
        </div>
        <v-btn
          v-if="!verificationSent"
          size="small"
          variant="outlined"
          :loading="resending"
          @click="resendVerification"
        >
          Resend Link
        </v-btn>
        <span v-else class="text-caption text-success font-weight-bold">
          <v-icon icon="mdi-check" size="16" class="mr-1" />
          Link Sent!
        </span>
      </div>
    </v-alert>

    <RouterView v-slot="{ Component }">
      <Suspense
        :timeout="0"
        @fallback="isFallbackStateActive = true"
        @resolve="isFallbackStateActive = false"
      >
        <Component :is="Component" />
      </Suspense>
    </RouterView>
  </Component>
</template>

<style lang="scss">
// As we are using `layouts` plugin we need its styles to be imported
@use "@layouts/styles/default-layout";
</style>
