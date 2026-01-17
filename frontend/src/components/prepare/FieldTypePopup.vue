<script setup>
/**
 * FieldTypePopup.vue - Field Type Selector Popup
 * Appears after drawing a rectangle on the PDF
 */
import { onMounted, onUnmounted } from 'vue'
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  position: {
    type: Object,
    default: () => ({ x: 0, y: 0 })
  },
  signerColor: {
    type: Object,
    default: () => ({ border: '#1976D2', bg: '#E3F2FD' })
  }
})

const emit = defineEmits(['update:modelValue', 'select', 'cancel'])

const fieldTypes = [
  { 
    type: 'SIGNATURE', 
    icon: 'mdi-draw', 
    label: 'Signature',
    description: 'Full signature'
  },
  { 
    type: 'INITIALS', 
    icon: 'mdi-alphabetical-variant', 
    label: 'Initials',
    description: 'Initials only'
  },
  { 
    type: 'DATE', 
    icon: 'mdi-calendar', 
    label: 'Date',
    description: 'Auto-fill date'
  },
  { 
    type: 'TEXT', 
    icon: 'mdi-form-textbox', 
    label: 'Text',
    description: 'Free text input'
  },
  { 
    type: 'CHECKBOX', 
    icon: 'mdi-checkbox-marked-outline', 
    label: 'Checkbox',
    description: 'Yes/No selection'
  },
]

function selectType(type) {
  emit('select', type)
  emit('update:modelValue', false)
}

function cancel() {
  emit('cancel')
  emit('update:modelValue', false)
}

// Close on escape
function handleKeydown(e) {
  if (e.key === 'Escape') {
    cancel()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <v-menu
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :close-on-content-click="false"
    location="bottom start"
    :style="{ 
      position: 'fixed',
      left: position.x + 'px',
      top: position.y + 'px'
    }"
  >
    <v-card 
      class="field-type-popup" 
      min-width="220"
      elevation="8"
    >
      <v-card-title class="text-subtitle-2 pb-1 pt-3">
        <v-icon icon="mdi-form-select" size="18" class="mr-2" />
        Select Field Type
      </v-card-title>
      
      <v-divider class="my-1" />

      <v-list density="compact" class="py-1">
        <v-list-item
          v-for="field in fieldTypes"
          :key="field.type"
          :prepend-icon="field.icon"
          @click="selectType(field.type)"
          class="field-type-item"
        >
          <v-list-item-title class="text-body-2">
            {{ field.label }}
          </v-list-item-title>
          <v-list-item-subtitle class="text-caption">
            {{ field.description }}
          </v-list-item-subtitle>
        </v-list-item>
      </v-list>

      <v-divider class="my-1" />

      <v-card-actions class="pa-2">
        <v-btn
          size="small"
          variant="text"
          block
          @click="cancel"
        >
          Cancel
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-menu>
</template>

<style scoped>
.field-type-popup {
  border-radius: 12px;
}

.field-type-item {
  border-radius: 8px;
  margin: 2px 4px;
}

.field-type-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.1);
}
</style>
