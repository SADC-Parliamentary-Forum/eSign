<script setup>
/**
 * Document Prepare Page - Redesigned
 * Beautiful, intuitive document preparation experience
 * - Responsive 3-panel layout
 * - Streamlined signer management
 * - Elegant field placement
 */
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed/dist/index.essential.mjs'
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist'
import PdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDisplay } from 'vuetify'

GlobalWorkerOptions.workerSrc = PdfWorker

// Core states
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { mobile, smAndDown, mdAndDown } = useDisplay()

// Use blank layout to remove app sidebar
definePage({
  meta: {
    layout: 'blank',
  },
})

// Document state
const doc = ref(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

// PDF state
const pdfSource = ref(null)
const pageCount = ref(0)
const pdfLoadingTask = ref(null)

// Signers state
const signers = ref([])
const selectedSigner = ref(null)

// Fields state
const fields = ref([])
const selectedFieldId = ref(null)

// Drawing state
const isDrawing = ref(false)
const drawStart = ref({ x: 0, y: 0, page: 1 })
const drawCurrent = ref({ x: 0, y: 0 })

// Field type popup
const showFieldTypePopup = ref(false)
const pendingField = ref(null)

// Drag & Resize State
const isDragging = ref(false)
const isResizing = ref(false)
const dragOffset = ref({ x: 0, y: 0 })
const activeInteractionFieldId = ref(null)

// Submit dialog
const showSubmitDialog = ref(false)
const showSelfSignDialog = ref(false)
const hasOrganizationalRoles = ref(false)
// ... (rest of existing code)

// Drawing handlers
function startDrawing(e, page) {
  if (!selectedSigner.value || isDragging.value || isResizing.value) return
  
  const target = e.currentTarget
  const rect = target.getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100
  
  isDrawing.value = true
  drawStart.value = { x, y, page }
  drawCurrent.value = { x, y }
}

function onDrawing(e, page) {
  if (isDragging.value || isResizing.value) {
      onInteractionMove(e, page)
      return
  }
  if (!isDrawing.value || page !== drawStart.value.page) return
  
  const target = e.currentTarget
  const rect = target.getBoundingClientRect()
  const x = ((e.clientX - rect.left) / rect.width) * 100
  const y = ((e.clientY - rect.top) / rect.height) * 100
  
  drawCurrent.value = { x, y }
}

function endDrawing(e) {
  if (isDragging.value || isResizing.value) {
      endInteraction()
      return
  }
  if (!isDrawing.value) return
  
  isDrawing.value = false
  
  const minX = Math.min(drawStart.value.x, drawCurrent.value.x)
  const minY = Math.min(drawStart.value.y, drawCurrent.value.y)
  const width = Math.abs(drawCurrent.value.x - drawStart.value.x)
  const height = Math.abs(drawCurrent.value.y - drawStart.value.y)
  
  // Minimum size check
  if (width < 3 || height < 2) return
  
  pendingField.value = {
    x: minX,
    y: minY,
    width,
    height,
    page: drawStart.value.page
  }
  
  showFieldTypePopup.value = true
}

// --- Interaction Handlers (Drag/Resize) ---

function startDrag(e, field) {
    if (isResizing.value) return
    e.stopPropagation() // Prevent drawing start
    
    isDragging.value = true
    activeInteractionFieldId.value = field.id
    selectedFieldId.value = field.id
    
    // Calculate offset from top-left of field
    // We need parent rect to convert mouse px to %
    const parent = e.target.closest('.field-overlay')
    const rect = parent.getBoundingClientRect()
    
    // Mouse Pos in %
    const mouseX = ((e.clientX - rect.left) / rect.width) * 100
    const mouseY = ((e.clientY - rect.top) / rect.height) * 100
    
    dragOffset.value = {
        x: mouseX - field.x,
        y: mouseY - field.y
    }
}

function startResize(e, field) {
    e.stopPropagation()
    isResizing.value = true
    activeInteractionFieldId.value = field.id
    selectedFieldId.value = field.id
}

function onInteractionMove(e, page) {
    const field = fields.value.find(f => f.id === activeInteractionFieldId.value)
    if (!field) return

    const target = e.currentTarget // .field-overlay
    const rect = target.getBoundingClientRect()
    const mouseX = ((e.clientX - rect.left) / rect.width) * 100
    const mouseY = ((e.clientY - rect.top) / rect.height) * 100

    if (isDragging.value) {
        let newX = mouseX - dragOffset.value.x
        let newY = mouseY - dragOffset.value.y
        
        // Bounds check (0-100)
        newX = Math.max(0, Math.min(100 - field.width, newX))
        newY = Math.max(0, Math.min(100 - field.height, newY))
        
        field.x = newX
        field.y = newY
    } else if (isResizing.value) {
        // Resize changes width/height based on mouse pos relative to field x/y
        let newW = mouseX - field.x
        let newH = mouseY - field.y
        
        // Minimum size
        newW = Math.max(5, Math.min(100 - field.x, newW))
        newH = Math.max(3, Math.min(100 - field.y, newH))
        
        field.width = newW
        field.height = newH
    }
}

function endInteraction() {
    isDragging.value = false
    isResizing.value = false
    activeInteractionFieldId.value = null
}

function duplicateFieldToAllPages(field) {
    if (!field) return
    
    // Ensure the source field has a group_id
    if (!field.group_id) {
        field.group_id = crypto.randomUUID()
    }
    const groupId = field.group_id
    
    // Create copies for all other pages
    const newFields = []
    for (let p = 1; p <= pageCount.value; p++) {
        if (p === field.page_number) continue // Skip current page
        
        // Check if field already exists at this exact spot? Maybe not necessary.
        
        newFields.push({
            ...field,
            id: crypto.randomUUID(),
            group_id: groupId, // Link all copies
            page_number: p
        })
    }
    
    fields.value.push(...newFields)
    showFieldTypePopup.value = false // Close any popups
}


const sequentialSigning = ref(false)
const expiresInDays = ref(30)

// Completion notification recipients
const notifyOwner = ref(true)
const notifySigners = ref(true)
const additionalEmails = ref('')

// Mobile drawer states
const showLeftDrawer = ref(false)
const showRightDrawer = ref(false)

// New signer form
const showAddSignerForm = ref(false)
const newSignerName = ref('')
const newSignerEmail = ref('')

// Signature Capture State for Self-Sign
const signatureMode = ref('draw') // 'draw' | 'upload' | 'type'
const initialsMode = ref('draw') // 'draw' | 'upload' | 'type'
const uploadedSignature = ref(null)
const uploadedInitials = ref(null)
const typedName = ref('')
const typedInitials = ref('')
const selectedFont = ref('Dancing Script')
const saveToProfile = ref(true)

const signatureFonts = [
  'Dancing Script',
  'Pacifico',
  'Pinyon Script',
  'Great Vibes',
  'Satisfy',
]
const signatureCanvas = ref(null)
const initialsCanvas = ref(null)
let sigCtx = null
let initCtx = null
let isSigDrawing = false
let isInitDrawing = false

function initSigCanvas() {
  if (signatureCanvas.value) {
    sigCtx = signatureCanvas.value.getContext('2d')
    sigCtx.lineWidth = 2
    sigCtx.lineCap = 'round'
    sigCtx.strokeStyle = '#000'
    sigCtx.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height)
  }
  
  if (initialsCanvas.value) {
    initCtx = initialsCanvas.value.getContext('2d')
    initCtx.lineWidth = 2
    initCtx.lineCap = 'round'
    initCtx.strokeStyle = '#000'
    initCtx.clearRect(0, 0, initialsCanvas.value.width, initialsCanvas.value.height)
  }
}

