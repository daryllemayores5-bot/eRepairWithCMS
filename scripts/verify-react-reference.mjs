import fs from 'node:fs'
import path from 'node:path'
import { createHash } from 'node:crypto'
import { fileURLToPath } from 'node:url'

const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const reference = path.join(workspace, 'frontend-react-reference')
const manifest = JSON.parse(fs.readFileSync(path.join(reference, 'reference-manifest.json'), 'utf8'))
const compareActive = process.argv.includes('--compare-active')
let failures = 0
for (const [relative, expected] of Object.entries(manifest.files)) {
  if (relative.includes('..') || path.isAbsolute(relative)) throw new Error('Invalid reference manifest path')
  for (const root of compareActive ? [reference, path.join(workspace, 'frontend')] : [reference]) {
    const file = path.join(root, relative)
    const actual = fs.existsSync(file) ? createHash('sha256').update(fs.readFileSync(file)).digest('hex') : null
    if (actual !== expected) { failures++; console.error(`Changed or missing: ${path.basename(root)}/${relative}`) }
  }
}
console.log(`${Object.keys(manifest.files).length} React reference files checked${compareActive ? ' against the active frontend as well' : ''}; ${failures} differences.`)
process.exitCode = failures ? 1 : 0
