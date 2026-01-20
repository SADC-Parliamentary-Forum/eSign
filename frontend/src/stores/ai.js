import { defineStore } from 'pinia'
import { aiAPI } from '@/utils/api'

export const useAIStore = defineStore('ai', {
  state: () => ({
    suggestions: [],
    bestMatch: null,
    analysis: null,
    loading: false,
    error: null,
    lastAnalyzedFile: null, // For caching
    retryCount: 0,
  }),

  getters: {
    strongSuggestions: state => state.suggestions.filter(s => s.strength === 'STRONG'),
    moderateSuggestions: state => state.suggestions.filter(s => s.strength === 'MODERATE'),
    hasSuggestions: state => state.suggestions.length > 0,
    topSuggestion: state => state.suggestions[0] || null,
    hasStrongMatch: state => state.suggestions.some(s => s.strength === 'STRONG'),
    isAnalyzing: state => state.loading,
    hasError: state => !!state.error,
  },

  actions: {
    async suggestTemplates(file, forceRefresh = false) {
      // Check cache unless force refresh
      if (!forceRefresh && this.lastAnalyzedFile === file?.name) {
        return this.suggestions
      }

      this.loading = true
      this.error = null

      try {
        const response = await aiAPI.suggestTemplate(file)
        const suggestions = response?.suggestions || response || []

        this.suggestions = suggestions.sort((a, b) => b.confidence - a.confidence)
        this.lastAnalyzedFile = file?.name
        this.retryCount = 0

        return this.suggestions
      } catch (error) {
        this.error = error.message || 'Failed to analyze document'
        console.error('Failed to get template suggestions:', error)

        // Retry logic
        if (this.retryCount < 2) {
          this.retryCount++
          console.log(`Retrying AI analysis (attempt ${this.retryCount})...`)
          return this.suggestTemplates(file, true)
        }

        throw error
      } finally {
        this.loading = false
      }
    },

    async analyzeDocument(file) {
      this.loading = true
      this.error = null

      try {
        this.analysis = await aiAPI.analyzeDocument(file)
        return this.analysis
      } catch (error) {
        this.error = error.message || 'Failed to analyze document'
        console.error('Failed to analyze document:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async validateTemplate(templateId, file) {
      this.loading = true
      this.error = null

      try {
        const result = await aiAPI.validateTemplate(templateId, file)
        return result
      } catch (error) {
        this.error = error.message || 'Failed to validate template'
        console.error('Failed to validate template:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async getBestMatch(file) {
      this.loading = true
      this.error = null

      try {
        const response = await aiAPI.getBestMatch(file)
        this.bestMatch = response?.best_match || response
        return this.bestMatch
      } catch (error) {
        this.error = error.message || 'Failed to get best match'
        console.error('Failed to get best match:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    clearSuggestions() {
      this.suggestions = []
      this.bestMatch = null
      this.analysis = null
      this.error = null
      this.lastAnalyzedFile = null
      this.retryCount = 0
    },

    clearError() {
      this.error = null
    },

    getConfidenceColor(confidence) {
      if (confidence >= 90) return 'success'
      if (confidence >= 70) return 'info'
      return 'warning'
    },

    getConfidenceIcon(strength) {
      switch (strength) {
        case 'STRONG':
          return 'mdi-star'
        case 'MODERATE':
          return 'mdi-star-half-full'
        default:
          return 'mdi-star-outline'
      }
    },

    getConfidenceLabel(strength) {
      switch (strength) {
        case 'STRONG':
          return 'Excellent Match'
        case 'MODERATE':
          return 'Good Match'
        default:
          return 'Possible Match'
      }
    },
  },
})
