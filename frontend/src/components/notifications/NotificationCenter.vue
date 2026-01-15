<script setup>
import { useNotificationStore } from '@/stores/notifications'

const notificationStore = useNotificationStore()

const notificationsOpen = ref(false)

const getNotificationIcon = type => {
  switch (type) {
    case 'success':
      return 'mdi-check-circle'
    case 'error':
      return 'mdi-alert-circle'
    case 'warning':
      return 'mdi-alert'
    case 'info':
    default:
      return 'mdi-information'
  }
}

const getNotificationColor = type => {
  switch (type) {
    case 'success':
      return 'success'
    case 'error':
      return 'error'
    case 'warning':
      return 'warning'
    case 'info':
    default:
      return 'info'
  }
}

const formatTime = timestamp => {
  const now = new Date()
  const time = new Date(timestamp)
  const diff = now - time

  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (days > 0) return `${days}d ago`
  if (hours > 0) return `${hours}h ago`
  if (minutes > 0) return `${minutes}m ago`
  return 'Just now'
}

const handleNotificationClick = notification => {
  notificationStore.markAsRead(notification.id)
  
  // Handle deep link if present
  if (notification.link) {
    navigateTo(notification.link)
    notificationsOpen.value = false
  }
}
</script>

<template>
  <v-menu
    v-model="notificationsOpen"
    :close-on-content-click="false"
    location="bottom end"
    max-width="400"
  >
    <template #activator="{ props }">
      <v-btn
        icon
        v-bind="props"
      >
        <v-badge
          :content="notificationStore.unreadCount"
          :model-value="notificationStore.hasUnread"
          color="error"
        >
          <v-icon>mdi-bell</v-icon>
        </v-badge>
      </v-btn>
    </template>

    <v-card>
      <v-card-title class="d-flex align-center">
        Notifications
        
        <v-spacer />
        
        <v-btn
          v-if="notificationStore.hasUnread"
          variant="text"
          size="small"
          @click="notificationStore.markAllAsRead()"
        >
          Mark all read
        </v-btn>
      </v-card-title>

      <v-divider />

      <v-list
        v-if="notificationStore.notifications.length > 0"
        max-height="400"
        class="overflow-y-auto"
      >
        <v-list-item
          v-for="notification in notificationStore.notifications"
          :key="notification.id"
          :class="{ 'bg-grey-lighten-4': !notification.read }"
          @click="handleNotificationClick(notification)"
        >
          <template #prepend>
            <v-avatar
              :color="getNotificationColor(notification.type)"
              size="40"
            >
              <v-icon
                :icon="getNotificationIcon(notification.type)"
                color="white"
              />
            </v-avatar>
          </template>

          <v-list-item-title class="text-wrap">
            {{ notification.message }}
          </v-list-item-title>

          <v-list-item-subtitle class="mt-1">
            {{ formatTime(notification.timestamp) }}
          </v-list-item-subtitle>

          <template #append>
            <v-btn
              icon="mdi-close"
              size="x-small"
              variant="text"
              @click.stop="notificationStore.removeNotification(notification.id)"
            />
          </template>
        </v-list-item>
      </v-list>

      <v-card-text
        v-else
        class="text-center text-medium-emphasis py-8"
      >
        <v-icon
          icon="mdi-bell-off"
          size="48"
          class="mb-2"
        />
        <div>No notifications</div>
      </v-card-text>

      <v-divider v-if="notificationStore.notifications.length > 0" />

      <v-card-actions v-if="notificationStore.notifications.length > 0">
        <v-btn
          block
          variant="text"
          @click="notificationStore.clearAll()"
        >
          Clear All
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-menu>
</template>
