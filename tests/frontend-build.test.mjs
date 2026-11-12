import { afterEach, test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, mkdir, writeFile, readFile, rm } from 'node:fs/promises'
import { join, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import { buildFrontend } from '../scripts/lib/frontend-build.mjs'
import { findVueFrontend } from '../scripts/lib/vue-workspace.mjs'

const temporaryParent = resolve(fileURLToPath(new URL('../.local/', import.meta.url)))
const fixtures = []
afterEach(async () => {
  for (const fixture of fixtures.splice(0)) {
    if (!resolve(fixture).startsWith(temporaryParent + sep)) throw new Error('Fixture escaped the temporary workspace.')
    await rm(fixture, { recursive: true, force: true })
  }
})
async function fixture(dependencies, compilerExit = 0) {
  await mkdir(temporaryParent, { recursive: true })
  const root = await mkdtemp(join(temporaryParent, 'frontend-build-test-'))
  fixtures.push(root)
  await writeFile(join(root, 'package.json'), JSON.stringify({ dependencies }))
  const log = join(root, 'steps.jsonl')
  for (const [name, file] of [['typescript', 'bin/tsc'], ['vue-tsc', 'bin/vue-tsc.js'], ['vite', 'bin/vite.js']]) {
    const directory = join(root, 'node_modules', name)
    await mkdir(join(directory, 'bin'), { recursive: true })
    await writeFile(join(directory, 'package.json'), JSON.stringify({ type: 'commonjs' }))
    await writeFile(join(directory, file), `const fs=require('node:fs');fs.appendFileSync(process.env.STEP_LOG,JSON.stringify({name:${JSON.stringify(name)},args:process.argv.slice(2),api:process.env.VITE_API_URL,cms:process.env.VITE_BACKEND_PUBLIC_URL})+'\\n');process.exit(${name === 'vite' ? 0 : compilerExit});`)
  }
  return {
    root,
    environment: { ...process.env, STEP_LOG: log, VITE_API_URL: 'http://127.0.0.1:8000/api/v1', VITE_BACKEND_PUBLIC_URL: 'http://127.0.0.1:8000' },
    steps: async () => (await readFile(log, 'utf8')).trim().split('\n').map(line => JSON.parse(line)),
  }
}

test('the current React production path keeps its compiler and same-origin overrides', async () => {
  const current = await fixture({ react: '^19' })
  buildFrontend(current.root, current.environment)
  assert.deepEqual(await current.steps(), [
    { name: 'typescript', args: ['-b'], api: '/api/v1', cms: '' },
    { name: 'vite', args: ['build', '--mode', 'production'], api: '/api/v1', cms: '' },
  ])
})

test('a Vue frontend invokes vue-tsc before Vite with the same production overrides', async () => {
  const next = await fixture({ vue: '^3' })
  buildFrontend(next.root, next.environment)
  assert.deepEqual(await next.steps(), [
    { name: 'vue-tsc', args: ['--noEmit', '-p', 'tsconfig.app.json'], api: '/api/v1', cms: '' },
    { name: 'vite', args: ['build', '--mode', 'production'], api: '/api/v1', cms: '' },
  ])
})

test('a failed Vue typecheck prevents production bundling', async () => {
  const next = await fixture({ vue: '^3' }, 9)
  assert.throws(() => buildFrontend(next.root, next.environment), error => error.exitCode === 9)
  assert.equal((await next.steps()).length, 1)
})

test('ambiguous or missing framework declarations cannot select a production compiler', async () => {
  for (const dependencies of [{}, { vue: '^3', react: '^19' }]) {
    const invalid = await fixture(dependencies)
    assert.throws(() => buildFrontend(invalid.root, invalid.environment), /exactly one active frontend framework/)
  }
})

test('Vue verification finds the staged frontend while React remains active', async () => {
  const project = await fixture({})
  for (const [name, dependencies] of [['frontend', { react: '^19' }], ['frontend-vue', { vue: '^3' }]]) {
    await mkdir(join(project.root, name))
    await writeFile(join(project.root, name, 'package.json'), JSON.stringify({ dependencies }))
  }
  assert.equal(findVueFrontend(project.root), join(project.root, 'frontend-vue'))
})

test('Vue verification follows the canonical frontend after activation', async () => {
  const project = await fixture({})
  for (const name of ['frontend', 'frontend-vue']) {
    await mkdir(join(project.root, name))
    await writeFile(join(project.root, name, 'package.json'), JSON.stringify({ dependencies: { vue: '^3' } }))
  }
  assert.equal(findVueFrontend(project.root), join(project.root, 'frontend'))
})

test('Vue verification refuses to treat a React-only project as a Vue migration', async () => {
  const project = await fixture({})
  await mkdir(join(project.root, 'frontend'))
  await writeFile(join(project.root, 'frontend/package.json'), JSON.stringify({ dependencies: { react: '^19' } }))
  assert.throws(() => findVueFrontend(project.root), /No Vue frontend/)
})
