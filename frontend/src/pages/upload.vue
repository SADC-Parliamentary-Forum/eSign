<script setup>
import { useRouter } from 'vue-router'
import { ref } from 'vue'

const router = useRouter()
const isDragging = ref(false)
const fileInput = ref(null)
const isSelfSign = ref(false)
const isUploading = ref(false)

function onFileChange(eventOrFiles) {
    let file = null
    if (Array.isArray(eventOrFiles)) {
        file = eventOrFiles[0]
    } else if (eventOrFiles?.target?.files?.[0]) {
        file = eventOrFiles.target.files[0]
    }

    if (!file) return

    // For single file, we proceed immediately to preparation or create a draft
    uploadSingleFile(file)
}

function onDrop(e) {
    isDragging.value = false
    const files = Array.from(e.dataTransfer.files)
    if(files.length) onFileChange(files)
}

async function uploadSingleFile(file) {
    if (isUploading.value) return
    
    isUploading.value = true
    try {
        const formData = new FormData()
        formData.append('file', file)
        formData.append('title', file.name.replace(/\.[^/.]+$/, ''))
        formData.append('is_self_sign', isSelfSign.value ? '1' : '0')
        
        const res = await $api('/documents', { method: 'POST', body: formData })
        
        // Go straight to prepare
        router.push(`/prepare/${res.id}`)
    } catch(e) {
        console.error(e)
        // Ideally show toast
    } finally {
        isUploading.value = false
    }
}
</script>

<template>
  <VContainer class="fill-height align-center justify-center py-12">
    <VRow justify="center">
      <VCol cols="12" md="8" lg="6">
        
        <div class="text-center mb-10">
            <h1 class="text-h3 font-weight-bold mb-4">New Document</h1>
            <p class="text-h6 text-medium-emphasis font-weight-regular">
                Get started by uploading a file or choosing a template
            </p>
        </div>

        <!-- HERO UPLOAD -->
        <VCard 
            elevation="2" 
            class="rounded-xl overflow-hidden mb-8 transition-swing"
            :class="{ 'ring-primary': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <div class="d-flex flex-column align-center justify-center py-12 px-6 bg-surface" @click="fileInput.click()" style="cursor: pointer">
                <input type="file" ref="fileInput" hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" @change="onFileChange" />
                
                <div class="icon-circle mb-6 pa-6 rounded-circle">
                    <VIcon icon="ri-upload-cloud-2-line" size="48" color="primary" />
                </div>
                
                <h2 class="text-h5 font-weight-bold mb-2">Upload a Document</h2>
                <p class="text-body-1 text-medium-emphasis mb-6 text-center" style="max-width: 300px">
                    Drag and drop your PDF or Word document here, or click to browse
                </p>
                
                <VBtn color="primary" size="large" rounded="pill" variant="flat" class="px-8" @click.stop="fileInput.click()">
                    Choose File
                </VBtn>
            </div>
            
            <VDivider />
            
            <!-- INTENTION SELECTION - Premium Card Design -->
            <div class="pa-4 bg-grey-lighten-5">
                <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-3 text-center">
                    What would you like to do?
                </div>
                <div class="d-flex gap-3 justify-center flex-wrap" @click.stop>
                    <!-- Option 1: Prepare for Others -->
                    <div 
                        class="option-card pa-4 rounded-lg text-center flex-grow-1"
                        :class="{ 'option-selected': !isSelfSign }"
                        @click="isSelfSign = false"
                        style="max-width: 180px; min-width: 150px; cursor: pointer;"
                    >
                        <VIcon 
                            icon="ri-group-line" 
                            size="32" 
                            :color="!isSelfSign ? 'primary' : 'grey'" 
                            class="mb-2"
                        />
                        <div class="text-subtitle-2 font-weight-bold">Prepare for Others</div>
                        <div class="text-caption text-medium-emphasis">Add recipients & fields</div>
                    </div>
                    
                    <!-- Option 2: Sign Myself -->
                    <div 
                        class="option-card pa-4 rounded-lg text-center flex-grow-1"
                        :class="{ 'option-selected': isSelfSign }"
                        @click="isSelfSign = true"
                        style="max-width: 180px; min-width: 150px; cursor: pointer;"
                    >
                        <VIcon 
                            icon="ri-quill-pen-line" 
                            size="32" 
                            :color="isSelfSign ? 'primary' : 'grey'" 
                            class="mb-2"
                        />
                        <div class="text-subtitle-2 font-weight-bold">Sign Myself</div>
                        <div class="text-caption text-medium-emphasis">Quick self-signature</div>
                    </div>
                </div>
            </div>
            
            <VDivider />
            
            <div class="pa-3 text-center text-caption text-medium-emphasis">
                Supported formats: PDF, DOCX, DOC, XLSX, XLS, PNG, JPG • Max size: 25MB
            </div>

            <VOverlay
                v-model="isUploading"
                contained
                class="align-center justify-center"
                persistent
            >
                <div class="text-center bg-surface pa-6 rounded-xl elevation-10">
                    <VProgressCircular indeterminate color="primary" size="48" class="mb-4" />
                    <h3 class="text-h6 font-weight-bold">Uploading...</h3>
                    <p class="text-caption text-medium-emphasis">Please wait while we process your file</p>
                </div>
            </VOverlay>
        </VCard>

        <!-- SECONDARY ACTIONS -->
        <VRow>
            <VCol cols="12" sm="6">
                <VCard variant="outlined" class="h-100 rounded-lg" hover link to="/templates">
                    <VCardText class="d-flex align-center">
                        <VAvatar color="secondary" variant="tonal" rounded class="mr-4">
                            <VIcon icon="ri-layout-masonry-line" />
                        </VAvatar>
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">Use a Template</div>
                            <div class="text-caption text-medium-emphasis">Start from a pre-saved layout</div>
                        </div>
                    </VCardText>
                </VCard>
            </VCol>
            
            <VCol cols="12" sm="6">
                <VCard variant="outlined" class="h-100 rounded-lg" hover link to="/bulk-sign">
                    <VCardText class="d-flex align-center">
                        <VAvatar color="info" variant="tonal" rounded class="mr-4">
                            <VIcon icon="ri-stack-line" />
                        </VAvatar>
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">Bulk Automation</div>
                            <div class="text-caption text-medium-emphasis">Sign multiple files at once</div>
                        </div>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>

      </VCol>
    </VRow>
  </VContainer>
</template>

<style scoped>
.ring-primary {
    box-shadow: 0 0 0 3px rgb(var(--v-theme-primary), 0.5) !important;
    transform: scale(1.01);
}
.icon-circle {
    background-color: rgb(var(--v-theme-primary), 0.08);
}

/* Option Card Styling */
.option-card {
    border: 2px solid rgba(var(--v-theme-on-surface), 0.08);
    background: white;
    transition: all 0.2s ease;
}
.option-card:hover {
    border-color: rgba(var(--v-theme-primary), 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.option-card.option-selected {
    border-color: rgb(var(--v-theme-primary));
    background: rgb(var(--v-theme-primary), 0.04);
}
</style>
