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
  <v-bottom-sheet
    v-if="isMobile"
    v-model="internalValue"
    :persistent="persistent"
  >
    <v-card rounded="t-xl">
      <v-card-title class="d-flex align-center justify-space-between">
        <span>{{ title }}</span>
        <v-btn
          icon="mdi-close"
          variant="text"
          size="small"
          @click="internalValue = false"
        />
      </v-card-title>
      <v-card-text>
        <slot />
      </v-card-text>
      <v-card-actions v-if="$slots.actions">
        <slot name="actions" />
      </v-card-actions>
    </v-card>
  </v-bottom-sheet>

  <!-- Dialog for desktop -->
  <v-dialog
    v-else
    v-model="internalValue"
    :persistent="persistent"
    max-width="600"
  >
    <v-card>
      <v-card-title>{{ title }}</v-card-title>
      <v-card-text>
        <slot />
      </v-card-text>
      <v-card-actions v-if="$slots.actions">
        <v-spacer />
        <slot name="actions" />
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
