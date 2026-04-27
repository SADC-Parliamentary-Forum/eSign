import { ref } from 'vue'

const PROCESSING_LABELS = {
  queued: 'Queued for processing...',
  converting: 'Converting document...',
  analyzing: 'Analyzing document...',
  uploading: 'Uploading converted PDF...',
  finalizing: 'Finalizing document...',
  ready: 'Document ready.',
  failed: 'Document processing failed.',
}

function formatElapsedLabel(baseLabel, elapsedMs) {
  const elapsedSeconds = Math.max(0, Math.floor(elapsedMs / 1000))
  if (elapsedSeconds < 10)
    return baseLabel

  return `${baseLabel} (${elapsedSeconds}s elapsed)`
}

export function useDocumentProcessingProgress() {
  const processingProgress = ref(0)
  const processingStage = ref('queued')
  const processingLabel = ref(PROCESSING_LABELS.queued)
  const processingError = ref('')

  function applyDocumentState(documentState = {}) {
    const stage = documentState.processing_stage || 'queued'

    const progress = Number.isFinite(Number(documentState.processing_progress))
      ? Number(documentState.processing_progress)
      : 0

    processingStage.value = stage
    processingProgress.value = Math.max(0, Math.min(100, progress))
    processingLabel.value = PROCESSING_LABELS[stage] || 'Processing document...'
    processingError.value = documentState.processing_error || ''
  }

  async function waitForReadyDocument(loadDocument, options = {}) {
    const {
      intervalMs = 2000,
      maxWaitMs = 180000,
      isReady = doc => doc?.status === 'DRAFT' && !!doc?.pdf_url,
      isFailed = doc => doc?.status === 'FAILED',
    } = options

    const startedAt = Date.now()

    while (Date.now() - startedAt < maxWaitMs) {
      const next = await loadDocument()

      applyDocumentState(next)

      const baseLabel = PROCESSING_LABELS[processingStage.value] || 'Processing document...'
      processingLabel.value = formatElapsedLabel(baseLabel, Date.now() - startedAt)

      if (isFailed(next)) {
        const message = next?.processing_error || 'Document conversion failed. Please try uploading again.'
        throw new Error(message)
      }

      if (isReady(next)) {
        return next
      }

      await new Promise(resolve => setTimeout(resolve, intervalMs))
    }

    const currentStage = processingStage.value || 'queued'
    throw new Error(`Document is still processing at stage "${currentStage}". Please try again shortly.`)
  }

  return {
    processingProgress,
    processingStage,
    processingLabel,
    processingError,
    applyDocumentState,
    waitForReadyDocument,
  }
}