function startSigDrawing(e) {
  isSigDrawing = true
  const rect = signatureCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  sigCtx.beginPath()
  sigCtx.moveTo(x, y)
}

function startInitDrawing(e) {
  isInitDrawing = true
  const rect = initialsCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  initCtx.beginPath()
  initCtx.moveTo(x, y)
}

function sigDraw(e) {
  if (!isSigDrawing) return
  if (e.cancelable) e.preventDefault()
  const rect = signatureCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  sigCtx.lineTo(x, y)
  sigCtx.stroke()
}

function initDraw(e) {
  if (!isInitDrawing) return
  if (e.cancelable) e.preventDefault()
  const rect = initialsCanvas.value.getBoundingClientRect()
  const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left
  const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top
  initCtx.lineTo(x, y)
  initCtx.stroke()
}

function stopSigDrawing() {
  isSigDrawing = false
  sigCtx?.closePath()
}

function stopInitDrawing() {
  isInitDrawing = false
  initCtx?.closePath()
}

function clearSigCanvas() {
  if (!sigCtx) return
  sigCtx.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height)
}

function clearInitCanvas() {
  if (!initCtx) return
  initCtx.clearRect(0, 0, initialsCanvas.value.width, initialsCanvas.value.height)
}

function generateTypedImage(text, font, width = 540, height = 120) {
  const offscreen = window.document.createElement('canvas')
  offscreen.width = width
  offscreen.height = height
  const ctx = offscreen.getContext('2d')
  
  // Clear for transparency
  ctx.clearRect(0, 0, width, height)
  
  ctx.fillStyle = '#000'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  
  const fontSize = height * 0.6
  ctx.font = `${fontSize}px "${font}", cursive`
  
  ctx.fillText(text, width / 2, height / 2)
  
  return offscreen.toDataURL('image/png')
}

function handleInitUpload(event) {
  const file = event.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = e => {
    uploadedInitials.value = e.target.result
  }
  reader.readAsDataURL(file)
}

function handleSigUpload(event) {
  const file = event.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = e => {
    uploadedSignature.value = e.target.result
  }
  reader.readAsDataURL(file)
}


// Color palette for signers
const signerColors = [
  { bg: '#E3F2FD', border: '#1976D2', text: '#1976D2' },
  { bg: '#F3E5F5', border: '#7B1FA2', text: '#7B1FA2' },
  { bg: '#E8F5E9', border: '#388E3C', text: '#388E3C' },
  { bg: '#FFF3E0', border: '#F57C00', text: '#F57C00' },
  { bg: '#FCE4EC', border: '#C2185B', text: '#C2185B' },
  { bg: '#E0F7FA', border: '#0097A7', text: '#0097A7' },
]

// Field types
const fieldTypes = [
  { type: 'SIGNATURE', icon: 'ri-pen-nib-line', label: 'Signature', desc: 'Full signature' },
  { type: 'INITIALS', icon: 'ri-font-size-2', label: 'Initials', desc: 'Quick initials' },
  { type: 'DATE', icon: 'ri-calendar-line', label: 'Date', desc: 'Auto-fill date' },
  { type: 'TEXT', icon: 'ri-text', label: 'Text', desc: 'Custom text' },
  { type: 'CHECKBOX', icon: 'ri-checkbox-line', label: 'Checkbox', desc: 'Yes/No option' },
]

// Responsive PDF width
const pdfWidth = computed(() => {
  if (mobile.value) return 350
  if (smAndDown.value) return 500
  if (mdAndDown.value) return 600
  return 700
})

// Validation computed
const validation = computed(() => {
  const issues = []
  
  if (signers.value.length === 0) {
    issues.push('Add at least one signer')
  }
  
  signers.value.forEach(signer => {
    const signerFields = fields.value.filter(f => f.signer_email === signer.email)
    if (signerFields.length === 0) {
      issues.push(`${signer.name} needs signature fields`)
    }
  })
  
  return {
    isValid: issues.length === 0,
    issues
  }
})

// Progress indicator
const progressSteps = computed(() => [
  { label: 'Add Signers', done: signers.value.length > 0, icon: 'ri-user-add-line' },
  { label: 'Place Fields', done: fields.value.length > 0, icon: 'ri-pen-nib-line' },
  { label: 'Submit', done: false, icon: 'ri-send-plane-line' },
])

onMounted(async () => {
  await fetchDocument()
})

async function waitForDocumentReady(documentId, maxAttempts = 60) {
  for (let i = 0; i < maxAttempts; i++) {
    const res = await $api(`/documents/${documentId}`)
    doc.value = res
    if (res.status === 'FAILED') throw new Error('Document conversion failed. Please upload a PDF or try again.')
    if (res.status === 'DRAFT' && res.pdf_url) return res
    await new Promise(r => setTimeout(r, 2000))
  }
  throw new Error('Document is taking too long to process. Please try again later.')
}

