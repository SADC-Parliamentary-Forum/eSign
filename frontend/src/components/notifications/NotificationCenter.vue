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
  <VMenu
    v-model="notificationsOpen"
    :close-on-content-click="false"
    location="bottom end"
    max-width="400"
  >
    <template #activator="{ props }">
      <VBtn
        icon
        v-bind="props"
      >
        <VBadge
          :content="notificationStore.unreadCount"
          :model-value="notificationStore.hasUnread"
          color="error"
        >
          <VIcon>mdi-bell</VIcon>
        </VBadge>
      </VBtn>
    </template>

    <VCard>
      <VCardTitle class="d-flex align-center">
        Notifications
        
        <VSpacer />
        
        <VBtn
          v-if="notificationStore.hasUnread"
          variant="text"
          size="small"
          @click="notificationStore.markAllAsRead()"
        >
          Mark all read
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VList
        v-if="notificationStore.notifications.length > 0"
        max-height="400"
        class="overflow-y-auto"
      >
        <VListItem
          v-for="notification in notificationStore.notifications"
          :key="notification.id"
          :class="{ 'bg-grey-lighten-4': !notification.read }"
          @click="handleNotificationClick(notification)"
        >
          <template #prepend>
            <VAvatar
              :color="getNotificationColor(notification.type)"
              size="40"
            >
              <VIcon
                :icon="getNotificationIcon(notification.type)"
                color="white"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="text-wrap">
            {{ notification.message }}
          </VListItemTitle>

          <VListItemSubtitle class="mt-1">
            {{ formatTime(notification.timestamp) }}
          </VListItemSubtitle>

          <template #append>
            <VBtn
              icon="mdi-close"
              size="x-small"
              variant="text"
              @click.stop="notificationStore.removeNotification(notification.id)"
            />
          </template>
        </VListItem>
      </VList>

      <VCardText
        v-else
        class="text-center text-medium-emphasis py-8"
      >
        <VIcon
          icon="mdi-bell-off"
          size="48"
          class="mb-2"
        />
        <div>No notifications</div>
      </VCardText>

      <VDivider v-if="notificationStore.notifications.length > 0" />

      <VCardActions v-if="notificationStore.notifications.length > 0">
        <VBtn
          block
          variant="text"
          @click="notificationStore.clearAll()"
        >
          Clear All
        </VBtn>
      </VCardActions>
    </VCard>
  </VMenu>
</template>
