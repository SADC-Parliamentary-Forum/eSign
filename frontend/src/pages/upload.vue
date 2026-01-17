<script setup>
/**
 * Upload Page - Simplified
 * 
 * Single purpose: Upload document and redirect to prepare page
 * Signers and fields are now managed in the prepare page
 */
import { useRouter } from 'vue-router'
import { useAIStore } from '@/stores/ai'
import { useTemplateStore } from '@/stores/templates'
import TemplateSuggestion from '@/components/ai/TemplateSuggestion.vue'
import SignatureLevelSelector from '@/components/signatures/SignatureLevelSelector.vue'

const route = useRoute()
const router = useRouter()
const aiStore = useAIStore()
const templateStore = useTemplateStore()

// Form state
const title = ref('')
const file = ref(null)
const uploading = ref(false)
const error = ref('')
const signatureLevel = ref('SIMPLE')

// AI Template Suggestions
const showTemplateSuggestions = ref(false)
const analyzingDocument = ref(false)
const appliedTemplate = ref(null)

// Load template if provided in query
onMounted(async () => {
  if (route.query.templateId) {
    try {
      await templateStore.fetchTemplate(route.query.templateId)
      const template = templateStore.activeTemplate
      if (template) {
        appliedTemplate.value = template
        title.value = `${template.name} - ${new Date().toLocaleDateString()}`
        signatureLevel.value = template.required_signature_level || 'SIMPLE'
      }
    } catch (e) {
      console.error('Failed to load template:', e)
    }
  }
})

const canUpload = computed(() => {
  return (file.value || appliedTemplate.value) && title.value.trim()
})

async function handleUpload() {
  if (!canUpload.value) return

  uploading.value = true
  error.value = ''

  try {
    let res

    if (file.value) {
      // File upload
      const formData = new FormData()
      formData.append('file', file.value[0] || file.value)
      formData.append('title', title.value)
      formData.append('signature_level', signatureLevel.value)
      
      if (appliedTemplate.value) {
        formData.append('template_id', appliedTemplate.value.id)
      }

      res = await $api('/documents', { method: 'POST', body: formData })
    } else if (appliedTemplate.value) {
      // Template-only creation
      res = await $api('/documents', {
        method: 'POST',
        body: {
          title: title.value,
          signature_level: signatureLevel.value,
          template_id: appliedTemplate.value.id
        }
      })
    }

    // Check for AI template suggestions (only for fresh file uploads)
    if (file.value && !appliedTemplate.value) {
      await analyzeForTemplates()
      if (aiStore.hasSuggestions) {
        showTemplateSuggestions.value = true
        // Store documentId for later
        sessionStorage.setItem('pendingDocumentId', res.id)
        return
      }
    }

    // Success - redirect to prepare page (standalone route)
    router.push(`/prepare/${res.id}`)

  } catch (e) {
    error.value = e.message || 'Upload failed. Please try again.'
    console.error('Upload error:', e)
  } finally {
    uploading.value = false
  }
}

async function analyzeForTemplates() {
  analyzingDocument.value = true
  try {
    const fileToAnalyze = file.value[0] || file.value
    await aiStore.suggestTemplates(fileToAnalyze)
  } catch (e) {
    console.error('AI analysis failed:', e)
  } finally {
    analyzingDocument.value = false
  }
}

async function applyTemplate(template) {
  appliedTemplate.value = template
  
  // Load full template details
  await templateStore.fetchTemplate(template.id)
  const fullTemplate = templateStore.activeTemplate
  
  if (fullTemplate?.required_signature_level) {
    signatureLevel.value = fullTemplate.required_signature_level
  }
  
  showTemplateSuggestions.value = false
  
  // Continue to prepare page
  const documentId = sessionStorage.getItem('pendingDocumentId')
  if (documentId) {
    sessionStorage.removeItem('pendingDocumentId')
    router.push(`/documents/${documentId}/prepare`)
  }
}

function skipTemplates() {
  aiStore.clearSuggestions()
  showTemplateSuggestions.value = false
  
  const documentId = sessionStorage.getItem('pendingDocumentId')
  if (documentId) {
    sessionStorage.removeItem('pendingDocumentId')
    router.push(`/documents/${documentId}/prepare`)
  }
}

function onFileChange(files) {
  file.value = files
  
  // Auto-generate title from filename (always overwrite to match file)
  if (files && (files.length > 0 || files.name)) {
    const f = Array.isArray(files) ? files[0] : files
    const filename = f.name
    title.value = filename.replace(/\.[^/.]+$/, '') // Remove extension
  }
}
</script>