async function fetchDocument() {
  try {
    loading.value = true
    let res = await $api(`/documents/${route.params.id}`)
    doc.value = res

    if (res.status === 'IN_PROGRESS' || (res.status === 'DRAFT' && !res.pdf_url)) {
      error.value = ''
      res = await waitForDocumentReady(route.params.id)
    }
    if (res.status === 'FAILED') {
      error.value = 'Document conversion failed. Please upload a PDF or try again.'
      return
    }

    // Fetch PDF only when document is ready and is PDF
    await loadPdfBlob(route.params.id)
    
    // Load existing signers if any
    if (res.signers?.length > 0) {
      signers.value = res.signers.map((s, i) => ({
        ...s,
        color: signerColors[i % signerColors.length]
      }))
    } else if (res.is_self_sign) {
      // Auto-add current user for self-sign
      const user = authStore.user
      if (user) {
        signers.value = [{
          id: crypto.randomUUID(),
          name: user.name,
          email: user.email,
          color: signerColors[0],
          role: 'Signer'
        }]
        
        // Pre-fill typed signature
        typedName.value = user.name
        typedInitials.value = user.name.split(' ').map(n => n[0]).join('').toUpperCase()
      }
    }

    if (signers.value.length > 0) {
      selectedSigner.value = signers.value[0]
    }
    
    // Load existing fields if any
    if (res.fields?.length > 0) {
      // 1. Identify unassigned organizational roles
      const unassignedRoles = new Map()
      const unassignedLegacyRoles = new Map()

      res.fields.forEach(f => {
        // Normalize type immediately
        if (f.type) f.type = f.type.toUpperCase()
        
        if (f.organizational_role_id && !f.signer_email && !f.document_signer_id) {
          if (!unassignedRoles.has(f.organizational_role_id)) {
            unassignedRoles.set(f.organizational_role_id, {
              id: f.organizational_role_id, // Use role ID as temp signer ID
              isPlaceholder: true,
              name: f.organizational_role?.name || 'Signer',
              role: f.signer_role || 'Signer',
              organizational_role_id: f.organizational_role_id,
              email: '', // Empty email indicates unassigned
              color: signerColors[signers.value.length % signerColors.length]
            })
            signers.value.push(unassignedRoles.get(f.organizational_role_id))
          }
        } 
        // Legacy string role support
        else if (f.signer_role && !f.signer_email && !f.document_signer_id) {
             if (!unassignedLegacyRoles.has(f.signer_role)) {
                 const newPlaceholder = {
                     id: 'legacy_' + f.signer_role.replace(/\s+/g, '_'),
                     isPlaceholder: true,
                     name: 'Placeholder',
                     role: f.signer_role,
                     organizational_role_id: null, // Legacy has no UUID
                     email: '',
                     color: signerColors[(signers.value.length + unassignedLegacyRoles.size) % signerColors.length]
                 }
                 unassignedLegacyRoles.set(f.signer_role, newPlaceholder)
                 signers.value.push(newPlaceholder)
             }
        }
      })

      fields.value = res.fields.map(f => {
        // Find owner (real or placeholder)
        let owner = null
        if (f.document_signer_id) {
          owner = signers.value.find(s => s.id === f.document_signer_id)
        }

        if (!owner && f.signer_email) {
          owner = signers.value.find(s => s.email === f.signer_email)
        }

        if (!owner && f.organizational_role_id) {
            owner = signers.value.find(s => s.organizational_role_id === f.organizational_role_id)
        }
        
        // Legacy fallback
        if (!owner && f.signer_role) {
             owner = signers.value.find(s => s.role === f.signer_role && s.isPlaceholder)
        }
        
        // Fix: Ensure type is uppercase for icon rendering
        if (f.type) f.type = f.type.toUpperCase()

        return {
          ...f,
          signer_color: owner?.color,
          document_signer_id: owner?.id || f.document_signer_id
        }
      })
      
      // Check if we are in strict template mode (roles exist)
      hasOrganizationalRoles.value = res.fields.some(f => f.organizational_role_id)
    }
  } catch (e) {
    error.value = 'Failed to load document: ' + (e.message || 'Unknown error')
    console.error('Failed to load document', e)
  } finally {
    loading.value = false
  }
}

async function loadPdfBlob(documentId) {
  try {
    pageCount.value = 0

    const token = localStorage.getItem('token')
    const response = await fetch(`/api/documents/${documentId}/pdf`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/pdf'
      }
    })

    const contentType = response.headers.get('Content-Type') || ''
    if (!response.ok) {
      const errBody = contentType.includes('application/json') ? await response.json().catch(() => ({})) : {}
      const msg = errBody.message || `Failed to load PDF: ${response.statusText}`
      throw new Error(msg)
    }

    const blob = await response.blob()
    if (blob.type && blob.type !== 'application/pdf') {
      throw new Error('This document could not be converted to PDF. Please upload a PDF file or try again.')
    }

    const objectUrl = URL.createObjectURL(blob)
    const pdfBytes = await blob.arrayBuffer()

    // Load with getDocument to pre-count pages and keep the worker alive for VuePdfEmbed.
    // We intentionally do NOT destroy the loading task so the worker remains running;
    // destroying it terminates the shared worker and VuePdfEmbed renders blank pages.
    if (pdfLoadingTask.value) {
      pdfLoadingTask.value.destroy().catch(() => {})
    }
    pdfLoadingTask.value = getDocument({ data: pdfBytes })
    const pdf = await pdfLoadingTask.value.promise
    pageCount.value = pdf.numPages || 1

    pdfSource.value = objectUrl
  } catch (e) {
    console.error('Failed to load PDF blob:', e)
    error.value = e.message || 'Failed to load PDF preview.'
  }
}

// Cleanup blob URL and pdfjs loading task on unmount
onUnmounted(() => {
  if (pdfLoadingTask.value) {
    pdfLoadingTask.value.destroy().catch(() => {})
    pdfLoadingTask.value = null
  }
  if (pdfSource.value && pdfSource.value.startsWith('blob:')) {
    URL.revokeObjectURL(pdfSource.value)
  }
})

function handleDocumentLoad(pdf) {
  pageCount.value = pdf.numPages
}

// Signer management
const editingSignerId = ref(null)

function addSigner() {
  if (!newSignerName.value || !newSignerEmail.value) return

  if (editingSignerId.value) {
    // Update existing
    const index = signers.value.findIndex(s => s.id === editingSignerId.value)
    if (index !== -1) {
      const s = signers.value[index]
      signers.value[index] = {
        ...s,
        name: newSignerName.value,
        email: newSignerEmail.value,
        isPlaceholder: false
      }
    }
    editingSignerId.value = null
  } else {
    // Add new
    const newSigner = {
      id: crypto.randomUUID(),
      name: newSignerName.value,
      email: newSignerEmail.value,
      color: signerColors[signers.value.length % signerColors.length]
    }
    signers.value.push(newSigner)
    selectedSigner.value = newSigner
  }

  // Reset form
  newSignerName.value = ''
  newSignerEmail.value = ''
  showAddSignerForm.value = false
}

function editSigner(signer) {
  newSignerName.value = signer.name === 'Signer' && signer.isPlaceholder ? '' : signer.name
  newSignerEmail.value = signer.email
  editingSignerId.value = signer.id
  showAddSignerForm.value = true
}

function removeSigner(index) {
  const removed = signers.value.splice(index, 1)[0]
  
  // Remove orphaned fields
  if (removed.isPlaceholder) {
     fields.value = fields.value.filter(f => f.organizational_role_id !== removed.organizational_role_id)
  } else {
     fields.value = fields.value.filter(f => f.signer_email !== removed.email)
  }
  
  // If removed signer was selected, select first remaining
  if (selectedSigner.value?.id === removed.id) {
    selectedSigner.value = signers.value[0] || null
  }
}

function selectSigner(signer) {
  selectedSigner.value = signer
}

// Drawing handlers


function selectFieldType(type) {
  if (!pendingField.value || !selectedSigner.value) return
  
  // Apply 45% size increase for INITIALS as requested by user
  let finalWidth = pendingField.value.width
  let finalHeight = pendingField.value.height
  
  if (type === 'INITIALS') {
    finalWidth *= 1.45
    finalHeight *= 1.45
  }

  const newField = {
    id: crypto.randomUUID(),
    document_id: doc.value?.id,
    type,
    page_number: pendingField.value.page,
    x: pendingField.value.x,
    y: pendingField.value.y,
    width: finalWidth,
    height: finalHeight,
    signer_email: selectedSigner.value.email,
    document_signer_id: selectedSigner.value.id,
    signer_color: selectedSigner.value.color,
    required: true,
    label: ['SIGNATURE', 'INITIALS', 'DATE'].includes(type) ? type : null
  }
  
  fields.value.push(newField)
  pendingField.value = null
  showFieldTypePopup.value = false
}

function cancelFieldType() {
  pendingField.value = null
  showFieldTypePopup.value = false
}

function selectField(field) {
  selectedFieldId.value = field.id
}

function deleteField(fieldId) {
  fields.value = fields.value.filter(f => f.id !== fieldId)
  selectedFieldId.value = null
}

