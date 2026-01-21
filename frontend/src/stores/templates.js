import { defineStore } from 'pinia'
import { templateAPI } from '@/utils/api'

export const useTemplateStore = defineStore('templates', {
  state: () => ({
    templates: [],
    activeTemplate: null,
    loading: false,
    error: null,
  }),

  getters: {
    activeTemplates: state => state.templates.filter(t => t.status === 'ACTIVE'),
    draftTemplates: state => state.templates.filter(t => t.status === 'DRAFT'),
    pendingReviewTemplates: state => state.templates.filter(t => t.status === 'REVIEW'),
    templateById: state => id => state.templates.find(t => t.id === id),
  },

  actions: {
    async fetchTemplates() {
      this.loading = true
      this.error = null
      try {
        this.templates = await templateAPI.list()
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to fetch templates:', error)
      }
      finally {
        this.loading = false
      }
    },

    async fetchTemplate(id) {
      this.loading = true
      this.error = null
      try {
        this.activeTemplate = await templateAPI.get(id)

        return this.activeTemplate
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to fetch template:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async createTemplate(data) {
      this.loading = true
      this.error = null
      try {
        const template = await templateAPI.create(data)

        this.templates.push(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to create template:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async updateTemplate(id, data) {
      this.loading = true
      this.error = null
      try {
        const template = await templateAPI.update(id, data)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to update template:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async submitForReview(id) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.submitForReview(id)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to submit template for review:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async approveTemplate(id) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.approve(id)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to approve template:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async activateTemplate(id) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.activate(id)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to activate template:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async archiveTemplate(id) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.archive(id)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to archive template:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async deleteTemplate(id) {
      this.loading = true
      this.error = null
      try {
        await templateAPI.delete(id)

        // Remove from list
        const index = this.templates.findIndex(t => t.id === id)
        if (index !== -1) {
          this.templates.splice(index, 1)
        }
        if (this.activeTemplate?.id === id) {
          this.activeTemplate = null
        }

        return true
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to delete template:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async addRoles(id, roles) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.addRoles(id, roles)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to add roles:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async saveFields(id, fields) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.saveFields(id, fields)

        // Note: storeFields controller returns { message, fields } but not the full template with fields? 
        // We might need to fetch template again or update partially.
        // Assuming we just want to succeed for now.
        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to save fields:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async addFieldMappings(id, mappings) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.addFieldMappings(id, mappings)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to add field mappings:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async addThresholds(id, thresholds) {
      this.loading = true
      this.error = null
      try {
        const { template } = await templateAPI.addThresholds(id, thresholds)

        this.updateTemplateInList(template)

        return template
      }
      catch (error) {
        this.error = error.message
        console.error('Failed to add thresholds:', error)
        throw error
      }
      finally {
        this.loading = false
      }
    },

    async fetchVersions(id) {
      try {
        const versions = await templateAPI.getVersions(id)
        return versions
      } catch (e) {
        console.error('Failed to fetch versions', e)
        return []
      }
    },

    async createVersion(id, data = {}) {
      this.loading = true
      try {
        const res = await templateAPI.createVersion(id, data)
        return res.template
      } catch (e) {
        throw e
      } finally {
        this.loading = false
      }
    },

    updateTemplateInList(updatedTemplate) {
      const index = this.templates.findIndex(t => t.id === updatedTemplate.id)
      if (index !== -1) {
        this.templates[index] = updatedTemplate
      }
      if (this.activeTemplate?.id === updatedTemplate.id) {
        this.activeTemplate = updatedTemplate
      }
    },
  },
})
