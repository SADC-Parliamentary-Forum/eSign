import { GlobalWorkerOptions as LegacyGlobalWorkerOptions } from 'pdfjs-dist/legacy/build/pdf'
import LegacyPdfWorker from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url'
import { GlobalWorkerOptions as ModernGlobalWorkerOptions } from 'pdfjs-dist'
import ModernPdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url'

// vue-pdf-embed uses the legacy pdf.js build internally, so configure that worker.
LegacyGlobalWorkerOptions.workerSrc = LegacyPdfWorker

// Keep modern build configured as well for any direct pdf.js imports.
ModernGlobalWorkerOptions.workerSrc = ModernPdfWorker

