<script setup>
import { useAuthStore } from '@/stores/auth'
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const email = ref('')
const authStore = useAuthStore()
const error = ref('')
const message = ref('')
const loading = ref(false)

async function handleForgotPassword() {
  loading.value = true
  error.value = ''
  message.value = ''

  try {
    const success = await authStore.forgotPassword(email.value)
    if (success) {
      message.value = 'We have emailed your password reset link!'
    }
  } catch (e) {
    error.value = e.message || 'Failed to send reset link'
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
          Forgot Password? 🔒
        </h4>
        <p class="mb-0">
          Enter your email and we'll send you instructions to reset your password
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

        <VForm @submit.prevent="handleForgotPassword">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="email"
                autofocus
                label="Email"
                type="email"
                placeholder="johndoe@email.com"
              />
            </VCol>

            <VCol cols="12">
              <VBtn
                block
                type="submit"
                :loading="loading"
              >
                Send Reset Link
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
