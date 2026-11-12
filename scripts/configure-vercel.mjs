import { access, writeFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { join } from 'node:path'
import { vercelConfig } from './lib/vercel-config.mjs'

try {
  vercelConfig(process.argv[2]) // Validate the backend origin before touching files.
  const root = fileURLToPath(new URL('../', import.meta.url))
  let staticBuild = false
  try {
    await access(join(root, 'frontend/package.json'))
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
    staticBuild = true
    try { await access(join(root, 'frontend/dist/index.html')) }
    catch { throw new Error('Supply the source checkout or an intact compiled release with frontend/dist/index.html.') }
  }
  const config = vercelConfig(process.argv[2], { staticBuild })
  // Exclusive creation protects any reviewed configuration already in place.
  await writeFile(fileURLToPath(new URL('../vercel.json', import.meta.url)), `${JSON.stringify(config, null, 2)}\n`, { flag: 'wx' })
  console.log(`Created vercel.json for ${staticBuild ? 'the compiled static release (no npm install/build)' : 'the source checkout (locked npm installation/build)'}. Review it before deploying the project root to Vercel.`)
} catch (error) {
  console.error(error.code === 'EEXIST' ? 'vercel.json already exists; review the existing configuration.' : error.message)
  process.exitCode = 1
}
