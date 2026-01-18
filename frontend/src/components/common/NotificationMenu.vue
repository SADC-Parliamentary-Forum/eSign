<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { formatDistanceToNow } from 'date-fns'

const router = useRouter()
const notifications = ref([])
const unreadCount = ref(0)
const loading = ref(false)

const fetchNotifications = async () => {
  try {
    loading.value = true
    const res = await fetch('/api/notifications', {
      headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    if (res.ok) {
        const data = await res.json()
        notifications.value = data.data
        unreadCount.value = data.unread_count
    }
  } catch (e) {
    console.error('Failed to fetch notifications', e)
  } finally {
    loading.value = false
  }
}

const markAsRead = async (notification) => {
  if (notification.read_at) return
  
  try {
    await fetch(`/api/notifications/${notification.id}/read`, {
      method: 'POST',
      headers: { 
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Content-Type': 'application/json'
      }
    })
    notification.read_at = new Date().toISOString()
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  } catch (e) {
    console.error('Failed to mark as read', e)
  }
}

const handleNotificationClick = (notification) => {
  markAsRead(notification)
  if (notification.data.document_id) {
    router.push(`/documents/${notification.data.document_id}`)
  }
}

onMounted(() => {
  fetchNotifications()
})
</script>

<template>
  <VBadge
    :content="unreadCount"
    :model-value="unreadCount > 0"
    color="error"
    dot
    location="top right"
    offset-x="3"
    offset-y="3"
  >
    <VBtn
      icon
      variant="text"
      color="default"
      class="text-medium-emphasis"
      size="small"
    >
      <VIcon icon="ri-notification-line" size="24" />

      <VMenu activator="parent" width="380" location="bottom end" offset="14px">
        <VCard class="d-flex flex-column">
          <!-- Header -->
          <VCardItem class="py-3">
            <VCardTitle class="text-h6">Notifications</VCardTitle>
            <template #append>
              <VChip
                v-if="unreadCount > 0"
                size="small"
                color="primary"
                class="me-2"
              >
                {{ unreadCount }} New
              </VChip>
            </template>
          </VCardItem>

          <VDivider />

          <!-- List -->
          <PerfectScrollbar :options="{ wheelPropagation: false }" style="max-height: 380px;">
            <VList v-if="notifications.length > 0" lines="two" class="py-0">
              <template v-for="(notification, index) in notifications" :key="notification.id">
                <VListItem
                  :value="notification"
                  :class="{ 'bg-var-theme-background': !notification.read_at }"
                  @click="handleNotificationClick(notification)"
                >
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="32" class="me-3">
                      <VIcon icon="ri-file-text-line" size="18" />
                    </VAvatar>
                  </template>

                  <VListItemTitle class="text-sm font-weight-medium mb-1">
                    {{ notification.data.message }}
                  </VListItemTitle>
                  
                  <VListItemSubtitle class="text-xs">
                    {{ formatDistanceToNow(new Date(notification.created_at), { addSuffix: true }) }}
                  </VListItemSubtitle>
                  
                  <template #append>
                    <VBadge
                      v-if="!notification.read_at"
                      color="primary"
                      dot
                      inline
                    />
                  </template>
                </VListItem>
                <VDivider v-if="index !== notifications.length - 1" />
              </template>
            </VList>

            <!-- Empty State -->
            <div v-else class="pa-6 text-center text-medium-emphasis">
              <VIcon icon="ri-notification-off-line" size="40" class="mb-2" />
              <div class="text-body-2">No notifications</div>
            </div>
          </PerfectScrollbar>
          
          <VDivider />
          
          <!-- Footer -->
          <VCardText class="py-2 text-center">
            <VBtn
              block
              variant="text"
              size="small"
              color="primary"
            >
              View All Notifications
            </VBtn>
          </VCardText>
        </VCard>
      </VMenu>
    </VBtn>
  </VBadge>
</template>

<style scoped>
.v-list-item--active {
    background-color: transparent !important;
    color: inherit !important;
}
</style>
