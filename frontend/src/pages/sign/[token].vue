<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const token = computed(() => route.params.token)
const document = ref(null)
const signer = ref(null)
const fields = ref([])
const requiresAccount = ref(false)
const loading = ref(true)
const error = ref('')
const submitting = ref(false)

// Signature canvas
const canvas = ref(null)
let ctx = null
let isDrawing = false

// Registration form
const showRegister = ref(false)
const registerForm = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})
const registering = ref(false)

onMounted(async () => {
  await fetchDocument()
})

async function fetchDocument() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`/api/sign/${token.value}`)
    const data = await res.json()
    
    if (!res.ok) {
      error.value = data.message || 'Failed to load document'
      return
    }
    
    document.value = data.document
    signer.value = data.signer
    fields.value = data.fields || []
    requiresAccount.value = data.requires_account

    // Mark as viewed
    await fetch(`/api/sign/${token.value}/view`, { method: 'POST' })
    
    setTimeout(initCanvas, 100)
  } catch (e) {
    error.value = 'Failed to load document'
  } finally {
    loading.value = false
  }
}

function initCanvas() {
  if (!canvas.value) return
  ctx = canvas.value.getContext('2d')
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.strokeStyle = '#000'
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.value.width, canvas.value.height)
}

function startDrawing(e) {
  isDrawing = true
  const rect = canvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  ctx.beginPath()
  ctx.moveTo(x, y)
}

function draw(e) {
  if (!isDrawing) return
  e.preventDefault()
  const rect = canvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  ctx.lineTo(x, y)
  ctx.stroke()
}

function stopDrawing() {
  isDrawing = false
  ctx?.closePath()
}

function clearCanvas() {
  if (!ctx) return
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.value.width, canvas.value.height)
  ctx.strokeStyle = '#000'
}

async function register() {
  registering.value = true
  error.value = ''
  try {
    const res = await fetch('/api/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(registerForm.value)
    })
    const data = await res.json()
    
    if (!res.ok) {
      error.value = data.message || 'Registration failed'
      return
    }
    
    // Save token and refresh
    localStorage.setItem('token', data.access_token)
    showRegister.value = false
    requiresAccount.value = false
  } catch (e) {
    error.value = 'Registration failed'
  } finally {
    registering.value = false
  }
}

async function submitSignature() {
  if (requiresAccount.value) {
    showRegister.value = true
    return
  }
  
  submitting.value = true
  error.value = ''
  
  const signatureData = canvas.value.toDataURL('image/png')
  const authToken = localStorage.getItem('token')
  
  try {
    const res = await fetch(`/api/sign/${token.value}/sign`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(authToken ? { 'Authorization': `Bearer ${authToken}` } : {})
      },
      body: JSON.stringify({ signature_data: signatureData })
    })
    const data = await res.json()
    
    if (!res.ok) {
      if (data.requires_registration) {
        showRegister.value = true
        return
      }
      error.value = data.message || 'Signing failed'
      return
    }
    
    // Success
    router.push('/sign/success')
  } catch (e) {
    error.value = 'Signing failed'
  } finally {
    submitting.value = false
  }
}

