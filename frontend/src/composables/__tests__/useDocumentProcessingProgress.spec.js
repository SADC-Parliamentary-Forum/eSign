import { describe, expect, it, vi } from 'vitest'
import { useDocumentProcessingProgress } from '../useDocumentProcessingProgress'

describe('useDocumentProcessingProgress', () => {
  it('returns document immediately when ready', async () => {
    const { waitForReadyDocument, processingStage } = useDocumentProcessingProgress()
    const readyDoc = { status: 'DRAFT', pdf_url: '/api/documents/1/pdf', processing_stage: 'ready' }

    const result = await waitForReadyDocument(async () => readyDoc)

    expect(result).toEqual(readyDoc)
    expect(processingStage.value).toBe('ready')
  })

  it('throws immediately when backend marks document as failed', async () => {
    const { waitForReadyDocument } = useDocumentProcessingProgress()
    const failedDoc = {
      status: 'FAILED',
      processing_stage: 'failed',
      processing_error: 'Conversion failed for uploaded file',
    }

    await expect(waitForReadyDocument(async () => failedDoc))
      .rejects
      .toThrow('Conversion failed for uploaded file')
  })

  it('includes stage context in timeout message', async () => {
    vi.useFakeTimers()
    const { waitForReadyDocument } = useDocumentProcessingProgress()

    const processingDoc = {
      status: 'DRAFT',
      pdf_url: null,
      processing_stage: 'converting',
      processing_progress: 15,
    }

    const promise = waitForReadyDocument(
      async () => processingDoc,
      { intervalMs: 1000, maxWaitMs: 2500 },
    )
    const rejectionExpectation = expect(promise)
      .rejects
      .toThrow('Document is still processing at stage "converting". Please try again shortly.')

    await vi.advanceTimersByTimeAsync(3000)
    await rejectionExpectation

    vi.useRealTimers()
  })
})

