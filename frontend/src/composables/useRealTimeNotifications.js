import { ref } from 'vue'
import { useNotificationStore } from '@/stores/notifications'
import { useWorkflowStore } from '@/stores/workflows'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'

export function useRealTimeNotifications() {
  const notificationStore = useNotificationStore()
  const workflowStore = useWorkflowStore()
  const authStore = useAuthStore()

  // Connection status
  const connectionStatus = ref('disconnected') // 'connecting', 'connected', 'disconnected', 'error'
  const reconnectAttempts = ref(0)
  const maxReconnectAttempts = 5
  let reconnectTimeout = null

  function setupListeners() {
    const echo = window.Echo
    if (!echo) {
      logger.warn('Echo not initialized - WebSocket features disabled')
      connectionStatus.value = 'error'
      return
    }

    connectionStatus.value = 'connecting'

    // Refresh auth header at subscription time so post-login reconnects
    // always use the latest bearer token (not the value at app boot).
    const accessToken = localStorage.getItem('accessToken') || localStorage.getItem('token')
    if (accessToken) {
      if (echo.connector?.options?.auth?.headers) {
        echo.connector.options.auth.headers.Authorization = `Bearer ${accessToken}`
      }
      if (echo.connector?.pusher?.config?.auth?.headers) {
        echo.connector.pusher.config.auth.headers.Authorization = `Bearer ${accessToken}`
      }
    }

    // Get user ID from auth store or localStorage
    const userId = authStore.user?.id || localStorage.getItem('user_id')
    if (!userId) {
      logger.warn('No user ID found for WebSocket subscription')
      return
    }

    try {
      // Listen to user's private channel for notifications
      echo.private(`App.Models.User.${userId}`)
        .notification(notification => {
          handleNotification(notification)
        })
        .error(error => {
          logger.error('Private channel error', { code: error?.status || error?.type })
          handleConnectionError()
        })

      // Listen for document events on public channel
      echo.channel('documents')
        .listen('DocumentSigned', event => {
          handleDocumentSigned(event)
        })
        .listen('DocumentCompleted', event => {
          handleDocumentCompleted(event)
        })
        .listen('DocumentDeclined', event => {
          handleDocumentDeclined(event)
        })
        .listen('SignerAdded', event => {
          handleSignerAdded(event)
        })

      // Listen for workflow events
      echo.channel('workflows')
        .listen('WorkflowStepCompleted', event => {
          handleWorkflowStepCompleted(event)
        })
        .listen('WorkflowCancelled', event => {
          handleWorkflowCancelled(event)
        })

      // Connection established
      connectionStatus.value = 'connected'
      reconnectAttempts.value = 0

      logger.info('Real-time notifications connected')
    } catch (error) {
      logger.captureError(error, { context: 'WebSocket setup' })
      handleConnectionError()
    }
  }

  function handleNotification(notification) {
    notificationStore.addNotification({
      id: notification.id || Date.now(),
      type: notification.type || 'info',
      title: notification.title,
      message: notification.message,
      data: notification.data,
      link: notification.link,
      read: false,
      created_at: new Date().toISOString(),
    })

    // Show toast for important notifications
    if (notification.type === 'error' || notification.type === 'warning') {
      showToast(notification)
    }

    playNotificationSound()
  }

  function handleDocumentSigned(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'success',
      title: 'Document Signed',
      message: `${event.signer?.name || 'A signer'} signed "${event.document?.title || 'a document'}"`,
      data: { document_id: event.document?.id },
      link: event.document?.id ? `/documents/${event.document.id}` : null,
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
    playNotificationSound()
  }

  function handleDocumentCompleted(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'success',
      title: 'Document Completed',
      message: `"${event.document?.title || 'Document'}" has been fully signed`,
      data: { document_id: event.document?.id },
      link: event.document?.id ? `/documents/${event.document.id}` : null,
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
    playNotificationSound()
  }

  function handleDocumentDeclined(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'error',
      title: 'Document Declined',
      message: `${event.user?.name || 'A signer'} declined "${event.document?.title || 'a document'}"`,
      data: {
        document_id: event.document?.id,
        reason: event.reason,
      },
      link: event.document?.id ? `/documents/${event.document.id}` : null,
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
    playNotificationSound()
  }

  function handleSignerAdded(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'info',
      title: 'Signature Requested',
      message: `You've been asked to sign "${event.document?.title || 'a document'}"`,
      data: { document_id: event.document?.id },
      link: event.document?.id ? `/documents/${event.document.id}` : null,
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
    playNotificationSound()
  }

  function handleWorkflowStepCompleted(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'info',
      title: 'Workflow Step Completed',
      message: `Step "${event.step?.role || 'Unknown'}" completed for "${event.document?.title || 'document'}"`,
      data: {
        workflow_id: event.workflow?.id,
        document_id: event.document?.id,
      },
      link: event.document?.id ? `/documents/${event.document.id}` : null,
      read: false,
      created_at: new Date().toISOString(),
    })

    // Refresh workflow state
    if (workflowStore.activeWorkflow?.id === event.workflow?.id) {
      workflowStore.fetchWorkflow(event.workflow.id)
    }
  }

  function handleWorkflowCancelled(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'warning',
      title: 'Workflow Cancelled',
      message: `Workflow for "${event.document?.title || 'document'}" was cancelled`,
      data: {
        workflow_id: event.workflow?.id,
        reason: event.reason,
      },
      link: event.document?.id ? `/documents/${event.document.id}` : null,
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
  }

  function handleConnectionError() {
    connectionStatus.value = 'error'

    if (reconnectAttempts.value < maxReconnectAttempts) {
      // Exponential backoff: 1s, 2s, 4s, 8s, 16s
      const delay = Math.pow(2, reconnectAttempts.value) * 1000
      reconnectAttempts.value++

      logger.info(`WebSocket reconnecting in ${delay / 1000}s (attempt ${reconnectAttempts.value}/${maxReconnectAttempts})`)

      reconnectTimeout = setTimeout(() => {
        connectionStatus.value = 'connecting'
        setupListeners()
      }, delay)
    } else {
      logger.warn('Max WebSocket reconnection attempts reached')
    }
  }

  function showToast(notification) {
    // This will be handled by the snackbar/toast system
    // For now, we add it to the notification store which can trigger toasts
  }

  function playNotificationSound() {
    try {
      const audio = new Audio('/notification.mp3')
      audio.volume = 0.3
      audio.play().catch(() => {
        // Ignore errors (e.g., user hasn't interacted with page yet)
      })
    } catch (e) {
      // Sound file doesn't exist, ignore
    }
  }

  function disconnect() {
    const echo = window.Echo
    if (echo) {
      echo.disconnect()
    }

    if (reconnectTimeout) {
      clearTimeout(reconnectTimeout)
      reconnectTimeout = null
    }

    connectionStatus.value = 'disconnected'
    reconnectAttempts.value = 0
  }

  return {
    setupListeners,
    disconnect,
    connectionStatus,
    reconnectAttempts,
  }
}
