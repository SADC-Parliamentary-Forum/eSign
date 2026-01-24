<script setup>
/**
 * Enhanced User Profile Page
 * Modern tabbed layout with signature management, activity, and security
 */
import { useAuthStore } from '@/stores/auth'
import { $api } from '@/utils/api'
import { useDisplay } from 'vuetify'
import { formatDateTime } from '@/utils/formatters'

const authStore = useAuthStore()
const { mobile } = useDisplay()

const loading = ref(true)
const saving = ref(false)
const success = ref('')
const error = ref('')
const activeTab = ref('profile')

// Profile form
const form = ref({
  name: '',
  email: '',
  phone: '',
  department: '',
  job_title: '',
})

// Avatar
const avatarFile = ref(null)
const avatarPreview = ref(null)

// Password form
const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)

// Signatures
const signatures = ref([])
const loadingSignatures = ref(false)
const showSignatureDialog = ref(false)
const signatureSaving = ref(false)
const signatureCanvas = ref(null)
const signatureType = ref('signature') // 'signature' or 'initials'
const signatureName = ref('')
const signatureMethod = ref('draw') // 'draw', 'type', 'upload'
const signatureTypeText = ref('')
const signatureUploadedImage = ref(null)
const signatureFonts = ['Dancing Script', 'Great Vibes', 'Sacramento', 'Parisienne', 'Allura']
const selectedFont = ref('Dancing Script')
let signatureCtx = null
let isDrawingSignature = false

// Activity
const activities = ref([])
const loadingActivities = ref(false)

// Notification preferences
const notificationPrefs = ref({
  email_document_sent: true,
  email_document_signed: true,
  email_document_completed: true,
  push_enabled: false,
})

onMounted(async () => {
  await loadProfile()
  await loadSignatures()
  await loadActivities()
})

async function loadProfile() {
  loading.value = true
  try {
    const user = await $api('/auth/me')
    form.value = {
      name: user.name || '',
      email: user.email || '',
      phone: user.phone || '',
      department: user.department || '',
      job_title: user.job_title || '',
    }
    avatarPreview.value = user.avatar_url || null
  } catch (e) {
    error.value = 'Failed to load profile: ' + (e.message || 'Unknown error')
  } finally {
    loading.value = false
  }
}

async function saveProfile() {
  saving.value = true
  error.value = ''
  success.value = ''
  
  try {
    await $api('/auth/profile', {
      method: 'PUT',
      body: form.value,
    })
    
    localStorage.setItem('user_name', form.value.name)
    localStorage.setItem('user_email', form.value.email)
    
    success.value = 'Profile updated successfully!'
    await authStore.fetchUser()
  } catch (e) {
    error.value = 'Failed to update profile: ' + (e.message || 'Unknown error')
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    error.value = 'Passwords do not match'
    return
  }
  
  saving.value = true
  error.value = ''
  success.value = ''
  
  try {
    await $api('/auth/password', {
      method: 'PUT',
      body: passwordForm.value,
    })
    
    success.value = 'Password changed successfully!'
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (e) {
    error.value = 'Failed to change password: ' + (e.message || 'Unknown error')
  } finally {
    saving.value = false
  }
}

// Avatar handling
function onAvatarChange(event) {
  const file = event.target.files?.[0]
  if (file) {
    avatarFile.value = file
    avatarPreview.value = URL.createObjectURL(file)
  }
}

async function uploadAvatar() {
  if (!avatarFile.value) return
  
  saving.value = true
  try {
    const formData = new FormData()
    formData.append('avatar', avatarFile.value)
    
    await $api('/auth/avatar', {
      method: 'POST',
      body: formData,
    })
    
    success.value = 'Avatar updated!'
    avatarFile.value = null
    await authStore.fetchUser()
  } catch (e) {
    error.value = 'Failed to upload avatar: ' + e.message
  } finally {
    saving.value = false
  }
}

// Signatures
async function loadSignatures() {
  loadingSignatures.value = true
  try {
    const res = await $api('/signatures/mine')
    signatures.value = Array.isArray(res) ? res : (res.data || [])
  } catch (e) {
    console.error('Failed to load signatures:', e)
  } finally {
    loadingSignatures.value = false
  }
}

function openSignatureDialog(type = 'signature') {
  signatureType.value = type
  signatureName.value = type === 'signature' ? 'My Signature' : 'My Initials'
  signatureMethod.value = 'draw'
  signatureTypeText.value = ''
  signatureUploadedImage.value = null
  showSignatureDialog.value = true
  nextTick(initSignatureCanvas)
}

function initSignatureCanvas() {
  if (!signatureCanvas.value) return
  signatureCtx = signatureCanvas.value.getContext('2d')
  signatureCtx.lineWidth = 2
  signatureCtx.lineCap = 'round'
  signatureCtx.strokeStyle = '#000'
  signatureCtx.fillStyle = '#fff'
  signatureCtx.fillRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height)
}

