import { defineStore } from 'pinia'
import { aiAPI } from '@/utils/api'

export const useAIStore = defineStore('ai', {
    state: () => ({
        suggestions: [],
        bestMatch: null,
        analysis: null,
        loading: false,
        error: null,
    }),

    getters: {
        strongSuggestions: state => state.suggestions.filter(s => s.strength === 'STRONG'),
        moderateSuggestions: state => state.suggestions.filter(s => s.strength === 'MODERATE'),
        hasSuggestions: state => state.suggestions.length > 0,
        topSuggestion: state => state.suggestions[0] || null,
    },

    actions: {
        async suggestTemplates(file) {
            this.loading = true
            this.error = null
            try {
                const { suggestions, total_count } = await aiAPI.suggestTemplate(file)
                this.suggestions = suggestions.sort((a, b) => b.confidence - a.confidence)

                return this.suggestions
            }
            catch (error) {
                this.error = error.message
                console.error('Failed to get template suggestions:', error)
                throw error
            }
            finally {
                this.loading = false
            }
        },

        async analyzeDocument(file) {
            this.loading = true
            this.error = null
            try {
                this.analysis = await aiAPI.analyzeDocument(file)
                return this.analysis
            }
            catch (error) {
                this.error = error.message
                console.error('Failed to analyze document:', error)
                throw error
            }
            finally {
                this.loading = false
            }
        },

        async validateTemplate(templateId, file) {
            this.loading = true
            this.error = null
            try {
                const validation = await aiAPI.validateTemplate(templateId, file)
                return validation
            }
            catch (error) {
                this.error = error.message
                console.error('Failed to validate template:', error)
                throw error
            }
            finally {
                this.loading = false
            }
        },

        async getBestMatch(file) {
            this.loading = true
            this.error = null
            try {
                const { best_match } = await aiAPI.getBestMatch(file)
                this.bestMatch = best_match
                return best_match
            }
            catch (error) {
                this.error = error.message
                console.error('Failed to get best match:', error)
                throw error
            }
            finally {
                this.loading = false
            }
        },

        clearSuggestions() {
            this.suggestions = []
            this.bestMatch = null
            this.analysis = null
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
    },
})
