<script setup>
import { $api } from '@/utils/api'
import TrustScoreIndicator from '@/components/common/TrustScoreIndicator.vue'

const props = defineProps({
  documentId: {
    type: [String, Number],
    required: true,
  },
  canDownload: {
    type: Boolean,
    default: true,
  },
})

const loading = ref(false)
const evidenceInfo = ref(null)
const downloading = ref(false)

onMounted(async () => {
  await loadEvidenceInfo()
})

async function loadEvidenceInfo() {
  loading.value = true
  try {
    const response = await $api(`/evidence/documents/${props.documentId}`)
    evidenceInfo.value = response
  }
  catch (error) {
    console.error('Failed to load evidence info:', error)
  }
  finally {
    loading.value = false
  }
}

async function generateEvidence() {
  loading.value = true
  try {
    await $api(`/evidence/documents/${props.documentId}/generate`, {
      method: 'POST',
    })
    await loadEvidenceInfo()
  }
  catch (error) {
    console.error('Failed to generate evidence package:', error)
  }
  finally {
    loading.value = false
  }
}

async function downloadEvidence() {
  downloading.value = true
  try {
    const response = await fetch(
      `${import.meta.env.VITE_API_URL}/evidence/documents/${props.documentId}/download`,
      {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
      },
    )

    if (!response.ok) throw new Error('Download failed')

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `Evidence_Package_${props.documentId}.pdf`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
  }
  catch (error) {
    console.error('Failed to download evidence package:', error)
  }
  finally {
    downloading.value = false
  }
}
</script>

<template>
  <v-card>
    <v-card-title class="d-flex align-center">
      <v-icon icon="mdi-file-certificate" class="mr-2" />
      Evidence Package
    </v-card-title>

    <v-card-text>
      <v-skeleton-loader v-if="loading" type="article" />

      <div v-else-if="evidenceInfo">
        <!-- Trust Score Display -->
        <div class="d-flex justify-center mb-4">
          <trust-score-indicator
            :score="evidenceInfo.trust_score || 0"
            :breakdown="evidenceInfo.trust_breakdown"
            :size="140"
            show-details
          />
        </div>

        <v-divider class="my-4" />

        <!-- Evidence Package Info -->
        <div v-if="evidenceInfo.evidence_package?.exists">
          <v-alert type="success" variant="tonal" class="mb-3">
            <v-icon icon="mdi-check-circle" class="mr-2" />
            Evidence package available
          </v-alert>

          <v-list density="compact">
            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-calendar" />
              </template>
              <v-list-item-title>Generated</v-list-item-title>
              <v-list-item-subtitle>
                {{ new Date(evidenceInfo.evidence_package.generated_at).toLocaleString() }}
              </v-list-item-subtitle>
            </v-list-item>

            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-file-pdf-box" />
              </template>
              <v-list-item-title>Format</v-list-item-title>
              <v-list-item-subtitle>PDF/A-3 (Long-term archival)</v-list-item-subtitle>
            </v-list-item>

            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-shield-check" />
              </template>
              <v-list-item-title>Contents</v-list-item-title>
              <v-list-item-subtitle>
                Complete audit trail, signatures, verifications, certificates
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>

          <v-btn
            v-if="canDownload"
            color="primary"
            block
            class="mt-4"
            :loading="downloading"
            prepend-icon="mdi-download"
            @click="downloadEvidence"
          >
            Download Evidence Package
          </v-btn>
        </div>

        <!-- No Evidence Package -->
        <div v-else>
          <v-alert type="info" variant="tonal" class="mb-3">
            Evidence package not yet generated
          </v-alert>

          <v-list density="compact" class="mb-4">
            <v-list-subheader>Package will include:</v-list-subheader>
            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2">Document summary</v-list-item-title>
            </v-list-item>
            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2">All signature details</v-list-item-title>
            </v-list-item>
            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2">Identity verifications</v-list-item-title>
            </v-list-item>
            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2">Certificate chain</v-list-item-title>
            </v-list-item>
            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2">Hash verification</v-list-item-title>
            </v-list-item>
            <v-list-item>
              <template #prepend>
                <v-icon icon="mdi-check" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2">Complete audit trail</v-list-item-title>
            </v-list-item>
          </v-list>

          <v-btn
            v-if="evidenceInfo.document?.status === 'COMPLETED'"
            color="success"
            block
            :loading="loading"
            prepend-icon="mdi-file-certificate"
            @click="generateEvidence"
          >
            Generate Evidence Package
          </v-btn>

          <v-alert v-else type="warning" variant="tonal" density="compact">
            Evidence package can only be generated for completed documents
          </v-alert>
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>
