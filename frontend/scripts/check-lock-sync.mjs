import { readFileSync } from 'node:fs'

const packageJson = JSON.parse(readFileSync(new URL('../package.json', import.meta.url), 'utf8'))
const packageLock = JSON.parse(readFileSync(new URL('../package-lock.json', import.meta.url), 'utf8'))
const lockRoot = packageLock.packages?.[''] ?? {}

const sections = ['dependencies', 'devDependencies', 'optionalDependencies']
const mismatches = []

for (const section of sections) {
  const packageEntries = packageJson[section] ?? {}
  const lockEntries = lockRoot[section] ?? {}

  for (const [name, version] of Object.entries(packageEntries)) {
    if (lockEntries[name] !== version) {
      mismatches.push(
        `${section}.${name}: package.json=${JSON.stringify(version)} package-lock.json=${JSON.stringify(lockEntries[name] ?? null)}`,
      )
    }
  }

  for (const name of Object.keys(lockEntries)) {
    if (!(name in packageEntries)) {
      mismatches.push(`${section}.${name}: package.json=null package-lock.json=${JSON.stringify(lockEntries[name])}`)
    }
  }
}

if (mismatches.length > 0) {
  console.error('frontend/package.json and frontend/package-lock.json are out of sync.')
  console.error('Regenerate the lock file from frontend/ with `npm install` and commit both files together.')

  for (const mismatch of mismatches) {
    console.error(`- ${mismatch}`)
  }

  process.exit(1)
}

console.log('Frontend lockfile matches package.json.')
