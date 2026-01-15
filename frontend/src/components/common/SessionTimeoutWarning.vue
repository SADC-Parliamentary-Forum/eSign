<script setup>
import { useSessionTimeout } from '@/composables/useSessionTimeout'

const { showWarning, timeRemaining, formatTimeRemaining, extendSession } = useSessionTimeout({
  timeoutMinutes: 30,
  warningMinutes: 5,
})
</script>

<template>
  <!-- Session Timeout Warning Dialog -->
  <v-dialog
    v-model="showWarning"
    max-width="500"
    persistent
  >
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon icon="mdi-clock-alert" color="warning" class="mr-2" />
        Session Expiring Soon
      </v-card-title>

      <v-card-text>
        <v-alert type="warning" variant="tonal" class="mb-4">
          Your session will expire in <strong>{{ formatTimeRemaining }}</strong> due to inactivity.
        </v-alert>

        <p>Click "Stay Logged In" to continue your session, or you will be automatically logged out.</p>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn
          color="primary"
          variant="elevated"
          @click="extendSession"
        >
          Stay Logged In
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
