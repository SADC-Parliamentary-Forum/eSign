<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { $api } from '@/utils/api'

const props = defineProps({
  modelValue: Boolean,
  documentIds: {
    type: Array,
    required: true
  }
})

const emit = defineEmits(['update:modelValue', 'signed', 'error'])

const loading = ref(false)
const checking = ref(false)
const signatures = ref([])
const confirmation = ref(false)
const results = ref(null)

const show = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const hasDefaultSignature = computed(() => {
    return signatures.value.some(s => s.type === 'signature' && s.is_default)
})

const hasDefaultInitials = computed(() => {
    return signatures.value.some(s => s.type === 'initials' && s.is_default)
})

const isReady = computed(() => {
    // We strictly require a default Signature. Initials are optional unless document specific fields need them, 
    // but generic check usually requires at least a signature.
    // However, backend validates strictly per field. 
    // We should at least warn if NO signature is present.
    return hasDefaultSignature.value
})

const missingRequirements = computed(() => {
    const missing = []
    if (!hasDefaultSignature.value) missing.push('Default Signature')
    // We don't check initials here as strictly, but good to mention
    return missing
})

watch(() => props.modelValue, (val) => {
    if (val) {
        checkSignatures()
        confirmation.value = false
        results.value = null
    }
})

async function checkSignatures() {
    checking.value = true
    try {
        const res = await $api('/signatures/mine')
        signatures.value = res || []
    } catch (e) {
        console.error('Failed to fetch signatures', e)
    } finally {
        checking.value = false
    }
}

async function performBulkSign() {
  if (!confirmation.value) return
  
  loading.value = true
  results.value = null
  
  try {
    const res = await $api('/documents/bulk-sign', {
        method: 'POST',
        body: {
            ids: props.documentIds,
            confirmation: true // "accepted" valid value
        }
    })
    
    results.value = res.results
    
    // If we have successful signs, emit event
    if (res.results && res.results.signed.length > 0) {
        emit('signed', res.results)
    }
  } catch (e) {
    emit('error', e.message)
    results.value = { errors: [{ reason: e.message }] }
  } finally {
    loading.value = false
  }
}

function close() {
    show.value = false
}
</script>

<template>
  <VDialog v-model="show" max-width="600">
    <VCard>
      <VCardTitle class="d-flex justify-space-between align-center pa-4">
        <span>Bulk Sign Documents</span>
        <VBtn icon="mdi-close" variant="text" @click="close" />
      </VCardTitle>
      
      <VDivider />
      
      <div v-if="checking" class="text-center pa-8">
         <VProgressCircular indeterminate color="primary" />
         <div class="mt-2 text-caption">Checking signature requirements...</div>
      </div>
      
      <div v-else-if="!results">
          <!-- Requirement Check -->
          <div v-if="!isReady" class="pa-4 bg-error-lighten-1 ma-4 rounded border-error text-error">
             <div class="d-flex align-center font-weight-bold mb-2">
                <VIcon icon="mdi-alert-circle" start />
                Missing Requirements
             </div>
             <p class="text-body-2 mb-3">
                You cannot perform bulk signing because you are missing the following defaults:
             </p>
             <ul class="ml-4 mb-3">
                <li v-for="req in missingRequirements" :key="req">{{ req }}</li>
             </ul>
             <VBtn 
                color="error" 
                variant="outlined" 
                size="small" 
                to="/settings/profile?tab=signatures"
                @click="close"
             >
                Manage Signatures
             </VBtn>
          </div>
          
          <div v-else class="pa-4">
             <p class="text-body-1 mb-4">
                You are about to sign <strong>{{ documentIds.length }}</strong> document(s).
             </p>
             
             <VAlert
                type="info"
                variant="tonal"
                title="How this works"
                class="mb-4"
                closable
             >
                <div class="text-body-2">
                   Your <strong>Default Signature</strong> and <strong>Initials</strong> will be automatically applied to all your assigned fields in these documents.
                   Any document requiring information you haven't provided (like text fields) will be skipped.
                </div>
             </VAlert>
             
             <div class="d-flex align-start bg-grey-lighten-4 pa-3 rounded mb-4">
                 <VCheckbox
                    v-model="confirmation"
                    hide-details
                    density="compact"
                    class="mt-0 pt-0"
                 >
                    <template #label>
                        <span class="text-body-2 font-weight-medium">
                            I approve this bulk signature and I have confirmed the contents of the documents.
                        </span>
                    </template>
                 </VCheckbox>
             </div>
             
             <div class="d-flex gap-2 justify-end">
                <VBtn variant="text" @click="close">Cancel</VBtn>
                <VBtn 
                    color="primary" 
                    :disabled="!confirmation" 
                    :loading="loading"
                    @click="performBulkSign"
                >
                    Sign Selected
                </VBtn>
             </div>
          </div>
      </div>
      
      <!-- Results View -->
      <div v-else class="pa-4">
         <div class="text-center mb-4">
            <VIcon icon="mdi-check-circle" color="success" size="48" class="mb-2" />
            <h3 class="text-h6">Processing Complete</h3>
         </div>
         
         <VList density="compact" class="bg-grey-lighten-5 rounded mb-4">
            <VListItem>
                <template #prepend><VIcon icon="mdi-check" color="success" /></template>
                <VListItemTitle>{{ results.signed?.length || 0 }} Signed Successfully</VListItemTitle>
            </VListItem>
            <VListItem v-if="results.skipped?.length">
                <template #prepend><VIcon icon="mdi-skip-next" color="warning" /></template>
                <VListItemTitle>{{ results.skipped.length }} Skipped</VListItemTitle>
                <VListItemSubtitle class="text-caption">
                    {{ results.skipped.map(s => s.reason).join(', ') }}
                </VListItemSubtitle>
            </VListItem>
             <VListItem v-if="results.errors?.length">
                <template #prepend><VIcon icon="mdi-alert" color="error" /></template>
                <VListItemTitle>{{ results.errors.length }} Errors</VListItemTitle>
            </VListItem>
         </VList>
         
         <div class="d-flex justify-end">
            <VBtn color="primary" @click="close">Close</VBtn>
         </div>
      </div>
      
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-error-lighten-1 {
    background-color: #FEE2E2;
}
.border-error {
    border: 1px solid #EF4444;
}
.text-error {
    color: #B91C1C !important;
}
</style>
