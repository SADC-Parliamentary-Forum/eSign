<script setup>
import { useAuthStore } from '@/stores/auth'
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  email: route.query.email || '',
  password: '',
  password_confirmation: '',
  token: route.query.token || '',
})

const isPasswordVisible = ref(false)
const error = ref('')
const message = ref('')
const loading = ref(false)

async function handleResetPassword() {
  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Passwords do not match'
    return
  }

  loading.value = true
  error.value = ''
  message.value = ''

  try {
    const success = await authStore.resetPassword({
      token: form.value.token,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    })

    if (success) {
      message.value = 'Password has been reset successfully!'
      setTimeout(() => {
        router.push('/login')
      }, 2000)
    }
  } catch (e) {
    error.value = e.message || 'Failed to reset password'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard
      flat
      :max-width="500"
      class="mt-12 mt-sm-0 pa-5 pa-lg-7"
    >
      <VCardText class="text-center mb-4">
        <h4 class="text-h4 mb-1">
          Reset Password 🔒
        </h4>
        <p class="mb-0">
          Enter your new password below
        </p>
      </VCardText>

      <VCardText>
        <VAlert
          v-if="error"
          type="error"
          class="mb-4"
        >
          {{ error }}
        </VAlert>

        <VAlert
          v-if="message"
          type="success"
          class="mb-4"
        >
          {{ message }}
        </VAlert>

        <VForm @submit.prevent="handleResetPassword">
          <VRow>
            <!-- Email (Readonly) -->
            <VCol cols="12">
              <VTextField
                v-model="form.email"
                label="Email"
                type="email"
                readonly
              />
            </VCol>

            <!-- Password -->
            <VCol cols="12">
              <VTextField
                v-model="form.password"
                label="New Password"
                placeholder="············"
                :type="isPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </VCol>

            <!-- Confirm Password -->
            <VCol cols="12">
              <VTextField
                v-model="form.password_confirmation"
                label="Confirm Password"
                placeholder="············"
                :type="isPasswordVisible ? 'text' : 'password'"
              />
            </VCol>

            <VCol cols="12">
              <VBtn
                block
                type="submit"
                :loading="loading"
              >
                Reset Password
              </VBtn>
            </VCol>

            <VCol
              cols="12"
              class="text-center text-base"
            >
              <RouterLink
                class="text-primary d-flex align-center justify-center"
                :to="{ name: 'login' }"
              >
                <VIcon
                  icon="ri-arrow-left-s-line"
                  class="me-2"
                />
                <span>Back to login</span>
              </RouterLink>
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
