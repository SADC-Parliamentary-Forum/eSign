<script setup>
import NotificationCenter from '@/components/notifications/NotificationCenter.vue'
import { useRealTimeNotifications } from '@/composables/useRealTimeNotifications'

// Set up real-time notification listeners
const { setupListeners } = useRealTimeNotifications()

onMounted(() => {
  setupListeners()
})
</script>

<template>
  <NotificationCenter />
</template>
