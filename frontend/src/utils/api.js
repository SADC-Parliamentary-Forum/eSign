import { ofetch } from 'ofetch'

import { config } from '@/config'

export const $api = ofetch.create({
  baseURL: config.api.baseUrl,
  headers: {
    Accept: 'application/json',
  },
  defaults: {
    withCredentials: true,
  },
  async onRequest({ options, request }) {
    options.headers = new Headers(options.headers)
    options.credentials = 'include'; // Ensure credentials are sent

    // Inject Authorization Token
    const accessToken = localStorage.getItem('accessToken') || localStorage.getItem('token')
    if (accessToken) {
      if (options.headers instanceof Headers) {
        options.headers.set('Authorization', `Bearer ${accessToken}`)
      } else {
        options.headers = { ...options.headers, Authorization: `Bearer ${accessToken}` }
      }
    }

    // Manual CSRF Handling for ofetch (Required for Laravel Sanctum)
    const getCookie = (name) => {
      const value = `; ${document.cookie}`;
      const parts = value.split(`; ${name}=`);
      if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
    }
    const xsrfToken = getCookie('XSRF-TOKEN')
    if (xsrfToken) {
      if (options.headers instanceof Headers) {
        options.headers.set('X-XSRF-TOKEN', xsrfToken)
      } else {
        options.headers = { ...options.headers, 'X-XSRF-TOKEN': xsrfToken }
      }
    }

    // Bot Protection
    if (window.grecaptcha && config.botProtection.enabled) {
      let action = null
      const url = request.toString()

      // Simple URL mapping to Actions
      if (url.includes('/auth/login')) action = 'login'
      else if (url.includes('/auth/register')) action = 'register'
      else if (url.includes('/auth/forgot-password')) action = 'forgot_password'
      else if (url.includes('/documents/bulk-sign')) action = 'bulk_sign'
      else if (url.match(/\/documents\/\d+\/sign$/)) action = 'sign_document'
      else if (url.endsWith('/documents') && options.method === 'POST') action = 'document_upload'

      if (action) {
        try {
          const token = await window.grecaptcha.execute(config.botProtection.siteKey, { action })
          if (token) {
            options.headers.set('X-Human-Token', token)
          }
        } catch (e) {
          console.warn('Bot Protection Token Check Failed', e)
        }
      }
    }
  },
  async onResponseError({ request, response }) {
    // Ignore 401s from login/register endpoints to allow form to handle errors
    if (request.toString().includes('/auth/login') || request.toString().includes('/auth/register')) {
      return
    }

    if (response.status === 401) {
      localStorage.removeItem('accessToken')
      localStorage.removeItem('token')
      localStorage.removeItem('userData')
      localStorage.removeItem('userAbilityRules')

      if (!window.location.pathname.includes('/login'))
        window.location.href = '/login'
    }
  },
})

// Template Management API
export const templateAPI = {
  list: () => $api('/templates'),
  get: id => $api(`/templates/${id}`),
  create: data => $api('/templates', { method: 'POST', body: data }),
  update: (id, data) => $api(`/templates/${id}`, { method: 'PUT', body: data }),
  delete: id => $api(`/templates/${id}`, { method: 'DELETE' }),

  // Governance
  submitForReview: id => $api(`/templates/${id}/submit-review`, { method: 'POST' }),
  approve: id => $api(`/templates/${id}/approve`, { method: 'POST' }),
  activate: id => $api(`/templates/${id}/activate`, { method: 'POST' }),
  archive: id => $api(`/templates/${id}/archive`, { method: 'POST' }),

  // Configuration
  addRoles: (id, roles) => $api(`/templates/${id}/roles`, { method: 'POST', body: { roles } }),
  saveFields: (id, fields) => $api(`/templates/${id}/fields`, { method: 'POST', body: { fields } }),
  addFieldMappings: (id, mappings) => $api(`/templates/${id}/field-mappings`, { method: 'POST', body: { mappings } }),
  addThresholds: (id, thresholds) => $api(`/templates/${id}/thresholds`, { method: 'POST', body: { thresholds } }),

  // Info
  getThresholdMatrix: id => $api(`/templates/${id}/threshold-matrix`),
  getVersions: id => $api(`/templates/${id}/versions`),
  createVersion: (id, data) => $api(`/templates/${id}/version`, { method: 'POST', body: data }),

  // Enhanced Features
  clone: id => $api(`/templates/${id}/clone`, { method: 'POST' }),
  apply: (id, documentId) => $api(`/templates/${id}/apply`, { method: 'POST', body: { document_id: documentId } }),
  getCategories: () => $api('/templates/meta/categories'),
  getMostUsed: () => $api('/templates/meta/most-used'),
  getRecentlyUsed: () => $api('/templates/meta/recent'),
}

// Workflow Management API
export const workflowAPI = {
  get: id => $api(`/workflows/${id}`),
  getSteps: id => $api(`/workflows/${id}/steps`),
  getByDocument: documentId => $api(`/documents/${documentId}/workflow`),
  getUserPending: () => $api('/workflows/user/pending'),
  cancel: (id, reason) => $api(`/workflows/${id}/cancel`, { method: 'POST', body: { reason } }),
}

// AI Features API
export const aiAPI = {
  suggestTemplate: file => {
    const formData = new FormData()

    formData.append('file', file)

    return $api('/ai/suggest-template', { method: 'POST', body: formData })
  },
  analyzeDocument: file => {
    const formData = new FormData()

    formData.append('file', file)

    return $api('/ai/analyze-document', { method: 'POST', body: formData })
  },
  validateTemplate: (templateId, file) => {
    const formData = new FormData()

    formData.append('template_id', templateId)
    formData.append('file', file)

    return $api('/ai/validate-template', { method: 'POST', body: formData })
  },
  getBestMatch: file => {
    const formData = new FormData()

    formData.append('file', file)

    return $api('/ai/best-match', { method: 'POST', body: formData })
  },
}

export const getErrorMessage = error => {
  if (error?.data?.errors) {
    const firstField = Object.keys(error.data.errors)[0]
    if (firstField)
      return error.data.errors[firstField][0]
  }
  return error?.data?.message || error?.message || 'An unknown error occurred'
}