function getFieldsByPage(page) {
  return fields.value.filter(f => f.page_number === page)
}

function getFieldColor(field) {
  return field.signer_color || { bg: '#FFF9C4', border: '#FBC02D', text: '#F57F17' }
}

function getFieldTypeIcon(type) {
  return fieldTypes.find(t => t.type === type)?.icon || 'ri-question-line'
}

// Drawing preview computed
const drawingRect = computed(() => {
  if (!isDrawing.value) return null
  
  const minX = Math.min(drawStart.value.x, drawCurrent.value.x)
  const minY = Math.min(drawStart.value.y, drawCurrent.value.y)
  const width = Math.abs(drawCurrent.value.x - drawStart.value.x)
  const height = Math.abs(drawCurrent.value.y - drawStart.value.y)
  
  return {
    left: minX + '%',
    top: minY + '%',
    width: width + '%',
    height: height + '%',
    page: drawStart.value.page
  }
})

// Submit flow
function openSubmitDialog() {
  if (!validation.value.isValid) {
    error.value = validation.value.issues.join('. ')
    return
  }
  showSubmitDialog.value = true
}

async function submitDocument() {
  saving.value = true
  error.value = ''
  
  try {
    // 1. Save or update signers
    const signerPayload = signers.value.map((s, i) => ({
      name: s.name,
      email: s.email,
      role: s.role || null,
      order: i + 1,
      organizational_role_id: s.organizational_role_id || null
    }))
    
    // The API returns the created signers with their real DB IDs
    const signersResponse = await $api(`/documents/${doc.value.id}/signers`, {
      method: 'POST',
      body: { 
        signers: signerPayload,
        sequential: sequentialSigning.value
      }
    })
    
    // 2. Map fields to the real signer IDs
    // We match signers by email to find the correct DB IDs
    const dbSigners = signersResponse.signers || []
    
    const fieldPayload = fields.value.map(f => {
      // Find the signer for this field
      // We look for a signer in our local list that matches the field's assigned signer
      const localSigner = signers.value.find(s => s.id === f.document_signer_id)
      
      let realSignerId = null
      let realSignerEmail = f.signer_email
      
      if (localSigner) {
        // If we found the local signer, find the corresponding DB signer by email
        const dbSigner = dbSigners.find(ds => ds.email === localSigner.email)
        if (dbSigner) {
          realSignerId = dbSigner.id
          realSignerEmail = dbSigner.email
        }
      } else if (f.signer_email) {
        // Fallback: try to match by email directly
        const dbSigner = dbSigners.find(ds => ds.email === f.signer_email)
        if (dbSigner) {
          realSignerId = dbSigner.id
        }
      }

      return {
        type: f.type,
        page_number: f.page_number,
        x: Number(f.x),
        y: Number(f.y),
        width: Number(f.width),
        height: Number(f.height),
        signer_email: realSignerEmail, // Ensure email is consistent
        document_signer_id: realSignerId, // Use the REAL DB ID
        required: f.required,
        group_id: f.group_id // Include Group Link
      }
    })
    
    await $api(`/documents/${doc.value.id}/fields`, {
      method: 'POST',
      body: { fields: fieldPayload }
    })
    
    // 3. Send document
    await $api(`/documents/${doc.value.id}/send`, {
      method: 'POST',
      body: {
        sequential: sequentialSigning.value,
        expires_in_days: expiresInDays.value,
        completion_recipients: {
          notify_owner: notifyOwner.value,
          notify_signers: notifySigners.value,
          additional_emails: additionalEmails.value
            ? additionalEmails.value.split(',').map(e => e.trim()).filter(e => e)
            : []
        }
      }
    })
    
    // Success - redirect to dashboard (or signing page if self-sign)
    if (doc.value.is_self_sign) {
        router.push(`/documents/${doc.value.id}`)
    } else {
        router.push('/')
    }
    
  } catch (e) {
    error.value = e.message || 'Failed to send document'
    console.error('Submit error:', e)
  } finally {
    saving.value = false
  }
}

