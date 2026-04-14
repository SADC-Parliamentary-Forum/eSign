<script setup>
import VuePdfEmbed from 'vue-pdf-embed/dist/index.essential.mjs'
import { useTemplateStore } from '@/stores/templates'

const route = useRoute()
const router = useRouter()
const templateStore = useTemplateStore()

// State
const loading = ref(true)
const processing = ref(false)
const signing = ref(false)
const template = ref(null)
const pdfSource = ref(null)
const pageCount = ref(0)
const uploadedFiles = ref([])
const createdDocuments = ref([])
const step = ref(1) // 1 = upload, 2 = preview, 3 = sign

// Snackbar
const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('success')

onMounted(async () => {
  await loadTemplate()
})

async function loadTemplate() {
  loading.value = true
  try {
    template.value = await templateStore.fetchTemplate(route.params.id)
    
    // Check if bulk enabled
    if (!template.value.is_bulk_enabled) {
      showSnackbar('This template is not enabled for bulk signing', 'warning')
    }
    
    // Load template PDF
    const token = localStorage.getItem('token')
    const response = await fetch(`${import.meta.env.VITE_API_URL || '/api'}/templates/${route.params.id}/pdf`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    
    if (response.ok) {
      const blob = await response.blob()
      pdfSource.value = URL.createObjectURL(blob)
    }
  } catch (error) {
    console.error('Failed to load template:', error)
    showSnackbar('Failed to load template', 'error')
  } finally {
    loading.value = false
  }
}

function handleFilesSelected(e) {
  const files = Array.from(e.target?.files || e)
  uploadedFiles.value = files.filter(f => f.type === 'application/pdf')
  
  if (uploadedFiles.value.length < files.length) {
    showSnackbar('Some non-PDF files were skipped', 'warning')
  }
}

async function processDocuments() {
  if (uploadedFiles.value.length === 0) return
  
  processing.value = true
  createdDocuments.value = []
  
  try {
    for (const file of uploadedFiles.value) {
      // Create document
      const formData = new FormData()
      formData.append('file', file)
      formData.append('title', file.name.replace('.pdf', ''))
      
      const doc = await $api('/documents', { method: 'POST', body: formData })
      
      // Apply template
      await templateStore.applyTemplate(template.value.id, doc.id)
      
      createdDocuments.value.push({
        ...doc,
        fileName: file.name,
        status: 'ready'
      })
    }
    
    step.value = 2
    showSnackbar(`${createdDocuments.value.length} documents processed successfully`, 'success')
  } catch (error) {
    console.error('Failed to process documents:', error)
    showSnackbar('Failed to process some documents: ' + (error.message || 'Unknown error'), 'error')
  } finally {
    processing.value = false
  }
}

async function signAllDocuments() {
  signing.value = true
  
  try {
    // Call bulk sign endpoint
    const documentIds = createdDocuments.value.map(d => d.id)
    await $api('/documents/bulk-sign', {
      method: 'POST',
      body: { document_ids: documentIds }
    })
    
    step.value = 3
    showSnackbar('All documents signed successfully!', 'success')
  } catch (error) {
    console.error('Failed to sign documents:', error)
    showSnackbar('Failed to sign documents: ' + (error.message || 'Unknown error'), 'error')
  } finally {
    signing.value = false
  }
}

async function downloadAll() {
  try {
    const documentIds = createdDocuments.value.map(d => d.id)
    const token = localStorage.getItem('token')
    
    // Use proxy in development to avoid CORS issues
    const apiUrl = import.meta.env.DEV 
      ? '/api' 
      : (import.meta.env.VITE_API_URL || '/api')

    const response = await fetch(`${apiUrl}/documents/bulk-download`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ ids: documentIds }),
      // Increase timeout for bulk downloads
      signal: AbortSignal.timeout(300000), // 5 minutes
    })
    
    if (response.ok) {
      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `bulk-signed-${new Date().toISOString().split('T')[0]}.zip`
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
      showSnackbar('Download started', 'success')
    } else {
      throw new Error('Download failed')
    }
  } catch (error) {
    console.error('Failed to download:', error)
    showSnackbar('Failed to download documents', 'error')
  }
}

function reset() {
  uploadedFiles.value = []
  createdDocuments.value = []
  step.value = 1
}

function showSnackbar(message, color = 'success') {
  snackbarMessage.value = message
  snackbarColor.value = color
  snackbar.value = true
}

