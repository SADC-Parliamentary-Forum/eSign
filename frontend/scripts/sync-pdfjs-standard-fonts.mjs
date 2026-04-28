import fs from 'node:fs/promises'
import path from 'node:path'

const PDFJS_VERSION = '4.10.38'
const outputDir = path.resolve(process.cwd(), 'public', 'pdfjs-standard-fonts')
const files = [
  'FoxitDingbats.pfb',
  'FoxitFixed.pfb',
  'FoxitFixedBold.pfb',
  'FoxitFixedBoldItalic.pfb',
  'FoxitFixedItalic.pfb',
  'FoxitSans.pfb',
  'FoxitSansBold.pfb',
  'FoxitSansBoldItalic.pfb',
  'FoxitSansItalic.pfb',
  'FoxitSerif.pfb',
  'FoxitSerifBold.pfb',
  'FoxitSerifBoldItalic.pfb',
  'FoxitSerifItalic.pfb',
  'FoxitSymbol.pfb',
  'LiberationSans-Regular.ttf',
  'LiberationSans-Bold.ttf',
  'LiberationSans-Italic.ttf',
  'LiberationSans-BoldItalic.ttf',
]

const mirrors = [
  `https://cdn.jsdelivr.net/npm/pdfjs-dist@${PDFJS_VERSION}/standard_fonts`,
  `https://unpkg.com/pdfjs-dist@${PDFJS_VERSION}/standard_fonts`,
]

async function ensureDir(dir) {
  await fs.mkdir(dir, { recursive: true })
}

async function downloadFile(fileName) {
  const targetPath = path.join(outputDir, fileName)

  for (const mirror of mirrors) {
    const sourceUrl = `${mirror}/${fileName}`
    const response = await fetch(sourceUrl)
    if (!response.ok) {
      continue
    }

    const buffer = Buffer.from(await response.arrayBuffer())
    await fs.writeFile(targetPath, buffer)
    return true
  }

  return false
}

async function main() {
  await ensureDir(outputDir)

  for (const fileName of files) {
    const ok = await downloadFile(fileName)
    if (!ok) {
      console.warn(`[sync-pdfjs-standard-fonts] skip ${fileName}: not available on mirrors`)
    }
  }

  // Provide a simple marker for troubleshooting deployments.
  await fs.writeFile(
    path.join(outputDir, '.version'),
    `${PDFJS_VERSION}\n`,
    'utf8',
  )
}

main().catch((error) => {
  console.error('[sync-pdfjs-standard-fonts] failed:', error.message)
  process.exit(1)
})
