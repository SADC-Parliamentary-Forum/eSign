<script setup>
/**
 * SignerPanel.vue - Signer Management Component
 * Allows adding/removing signers with color-coded badges
 */
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  selectedSigner: {
    type: Object,
    default: null,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'update:selectedSigner', 'signerSelected'])

// Color palette for signers (up to 8 signers)
const signerColors = [
  { bg: '#E3F2FD', border: '#1976D2', text: '#1976D2' }, // Blue
  { bg: '#F3E5F5', border: '#7B1FA2', text: '#7B1FA2' }, // Purple
  { bg: '#E8F5E9', border: '#388E3C', text: '#388E3C' }, // Green
  { bg: '#FFF3E0', border: '#F57C00', text: '#F57C00' }, // Orange
  { bg: '#FCE4EC', border: '#C2185B', text: '#C2185B' }, // Pink
  { bg: '#E0F7FA', border: '#0097A7', text: '#0097A7' }, // Cyan
  { bg: '#FFF8E1', border: '#FFA000', text: '#FFA000' }, // Amber
  { bg: '#EFEBE9', border: '#5D4037', text: '#5D4037' }, // Brown
]

const signers = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

// New signer form
const newSignerName = ref('')
const newSignerEmail = ref('')
const showAddForm = ref(false)

function getSignerColor(index) {
  return signerColors[index % signerColors.length]
}

function addSigner() {
  if (!newSignerName.value || !newSignerEmail.value) return
  
  const newSigner = {
    id: crypto.randomUUID(),
    name: newSignerName.value,
    email: newSignerEmail.value,
    colorIndex: signers.value.length,
    color: getSignerColor(signers.value.length),
  }
  
  signers.value = [...signers.value, newSigner]
  
  // Auto-select the new signer
  selectSigner(newSigner)
  
  // Reset form
  newSignerName.value = ''
  newSignerEmail.value = ''
  showAddForm.value = false
}

function removeSigner(index) {
  const updated = [...signers.value]
  const removed = updated.splice(index, 1)[0]

  signers.value = updated
  
  // If removed signer was selected, select first remaining
  if (props.selectedSigner?.id === removed.id) {
    if (updated.length > 0) {
      selectSigner(updated[0])
    } else {
      emit('update:selectedSigner', null)
    }
  }
}

function selectSigner(signer) {
  emit('update:selectedSigner', signer)
  emit('signerSelected', signer)
}

function isSelected(signer) {
  return props.selectedSigner?.id === signer.id
}
</script>

<template>
  <div class="signer-panel">
    <div class="text-overline mb-3 d-flex align-center justify-between">
      <span>Signers</span>
      <VChip
        size="x-small"
        color="primary"
        variant="tonal"
      >
        {{ signers.length }}
      </VChip>
    </div>

    <!-- Signers List -->
    <div class="signers-list mb-4">
      <div
        v-for="(signer, index) in signers"
        :key="signer.id"
        class="signer-item pa-3 mb-2 rounded-lg cursor-pointer"
        :class="{ 'signer-selected': isSelected(signer) }"
        :style="{
          backgroundColor: isSelected(signer) ? signer.color.bg : 'transparent',
          borderLeft: `4px solid ${signer.color.border}`
        }"
        @click="selectSigner(signer)"
      >
        <div class="d-flex align-center">
          <VAvatar 
            size="32" 
            :color="signer.color.border"
            class="mr-3"
          >
            <span class="text-white text-caption font-weight-bold">
              {{ signer.name.charAt(0).toUpperCase() }}
            </span>
          </VAvatar>
          
          <div class="flex-grow-1 overflow-hidden">
            <div class="text-body-2 font-weight-medium text-truncate">
              {{ signer.name }}
            </div>
            <div class="text-caption text-medium-emphasis text-truncate">
              {{ signer.email }}
            </div>
          </div>

          <VBtn
            v-if="!disabled"
            icon="mdi-close"
            size="x-small"
            variant="text"
            color="error"
            @click.stop="removeSigner(index)"
          />
        </div>
      </div>

      <div
        v-if="signers.length === 0"
        class="text-center py-6 text-medium-emphasis"
      >
        <VIcon
          icon="mdi-account-plus"
          size="48"
          class="mb-2"
        />
        <div class="text-body-2">
          No signers added yet
        </div>
      </div>
    </div>

    <!-- Add Signer Form -->
    <VExpandTransition>
      <div
        v-if="showAddForm && !disabled"
        class="add-signer-form pa-3 rounded-lg bg-surface-variant mb-3"
      >
        <VTextField
          v-model="newSignerName"
          label="Name"
          variant="outlined"
          density="compact"
          hide-details
          class="mb-2"
          @keyup.enter="$refs.emailInput?.focus()"
        />
        <VTextField
          ref="emailInput"
          v-model="newSignerEmail"
          label="Email"
          type="email"
          variant="outlined"
          density="compact"
          hide-details
          class="mb-3"
          @keyup.enter="addSigner"
        />
        <div class="d-flex gap-2">
          <VBtn
            size="small"
            variant="text"
            @click="showAddForm = false"
          >
            Cancel
          </VBtn>
          <VBtn
            size="small"
            color="primary"
            :disabled="!newSignerName || !newSignerEmail"
            @click="addSigner"
          >
            Add
          </VBtn>
        </div>
      </div>
    </VExpandTransition>

    <!-- Add Signer Button -->
    <VBtn
      v-if="!showAddForm && !disabled"
      block
      variant="tonal"
      color="primary"
      prepend-icon="mdi-account-plus"
      @click="showAddForm = true"
    >
      Add Signer
    </VBtn>

    <!-- Help Text -->
    <div
      v-if="signers.length > 0"
      class="text-caption text-medium-emphasis mt-4 text-center"
    >
      <VIcon
        icon="mdi-information"
        size="14"
        class="mr-1"
      />
      Select a signer, then draw on the PDF
    </div>
  </div>
</template>

<style scoped>
.signer-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.signers-list {
  flex: 1;
  overflow-y: auto;
}

.signer-item {
  transition: all 0.2s ease;
  border: 1px solid transparent;
}

.signer-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.signer-selected {
  border-color: currentColor;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
