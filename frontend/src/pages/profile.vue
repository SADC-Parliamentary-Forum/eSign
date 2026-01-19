<script setup>
/**
 * User Profile Page
 * View and update user profile information
 */
import { useAuthStore } from '@/stores/auth'
import { $api } from '@/utils/api'

const authStore = useAuthStore()

const loading = ref(false)
const saving = ref(false)
const success = ref('')
const error = ref('')

const form = ref({
  name: '',
  email: '',
  phone: '',
  department: '',
  job_title: '',
})

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const showPasswordForm = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)

onMounted(async () => {
  await loadProfile()
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
    
    // Update localStorage
    localStorage.setItem('user_name', form.value.name)
    localStorage.setItem('user_email', form.value.email)
    
    success.value = 'Profile updated successfully!'
    
    // Refresh auth store
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
    showPasswordForm.value = false
  } catch (e) {
    error.value = 'Failed to change password: ' + (e.message || 'Unknown error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <VContainer
    class="py-6"
    max-width="800"
  >
    <VRow>
      <VCol cols="12">
        <!-- Header -->
        <div class="d-flex align-center mb-6">
          <VBtn
            icon="mdi-arrow-left"
            variant="text"
            to="/"
            class="mr-2"
          />
          <div>
            <h1 class="text-h5 font-weight-bold">
              My Profile
            </h1>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Manage your account settings
            </p>
          </div>
        </div>

        <!-- Alerts -->
        <VAlert
          v-if="success"
          type="success"
          variant="tonal"
          closable
          class="mb-4"
          @click:close="success = ''"
        >
          {{ success }}
        </VAlert>
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

        <!-- Loading -->
        <div
          v-if="loading"
          class="text-center py-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
          />
        </div>

        <template v-else>
          <!-- Profile Card -->
          <VCard class="mb-4">
            <VCardTitle class="d-flex align-center">
              <VIcon
                icon="mdi-account-circle"
                class="mr-2"
              />
              Personal Information
            </VCardTitle>
            
            <VCardText>
              <VForm @submit.prevent="saveProfile">
                <VRow>
                  <VCol
cols="12"
                        md="6"
>
                    <VTextField
                      v-model="form.name"
                      label="Full Name"
                      prepend-inner-icon="mdi-account"
                      variant="outlined"
                      required
                    />
                  </VCol>
                  <VCol
cols="12"
                        md="6"
>
                    <VTextField
                      v-model="form.email"
                      label="Email Address"
                      type="email"
                      prepend-inner-icon="mdi-email"
                      variant="outlined"
                      required
                      disabled
                      hint="Email cannot be changed"
                      persistent-hint
                    />
                  </VCol>
                  <VCol
cols="12"
                        md="6"
>
                    <VTextField
                      v-model="form.phone"
                      label="Phone Number"
                      prepend-inner-icon="mdi-phone"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol
cols="12"
                        md="6"
>
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

                <VBtn 
                  type="submit" 
                  color="primary" 
                  :loading="saving"
                  class="mt-2"
                >
                  <VIcon
                    icon="mdi-content-save"
                    class="mr-2"
                  />
                  Save Changes
                </VBtn>
              </VForm>
            </VCardText>
          </VCard>

          <!-- Password Card -->
          <VCard>
            <VCardTitle class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon
                  icon="mdi-lock"
                  class="mr-2"
                />
                Security
              </div>
              <VBtn 
                variant="text" 
                size="small"
                @click="showPasswordForm = !showPasswordForm"
              >
                {{ showPasswordForm ? 'Cancel' : 'Change Password' }}
              </VBtn>
            </VCardTitle>
            
            <VExpandTransition>
              <VCardText v-if="showPasswordForm">
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
                    <VCol cols="12"
md="6">
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
                    <VCol cols="12"
md="6">
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

                  <VBtn 
                    type="submit" 
                    color="warning" 
                    :loading="saving"
                    class="mt-2"
                  >
                    <VIcon
icon="mdi-lock-reset"
                           class="mr-2"
/>
                    Update Password
                  </VBtn>
                </VForm>
              </VCardText>
            </VExpandTransition>
            
            <VCardText v-if="!showPasswordForm">
              <div class="text-body-2 text-medium-emphasis">
                <VIcon
                  icon="mdi-shield-check"
                  color="success"
                  class="mr-1"
                />
                Your password is secure. Click "Change Password" to update it.
              </div>
            </VCardText>
          </VCard>
        </template>
      </VCol>
    </VRow>
  </VContainer>
</template>