<template>
  <v-container class="py-8">
    <v-row justify="center">
      <v-col cols="12" md="8" lg="6">
        <!-- Header -->
        <div class="text-center mb-8">
          <v-icon icon="ri-upload-cloud-2-line" size="64" color="primary" class="mb-4" />
          <h1 class="text-h4 font-weight-bold mb-2">Upload Document</h1>
          <p class="text-body-1 text-medium-emphasis">
            Upload a PDF or Word document to prepare for signing
          </p>
        </div>

        <!-- Main Upload Card -->
        <v-card elevation="2" class="mb-6">
          <v-card-text class="pa-6">
            <v-form @submit.prevent="handleUpload">
              <!-- File Upload -->
              <v-file-input
                :model-value="file"
                @update:model-value="onFileChange"
                label="Select Document"
                placeholder="Choose PDF or Word file"
                variant="outlined"
                prepend-icon=""
                prepend-inner-icon="ri-file-text-line"
                accept=".pdf,.doc,.docx"
                show-size
                class="mb-4"
                hint="Supported formats: PDF, DOC, DOCX (max 20MB)"
                persistent-hint
              >
                <template #selection="{ fileNames }">
                  <v-chip
                    v-for="fileName in fileNames"
                    :key="fileName"
                    color="primary"
                    variant="outlined"
                  >
                    {{ fileName }}
                  </v-chip>
                </template>
              </v-file-input>

              <!-- Title Input -->
              <v-text-field
                v-model="title"
                label="Document Title"
                placeholder="e.g. Employment Contract"
                variant="outlined"
                prepend-inner-icon="ri-text"
                class="mb-4"
                :rules="[v => !!v || 'Title is required']"
              />

              <!-- Template Badge (if applied) -->
              <v-alert 
                v-if="appliedTemplate" 
                type="success" 
                variant="tonal" 
                class="mb-4"
                closable
                @click:close="appliedTemplate = null"
              >
                <div class="d-flex align-center">
                  <v-icon icon="ri-file-check-line" class="mr-2" />
                  <div>
                    <div class="font-weight-bold">Template Applied</div>
                    <div class="text-body-2">{{ appliedTemplate.name }}</div>
                  </div>
                </div>
              </v-alert>

              <!-- Signature Level (collapsed by default) -->
              <v-expansion-panels variant="accordion" class="mb-6">
                <v-expansion-panel>
                  <v-expansion-panel-title>
                    <v-icon icon="ri-shield-check-line" class="mr-2" />
                    Signature Security Level
                    <v-chip size="x-small" class="ml-2" color="primary" variant="tonal">
                      {{ signatureLevel }}
                    </v-chip>
                  </v-expansion-panel-title>
                  <v-expansion-panel-text>
                    <SignatureLevelSelector v-model="signatureLevel" />
                  </v-expansion-panel-text>
                </v-expansion-panel>
              </v-expansion-panels>

              <!-- Error Alert -->
              <v-alert 
                v-if="error" 
                type="error" 
                variant="tonal" 
                closable 
                class="mb-4"
                @click:close="error = ''"
              >
                {{ error }}
              </v-alert>

              <!-- Actions -->
              <div class="d-flex gap-4 justify-end">
                <v-btn variant="outlined" color="secondary" to="/">
                  Cancel
                </v-btn>
                <v-btn
                  type="submit"
                  color="primary"
                  size="large"
                  :loading="uploading"
                  :disabled="!canUpload"
                >
                  <v-icon icon="ri-arrow-right-line" class="mr-2" />
                  Continue to Prepare
                </v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>

        <!-- AI Template Suggestions Dialog -->
        <v-dialog v-model="showTemplateSuggestions" max-width="600" persistent>
          <v-card>
            <v-card-title class="d-flex align-center bg-purple text-white">
              <v-avatar color="purple-lighten-3" class="mr-3">
                <v-icon icon="ri-robot-line" />
              </v-avatar>
              AI Template Suggestions
            </v-card-title>

            <v-card-text class="pt-4">
              <v-alert type="info" variant="tonal" class="mb-4">
                <v-icon icon="ri-lightbulb-line" class="mr-2" />
                We found templates that match your document. Apply one to auto-configure fields.
              </v-alert>

              <v-progress-linear v-if="analyzingDocument" indeterminate class="mb-4" />

              <template v-if="!analyzingDocument && aiStore.suggestions?.length > 0">
                <TemplateSuggestion
                  v-for="suggestion in aiStore.suggestions.slice(0, 3)"
                  :key="suggestion.template.id"
                  :suggestion="suggestion"
                  class="mb-3"
                  @apply="applyTemplate"
                />
              </template>
            </v-card-text>

            <v-divider />

            <v-card-actions class="pa-4">
              <v-btn block variant="outlined" @click="skipTemplates">
                Skip - Configure Manually
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Help Section -->
        <v-card variant="tonal" color="info" class="mt-4">
          <v-card-text>
            <div class="d-flex align-start">
              <v-icon icon="ri-information-line" class="mr-3 mt-1" />
              <div>
                <div class="font-weight-bold mb-1">What happens next?</div>
                <ul class="text-body-2 pl-4 mb-0">
                  <li>Your document will open in preview mode</li>
                  <li>Add signers by their email addresses</li>
                  <li>Draw signature fields directly on the PDF</li>
                  <li>Submit to notify all parties</li>
                </ul>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<style scoped>
/* Simple, clean styles */
</style>
