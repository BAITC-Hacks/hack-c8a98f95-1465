import { expect, test } from '@playwright/test'

const listPattern = '**/api/tasks*'
const apiBase = process.env.E2E_API_URL || 'http://127.0.0.1:8000/api'

async function screenshot(page, testInfo, name) {
  const path = testInfo.outputPath(`${name}.png`)
  await page.screenshot({ path, fullPage: true })
  await testInfo.attach(name, { path, contentType: 'image/png' })
}

async function expectCatalogueLoaded(page) {
  await expect(page.getByRole('status').filter({ hasText: 'Найдено задач:' })).toBeVisible()
  await expect(page.locator('.task-card').first()).toBeVisible()
}

test('каталог показывает загрузку и восстанавливается после ошибки API', async ({ page }) => {
  let releaseRequests
  const pending = new Promise((resolve) => { releaseRequests = resolve })
  await page.route(listPattern, async (route) => {
    await pending
    await route.abort('failed')
  })
  try {
    await page.goto('/catalog')
    await expect(page.getByRole('status').filter({ hasText: 'Загружаем задачи…' })).toBeVisible()
  } finally {
    releaseRequests()
  }
  await expect(page.getByRole('alert')).toContainText('Нет связи с API')
  await expect(page.locator('.task-card')).toHaveCount(0)
  await page.unroute(listPattern)
  await page.getByRole('button', { name: 'Повторить', exact: true }).click()
  await expectCatalogueLoaded(page)
  await expect(page.getByRole('alert')).toHaveCount(0)
})

test('фильтры отправляются в API, пустой результат и URL сохраняются после перезагрузки', async ({ page }) => {
  await page.goto('/catalog')
  await expectCatalogueLoaded(page)
  const query = `не-существующая-задача-${Date.now()}`
  const expected = {
    q: query,
    category: 'Языки',
    region: 'Алматы',
    scope: 'institution',
    sort: 'newest',
  }
  await page.getByLabel('Поиск по задачам', { exact: true }).fill(query)
  await page.getByLabel('Тема', { exact: true }).fill(expected.category)
  await page.getByLabel('Регион', { exact: true }).fill(expected.region)
  await page.getByLabel('Охват', { exact: true }).selectOption(expected.scope)
  await page.getByLabel('Сортировка', { exact: true }).selectOption(expected.sort)
  const apiRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return url.pathname === '/api/tasks' && url.searchParams.get('q') === query
  })
  await page.getByRole('button', { name: 'Найти задачи', exact: true }).click()
  const requestedUrl = new URL((await apiRequest).url())
  for (const [key, value] of Object.entries(expected)) {
    expect(requestedUrl.searchParams.get(key)).toBe(value)
  }
  await expect(page.getByRole('heading', { name: 'Задачи не найдены', exact: true })).toBeVisible()
  await expect(page.getByRole('status').filter({ hasText: 'Найдено задач: 0' })).toBeVisible()
  const urlBeforeReload = page.url()
  for (const [key, value] of Object.entries(expected)) {
    expect(new URL(urlBeforeReload).searchParams.get(key)).toBe(value)
  }
  await page.reload()
  await expect(page).toHaveURL(urlBeforeReload)
  await expect(page.getByLabel('Поиск по задачам', { exact: true })).toHaveValue(query)
  await expect(page.getByLabel('Тема', { exact: true })).toHaveValue(expected.category)
  await expect(page.getByLabel('Регион', { exact: true })).toHaveValue(expected.region)
  await expect(page.getByLabel('Охват', { exact: true })).toHaveValue(expected.scope)
  await expect(page.getByLabel('Сортировка', { exact: true })).toHaveValue(expected.sort)
  await expect(page.getByRole('heading', { name: 'Задачи не найдены', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Показать все задачи', exact: true }).click()
  await expect(page).toHaveURL(/\/catalog$/)
  await expectCatalogueLoaded(page)
  await expect(page.getByLabel('Поиск по задачам', { exact: true })).toHaveValue('')
  await expect(page.getByLabel('Сортировка', { exact: true })).toHaveValue('score')
})

test('ошибка загрузки опубликованной задачи позволяет повторить запрос', async ({ page, request }) => {
  const response = await request.get(`${apiBase}/tasks`)
  expect(response.ok()).toBeTruthy()
  const { data } = await response.json()
  expect(data.length).toBeGreaterThan(0)
  const task = data[0]
  const detailPattern = `**/api/tasks/${task.id}`
  await page.route(detailPattern, (route) => route.abort('failed'))
  await page.goto(`/tasks/${task.id}`)
  await expect(page.getByLabel('Демопрофиль')).toBeEnabled()
  await expect(page.getByRole('alert')).toContainText('Нет связи с API')
  await expect(page.getByRole('heading', { name: task.title, exact: true })).toHaveCount(0)
  await page.unroute(detailPattern)
  await page.getByRole('button', { name: 'Повторить', exact: true }).click()
  await expect(page.getByRole('heading', { name: task.title, exact: true })).toBeVisible()
  await expect(page.getByRole('alert')).toHaveCount(0)
  await expect(page.getByText('Открыта для откликов', { exact: true })).toBeVisible()
})

test('несуществующая задача показывает ошибку API и ссылку в каталог', async ({ page }) => {
  await page.goto('/tasks/999999999')
  await expect(page.getByLabel('Демопрофиль')).toBeEnabled()
  await expect(page.getByRole('alert')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Повторить', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Отправить отклик', exact: true })).toHaveCount(0)
  await page.locator('main').getByRole('link', { name: 'Каталог задач', exact: true }).click()
  await expectCatalogueLoaded(page)
})

for (const viewport of [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 },
]) {
  test(`каталог ${viewport.name}: содержимое помещается по ширине`, async ({ page }, testInfo) => {
    await page.setViewportSize({ width: viewport.width, height: viewport.height })
    await page.goto('/catalog')
    await expectCatalogueLoaded(page)
    await expect(page.getByLabel('Демопрофиль')).toBeEnabled()
    const dimensions = await page.evaluate(() => ({
      viewport: window.innerWidth,
      document: document.documentElement.scrollWidth,
      body: document.body.scrollWidth,
    }))
    await screenshot(page, testInfo, `catalogue-${viewport.name}`)
    expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1)
    expect(dimensions.body).toBeLessThanOrEqual(dimensions.viewport + 1)
    await expect(page.getByLabel('Поиск по задачам', { exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Найти задачи', exact: true })).toBeVisible()
  })
}