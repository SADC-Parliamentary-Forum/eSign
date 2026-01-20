<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { $api } from '@/utils/api'
import { useToast } from '@/composables/useToast'

const delegations = ref([])
const loading = ref(false)
const showDialog = ref(false)
const valid = ref(false)
const form = ref(null)

const newDelegation = ref({
  delegate_email: '',
  starts_at: '',
  ends_at: '',
  reason: '',
})

const router = useRouter()
const toast = useToast()

const headers = [
  { title: 'Status', key: 'status', align: 'start' },
  { title: 'Delegate', key: 'delegate' },
  { title: 'Duration', key: 'duration' },
  { title: 'Reason', key: 'reason' },
  { title: 'Actions', key: 'actions', align: 'end' },
]

// Min date for start is today
const minStart = computed(() => {
    const d = new Date()
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
    return d.toISOString().slice(0, 16)
})

function getStatus(item) {
    const now = new Date()
    const start = new Date(item.starts_at)
    const end = item.ends_at ? new Date(item.ends_at) : null
    
    if (end && now > end) return { color: 'grey', text: 'Expired' }
    if (now < start) return { color: 'info', text: 'Scheduled' }
    return { color: 'success', text: 'Active' }
}

function formatDate(date) {
    if (!date) return ''
    return new Date(date).toLocaleString('en-US', { 
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' 
    })
}

async function fetchDelegations() {
  loading.value = true
  try {
    const data = await $api('/delegations')
    delegations.value = data.my_delegations || data.data || []
  } catch (e) {
    console.error(e)
    toast.error('Failed to load delegations')
  } finally {
    loading.value = false
  }
}

async function createDelegation() {
  if (!form.value?.validate()) return

  try {
    await $api('/delegations', {
      method: 'POST',
      body: newDelegation.value
    })
    toast.success('Delegation created')
    showDialog.value = false
    // Reset form
    newDelegation.value = { delegate_email: '', starts_at: '', ends_at: '', reason: '' }
    await fetchDelegations()
  } catch (e) {
    console.error(e)
    toast.error(e.response?._data?.message || 'Could not create delegation')
  }
}

async function deleteDelegation(id) {
  if (!confirm('Are you sure you want to revoke this delegation?')) return
  try {
    await $api(`/delegations/${id}`, { method: 'DELETE' })
    toast.success('Delegation revoked')
    await fetchDelegations()
  } catch (e) {
    console.error(e)
    toast.error('Could not delete delegation')
  }
}

onMounted(() => {
  fetchDelegations()
})
</script>

<template>
  <VContainer fluid class="pa-6">
    <VRow justify="center">
      <VCol cols="12" md="10" lg="8">
        <!-- Header Section -->
        <div class="d-flex align-center justify-space-between mb-6">
            <div>
                <h1 class="text-h4 font-weight-bold">Delegations</h1>
                <p class="text-body-1 text-medium-emphasis mt-1">
                    Manage your Out of Office settings. Assign someone to sign on your behalf.
                </p>
            </div>
            <VBtn
                color="primary"
                prepend-icon="mdi-plus"
                size="large"
                @click="showDialog = true"
            >
                Add Delegation
            </VBtn>
        </div>

        <!-- Info Alert -->
        <VAlert
            v-if="!delegations.length"
            type="info"
            variant="tonal"
            border="start"
            class="mb-6"
            title="What is Delegation?"
        >
            Delegation allows you to temporarily authorize another user to access and sign documents assigned to you. 
            This is useful when you are on leave or unavailable. You can set a start and end time, or leave it indefinite.
        </VAlert>

        <!-- Delegations Card -->
        <VCard border flat>
            <VDataTable
                :headers="headers"
                :items="delegations"
                :loading="loading"
                hover
            >
                <template #item.status="{ item }">
                    <VChip
                        :color="getStatus(item).color"
                        size="small"
                        label
                        class="text-capitalize"
                    >
                        {{ getStatus(item).text }}
                    </VChip>
                </template>

                <template #item.delegate="{ item }">
                    <div class="d-flex align-center gap-2">
                        <VAvatar size="32" color="primary" variant="tonal">
                            {{ item.delegate?.name?.charAt(0) || item.delegate?.email?.charAt(0) || 'D' }}
                        </VAvatar>
                        <div class="d-flex flex-column">
                            <span class="font-weight-medium">{{ item.delegate?.name || 'Unknown' }}</span>
                            <span class="text-caption text-medium-emphasis">{{ item.delegate?.email }}</span>
                        </div>
                    </div>
                </template>

                <template #item.duration="{ item }">
                    <div class="d-flex flex-column">
                        <span class="text-body-2">From: {{ formatDate(item.starts_at) }}</span>
                        <span class="text-body-2 text-medium-emphasis">
                            To: {{ item.ends_at ? formatDate(item.ends_at) : 'Until revoked' }}
                        </span>
                    </div>
                </template>

                <template #item.actions="{ item }">
                    <VBtn
                        variant="text"
                        color="error"
                        density="compact"
                        prepend-icon="mdi-close-circle-outline"
                        @click="deleteDelegation(item.id)"
                    >
                        Revoke
                    </VBtn>
                </template>
                
                <template #no-data>
                    <div class="d-flex flex-column align-center justify-center py-8 text-medium-emphasis">
                        <VIcon icon="mdi-account-arrow-right-outline" size="64" class="mb-4" />
                        <div class="text-h6">No active delegations</div>
                        <div class="text-body-2">You are currently handling all your own documents.</div>
                    </div>
                </template>
            </VDataTable>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create Dialog -->
    <VDialog v-model="showDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4 d-flex justify-space-between align-center">
            Create Delegation
            <VBtn icon="mdi-close" variant="text" density="compact" @click="showDialog = false" />
        </VCardTitle>
        <VDivider />
        
        <VCardText class="pa-4">
          <VForm ref="form" v-model="valid" @submit.prevent="createDelegation">
              <VTextField
                v-model="newDelegation.delegate_email"
                label="Delegate Email"
                placeholder="colleague@example.com"
                prepend-inner-icon="mdi-email-outline"
                :rules="[v => !!v || 'Email is required', v => /.+@.+\..+/.test(v) || 'Invalid email']"
                variant="outlined"
                class="mb-4"
              />
              
              <div class="d-flex gap-4">
                  <VTextField
                    v-model="newDelegation.starts_at"
                    label="Start Date"
                    type="datetime-local"
                    :min="minStart"
                    variant="outlined"
                    class="mb-4 w-50"
                  />
                  
                  <VTextField
                    v-model="newDelegation.ends_at"
                    label="End Date (Optional)"
                    type="datetime-local"
                    :min="newDelegation.starts_at || minStart"
                    variant="outlined"
                    class="mb-4 w-50"
                    hint="Leave empty for indefinite"
                    persistent-hint
                  />
              </div>

              <VTextarea
                v-model="newDelegation.reason"
                label="Reason"
                placeholder="e.g. Annual Leave, Medical Leave"
                rows="2"
                variant="outlined"
              />
          </VForm>
        </VCardText>
        
        <VDivider />
        
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="showDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="loading"
            @click="createDelegation"
          >
            Create Delegation
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
