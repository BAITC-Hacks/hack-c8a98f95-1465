import { test, expect } from '@playwright/test'

const apiURL = process.env.E2E_API_URL || 'http://127.0.0.1:18082/api'
const customer = { 'X-Demo-Role': 'customer', 'X-Demo-Id': '1' }

async function fixture(request) {
  const created = await request.post(apiURL + '/tasks', { headers: customer, data: {
    title: 'Задача для решений ' + Date.now(), organization: 'Демо университет «Алем»',
    region: 'Астана', category: 'Организация обучения', scope: 'institution',
  } })
  expect(created.ok()).toBeTruthy()
  const task = (await created.json()).data
  expect((await request.post(`${apiURL}/tasks/${task.id}/publish`, { headers: customer, data: { confirmed: true } })).ok()).toBeTruthy()
  for (const id of [1, 2]) {
    const offer = await request.post(`${apiURL}/tasks/${task.id}/offers`, {
      headers: { 'X-Demo-Role': 'team', 'X-Demo-Id': String(id) },
      data: { idea: 'Веб-прототип для проверки расписания.', plan: 'Исследование, разработка, тестирование со студентами.', timeline: 'Три недели' },
    })
    expect(offer.ok()).toBeTruthy()
  }
  return task
}

test('нулевой рейтинг не блокирует публикацию, каталог и отклик другого учреждения', async ({ page, request }) => {
  const title = 'Нулевая готовность ' + Date.now()
  await page.goto('/tasks/new')
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')
  await page.locator('input[name="title"]').fill(title)
  await page.locator('input[name="region"]').fill('Алматы')
  await page.locator('input[name="category"]').fill('Языки')
  await page.getByLabel('Охват', { exact: true }).selectOption('institution')
  await page.getByRole('button', { name: 'Создать черновик', exact: true }).click()
  await expect(page).toHaveURL(/\/tasks\/\d+\/edit$/)
  await expect(page.locator('.score-panel__number strong')).toHaveText('0')
  await page.getByLabel('Я проверил сведения и подтверждаю публикацию').check()
  await page.getByRole('button', { name: 'Опубликовать задачу', exact: true }).click()
  await expect(page).toHaveURL(/\/tasks\/\d+$/)
  const taskId = new URL(page.url()).pathname.split('/').pop()
  await expect(page.getByText('Задача для своего учреждения открыта всем командам.', { exact: true })).toBeVisible()
  await page.getByLabel('Демопрофиль').selectOption('team:2')
  await page.goto('/catalog?q=' + encodeURIComponent(title))
  const card = page.locator('.task-card')
  await expect(card).toHaveCount(1)
  await expect(card).toContainText('Алматы')
  await expect(card).toContainText('Демо университет «Алем»')
  await expect(card).toContainText('Внутри учреждения')
  await expect(card.locator('.task-card-score strong')).toHaveText('0')
  await expect(card.locator('.task-draft-label')).toHaveCount(0)
  await page.getByRole('link', { name: title, exact: true }).click()
  await page.getByLabel('Идея решения', { exact: true }).fill('Внешняя команда помогает учреждению.')
  await page.getByLabel('План работы', { exact: true }).fill('Изучим процесс и соберём прототип.')
  await page.getByLabel('Сроки', { exact: true }).fill('Две недели')
  await page.getByRole('button', { name: 'Отправить отклик', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Отклик отправлен' })).toBeVisible()
  await expect(page.locator('.detail-offer-count strong')).toHaveText('1')
  const offers = (await (await request.get(`${apiURL}/tasks/${taskId}/offers`, { headers: customer })).json()).data
  expect(offers[0].team.organization).toBe('Демо колледж «Самғау»')
  expect(offers[0].decision).toBe('pending')
  const saved = (await (await request.get(`${apiURL}/tasks/${taskId}`)).json()).data
  expect(saved.score).toBe(0)
  expect(saved.status).toBe('published')
})

test('выбор нескольких команд, отклонение и фильтр сохраняются в API', async ({ page, request }) => {
  const task = await fixture(request)
  await page.goto(`/tasks/${task.id}/offers`)
  const first = page.getByRole('article', { name: 'Отклик команды Steppe Coders', exact: true })
  const second = page.getByRole('article', { name: 'Отклик команды Qadam', exact: true })
  await first.getByRole('button', { name: 'Выбрать', exact: true }).click()
  await second.getByRole('button', { name: 'Выбрать', exact: true }).click()
  await expect(page.locator('.review-offer .decision-selected')).toHaveCount(2)
  await first.getByRole('button', { name: 'Отклонить', exact: true }).click()
  await expect(first.locator('.tag')).toHaveText('Отклонён')
  await page.reload()
  await expect(first.locator('.tag')).toHaveText('Отклонён')
  await expect(second.locator('.tag')).toHaveText('Команда выбрана')
  await page.getByLabel('Решение', { exact: true }).selectOption('selected')
  await expect(page.locator('.review-offer')).toHaveCount(1)
  await expect(second).toBeVisible()
  await page.getByLabel('Демопрофиль').selectOption('team:1')
  await page.getByRole('navigation').getByRole('link', { name: 'Мой кабинет' }).click()
  await expect(page.locator('.offer-card').filter({ has: page.getByRole('link', { name: 'Задача #' + task.id, exact: true }) }).locator('.tag')).toHaveText('Отклонён')
})

test('сбой решения не показывает ложный успех, повторный запрос сохраняет решение', async ({ page, request }) => {
  const task = await fixture(request)
  const pattern = '**/api/offers/*/decision'
  await page.goto(`/tasks/${task.id}/offers`)
  const offer = page.locator('.review-offer').first()
  await expect(offer.locator('.tag')).toHaveText('Ожидает решения')
  await page.route(pattern, route => route.abort('failed'))
  await offer.getByRole('button', { name: 'Выбрать', exact: true }).click()
  await expect(offer.getByRole('alert')).toContainText('Нет связи с API')
  await expect(offer.locator('.tag')).toHaveText('Ожидает решения')
  await page.unroute(pattern)
  await offer.getByRole('button', { name: 'Выбрать', exact: true }).click()
  await expect(offer.locator('.tag')).toHaveText('Команда выбрана')
  await expect(offer.getByRole('alert')).toHaveCount(0)
})

test('панель восстанавливает загрузку и скрывает чужие отклики при смене роли', async ({ page, request }) => {
  const task = await fixture(request)
  const pattern = `**/api/tasks/${task.id}/offers`
  await page.route(pattern, route => route.abort('failed'))
  await page.goto(`/tasks/${task.id}/offers`)
  await expect(page.getByRole('alert')).toContainText('Нет связи с API')
  await page.unroute(pattern)
  await page.getByRole('button', { name: 'Повторить', exact: true }).click()
  await expect(page.locator('.review-offer')).toHaveCount(2)
  await page.getByLabel('Демопрофиль').selectOption('customer:2')
  await expect(page.getByRole('alert')).toContainText('только заказчику')
  await expect(page.locator('.review-offer')).toHaveCount(0)
  await page.getByLabel('Демопрофиль').selectOption('team:2')
  await expect(page.getByRole('heading', { name: 'Отклики доступны заказчику' })).toBeVisible()
  await expect(page.locator('.review-offer')).toHaveCount(0)
})

for (const viewport of [
  { width: 1366, height: 768 },
  { width: 1920, height: 1080 },
  { width: 390, height: 844 },
]) {
  test(`панель заказчика ${viewport.width}: адаптивность и читаемость`, async ({ page }, testInfo) => {
    const errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.setViewportSize(viewport)
    await page.goto('/tasks/2/offers')
    await expect(page.locator('.review-offer')).toHaveCount(2)
    await expect(page.getByRole('button', { name: 'Выбрать', exact: true }).first()).toBeEnabled()
    if (viewport.width >= 1366) await expect(page.getByRole('button', { name: 'Выбрать', exact: true }).first()).toBeInViewport()
    await page.evaluate(() => document.fonts.ready)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    const clipped = await page.locator('.review-offer button, .review-offer h2, .review-offer .tag').evaluateAll(elements =>
      elements.filter(el => el.scrollWidth > el.clientWidth + 1).map(el => el.textContent))
    expect(clipped).toEqual([])
    await page.screenshot({ path: testInfo.outputPath(`offers-${viewport.width}.png`), fullPage: true })
    await page.getByRole('navigation').getByRole('link', { name: 'Мой кабинет' }).click()
    await expect(page.locator('.task-card').first()).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    await page.screenshot({ path: testInfo.outputPath(`workspace-${viewport.width}.png`), fullPage: true })
    expect(errors).toEqual([])
  })
}
