import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { verifyArtifact } from './lib/release-artifact.mjs'

try {
  const root = process.argv[2] ? resolve(process.argv[2]) : fileURLToPath(new URL('../', import.meta.url))
  const result = await verifyArtifact(root)
  console.log(`Release integrity passed: ${result.files} files, ${result.bytes} bytes. Checksums detect changes; they are not a publisher signature.`)
} catch (error) {
  console.error(`Release integrity failed: ${error.message}`)
  process.exitCode = 1
}
