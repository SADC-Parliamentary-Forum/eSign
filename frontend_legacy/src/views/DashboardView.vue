<template>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="brand">SADC-eSign</div>
      <nav>
        <router-link to="/" class="active">Dashboard</router-link>
        <router-link to="/inbox">Inbox</router-link>
        <router-link to="/settings" v-if="isAdmin">Settings</router-link>
      </nav>
      <div class="user-profile">
        <span>{{ authStore.user?.name }}</span>
        <button @click="logout" class="logout-btn">Logout</button>
      </div>
    </aside>
    
    <main class="content">
      <header>
        <h2>Dashboard</h2>
        <button class="upload-btn" @click="$router.push('/upload')">New Document</button>
      </header>
      
      <div class="stats-grid">
        <div class="stat-card">
          <h3>Pending</h3>
          <p class="stat-value">{{ stats.pending }}</p>
        </div>
        <div class="stat-card">
          <h3>Signed</h3>
          <p class="stat-value">{{ stats.signed }}</p>
        </div>
        <div class="stat-card">
          <h3>Rejected</h3>
          <p class="stat-value">{{ stats.rejected }}</p>
        </div>
      </div>
      
      <div class="recent-docs">
        <h3>Recent Documents</h3>
        <ul v-if="documents.length" class="doc-list">
            <li v-for="doc in documents" :key="doc.id" class="doc-item" @click="$router.push('/documents/' + doc.id)">
                <div class="doc-info">
                    <span class="doc-title">{{ doc.title }}</span>
                    <span class="doc-meta">{{ doc.department }} • {{ formatDate(doc.created_at) }}</span>
                </div>
                <div class="doc-status">
                     <span :class="'status-badge ' + doc.status">{{ doc.status }}</span>
                </div>
            </li>
        </ul>
        <p v-else class="empty-state">No documents found.</p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const documents = ref([])
const stats = ref({ pending: 0, signed: 0, rejected: 0 })

const isAdmin = computed(() => authStore.role === 'admin')

onMounted(fetchDocuments)

async function fetchDocuments() {
    try {
        const res = await fetch('/api/documents', {
            headers: { 'Authorization': `Bearer ${authStore.token}` }
        })
        if (res.ok) {
            documents.value = await res.json()
            calculateStats()
        }
    } catch (e) {
        console.error("Failed to fetch docs", e)
    }
}

function calculateStats() {
    stats.value.pending = documents.value.filter(d => d.status === 'pending' || d.status === 'draft').length
    stats.value.signed = documents.value.filter(d => d.status === 'signed').length
    stats.value.rejected = documents.value.filter(d => d.status === 'rejected').length
}

function formatDate(date) {
    return new Date(date).toLocaleDateString()
}

function logout() {
  authStore.clearAuth()
  router.push('/login')
}
</script>

<style scoped>
/* Inherit styles from earlier + new list styles */
.dashboard-layout { display: flex; height: 100vh; background: #f7fafc; font-family: 'Inter', sans-serif; }
.sidebar { width: 250px; background: #2d3748; color: white; display: flex; flex-direction: column; }
.brand { padding: 1.5rem; font-size: 1.25rem; font-weight: bold; border-bottom: 1px solid #4a5568; }
nav { padding: 1rem; flex: 1; }
nav a { display: block; padding: 0.75rem 1rem; color: #a0aec0; text-decoration: none; border-radius: 4px; margin-bottom: 0.5rem; }
nav a:hover, nav a.router-link-active { background: #4a5568; color: white; }
.user-profile { padding: 1rem; border-top: 1px solid #4a5568; }
.logout-btn { background: none; border: none; color: #fc8181; cursor: pointer; padding: 0; margin-top: 0.5rem; }
.content { flex: 1; padding: 2rem; overflow-y: auto; }
header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
.upload-btn { background: #3182ce; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; font-weight: 600; cursor: pointer; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.stat-value { font-size: 2rem; font-weight: bold; color: #2d3748; margin: 0.5rem 0 0; }
.recent-docs { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

/* New List Styles */
.doc-list { list-style: none; padding: 0; margin: 0; }
.doc-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.1s; }
.doc-item:hover { background: #f7fafc; }
.doc-title { display: block; font-weight: 600; color: #2d3748; }
.doc-meta { font-size: 0.875rem; color: #718096; }
.status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-badge.draft { background: #edf2f7; color: #4a5568; }
.status-badge.pending { background: #feebc8; color: #c05621; }
.status-badge.signed { background: #c6f6d5; color: #2f855a; }
.status-badge.rejected { background: #fed7d7; color: #c53030; }
.empty-state { color: #a0aec0; text-align: center; padding: 2rem; }
</style>
