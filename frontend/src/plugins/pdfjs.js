import { GlobalWorkerOptions } from 'vue-pdf-embed/dist/index.essential.mjs'
import PdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url'

// Configure worker on the same pdf.js instance used by vue-pdf-embed.
GlobalWorkerOptions.workerSrc = PdfWorker

