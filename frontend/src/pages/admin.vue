<script setup>
/**
 * Enhanced Admin Console
 * Modern admin dashboard with user management, audit logs, and system settings
 */
import { onMounted, ref, watch, computed } from 'vue'

const activeTab = ref('dashboard')
const users = ref([])
const roles = ref([])
const logs = ref([])
const systemLogs = ref('')
const loading = ref(false)
const logLoading = ref(false)
const searchQuery = ref('')
const statusFilter = ref('ALL')
const roleFilter = ref('ALL')

// Dialogs
const showCreateUserDialog = ref(false)
const showEditUserDialog = ref(false)
const selectedUser = ref(null)
const saving = ref(false)
const success = ref('')
const error = ref('')

// Create/Edit User Form
const userForm = ref({
  name: '',
  email: '',
  password: '',
  role_id: null,
  status: 'ACTIVE',
  department: '',
  job_title: '',
})

// Dashboard Stats
const stats = ref({
  totalUsers: 0,
  activeUsers: 0,
  pendingUsers: 0,
  totalDocuments: 0,
})

// System Settings
const settings = ref({
  app_name: 'eSign',
  require_mfa: false,
  session_timeout: 60,
  max_document_size: 25,
  allowed_file_types: 'pdf,doc,docx',
  email_from_name: 'eSign Platform',
  email_from_address: 'noreply@esign.com',
})
const settingsLoading = ref(false)
const settingsSaving = ref(false)

onMounted(async () => {
  loading.value = true
  await Promise.all([fetchUsers(), fetchRoles(), fetchAudit(), fetchStats()])
  loading.value = false
})

async function fetchUsers() {
  try {
    const res = await $api('/admin/users')
    users.value = res.data || res
  } catch (e) {
    console.error('Failed to fetch users', e)
  }
}

async function fetchRoles() {
  try {
    const res = await $api('/admin/roles')
    roles.value = res.data || res || []
  } catch (e) {
    console.error('Failed to fetch roles', e)
  }
}

async function fetchAudit() {
  try {
    const res = await $api('/admin/audit-logs')
    logs.value = res.data || []
  } catch (e) {
    console.error('Failed to fetch audit logs', e)
  }
}

async function fetchStats() {
  try {
    // Calculate from users data
    stats.value.totalUsers = users.value.length
    stats.value.activeUsers = users.value.filter(u => u.status === 'ACTIVE').length
    stats.value.pendingUsers = users.value.filter(u => u.status === 'INVITED' || u.status === 'PENDING').length
    
    // Try to get document stats
    const docStats = await $api('/documents/stats').catch(() => ({}))
    stats.value.totalDocuments = docStats.total || 0
  } catch (e) {
    console.error('Failed to fetch stats', e)
  }
}

async function fetchSystemLogs() {
  logLoading.value = true
  try {
    const res = await $api('/admin/logs/system')
    systemLogs.value = res.content || ''
  } catch (e) {
    console.error('Failed to fetch system logs', e)
    systemLogs.value = 'Error fetching logs.'
  }
  logLoading.value = false
}

watch(activeTab, val => {
  if (val === 'system-logs' && !systemLogs.value) {
    fetchSystemLogs()
  }
  if (val === 'settings') {
    fetchSettings()
  }
})

// Export Users to CSV
function exportUsers() {
  const headers = ['Name', 'Email', 'Role', 'Department', 'Job Title', 'Status', 'MFA Enabled', 'Created At']
  const rows = users.value.map(u => [
    u.name,
    u.email,
    u.role?.display_name || 'No Role',
    u.department || '',
    u.job_title || '',
    u.status,
    u.mfa_enabled ? 'Yes' : 'No',
    formatTime(u.created_at)
  ])
  
  const csvContent = [
    headers.join(','),
    ...rows.map(row => row.map(cell => `"${(cell || '').toString().replace(/"/g, '""')}"`).join(','))
  ].join('\n')
  
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `users_export_${new Date().toISOString().split('T')[0]}.csv`
  link.click()
  URL.revokeObjectURL(url)
  success.value = 'Users exported successfully!'
}

