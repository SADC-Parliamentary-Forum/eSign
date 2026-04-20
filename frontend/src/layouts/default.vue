<script setup>
import { useConfigStore } from '@core/stores/config'
import { AppContentLayoutNav } from '@layouts/enums'
import { switchToVerticalNavOnLtOverlayNavBreakpoint } from '@layouts/utils'
import { useAuthStore } from '@/stores/auth'
import { apiFetch } from '@/utils/http'
import { ref, computed, defineAsyncComponent, watch } from 'vue'

const DefaultLayoutWithHorizontalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithHorizontalNav.vue'))
const DefaultLayoutWithVerticalNav = defineAsyncComponent(() => import('./components/DefaultLayoutWithVerticalNav.vue'))
const configStore = useConfigStore()
const authStore = useAuthStore()

// ℹ️ This will switch to vertical nav when define breakpoint is reached when in horizontal nav layout

// Remove below composable usage if you are not using horizontal nav layout in your app
switchToVerticalNavOnLtOverlayNavBreakpoint()

// When we have a token, always load user+role so name/role and admin privileges are correct
// (Router allows access based on token; without this, refresh or stale state shows null user/role)
const userReady = ref(false)
const hasToken = computed(() => !!authStore.token)
onMounted(async () => {
  if (hasToken.value) {
    await authStore.fetchUser()
  }
  userReady.value = true
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
    const res = await apiFetch('/email/verification-notification', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
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
  <!-- Brief loading until user + role are loaded when we have a token -->
  <VOverlay
    v-if="hasToken && !userReady"
    :model-value="true"
    persistent
    class="align-center justify-center"
  >
    <VProgressCircular indeterminate color="primary" size="48" />
    <p class="text-body-2 mt-2 text-medium-emphasis">Loading your account...</p>
  </VOverlay>

  <Component
    v-else
    v-bind="layoutAttrs"
    :is="configStore.appContentLayoutNav === AppContentLayoutNav.Vertical ? DefaultLayoutWithVerticalNav : DefaultLayoutWithHorizontalNav"
  >
    <AppLoadingIndicator ref="refLoadingIndicator" />

    <!-- Verification Banner -->
    <VAlert
      v-if="authStore.isAuthenticated && !authStore.user?.email_verified_at"
      color="warning"
      variant="tonal"
      class="mb-0 rounded-0"
      closable
    >
      <div class="d-flex flex-wrap align-center justify-space-between w-100 gap-2">
        <div class="d-flex align-center">
          <VIcon
            icon="mdi-alert"
            class="mr-2"
            size="20"
          />
          <span class="text-body-2">
            Your email address is not verified. Please check your email inbox for a verification link.
          </span>
        </div>
        <VBtn
          v-if="!verificationSent"
          size="small"
          variant="outlined"
          :loading="resending"
          @click="resendVerification"
        >
          Resend Link
        </VBtn>
        <span
          v-else
          class="text-caption text-success font-weight-bold"
        >
          <VIcon
            icon="mdi-check"
            size="16"
            class="mr-1"
          />
          Link Sent!
        </span>
      </div>
    </VAlert>

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
