import { spawn, spawnSync } from 'node:child_process'
import { existsSync, mkdtempSync, writeFileSync, unlinkSync, rmdirSync } from 'node:fs'
import { randomBytes } from 'node:crypto'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const backend = fileURLToPath(new URL('../../backend/', import.meta.url))
const target = new URL(process.env.E2E_API_URL || 'http://127.0.0.1:18082/api')
if (target.hostname !== '127.0.0.1') throw new Error('Managed test API must use 127.0.0.1.')
if (!existsSync(join(backend, 'vendor/autoload.php'))) throw new Error('Run composer install in backend first.')
const php = process.env.E2E_PHP || (process.platform === 'win32' && existsSync('C:/xampp/php/php.exe') ? 'C:/xampp/php/php.exe' : 'php')
const temp = mkdtempSync(join(tmpdir(), 'alemedu-e2e-'))
const database = join(temp, 'database.sqlite')
writeFileSync(database, '')
const env = {
  ...process.env,
  APP_ENV: 'testing', APP_KEY: 'base64:' + randomBytes(32).toString('base64'),
  APP_CONFIG_CACHE: join(temp, 'config.php'), DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
  DEMO_MODE: 'true', CACHE_STORE: 'array', SESSION_DRIVER: 'array',
  CORS_ALLOWED_ORIGINS: `http://127.0.0.1:${process.env.E2E_PORT || '5174'}`,
}

function cleanup() {
  for (const suffix of ['', '-journal', '-wal', '-shm']) {
    try { unlinkSync(database + suffix) } catch {}
  }
  try { rmdirSync(temp) } catch {}
}

const migration = spawnSync(php, ['artisan', 'migrate', '--seed', '--force'], { cwd: backend, env, stdio: 'inherit', windowsHide: true })
if (migration.error || migration.status !== 0) {
  cleanup()
  throw migration.error || new Error('Test database preparation failed.')
}
const server = spawn(php, ['-S', `127.0.0.1:${target.port}`, '-t', 'public', 'public/index.php'], { cwd: backend, env, stdio: 'inherit', windowsHide: true })
server.on('error', error => { console.error(error.message); cleanup(); process.exitCode = 1 })
server.on('close', code => { cleanup(); process.exitCode = code || 0 })
process.on('SIGINT', () => server.kill())
process.on('SIGTERM', () => server.kill())
