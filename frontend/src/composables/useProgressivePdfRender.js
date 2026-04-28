import { computed, onUnmounted, ref } from 'vue'
import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist'
// eslint-disable-next-line import/default
import PdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url'

GlobalWorkerOptions.workerSrc = PdfWorker

const standardFontDataUrl
  = import.meta.env.VITE_PDFJS_STANDARD_FONT_DATA_URL
    || '/pdfjs-standard-fonts/'

export function useProgressivePdfRender() {
  const pdfSource = ref(null)
  const pageCount = ref(0)
  const visiblePageCount = ref(0)
  const renderedPages = ref(0)
  const renderError = ref('')
  const loadingPdf = ref(false)

  let released = false
  let backgroundTimer = null

  const renderProgress = computed(() => {
    if (!pageCount.value) {
      return 0
    }

    return Math.round((renderedPages.value / pageCount.value) * 100)
  })

  const visiblePages = computed(() =>
    Array.from({ length: visiblePageCount.value }, (_, i) => i + 1),
  )

  function cleanupBlob() {
    if (pdfSource.value && pdfSource.value.startsWith('blob:'))
      URL.revokeObjectURL(pdfSource.value)
    pdfSource.value = null
  }

  function stopBackgroundReveal() {
    if (backgroundTimer) {
      clearInterval(backgroundTimer)
      backgroundTimer = null
    }
  }

  function startBackgroundReveal(step = 1, everyMs = 350) {
    stopBackgroundReveal()
    backgroundTimer = setInterval(() => {
      if (released) {
        stopBackgroundReveal()

        return
      }

      if (visiblePageCount.value >= pageCount.value) {
        stopBackgroundReveal()

        return
      }

      visiblePageCount.value = Math.min(pageCount.value, visiblePageCount.value + step)
    }, everyMs)
  }

  async function loadPdfFromResponse(response, options = {}) {
    const { initialVisiblePages = 2 } = options

    cleanupBlob()
    renderError.value = ''
    loadingPdf.value = true
    renderedPages.value = 0
    pageCount.value = 0
    visiblePageCount.value = 0

    try {
      const contentType = response.headers.get('Content-Type') || ''
      if (!response.ok) {
        const errBody = contentType.includes('application/json') ? await response.json().catch(() => ({})) : {}
        const msg = errBody.message || `Failed to load PDF (${response.status})`
        throw new Error(msg)
      }

      const blob = await response.blob()
      if (blob.type && blob.type !== 'application/pdf')
        throw new Error('This document could not be converted to PDF. Please upload a PDF file or try again.')

      const pdfBytes = await blob.arrayBuffer()
      const loadingTask = getDocument({
        data: pdfBytes,
        standardFontDataUrl,
        useSystemFonts: true,
      })
      const pdf = await loadingTask.promise

      const detectedPages = Number(pdf?.numPages) || 0
      if (detectedPages <= 0)
        throw new Error('PDF preview loaded, but page count could not be determined.')

      pageCount.value = detectedPages
      visiblePageCount.value = Math.min(initialVisiblePages, detectedPages)
      pdfSource.value = URL.createObjectURL(blob)
      startBackgroundReveal()
    } catch (err) {
      renderError.value = err?.message || 'Failed to load PDF preview.'
      throw err
    } finally {
      loadingPdf.value = false
    }
  }

  function markPageRendered(page) {
    const number = Number(page)
    if (!Number.isFinite(number) || number <= 0)
      return
    renderedPages.value = Math.max(renderedPages.value, Math.min(number, pageCount.value))
  }

  onUnmounted(() => {
    released = true
    stopBackgroundReveal()
    cleanupBlob()
  })

  return {
    pdfSource,
    pageCount,
    visiblePageCount,
    visiblePages,
    renderedPages,
    renderProgress,
    renderError,
    loadingPdf,
    loadPdfFromResponse,
    markPageRendered,
    stopBackgroundReveal,
    startBackgroundReveal,
    cleanupBlob,
  }
}
