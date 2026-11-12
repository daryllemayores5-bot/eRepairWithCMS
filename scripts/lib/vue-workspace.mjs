import { existsSync, readFileSync } from 'node:fs'
import { join, resolve } from 'node:path'

/** Keep verification usable before and after the staged frontend is activated. */
export function findVueFrontend(workspace) {
  for (const name of ['frontend', 'frontend-vue']) {
    const directory = resolve(workspace, name)
    const file = join(directory, 'package.json')
    if (!existsSync(file)) continue
    const manifest = JSON.parse(readFileSync(file, 'utf8'))
    if (manifest.dependencies?.vue && !manifest.dependencies?.react) return directory
  }
  throw new Error('No Vue frontend found in frontend or frontend-vue.')
}
