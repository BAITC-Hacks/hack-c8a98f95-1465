import { chromium, expect } from '@playwright/test'
import assert from 'node:assert/strict'
import { mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { join } from 'node:path'

const base = new URL(process.argv[2] || 'http://127.0.0.1:8080').origin
const output = fileURLToPath(new URL('../../.run/', import.meta.url))
mkdirSync(output, { recursive: true })
const browser = await chromium.launch({ headless: true })
try {
  const context = await browser.newContext({ baseURL: base })
  const health = await context.request.get('/api/health')
  assert.equal(health.status(), 200)
  assert.equal((await health.json()).data.status, 'ok')
  for (const path of ['/.env', '/.git/config', '/api/not-found']) {
    const response = await context.request.get(path)
    assert.ok([403, 404].includes(response.status()), `${path} must not be public`)
  }
  const page = await context.newPage()
  const errors = []
  const apiOrigins = new Set()
  page.on('pageerror', error => errors.push(error.message))
  page.on('request', request => {
    const url = new URL(request.url())
    if (url.pathname.startsWith('/api/')) apiOrigins.add(url.origin)
  })
  for (const [name, viewport] of [['desktop', { width: 1366, height: 768 }], ['mobile', { width: 390, height: 844 }]]) {
    await page.setViewportSize(viewport)
    await page.goto('/catalog')
    await expect(page.locator('.task-card').first()).toBeVisible({ timeout: 30000 })
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth))
    await page.screenshot({ path: join(output, `deployment-${name}.png`), fullPage: true })
    await page.goto('/tasks/2/offers')
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    await expect(page.getByRole('alert')).toHaveCount(0)
    await page.reload()
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  }
  assert.deepEqual([...apiOrigins], [base], 'Browser API calls must use the deployed origin')
  assert.deepEqual(errors, [])
  console.log(JSON.stringify({ site: base, status: 'ok', apiSameOrigin: true, viewports: ['1366x768', '390x844'], screenshots: output }))
} finally { await browser.close() }
