import { GlobalWorkerOptions } from 'vue-pdf-embed/dist/index.essential.mjs'
import PdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url'

GlobalWorkerOptions.workerSrc = PdfWorker

