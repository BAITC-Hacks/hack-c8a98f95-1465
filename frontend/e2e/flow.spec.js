import { test, expect } from '@playwright/test'

const apiURL = process.env.E2E_API_URL || 'http://127.0.0.1:8000/api'
const customerHeaders = { 'X-Demo-Role': 'customer', 'X-Demo-Id': '1', Accept: 'application/json' }

test('создать → AI → подтвердить рейтинг → опубликовать → каталог → отклик', async ({ page, request }, testInfo) => {
  const browserErrors = []
  page.on('pageerror', error => browserErrors.push(error.message))
  const title = 'Практика английского E2E ' + Date.now()
  await page.goto('/tasks/new')
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')
  await page.locator('input[name="title"]').fill(title)
  await page.locator('input[name="region"]').fill('Алматы')
  await page.locator('input[name="category"]').fill('Языки')
  await page.locator('textarea[name="context"]').fill('Первокурсникам не хватает регулярной разговорной практики английского.')
  const createResponse = page.waitForResponse(response => response.url().endsWith('/api/tasks') && response.request().method() === 'POST')
  await page.getByRole('button', { name: 'Создать черновик' }).click()
  const created = (await (await createResponse).json()).data
  await expect(page).toHaveURL(new RegExp('/tasks/' + created.id + '/edit'))
  await expect(page.locator('.score-panel__number strong')).toHaveText(String(created.score))
  await expect(page.locator('.question-list li')).not.toHaveCount(0)
  await expect(page.getByText('Демо: вопросы формирует локальная AI-заглушка.')).toBeVisible()
  const content = {
    context: 'Первокурсникам не хватает регулярной разговорной практики английского.',
    users: 'Студенты первого курса с уровнем A2.',
    materials: 'Учебные диалоги и открытые примеры.',
    constraints: 'Один месяц, работа в мобильном браузере.',
    expectedOutcome: 'Рабочий прототип тренажёра разговорных навыков.',
    successCriteria: 'Пять студентов самостоятельно проходят три диалога.',
    contact: 'coordinator@example.invalid, встреча раз в неделю.',
  }
  for (const [key, value] of Object.entries(content)) {
    await page.locator('textarea[name="' + key + '"]').fill(value)
    await page.locator('#field-' + key + ' input[type="checkbox"]').check()
  }
  const saveResponse = page.waitForResponse(response => response.url().endsWith('/api/tasks/' + created.id) && response.request().method() === 'PUT')
  await page.getByRole('button', { name: 'Сохранить изменения', exact: true }).click()
  const saved = (await (await saveResponse).json()).data
  await expect(page.locator('.score-panel__number strong')).toHaveText(String(saved.score))
  await expect(page.locator('.score-panel__level')).toHaveText(saved.readinessLabel)
  expect(saved.confirmedFields).toHaveLength(7)
  for (const criterion of Object.values(saved.scoreBreakdown)) {
    await expect(page.locator('.score-panel__criteria')).toContainText(criterion.label)
    await expect(page.getByRole('progressbar', { name: criterion.label, exact: true })).toHaveAttribute('value', String(criterion.points))
    await expect(page.getByRole('progressbar', { name: criterion.label, exact: true })).toHaveAttribute('max', String(criterion.maxPoints))
  }
  // An edited field loses confirmation; the score must come from the next server response.
  await page.locator('textarea[name="context"]').fill(content.context + ' Нужны диалоги в парах.')
  await expect(page.locator('#field-context input[type="checkbox"]')).not.toBeChecked()
  const changedResponse = page.waitForResponse(response => response.url().endsWith('/api/tasks/' + created.id) && response.request().method() === 'PUT')
  await page.getByRole('button', { name: 'Сохранить изменения', exact: true }).click()
  const changed = (await (await changedResponse).json()).data
  await expect(page.locator('.score-panel__number strong')).toHaveText(String(changed.score))
  expect(changed.confirmedFields).not.toContain('context')
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.screenshot({ path: testInfo.outputPath('editor-desktop.png'), fullPage: true })
  await page.getByLabel('Я проверил сведения и подтверждаю публикацию').check()
  await page.getByRole('button', { name: 'Опубликовать задачу', exact: true }).click()
  await expect(page).toHaveURL(new RegExp('/tasks/' + created.id + '$'))
  const published = (await (await request.get(apiURL + '/tasks/' + created.id)).json()).data
  expect(published.status).toBe('published')
  await expect(page.locator('.score-panel__number strong')).toHaveText(String(published.score))

  await page.getByRole('navigation').getByRole('link', { name: 'Каталог задач' }).click()
  await page.getByLabel('Поиск по задачам').fill(title)
  await page.getByLabel('Тема', { exact: true }).fill('Языки')
  await page.getByLabel('Регион', { exact: true }).fill('Алматы')
  await page.getByLabel('Охват', { exact: true }).selectOption('kazakhstan')
  await page.getByLabel('Сортировка').selectOption('score')
  await page.getByRole('button', { name: 'Найти задачи' }).click()
  await expect(page.locator('.task-card')).toHaveCount(1)
  await expect(page.locator('.task-card-score')).toContainText(String(published.score))
  await page.getByRole('link', { name: title, exact: true }).click()

  await page.getByLabel('Демопрофиль').selectOption('team:1')
  await page.getByLabel('Идея решения', { exact: true }).fill('Разработать тренажёр парных диалогов.')
  await page.getByLabel('План работы', { exact: true }).fill('Исследование, прототип, тестирование со студентами.')
  await page.getByLabel('Сроки', { exact: true }).fill('Четыре недели')
  const offerResponse = page.waitForResponse(response => response.url().endsWith('/offers') && response.request().method() === 'POST')
  await page.getByRole('button', { name: 'Отправить отклик', exact: true }).click()
  const offer = (await (await offerResponse).json()).data
  await expect(page.getByRole('heading', { name: 'Отклик отправлен' })).toBeVisible()
  expect(offer.taskId).toBe(created.id)
  expect(offer.teamId).toBe(1)
  expect(offer.decision).toBe('pending')
  const persistedOffers = (await (await request.get(apiURL + '/tasks/' + created.id + '/offers', { headers: customerHeaders })).json()).data
  expect(persistedOffers.some(item => item.id === offer.id)).toBeTruthy()
  await page.getByRole('link', { name: 'Посмотреть мои отклики' }).click()
  await expect(page.getByText('Разработать тренажёр парных диалогов.').first()).toBeVisible()
  expect(browserErrors).toEqual([])
})