function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
}
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="loading" class="d-flex justify-center align-center" style="min-height: 400px;">
      <VProgressCircular indeterminate size="64" />
    </div>

    <template v-else-if="template">
      <!-- Header -->
      <div class="d-flex align-center gap-3 mb-6">
        <VBtn icon="mdi-arrow-left" variant="text" @click="router.push('/templates')" />
        <div class="flex-grow-1">
          <h2 class="text-h4 font-weight-bold">Bulk Sign</h2>
          <div class="text-body-1 text-medium-emphasis">{{ template.name }}</div>
        </div>
        <VChip v-if="template.is_bulk_enabled" color="success" variant="tonal">
          <VIcon start size="16">mdi-check</VIcon>
          Bulk Enabled
        </VChip>
      </div>

      <!-- Stepper -->
      <VCard class="mb-6">
        <VCardText class="pa-0">
          <VStepper v-model="step" :items="['Upload Files', 'Preview', 'Complete']" flat />
        </VCardText>
      </VCard>

      <!-- Step 1: Upload Files -->
      <VCard v-if="step === 1">
        <VCardText>
          <VRow>
            <!-- Left: Template Info -->
            <VCol cols="12" md="4">
              <h3 class="text-h6 mb-4">Template Preview</h3>
              <div 
                class="border rounded bg-grey-lighten-4 overflow-auto mb-4"
                style="height: 300px;"
              >
                <div v-if="pdfSource" class="pa-2 d-flex justify-center">
                  <VuePdfEmbed 
                    :source="pdfSource" 
                    :page="1"
                    :width="280"
                    @loaded="handleDocumentLoad"
                  />
                </div>
              </div>
              
              <VCard variant="tonal" color="info" class="pa-3">
                <div class="text-subtitle-2 font-weight-bold mb-2">Template Details</div>
                <div class="text-caption">
                  <div><strong>Fields:</strong> {{ template.fields?.length || 0 }}</div>
                  <div><strong>Category:</strong> {{ template.category || 'None' }}</div>
                  <div><strong>Used:</strong> {{ template.usage_count || 0 }} times</div>
                </div>
              </VCard>
            </VCol>

            <!-- Right: Upload Area -->
            <VCol cols="12" md="8">
              <h3 class="text-h6 mb-4">Upload Multiple Documents</h3>
              
              <VCard 
                variant="outlined"
                class="border-dashed pa-8 mb-4"
                :loading="processing"
              >
                <div class="text-center">
                  <VIcon size="64" color="primary" class="mb-4">mdi-file-multiple</VIcon>
                  <h4 class="text-h6 mb-2">Drop PDF files here</h4>
                  <p class="text-body-2 text-medium-emphasis mb-4">
                    Select multiple PDF documents with similar structure
                  </p>
                  
                  <VFileInput
                    accept=".pdf"
                    label="Select PDFs"
                    variant="outlined"
                    prepend-icon=""
                    prepend-inner-icon="mdi-file-pdf-box"
                    multiple
                    class="mx-auto"
                    style="max-width: 400px;"
                    @change="handleFilesSelected"
                  />
                </div>
              </VCard>

              <!-- Selected Files List -->
              <VCard v-if="uploadedFiles.length > 0" variant="outlined">
                <VCardTitle class="d-flex align-center">
                  <span>Selected Files ({{ uploadedFiles.length }})</span>
                  <VSpacer />
                  <VBtn size="small" variant="text" color="error" @click="uploadedFiles = []">
                    Clear All
                  </VBtn>
                </VCardTitle>
                <VDivider />
                <VList density="compact" style="max-height: 200px; overflow-y: auto;">
                  <VListItem v-for="(file, index) in uploadedFiles" :key="index">
                    <template #prepend>
                      <VIcon color="error">mdi-file-pdf-box</VIcon>
                    </template>
                    <VListItemTitle>{{ file.name }}</VListItemTitle>
                    <VListItemSubtitle>{{ (file.size / 1024 / 1024).toFixed(2) }} MB</VListItemSubtitle>
                  </VListItem>
                </VList>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
        
        <VDivider />
        
        <VCardActions class="pa-4">
          <VBtn variant="text" @click="router.push('/templates')">Cancel</VBtn>
          <VSpacer />
          <VBtn 
            color="primary" 
            variant="flat"
            size="large"
            :disabled="uploadedFiles.length === 0"
            :loading="processing"
            @click="processDocuments"
          >
            <VIcon start>mdi-cog</VIcon>
            Process {{ uploadedFiles.length }} Documents
          </VBtn>
        </VCardActions>
      </VCard>

      <!-- Step 2: Preview & Sign -->
      <VCard v-if="step === 2">
        <VCardTitle>
          <VIcon start color="success">mdi-check-circle</VIcon>
          {{ createdDocuments.length }} Documents Ready
        </VCardTitle>
        
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            Template fields have been applied to all documents. Review below and click "Sign All" when ready.
          </VAlert>
          
          <VList>
            <VListItem v-for="doc in createdDocuments" :key="doc.id">
              <template #prepend>
                <VAvatar color="success" variant="tonal">
                  <VIcon>mdi-file-check</VIcon>
                </VAvatar>
              </template>
              <VListItemTitle>{{ doc.fileName }}</VListItemTitle>
              <VListItemSubtitle>{{ template.fields?.length || 0 }} fields applied</VListItemSubtitle>
              <template #append>
                <VChip color="success" size="small" variant="tonal">Ready</VChip>
              </template>
            </VListItem>
          </VList>
        </VCardText>
        
        <VDivider />
        
        <VCardActions class="pa-4">
          <VBtn variant="text" @click="reset">Start Over</VBtn>
          <VSpacer />
          <VBtn 
            color="primary" 
            variant="flat"
            size="large"
            :loading="signing"
            @click="signAllDocuments"
          >
            <VIcon start>mdi-draw</VIcon>
            Sign All Documents
          </VBtn>
        </VCardActions>
      </VCard>

      <!-- Step 3: Complete -->
      <VCard v-if="step === 3">
        <VCardText class="text-center pa-8">
          <VAvatar color="success" size="80" class="mb-4">
            <VIcon size="48">mdi-check</VIcon>
          </VAvatar>
          
          <h3 class="text-h5 font-weight-bold mb-2">All Documents Signed!</h3>
          <p class="text-body-1 text-medium-emphasis mb-6">
            {{ createdDocuments.length }} documents have been successfully signed.
          </p>
          
          <div class="d-flex justify-center gap-4">
            <VBtn 
              color="primary" 
              variant="flat"
              size="large"
              prepend-icon="mdi-download"
              @click="downloadAll"
            >
              Download All (ZIP)
            </VBtn>
            
            <VBtn 
              variant="outlined"
              size="large"
              @click="router.push('/')"
            >
              Go to Dashboard
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </template>

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar" :color="snackbarColor" location="bottom end">
      {{ snackbarMessage }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.border-dashed {
  border-style: dashed !important;
}
</style>

