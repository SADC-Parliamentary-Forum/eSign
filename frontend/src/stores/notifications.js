import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { logger } from '@/utils/logger'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    notifications: [],
    unreadCount: 0,
    connectionStatus: 'disconnected', // 'connected', 'disconnected', 'connecting', 'error'
    soundEnabled: true,
    loading: false,
  }),

  getters: {
    unreadNotifications: state => state.notifications.filter(n => !n.read),
    hasUnread: state => state.unreadCount > 0,
    recentNotifications: state => state.notifications.slice(0, 10),
    isConnected: state => state.connectionStatus === 'connected',
  },

  actions: {
    addNotification(notification) {
      const newNotification = {
        id: notification.id || Date.now(),
        type: notification.type || 'info',
        title: notification.title || '',
        message: notification.message || '',
        data: notification.data || {},
        link: notification.link || null,
        read: false,
        timestamp: new Date(),
        created_at: notification.created_at || new Date().toISOString(),
      }

      // Prevent duplicates
      const exists = this.notifications.find(n => n.id === newNotification.id)
      if (exists) return

      this.notifications.unshift(newNotification)
      this.unreadCount++

      // Keep only last 50 notifications
      if (this.notifications.length > 50) {
        this.notifications = this.notifications.slice(0, 50)
      }

      // Auto-dismiss success notifications after 5 seconds
      if (notification.type === 'success' && notification.autoDismiss !== false) {
        setTimeout(() => {
          this.removeNotification(newNotification.id)
        }, 5000)
      }
    },

    async fetchNotifications() {
      this.loading = true
      try {
        const response = await $api('/notifications')
        this.notifications = (response.data || response || []).map(n => ({
          ...n,
          timestamp: new Date(n.created_at),
        }))
        this.unreadCount = this.notifications.filter(n => !n.read).length
      } catch (error) {
        logger.warn('Failed to fetch notifications', { status: error?.status })
      } finally {
        this.loading = false
      }
    },

    markAsRead(id) {
      const notification = this.notifications.find(n => n.id === id)
      if (notification && !notification.read) {
        notification.read = true
        this.unreadCount = Math.max(0, this.unreadCount - 1)

        // API call to mark as read (fire and forget)
        $api(`/notifications/${id}/read`, { method: 'POST' }).catch(() => { })
      }
    },

    markAllAsRead() {
      this.notifications.forEach(n => {
        n.read = true
      })
      this.unreadCount = 0

      // API call to mark all as read
      $api('/notifications/read-all', { method: 'POST' }).catch(() => { })
    },

    removeNotification(id) {
      const index = this.notifications.findIndex(n => n.id === id)
      if (index !== -1) {
        const wasUnread = !this.notifications[index].read
        this.notifications.splice(index, 1)
        if (wasUnread) {
          this.unreadCount = Math.max(0, this.unreadCount - 1)
        }
      }
    },

    clearAll() {
      this.notifications = []
      this.unreadCount = 0
    },

    setConnectionStatus(status) {
      this.connectionStatus = status
    },

    toggleSound() {
      this.soundEnabled = !this.soundEnabled
    },
  },
})
