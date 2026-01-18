<script setup>
// Auth Logic
import { useAuthStore } from '@/stores/auth'
import { useRouter, useRoute } from 'vue-router'
import { ref } from 'vue'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const isPasswordVisible = ref(false)
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const error = ref('')
const loading = ref(false)

async function handleRegister() {
  if (form.value.password !== form.value.password_confirmation) {
      error.value = 'Passwords do not match'
      return
  }

  loading.value = true
  error.value = ''
  
  try {
    const success = await authStore.register({
        name: form.value.name,
        email: form.value.email,
        password: form.value.password,
        password_confirmation: form.value.password_confirmation
    })
    
    if (success) {
        const returnUrl = route.query.returnUrl || '/'
        router.push(returnUrl)
    }
  } catch (e) {
    error.value = e.message || 'Registration failed'
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
        <img 
          src="/sadc-logo.jpg" 
          alt="SADC Parliamentary Forum" 
          style="max-width: 70px; height: auto; margin-bottom: 12px;"
        >
        <h4 class="text-h4 mb-1">
          Create an Account 🚀
        </h4>
        <p class="mb-0">
          Sign up to get started with SADC PF eSign
        </p>
      </VCardText>

      <VCardText>
        <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>
        
        <VForm @submit.prevent="handleRegister">
          <VRow>
            <!-- name -->
            <VCol cols="12">
              <VTextField
                v-model="form.name"
                autofocus
                label="Full Name"
                placeholder="John Doe"
              />
            </VCol>

            <!-- email -->
            <VCol cols="12">
              <VTextField
                v-model="form.email"
                label="Email"
                type="email"
                placeholder="johndoe@email.com"
              />
            </VCol>

            <!-- password -->
            <VCol cols="12">
              <VTextField
                v-model="form.password"
                label="Password"
                placeholder="············"
                :type="isPasswordVisible ? 'text' : 'password'"
                :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />
            </VCol>

            <!-- confirm password -->
            <VCol cols="12">
              <VTextField
                v-model="form.password_confirmation"
                label="Confirm Password"
                placeholder="············"
                :type="isPasswordVisible ? 'text' : 'password'"
              />
            </VCol>

            <!-- register button -->
            <VCol cols="12">
              <VBtn
                block
                type="submit"
                :loading="loading"
              >
                Register
              </VBtn>
            </VCol>

            <!-- login link -->
            <VCol cols="12" class="text-center text-base">
              <span>Already have an account?</span>
              <RouterLink
                class="text-primary ms-2"
                :to="{ path: '/login', query: route.query }"
              >
                Sign in instead
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
