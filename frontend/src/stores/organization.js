import { defineStore } from 'pinia'

export const useOrganizationStore = defineStore('organization', {
    state: () => ({
        departments: [],
        roles: [],
        loading: false,
        error: null,
    }),

    getters: {
        activeDepartments: state => state.departments.filter(d => d.is_active !== false),
        activeRoles: state => state.roles.filter(r => r.is_active !== false),
        rolesByLevel: state => [...state.roles].sort((a, b) => a.level - b.level),
        roleById: state => id => state.roles.find(r => r.id === id),
        departmentById: state => id => state.departments.find(d => d.id === id),
    },

    actions: {
        async fetchDepartments() {
            this.loading = true
            try {
                this.departments = await $api('/departments')
            } catch (error) {
                this.error = error.message
                console.error('Failed to fetch departments:', error)
            } finally {
                this.loading = false
            }
        },

        async fetchRoles() {
            this.loading = true
            try {
                this.roles = await $api('/org-roles')
            } catch (error) {
                this.error = error.message
                console.error('Failed to fetch organizational roles:', error)
            } finally {
                this.loading = false
            }
        },

        async fetchAll() {
            await Promise.all([
                this.fetchDepartments(),
                this.fetchRoles()
            ])
        },
    },
})