// Keyboard shortcuts
function handleKeydown(e) {
  if (e.key === 'Delete' && selectedFieldId.value) {
    deleteField(selectedFieldId.value)
  }
  if (e.key === 'Escape') {
    if (showFieldTypePopup.value) {
      cancelFieldType()
    } else {
      selectedFieldId.value = null
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
// Watch for dialog open to init canvas
watch(showSelfSignDialog, (val) => {
  if (val && signatureMode.value === 'draw') {
    setTimeout(initSigCanvas, 100)
  }
})

function openSelfSignDialog() {
  if (!validation.value.isValid) {
    error.value = validation.value.issues.join('. ')
    return
  }
  showSelfSignDialog.value = true
}

async function handleSelfSign() {
  saving.value = true
  error.value = ''
  
  try {
     // 1. Save Signers (Just me for self-sign)
    const signerPayload = signers.value.map((s, i) => ({
      name: s.name,
      email: s.email,
      role: s.role || null,
      order: i + 1
    }))
    
    // Create signers to get real IDs
    const signersResponse = await $api(`/documents/${doc.value.id}/signers`, {
      method: 'POST',
      body: { 
        signers: signerPayload,
        sequential: false
      }
    })
    
    const dbSigners = signersResponse.signers || []
    
    // 2. Save Fields (Map to real IDs)
    const fieldPayload = fields.value.map(f => {
      // Find the signer for this field
      const localSigner = signers.value.find(s => s.id === f.document_signer_id)
      let realSignerId = null
      let realSignerEmail = f.signer_email
      
      if (localSigner) {
        const dbSigner = dbSigners.find(ds => ds.email === localSigner.email)
        if (dbSigner) {
          realSignerId = dbSigner.id
          realSignerEmail = dbSigner.email
        }
      } else if (f.signer_email) {
          const dbSigner = dbSigners.find(ds => ds.email === f.signer_email)
          if (dbSigner) realSignerId = dbSigner.id
      }

      return {
        type: f.type,
        page_number: f.page_number,
        x: Number(f.x),
        y: Number(f.y),
        width: Number(f.width),
        height: Number(f.height),
        signer_email: realSignerEmail,
        document_signer_id: realSignerId,
        required: f.required,
        group_id: f.group_id
      }
    })
    
    await $api(`/documents/${doc.value.id}/fields`, {
      method: 'POST',
      body: { fields: fieldPayload }
    })
    
    // 3. Call Finish
    let sigData = null
    let initData = null
    
    // Determine Signature
    if (signatureMode.value === 'upload') {
        sigData = uploadedSignature.value
    } else if (signatureMode.value === 'type') {
        sigData = generateTypedImage(typedName.value, selectedFont.value, 540, 120)
    } else {
        sigData = signatureCanvas.value.toDataURL('image/png')
    }

    // Determine Initials
    if (initialsMode.value === 'upload') {
        initData = uploadedInitials.value
    } else if (initialsMode.value === 'type') {
        initData = generateTypedImage(typedInitials.value, selectedFont.value, 540, 80)
    } else {
        initData = initialsCanvas.value.toDataURL('image/png')
    }

    await $api(`/documents/${doc.value.id}/sign-self`, {
        method: 'POST',
        body: {
            signature_data: sigData,
            initials_data: initData,
            save_to_profile: saveToProfile.value
        }
    })
    
    // Redirect to document view (completed)
    router.push(`/documents/${doc.value.id}`)
    
  } catch (e) {
    error.value = e.message || 'Failed to sign document'
    showSelfSignDialog.value = false
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="prepare-page">
    <!-- Top Header Bar -->
    <header class="header-bar">
      <div class="header-left">
        <v-btn 
          icon="ri-home-line" 
          variant="text" 
          size="small"
          @click="router.push('/')"
          title="Back to Dashboard"
        />
        <v-divider vertical class="mx-2" />
        <div class="document-info">
          <span v-if="doc" class="document-title">{{ doc.title }}</span>
          <v-skeleton-loader v-else type="text" width="150" />
        </div>
      </div>
      
      <div class="header-center d-none d-md-flex">
        <!-- Progress Steps -->
        <div class="progress-steps">
          <div 
            v-for="(step, i) in progressSteps" 
            :key="i"
            class="progress-step"
            :class="{ 'step-done': step.done }"
          >
            <v-icon :icon="step.icon" size="18" />
            <span class="step-label">{{ step.label }}</span>
            <v-icon v-if="i < progressSteps.length - 1" icon="ri-arrow-right-s-line" size="16" class="step-arrow" />
          </div>
        </div>
      </div>
      
      <div class="header-right">
        <!-- Mobile menu buttons -->
        <v-btn 
          v-if="smAndDown"
          icon="ri-group-line" 
          variant="text"
          size="small"
          :badge="signers.length || undefined"
          @click="showLeftDrawer = true"
        />
        <v-btn 
          v-if="smAndDown"
          icon="ri-palette-line" 
          variant="text"
          size="small"
          @click="showRightDrawer = true"
        />
        
        <v-btn
          color="primary"
          variant="elevated"
          size="small"
          :disabled="!validation.isValid"
          @click="doc?.is_self_sign ? openSelfSignDialog() : openSubmitDialog()"
          class="submit-btn"
        >
          <v-icon :icon="doc?.is_self_sign ? 'ri-quill-pen-line' : 'ri-send-plane-line'" class="mr-1" size="18" />
          <span class="d-none d-sm-inline">{{ doc?.is_self_sign ? 'Sign & Finish' : 'Submit' }}</span>
        </v-btn>
      </div>
    </header>

    <!-- Error Alert -->
    <v-alert v-if="error" type="error" variant="tonal" closable class="mx-4 mt-2" @click:close="error = ''">
      {{ error }}
    </v-alert>

    <!-- Main Content Area -->
    <div class="main-content">
      <!-- Left Sidebar: Signers (Desktop) -->
      <aside v-if="!smAndDown" class="left-sidebar">
        <div class="sidebar-header">
          <span class="sidebar-title">Signers</span>
          <v-chip size="x-small" color="primary" variant="flat">{{ signers.length }}</v-chip>
        </div>
        
        <!-- Add Signer Button - Always visible at top -->
        <v-btn 
          v-if="!showAddSignerForm && !doc?.is_self_sign && !hasOrganizationalRoles"
          block 
          color="primary" 
          variant="tonal"
          size="small"
          prepend-icon="ri-user-add-line"
          class="mb-3"
          @click="showAddSignerForm = true"
        >
          Add Signer
        </v-btn>
        
        <!-- Inline Add Signer Form -->
        <v-expand-transition>
          <div v-if="showAddSignerForm" class="add-signer-form mb-3">
            <div v-if="editingSignerId && signers.find(s => s.id === editingSignerId)?.isPlaceholder" class="text-caption font-weight-bold text-primary mb-2">
                Assigning to Role: {{ signers.find(s => s.id === editingSignerId)?.role }}
            </div>
            <v-text-field
              v-model="newSignerName"
              label="Name"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-2"
              autofocus
            />
            <v-text-field
              v-model="newSignerEmail"
              label="Email"
              type="email"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-2"
              @keyup.enter="addSigner"
            />
            <div class="d-flex gap-2">
              <v-btn size="small" variant="text" @click="showAddSignerForm = false; editingSignerId = null">Cancel</v-btn>
              <v-btn size="small" color="primary" @click="addSigner" :disabled="!newSignerName || !newSignerEmail">{{ editingSignerId ? 'Update' : 'Add' }}</v-btn>
            </div>
          </div>
        </v-expand-transition>
        
        <!-- Signers List -->
        <div class="signers-list">
          <div
            v-for="(signer, index) in signers"
            :key="signer.id"
            class="signer-item"
            :class="{ 'signer-selected': selectedSigner?.id === signer.id }"
            :style="{ borderLeftColor: signer.color.border }"
            @click="selectSigner(signer)"
          >
            <v-avatar size="28" :color="signer.color.border" class="mr-2">
              <span class="text-white text-caption">{{ signer.name.charAt(0) }}</span>
            </v-avatar>
            <div class="signer-info">
              <div class="signer-name">
                  {{ signer.name }}
                  <span v-if="signer.isPlaceholder" class="text-caption text-warning ml-1">(Unassigned)</span>
              </div>
              <div v-if="signer.role && signer.role !== 'Signer' && signer.role !== signer.name" class="text-caption text-medium-emphasis">
                  Role: {{ signer.role }}
              </div>
              <div class="signer-email">{{ signer.email }}</div>
            </div>
            <div class="d-flex align-center">
                 <v-btn 
                   icon="mdi-pencil" 
                   size="x-small" 
                   variant="text" 
                   color="medium-emphasis"
                   @click.stop="editSigner(signer)"
                 />
                <v-btn 
                  v-if="!doc?.is_self_sign && !signer.isPlaceholder"
                  icon="ri-close-line" 
                  size="x-small" 
                  variant="text" 
                  @click.stop="removeSigner(index)"
                />
            </div>
          </div>
          
          <div v-if="signers.length === 0" class="empty-state">
            <v-icon icon="ri-user-add-line" size="32" class="mb-2" />
            <div class="text-caption">Add your first signer above</div>
          </div>
        </div>
      </aside>

      <!-- Center: PDF Canvas -->
      <main class="pdf-area">
        <div v-if="loading" class="loading-state">
          <v-progress-circular indeterminate size="48" color="primary" />
          <div class="text-caption mt-3">Loading document...</div>
        </div>

        <div v-else-if="pdfSource" class="pdf-scroll">
          <div
            v-if="pageCount === 0"
            class="pdf-page-wrapper"
          >
            <div class="pdf-page" :style="{ width: pdfWidth + 'px' }">
              <VuePdfEmbed
                :source="pdfSource"
                :page="1"
                :width="pdfWidth"
                @loaded="handleDocumentLoad"
              />
            </div>
            <div class="page-indicator">Loading pages...</div>
          </div>

          <div
            v-else
            v-for="page in pageCount" 
            :key="page" 
            class="pdf-page-wrapper"
          >
            <div class="pdf-page" :style="{ width: pdfWidth + 'px' }">
              <VuePdfEmbed 
                :source="pdfSource" 
                :page="page"
                :width="pdfWidth"
              />
              
              <!-- Field Overlay -->
              <div 
                class="field-overlay"
                :class="{ 
                    'draw-cursor': selectedSigner && !isDragging && !isResizing,
                    'grabbing': isDragging,
                    'resizing': isResizing
                }"
                @mousedown="startDrawing($event, page)"
                @mousemove="onDrawing($event, page)"
                @mouseup="endDrawing"
                @mouseleave="endDrawing"
              >
                <!-- Placed Fields -->
                <div
                  v-for="field in getFieldsByPage(page)"
                  :key="field.id"
                  class="field-box"
                  :class="{ 
                      'field-selected': selectedFieldId === field.id,
                      'is-interacting': activeInteractionFieldId === field.id
                  }"
                  :style="{
                    left: field.x + '%',
                    top: field.y + '%',
                    width: field.width + '%',
                    height: field.height + '%',
                    backgroundColor: getFieldColor(field).bg,
                    borderColor: getFieldColor(field).border,
                    color: getFieldColor(field).text,
                    zIndex: selectedFieldId === field.id ? 10 : 1
                  }"
                  @mousedown="startDrag($event, field)"
                  @click.stop="selectField(field)"
                >
                  <v-icon :icon="getFieldTypeIcon(field.type)" size="14" />
                  
                  <!-- Toolbar -->
                  <div v-if="selectedFieldId === field.id" class="field-toolbar">
                      <v-btn
                        icon="ri-file-copy-line"
                        size="x-small"
                        color="secondary"
                        variant="flat"
                        class="toolbar-btn"
                        title="Duplicate to all pages"
                        @click.stop="duplicateFieldToAllPages(field)"
                      />
                      <v-btn
                        icon="ri-delete-bin-line"
                        size="x-small"
                        color="error"
                        variant="flat"
                        class="toolbar-btn"
                        @click.stop="deleteField(field.id)"
                      />
                  </div>

                  <!-- Resize Handle -->
                  <div 
                    v-if="selectedFieldId === field.id"
                    class="resize-handle"
                    @mousedown="startResize($event, field)"
                  />
                </div>
                
                <!-- Drawing Preview -->
                <div
                  v-if="drawingRect && drawingRect.page === page"
                  class="drawing-preview"
                  :style="{
                    left: drawingRect.left,
                    top: drawingRect.top,
                    width: drawingRect.width,
                    height: drawingRect.height,
                    borderColor: selectedSigner?.color?.border || '#1976D2'
                  }"
                />
              </div>
            </div>
            <div class="page-indicator">{{ page }} / {{ pageCount }}</div>
          </div>
        </div>
        
        <!-- No signer hint -->
        <v-fade-transition>
          <div v-if="!selectedSigner && signers.length === 0 && !loading && pdfSource" class="hint-overlay">
            <v-card class="hint-card" max-width="300">
              <v-card-text class="text-center">
                <v-icon icon="ri-hand-coin-line" size="48" color="primary" class="mb-3" />
                <div class="text-h6 mb-2">Let's Get Started!</div>
                <div class="text-body-2 text-medium-emphasis">
                  Add a signer first, then draw signature fields on the document.
                </div>
              </v-card-text>
            </v-card>
          </div>
        </v-fade-transition>
      </main>

      <!-- Right Sidebar: Field Types (Desktop) -->
      <aside v-if="!smAndDown" class="right-sidebar">
        <div class="sidebar-header">
          <span class="sidebar-title">Field Types</span>
        </div>
        
        <div class="field-types-hint">
          {{ selectedSigner ? `Draw on PDF to add fields for ${selectedSigner.name}` : 'Select a signer first' }}
        </div>
        
        <div class="field-types-list">
          <div
            v-for="type in fieldTypes"
            :key="type.type"
            class="field-type-item"
            :class="{ 'disabled': !selectedSigner }"
          >
            <v-icon :icon="type.icon" size="20" class="field-type-icon" />
            <div class="field-type-info">
              <div class="field-type-label">{{ type.label }}</div>
              <div class="field-type-desc">{{ type.desc }}</div>
            </div>
          </div>
        </div>
        
        <v-divider class="my-3" />
        
        <div class="summary-section">
          <div class="summary-title">Summary</div>
          <div class="summary-item">
            <v-icon icon="ri-group-line" size="16" />
            <span>{{ signers.length }} signer(s)</span>
          </div>
          <div class="summary-item">
            <v-icon icon="ri-pen-nib-line" size="16" />
            <span>{{ fields.length }} field(s)</span>
          </div>
          <div class="summary-item">
            <v-icon icon="ri-pages-line" size="16" />
            <span>{{ pageCount }} page(s)</span>
          </div>
        </div>
      </aside>
    </div>

    <!-- Mobile Left Drawer -->
    <v-navigation-drawer v-model="showLeftDrawer" temporary location="left" width="280">
      <div class="pa-4">
        <div class="sidebar-header mb-3">
          <span class="sidebar-title">Signers</span>
          <v-chip size="x-small" color="primary" variant="flat">{{ signers.length }}</v-chip>
        </div>
        
        <v-btn 
          v-if="!showAddSignerForm && !hasOrganizationalRoles"
          block 
          color="primary" 
          variant="tonal"
          prepend-icon="mdi-account-plus"
          class="mb-3"
          @click="showAddSignerForm = true"
        >
          Add Signer
        </v-btn>
        
        <v-expand-transition>
          <div v-if="showAddSignerForm" class="add-signer-form mb-3">
            <v-text-field v-model="newSignerName" label="Name" variant="outlined" density="compact" hide-details class="mb-2" />
            <v-text-field v-model="newSignerEmail" label="Email" type="email" variant="outlined" density="compact" hide-details class="mb-2" />
            <div class="d-flex gap-2">
              <v-btn size="small" variant="text" @click="showAddSignerForm = false; editingSignerId = null">Cancel</v-btn>
              <v-btn size="small" color="primary" @click="addSigner">{{ editingSignerId ? 'Update' : 'Add' }}</v-btn>
            </div>
          </div>
        </v-expand-transition>
        
        <div class="signers-list">
          <div
            v-for="(signer, index) in signers"
            :key="signer.id"
            class="signer-item"
            :class="{ 'signer-selected': selectedSigner?.id === signer.id }"
            :style="{ borderLeftColor: signer.color.border }"
            @click="selectSigner(signer); showLeftDrawer = false"
          >
            <v-avatar size="28" :color="signer.color.border" class="mr-2">
              <span class="text-white text-caption">{{ signer.name.charAt(0) }}</span>
            </v-avatar>
            <div class="signer-info">
              <div class="signer-name">
                  {{ signer.name }}
                  <span v-if="signer.isPlaceholder" class="text-caption text-warning ml-1">(Unassigned)</span>
              </div>
              <div v-if="signer.role && signer.role !== 'Signer' && signer.role !== signer.name" class="text-caption text-medium-emphasis">
                  Role: {{ signer.role }}
              </div>
              <div class="signer-email">{{ signer.email }}</div>
            </div>
            <div class="d-flex align-center">
                 <v-btn 
                   icon="mdi-pencil" 
                   size="x-small" 
                   variant="text" 
                   color="medium-emphasis"
                   @click.stop="editSigner(signer)"
                 />
                <v-btn 
                    v-if="!signer.isPlaceholder" 
                    icon="mdi-close" 
                    size="x-small" 
                    variant="text" 
                    @click.stop="removeSigner(index)" 
                />
            </div>
          </div>
        </div>
      </div>
    </v-navigation-drawer>

    <!-- Mobile Right Drawer -->
    <v-navigation-drawer v-model="showRightDrawer" temporary location="right" width="250">
      <div class="pa-4">
        <div class="sidebar-header mb-3">
          <span class="sidebar-title">Field Types</span>
        </div>
        
        <div class="field-types-list">
          <div v-for="type in fieldTypes" :key="type.type" class="field-type-item" :class="{ 'disabled': !selectedSigner }">
            <v-icon :icon="type.icon" size="20" class="field-type-icon" />
            <div class="field-type-info">
              <div class="field-type-label">{{ type.label }}</div>
            </div>
          </div>
        </div>
        
        <v-divider class="my-3" />
        
        <div class="summary-section">
          <div class="summary-item"><v-icon icon="mdi-account-multiple" size="16" /><span>{{ signers.length }} signer(s)</span></div>
          <div class="summary-item"><v-icon icon="mdi-draw" size="16" /><span>{{ fields.length }} field(s)</span></div>
        </div>
      </div>
    </v-navigation-drawer>

    <!-- Field Type Selection Dialog -->
    <v-dialog v-model="showFieldTypePopup" max-width="320" persistent>
      <v-card rounded="lg">
        <v-card-title class="d-flex align-center pa-4">
          <v-icon icon="ri-shape-2-line" class="mr-2" color="primary" />
          Choose Field Type
        </v-card-title>
        
        <v-divider />

        <v-list density="compact">
          <v-list-item
            v-for="type in fieldTypes"
            :key="type.type"
            :prepend-icon="type.icon"
            :title="type.label"
            :subtitle="type.desc"
            @click="selectFieldType(type.type)"
          />
        </v-list>

        <v-divider />

        <v-card-actions>
          <v-btn block variant="text" @click="cancelFieldType">Cancel</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Submit Confirmation Dialog -->
    <v-dialog v-model="showSubmitDialog" max-width="450" persistent>
      <v-card rounded="lg">
        <v-card-title class="bg-primary text-white pa-4">
          <v-icon icon="ri-send-plane-line" class="mr-2" />
          Send for Signing
        </v-card-title>
        
        <v-card-text class="pa-4">
          <v-alert type="info" variant="tonal" density="compact" class="mb-4">
            <strong>{{ doc?.title }}</strong><br>
            <span class="text-caption">{{ signers.length }} signer(s) • {{ fields.length }} field(s)</span>
          </v-alert>

          <div class="text-subtitle-2 mb-2">Recipients:</div>
          <v-chip
            v-for="signer in signers"
            :key="signer.id"
            size="small"
            class="mr-1 mb-1"
            :style="{ borderColor: signer.color.border }"
            variant="outlined"
          >
            {{ signer.name }}
          </v-chip>
          
          <v-divider class="my-4" />

          <v-switch
            v-model="sequentialSigning"
            label="Sequential signing (in order)"
            color="primary"
            hide-details
            density="compact"
            class="mb-3"
          />

          <v-text-field
            v-model.number="expiresInDays"
            label="Expires in (days)"
            type="number"
            variant="outlined"
            density="compact"
            :min="1"
            :max="365"
          />

          <v-divider class="my-4" />

          <div class="text-subtitle-2 mb-2">Completion Notifications:</div>
          <div class="text-caption text-medium-emphasis mb-3">
            Who should receive the signed document and audit trail?
          </div>

          <v-checkbox
            v-model="notifyOwner"
            label="Notify me (document owner)"
            color="primary"
            hide-details
            density="compact"
            class="mb-2"
          />

          <v-checkbox
            v-model="notifySigners"
            label="Notify all signers"
            color="primary"
            hide-details
            density="compact"
            class="mb-3"
          />

          <v-text-field
            v-model="additionalEmails"
            label="Additional recipients (comma-separated)"
            placeholder="email1@example.com, email2@example.com"
            variant="outlined"
            density="compact"
            hint="Optional: Add CC recipients"
            persistent-hint
          />
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4">
          <v-btn variant="text" @click="showSubmitDialog = false">Cancel</v-btn>
          <v-spacer />
          <v-btn
            color="primary"
            variant="elevated"
            :loading="saving"
            @click="submitDocument"
          >
            <v-icon icon="ri-send-plane-line" class="mr-1" />
            Send Now
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Self Sign Dialog -->
    <v-dialog v-model="showSelfSignDialog" max-width="550" persistent>
      <v-card rounded="lg">
        <v-card-title class="bg-primary text-white pa-4">
          <v-icon icon="ri-quill-pen-line" class="mr-2" />
          Finalize & Sign
        </v-card-title>
        
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-4">
            Place your signature below to finalize this document. It will be marked as COMPLETED immediately.
          </p>

          <v-tabs v-model="signatureMode" density="compact" color="primary" class="mb-4">
            <v-tab value="draw">Draw</v-tab>
            <v-tab value="type">Type</v-tab>
            <v-tab value="upload">Upload</v-tab>
          </v-tabs>

          <div v-if="signatureMode === 'draw'">
            <div class="text-caption mb-1">Signature:</div>
            <div class="signature-canvas-container border rounded mb-2">
              <canvas
                ref="signatureCanvas"
                width="500"
                height="120"
                style="width: 100%; height: 120px; background: #fff; cursor: crosshair;"
                @mousedown="startSigDrawing"
                @mousemove="sigDraw"
                @mouseup="stopSigDrawing"
                @mouseleave="stopSigDrawing"
                @touchstart="startSigDrawing"
                @touchmove="sigDraw"
                @touchend="stopSigDrawing"
              ></canvas>
              <div class="d-flex justify-end pr-2 pb-1">
                <v-btn size="x-small" variant="text" @click="clearSigCanvas">Clear</v-btn>
              </div>
            </div>
          </div>

          <div v-else-if="signatureMode === 'type'">
            <v-text-field
              v-model="typedName"
              label="Full Name"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-2"
            />
            <div class="signature-preview text-center pa-2 border rounded bg-grey-lighten-5 mb-2" :style="{ fontFamily: selectedFont, fontSize: '32px' }">
              {{ typedName || 'Your Signature' }}
            </div>
            
            <div class="text-caption mb-1">Font Style:</div>
            <v-chip-group v-model="selectedFont" mandatory selected-class="text-primary" class="mb-2">
              <v-chip v-for="font in signatureFonts" :key="font" :value="font" size="small" variant="outlined" filter>
                <span :style="{ fontFamily: font }">Sign</span>
              </v-chip>
            </v-chip-group>
          </div>

          <div v-else>
            <v-file-input
              label="Signature Image"
              variant="outlined"
              density="compact"
              accept="image/*"
              prepend-icon="ri-attachment-line"
              @change="handleSigUpload"
            />
          </div>

          <!-- Initials Section -->
          <div class="mb-4">
            <div class="text-subtitle-2 font-weight-bold mb-2">Initials</div>
            <v-tabs v-model="initialsMode" density="compact" color="primary" class="mb-2">
              <v-tab value="draw">Draw</v-tab>
              <v-tab value="type">Type</v-tab>
              <v-tab value="upload">Upload</v-tab>
            </v-tabs>

            <div v-if="initialsMode === 'draw'">
              <div class="signature-canvas-container border rounded mb-2">
                <canvas
                  ref="initialsCanvas"
                  width="500"
                  height="80"
                  style="width: 100%; height: 80px; background: #fff; cursor: crosshair;"
                  @mousedown="startInitDrawing"
                  @mousemove="initDraw"
                  @mouseup="stopInitDrawing"
                  @mouseleave="stopInitDrawing"
                  @touchstart="startInitDrawing"
                  @touchmove="initDraw"
                  @touchend="stopInitDrawing"
                ></canvas>
                <div class="d-flex justify-end pr-2 pb-1">
                  <v-btn size="x-small" variant="text" @click="clearInitCanvas">Clear</v-btn>
                </div>
              </div>
            </div>

            <div v-else-if="initialsMode === 'type'">
              <v-text-field
                v-model="typedInitials"
                label="Initials"
                variant="outlined"
                density="compact"
                hide-details
                class="mb-2"
              />
              <div class="initials-preview text-center pa-2 border rounded bg-grey-lighten-5 mb-2" :style="{ fontFamily: selectedFont, fontSize: '32px' }">
                {{ typedInitials || 'Init' }}
              </div>
            </div>

            <div v-else>
              <v-file-input
                label="Initials Image"
                variant="outlined"
                density="compact"
                accept="image/*"
                prepend-icon="ri-font-size"
                @change="handleInitUpload"
              />
            </div>
          </div>

          <v-checkbox
            v-model="saveToProfile"
            label="Save as my default signature"
            color="primary"
            hide-details
            density="compact"
          />
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4">
          <v-btn variant="text" @click="showSelfSignDialog = false">Cancel</v-btn>
          <v-spacer />
          <v-btn
            color="primary"
            variant="elevated"
            :loading="saving"
            @click="handleSelfSign"
          >
            <v-icon icon="ri-quill-pen-line" class="mr-1" />
            Sign & Complete
          </v-btn>
        </v-card-actions>
      </v-card>
  </v-dialog>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Dancing+Script&family=Pacifico&family=Pinyon+Script&family=Great+Vibes&family=Satisfy&display=swap');

.prepare-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
}