test('публикация показывает ошибки полей и сохраняет черновик', async ({ page }, testInfo) => {
  await page.goto('/tasks/new')
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')
  await page.locator('input[name="title"]').fill('Неполная задача ' + Date.now())
  await page.getByRole('button', { name: 'Создать черновик' }).click()
  await expect(page).toHaveURL(/\/tasks\/\d+\/edit/)
  await page.getByLabel('Я проверил сведения и подтверждаю публикацию').check()
  await page.getByRole('button', { name: 'Опубликовать задачу', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('Регион')
  await expect(page.getByRole('alert')).toContainText('Тема')
  await expect(page.locator('input[name="title"]')).toHaveValue(/Неполная задача/)
  await page.screenshot({ path: testInfo.outputPath('validation-errors.png'), fullPage: true })
})

test('несохранённая форма защищена при смене профиля и страницы', async ({ page }) => {
  await page.goto('/tasks/new')
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')
  await page.locator('input[name="title"]').fill('Несохранённая идея')
  page.once('dialog', dialog => dialog.dismiss())
  await page.getByLabel('Демопрофиль').selectOption('team:1')
  await expect(page.getByLabel('Демопрофиль')).toHaveValue('customer:1')
  await expect(page.locator('input[name="title"]')).toHaveValue('Несохранённая идея')
  page.once('dialog', dialog => dialog.dismiss())
  await page.getByRole('navigation').getByRole('link', { name: 'Каталог задач' }).click()
  await expect(page).toHaveURL(/\/tasks\/new$/)
  await expect(page.locator('input[name="title"]')).toHaveValue('Несохранённая идея')
  page.once('dialog', dialog => dialog.accept())
  await page.getByLabel('Демопрофиль').selectOption('team:1')
  await expect(page.getByRole('heading', { name: 'Создавайте задачи от имени заказчика' })).toBeVisible()
})
test('переход назад между редакторами не теряет несохранённый текст', async ({ page, request }) => {
  const response = await request.post(apiURL + '/tasks', { headers: customerHeaders, data: { title: 'Первый черновик ' + Date.now() } })
  expect(response.ok()).toBeTruthy()
  const first = (await response.json()).data
  await page.goto('/tasks/' + first.id + '/edit')
  await expect(page.locator('input[name="title"]')).toHaveValue(first.title)
  await page.getByRole('navigation').getByRole('link', { name: '+ Создать задачу' }).click()
  await page.locator('input[name="title"]').fill('Второй черновик ' + Date.now())
  await page.getByRole('button', { name: 'Создать черновик' }).click()
  await expect(page).toHaveURL(/\/tasks\/\d+\/edit/)
  await expect(page.locator('.score-panel__number strong')).toHaveText('0')
  const secondUrl = page.url()
  await page.locator('input[name="title"]').fill('Важные несохранённые правки')
  page.once('dialog', dialog => dialog.dismiss())
  await page.goBack()
  await expect(page).toHaveURL(secondUrl)
  await expect(page.locator('input[name="title"]')).toHaveValue('Важные несохранённые правки')
  page.once('dialog', dialog => dialog.accept())
  await page.goBack()
  await expect(page).toHaveURL(new RegExp('/tasks/' + first.id + '/edit$'))
  await expect(page.locator('input[name="title"]')).toHaveValue(first.title)
})