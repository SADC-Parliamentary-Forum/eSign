<script setup>
import { onMounted, ref } from 'vue'

const activeTab = ref('users')
const users = ref([])
const logs = ref([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  await Promise.all([fetchUsers(), fetchAudit()])
  loading.value = false
})

async function fetchUsers() {
  try {
     const res = await $api('/users')
     users.value = res.data || res 
  } catch (e) {
     console.error("Failed to fetch users", e)
  }
}

async function fetchAudit() {
  try {
     const res = await $api('/audit-logs')
     logs.value = res.data || []
  } catch (e) {
     console.error("Failed to fetch audit logs", e)
  }
}

function formatTime(time) {
  if (!time) return '-'
  return new Date(time).toLocaleString()
}

const userHeaders = [
  { title: 'Name', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'Role', key: 'role.display_name' },
  { title: 'MFA Enabled', key: 'mfa_enabled' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const auditHeaders = [
  { title: 'Event', key: 'event' },
  { title: 'User', key: 'user.name' },
  { title: 'IP Address', key: 'ip_address' },
  { title: 'Time', key: 'created_at' },
]
</script>

<template>
  <VRow>
    <VCol cols="12">
       <div class="d-flex justify-space-between align-center mb-6">
          <h2 class="text-h4 font-weight-bold">Admin Console</h2>
          <VBtn variant="tonal" to="/">Back to Dashboard</VBtn>
       </div>

       <VCard>
         <VTabs v-model="activeTab" grow>
           <VTab value="users">
             <VIcon start icon="ri-user-line" />
             Users
           </VTab>
           <VTab value="audit">
             <VIcon start icon="ri-history-line" />
             Audit Logs
           </VTab>
         </VTabs>

         <VCardText class="pa-0">
           <VWindow v-model="activeTab">
             
             <!-- Users Tab -->
             <VWindowItem value="users">
               <VDataTable :headers="userHeaders" :items="users" :loading="loading" class="text-no-wrap">
                 <template #item.name="{ item }">
                    <div class="d-flex align-center">
                       <VAvatar size="32" color="primary" variant="tonal" class="me-2">
                          {{ item.name.charAt(0).toUpperCase() }}
                       </VAvatar>
                       <span class="font-weight-medium">{{ item.name }}</span>
                    </div>
                 </template>
                 <template #item.mfa_enabled="{ item }">
                   <VChip :color="item.mfa_enabled ? 'success' : 'secondary'" size="small" variant="tonal">
                     {{ item.mfa_enabled ? 'Enabled' : 'Disabled' }}
                   </VChip>
                 </template>
                 <template #item.actions>
                   <VBtn size="small" variant="text" color="primary" icon="ri-edit-line" />
                 </template>
               </VDataTable>
             </VWindowItem>

             <!-- Audit Tab -->
             <VWindowItem value="audit">
               <VDataTable :headers="auditHeaders" :items="logs" :loading="loading" class="text-no-wrap">
                 <template #item.user.name="{ item }">
                    <span v-if="item.user" class="font-weight-medium">{{ item.user.name }}</span>
                    <span v-else class="text-disabled font-italic">System</span>
                 </template>
                 <template #item.created_at="{ item }">
                    {{ formatTime(item.created_at) }}
                 </template>
               </VDataTable>
             </VWindowItem>
             
           </VWindow>
         </VCardText>
       </VCard>
    </VCol>
  </VRow>
</template>
