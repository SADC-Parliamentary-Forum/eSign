<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast' // assume toast composable exists

const delegations = ref([])
const loading = ref(false)
const showDialog = ref(false)

const newDelegation = ref({
  delegate_email: '',
  starts_at: '',
  ends_at: '',
  reason: '',
})

const router = useRouter()
const toast = useToast()

async function fetchDelegations() {
  loading.value = true
  try {
    const res = await fetch('/api/delegations', { credentials: 'include' })
    const data = await res.json()

    delegations.value = data.my_delegations || []
  } catch (e) {
    console.error(e)
    toast.error('Failed to load delegations')
  } finally {
    loading.value = false
  }
}

async function createDelegation() {
  try {
    const res = await fetch('/api/delegations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(newDelegation.value),
    })

    if (!res.ok) throw new Error('Failed')
    toast.success('Delegation created')
    showDialog.value = false
    await fetchDelegations()
  } catch (e) {
    console.error(e)
    toast.error('Could not create delegation')
  }
}

async function deleteDelegation(id) {
  try {
    const res = await fetch(`/api/delegations/${id}`, {
      method: 'DELETE',
      credentials: 'include',
    })

    if (!res.ok) throw new Error('Failed')
    toast.success('Delegation removed')
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
  <VContainer
    fluid
    class="pa-6"
  >
    <VToolbar
      flat
      color="surface"
    >
      <VToolbarTitle>Delegations (Out of Office)</VToolbarTitle>
      <VSpacer />
      <VBtn
        color="primary"
        prepend-icon="mdi-plus"
        @click="showDialog = true"
      >
        Add Delegation
      </VBtn>
    </VToolbar>

    <VDataTable
      :items="delegations"
      :loading="loading"
      class="mt-4"
      hide-default-footer
    >
      <template #item.delegate="{ item }">
        <span>{{ item.delegate?.email ?? 'N/A' }}</span>
      </template>
      <template #item.starts_at="{ item }">
        <span>{{ new Date(item.starts_at).toLocaleString() }}</span>
      </template>
      <template #item.ends_at="{ item }">
        <span>{{ item.ends_at ? new Date(item.ends_at).toLocaleString() : 'Indefinite' }}</span>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          icon
          small
          color="error"
          @click="deleteDelegation(item.id)"
        >
          <VIcon>mdi-delete</VIcon>
        </VBtn>
      </template>
    </VDataTable>

    <VDialog
      v-model="showDialog"
      max-width="500"
    >
      <VCard title="Create Delegation">
        <VCardText>
          <VTextField
            v-model="newDelegation.delegate_email"
            label="Delegate Email"
            required
          />
          <VTextField
            v-model="newDelegation.starts_at"
            label="Start (ISO datetime)"
            type="datetime-local"
            required
          />
          <VTextField
            v-model="newDelegation.ends_at"
            label="End (ISO datetime)"
            type="datetime-local"
          />
          <VTextarea
            v-model="newDelegation.reason"
            label="Reason (optional)"
            rows="2"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            text
            @click="showDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="createDelegation"
          >
            Create
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
