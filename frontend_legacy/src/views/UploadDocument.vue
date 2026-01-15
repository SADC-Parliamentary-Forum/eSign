<template>
  <div class="upload-container">
    <h2>Upload Document</h2>
    
    <form @submit.prevent="handleUpload" class="upload-form">
      <div class="form-group">
        <label>Title</label>
        <input v-model="title" type="text" required placeholder="e.g. Invoice #1234" />
      </div>

      <div class="form-group">
        <label>Department</label>
        <select v-model="department">
          <option value="Finance">Finance</option>
          <option value="Procurement">Procurement</option>
          <option value="HR">HR</option>
          <option value="Legal">Legal</option>
        </select>
      </div>

      <div class="form-group">
        <label>Contract Value (USD)</label>
        <input v-model="value" type="number" step="0.01" />
      </div>

      <div class="file-drop-zone" @dragover.prevent @drop.prevent="handleDrop" @click="triggerFileSelect">
        <div v-if="file" class="file-selected">
          <p>Selected: {{ file.name }}</p>
          <button @click.stop="file = null" class="remove-btn">Remove</button>
        </div>
        <div v-else>
          <p>Drag & Drop your PDF/DOCX here</p>
          <p class="subtext">or click to browse</p>
        </div>
        <input ref="fileInput" type="file" hidden @change="handleFileSelect" accept=".pdf,.doc,.docx" />
      </div>

      <div v-if="error" class="error">{{ error }}</div>
      <div v-if="success" class="success">{{ success }}</div>

      <button type="submit" :disabled="!file || uploading">
        {{ uploading ? 'Uploading...' : 'Upload Document' }}
      </button>

      <button type="button" class="cancel-btn" @click="$router.push('/')">Cancel</button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const title = ref('')
const department = ref('Finance')
const value = ref('')
const file = ref(null)
const fileInput = ref(null)
const uploading = ref(false)
const error = ref('')
const success = ref('')

function triggerFileSelect() {
  fileInput.value.click()
}

function handleFileSelect(event) {
  file.value = event.target.files[0]
}

function handleDrop(event) {
  file.value = event.dataTransfer.files[0]
}

async function handleUpload() {
  if (!file.value) return;

  uploading.value = true;
  error.value = '';
  success.value = '';

  const formData = new FormData();
  formData.append('file', file.value);
  formData.append('title', title.value);
  formData.append('department', department.value);
  if (value.value) formData.append('value', value.value);

  try {
    const response = await fetch('/api/documents', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${authStore.token}`
      },
      body: formData
    });

    if (!response.ok) {
      const data = await response.json();
      throw new Error(data.message || 'Upload failed');
    }

    success.value = 'Document uploaded successfully!';
    setTimeout(() => router.push('/'), 1500);
  } catch (e) {
    error.value = e.message;
  } finally {
    uploading.value = false;
  }
}
</script>

<style scoped>
.upload-container {
  max-width: 600px;
  margin: 2rem auto;
  padding: 2rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.upload-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

input, select {
  padding: 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
}

.file-drop-zone {
  border: 2px dashed #cbd5e0;
  padding: 2rem;
  text-align: center;
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.2s;
}

.file-drop-zone:hover {
  border-color: #4299e1;
}

.subtext {
  color: #718096;
  font-size: 0.875rem;
}

.remove-btn {
  background: #fc8181;
  color: white;
  border: none;
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  border-radius: 4px;
  margin-top: 0.5rem;
}

button[type="submit"] {
  background: #3182ce;
  color: white;
  padding: 1rem;
  border: none;
  border-radius: 4px;
  font-weight: bold;
  cursor: pointer;
}

button[type="submit"]:disabled {
  background: #a0aec0;
}

.cancel-btn {
  background: transparent;
  border: 1px solid #e2e8f0;
  padding: 0.75rem;
  border-radius: 4px;
  cursor: pointer;
}

.error { color: #e53e3e; }
.success { color: #38a169; }
</style>