function startSignatureDrawing(e) {
  isDrawingSignature = true
  const rect = signatureCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  signatureCtx.beginPath()
  signatureCtx.moveTo(x, y)
}

function drawSignature(e) {
  if (!isDrawingSignature) return
  e.preventDefault()
  const rect = signatureCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  signatureCtx.lineTo(x, y)
  signatureCtx.stroke()
}

function stopSignatureDrawing() {
  isDrawingSignature = false
  signatureCtx?.closePath()
}

function clearSignatureCanvas() {
  if (signatureCtx) {
    signatureCtx.fillStyle = '#fff'
    signatureCtx.fillRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height)
    signatureCtx.strokeStyle = '#000'
  }
}

function handleSignatureUpload(e) {
  const file = e.target.files?.[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = ev => {
      signatureUploadedImage.value = ev.target.result
    }
    reader.readAsDataURL(file)
  }
}

function generateTypedSignature() {
  const tCanvas = document.createElement('canvas')
  tCanvas.width = 450
  tCanvas.height = 150
  const tCtx = tCanvas.getContext('2d')
  tCtx.fillStyle = '#fff'
  tCtx.fillRect(0, 0, tCanvas.width, tCanvas.height)
  tCtx.font = `48px "${selectedFont.value}"`
  tCtx.fillStyle = '#000'
  tCtx.textAlign = 'center'
  tCtx.textBaseline = 'middle'
  tCtx.fillText(signatureTypeText.value || signatureName.value, tCanvas.width / 2, tCanvas.height / 2)
  return tCanvas.toDataURL('image/png')
}

async function saveSignature() {
  signatureSaving.value = true
  try {
    let imageData = null
    let method = 'DRAWN'
    
    if (signatureMethod.value === 'draw') {
      imageData = signatureCanvas.value.toDataURL('image/png')
      method = 'DRAWN'
    } else if (signatureMethod.value === 'upload') {
      imageData = signatureUploadedImage.value
      method = 'UPLOADED'
    } else {
      imageData = generateTypedSignature()
      method = 'TYPED'
    }
    
    await $api('/signatures/mine', {
      method: 'POST',
      body: {
        type: signatureType.value,
        name: signatureName.value,
        image_data: imageData,
        method,
      },
    })
    
    showSignatureDialog.value = false
    await loadSignatures()
    success.value = 'Signature saved!'
  } catch (e) {
    error.value = 'Failed to save signature: ' + e.message
  } finally {
    signatureSaving.value = false
  }
}

async function deleteSignature(id) {
  if (!confirm('Delete this signature?')) return
  
  try {
    await $api(`/signatures/mine/${id}`, { method: 'DELETE' })
    await loadSignatures()
    success.value = 'Signature deleted'
  } catch (e) {
    error.value = 'Failed to delete signature: ' + e.message
  }
}

async function setDefaultSignature(id) {
  try {
    await $api(`/signatures/mine/${id}/default`, { method: 'PATCH' })
    await loadSignatures()
    success.value = 'Default signature updated'
  } catch (e) {
    error.value = 'Failed to set default: ' + e.message
  }
}

// Activity
async function loadActivities() {
  loadingActivities.value = true
  try {
    const res = await $api('/documents/activity?limit=10')
    activities.value = res.data || res || []
  } catch (e) {
    console.error('Failed to load activities:', e)
  } finally {
    loadingActivities.value = false
  }
}

function formatDate(date) {
  return formatDateTime(date)
}

function getActivityIcon(type) {
  const icons = {
    'document_created': 'mdi-file-plus',
    'document_sent': 'mdi-send',
    'document_signed': 'mdi-draw',
    'document_completed': 'mdi-check-circle',
    'document_declined': 'mdi-close-circle',
  }
  return icons[type] || 'mdi-file-document'
}

function getActivityColor(type) {
  const colors = {
    'document_created': 'info',
    'document_sent': 'primary',
    'document_signed': 'success',
    'document_completed': 'success',
    'document_declined': 'error',
  }
  return colors[type] || 'grey'
}

watch(() => signatureMethod.value, (val) => {
  if (val === 'draw') {
    nextTick(initSignatureCanvas)
  }
})
</script>

