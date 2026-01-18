<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref('')
const verified = ref(false)

onMounted(async () => {
  const { id, hash, expires, signature } = route.query

  if (!id || !hash || !expires || !signature) {
    error.value = 'Invalid verification link.'
    loading.value = false
    return
  }

  try {
    // Construct the backend API URL using the params from the frontend URL
    // The backend expects: /api/verification/email/verify/{id}/{hash}?expires=...&signature=...
    const apiUrl = `/api/verification/email/verify/${id}/${hash}?expires=${expires}&signature=${signature}`

    const res = await fetch(apiUrl, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    })

    const data = await res.json()

    if (res.ok) {
      verified.value = true
      // Optional: Refresh user profile if logged in
      setTimeout(() => {
        router.push('/login')
      }, 3000)
    } else {
      error.value = data.message || 'Verification failed.'
    }
  } catch (e) {
    console.error(e)
    error.value = 'An error occurred during verification.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard class="auth-card pa-4 pt-7" max-width="448">
      <VCardItem class="justify-center">
        <template #prepend>
          <div class="d-flex">
            <!-- App Logo -->
             <VIcon icon="ri-mail-check-line" size="40" color="primary" />
          </div>
        </template>
        <VCardTitle class="font-weight-bold text-h5 text-primary">
          Email Verification
        </VCardTitle>
      </VCardItem>

      <VCardText class="pt-2 text-center">
        <div v-if="loading">
          <VProgressCircular indeterminate color="primary" class="mb-4" />
          <p class="text-body-1">Verifying your email...</p>
        </div>

        <div v-else-if="verified">
          <VIcon icon="ri-checkbox-circle-fill" size="64" color="success" class="mb-4" />
          <h3 class="text-h6 font-weight-bold text-success mb-2">Verified!</h3>
          <p class="text-body-2 mb-4">
            Your email has been successfully verified. 
            Redirecting you to the login page...
          </p>
          <VBtn block to="/login">
            Go to Login
          </VBtn>
        </div>

        <div v-else>
          <VIcon icon="ri-error-warning-fill" size="64" color="error" class="mb-4" />
          <h3 class="text-h6 font-weight-bold text-error mb-2">Verification Failed</h3>
          <p class="text-body-2 mb-4">{{ error }}</p>
          <VBtn block variant="outlined" to="/auth/login">
            Back to Login
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";
</style>

<route lang="yaml">
meta:
  layout: blank
  public: true
</route>
