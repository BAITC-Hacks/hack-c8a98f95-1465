import { test, expect } from '@playwright/test'

const apiURL = process.env.E2E_API_URL || 'http://127.0.0.1:18082/api'
const headers = { 'X-Demo-Role': 'customer', 'X-Demo-Id': '1' }
const questions = [
  { id: 'clarify_users', field: 'users', question: 'Как преподаватели и студенты будут согласовывать время консультаций?' },
  { id: 'clarify_materials', field: 'materials', question: 'В каком виде сейчас доступны расписания преподавателей?' },
  { id: 'clarify_successCriteria', field: 'successCriteria', question: 'Как проверите, что студенты находят актуальное время консультации?' },
]
const result = { mode: 'openai', model: 'gpt-5.4-mini', questions, suggestedFields: { users: 'Untrusted invented value' } }

async function draft(request) {
  const response = await request.post(apiURL + '/tasks', { headers, data: { title: 'AI E2E ' + Date.now(), context: 'Нужен общий календарь консультаций.' } })
  expect(response.ok()).toBeTruthy()
  return (await response.json()).data
}

for (const viewport of [{ width: 1366, height: 768 }, { width: 390, height: 844 }]) {
  test(`OpenAI questions render without changing the task ${viewport.width}`, async ({ page, request }, testInfo) => {
    await page.setViewportSize(viewport)
    const task = await draft(request)
    const errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.route('**/api/ai/questions', route => route.fulfill({ json: { data: result } }))
    await page.goto(`/tasks/${task.id}/edit`)
    await page.getByRole('button', { name: 'Получить вопросы AI', exact: true }).click()
    const panel = page.getByRole('region', { name: 'AI-помощник', exact: true })
    await expect(panel.getByRole('status')).toHaveText('OpenAI · gpt-5.4-mini')
    await expect(panel.locator('.question-list li')).toHaveCount(3)
    await expect(panel).not.toContainText('заглушка')
    await expect(page.locator('textarea[name="users"]')).toHaveValue('')
    await expect(page.locator('.score-panel__number strong')).toHaveText(String(task.score))
    await panel.getByRole('link', { name: questions[0].question }).click()
    await expect(page).toHaveURL(/#field-users$/)
    await expect(page.locator('#field-users')).toBeInViewport()
    await panel.scrollIntoViewIfNeeded()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy()
    await panel.screenshot({ path: testInfo.outputPath('openai-panel.png') })
    const persisted = (await (await request.get(`${apiURL}/tasks/${task.id}`, { headers })).json()).data
    expect(persisted.status).toBe('draft')
    expect(persisted.score).toBe(task.score)
    expect(errors).toEqual([])
  })
}

test('OpenAI errors preserve edits and can be retried without claiming success', async ({ page, request }) => {
  const task = await draft(request)
  let attempt = 0
  let release
  const pending = new Promise(resolve => { release = resolve })
  await page.route('**/api/ai/questions', async route => {
    attempt++
    if (attempt === 2) {
      await pending
      await route.fulfill({ status: 503, json: { message: 'Лимит OpenAI исчерпан.', code: 'ai_quota' } })
    } else await route.fulfill({ json: { data: result } })
  })
  await page.goto(`/tasks/${task.id}/edit`)
  await page.getByRole('button', { name: 'Получить вопросы AI', exact: true }).click()
  await expect(page.locator('.ai-panel')).toContainText('OpenAI · gpt-5.4-mini')
  await page.locator('textarea[name="users"]').fill('Несохранённые сведения заказчика')
  await page.getByRole('button', { name: 'Обновить вопросы', exact: true }).click()
  try {
    await expect(page.getByRole('button', { name: 'Готовим вопросы…', exact: true })).toBeDisabled()
  } finally { release() }
  await expect(page.getByRole('alert')).toContainText('Лимит OpenAI исчерпан.')
  await expect(page.locator('.ai-panel')).not.toContainText('OpenAI · gpt-5.4-mini')
  await expect(page.locator('.question-list li')).toHaveCount(0)
  await expect(page.locator('textarea[name="users"]')).toHaveValue('Несохранённые сведения заказчика')
  await page.getByRole('button', { name: 'Получить вопросы AI', exact: true }).click()
  await expect(page.getByRole('alert')).toHaveCount(0)
  await expect(page.locator('.question-list li')).toHaveCount(3)
})