<template>
  <VContainer class="py-6" max-width="1000">
    <!-- Header with Avatar -->
    <div class="profile-header mb-6">
      <div class="d-flex align-center gap-4">
        <VBtn icon="mdi-arrow-left" variant="text" to="/" />
        
        <div class="avatar-section position-relative">
          <VAvatar size="80" color="primary" class="elevation-3">
            <VImg v-if="avatarPreview" :src="avatarPreview" cover />
            <span v-else class="text-h4 text-white">{{ form.name?.charAt(0) || 'U' }}</span>
          </VAvatar>
          <VBtn
            icon="mdi-camera"
            size="x-small"
            color="primary"
            class="avatar-edit-btn"
            @click="$refs.avatarInput.click()"
          />
          <input
            ref="avatarInput"
            type="file"
            accept="image/*"
            hidden
            @change="onAvatarChange"
          >
        </div>
        
        <div class="flex-grow-1">
          <h1 class="text-h5 font-weight-bold mb-1">{{ form.name || 'User' }}</h1>
          <div class="text-body-2 text-medium-emphasis">{{ form.email }}</div>
          <div v-if="form.job_title" class="text-body-2 text-medium-emphasis">
            {{ form.job_title }} · {{ form.department }}
          </div>
        </div>
        
        <VBtn
          v-if="avatarFile"
          color="primary"
          size="small"
          :loading="saving"
          @click="uploadAvatar"
        >
          Save Avatar
        </VBtn>
      </div>
    </div>

    <!-- Alerts -->
    <VAlert v-if="success" type="success" variant="tonal" closable class="mb-4" @click:close="success = ''">
      {{ success }}
    </VAlert>
    <VAlert v-if="error" type="error" variant="tonal" closable class="mb-4" @click:close="error = ''">
      {{ error }}
    </VAlert>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
      <VProgressCircular indeterminate color="primary" />
    </div>

    <template v-else>
      <!-- Tabs -->
      <VTabs v-model="activeTab" class="mb-4" :show-arrows="mobile">
        <VTab value="profile">
          <VIcon icon="mdi-account" class="mr-2" />
          Profile
        </VTab>
        <VTab value="signatures">
          <VIcon icon="mdi-draw" class="mr-2" />
          Signatures
        </VTab>
        <VTab value="security">
          <VIcon icon="mdi-shield-lock" class="mr-2" />
          Security
        </VTab>
        <VTab value="activity">
          <VIcon icon="mdi-history" class="mr-2" />
          Activity
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <!-- Profile Tab -->
        <VWindowItem value="profile">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="mdi-account-circle" class="mr-2" />
              Personal Information
            </VCardTitle>
            
            <VCardText>
              <VForm @submit.prevent="saveProfile">
                <VRow>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="form.name"
                      label="Full Name"
                      prepend-inner-icon="mdi-account"
                      variant="outlined"
                      required
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="form.email"
                      label="Email Address"
                      type="email"
                      prepend-inner-icon="mdi-email"
                      variant="outlined"
                      disabled
                      hint="Email cannot be changed"
                      persistent-hint
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="form.phone"
                      label="Phone Number"
                      prepend-inner-icon="mdi-phone"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="form.job_title"
                      label="Job Title"
                      prepend-inner-icon="mdi-briefcase"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol cols="12">
                    <VTextField
                      v-model="form.department"
                      label="Department"
                      prepend-inner-icon="mdi-office-building"
                      variant="outlined"
                    />
                  </VCol>
                </VRow>

                <VBtn type="submit" color="primary" :loading="saving" class="mt-4">
                  <VIcon icon="mdi-content-save" class="mr-2" />
                  Save Changes
                </VBtn>
              </VForm>
            </VCardText>
          </VCard>

          <!-- Notification Preferences -->
          <VCard class="mt-4">
            <VCardTitle class="d-flex align-center">
              <VIcon icon="mdi-bell" class="mr-2" />
              Notification Preferences
            </VCardTitle>
            <VCardText>
              <VSwitch v-model="notificationPrefs.email_document_sent" label="Email when document is sent for signing" color="primary" hide-details />
              <VSwitch v-model="notificationPrefs.email_document_signed" label="Email when someone signs my document" color="primary" hide-details />
              <VSwitch v-model="notificationPrefs.email_document_completed" label="Email when document is fully completed" color="primary" hide-details />
              <VDivider class="my-4" />
              <VSwitch v-model="notificationPrefs.push_enabled" label="Enable push notifications" color="primary" hide-details />
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Signatures Tab -->
        <VWindowItem value="signatures">
          <VCard>
            <VCardTitle class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon icon="mdi-draw" class="mr-2" />
                My Signatures
              </div>
              <div class="d-flex gap-2">
                <VBtn size="small" variant="tonal" prepend-icon="mdi-pen" @click="openSignatureDialog('signature')">
                  Add Signature
                </VBtn>
                <VBtn size="small" variant="tonal" prepend-icon="mdi-format-letter-case" @click="openSignatureDialog('initials')">
                  Add Initials
                </VBtn>
              </div>
            </VCardTitle>
            
            <VCardText>
              <div v-if="loadingSignatures" class="text-center py-4">
                <VProgressCircular indeterminate size="24" />
              </div>
              
              <VRow v-else-if="signatures.length > 0">
                <VCol v-for="sig in signatures" :key="sig.id" cols="12" sm="6" md="4">
                  <VCard variant="outlined" class="signature-card">
                    <div class="signature-preview pa-4 bg-grey-lighten-4">
                      <img :src="sig.image_data" :alt="sig.name" class="w-100" style="max-height: 80px; object-fit: contain;" />
                    </div>
                    <VCardText class="py-2">
                      <div class="d-flex align-center justify-space-between">
                        <div>
                          <div class="font-weight-medium">{{ sig.name }}</div>
                          <VChip size="x-small" :color="sig.type === 'signature' ? 'primary' : 'secondary'" class="mt-1">
                            {{ sig.type }}
                          </VChip>
                          <VChip v-if="sig.is_default" size="x-small" color="success" class="mt-1 ml-1">
                            Default
                          </VChip>
                        </div>
                        <VMenu>
                          <template #activator="{ props }">
                            <VBtn icon="mdi-dots-vertical" variant="text" size="small" v-bind="props" />
                          </template>
                          <VList density="compact">
                            <VListItem v-if="!sig.is_default" @click="setDefaultSignature(sig.id)">
                              <VListItemTitle>Set as Default</VListItemTitle>
                            </VListItem>
                            <VListItem @click="deleteSignature(sig.id)" class="text-error">
                              <VListItemTitle>Delete</VListItemTitle>
                            </VListItem>
                          </VList>
                        </VMenu>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
              
              <VEmptyState
                v-else
                icon="mdi-draw"
                title="No signatures yet"
                text="Create your first signature to use when signing documents"
              >
                <template #actions>
                  <VBtn color="primary" @click="openSignatureDialog('signature')">Create Signature</VBtn>
                </template>
              </VEmptyState>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Security Tab -->
        <VWindowItem value="security">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="mdi-lock" class="mr-2" />
              Change Password
            </VCardTitle>
            
            <VCardText>
              <VForm @submit.prevent="changePassword">
                <VRow>
                  <VCol cols="12">
                    <VTextField
                      v-model="passwordForm.current_password"
                      label="Current Password"
                      :type="showCurrentPassword ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock"
                      :append-inner-icon="showCurrentPassword ? 'mdi-eye-off' : 'mdi-eye'"
                      variant="outlined"
                      @click:append-inner="showCurrentPassword = !showCurrentPassword"
                      required
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="passwordForm.password"
                      label="New Password"
                      :type="showNewPassword ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-plus"
                      :append-inner-icon="showNewPassword ? 'mdi-eye-off' : 'mdi-eye'"
                      variant="outlined"
                      @click:append-inner="showNewPassword = !showNewPassword"
                      required
                      hint="Minimum 8 characters"
                      persistent-hint
                    />
                  </VCol>
                  <VCol cols="12" md="6">
                    <VTextField
                      v-model="passwordForm.password_confirmation"
                      label="Confirm New Password"
                      :type="showNewPassword ? 'text' : 'password'"
                      prepend-inner-icon="mdi-lock-check"
                      variant="outlined"
                      required
                    />
                  </VCol>
                </VRow>

                <VBtn type="submit" color="warning" :loading="saving" class="mt-4">
                  <VIcon icon="mdi-lock-reset" class="mr-2" />
                  Update Password
                </VBtn>
              </VForm>
            </VCardText>
          </VCard>

          <!-- Two-Factor (Placeholder) -->
          <VCard class="mt-4">
            <VCardTitle class="d-flex align-center">
              <VIcon icon="mdi-two-factor-authentication" class="mr-2" />
              Two-Factor Authentication
            </VCardTitle>
            <VCardText>
              <VAlert type="info" variant="tonal" class="mb-4">
                Two-factor authentication adds an extra layer of security to your account.
              </VAlert>
              <VBtn color="primary" variant="outlined" disabled>
                Enable 2FA (Coming Soon)
              </VBtn>
            </VCardText>
          </VCard>

          <!-- Danger Zone -->
          <VCard class="mt-4 border-error">
            <VCardTitle class="d-flex align-center text-error">
              <VIcon icon="mdi-alert-circle" class="mr-2" />
              Danger Zone
            </VCardTitle>
            <VCardText>
              <div class="text-body-2 mb-4">
                Once you delete your account, there is no going back. Please be certain.
              </div>
              <VBtn color="error" variant="outlined" disabled>
                Delete Account (Contact Admin)
              </VBtn>
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- Activity Tab -->
        <VWindowItem value="activity">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="mdi-history" class="mr-2" />
              Recent Activity
            </VCardTitle>
            
            <VCardText>
              <div v-if="loadingActivities" class="text-center py-4">
                <VProgressCircular indeterminate size="24" />
              </div>
              
              <VTimeline v-else-if="activities.length > 0" density="compact" side="end">
                <VTimelineItem
                  v-for="activity in activities"
                  :key="activity.id"
                  :dot-color="getActivityColor(activity.event_type)"
                  size="small"
                >
                  <template #icon>
                    <VIcon :icon="getActivityIcon(activity.event_type)" size="14" />
                  </template>
                  <div class="d-flex justify-space-between align-center">
                    <div>
                      <div class="font-weight-medium">{{ activity.description || activity.event_type }}</div>
                      <div class="text-caption text-medium-emphasis">{{ activity.document?.title || 'Document' }}</div>
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ formatDate(activity.created_at) }}
                    </div>
                  </div>
                </VTimelineItem>
              </VTimeline>
              
              <VEmptyState
                v-else
                icon="mdi-history"
                title="No recent activity"
                text="Your document activity will appear here"
              />
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>
    </template>

    <!-- Signature Dialog -->
    <VDialog v-model="showSignatureDialog" max-width="500" persistent>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>{{ signatureType === 'signature' ? 'Create Signature' : 'Create Initials' }}</span>
          <VBtn icon="mdi-close" variant="text" size="small" @click="showSignatureDialog = false" />
        </VCardTitle>
        
        <VCardText>
          <VTextField
            v-model="signatureName"
            label="Name/Label"
            variant="outlined"
            density="compact"
            class="mb-4"
          />
          
          <VTabs v-model="signatureMethod" class="mb-4">
            <VTab value="draw">Draw</VTab>
            <VTab value="type">Type</VTab>
            <VTab value="upload">Upload</VTab>
          </VTabs>
          
          <VWindow v-model="signatureMethod">
            <!-- Draw -->
            <VWindowItem value="draw">
              <div class="signature-canvas-wrapper border rounded mb-2">
                <canvas
                  ref="signatureCanvas"
                  width="450"
                  height="150"
                  @mousedown="startSignatureDrawing"
                  @mousemove="drawSignature"
                  @mouseup="stopSignatureDrawing"
                  @mouseleave="stopSignatureDrawing"
                  @touchstart.prevent="startSignatureDrawing"
                  @touchmove.prevent="drawSignature"
                  @touchend="stopSignatureDrawing"
                  style="width: 100%; cursor: crosshair;"
                />
              </div>
              <VBtn size="small" variant="text" @click="clearSignatureCanvas">
                <VIcon icon="mdi-eraser" class="mr-1" /> Clear
              </VBtn>
            </VWindowItem>
            
            <!-- Type -->
            <VWindowItem value="type">
              <VTextField
                v-model="signatureTypeText"
                :placeholder="signatureName"
                variant="outlined"
                class="mb-4"
              />
              <VSelect
                v-model="selectedFont"
                :items="signatureFonts"
                label="Font Style"
                variant="outlined"
                density="compact"
              />
              <div class="signature-preview border rounded pa-4 mt-4 text-center" :style="{ fontFamily: selectedFont, fontSize: '32px' }">
                {{ signatureTypeText || signatureName }}
              </div>
            </VWindowItem>
            
            <!-- Upload -->
            <VWindowItem value="upload">
              <VFileInput
                label="Upload Signature Image"
                accept="image/*"
                variant="outlined"
                prepend-icon=""
                prepend-inner-icon="mdi-image"
                @change="handleSignatureUpload"
              />
              <div v-if="signatureUploadedImage" class="signature-preview border rounded pa-4 mt-4 text-center">
                <img :src="signatureUploadedImage" alt="Uploaded signature" style="max-height: 100px;" />
              </div>
            </VWindowItem>
          </VWindow>
        </VCardText>
        
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showSignatureDialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="signatureSaving" @click="saveSignature">Save Signature</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.avatar-section {
  position: relative;
}

.avatar-edit-btn {
  position: absolute;
  bottom: 0;
  right: 0;
}

.signature-card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.signature-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.signature-canvas-wrapper {
  background: #fff;
  touch-action: none;
}
</style>
