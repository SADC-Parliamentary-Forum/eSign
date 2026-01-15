<template>
  <div class="admin-layout">
    <header>
      <h2>Admin Console</h2>
      <button @click="$router.push('/')">Back to Dashboard</button>
    </header>

    <div class="tabs">
      <button :class="{ active: activeTab === 'users' }" @click="activeTab = 'users'">Users</button>
      <button :class="{ active: activeTab === 'audit' }" @click="activeTab = 'audit'">Audit Logs</button>
    </div>

    <!-- Users Tab -->
    <div v-if="activeTab === 'users'" class="tab-content">
      <h3>System Users</h3>
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>MFA</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id">
            <td>{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>{{ user.role?.display_name }}</td>
            <td>{{ user.mfa_enabled ? 'Yes' : 'No' }}</td>
            <td>
              <button class="action-btn">Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Audit Tab -->
    <div v-if="activeTab === 'audit'" class="tab-content">
      <h3>System Audit Logs</h3>
      <table class="data-table">
        <thead>
          <tr>
            <th>Event</th>
            <th>User</th>
            <th>IP/Agent</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td>{{ log.event }}</td>
            <td>{{ log.user?.name || 'System' }}</td>
            <td>{{ log.ip_address }}</td>
            <td>{{ formatTime(log.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const activeTab = ref('users')
const users = ref([])
const logs = ref([])

onMounted(async () => {
  await fetchUsers()
  await fetchAudit()
})

async function fetchUsers() {
  const res = await fetch('/api/users', {
    headers: { 'Authorization': `Bearer ${authStore.token}` }
  })
  if (res.ok) users.value = await res.json()
}

async function fetchAudit() {
  const res = await fetch('/api/audit-logs', {
    headers: { 'Authorization': `Bearer ${authStore.token}` }
  })
  if (res.ok) {
    const data = await res.json()
    logs.value = data.data // Paginated response
  }
}

function formatTime(time) {
  return new Date(time).toLocaleString()
}
</script>

<style scoped>
.admin-layout {
  padding: 2rem;
  max-width: 1000px;
  margin: 0 auto;
  font-family: 'Inter', sans-serif;
  background: #f7fafc;
  min-height: 100vh;
}

header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.tabs {
  margin-bottom: 1.5rem;
  border-bottom: 2px solid #e2e8f0;
}

.tabs button {
  padding: 0.75rem 1.5rem;
  border: none;
  background: none;
  cursor: pointer;
  font-weight: 600;
  color: #718096;
}

.tabs button.active {
  color: #3182ce;
  border-bottom: 2px solid #3182ce;
  margin-bottom: -2px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.data-table th, .data-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

.data-table th {
  background: #f7fafc;
  font-weight: 600;
  color: #4a5568;
}

.action-btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  border: 1px solid #cbd5e0;
  border-radius: 4px;
  background: white;
  cursor: pointer;
}
</style>
