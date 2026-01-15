<script setup>
import { $api } from '@/utils/api'

const props = defineProps({
  modelValue: Boolean,
  signerId: {
    type: [String, Number],
    required: true,
  },
})

const emit = defineEmits(['update:modelValue', 'verified'])

const show = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const loading = ref(false)
const code = ref('')
const error = ref('')
const timeRemaining = ref(300) // 5 minutes
const attemptsRemaining = ref(3)
let countdownInterval = null

onMounted(() => {
  if (show.value) {
    sendOTP()
  }
})

watch(show, (newVal) => {
  if (newVal) {
    sendOTP()
  }
  else {
    stopCountdown()
  }
})

onUnmounted(() => {
  stopCountdown()
})

async function sendOTP() {
  loading.value = true
  error.value = ''
  
  try {
    await $api(`/verification/signers/${props.signerId}/otp`, {
      method: 'POST',
    })
    
    // Start countdown
    startCountdown()
  }
  catch (err) {
    error.value = 'Failed to send OTP. Please try again.'
  }
  finally {
    loading.value = false
  }
}

async function verifyOTP() {
  if (code.value.length !== 6) {
    error.value = 'Please enter a 6-digit code'
    return
  }

  loading.value = true
  error.value = ''
  
  try {
    await $api(`/verification/signers/${props.signerId}/otp/verify`, {
      method: 'POST',
      body: JSON.stringify({ code: code.value }),
      headers: { 'Content-Type': 'application/json' },
    })
    
    emit('verified')
    show.value = false
  }
  catch (err) {
    attemptsRemaining.value--
    if (attemptsRemaining.value > 0) {
      error.value = `Invalid code. ${attemptsRemaining.value} attempts remaining.`
    }
    else {
      error.value = 'Maximum attempts reached. Please request a new code.'
      code.value = ''
      setTimeout(() => {
        show.value = false
      }, 3000)
    }
  }
  finally {
    loading.value = false
  }
}

function startCountdown() {
  timeRemaining.value = 300
  countdownInterval = setInterval(() => {
    timeRemaining.value--
    if (timeRemaining.value <= 0) {
      stopCountdown()
      error.value = 'Code expired. Please request a new one.'
    }
  }, 1000)
}

function stopCountdown() {
  if (countdownInterval) {
    clearInterval(countdownInterval)
    countdownInterval = null
  }
}

const formattedTime = computed(() => {
  const minutes = Math.floor(timeRemaining.value / 60)
  const seconds = timeRemaining.value % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

async function resend() {
  code.value = ''
  error.value = ''
  attemptsRemaining.value = 3
  await sendOTP()
}
</script>

<template>
  <v-dialog
    v-model="show"
    max-width="500"
    persistent
  >
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon icon="mdi-lock-check" class="mr-2" />
        Enter Verification Code
      </v-card-title>

      <v-card-text>
        <v-alert type="info" variant="tonal" class="mb-4">
          A 6-digit verification code has been sent to your email. Please enter it below.
        </v-alert>

        <v-otp-input
          v-model="code"
          :length="6"
          :loading="loading"
          variant="outlined"
          @finish="verifyOTP"
        />

        <div class="d-flex justify-space-between align-center mt-3 text-caption">
          <div>
            <v-icon icon="mdi-clock-outline" size="small" class="mr-1" />
            Expires in {{ formattedTime }}
          </div>
          <div>
            <v-icon icon="mdi-alert-circle-outline" size="small" class="mr-1" />
            {{ attemptsRemaining }} attempts left
          </div>
        </div>

        <v-alert v-if="error" type="error" variant="tonal" class="mt-3" density="compact">
          {{ error }}
        </v-alert>

        <v-btn
          variant="text"
          size="small"
          class="mt-3"
          :disabled="loading || timeRemaining > 240"
          @click="resend"
        >
          Didn't receive code? Resend
        </v-btn>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn
          variant="text"
          :disabled="loading"
          @click="show = false"
        >
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          :loading="loading"
          :disabled="code.length !== 6"
          @click="verifyOTP"
        >
          Verify
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