/* Header Bar */
.header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: white;
  border-bottom: 1px solid rgba(0,0,0,0.08);
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  z-index: 100;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.document-info {
  display: flex;
  flex-direction: column;
}

.document-title {
  font-weight: 600;
  font-size: 14px;
  color: #333;
}

.header-center {
  flex: 1;
  display: flex;
  justify-content: center;
}

.progress-steps {
  display: flex;
  align-items: center;
  gap: 4px;
}

.progress-step {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 16px;
  font-size: 12px;
  color: #666;
  background: #f0f0f0;
  transition: all 0.2s;
}

.progress-step.step-done {
  background: #e8f5e9;
  color: #2e7d32;
}

.step-arrow {
  opacity: 0.4;
  margin: 0 2px;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.submit-btn {
  font-weight: 600;
}

/* Main Content */
.main-content {
  display: flex;
  flex: 1;
  overflow: hidden;
}

/* Sidebars */
.left-sidebar,
.right-sidebar {
  width: 200px;
  flex-shrink: 0;
  background: white;
  border-right: 1px solid rgba(0,0,0,0.06);
  padding: 12px;
  overflow-y: auto;
}

.right-sidebar {
  border-right: none;
  border-left: 1px solid rgba(0,0,0,0.06);
  width: 180px;
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.sidebar-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #666;
}

/* Add Signer Form */
.add-signer-form {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 8px;
}

/* Signers List */
.signers-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.signer-item {
  display: flex;
  align-items: center;
  padding: 8px;
  border-radius: 8px;
  border-left: 3px solid transparent;
  cursor: pointer;
  transition: all 0.15s;
}

.signer-item:hover {
  background: #f5f5f5;
}

.signer-item.signer-selected {
  background: #e3f2fd;
}

.signer-info {
  flex: 1;
  min-width: 0;
}

.signer-name {
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.signer-email {
  font-size: 11px;
  color: #888;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.empty-state {
  text-align: center;
  padding: 24px 8px;
  color: #999;
}

/* Field Types */
.field-types-hint {
  font-size: 11px;
  color: #888;
  margin-bottom: 12px;
  line-height: 1.4;
}

.field-types-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-type-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border-radius: 6px;
  transition: all 0.15s;
}

.field-type-item:not(.disabled):hover {
  background: #e3f2fd;
}

.field-type-item.disabled {
  opacity: 0.4;
}

.field-type-icon {
  color: #1976d2;
}

.field-type-info {
  flex: 1;
}

.field-type-label {
  font-size: 12px;
  font-weight: 500;
}

.field-type-desc {
  font-size: 10px;
  color: #888;
}

/* Summary */
.summary-section {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #888;
  margin-bottom: 4px;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #666;
}

/* PDF Area */
.pdf-area {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
}

.loading-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.pdf-scroll {
  flex: 1;
  overflow: auto;
  padding: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.pdf-page-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.pdf-page {
  background: white;
  border-radius: 4px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.12);
  overflow: hidden;
  position: relative;
}

.page-indicator {
  margin-top: 8px;
  font-size: 11px;
  color: #888;
  background: rgba(255,255,255,0.9);
  padding: 2px 12px;
  border-radius: 10px;
}

/* Field Overlay */
.field-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 10;
}