async function declineToSign() {
  if (!confirm('Are you sure you want to decline signing this document?')) return
  
  submitting.value = true
  try {
    const res = await fetch(`/api/sign/${token.value}/decline`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reason: 'User declined' })
    })
    
    if (res.ok) {
      router.push('/sign/declined')
    }
  } catch (e) {
    error.value = 'Failed to decline'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="sign-page">
    <!-- Loading -->
    <div v-if="loading" class="text-center py-16">
      <VProgressCircular indeterminate color="primary" size="64" />
      <div class="text-body-1 text-disabled mt-4">Loading document...</div>
    </div>

    <!-- Error -->
    <VCard v-else-if="error && !document" class="mx-auto my-8" max-width="500">
      <VCardText class="text-center py-10">
        <VIcon icon="ri-error-warning-line" size="64" color="error" class="mb-4" />
        <h3 class="text-h6 mb-2">Unable to Load Document</h3>
        <p class="text-body-2 text-disabled">{{ error }}</p>
      </VCardText>
    </VCard>

    <!-- Document -->
    <VCard v-else class="mx-auto my-4" max-width="600">
      <VCardItem>
        <VCardTitle class="text-h5">{{ document.title }}</VCardTitle>
        <VCardSubtitle>
          You've been asked to sign this document
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText>
        <!-- Signer Info -->
        <div class="d-flex align-center mb-4 pa-3 bg-grey-lighten-4 rounded">
          <VAvatar color="primary" variant="tonal" class="me-3">
            <VIcon icon="ri-user-line" />
          </VAvatar>
          <div>
            <div class="font-weight-medium">{{ signer.name }}</div>
            <div class="text-body-2 text-disabled">{{ signer.email }}</div>
          </div>
          <VSpacer />
          <VChip :color="signer.can_sign ? 'success' : 'warning'" size="small">
            {{ signer.can_sign ? 'Ready to Sign' : 'Waiting' }}
          </VChip>
        </div>

        <!-- Download Link -->
        <VBtn 
          block 
          variant="outlined" 
          prepend-icon="ri-file-download-line" 
          class="mb-6"
          :href="'/storage/' + document.file_path"
          target="_blank"
        >
          Review Document (PDF)
        </VBtn>

        <!-- Signature Canvas -->
        <div v-if="signer.can_sign">
          <h4 class="text-subtitle-1 font-weight-medium mb-2">Your Signature</h4>
          <p class="text-body-2 text-disabled mb-3">Draw your signature in the box below</p>
          
          <div class="canvas-wrapper mb-4">
            <canvas 
              ref="canvas" 
              width="540" 
              height="150"
              @mousedown="startDrawing"
              @mousemove="draw"
              @mouseup="stopDrawing"
              @mouseleave="stopDrawing"
              @touchstart="startDrawing"
              @touchmove="draw"
              @touchend="stopDrawing"
            />
          </div>

          <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>

          <div class="d-flex gap-2">
            <VBtn variant="text" @click="clearCanvas">Clear</VBtn>
            <VSpacer />
            <VBtn variant="outlined" color="error" @click="declineToSign">Decline</VBtn>
            <VBtn color="success" :loading="submitting" @click="submitSignature">
              Sign Document
            </VBtn>
          </div>
        </div>

        <VAlert v-else type="info" variant="tonal">
          It's not your turn to sign yet. You'll be notified when it's your turn.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Registration Dialog -->
    <VDialog v-model="showRegister" max-width="400" persistent>
      <VCard>
        <VCardTitle class="pt-4">Create Account to Sign</VCardTitle>
        <VCardText>
          <p class="text-body-2 text-disabled mb-4">
            Please create an account to sign this document. Your signature will be saved for future use.
          </p>
          <VTextField v-model="registerForm.name" label="Full Name" class="mb-3" />
          <VTextField v-model="registerForm.email" label="Email" type="email" class="mb-3" />
          <VTextField v-model="registerForm.password" label="Password" type="password" class="mb-3" />
          <VTextField v-model="registerForm.password_confirmation" label="Confirm Password" type="password" />
          <VAlert v-if="error" type="error" variant="tonal" class="mt-3">{{ error }}</VAlert>
        </VCardText>
        <VCardActions class="pa-4">
          <VBtn variant="text" @click="showRegister = false">Cancel</VBtn>
          <VSpacer />
          <VBtn color="primary" :loading="registering" @click="register">Create Account</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.sign-page {
  min-height: 100vh;
  background: rgb(var(--v-theme-surface));
  padding: 16px;
}
.canvas-wrapper {
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: white;
  overflow: hidden;
}
canvas {
  display: block;
  width: 100%;
  cursor: crosshair;
  touch-action: none;
}
</style>
