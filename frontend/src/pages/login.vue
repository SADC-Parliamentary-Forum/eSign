<script setup>
// Auth Logic
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const form = ref({
  email: '',
  password: '',
  remember: false,
})

const isPasswordVisible = ref(false)

const authStore = useAuthStore()
const router = useRouter()
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  loading.value = true
  error.value = ''
  
  const success = await authStore.login(form.value.email, form.value.password)
  
  if (success) {
    router.push('/')
  } else {
    error.value = 'Invalid email or password'
  }
  loading.value = false
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <VCard
      flat
      :max-width="500"
      class="mt-12 mt-sm-0 pa-5 pa-lg-7"
    >
      <!-- Logo -->
      <VCardText class="text-center mb-4">
        <img 
          src="/sadc-logo.jpg" 
          alt="SADC Parliamentary Forum" 
          style="max-width: 120px; height: auto; margin-bottom: 16px;"
        >
        <h4 class="text-h4 mb-1">
          Welcome to SADC PF eSign! 👋🏻
        </h4>
        <p class="mb-0">
          Please sign-in to your account
        </p>
      </VCardText>

      <VCardText>
        <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>
        
        <VForm @submit.prevent="handleLogin">
          <VRow>
            <!-- email -->
            <VCol cols="12">
              <VTextField
                v-model="form.email"
                autofocus
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
                autocomplete="password"
                :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                @click:append-inner="isPasswordVisible = !isPasswordVisible"
              />

              <!-- remember me checkbox -->
              <div class="d-flex align-center justify-space-between flex-wrap my-6 gap-x-2">
                <VCheckbox
                  v-model="form.remember"
                  label="Remember me"
                />

                <a
                  class="text-primary"
                  href="javascript:void(0)"
                >
                  Forgot Password?
                </a>
              </div>

              <!-- login button -->
              <VBtn
                block
                type="submit"
                :loading="loading"
              >
                Login
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
