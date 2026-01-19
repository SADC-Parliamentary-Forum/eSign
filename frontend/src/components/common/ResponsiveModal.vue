<!-- Mobile-optimized bottom sheet for actions -->
<script setup>
import { useResponsive } from '@/composables/useResponsive'

const props = defineProps({
  modelValue: Boolean,
  title: String,
  persistent: Boolean,
})

const emit = defineEmits(['update:modelValue'])

const { isMobile } = useResponsive()

const internalValue = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})
</script>

<template>
  <!-- Bottom sheet for mobile, dialog for desktop -->
  <VBottomSheet
    v-if="isMobile"
    v-model="internalValue"
    :persistent="persistent"
  >
    <VCard rounded="t-xl">
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>{{ title }}</span>
        <VBtn
          icon="mdi-close"
          variant="text"
          size="small"
          @click="internalValue = false"
        />
      </VCardTitle>
      <VCardText>
        <slot />
      </VCardText>
      <VCardActions v-if="$slots.actions">
        <slot name="actions" />
      </VCardActions>
    </VCard>
  </VBottomSheet>

  <!-- Dialog for desktop -->
  <VDialog
    v-else
    v-model="internalValue"
    :persistent="persistent"
    max-width="600"
  >
    <VCard>
      <VCardTitle>{{ title }}</VCardTitle>
      <VCardText>
        <slot />
      </VCardText>
      <VCardActions v-if="$slots.actions">
        <VSpacer />
        <slot name="actions" />
      </VCardActions>
    </VCard>
  </VDialog>
</template>
