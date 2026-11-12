import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'

export function buildFrontend(frontend, environment = process.env) {
  const manifest = JSON.parse(readFileSync(join(frontend, 'package.json'), 'utf8'))
  const vue = Boolean(manifest.dependencies?.vue)
  const react = Boolean(manifest.dependencies?.react)
  if (vue === react) throw new Error('Expected exactly one active frontend framework: Vue or React.')

  // Preserve the single-origin API, session and native CMS deployment contract.
  const env = { ...environment, VITE_API_URL: '/api/v1', VITE_BACKEND_PUBLIC_URL: '' }
  const steps = [
    vue
      ? ['vue-tsc/bin/vue-tsc.js', ['--noEmit', '-p', 'tsconfig.app.json']]
      : ['typescript/bin/tsc', ['-b']],
    ['vite/bin/vite.js', ['build', '--mode', 'production']],
  ]
  for (const [file, args] of steps) {
    const result = spawnSync(process.execPath, [join(frontend, 'node_modules', file), ...args], {
      cwd: frontend, env, stdio: 'inherit', windowsHide: true,
    })
    if (result.error) throw result.error
    if (result.status !== 0) {
      const error = new Error(`Frontend build stopped: ${file} failed with exit code ${result.status ?? 1}.`)
      error.exitCode = result.status || 1
      throw error
    }
  }
}
