<script setup>
import { useRouter } from 'vue-router'
import { ref, computed, onMounted } from 'vue'
import { useAIStore } from '@/stores/ai'
import { useTemplateStore } from '@/stores/templates'
import SignatureLevelSelector from '@/components/signatures/SignatureLevelSelector.vue'

const router = useRouter()
const aiStore = useAIStore()
const templates = ref([])

// Stepper State
const currentStep = ref(1)
const steps = [
    { title: 'Upload', icon: 'ri-upload-cloud-2-line' },
    { title: 'Configure', icon: 'ri-settings-3-line' },
    { title: 'Process', icon: 'ri-play-circle-line' }
]

// State
const fileQueue = ref([])
const signatureLevel = ref('SIMPLE')
const isDragging = ref(false)
const processing = ref(false)
const fileInput = ref(null)

// Load Templates
onMounted(async () => {
    try {
        const res = await $api('/templates')
        templates.value = Array.isArray(res) ? res : (res.data || [])
    } catch(e) { console.error(e) }
})

// --- Step 1: Upload ---
function onFileChange(eventOrFiles) {
    let newFiles = []
    if (Array.isArray(eventOrFiles)) {
        newFiles = eventOrFiles
    } else if (eventOrFiles?.target?.files) {
        newFiles = Array.from(eventOrFiles.target.files)
    }
    if (!newFiles.length) return

    const startId = fileQueue.value.length
    const newItems = newFiles.map((f, i) => ({
        id: startId + i,
        file: f,
        title: f.name.replace(/\.[^/.]+$/, ''),
        templateId: null,
        status: 'pending',
        progress: 0,
        error: null,
        aiMatch: null
    }))
    fileQueue.value = [...fileQueue.value, ...newItems]
}

function removeFile(index) {
    fileQueue.value.splice(index, 1)
}

function onDrop(e) {
    isDragging.value = false
    const droppedFiles = Array.from(e.dataTransfer.files)
    if(droppedFiles.length) onFileChange(droppedFiles)
}

// --- Step 2: Configure ---
async function autoMatchTemplates() {
    for (const item of fileQueue.value) {
        if (item.templateId) continue
        item.status = 'analyzing'
        try {
            await aiStore.suggestTemplates(item.file)
            if (aiStore.hasSuggestions && aiStore.suggestions.length > 0) {
                const best = aiStore.suggestions[0]
                if (best.confidence > 0.8) {
                    item.templateId = best.template.id
                    item.aiMatch = best
                }
            }
        } catch(e) { console.error(e) }
        finally { item.status = 'pending' }
    }
}

const canProceedToProcess = computed(() => {
    return fileQueue.value.length > 0 && fileQueue.value.every(f => f.templateId)
})

// --- Step 3: Process ---
async function startBulkProcess() {
    processing.value = true
    
    let successCount = 0
    
    for (const item of fileQueue.value) {
        if (['done', 'signed'].includes(item.status)) continue
        
        item.status = 'uploading'
        item.progress = 20
        
        try {
             // 1. Upload
             const formData = new FormData()
             formData.append('file', item.file)
             formData.append('title', item.title)
             formData.append('signature_level', signatureLevel.value)
             formData.append('is_self_sign', '1') // Bulk Sign implies self-sign for now
             if (item.templateId) formData.append('template_id', item.templateId)
             
             const res = await $api('/documents', { method: 'POST', body: formData })
             item.progress = 60
             item.status = 'signing'
             
             // 2. Sign
             await $api(`/documents/${res.id}/sign-self`, { method: 'POST' })
             
             item.status = 'signed'
             item.progress = 100
             successCount++
        } catch (e) {
            item.status = 'error'
            item.error = e.message
            item.progress = 0
        }
    }
    
    processing.value = false
}

const completedCount = computed(() => fileQueue.value.filter(i => i.status === 'signed').length)
const isAllDone = computed(() => fileQueue.value.length > 0 && completedCount.value === fileQueue.value.length)

</script>

