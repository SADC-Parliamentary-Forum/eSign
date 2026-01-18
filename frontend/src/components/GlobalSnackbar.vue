<script setup>
import { useToast } from '@/composables/useToast'

const { state } = useToast()
</script>

<template>
  <VSnackbar
    v-model="state.show"
    :color="state.color"
    :timeout="state.timeout"
    location="top end"
  >
    {{ state.message }}
  </VSnackbar>
</template>
