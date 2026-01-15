<script setup>
import { useRouter } from 'vue-router'
import { useAIStore } from '@/stores/ai'
import { useTemplateStore } from '@/stores/templates'
import TemplateSuggestion from '@/components/ai/TemplateSuggestion.vue'
import SignatureLevelSelector from '@/components/signatures/SignatureLevelSelector.vue'

const router = useRouter()
const aiStore = useAIStore()
const templateStore = useTemplateStore()

// Steps
const step = ref(1)

// Step 1: Document Upload
const title = ref('')
const file = ref(null)
const uploading = ref(false)
const documentId = ref(null)
const analyzingDocument = ref(false)

// AI Template Suggestions
const showTemplateSuggestions = ref(false)
const selectedTemplate = ref(null)
const appliedTemplate = ref(null)

// Template-based fields
const amount = ref(null)
const autoCalculatedRoles = ref([])

// Step 2: Add Signers
const signers = ref([{ name: '', email: '', role: '' }])
const sequentialSigning = ref(false)

// Step 3: Send
const sending = ref(false)
const expiresInDays = ref(30)

// Signature Level (Phase 10: Legal Defensibility)
const signatureLevel = ref('SIMPLE')

const error = ref('')
const success = ref('')

function addSigner() {
  signers.value.push({ name: '', email: '', role: '' })
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
  formData.append('signature_level', signatureLevel.value)

  try {
    const res = await $api('/documents', {
      method: 'POST',
      body: formData,
    })

    documentId.value = res.id
    
    // Analyze document for template suggestions
    await analyzeForTemplates()
    
    if (aiStore.hasSuggestions) {
      showTemplateSuggestions.value = true
    }
    else {
      step.value = 2
    }
  }
  catch (e) {
    error.value = e.message || 'Upload failed'
  }
  finally {
    uploading.value = false
  }
}

async function analyzeForTemplates() {
  analyzingDocument.value = true
  try {
    const fileToAnalyze = file.value[0] || file.value
    await aiStore.suggestTemplates(fileToAnalyze)
  }
  catch (e) {
    console.error('Failed to get AI suggestions:', e)
    // Don't block the flow if AI fails
  }
  finally {
    analyzingDocument.value = false
  }
}

async function applyTemplate(template) {
  selectedTemplate.value = template
  appliedTemplate.value = template
  
  // Load template details
  await templateStore.fetchTemplate(template.id)
  const fullTemplate = templateStore.activeTemplate
  
  // Pre-populate signers based on template roles
  if (fullTemplate.roles?.length > 0) {
    signers.value = fullTemplate.roles.map(role => ({
      name: '',
      email: '',
      role: role.role,
      action: role.action,
      required: role.required,
      signing_order: role.signing_order,
    }))
  }
  
  // Check if template requires financial amount
  if (fullTemplate.amount_required) {
    // Show amount input before proceeding
    return
  }
  
  showTemplateSuggestions.value = false
  step.value = 2
}

function skipTemplates() {
  aiStore.clearSuggestions()
  showTemplateSuggestions.value = false
  step.value = 2
}

async function calculateRolesFromAmount() {
  if (!appliedTemplate.value || !amount.value) return
  
  try {
    const response = await $api(`/templates/${appliedTemplate.value.id}/threshold-matrix`)
    const thresholds = response.thresholds || []
    
    // Find matching threshold
    const matchingThreshold = thresholds.find(t =>
      amount.value >= t.min_amount && 
      (t.max_amount === null || amount.value <= t.max_amount),
    )
    
    if (matchingThreshold) {
      autoCalculatedRoles.value = matchingThreshold.required_roles
      
      // Update signers to include required roles
      const requiredRoles = matchingThreshold.required_roles
      signers.value = requiredRoles.map((role, index) => ({
        name: '',
        email: '',
        role,
        action: 'SIGN',
        required: true,
        signing_order: index + 1,
      }))
    }
  }
  catch (e) {
    console.error('Failed to calculate roles:', e)
  }
}

async function saveSigners() {
  if (!canProceedToStep3.value) return

  try {
    await $api(`/documents/${documentId.value}/signers`, {
      method: 'POST',
      body: { 
        signers: signers.value,
        template_id: appliedTemplate.value?.id,
        amount: amount.value,
      },
    })
    step.value = 3
  }
  catch (e) {
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
        expires_in_days: expiresInDays.value,
      },
    })

    success.value = 'Document sent for signing!'
    setTimeout(() => router.push('/'), 1500)
  }
  catch (e) {
    error.value = e.message || 'Failed to send document'
  }
  finally {
    sending.value = false
  }
}
</script>

