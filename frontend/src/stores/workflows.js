import { defineStore } from 'pinia'
import { workflowAPI } from '@/utils/api'

export const useWorkflowStore = defineStore('workflows', {
  state: () => ({
    workflows: [],
    activeWorkflow: null,
    pendingSteps: [],
    loading: false,
    error: null,
  }),

  getters: {
    pendingStepsCount: state => state.pendingSteps.length,
    hasActiveTasks: state => state.pendingSteps.length > 0,
    workflowById: state => id => state.workflows.find(w => w.id === id),
  },

  actions: {
    async fetchWorkflow(id) {
      this.loading = true
      this.error = null
      try {
        const { workflow, current_steps, can_user_sign } = await workflowAPI.get(id)

        this.activeWorkflow = {
          ...workflow,
          currentSteps: current_steps,
          canUserSign: can_user_sign,
        }
        
        return this.activeWorkflow
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to fetch workflow:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async fetchWorkflowSteps(id) {
      this.loading = true
      this.error = null
      try {
        return await workflowAPI.getSteps(id)
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to fetch workflow steps:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async fetchDocumentWorkflow(documentId) {
      this.loading = true
      this.error = null
      try {
        const { workflow, current_steps, can_user_sign } = await workflowAPI.getByDocument(documentId)

        this.activeWorkflow = {
          ...workflow,
          currentSteps: current_steps,
          canUserSign: can_user_sign,
        }
        
        return this.activeWorkflow
      }
      catch (error) {
        if (error.response?.status === 404) {
          this.activeWorkflow = null
          
          return null
        }
        this.error = error.message
        console.error('Failed to fetch document workflow:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async fetchUserPendingSteps() {
      this.loading = true
      this.error = null
      try {
        const { pending_steps } = await workflowAPI.getUserPending()

        this.pendingSteps = pending_steps
        
        return pending_steps
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to fetch pending steps:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async cancelWorkflow(id, reason) {
      this.loading = true
      this.error = null
      try {
        const { workflow } = await workflowAPI.cancel(id, reason)

        // Update active workflow if it's the one being cancelled
        if (this.activeWorkflow?.id === id) {
          this.activeWorkflow = workflow
        }

        // Remove any pending steps for this workflow
        this.pendingSteps = this.pendingSteps.filter(step => step.workflow_id !== id)

        return workflow
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to cancel workflow:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    refreshPendingSteps() {
      return this.fetchUserPendingSteps()
    },
  },
})
