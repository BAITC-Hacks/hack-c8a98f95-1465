import { expect, test } from '@playwright/test'

const apiBase = process.env.E2E_API_URL || 'http://127.0.0.1:8000/api'

async function capture(page, testInfo, name) {
  await page.evaluate(() => document.fonts.ready)
  const path = testInfo.outputPath(`${name}.png`)
  await page.screenshot({ path, fullPage: true })
  await testInfo.attach(name, { path, contentType: 'image/png' })
}

async function expectNoHorizontalOverflow(page) {
  const dimensions = await page.evaluate(() => ({
    content: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
  }))
  expect(dimensions.content).toBeLessThanOrEqual(dimensions.viewport)
}

test('главная объясняет назначение и запускает поиск от имени команды', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Реальные задачи.')
  await expect(page.locator('.hero-description')).toContainText('Студенческие команды предлагают решения')
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')

  const roles = page.getByRole('group', { name: 'Ваша роль на площадке' })
  await roles.getByRole('button', { name: 'Я в команде', exact: true }).click()
  await expect(roles.getByRole('button', { name: 'Я в команде', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByRole('heading', { name: 'Предложите решение', exact: true })).toBeVisible()
  // Choosing a journey previews it; the explicit start action changes the profile.
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')
  await page.getByRole('button', { name: 'Найти задачу', exact: true }).click()
  await expect(page).toHaveURL(/\/catalog$/)
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('team:1')
  await expect(page.getByRole('heading', { level: 1, name: 'Каталог задач', exact: true })).toBeVisible()
})

test('старт заказчика сохраняет выбранную организацию и открывает её черновик', async ({ page, request }) => {
  const response = await request.get(apiBase + '/demo/profiles')
  expect(response.ok()).toBeTruthy()
  const profiles = (await response.json()).data
  const customer = profiles.customers.find(profile => profile.id === 2)
  expect(customer).toBeTruthy()

  await page.goto('/')
  await expect(page.getByLabel('Демопрофиль')).toBeEnabled()
  await page.getByLabel('Демопрофиль').selectOption('customer:2')
  await page.getByRole('group', { name: 'Ваша роль на площадке' }).getByRole('button', { name: 'Я заказчик', exact: true }).click()
  await page.getByRole('button', { name: 'Создать задачу', exact: true }).click()
  await expect(page).toHaveURL(/\/tasks\/new$/)
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:2')
  await expect(page.getByLabel('Организация', { exact: true })).toHaveValue(customer.organization)
  await expect(page.getByRole('button', { name: 'Создать черновик', exact: true })).toBeVisible()
})

test('инструкция объясняет рейтинг и AI и ведёт к старту выбранной роли', async ({ page }) => {
  await page.goto('/guide')
  await expect(page.getByRole('heading', { level: 1, name: 'Как работает AlemEdu' })).toBeVisible()
  await expect(page.locator('#rating')).toContainText('Рейтинг показывает полноту описания')
  await expect(page.locator('#rating')).toContainText('не оценивает качество будущего решения и навыки команды')
  await expect(page.locator('.guide-demo-panel')).toContainText('Внешняя AI-модель пока не подключена')

  await page.getByRole('link', { name: 'Начать как команда', exact: true }).click()
  await expect(page).toHaveURL(/\/\?role=team#start$/)
  await expect(page.getByRole('button', { name: 'Я в команде', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByRole('heading', { name: 'С чего начнём?', exact: true })).toBeInViewport()
  await page.getByRole('button', { name: 'Найти задачу', exact: true }).click()
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('team:1')

  await page.goto('/guide')
  await page.getByRole('link', { name: 'Начать как заказчик', exact: true }).click()
  await expect(page).toHaveURL(/\/\?role=customer#start$/)
  await expect(page.getByRole('button', { name: 'Я заказчик', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await page.getByRole('button', { name: 'Создать задачу', exact: true }).click()
  await expect(page).toHaveURL(/\/tasks\/new$/)
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')
})

for (const viewport of [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 },
]) {
  test(`главная и инструкция читаются без горизонтального скролла — ${viewport.name}`, async ({ page }, testInfo) => {
    const browserErrors = []
    page.on('pageerror', error => browserErrors.push(error.message))
    await page.setViewportSize({ width: viewport.width, height: viewport.height })
    await page.goto('/')
    await expect(page.getByLabel('Демопрофиль')).toBeEnabled()
    await expect(page.locator('.home-catalog')).toHaveAttribute('aria-busy', 'false')
    await expect(page.getByRole('button', { name: 'Создать задачу', exact: true })).toBeEnabled()
    await expectNoHorizontalOverflow(page)
    await capture(page, testInfo, `home-${viewport.name}`)

    await page.locator('.home-rating-note').getByRole('link', { name: 'Как это работает', exact: true }).click()
    await expect(page).toHaveURL(/\/guide#rating$/)
    await expect(page.getByRole('heading', { name: 'Рейтинг показывает полноту описания', exact: true })).toBeInViewport()
    await page.evaluate(() => window.scrollTo(0, 0))
    await expectNoHorizontalOverflow(page)
    await capture(page, testInfo, `guide-${viewport.name}`)
    expect(browserErrors).toEqual([])
  })
}
