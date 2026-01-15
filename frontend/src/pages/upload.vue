<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

// Steps
const step = ref(1)

// Step 1: Document Upload
const title = ref('')
const file = ref(null)
const uploading = ref(false)
const documentId = ref(null)

// Step 2: Add Signers
const signers = ref([{ name: '', email: '' }])
const sequentialSigning = ref(false)

// Step 3: Send
const sending = ref(false)
const expiresInDays = ref(30)

const error = ref('')
const success = ref('')

function addSigner() {
  signers.value.push({ name: '', email: '' })
}

function removeSigner(index) {
  if (signers.value.length > 1) {
    signers.value.splice(index, 1)
  }
}

const canProceedToStep2 = computed(() => file.value && title.value)
const canProceedToStep3 = computed(() => signers.value.every(s => s.name && s.email))

async function handleUpload() {
  if (!file.value) return

  uploading.value = true
  error.value = ''

  const formData = new FormData()
  formData.append('file', file.value[0] || file.value)
  formData.append('title', title.value)

  try {
    const res = await $api('/documents', {
      method: 'POST',
      body: formData
    })

    documentId.value = res.id
    step.value = 2
  } catch (e) {
    error.value = e.message || 'Upload failed'
  } finally {
    uploading.value = false
  }
}

async function saveSigners() {
  if (!canProceedToStep3.value) return

  try {
    await $api(`/documents/${documentId.value}/signers`, {
      method: 'POST',
      body: { signers: signers.value }
    })
    step.value = 3
  } catch (e) {
    error.value = e.message || 'Failed to add signers'
  }
}

async function sendDocument() {
  sending.value = true
  error.value = ''

  try {
    await $api(`/documents/${documentId.value}/send`, {
      method: 'POST',
      body: {
        sequential: sequentialSigning.value,
        expires_in_days: expiresInDays.value
      }
    })

    success.value = 'Document sent for signing!'
    setTimeout(() => router.push('/'), 1500)
  } catch (e) {
    error.value = e.message || 'Failed to send document'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <VRow justify="center">
    <VCol cols="12" md="8" lg="6">
      <!-- Stepper Header -->
      <VCard class="mb-6">
        <VCardText class="pa-4">
          <div class="d-flex justify-space-between align-center">
            <div v-for="(s, i) in ['Upload', 'Add Signers', 'Send']" :key="i" class="text-center flex-grow-1">
              <VAvatar 
                :color="step > i + 1 ? 'success' : step === i + 1 ? 'primary' : 'grey'" 
                size="32"
                class="mb-1"
              >
                <VIcon v-if="step > i + 1" icon="ri-check-line" size="18" />
                <span v-else>{{ i + 1 }}</span>
              </VAvatar>
              <div class="text-caption" :class="step === i + 1 ? 'font-weight-bold' : 'text-disabled'">{{ s }}</div>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Step 1: Upload -->
      <VCard v-if="step === 1">
        <VCardItem>
          <VCardTitle class="text-h5">Upload Document</VCardTitle>
          <VCardSubtitle>Add a document for signing</VCardSubtitle>
        </VCardItem>

        <VCardText>
          <VForm @submit.prevent="handleUpload">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="title"
                  label="Document Title"
                  placeholder="e.g. Service Agreement"
                  variant="outlined"
                  required
                />
              </VCol>

              <VCol cols="12">
                <VFileInput
                  v-model="file"
                  label="Document File"
                  placeholder="Select PDF or DOCX"
                  prepend-icon=""
                  prepend-inner-icon="ri-file-upload-line"
                  accept=".pdf,.doc,.docx"
                  variant="outlined"
                  show-size
                />
              </VCol>

              <VCol cols="12">
                <VAlert v-if="error" type="error" variant="tonal" closable class="mb-4">{{ error }}</VAlert>
              </VCol>

              <VCol cols="12" class="d-flex gap-4 justify-end">
                <VBtn variant="outlined" color="secondary" to="/">Cancel</VBtn>
                <VBtn type="submit" :loading="uploading" :disabled="!canProceedToStep2">
                  Continue
                  <VIcon icon="ri-arrow-right-line" class="ms-1" />
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>

      <!-- Step 2: Add Signers -->
      <VCard v-if="step === 2">
        <VCardItem>
          <VCardTitle class="text-h5">Add Signers</VCardTitle>
          <VCardSubtitle>Who needs to sign this document?</VCardSubtitle>
        </VCardItem>

        <VCardText>
          <div v-for="(signer, i) in signers" :key="i" class="d-flex gap-3 mb-3 align-center">
            <VTextField v-model="signer.name" label="Name" variant="outlined" density="compact" class="flex-grow-1" />
            <VTextField v-model="signer.email" label="Email" type="email" variant="outlined" density="compact" class="flex-grow-1" />
            <VBtn icon="ri-delete-bin-line" variant="text" color="error" @click="removeSigner(i)" :disabled="signers.length === 1" />
          </div>

          <VBtn variant="text" prepend-icon="ri-add-line" @click="addSigner" class="mb-4">Add Another Signer</VBtn>

          <VSwitch v-model="sequentialSigning" label="Require sequential signing (in order)" color="primary" class="mb-4" />

          <VAlert v-if="error" type="error" variant="tonal" closable class="mb-4">{{ error }}</VAlert>

          <div class="d-flex gap-4 justify-end">
            <VBtn variant="outlined" @click="step = 1">Back</VBtn>
            <VBtn :disabled="!canProceedToStep3" @click="saveSigners">
              Continue
              <VIcon icon="ri-arrow-right-line" class="ms-1" />
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Step 3: Send -->
      <VCard v-if="step === 3">
        <VCardItem>
          <VCardTitle class="text-h5">Send for Signing</VCardTitle>
          <VCardSubtitle>Review and send to signers</VCardSubtitle>
        </VCardItem>

        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            <div class="font-weight-bold mb-1">{{ title }}</div>
            <div class="text-body-2">
              {{ signers.length }} signer(s) • {{ sequentialSigning ? 'Sequential' : 'Parallel' }} signing
            </div>
          </VAlert>

          <VList density="compact" class="mb-4">
            <VListItem v-for="(signer, i) in signers" :key="i" :title="signer.name" :subtitle="signer.email">
              <template #prepend>
                <VAvatar color="primary" variant="tonal" size="36">{{ i + 1 }}</VAvatar>
              </template>
            </VListItem>
          </VList>

          <VTextField
            v-model.number="expiresInDays"
            label="Expires in (days)"
            type="number"
            variant="outlined"
            class="mb-4"
          />

          <VAlert v-if="error" type="error" variant="tonal" closable class="mb-4">{{ error }}</VAlert>
          <VAlert v-if="success" type="success" variant="tonal" class="mb-4">{{ success }}</VAlert>

          <div class="d-flex gap-4 justify-end">
            <VBtn variant="outlined" @click="step = 2">Back</VBtn>
            <VBtn color="success" :loading="sending" @click="sendDocument">
              <VIcon icon="ri-send-plane-line" class="me-1" />
              Send for Signing
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

