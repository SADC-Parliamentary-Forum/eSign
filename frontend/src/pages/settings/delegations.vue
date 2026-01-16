<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '@/composables/useToast'; // assume toast composable exists

const delegations = ref([]);
const loading = ref(false);
const showDialog = ref(false);
const newDelegation = ref({
  delegate_email: '',
  starts_at: '',
  ends_at: '',
  reason: ''
});

const router = useRouter();
const toast = useToast();

async function fetchDelegations() {
  loading.value = true;
  try {
    const res = await fetch('/api/delegations', { credentials: 'include' });
    const data = await res.json();
    delegations.value = data.my_delegations || [];
  } catch (e) {
    console.error(e);
    toast.error('Failed to load delegations');
  } finally {
    loading.value = false;
  }
}

async function createDelegation() {
  try {
    const res = await fetch('/api/delegations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(newDelegation.value)
    });
    if (!res.ok) throw new Error('Failed');
    toast.success('Delegation created');
    showDialog.value = false;
    await fetchDelegations();
  } catch (e) {
    console.error(e);
    toast.error('Could not create delegation');
  }
}

async function deleteDelegation(id) {
  try {
    const res = await fetch(`/api/delegations/${id}`, {
      method: 'DELETE',
      credentials: 'include'
    });
    if (!res.ok) throw new Error('Failed');
    toast.success('Delegation removed');
    await fetchDelegations();
  } catch (e) {
    console.error(e);
    toast.error('Could not delete delegation');
  }
}

onMounted(() => {
  fetchDelegations();
});
</script>

<template>
  <v-container fluid class="pa-6">
    <v-toolbar flat color="surface">
      <v-toolbar-title>Delegations (Out of Office)</v-toolbar-title>
      <v-spacer></v-spacer>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="showDialog = true">Add Delegation</v-btn>
    </v-toolbar>

    <v-data-table :items="delegations" :loading="loading" class="mt-4" hide-default-footer>
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
        <v-btn icon small color="error" @click="deleteDelegation(item.id)">
          <v-icon>mdi-delete</v-icon>
        </v-btn>
      </template>
    </v-data-table>

    <v-dialog v-model="showDialog" max-width="500">
      <v-card title="Create Delegation">
        <v-card-text>
          <v-text-field v-model="newDelegation.delegate_email" label="Delegate Email" required></v-text-field>
          <v-text-field v-model="newDelegation.starts_at" label="Start (ISO datetime)" type="datetime-local" required></v-text-field>
          <v-text-field v-model="newDelegation.ends_at" label="End (ISO datetime)" type="datetime-local"></v-text-field>
          <v-textarea v-model="newDelegation.reason" label="Reason (optional)" rows="2"></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="showDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="createDelegation">Create</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
