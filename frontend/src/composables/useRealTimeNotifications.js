import { useNotificationStore } from '@/stores/notifications'
import { useWorkflowStore } from '@/stores/workflows'

export function useRealTimeNotifications() {
  const notificationStore = useNotificationStore()
  const workflowStore = useWorkflowStore()

  function setupListeners() {
    const echo = window.Echo
    if (!echo) {
      console.warn('Echo not initialized')
      
      return
    }

    const userId = localStorage.getItem('user_id')
    if (!userId) return

    // Listen to user's private channel
    echo.private(`App.Models.User.${userId}`)
      .notification(notification => {
        notificationStore.addNotification({
          id: notification.id || Date.now(),
          type: notification.type || 'info',
          title: notification.title,
          message: notification.message,
          data: notification.data,
          read: false,
          created_at: new Date().toISOString(),
        })

        // Play notification sound (optional)
        playNotificationSound()
      })

    // Listen for document events
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

    // Listen for workflow events
    echo.channel('workflows')
      .listen('WorkflowStepCompleted', event => {
        handleWorkflowStepCompleted(event)
      })
      .listen('WorkflowCancelled', event => {
        handleWorkflowCancelled(event)
      })
  }

  function handleDocumentSigned(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'success',
      title: 'Document Signed',
      message: `${event.signer.name} signed "${event.document.title}"`,
      data: { document_id: event.document.id },
      read: false,
      created_at: new Date().toISOString(),
    })

    // Refresh workflows to update dashboards
    workflowStore.refreshPendingSteps()
  }

  function handleDocumentCompleted(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'success',
      title: 'Document Completed',
      message: `"${event.document.title}" has been fully signed`,
      data: { document_id: event.document.id },
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
  }

  function handleDocumentDeclined(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'error',
      title: 'Document Declined',
      message: `${event.user.name} declined "${event.document.title}"`,
      data: {
        document_id: event.document.id,
        reason: event.reason,
      },
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
  }

  function handleWorkflowStepCompleted(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'info',
      title: 'Workflow Step Completed',
      message: `Step "${event.step.role}" completed for "${event.document.title}"`,
      data: {
        workflow_id: event.workflow.id,
        document_id: event.document.id,
      },
      read: false,
      created_at: new Date().toISOString(),
    })

    // Refresh workflow state
    if (workflowStore.activeWorkflow?.id === event.workflow.id) {
      workflowStore.fetchWorkflow(event.workflow.id)
    }
  }

  function handleWorkflowCancelled(event) {
    notificationStore.addNotification({
      id: Date.now(),
      type: 'warning',
      title: 'Workflow Cancelled',
      message: `Workflow for "${event.document.title}" was cancelled`,
      data: {
        workflow_id: event.workflow.id,
        reason: event.reason,
      },
      read: false,
      created_at: new Date().toISOString(),
    })

    workflowStore.refreshPendingSteps()
  }

  function playNotificationSound() {
    try {
      const audio = new Audio('/notification.mp3')

      audio.volume = 0.5
      audio.play().catch(() => {
        // Ignore errors (e.g., user hasn't interacted with page yet)
      })
    }
    catch (e) {
      // Sound file doesn't exist, ignore
    }
  }

  function disconnect() {
    const echo = window.Echo
    if (echo) {
      echo.disconnect()
    }
  }

  return {
    setupListeners,
    disconnect,
  }
}
