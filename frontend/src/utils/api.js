import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: {
    Accept: 'application/json',
  },
  async onRequest({ options }) {
    options.headers = new Headers(options.headers)

    const accessToken = localStorage.getItem('accessToken') || localStorage.getItem('token')
    if (accessToken)
      options.headers.set('Authorization', `Bearer ${accessToken}`)
  },
  async onResponseError({ response }) {
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