<template>
  <v-row justify="center">
    <v-col cols="12" md="8" lg="6">
      <!-- Stepper Header -->
      <v-card class="mb-6">
        <v-card-text class="pa-4">
          <div class="d-flex justify-space-between align-center">
            <div v-for="(s, i) in ['Upload', 'Add Signers', 'Send']" :key="i" class="text-center flex-grow-1">
              <v-avatar 
                :color="step > i + 1 ? 'success' : step === i + 1 ? 'primary' : 'grey'" 
                size="32"
                class="mb-1"
              >
                <v-icon v-if="step > i + 1" icon="mdi-check" size="18" />
                <span v-else>{{ i + 1 }}</span>
              </v-avatar>
              <div class="text-caption" :class="step === i + 1 ? 'font-weight-bold' : 'text-medium-emphasis'">
                {{ s }}
              </div>
            </div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Step 1: Upload -->
      <v-card v-if="step === 1">
        <v-card-item>
          <v-card-title class="text-h5">
            Upload Document
          </v-card-title>
          <v-card-subtitle>Add a document for signing</v-card-subtitle>
        </v-card-item>

        <v-card-text>
          <v-form @submit.prevent="handleUpload">
            <v-row>
              <v-col cols="12">
                <v-text-field
                  v-model="title"
                  label="Document Title"
                  placeholder="e.g. Service Agreement"
                  variant="outlined"
                  required
                />
              </v-col>

              <v-col cols="12">
                <v-file-input
                  v-model="file"
                  label="Document File"
                  placeholder="Select PDF or DOCX"
                  prepend-icon=""
                  prepend-inner-icon="mdi-file-upload"
                  accept=".pdf,.doc,.docx"
                  variant="outlined"
                  show-size
                />
              </v-col>

              <v-col cols="12">
                <v-alert v-if="error" type="error" variant="tonal" closable class="mb-4">
                  {{ error }}
                </v-alert>
              </v-col>

              <v-col cols="12" class="d-flex gap-4 justify-end">
                <v-btn variant="outlined" color="secondary" to="/">
                  Cancel
                </v-btn>
                <v-btn type="submit" :loading="uploading" :disabled="!canProceedToStep2">
                  Continue
                  <v-icon icon="mdi-arrow-right" class="ms-1" />
                </v-btn>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>

      <!-- AI Template Suggestions -->
      <v-card v-if="showTemplateSuggestions" class="mt-4">
        <v-card-item>
          <template #prepend>
            <v-avatar color="purple">
              <v-icon>mdi-robot</v-icon>
            </v-avatar>
          </template>
          
          <v-card-title>AI Template Suggestions</v-card-title>
          <v-card-subtitle>
            We found {{ aiStore.suggestions.length }} matching template(s)
          </v-card-subtitle>
        </v-card-item>

        <v-card-text>
          <v-alert type="info" variant="tonal" class="mb-4">
            <v-icon icon="mdi-lightbulb" class="mr-2" />
            Apply a template to automatically configure roles and signing order
          </v-alert>

          <v-progress-linear v-if="analyzingDocument" indeterminate class="mb-4" />

          <template v-else>
            <template-suggestion
              v-for="suggestion in aiStore.suggestions.slice(0, 3)"
              :key="suggestion.template.id"
              :suggestion="suggestion"
              class="mb-3"
              @apply="applyTemplate"
            />

            <v-btn
              block
              variant="outlined"
              @click="skipTemplates"
            >
              Skip - Configure Manually
            </v-btn>
          </template>
        </v-card-text>
      </v-card>

      <!-- Amount Input (if template requires it) -->
      <v-card v-if="appliedTemplate?.amount_required && !step > 1" class="mt-4">
        <v-card-title>Financial Information Required</v-card-title>
        <v-card-text>
          <v-text-field
            v-model.number="amount"
            label="Document Amount"
            type="number"
            prefix="$"
            variant="outlined"
            @update:model-value="calculateRolesFromAmount"
          />

          <v-alert v-if="autoCalculatedRoles.length > 0" type="success" variant="tonal" class="mt-4">
            <div class="font-weight-bold mb-2">
              Required Approvers (based on ${{ amount?.toLocaleString() }}):
            </div>
            <v-chip
              v-for="role in autoCalculatedRoles"
              :key="role"
              class="mr-2"
              color="success"
              size="small"
            >
              {{ role }}
            </v-chip>
          </v-alert>

          <v-btn
            block
            color="primary"
            class="mt-4"
            :disabled="!amount"
            @click="() => { showTemplateSuggestions = false; step = 2 }"
          >
            Continue with Calculated Roles
          </v-btn>
        </v-card-text>
      </v-card>

      <!-- Step 2: Add Signers -->
      <v-card v-if="step === 2">
        <v-card-item>
          <v-card-title class="text-h5">
            Add Signers
          </v-card-title>
          <v-card-subtitle>Who needs to sign this document?</v-card-subtitle>
        </v-card-item>

        <v-card-text>
          <v-alert v-if="appliedTemplate" type="info" variant="tonal" class="mb-4">
            <v-icon icon="mdi-file-check" class="mr-2" />
            Template Applied: <strong>{{ appliedTemplate.name }}</strong>
          </v-alert>

          <div v-for="(signer, i) in signers" :key="i" class="mb-3">
            <v-row align="center">
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="signer.name"
                  label="Name"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="signer.email"
                  label="Email"
                  type="email"
                  variant="outlined"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" sm="3">
                <v-chip
                  v-if="signer.role"
                  :color="signer.required ? 'primary' : 'grey'"
                  size="small"
                >
                  {{ signer.role }}
                </v-chip>
                <span v-else class="text-caption text-medium-emphasis">No role</span>
              </v-col>
              <v-col cols="12" sm="1">
                <v-btn
                  icon="mdi-delete"
                  variant="text"
                  color="error"
                  size="small"
                  @click="removeSigner(i)"
                  :disabled="signers.length === 1"
                />
              </v-col>
            </v-row>
          </div>

          <v-btn variant="text" prepend-icon="mdi-plus" @click="addSigner" class="mb-4">
            Add Another Signer
          </v-btn>

          <v-switch
            v-model="sequentialSigning"
            label="Require sequential signing (in order)"
            color="primary"
            class="mb-4"
          />

          <v-alert v-if="error" type="error" variant="tonal" closable class="mb-4">
            {{ error }}
          </v-alert>

          <div class="d-flex gap-4 justify-end">
            <v-btn variant="outlined" @click="step = 1">
              Back
            </v-btn>
            <v-btn :disabled="!canProceedToStep3" @click="saveSigners">
              Continue
              <v-icon icon="mdi-arrow-right" class="ms-1" />
            </v-btn>
          </div>
        </v-card-text>
      </v-card>

      <!-- Step 3: Send -->
      <v-card v-if="step === 3">
        <v-card-item>
          <v-card-title class="text-h5">
            Send for Signing
          </v-card-title>
          <v-card-subtitle>Review and send to signers</v-card-subtitle>
        </v-card-item>

        <v-card-text>
          <v-alert type="info" variant="tonal" class="mb-4">
            <div class="font-weight-bold mb-1">
              {{ title }}
            </div>
            <div class="text-body-2">
              {{ signers.length }} signer(s) • {{ sequentialSigning ? 'Sequential' : 'Parallel' }} signing
              <span v-if="amount"> • Amount: ${{ amount.toLocaleString() }}</span>
            </div>
          </v-alert>

          <v-list density="compact" class="mb-4">
            <v-list-item
              v-for="(signer, i) in signers"
              :key="i"
              :title="signer.name"
              :subtitle="signer.email"
            >
              <template #prepend>
                <v-avatar color="primary" variant="tonal" size="36">
                  {{ i + 1 }}
                </v-avatar>
              </template>
              <template #append>
                <v-chip v-if="signer.role" size="small">
                  {{ signer.role }}
                </v-chip>
              </template>
            </v-list-item>
          </v-list>

          <v-text-field
            v-model.number="expiresInDays"
            label="Expires in (days)"
            type="number"
            variant="outlined"
            class="mb-4"
          />

          <v-alert v-if="error" type="error" variant="tonal" closable class="mb-4">
            {{ error }}
          </v-alert>
          <v-alert v-if="success" type="success" variant="tonal" class="mb-4">
            {{ success }}
          </v-alert>

          <div class="d-flex gap-4 justify-end">
            <v-btn variant="outlined" @click="step = 2">
              Back
            </v-btn>
            <v-btn color="success" :loading="sending" @click="sendDocument">
              <v-icon icon="mdi-send" class="me-1" />
              Send for Signing
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>
