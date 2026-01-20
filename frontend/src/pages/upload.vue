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
const isSelfSign = ref(false)

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
      formData.append('is_self_sign', isSelfSign.value ? '1' : '0')
      
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
          template_id: appliedTemplate.value.id,
          is_self_sign: isSelfSign.value,
        },
      })
    }

    // Check for AI template suggestions (only for fresh file uploads)
    if (file.value && !appliedTemplate.value && !isSelfSign.value) {
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
  <VContainer class="py-8">
    <VRow justify="center">
      <VCol
        cols="12"
        md="8"
        lg="6"
      >
        <!-- Header -->
        <div class="text-center mb-8">
          <VIcon
            icon="ri-upload-cloud-2-line"
            size="64"
            color="primary"
            class="mb-4"
          />
          <h1 class="text-h4 font-weight-bold mb-2">
            Upload Document
          </h1>
          <p class="text-body-1 text-medium-emphasis">
            Upload a PDF or Word document to prepare for signing
          </p>
        </div>

        <!-- Main Upload Card -->
        <VCard
          elevation="2"
          class="mb-6"
        >
          <VCardText class="pa-6">
            <VForm @submit.prevent="handleUpload">
              <!-- Signing Mode Selector -->
              <div class="mb-6">
                <div class="text-subtitle-1 font-weight-bold mb-2">How would you like to sign?</div>
                <VRadioGroup v-model="isSelfSign" inline hide-details color="primary">
                  <VRadio :value="true" label="Sign myself only" />
                  <VRadio :value="false" label="Send to others for signing" />
                </VRadioGroup>
              </div>

              <!-- File Upload -->
              <VFileInput
                :model-value="file"
                label="Select Document"
                placeholder="Choose PDF or Word file"
                variant="outlined"
                @update:model-value="onFileChange"
                prepend-icon=""
                prepend-inner-icon="ri-file-text-line"
                accept=".pdf,.doc,.docx"
                show-size
                class="mb-4"
                hint="Supported formats: PDF, DOC, DOCX (max 20MB)"
                persistent-hint
              >
                <template #selection="{ fileNames }">
                  <VChip
                    v-for="fileName in fileNames"
                    :key="fileName"
                    color="primary"
                    variant="outlined"
                  >
                    {{ fileName }}
                  </VChip>
                </template>
              </VFileInput>

              <!-- Title Input -->
              <VTextField
                v-model="title"
                label="Document Title"
                placeholder="e.g. Employment Contract"
                variant="outlined"
                prepend-inner-icon="ri-text"
                class="mb-4"
                :rules="[v => !!v || 'Title is required']"
              />

              <!-- Template Badge (if applied) -->
              <VAlert 
                v-if="appliedTemplate" 
                type="success" 
                variant="tonal" 
                class="mb-4"
                closable
                @click:close="appliedTemplate = null"
              >
                <div class="d-flex align-center">
                  <VIcon
                    icon="ri-file-check-line"
                    class="mr-2"
                  />
                  <div>
                    <div class="font-weight-bold">
                      Template Applied
                    </div>
                    <div class="text-body-2">
                      {{ appliedTemplate.name }}
                    </div>
                  </div>
                </div>
              </VAlert>

              <!-- Signature Level (collapsed by default) -->
              <VExpansionPanels
                variant="accordion"
                class="mb-6"
              >
                <VExpansionPanel>
                  <VExpansionPanelTitle>
                    <VIcon icon="ri-shield-check-line"
class="mr-2" />
                    Signature Security Level
                    <VChip size="x-small" class="ml-2" color="primary" variant="tonal">
                      {{ signatureLevel }}
                    </VChip>
                  </VExpansionPanelTitle>
                  <VExpansionPanelText>
                    <SignatureLevelSelector v-model="signatureLevel" />
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>

              <!-- Error Alert -->
              <VAlert 
                v-if="error" 
                type="error" 
                variant="tonal" 
                closable 
                class="mb-4"
                @click:close="error = ''"
              >
                {{ error }}
              </VAlert>

              <!-- Actions -->
              <div class="d-flex gap-4 justify-end">
                <VBtn
                  variant="outlined"
                  color="secondary"
                  to="/"
                >
                  Cancel
                </VBtn>
                <VBtn
                  type="submit"
                  color="primary"
                  size="large"
                  :loading="uploading"
                  :disabled="!canUpload"
                >
                  <VIcon
                    icon="ri-arrow-right-line"
                    class="mr-2"
                  />
                  Continue to Prepare
                </VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>

        <!-- AI Template Suggestions Dialog -->
        <VDialog
          v-model="showTemplateSuggestions"
          max-width="600"
          persistent
        >
          <VCard>
            <VCardTitle class="d-flex align-center bg-purple text-white">
              <VAvatar
                color="purple-lighten-3"
                class="mr-3"
              >
                <VIcon icon="ri-robot-line" />
              </VAvatar>
              AI Template Suggestions
            </VCardTitle>

            <VCardText class="pt-4">
              <VAlert
                type="info"
                variant="tonal"
                class="mb-4"
              >
                <VIcon
                  icon="ri-lightbulb-line"
                  class="mr-2"
                />
                We found templates that match your document. Apply one to auto-configure fields.
              </VAlert>

              <VProgressLinear
                v-if="analyzingDocument"
                indeterminate
                class="mb-4"
              />

              <template v-if="!analyzingDocument && aiStore.suggestions?.length > 0">
                <TemplateSuggestion
                  v-for="suggestion in aiStore.suggestions.slice(0, 3)"
                  :key="suggestion.template.id"
                  :suggestion="suggestion"
                  class="mb-3"
                  @apply="applyTemplate"
                />
              </template>
            </VCardText>

            <VDivider />

            <VCardActions class="pa-4">
              <VBtn
                block
                variant="outlined"
                @click="skipTemplates"
              >
                Skip - Configure Manually
              </VBtn>
            </VCardActions>
          </VCard>
        </VDialog>

        <!-- Help Section -->
        <VCard
          variant="tonal"
          color="info"
          class="mt-4"
        >
          <VCardText>
            <div class="d-flex align-start">
              <VIcon
                icon="ri-information-line"
                class="mr-3 mt-1"
              />
              <div>
                <div class="font-weight-bold mb-1">
                  What happens next?
                </div>
                <ul class="text-body-2 pl-4 mb-0">
                  <li>Your document will open in preview mode</li>
                  <li>Add signers by their email addresses</li>
                  <li>Draw signature fields directly on the PDF</li>
                  <li>Submit to notify all parties</li>
                </ul>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>

<style scoped>
/* Simple, clean styles */
</style>