// Settings
async function fetchSettings() {
  settingsLoading.value = true
  try {
    const res = await $api('/admin/settings').catch(() => ({}))
    if (res && Object.keys(res).length > 0) {
      settings.value = { ...settings.value, ...res }
    }
  } catch (e) {
    console.error('Failed to fetch settings', e)
  } finally {
    settingsLoading.value = false
  }
}

async function saveSettings() {
  settingsSaving.value = true
  error.value = ''
  try {
    await $api('/admin/settings', {
      method: 'PUT',
      body: settings.value,
    })
    success.value = 'Settings saved successfully!'
  } catch (e) {
    error.value = 'Failed to save settings: ' + (e.message || 'Unknown error')
  } finally {
    settingsSaving.value = false
  }
}

// Filtered users
const filteredUsers = computed(() => {
  let result = [...users.value]
  
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(u => 
      u.name?.toLowerCase().includes(q) || 
      u.email?.toLowerCase().includes(q)
    )
  }
  
  if (statusFilter.value !== 'ALL') {
    result = result.filter(u => u.status === statusFilter.value)
  }
  
  if (roleFilter.value !== 'ALL') {
    result = result.filter(u => u.role?.id === roleFilter.value || u.role_id === roleFilter.value)
  }
  
  return result
})

// Create User
function openCreateDialog() {
  userForm.value = {
    name: '',
    email: '',
    password: '',
    role_id: roles.value[0]?.id || null,
    status: 'ACTIVE',
    department: '',
    job_title: '',
  }
  showCreateUserDialog.value = true
}

async function createUser() {
  if (!userForm.value.name || !userForm.value.email || !userForm.value.password) {
    error.value = 'Name, email, and password are required'
    return
  }
  
  saving.value = true
  error.value = ''
  
  try {
    await $api('/admin/users', {
      method: 'POST',
      body: userForm.value,
    })
    
    showCreateUserDialog.value = false
    success.value = 'User created successfully!'
    await fetchUsers()
    await fetchStats()
  } catch (e) {
    error.value = 'Failed to create user: ' + (e.message || 'Unknown error')
  } finally {
    saving.value = false
  }
}

// Edit User
function openEditDialog(user) {
  selectedUser.value = user
  userForm.value = {
    name: user.name,
    email: user.email,
    password: '',
    role_id: user.role?.id || user.role_id,
    status: user.status || 'ACTIVE',
    department: user.department || '',
    job_title: user.job_title || '',
  }
  showEditUserDialog.value = true
}

