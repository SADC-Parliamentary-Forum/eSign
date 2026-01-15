<template>
  <div class="signing-container">
    <header>
      <h2>Sign Document: {{ document?.title }}</h2>
      <button @click="$router.back()">Cancel</button>
    </header>

    <div v-if="loading" class="loading">Loading document...</div>
    
    <div v-else class="content">
      <!-- Document Preview (Placeholder for PDF Viewer) -->
      <div class="pdf-viewer">
        <p>Document Preview (PDF Rendering would go here)</p>
        <p><strong>Hash:</strong> {{ document.file_hash }}</p>
        <div class="actions">
           <a :href="'/storage/' + document.file_path" target="_blank" class="download-link">Download PDF</a>
           <button @click="analyzeDocument" :disabled="analyzing" class="ai-btn">
             {{ analyzing ? 'Scanning...' : '✨ AI Risk Scan' }}
           </button>
        </div>
      </div>

      <!-- AI Results -->
      <div v-if="risks.length > 0" class="risk-report">
        <h3>⚠️ AI Risk Findings</h3>
        <ul>
          <li v-for="(risk, i) in risks" :key="i" :class="risk.severity">
            <strong>{{ risk.term }}:</strong> {{ risk.message }}
          </li>
        </ul>
      </div>

      <div class="signature-pad-section">
        <h3>Your Signature</h3>
        <p>Draw your signature below inside the box.</p>
        
        <canvas ref="canvas" width="500" height="200" 
          @mousedown="startDrawing" 
          @mousemove="draw" 
          @mouseup="stopDrawing" 
          @mouseleave="stopDrawing">
        </canvas>
        
        <div class="controls">
          <button @click="clearCanvas" class="clear-btn">Clear</button>
          <button @click="submitSignature" class="sign-btn" :disabled="submitting">
            {{ submitting ? 'Signing...' : 'Confirm Signature' }}
          </button>
        </div>
        
        <div v-if="error" class="error">{{ error }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const document = ref({})
const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const canvas = ref(null)
let ctx = null
let isDrawing = false

onMounted(async () => {
  await fetchDocument()
  initCanvas()
})

async function fetchDocument() {
  try {
    const res = await fetch(`/api/documents/${route.params.id}`, {
      headers: { 'Authorization': `Bearer ${authStore.token}` }
    })
    document.value = await res.json()
  } catch (e) {
    error.value = 'Failed to load document'
  } finally {
    loading.value = false
  }
}

function initCanvas() {
  if (!canvas.value) return
  ctx = canvas.value.getContext('2d')
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.strokeStyle = '#000'
}

function startDrawing(e) {
  isDrawing = true
  const rect = canvas.value.getBoundingClientRect()
  ctx.beginPath()
  ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top)
}

function draw(e) {
  if (!isDrawing) return
  const rect = canvas.value.getBoundingClientRect()
  ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top)
  ctx.stroke()
}

function stopDrawing() {
  isDrawing = false
  ctx.closePath()
}

function clearCanvas() {
  ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
}

const risks = ref([])
const analyzing = ref(false)

async function analyzeDocument() {
  analyzing.value = true
  try {
    const res = await fetch(`/api/documents/${document.value.id}/analyze`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${authStore.token}` }
    })
    const data = await res.json()
    if (res.ok) {
      risks.value = data.risks || []
      if (risks.value.length === 0) {
        alert('No significant risks detected.')
      }
    } else {
      alert('Analysis failed: ' + (data.message || 'Unknown error'))
    }
  } catch (e) {
    alert('Analysis error: ' + e.message)
  } finally {
    analyzing.value = false
  }
}

async function submitSignature() {
  submitting.value = true
  error.value = ''
  
  const signatureData = canvas.value.toDataURL('image/png')
  
  try {
    const res = await fetch(`/api/documents/${document.value.id}/sign`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authStore.token}`
      },
      body: JSON.stringify({ signature_data: signatureData })
    })

    if (!res.ok) throw new Error('Signature failed')
    
    router.push('/')
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.signing-container {
  max-width: 800px;
  margin: 2rem auto;
  padding: 2rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  font-family: 'Inter', sans-serif;
}

header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 1rem;
  margin-bottom: 2rem;
}

.pdf-viewer {
  background: #f7fafc;
  padding: 2rem;
  border: 1px solid #e2e8f0;
  margin-bottom: 2rem;
  text-align: center;
}

canvas {
  border: 2px dashed #cbd5e0;
  border-radius: 4px;
  cursor: crosshair;
  background: #fff;
  display: block;
  margin: 0 auto;
}

.controls {
  margin-top: 1rem;
  display: flex;
  justify-content: center;
  gap: 1rem;
}

.clear-btn {
  padding: 0.75rem 1.5rem;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  cursor: pointer;
}

.sign-btn {
  padding: 0.75rem 1.5rem;
  background: #2f855a;
  color: white;
  border: none;
  border-radius: 4px;
  font-weight: bold;
  cursor: pointer;
}

.sign-btn:disabled {
  background: #9ae6b4;
  cursor: not-allowed;
}

.download-link {
  color: #3182ce;
  text-decoration: underline;
}

.error { color: #e53e3e; margin-top: 1rem; text-align: center; }

.actions { display: flex; gap: 1rem; justify-content: center; margin-top: 1rem; align-items: center; }
.ai-btn { background: #6b46c1; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; }
.ai-btn:disabled { opacity: 0.7; }

.risk-report { background: #fff5f5; border: 1px solid #feb2b2; padding: 1rem; margin-bottom: 2rem; border-radius: 4px; }
.risk-report h3 { color: #c53030; margin-top: 0; }
.risk-report ul { padding-left: 1.5rem; }
.risk-report li.critical { color: #9b2c2c; font-weight: bold; }
.risk-report li.high { color: #c53030; }
.risk-report li.medium { color: #dd6b20; }
</style>
