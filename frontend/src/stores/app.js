import { defineStore } from 'pinia'
import { $api } from '@/utils/api'

export const useAppStore = defineStore('app', {
    state: () => ({
        settings: {
            app_name: 'eSign',
            timezone: 'UTC',
            date_format: 'MMM d, yyyy',
            time_format: 'h:mm a',
            locale: 'en-US',
        },
        loading: false,
    }),

    actions: {
        async fetchSettings() {
            this.loading = true
            try {
                const data = await $api('/admin/settings')
                if (data) {
                    this.settings = { ...this.settings, ...data }
                }
            } catch (error) {
                // Silently handle 403 Forbidden errors (e.g. for non-admin users)
                if (error.response?.status !== 403) {
                    console.error('Failed to fetch app settings:', error)
                }
            } finally {
                this.loading = false
            }
        },

        updateSettings(newSettings) {
            this.settings = { ...this.settings, ...newSettings }
        }
    },
})