async function updateUser() {
  saving.value = true
  error.value = ''
  
  try {
    const payload = { ...userForm.value }
    if (!payload.password) {
      delete payload.password // Don't send empty password
    }
    
    await $api(`/admin/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: payload,
    })
    
    showEditUserDialog.value = false
    success.value = 'User updated successfully!'
    await fetchUsers()
  } catch (e) {
    error.value = 'Failed to update user: ' + (e.message || 'Unknown error')
  } finally {
    saving.value = false
  }
}

// Delete User
async function deleteUser(user) {
  if (!confirm(`Are you sure you want to delete ${user.name}? This action cannot be undone.`)) return
  
  try {
    await $api(`/admin/users/${user.id}`, { method: 'DELETE' })
    success.value = 'User deleted'
    await fetchUsers()
    await fetchStats()
  } catch (e) {
    error.value = 'Failed to delete user: ' + (e.message || 'Unknown error')
  }
}

// Toggle User Status
async function toggleUserStatus(user) {
  const newStatus = user.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'
  
  try {
    await $api(`/admin/users/${user.id}`, {
      method: 'PUT',
      body: { status: newStatus },
    })
    await fetchUsers()
    await fetchStats()
  } catch (e) {
    error.value = 'Failed to update user status'
  }
}

function formatTime(time) {
  if (!time) return '-'
  return new Date(time).toLocaleString()
}

function getStatusColor(status) {
  const colors = {
    ACTIVE: 'success',
    INACTIVE: 'error',
    INVITED: 'warning',
    PENDING: 'info',
  }
  return colors[status] || 'grey'
}

const userHeaders = [
  { title: 'User', key: 'name', sortable: true },
  { title: 'Role', key: 'role.display_name' },
  { title: 'Department', key: 'department' },
  { title: 'Status', key: 'status' },
  { title: 'MFA', key: 'mfa_enabled' },
  { title: 'Joined', key: 'created_at' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
]

const auditHeaders = [
  { title: 'Event', key: 'event' },
  { title: 'User', key: 'user.name' },
  { title: 'Details', key: 'description' },
  { title: 'IP Address', key: 'ip_address' },
  { title: 'Time', key: 'created_at' },
]
</script>

<template>
  <VContainer class="py-6" fluid>
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold">Admin Console</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">Manage users, view audit logs, and configure settings</p>
      </div>
      <VBtn variant="tonal" to="/" prepend-icon="mdi-arrow-left">
        Back to Dashboard
      </VBtn>
    </div>

    <!-- Alerts -->
    <VAlert v-if="success" type="success" variant="tonal" closable class="mb-4" @click:close="success = ''">
      {{ success }}
    </VAlert>
    <VAlert v-if="error" type="error" variant="tonal" closable class="mb-4" @click:close="error = ''">
      {{ error }}
    </VAlert>

    <!-- Tabs -->
    <VTabs v-model="activeTab" class="mb-4">
      <VTab value="dashboard">
        <VIcon icon="mdi-view-dashboard" class="mr-2" />
        Dashboard
      </VTab>
      <VTab value="users">
        <VIcon icon="mdi-account-group" class="mr-2" />
        Users
      </VTab>
      <VTab value="audit">
        <VIcon icon="mdi-history" class="mr-2" />
        Audit Logs
      </VTab>
      <VTab value="system-logs">
        <VIcon icon="mdi-file-document" class="mr-2" />
        System Logs
      </VTab>
      <VTab value="settings">
        <VIcon icon="mdi-cog" class="mr-2" />
        Settings
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <!-- Dashboard Tab -->
      <VWindowItem value="dashboard">
        <VRow>
          <VCol cols="12" sm="6" md="3">
            <VCard class="text-center pa-4" color="primary" variant="tonal">
              <VIcon icon="mdi-account-group" size="40" class="mb-2" />
              <div class="text-h4 font-weight-bold">{{ stats.totalUsers }}</div>
              <div class="text-caption">Total Users</div>
            </VCard>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VCard class="text-center pa-4" color="success" variant="tonal">
              <VIcon icon="mdi-account-check" size="40" class="mb-2" />
              <div class="text-h4 font-weight-bold">{{ stats.activeUsers }}</div>
              <div class="text-caption">Active Users</div>
            </VCard>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VCard class="text-center pa-4" color="warning" variant="tonal">
              <VIcon icon="mdi-account-clock" size="40" class="mb-2" />
              <div class="text-h4 font-weight-bold">{{ stats.pendingUsers }}</div>
              <div class="text-caption">Pending</div>
            </VCard>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VCard class="text-center pa-4" color="info" variant="tonal">
              <VIcon icon="mdi-file-document-multiple" size="40" class="mb-2" />
              <div class="text-h4 font-weight-bold">{{ stats.totalDocuments }}</div>
              <div class="text-caption">Documents</div>
            </VCard>
          </VCol>
        </VRow>

        <VRow class="mt-4">
          <VCol cols="12" md="6">
            <VCard>
              <VCardTitle>Quick Actions</VCardTitle>
              <VCardText>
              <VBtn block color="primary" class="mb-2" prepend-icon="mdi-account-plus" @click="openCreateDialog">
                  Create New User
                </VBtn>
                <VBtn block variant="outlined" class="mb-2" prepend-icon="mdi-download" @click="exportUsers">
                  Export User List
                </VBtn>
                <VBtn block variant="outlined" prepend-icon="mdi-cog" @click="activeTab = 'settings'">
                  System Settings
                </VBtn>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="6">
            <VCard>
              <VCardTitle>Recent Activity</VCardTitle>
              <VCardText>
                <VList density="compact" v-if="logs.length > 0">
                  <VListItem v-for="log in logs.slice(0, 5)" :key="log.id">
                    <VListItemTitle>{{ log.event }}</VListItemTitle>
                    <VListItemSubtitle>{{ log.user?.name || 'System' }} · {{ formatTime(log.created_at) }}</VListItemSubtitle>
                  </VListItem>
                </VList>
                <div v-else class="text-center text-medium-emphasis py-4">No recent activity</div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>

      <!-- Users Tab -->
      <VWindowItem value="users">
        <VCard>
          <VCardText>
            <!-- Toolbar -->
            <div class="d-flex flex-wrap gap-4 mb-4">
              <VTextField
                v-model="searchQuery"
                placeholder="Search users..."
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 300px;"
                clearable
              />
              <VSelect
                v-model="statusFilter"
                :items="['ALL', 'ACTIVE', 'INACTIVE', 'INVITED']"
                label="Status"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 150px;"
              />
              <VSelect
                v-model="roleFilter"
                :items="[{ id: 'ALL', display_name: 'All Roles' }, ...roles]"
                item-title="display_name"
                item-value="id"
                label="Role"
                variant="outlined"
                density="compact"
                hide-details
                style="max-width: 180px;"
              />
              <VSpacer />
              <VBtn color="primary" prepend-icon="mdi-account-plus" @click="openCreateDialog">
                Create User
              </VBtn>
            </div>

            <!-- Users Table -->
            <VDataTable
              :headers="userHeaders"
              :items="filteredUsers"
              :loading="loading"
              class="text-no-wrap"
              hover
            >
              <template #item.name="{ item }">
                <div class="d-flex align-center py-2">
                  <VAvatar size="36" color="primary" variant="tonal" class="mr-3">
                    {{ item.name?.charAt(0).toUpperCase() }}
                  </VAvatar>
                  <div>
                    <div class="font-weight-medium">{{ item.name }}</div>
                    <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
                  </div>
                </div>
              </template>
              <template #item.role.display_name="{ item }">
                <VChip size="small" variant="tonal" :color="item.role?.name === 'admin' ? 'error' : 'primary'">
                  {{ item.role?.display_name || 'No Role' }}
                </VChip>
              </template>
              <template #item.department="{ item }">
                {{ item.department || '-' }}
              </template>
              <template #item.status="{ item }">
                <VChip :color="getStatusColor(item.status)" size="small" variant="tonal">
                  {{ item.status }}
                </VChip>
              </template>
              <template #item.mfa_enabled="{ item }">
                <VIcon :icon="item.mfa_enabled ? 'mdi-shield-check' : 'mdi-shield-off'" :color="item.mfa_enabled ? 'success' : 'grey'" />
              </template>
              <template #item.created_at="{ item }">
                {{ formatTime(item.created_at) }}
              </template>
              <template #item.actions="{ item }">
                <VBtn icon="mdi-pencil" size="small" variant="text" color="primary" @click="openEditDialog(item)" />
                <VBtn
                  :icon="item.status === 'ACTIVE' ? 'mdi-account-off' : 'mdi-account-check'"
                  size="small"
                  variant="text"
                  :color="item.status === 'ACTIVE' ? 'warning' : 'success'"
                  @click="toggleUserStatus(item)"
                  :title="item.status === 'ACTIVE' ? 'Deactivate' : 'Activate'"
                />
                <VBtn icon="mdi-delete" size="small" variant="text" color="error" @click="deleteUser(item)" />
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Audit Tab -->
      <VWindowItem value="audit">
        <VCard>
          <VCardText>
            <VDataTable
              :headers="auditHeaders"
              :items="logs"
              :loading="loading"
              class="text-no-wrap"
            >
              <template #item.user.name="{ item }">
                <span v-if="item.user" class="font-weight-medium">{{ item.user.name }}</span>
                <span v-else class="text-disabled font-italic">System</span>
              </template>
              <template #item.created_at="{ item }">
                {{ formatTime(item.created_at) }}
              </template>
            </VDataTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- System Logs Tab -->
      <VWindowItem value="system-logs">
        <VCard>
          <VCardText>
            <div class="d-flex justify-end mb-4">
              <VBtn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="logLoading" @click="fetchSystemLogs">
                Refresh
              </VBtn>
            </div>
            <VCard variant="outlined" class="bg-grey-lighten-4">
              <VCardText class="pa-0">
                <pre class="pa-4 overflow-auto" style="max-height: 600px; white-space: pre-wrap; font-family: monospace; font-size: 12px;">{{ systemLogs || 'No logs available' }}</pre>
              </VCardText>
            </VCard>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Settings Tab -->
      <VWindowItem value="settings">
        <VRow>
          <VCol cols="12" md="6">
            <VCard>
              <VCardTitle>
                <VIcon icon="mdi-application" class="mr-2" />
                Application Settings
              </VCardTitle>
              <VCardText>
                <VTextField
                  v-model="settings.app_name"
                  label="Application Name"
                  variant="outlined"
                  class="mb-4"
                />
                <VTextField
                  v-model.number="settings.session_timeout"
                  label="Session Timeout (minutes)"
                  type="number"
                  variant="outlined"
                  class="mb-4"
                />
                <VSwitch
                  v-model="settings.require_mfa"
                  label="Require MFA for all users"
                  color="primary"
                  hide-details
                />
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" md="6">
            <VCard>
              <VCardTitle>
                <VIcon icon="mdi-file-document" class="mr-2" />
                Document Settings
              </VCardTitle>
              <VCardText>
                <VTextField
                  v-model.number="settings.max_document_size"
                  label="Max Document Size (MB)"
                  type="number"
                  variant="outlined"
                  class="mb-4"
                />
                <VTextField
                  v-model="settings.allowed_file_types"
                  label="Allowed File Types"
                  variant="outlined"
                  hint="Comma-separated list (e.g., pdf,doc,docx)"
                  persistent-hint
                />
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12" md="6">
            <VCard>
              <VCardTitle>
                <VIcon icon="mdi-email" class="mr-2" />
                Email Settings
              </VCardTitle>
              <VCardText>
                <VTextField
                  v-model="settings.email_from_name"
                  label="From Name"
                  variant="outlined"
                  class="mb-4"
                />
                <VTextField
                  v-model="settings.email_from_address"
                  label="From Email Address"
                  variant="outlined"
                />
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12">
            <VBtn color="primary" size="large" :loading="settingsSaving" @click="saveSettings">
              <VIcon icon="mdi-content-save" class="mr-2" />
              Save Settings
            </VBtn>
          </VCol>
        </VRow>
      </VWindowItem>
    </VWindow>

    <!-- Create User Dialog -->
    <VDialog v-model="showCreateUserDialog" max-width="600" persistent>
      <VCard title="Create New User">
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.name" label="Full Name" variant="outlined" required />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.email" label="Email" type="email" variant="outlined" required />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="userForm.password"
                label="Password"
                type="password"
                variant="outlined"
                required
                hint="Minimum 8 characters"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="userForm.role_id"
                :items="roles"
                item-title="display_name"
                item-value="id"
                label="Role"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.department" label="Department" variant="outlined" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.job_title" label="Job Title" variant="outlined" />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="userForm.status"
                :items="['ACTIVE', 'INACTIVE', 'INVITED']"
                label="Status"
                variant="outlined"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showCreateUserDialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="saving" @click="createUser">Create User</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Edit User Dialog -->
    <VDialog v-model="showEditUserDialog" max-width="600" persistent>
      <VCard title="Edit User">
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.name" label="Full Name" variant="outlined" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.email" label="Email" type="email" variant="outlined" disabled hint="Email cannot be changed" persistent-hint />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="userForm.password"
                label="New Password"
                type="password"
                variant="outlined"
                hint="Leave blank to keep current"
                persistent-hint
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="userForm.role_id"
                :items="roles"
                item-title="display_name"
                item-value="id"
                label="Role"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.department" label="Department" variant="outlined" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="userForm.job_title" label="Job Title" variant="outlined" />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="userForm.status"
                :items="['ACTIVE', 'INACTIVE', 'INVITED']"
                label="Status"
                variant="outlined"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showEditUserDialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="saving" @click="updateUser">Save Changes</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
