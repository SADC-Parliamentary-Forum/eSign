<script setup>
import { useAuthStore } from '@/stores/auth'
import { $api, getErrorMessage } from '@/utils/api'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
  },
})

const authStore = useAuthStore()
const router = useRouter()

const form = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const isCurrentPasswordVisible = ref(false)
const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const loading = ref(false)
const error = ref('')

async function handleSubmit() {
  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'New passwords do not match'
    return
  }
  
  loading.value = true
  error.value = ''

  try {
    await $api('/auth/password', {
      method: 'PUT',
      body: form.value,
    })

    // Update user state locally to clear the flag (assuming backend cleared it)
    await authStore.fetchUser() // This is safer
    
    router.push('/')
  } catch (e) {
    error.value = getErrorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard flat :max-width="500" class="mt-12 mt-sm-0 pa-5 pa-lg-7">
      <VCardText class="text-center mb-4">
        <h4 class="text-h4 mb-1">Change Password 🔒</h4>
        <p class="mb-0">For your security, you must change your password before continuing.</p>
      </VCardText>

      <VCardText>
        <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Current Password -->
            <VCol cols="12">
              <VTextField
                v-model="form.current_password"
                label="Current Password (Temporary)"
                :type="isCurrentPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isCurrentPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                @click:append-inner="isCurrentPasswordVisible = !isCurrentPasswordVisible"
              />
            </VCol>

            <!-- New Password -->
            <VCol cols="12">
              <VTextField
                v-model="form.password"
                label="New Password"
                hint="At least 12 characters"
                :type="isPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </VCol>

            <!-- Confirm Password -->
            <VCol cols="12">
              <VTextField
                v-model="form.password_confirmation"
                label="Confirm New Password"
                :type="isConfirmPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isConfirmPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
              />
            </VCol>

            <VCol cols="12">
              <VBtn block type="submit" :loading="loading">
                Update Password
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.auth-wrapper {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
