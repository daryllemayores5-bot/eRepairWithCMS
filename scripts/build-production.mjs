import { fileURLToPath } from 'node:url'
import { buildFrontend } from './lib/frontend-build.mjs'

const frontend = fileURLToPath(new URL('../frontend/', import.meta.url))
try {
  buildFrontend(frontend)
} catch (error) {
  console.error(error.message)
  process.exitCode = error.exitCode || 1
}
