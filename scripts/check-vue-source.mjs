import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { findVueFrontend } from './lib/vue-workspace.mjs'

const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const frontend = findVueFrontend(workspace)
const sourceRoot = path.join(frontend, 'src')
let compiler
for (const packageFile of [path.join(frontend, 'package.json'), path.join(workspace, '.local/vue-tools/package.json')]) {
  try {
    compiler = createRequire(packageFile)('@vue/compiler-sfc')
    break
  } catch { /* A cached compiler permits syntax checks before full installation. */ }
}
if (!compiler) throw new Error('Install the Vue frontend dependencies before checking SFC source.')

let components = 0
let typescriptFiles = 0
const failures = []
function checkImports(source, filename) {
  for (const match of source.matchAll(/(?:\bfrom\s*|\bimport\s*\()\s*['"]([@.][^'"]+)['"]/g)) {
    const specifier = match[1]
    if (!specifier.startsWith('@/') && !specifier.startsWith('.')) continue
    const target = specifier.startsWith('@/')
      ? path.join(sourceRoot, specifier.slice(2))
      : path.resolve(path.dirname(filename), specifier)
    if (![target, `${target}.ts`, `${target}.vue`, path.join(target, 'index.ts')].some(file => fs.existsSync(file) && fs.statSync(file).isFile())) {
      throw new Error(`Missing local import: ${specifier}`)
    }
  }
}

function walk(directory) {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const filename = path.join(directory, entry.name)
    if (entry.isDirectory()) { walk(filename); continue }
    if (!/\.(vue|ts)$/.test(filename) || filename.endsWith('.d.ts')) continue
    const source = fs.readFileSync(filename, 'utf8')
    try {
      if (/from\s*['"](?:react(?:\/[^'"]*)?|react-dom[^'"]*|react-router-dom|@tanstack\/react-query|react-hook-form|react-leaflet|lucide-react)['"]/.test(source)) {
        throw new Error('React runtime import in Vue source')
      }
      if (filename.endsWith('.ts')) {
        compiler.babelParse(source, { sourceType: 'module', plugins: ['typescript'] })
        checkImports(source, filename)
        typescriptFiles++
        continue
      }
      const { descriptor, errors } = compiler.parse(source, { filename })
      if (errors.length) throw new Error(errors.map(String).join('\n'))
      if (!descriptor.scriptSetup) throw new Error('Expected Composition API <script setup>')
      const script = compiler.compileScript(descriptor, { id: `source-check-${components}` })
      checkImports(script.content, filename)
      compiler.babelParse(script.content, { sourceType: 'module', plugins: ['typescript'] })
      const result = compiler.compileTemplate({
        source: descriptor.template.content, filename, id: `source-check-${components}`,
        compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] }, isProd: true,
      })
      if (result.errors.length) throw new Error(result.errors.map(String).join('\n'))
      const unbound = [...new Set([...result.code.matchAll(/_ctx\.([a-zA-Z_$][\w$]*)/g)].map(match => match[1]))].filter(name => !name.startsWith('$'))
      if (unbound.length) throw new Error(`Unbound template identifiers: ${unbound.join(', ')}`)
      components++
    } catch (error) {
      failures.push(`${path.relative(workspace, filename)}: ${error.message}`)
    }
  }
}

walk(sourceRoot)
walk(path.join(frontend, 'live-tests'))
const shared = ['index.css', 'services/api.ts', 'types/index.ts', 'lib/utils.ts', 'lib/errors.ts', 'lib/format.ts', 'lib/repair-request.ts', 'lib/retire-legacy-worker.ts']
for (const relative of shared) {
  const original = fs.readFileSync(path.join(workspace, 'frontend-react-reference/src', relative))
  const migrated = fs.readFileSync(path.join(sourceRoot, relative))
  if (!original.equals(migrated)) failures.push(`Shared source differs from the React reference: ${relative}`)
}
for (const failure of failures) console.error(failure)
console.log(`${components} Vue SFCs and ${typescriptFiles} TypeScript files checked; ${failures.length} failures.`)
console.log(`${shared.length} shared CSS/API/type/business-rule files compared against the preserved React source.`)
console.log('This checks syntax, bindings and local imports. It does not replace vue-tsc, builds, runtime or browser acceptance.')
process.exitCode = failures.length ? 1 : 0
