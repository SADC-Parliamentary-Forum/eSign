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
      body: form.value
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
      body: passwordForm.value
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
  <v-container class="py-6" max-width="800">
    <v-row>
      <v-col cols="12">
        <!-- Header -->
        <div class="d-flex align-center mb-6">
          <v-btn icon="mdi-arrow-left" variant="text" to="/" class="mr-2" />
          <div>
            <h1 class="text-h5 font-weight-bold">My Profile</h1>
            <p class="text-body-2 text-medium-emphasis mb-0">Manage your account settings</p>
          </div>
        </div>

        <!-- Alerts -->
        <v-alert v-if="success" type="success" variant="tonal" closable class="mb-4" @click:close="success = ''">
          {{ success }}
        </v-alert>
        <v-alert v-if="error" type="error" variant="tonal" closable class="mb-4" @click:close="error = ''">
          {{ error }}
        </v-alert>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-8">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <template v-else>
          <!-- Profile Card -->
          <v-card class="mb-4">
            <v-card-title class="d-flex align-center">
              <v-icon icon="mdi-account-circle" class="mr-2" />
              Personal Information
            </v-card-title>
            
            <v-card-text>
              <v-form @submit.prevent="saveProfile">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="form.name"
                      label="Full Name"
                      prepend-inner-icon="mdi-account"
                      variant="outlined"
                      required
                    />
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
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
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="form.phone"
                      label="Phone Number"
                      prepend-inner-icon="mdi-phone"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="form.job_title"
                      label="Job Title"
                      prepend-inner-icon="mdi-briefcase"
                      variant="outlined"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.department"
                      label="Department"
                      prepend-inner-icon="mdi-office-building"
                      variant="outlined"
                    />
                  </v-col>
                </v-row>

                <v-btn 
                  type="submit" 
                  color="primary" 
                  :loading="saving"
                  class="mt-2"
                >
                  <v-icon icon="mdi-content-save" class="mr-2" />
                  Save Changes
                </v-btn>
              </v-form>
            </v-card-text>
          </v-card>

          <!-- Password Card -->
          <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <v-icon icon="mdi-lock" class="mr-2" />
                Security
              </div>
              <v-btn 
                variant="text" 
                size="small"
                @click="showPasswordForm = !showPasswordForm"
              >
                {{ showPasswordForm ? 'Cancel' : 'Change Password' }}
              </v-btn>
            </v-card-title>
            
            <v-expand-transition>
              <v-card-text v-if="showPasswordForm">
                <v-form @submit.prevent="changePassword">
                  <v-row>
                    <v-col cols="12">
                      <v-text-field
                        v-model="passwordForm.current_password"
                        label="Current Password"
                        :type="showCurrentPassword ? 'text' : 'password'"
                        prepend-inner-icon="mdi-lock"
                        :append-inner-icon="showCurrentPassword ? 'mdi-eye-off' : 'mdi-eye'"
                        @click:append-inner="showCurrentPassword = !showCurrentPassword"
                        variant="outlined"
                        required
                      />
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        v-model="passwordForm.password"
                        label="New Password"
                        :type="showNewPassword ? 'text' : 'password'"
                        prepend-inner-icon="mdi-lock-plus"
                        :append-inner-icon="showNewPassword ? 'mdi-eye-off' : 'mdi-eye'"
                        @click:append-inner="showNewPassword = !showNewPassword"
                        variant="outlined"
                        required
                        hint="Minimum 8 characters"
                        persistent-hint
                      />
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        v-model="passwordForm.password_confirmation"
                        label="Confirm New Password"
                        :type="showNewPassword ? 'text' : 'password'"
                        prepend-inner-icon="mdi-lock-check"
                        variant="outlined"
                        required
                      />
                    </v-col>
                  </v-row>

                  <v-btn 
                    type="submit" 
                    color="warning" 
                    :loading="saving"
                    class="mt-2"
                  >
                    <v-icon icon="mdi-lock-reset" class="mr-2" />
                    Update Password
                  </v-btn>
                </v-form>
              </v-card-text>
            </v-expand-transition>
            
            <v-card-text v-if="!showPasswordForm">
              <div class="text-body-2 text-medium-emphasis">
                <v-icon icon="mdi-shield-check" color="success" class="mr-1" />
                Your password is secure. Click "Change Password" to update it.
              </div>
            </v-card-text>
          </v-card>
        </template>
      </v-col>
    </v-row>
  </v-container>
</template>
