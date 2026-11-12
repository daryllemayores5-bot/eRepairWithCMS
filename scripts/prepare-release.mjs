import { mkdir } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { join } from 'node:path'
import { buildArtifact, verifyArtifact } from './lib/release-artifact.mjs'

const workspace = fileURLToPath(new URL('../', import.meta.url))
const releases = join(workspace, '.local/releases')
await mkdir(releases, { recursive: true })
const destination = join(releases, `erepair-${new Date().toISOString().replace(/[:.]/g, '-')}`)
try {
  await buildArtifact(workspace, destination)
  const result = await verifyArtifact(destination)
  console.log(`Release package verified: ${destination}\n${result.files} files, ${result.bytes} bytes.\nFollow deployment/INSTALL.md. Production configuration and browser acceptance remain required.`)
} catch (error) {
  console.error(`Release preparation failed: ${error.message}\nIncomplete output, if any, is preserved for inspection: ${destination}`)
  process.exitCode = 1
}
