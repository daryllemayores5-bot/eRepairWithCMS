import { fileURLToPath } from 'node:url'
import { buildFrontend } from '../../scripts/lib/frontend-build.mjs'

const frontend = fileURLToPath(new URL('../', import.meta.url))
try {
  buildFrontend(frontend)
} catch (error) {
  console.error(error.message)
  process.exitCode = error.exitCode || 1
}