<template>
<VContainer class="fill-height align-start py-8">
    <VRow justify="center">
        <VCol cols="12" md="10" lg="8">
            <div class="mb-6 d-flex align-center justify-space-between">
                <div>
                    <h1 class="text-h4 font-weight-bold">Bulk Automation</h1>
                    <p class="text-medium-emphasis">Batch sign multiple documents at once</p>
                </div>
                <VBtn variant="text" prepend-icon="ri-arrow-left-line" to="/upload">Back to Upload</VBtn>
            </div>

            <VStepper v-model="currentStep" :items="steps" hide-actions class="bg-transparent elevation-0 mb-6">
                <template v-slot:item.1>
                    <VCard elevation="1" border class="rounded-lg">
                        <VCardText class="pa-8 text-center">
                             <div 
                                class="dropzone d-flex flex-column align-center justify-center rounded-lg border-dashed py-12 transition-swing mb-6"
                                :class="{ 'bg-blue-lighten-5 border-primary': isDragging }"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="onDrop"
                                @click="fileInput.click()"
                            >
                                <input type="file" ref="fileInput" multiple hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" @change="onFileChange" />
                                <VIcon icon="ri-stack-line" size="64" color="primary" class="mb-4" />
                                <h3 class="text-h6 font-weight-regular mb-1">Drop multiple files here</h3>
                                <p class="text-body-2 text-medium-emphasis">PDF, DOCX, DOC, XLSX, XLS, PNG, JPG supported</p>
                            </div>
                            
                            <div v-if="fileQueue.length > 0">
                                <VChip color="primary" class="mb-4">{{ fileQueue.length }} files selected</VChip>
                            </div>
                            
                            <VBtn 
                                color="primary" 
                                size="large" 
                                :disabled="fileQueue.length === 0"
                                @click="currentStep = 2"
                            >
                                Continue to Configuration
                                <VIcon end icon="ri-arrow-right-line"/>
                            </VBtn>
                        </VCardText>
                    </VCard>
                </template>

                <template v-slot:item.2>
                    <VCard elevation="1" border class="rounded-lg">
                        <VCardTitle class="pa-4 d-flex justify-space-between align-center border-bottom">
                            <span>Configure & Match</span>
                            <div class="d-flex align-center">
                                <span class="text-caption mr-2 text-medium-emphasis">Security:</span>
                                <SignatureLevelSelector v-model="signatureLevel" density="compact" hide-details variant="plain" />
                            </div>
                        </VCardTitle>
                        <VCardText class="pa-0">
                            <VAlert type="info" variant="tonal" class="ma-4" density="compact">
                                All documents need a matching template to be auto-signed.
                                <template #append>
                                    <VBtn size="small" variant="text" color="info" @click="autoMatchTemplates" prepend-icon="ri-magic-line">Auto-Match</VBtn>
                                </template>
                            </VAlert>

                            <VList lines="two" class="bg-transparent">
                                <VListItem v-for="(item, i) in fileQueue" :key="i" class="border-bottom">
                                    <template #prepend>
                                        <VAvatar color="primary" variant="tonal" rounded>
                                            <span class="text-caption font-weight-bold">{{ i + 1 }}</span>
                                        </VAvatar>
                                    </template>
                                    <VListItemTitle>{{ item.title }}</VListItemTitle>
                                    <VListItemSubtitle>{{ (item.file.size/1024/1024).toFixed(2) }} MB</VListItemSubtitle>
                                    
                                    <template #append>
                                        <div style="width: 200px" class="d-flex align-center gap-2">
                                            <VSelect
                                                v-model="item.templateId"
                                                :items="templates"
                                                item-title="name"
                                                item-value="id"
                                                density="compact"
                                                variant="outlined"
                                                hide-details
                                                placeholder="Select Template"
                                            />
                                            <VBtn icon="ri-close-line" variant="text" size="small" color="medium-emphasis" @click="removeFile(i)" />
                                        </div>
                                    </template>
                                </VListItem>
                            </VList>
                        </VCardText>
                        <VCardActions class="pa-4 bg-grey-lighten-5">
                            <VBtn variant="text" @click="currentStep = 1">Back</VBtn>
                            <VSpacer />
                            <VBtn 
                                color="primary" 
                                @click="currentStep = 3" 
                                :disabled="!canProceedToProcess"
                            >
                                Review & Process
                            </VBtn>
                        </VCardActions>
                    </VCard>
                </template>

                <template v-slot:item.3>
                     <VCard elevation="1" border class="rounded-lg">
                        <VCardText class="pa-8 text-center" v-if="!processing && !isAllDone">
                             <VIcon icon="ri-shield-check-line" size="64" color="primary" class="mb-4" />
                             <h2 class="text-h5 font-weight-bold mb-2">Ready to Sign {{ fileQueue.length }} Documents</h2>
                             <p class="text-body-1 text-medium-emphasis mb-8">
                                You are about to apply your signature to all selected documents using 
                                <span class="font-weight-bold text-primary">{{ signatureLevel }}</span> level security.
                             </p>
                             <VBtn size="x-large" color="primary" @click="startBulkProcess" prepend-icon="ri-quill-pen-line">
                                Sign All Documents
                             </VBtn>
                        </VCardText>

                        <div v-else class="pa-6">
                            <div class="mb-4 d-flex justify-space-between align-center">
                                <span class="text-h6">Processing...</span>
                                <span class="text-caption">{{ completedCount }} / {{ fileQueue.length }}</span>
                            </div>
                             <VProgressLinear :model-value="(completedCount / fileQueue.length) * 100" color="primary" height="8" rounded class="mb-6" />

                             <VList density="compact" class="border rounded-lg mb-4" style="max-height: 300px; overflow-y: auto">
                                <VListItem v-for="(item, i) in fileQueue" :key="i">
                                    <template #prepend>
                                        <VIcon 
                                            :icon="item.status === 'signed' ? 'ri-check-circle-fill' : (item.status === 'error' ? 'ri-error-warning-fill' : 'ri-loader-4-line')" 
                                            :color="item.status === 'signed' ? 'success' : (item.status === 'error' ? 'error' : 'info')"
                                            :class="{'spin': item.status === 'signing' || item.status === 'uploading'}"
                                        />
                                    </template>
                                    <VListItemTitle>{{ item.title }}</VListItemTitle>
                                    <template #append>
                                        <span class="text-caption text-uppercase" :class="'text-' + (item.status === 'signed' ? 'success' : 'medium-emphasis')">
                                            {{ item.status }}
                                        </span>
                                    </template>
                                </VListItem>
                             </VList>

                             <div v-if="isAllDone" class="d-flex justify-center mt-6">
                                <VBtn color="success" size="large" to="/?status=COMPLETED" prepend-icon="ri-check-line">
                                    Done
                                </VBtn>
                             </div>
                        </div>
                     </VCard>
                </template>
            </VStepper>
        </VCol>
    </VRow>
</VContainer>
</template>

<style scoped>
.dropzone {
    border: 2px dashed rgba(var(--v-theme-on-surface), 0.12);
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
.dropzone:hover {
    border-color: rgb(var(--v-theme-primary));
    background-color: rgb(var(--v-theme-primary), 0.04);
}
.border-primary { border-color: rgb(var(--v-theme-primary)) !important; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>
