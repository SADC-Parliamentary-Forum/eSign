<script setup>
import { onMounted, ref, watch, nextTick } from 'vue'
import EditUserDialog from '@/components/dialogs/EditUserDialog.vue'

const activeTab = ref('users')
const users = ref([])
const logs = ref([])
const systemLogs = ref('')
const loading = ref(false)
const logLoading = ref(false)

// Edit User State
const isEditDialogVisible = ref(false)
const selectedUser = ref(null)

onMounted(async () => {
  loading.value = true
  await Promise.all([fetchUsers(), fetchAudit()])
  loading.value = false
})

async function fetchUsers() {
  try {
     const res = await $api('/admin/users')
     users.value = res.data || res 
  } catch (e) {
     console.error("Failed to fetch users", e)
  }
}

async function fetchAudit() {
  try {
     const res = await $api('/admin/audit-logs')
     logs.value = res.data || []
  } catch (e) {
     console.error("Failed to fetch audit logs", e)
  }
}

async function fetchSystemLogs() {
  logLoading.value = true
  try {
    const res = await $api('/admin/logs/system')
    systemLogs.value = res.content || ''
  } catch (e) {
     console.error("Failed to fetch system logs", e)
     systemLogs.value = "Error fetching logs."
  }
  logLoading.value = false
}

// Watch for tab change to fetch system logs lazily
watch(activeTab, (val) => {
  if (val === 'system-logs' && !systemLogs.value) {
    fetchSystemLogs()
  }
})

function formatTime(time) {
  if (!time) return '-'
  return new Date(time).toLocaleString()
}

// User Actions
const openEditDialog = (user) => {
  console.log('Opening edit dialog for:', user)
  selectedUser.value = user
  isEditDialogVisible.value = true
}

const onUserUpdate = async (userData) => {
  try {
    await $api(`/admin/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: userData
    })
    isEditDialogVisible.value = false
    await fetchUsers()
  } catch (e) {
    console.error("Failed to update user", e)
  }
}

const deleteUser = async (user) => {
  if (!confirm(`Are you sure you want to delete ${user.name}?`)) return
  
  try {
    await $api(`/admin/users/${user.id}`, {
      method: 'DELETE'
    })
    await fetchUsers()
  } catch (e) {
    console.error("Failed to delete user", e)
  }
}

const userHeaders = [
  { title: 'Name', key: 'name' },
  { title: 'Email', key: 'email' },
  { title: 'Role', key: 'role.display_name' },
  { title: 'Status', key: 'status' },
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
           <VTab value="system-logs">
             <VIcon start icon="ri-file-list-3-line" />
             System Logs
           </VTab>
         </VTabs>

         <VCardText class="pa-0">
           <VWindow v-model="activeTab">
             
             <!-- Users Tab -->
             <VWindowItem value="users">
               <VCardText>
                 <VDataTable :headers="userHeaders" :items="users" :loading="loading" class="text-no-wrap">
                   <template #item.name="{ item }">
                      <div class="d-flex align-center">
                         <VAvatar size="32" color="primary" variant="tonal" class="me-2">
                            {{ item.name.charAt(0).toUpperCase() }}
                         </VAvatar>
                         <span class="font-weight-medium">{{ item.name }}</span>
                      </div>
                   </template>
                   <template #item.status="{ item }">
                     <VChip :color="item.status === 'ACTIVE' ? 'success' : 'warning'" size="small" variant="tonal">
                       {{ item.status }}
                     </VChip>
                   </template>
                   <template #item.mfa_enabled="{ item }">
                     <VChip :color="item.mfa_enabled ? 'success' : 'secondary'" size="small" variant="tonal">
                       {{ item.mfa_enabled ? 'Enabled' : 'Disabled' }}
                     </VChip>
                   </template>
                   <template #item.actions="{ item }">
                     <VBtn size="small" variant="text" color="primary" icon="ri-edit-line" @click="openEditDialog(item)" />
                     <VBtn size="small" variant="text" color="error" icon="ri-delete-bin-line" @click="deleteUser(item)" />
                   </template>
                 </VDataTable>
               </VCardText>
             </VWindowItem>

             <!-- Audit Tab -->
             <VWindowItem value="audit">
               <VCardText>
                 <VDataTable :headers="auditHeaders" :items="logs" :loading="loading" class="text-no-wrap">
                   <template #item.user.name="{ item }">
                      <span v-if="item.user" class="font-weight-medium">{{ item.user.name }}</span>
                      <span v-else class="text-disabled font-italic">System</span>
                   </template>
                   <template #item.created_at="{ item }">
                      {{ formatTime(item.created_at) }}
                   </template>
                 </VDataTable>
               </VCardText>
             </VWindowItem>
             
             <!-- System Logs Tab -->
             <VWindowItem value="system-logs">
               <VCardText>
                 <div class="d-flex justify-end mb-4">
                   <VBtn size="small" variant="outlined" prepend-icon="ri-refresh-line" @click="fetchSystemLogs" :loading="logLoading">
                     Refresh
                   </VBtn>
                 </div>
                 <VCard variant="outlined" class="bg-grey-100 dark:bg-grey-900">
                   <VCardText class="pa-0">
                     <pre class="pa-4 overflow-auto" style="max-height: 600px; white-space: pre-wrap; font-family: monospace; font-size: 12px;">{{ systemLogs }}</pre>
                   </VCardText>
                 </VCard>
               </VCardText>
             </VWindowItem>

           </VWindow>
         </VCardText>
       </VCard>
       
       <EditUserDialog
         v-if="isEditDialogVisible && selectedUser"
         v-model:isDialogVisible="isEditDialogVisible"
         :user="selectedUser"
         @submit="onUserUpdate"
       />
    </VCol>
  </VRow>
</template>
