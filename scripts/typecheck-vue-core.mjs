import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { findVueFrontend } from './lib/vue-workspace.mjs'

const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const frontend = findVueFrontend(workspace)
const vueDirectory = path.relative(workspace, frontend)
function packagePath(name, roots) {
  for (const root of roots) {
    const direct = path.join(workspace, root, 'node_modules', name, 'package.json')
    if (fs.existsSync(direct)) return direct
    try { return createRequire(path.join(workspace, root, 'package.json')).resolve(`${name}/package.json`) }
    catch { /* Prefer the staged installation; otherwise use existing packages. */ }
  }
  throw new Error(`Missing type-check dependency: ${name}`)
}

const paths = { '@/*': [path.join(frontend, 'src/*')] }
for (const [name, roots] of [
  ['vue', [vueDirectory, '.local/vue-tools']],
  ['axios', [vueDirectory, 'frontend']],
  ['zod', [vueDirectory, 'frontend']],
  ['clsx', [vueDirectory, 'frontend']],
  ['tailwind-merge', [vueDirectory, 'frontend']],
  ['@tanstack/query-core', [vueDirectory, 'frontend']],
]) {
  const file = packagePath(name, roots)
  const manifest = JSON.parse(fs.readFileSync(file, 'utf8'))
  paths[name] = [path.resolve(path.dirname(file), manifest.types || manifest.typings)]
}
paths.leaflet = [path.join(path.dirname(packagePath('@types/leaflet', [vueDirectory, 'frontend'])), 'index.d.ts')]

const modules = [
  'services/auth-session.ts', 'services/api.ts', 'composables/form.ts',
  'composables/technician-map.ts', 'lib/session-cache.ts', 'lib/initial-fields.ts',
  'lib/map.ts', 'lib/repair-request.ts', 'lib/utils.ts', 'lib/errors.ts',
  'lib/format.ts', 'types/index.ts', 'lib/form-schemas.ts', 'services/repair-submission.ts',
]
const config = {
  compilerOptions: {
    target: 'ES2022', module: 'ESNext', moduleResolution: 'Bundler',
    lib: ['ES2022', 'DOM', 'DOM.Iterable'], strict: true, skipLibCheck: true,
    noEmit: true, allowImportingTsExtensions: true, types: [], paths,
  },
  files: [
    ...modules.map(file => path.join(frontend, 'src', file)),
    path.join(path.dirname(packagePath('vite', [vueDirectory, 'frontend'])), 'client.d.ts'),
  ],
}
const configFile = path.join(workspace, '.local/tsconfig.vue-core.json')
fs.mkdirSync(path.dirname(configFile), { recursive: true })
fs.writeFileSync(configFile, `${JSON.stringify(config, null, 2)}\n`)
const compiler = path.join(path.dirname(packagePath('typescript', [vueDirectory, 'frontend'])), 'bin/tsc')
const result = spawnSync(process.execPath, [compiler, '--noEmit', '-p', configFile], {
  cwd: workspace, stdio: 'inherit', windowsHide: true,
})
if (result.error) throw result.error
if (result.status !== 0) process.exit(result.status || 1)
console.log(`${modules.length} Vue session, form, map and shared application modules passed isolated type checking.`)
console.log('This uses real installed declarations. Full SFC, Pinia and Vue Query integration type checking remains separate.')
