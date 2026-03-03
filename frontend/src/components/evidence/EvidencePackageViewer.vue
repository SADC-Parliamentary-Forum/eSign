<script setup>
import { $api } from '@/utils/api'
import { config } from '@/config'
import TrustScoreIndicator from '@/components/common/TrustScoreIndicator.vue'

const apiBaseUrl = config.api.baseUrl || import.meta.env.VITE_API_URL || '/api'

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
      `${apiBaseUrl}/evidence/documents/${props.documentId}/download`,
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
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon
        icon="mdi-file-certificate"
        class="mr-2"
      />
      Evidence Package
    </VCardTitle>

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="article"
      />

      <div v-else-if="evidenceInfo">
        <!-- Trust Score Display -->
        <div class="d-flex justify-center mb-4">
          <TrustScoreIndicator
            :score="evidenceInfo.trust_score || 0"
            :breakdown="evidenceInfo.trust_breakdown"
            :size="140"
            show-details
          />
        </div>

        <VDivider class="my-4" />

        <!-- Evidence Package Info -->
        <div v-if="evidenceInfo.evidence_package?.exists">
          <VAlert
            type="success"
            variant="tonal"
            class="mb-3"
          >
            <VIcon
              icon="mdi-check-circle"
              class="mr-2"
            />
            Evidence package available
          </VAlert>

          <VList density="compact">
            <VListItem>
              <template #prepend>
                <VIcon icon="mdi-calendar" />
              </template>
              <VListItemTitle>Generated</VListItemTitle>
              <VListItemSubtitle>
                {{ new Date(evidenceInfo.evidence_package.generated_at).toLocaleString() }}
              </VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon icon="mdi-file-pdf-box" />
              </template>
              <VListItemTitle>Format</VListItemTitle>
              <VListItemSubtitle>PDF/A-3 (Long-term archival)</VListItemSubtitle>
            </VListItem>

            <VListItem>
              <template #prepend>
                <VIcon icon="mdi-shield-check" />
              </template>
              <VListItemTitle>Contents</VListItemTitle>
              <VListItemSubtitle>
                Complete audit trail, signatures, verifications, certificates
              </VListItemSubtitle>
            </VListItem>
          </VList>

          <VBtn
            v-if="canDownload"
            color="primary"
            block
            class="mt-4"
            :loading="downloading"
            prepend-icon="mdi-download"
            @click="downloadEvidence"
          >
            Download Evidence Package
          </VBtn>
        </div>

        <!-- No Evidence Package -->
        <div v-else>
          <VAlert
            type="info"
            variant="tonal"
            class="mb-3"
          >
            Evidence package not yet generated
          </VAlert>

          <VList
            density="compact"
            class="mb-4"
          >
            <VListSubheader>Package will include:</VListSubheader>
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="mdi-check"
                  color="success"
                  size="small"
                />
              </template>
              <VListItemTitle class="text-body-2">
                Document summary
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="mdi-check"
                  color="success"
                  size="small"
                />
              </template>
              <VListItemTitle class="text-body-2">
                All signature details
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="mdi-check"
                  color="success"
                  size="small"
                />
              </template>
              <VListItemTitle class="text-body-2">
                Identity verifications
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="mdi-check"
                  color="success"
                  size="small"
                />
              </template>
              <VListItemTitle class="text-body-2">
                Certificate chain
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="mdi-check"
                  color="success"
                  size="small"
                />
              </template>
              <VListItemTitle class="text-body-2">
                Hash verification
              </VListItemTitle>
            </VListItem>
            <VListItem>
              <template #prepend>
                <VIcon
                  icon="mdi-check"
                  color="success"
                  size="small"
                />
              </template>
              <VListItemTitle class="text-body-2">
                Complete audit trail
              </VListItemTitle>
            </VListItem>
          </VList>

          <VBtn
            v-if="evidenceInfo.document?.status === 'COMPLETED'"
            color="success"
            block
            :loading="loading"
            prepend-icon="mdi-file-certificate"
            @click="generateEvidence"
          >
            Generate Evidence Package
          </VBtn>

          <VAlert
            v-else
            type="warning"
            variant="tonal"
            density="compact"
          >
            Evidence package can only be generated for completed documents
          </VAlert>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
