import { defineStore } from 'pinia'

/**
 * AI store is intentionally disabled.
 * AI features have been replaced by the Amount-in-Words verification feature.
 * This stub keeps existing imports from breaking.
 */
export const useAIStore = defineStore('ai', {
  state: () => ({
    suggestions: [],
    bestMatch: null,
    analysis: null,
    loading: false,
    error: null,
  }),

  getters: {
    strongSuggestions: () => [],
    moderateSuggestions: () => [],
    hasSuggestions: () => false,
    topSuggestion: () => null,
    hasStrongMatch: () => false,
    isAnalyzing: () => false,
    hasError: state => !!state.error,
  },

  actions: {
    async suggestTemplates() { return [] },
    async analyzeDocument() { return null },
    async validateTemplate() { return null },
    async getBestMatch() { return null },
    clearSuggestions() {
      this.suggestions = []
      this.bestMatch = null
      this.analysis = null
      this.error = null
    },
    clearError() { this.error = null },
    getConfidenceColor() { return 'grey' },
    getConfidenceIcon() { return 'mdi-star-outline' },
    getConfidenceLabel() { return 'N/A' },
  },
})
