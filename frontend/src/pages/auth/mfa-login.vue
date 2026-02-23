<script setup>
import { useAuthStore } from '@/stores/auth'
import { $api } from '@/utils/api'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const authStore = useAuthStore()
const router = useRouter()

const otp = ref('')
const loading = ref(false)
const error = ref('')
const message = ref('')
const resendDisabled = ref(false)
const resendTimer = ref(0) // Seconds

onMounted(async () => {
  // If no partial token (tempMfaToken), redirect to login
  if (!authStore.tempMfaToken) {
    router.replace('/login')
    return
  }
  
  // Auto-send code on arrival
  await sendCode()
})

async function sendCode() {
  if (resendDisabled.value) return
  
  loading.value = true
  message.value = ''
  error.value = ''
  
  try {
    await $api('/auth/mfa/send', { 
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${authStore.tempMfaToken}`
      }
    })
    message.value = 'Verification code sent to your email.'
    startResendTimer()
  } catch (e) {
    error.value = 'Failed to send code: ' + (e.message || e)
  } finally {
    loading.value = false
  }
}

function startResendTimer() {
  resendDisabled.value = true
  resendTimer.value = 60
  
  const interval = setInterval(() => {
    resendTimer.value--
    if (resendTimer.value <= 0) {
      clearInterval(interval)
      resendDisabled.value = false
    }
  }, 1000)
}

async function verifyCode() {
  if (!otp.value) return
  
  loading.value = true
  error.value = ''
  
  try {
    const data = await $api('/auth/mfa/verify', {
      method: 'POST',
      body: { code: otp.value },
      headers: {
        'Authorization': `Bearer ${authStore.tempMfaToken}`
      }
    })
    
    // Auth Store should manually update state since setAuth logic in store might differ
    // But store.setAuth IS exposed
    // Wait, auth store doesn't expose setAuth directly in the return, but it does expose 'setAuth' function?
    // Checking auth.js... Yes, it returns setAuth.
    
    // Actually, let's use the response data directly.
    authStore.setAuth(data.access_token, data.user)
    
    router.replace('/')
  } catch (e) {
    error.value = 'Verification failed: ' + (e.message || 'Invalid code')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard class="pa-4 pt-7" max-width="450">
      <VCardText class="text-center">
        <h4 class="text-h4 font-weight-bold mb-2">Two-Step Verification 💬</h4>
        <p class="mb-4 text-body-2">
          We sent a verification code to your email. Enter the code from the email in the field below.
        </p>
        <p class="font-weight-bold mb-6">******</p>

        <VOtpInput
          v-model="otp"
          :length="6"
          class="mb-6 justify-center"
          :disabled="loading"
          @finish="verifyCode"
        />

        <VAlert v-if="error" type="error" variant="tonal" class="mb-4">
          {{ error }}
        </VAlert>
        
        <VAlert v-if="message" type="success" variant="tonal" class="mb-4">
          {{ message }}
        </VAlert>

        <VBtn
          block
          :loading="loading"
          :disabled="!otp || otp.length < 6"
          @click="verifyCode"
        >
          Verify My Account
        </VBtn>

        <div class="d-flex justify-center align-center mt-4">
          <span class="mr-1">Didn't get the code?</span>
          <a
            v-if="!resendDisabled"
            href="#"
            class="text-primary font-weight-bold"
            @click.prevent="sendCode"
          >
            Resend
          </a>
          <span v-else class="text-medium-emphasis">
            Resend in {{ resendTimer }}s
          </span>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
.auth-wrapper {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
