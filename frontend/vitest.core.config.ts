import path from 'node:path'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// The isolated core suite can also run with the cached Vue compiler and the
// original installed test runner. It never substitutes missing app integrations.
const frontend = path.dirname(fileURLToPath(import.meta.url))
const workspace = path.resolve(frontend, '..')
const vueDirectory = path.relative(workspace, frontend)
function resolvePackage(name: string, roots: string[]) {
  for (const root of roots) {
    try { return createRequire(path.join(workspace, root, 'package.json')).resolve(name) }
    catch { /* Try the next existing installation. */ }
  }
  throw new Error(`Missing test dependency: ${name}`)
}
const require = createRequire(import.meta.url)
const { defineConfig } = require(resolvePackage('vitest/config', [vueDirectory, 'frontend']))
const vue = require(resolvePackage('@vitejs/plugin-vue', [vueDirectory, '.local/vue-tools'])).default
const compiler = require(resolvePackage('@vue/compiler-sfc', [vueDirectory, '.local/vue-tools']))
const vitestPackage = resolvePackage('vitest/package.json', [vueDirectory, 'frontend'])
const aliases = ['clsx', 'tailwind-merge', 'class-variance-authority', 'zod', '@tanstack/query-core', 'axios', 'leaflet'].map(name => ({
  find: name, replacement: resolvePackage(name, [vueDirectory, 'frontend']),
}))

export default defineConfig({
  root: frontend,
  plugins: [vue({ compiler })],
  resolve: { alias: [
    { find: '@', replacement: path.join(frontend, 'src') },
    { find: /^vue$/, replacement: resolvePackage('vue/dist/vue.esm-bundler.js', [vueDirectory, '.local/vue-tools']) },
    { find: /^vitest$/, replacement: path.join(path.dirname(vitestPackage), 'dist/index.js') },
    ...aliases,
  ] },
  test: {
    include: ['src/test/core.test.ts', 'src/lib/retire-legacy-worker.test.ts', 'src/services/auth-session.test.ts', 'src/composables/technician-map.test.ts', 'src/lib/form-schemas.test.ts', 'src/services/repair-submission.test.ts'],
    environment: 'jsdom', clearMocks: true, maxWorkers: 1,
  },
})
