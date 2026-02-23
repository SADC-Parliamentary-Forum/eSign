<script setup>
/**
 * Template Creation - Simple Upload
 * Just upload a PDF and give it a name, then go to editor
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTemplateStore } from '@/stores/templates'

const router = useRouter()
const templateStore = useTemplateStore()

const name = ref('')
const category = ref('Contract')
const file = ref(null)
const loading = ref(false)
const error = ref('')

const categories = ['Contract', 'HR', 'Finance', 'Legal', 'Internal', 'Other']

async function handleCreate() {
  if (!name.value || !file.value) {
    error.value = 'Please provide a name and upload a PDF'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const formData = new FormData()
    formData.append('name', name.value)
    formData.append('category', category.value)
    formData.append('file', file.value)

    const template = await templateStore.createTemplate(formData)
    
    // Go directly to editor
    router.push(`/templates/${template.id}/edit`)
  } catch (e) {
    error.value = e.message || 'Failed to create template'
    console.error('Failed to create template:', e)
  } finally {
    loading.value = false
  }
}

function handleFileChange(e) {
  const f = e.target?.files?.[0]
  if (f) {
    file.value = f
    if (!name.value) {
      name.value = f.name.replace(/\.[^/.]+$/, '')
    }
  }
}
</script>

<template>
  <div class="d-flex justify-center align-center" style="min-height: 80vh;">
    <VCard max-width="500" width="100%" class="pa-2">
      <VCardTitle class="text-h5 font-weight-bold text-center pt-6">
        <VIcon size="48" color="primary" class="mb-2">mdi-file-plus</VIcon>
        <div>Create Template</div>
      </VCardTitle>

      <VCardText class="pt-4">
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VTextField
          v-model="name"
          label="Template Name"
          placeholder="e.g. Employment Contract"
          variant="outlined"
          prepend-inner-icon="mdi-file-document"
          class="mb-4"
        />

        <VSelect
          v-model="category"
          :items="categories"
          label="Category"
          variant="outlined"
          prepend-inner-icon="mdi-folder"
          class="mb-4"
        />

        <VFileInput
          label="Upload Template File"
          accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
          variant="outlined"
          prepend-icon=""
          prepend-inner-icon="mdi-file-pdf-box"
          show-size
          @change="handleFileChange"
        />

        <VAlert v-if="file" type="success" variant="tonal" density="compact" class="mt-2">
          <strong>{{ file.name }}</strong> ready
        </VAlert>
      </VCardText>

      <VCardActions class="px-4 pb-4">
        <VBtn variant="text" @click="router.push('/templates')">
          Cancel
        </VBtn>
        <VSpacer />
        <VBtn
          color="primary"
          variant="flat"
          size="large"
          :loading="loading"
          :disabled="!name || !file"
          @click="handleCreate"
        >
          <VIcon start>mdi-arrow-right</VIcon>
          Continue to Editor
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>
