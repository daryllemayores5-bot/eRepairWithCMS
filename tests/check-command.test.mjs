import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, writeFile, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execFile } from 'node:child_process'
import { promisify } from 'node:util'

const run = promisify(execFile)
const helper = fileURLToPath(new URL('../scripts/lib/check-command.ps1', import.meta.url))
const quote = value => `'${value.replaceAll("'", "''")}'`
const windows = { skip: process.platform !== 'win32' }
async function fixture(t) {
  const root = await mkdtemp(join(tmpdir(), 'erepair-native-check-'))
  t.after(async () => {
    assert.ok(resolve(root).startsWith(resolve(tmpdir()) + sep))
    await rm(root, { recursive: true, force: true })
  })
  return {
    root,
    async execute(body) {
      const script = join(root, 'check.ps1')
      await writeFile(script, `$ErrorActionPreference='Stop'\n. ${quote(helper)}\ntry {\n${body}\nWrite-Output "Preference=$ErrorActionPreference"\nexit 0\n} catch { Write-Output $_.Exception.Message; exit 1 }\n`)
      return run('powershell.exe', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', script], { windowsHide: true })
    },
  }
}

test('successful native diagnostics on stderr do not fail the release gate', windows, async t => {
  const f = await fixture(t)
  const executable = join(f.root, 'success.cmd')
  await writeFile(executable, '@echo off\r\necho informational diagnostic 1>&2\r\nexit /b 0\r\n')
  const result = await f.execute(`Invoke-Check -Executable ${quote(executable)}\nWrite-Output 'Completed'`)
  assert.match(result.stdout, /Completed/)
  assert.match(result.stdout, /Preference=Stop/)
})

test('a real native failure stops subsequent release checks', windows, async t => {
  const f = await fixture(t)
  const executable = join(f.root, 'failure.cmd')
  await writeFile(executable, '@echo off\r\nexit /b 7\r\n')
  await assert.rejects(f.execute(`Invoke-Check -Executable ${quote(executable)}\nWrite-Output 'MUST_NOT_RUN'`), error => {
    assert.match(error.stdout, /failed with exit code 7/)
    assert.doesNotMatch(error.stdout, /MUST_NOT_RUN/)
    return true
  })
})

test('multiple native command matches preserve normal first-match resolution', windows, async t => {
  const f = await fixture(t)
  const name = 'erepair-native-fixture'
  await writeFile(join(f.root, `${name}.cmd`), '@echo off\r\necho Selected native command\r\nexit /b 0\r\n')
  await writeFile(join(f.root, name), 'alternate extensionless command fixture')
  const result = await f.execute(`$env:PATH=${quote(f.root)}+';'+$env:PATH\nif (@(Get-Command ${quote(name)} -CommandType Application).Count -lt 2) { throw 'Multiple-command fixture was not established' }\nInvoke-Check -Executable ${quote(name)}\nWrite-Output 'Completed'`)
  assert.match(result.stdout, /Selected native command/)
  assert.match(result.stdout, /Completed/)
})

test('a missing native executable cannot reuse an earlier successful exit code', windows, async t => {
  const f = await fixture(t)
  await assert.rejects(f.execute(`$global:LASTEXITCODE=0\nInvoke-Check -Executable ${quote(join(f.root, 'missing.exe'))}\nWrite-Output 'MUST_NOT_RUN'`), error => {
    assert.doesNotMatch(error.stdout, /MUST_NOT_RUN/)
    assert.equal(error.code, 1)
    return true
  })
})

test('an existing invalid executable cannot reuse an earlier successful exit code', windows, async t => {
  const f = await fixture(t)
  const executable = join(f.root, 'invalid.exe')
  await writeFile(executable, 'This is a test fixture, not an executable.')
  await assert.rejects(f.execute(`$global:LASTEXITCODE=0\nInvoke-Check -Executable ${quote(executable)}\nWrite-Output 'MUST_NOT_RUN'`), error => {
    assert.equal(error.code, 1)
    assert.doesNotMatch(error.stdout, /MUST_NOT_RUN/)
    return true
  })
})

test('live acceptance permits the separate trusted development and built-preview origins', windows, async t => {
  const f = await fixture(t)
  const result = await f.execute(`Write-Output (Get-ReleaseLoopbackOrigin 'http://127.0.0.1:5183/')\nWrite-Output (Get-ReleaseLoopbackOrigin 'http://localhost:5184')`)
  assert.match(result.stdout, /http:\/\/127\.0\.0\.1:5183/)
  assert.match(result.stdout, /http:\/\/localhost:5184/)
})

test('live acceptance rejects nonloopback and credential-bearing origins', windows, async t => {
  const f = await fixture(t)
  const invalid = ['https://127.0.0.1:5183', 'http://erepair.example', 'http://127.0.0.1.evil.test', 'http://user:secret@localhost', 'http://localhost/path', 'http://localhost?token=value', 'http://localhost#fragment', 'not a URL']
  const body = `foreach ($value in @(${invalid.map(quote).join(',')})) {\n$rejected=$false\ntry { Get-ReleaseLoopbackOrigin $value | Out-Null } catch { $rejected=$true }\nif (!$rejected) { throw 'Unsafe origin accepted' }\n}\nWrite-Output 'All unsafe origins rejected'`
  const result = await f.execute(body)
  assert.match(result.stdout, /All unsafe origins rejected/)
})
