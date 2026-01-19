<script setup>
import { useSessionTimeout } from '@/composables/useSessionTimeout'

const { showWarning, timeRemaining, formatTimeRemaining, extendSession } = useSessionTimeout({
  timeoutMinutes: 30,
  warningMinutes: 5,
})
</script>

<template>
  <!-- Session Timeout Warning Dialog -->
  <VDialog
    v-model="showWarning"
    max-width="500"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon
          icon="mdi-clock-alert"
          color="warning"
          class="mr-2"
        />
        Session Expiring Soon
      </VCardTitle>

      <VCardText>
        <VAlert
          type="warning"
          variant="tonal"
          class="mb-4"
        >
          Your session will expire in <strong>{{ formatTimeRemaining }}</strong> due to inactivity.
        </VAlert>

        <p>Click "Stay Logged In" to continue your session, or you will be automatically logged out.</p>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          variant="elevated"
          @click="extendSession"
        >
          Stay Logged In
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
