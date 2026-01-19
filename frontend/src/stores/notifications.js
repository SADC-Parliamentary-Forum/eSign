import { defineStore } from 'pinia'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    notifications: [],
    unreadCount: 0,
  }),

  getters: {
    unreadNotifications: state => state.notifications.filter(n => !n.read),
    hasUnread: state => state.unreadCount > 0,
  },

  actions: {
    addNotification(notification) {
      const newNotification = {
        id: Date.now(),
        read: false,
        timestamp: new Date(),
        ...notification,
      }

      this.notifications.unshift(newNotification)
      this.unreadCount++

      // Auto-dismiss success notifications after 5 seconds
      if (notification.type === 'success') {
        setTimeout(() => {
          this.removeNotification(newNotification.id)
        }, 5000)
      }
    },

    markAsRead(id) {
      const notification = this.notifications.find(n => n.id === id)
      if (notification && !notification.read) {
        notification.read = true
        this.unreadCount = Math.max(0, this.unreadCount - 1)
      }
    },

    markAllAsRead() {
      this.notifications.forEach(n => {
        n.read = true
      })
      this.unreadCount = 0
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
  },
})
