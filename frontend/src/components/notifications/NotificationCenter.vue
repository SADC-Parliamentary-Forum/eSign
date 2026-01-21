<script setup>
import { useNotificationStore } from '@/stores/notifications'
import { useRouter } from 'vue-router'

const router = useRouter()
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
    router.push(notification.link)
    notificationsOpen.value = false
  } else if (notification.data?.document_id) {
    router.push(`/documents/${notification.data.document_id}`)
    notificationsOpen.value = false
  }
}

// Fetch notifications on mount
onMounted(() => {
  notificationStore.fetchNotifications()
})
</script>

<template>
  <VMenu
    v-model="notificationsOpen"
    :close-on-content-click="false"
    location="bottom end"
    max-width="400"
    min-width="320"
  >
    <template #activator="{ props }">
      <IconBtn v-bind="props">
        <VBadge
          :content="notificationStore.unreadCount"
          :model-value="notificationStore.hasUnread"
          color="error"
          max="99"
          location="top end"
          offset-x="2"
          offset-y="2"
        >
          <VIcon>mdi-bell</VIcon>
        </VBadge>
      </IconBtn>
    </template>

    <VCard>
      <VCardTitle class="d-flex align-center py-3">
        <VIcon icon="mdi-bell" class="mr-2" />
        Notifications
        
        <VSpacer />
        
        <VChip
          v-if="notificationStore.unreadCount > 0"
          size="x-small"
          color="error"
          class="mr-2"
        >
          {{ notificationStore.unreadCount }} new
        </VChip>
        
        <VBtn
          v-if="notificationStore.hasUnread"
          variant="text"
          size="x-small"
          @click="notificationStore.markAllAsRead()"
        >
          Mark all read
        </VBtn>
      </VCardTitle>

      <VDivider />

      <!-- Loading State -->
      <VCardText v-if="notificationStore.loading" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
      </VCardText>

      <!-- Notifications List -->
      <VList
        v-else-if="notificationStore.notifications.length > 0"
        max-height="400"
        class="overflow-y-auto py-0"
      >
        <VListItem
          v-for="notification in notificationStore.recentNotifications"
          :key="notification.id"
          :class="{ 'bg-grey-lighten-4': !notification.read }"
          class="notification-item"
          @click="handleNotificationClick(notification)"
        >
          <template #prepend>
            <VAvatar
              :color="getNotificationColor(notification.type)"
              size="40"
              class="mr-3"
            >
              <VIcon
                :icon="getNotificationIcon(notification.type)"
                color="white"
                size="20"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="text-wrap font-weight-medium text-body-2">
            {{ notification.title || notification.message }}
          </VListItemTitle>

          <VListItemSubtitle 
            v-if="notification.title && notification.message" 
            class="text-wrap text-caption mt-1"
          >
            {{ notification.message }}
          </VListItemSubtitle>

          <VListItemSubtitle class="mt-1 text-caption text-medium-emphasis">
            {{ formatTime(notification.timestamp) }}
          </VListItemSubtitle>

          <template #append>
            <div class="d-flex flex-column align-center">
              <VIcon
                v-if="!notification.read"
                icon="mdi-circle"
                color="primary"
                size="8"
                class="mb-1"
              />
              <VBtn
                icon="mdi-close"
                size="x-small"
                variant="text"
                @click.stop="notificationStore.removeNotification(notification.id)"
              />
            </div>
          </template>
        </VListItem>
      </VList>

      <!-- Empty State -->
      <VCardText
        v-else
        class="text-center text-medium-emphasis py-8"
      >
        <VIcon
          icon="mdi-bell-off"
          size="48"
          class="mb-2 text-grey-lighten-1"
        />
        <div class="text-body-2">No notifications</div>
        <div class="text-caption">You're all caught up!</div>
      </VCardText>

      <VDivider v-if="notificationStore.notifications.length > 0" />

      <VCardActions v-if="notificationStore.notifications.length > 0" class="pa-2">
        <VBtn
          block
          variant="text"
          size="small"
          color="error"
          @click="notificationStore.clearAll()"
        >
          <VIcon icon="mdi-delete-sweep" class="mr-1" />
          Clear All
        </VBtn>
      </VCardActions>
    </VCard>
  </VMenu>
</template>

<style scoped>
.notification-item {
  cursor: pointer;
  transition: background-color 0.15s;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.notification-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}

.notification-item:last-child {
  border-bottom: none;
}
</style>