.field-overlay.draw-cursor {
  cursor: crosshair;
}

.field-box {
  position: absolute;
  border: 2px solid;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.field-box:hover {
  transform: scale(1.02);
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.field-box.field-selected {
  box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.4);
}


.field-overlay.grabbing {
  cursor: grabbing;
}

.field-overlay.resizing {
  cursor: nwse-resize;
}

.field-box.is-interacting {
  transition: none;
  z-index: 100 !important;
}

.field-toolbar {
  position: absolute;
  top: -36px;
  right: -2px;
  display: flex;
  gap: 4px;
  background: white;
  padding: 4px;
  border-radius: 6px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.15);
  z-index: 100;
  animation: fadeIn 0.15s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}

.resize-handle {
  position: absolute;
  bottom: -5px;
  right: -5px;
  width: 12px;
  height: 12px;
  background: white;
  border: 2px solid #1976D2;
  border-radius: 50%;
  cursor: nwse-resize;
  z-index: 20;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  transition: transform 0.1s;
}

.resize-handle:hover {
  background: #1976D2;
  transform: scale(1.2);
}

.drawing-preview {
  position: absolute;
  border: 2px dashed;
  background: rgba(25, 118, 210, 0.1);
  pointer-events: none;
  border-radius: 4px;
}

.font-selection-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 8px;
}

.font-card {
  cursor: pointer;
  transition: all 0.2s ease;
  overflow: hidden;
  text-align: center;
}

.font-card.selected {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.05);
}

/* Hint Overlay */
.hint-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,0.03);
  pointer-events: none;
}

.hint-card {
  pointer-events: auto;
}

/* Responsive */
@media (max-width: 960px) {
  .left-sidebar,
  .right-sidebar {
    display: none;
  }
}

@media (max-width: 600px) {
  .header-bar {
    padding: 6px 12px;
  }
  
  .pdf-scroll {
    padding: 12px;
  }
}
</style>

