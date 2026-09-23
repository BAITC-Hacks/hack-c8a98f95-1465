import { beforeEach, describe, expect, it, vi } from 'vitest'

import axios from 'axios'

// Exercise Axios serialization with an in-memory transport; no network requests are sent.
const request = vi.fn()
const originalAdapter = axios.defaults.adapter
axios.defaults.adapter = request
const { api, ApiError, isRequestCanceled, setProfile } = await import('../api')
axios.defaults.adapter = originalAdapter

function transportCall(index) {
  const config = request.mock.calls[index][0]
  return { ...config, data: typeof config.data === 'string' ? JSON.parse(config.data) : config.data }
}

beforeEach(() => {
  request.mockReset()
  setProfile(null)
})

const response = (data, extra = {}) => ({ status: 200, data: { data, ...extra } })

describe('backend API contract', () => {
  it('loads customer offers and sends only the explicit decision to the API', async () => {
    setProfile({ role: 'customer', id: 1 })
    const offer = { id: 12, taskId: 9, decision: 'pending', team: { id: 2, name: 'Qadam' } }
    request.mockResolvedValueOnce(response([offer]))
    expect(await api.taskOffers(9)).toEqual([offer])
    expect(transportCall(0)).toMatchObject({ method: 'get', url: '/tasks/9/offers' })
    request.mockResolvedValueOnce(response({ ...offer, decision: 'selected' }))
    expect((await api.decideOffer(12, 'selected')).decision).toBe('selected')
    expect(transportCall(1)).toMatchObject({
      method: 'patch', url: '/offers/12/decision', data: { decision: 'selected' },
      headers: { 'X-Demo-Role': 'customer', 'X-Demo-Id': '1' },
    })
  })
  it('preserves server ratings through create, update, publish, catalogue and offer flow', async () => {
    setProfile({ role: 'customer', id: 2 })
    const draft = { id: 9, score: 37, scoreBreakdown: { context: { points: 37, maxPoints: 47 } }, status: 'draft' }
    const create = { title: 'Практика языка', context: 'Нужен разговорный тренажёр' }
    request.mockResolvedValueOnce(response(draft))
    expect(await api.createTask(create)).toEqual(draft)
    expect(transportCall(0)).toMatchObject({
      method: 'post', url: '/tasks', data: create,
      headers: { 'X-Demo-Role': 'customer', 'X-Demo-Id': '2' },
    })

    const update = { users: 'Первокурсники', confirmedFields: ['context', 'users'] }
    request.mockResolvedValueOnce(response({ ...draft, score: 52 }))
    expect((await api.updateTask(9, update)).score).toBe(52)
    expect(transportCall(1)).toMatchObject({ method: 'put', url: '/tasks/9', data: update })

    request.mockResolvedValueOnce(response({ ...draft, status: 'published', score: 61 }))
    expect((await api.publishTask(9)).score).toBe(61)
    expect(transportCall(2)).toMatchObject({ method: 'post', url: '/tasks/9/publish', data: { confirmed: true } })

    request.mockResolvedValueOnce(response([draft], { meta: { total: 1 } }))
    expect(await api.listTasks({ sort: 'score' })).toEqual({ data: [draft], meta: { total: 1 } })

    setProfile({ role: 'team', id: 4 })
    const offer = { idea: 'Диалоги', plan: 'Прототип и тест', timeline: '4 недели', prototypeLink: null }
    request.mockResolvedValueOnce(response({ id: 12, decision: 'pending' }))
    expect(await api.createOffer(9, offer)).toEqual({ id: 12, decision: 'pending' })
    expect(transportCall(4)).toMatchObject({
      method: 'post', url: '/tasks/9/offers', data: offer,
      headers: { 'X-Demo-Role': 'team', 'X-Demo-Id': '4' },
    })
  })

  it('forwards catalogue filtering and cancellation without discarding metadata', async () => {
    const params = { q: 'язык', category: 'Языки', region: 'Алматы', scope: 'institution', sort: 'newest' }
    const signal = new AbortController().signal
    request.mockResolvedValueOnce(response([], { meta: { total: 0 } }))
    expect(await api.listTasks(params, { signal })).toEqual({ data: [], meta: { total: 0 } })
    expect(request).toHaveBeenCalledWith(expect.objectContaining({ method: 'get', url: '/tasks', params, signal }))
  })

  it('keeps the identity of a pending request when another profile is selected', async () => {
    let resolveRequest
    request.mockImplementationOnce(() => new Promise((resolve) => { resolveRequest = resolve }))
    setProfile({ role: 'customer', id: 1 })
    const pending = api.getTask(7)
    setProfile({ role: 'team', id: 5 })
    expect(request.mock.calls[0][0].headers).toMatchObject({ 'X-Demo-Role': 'customer', 'X-Demo-Id': '1' })
    resolveRequest(response({ id: 7 }))
    await pending
    setProfile(null)
    request.mockResolvedValueOnce(response([]))
    await api.myOffers()
    expect(request.mock.calls[1][0].headers['X-Demo-Role']).toBeUndefined()
    expect(request.mock.calls[1][0].headers['X-Demo-Id']).toBeUndefined()
  })

  it('returns AI questions exactly as supplied by the backend', async () => {
    const input = { description: 'Нужен разговорный тренажёр', fields: { users: 'Первокурсники' } }
    const result = { mode: 'mock', questions: [{ id: 'clarify_contact', field: 'contact', question: 'Как связаться?' }], suggestedFields: { context: input.description } }
    request.mockResolvedValueOnce(response(result))
    expect(await api.questions(input)).toEqual(result)
    expect(transportCall(0)).toMatchObject({ method: 'post', url: '/ai/questions', data: input, timeout: 50000 })
  })

  it('keeps normal requests fast and preserves OpenAI errors for retry', async () => {
    request.mockResolvedValueOnce(response({ mode: 'openai', model: 'test-model', questions: [] }))
    expect((await api.questions({ description: 'Описание' })).mode).toBe('openai')
    request.mockResolvedValueOnce(response([]))
    await api.myTasks()
    expect(transportCall(1).timeout).toBe(20000)
    request.mockRejectedValueOnce({ response: { status: 503, data: { message: 'Лимит OpenAI исчерпан.' } } })
    await expect(api.questions({ description: 'Описание' })).rejects.toMatchObject({ status: 503, message: 'Лимит OpenAI исчерпан.' })
  })

  it('retains backend field errors for an actionable validation message', async () => {
    const errors = { organization: ['Заполните поле перед публикацией.'] }
    request.mockRejectedValueOnce({ response: { status: 422, data: { message: 'Проверьте введённые данные.', errors } } })
    await expect(api.publishTask(2)).rejects.toMatchObject({ name: 'ApiError', status: 422, message: 'Проверьте введённые данные.', errors })
  })

  it('reports unreachable API and preserves cancellation for stale catalogue requests', async () => {
    request.mockRejectedValueOnce(new Error('Network Error'))
    await expect(api.profiles()).rejects.toBeInstanceOf(ApiError)
    const canceled = { code: 'ERR_CANCELED' }
    request.mockRejectedValueOnce(canceled)
    await expect(api.listTasks()).rejects.toBe(canceled)
    expect(isRequestCanceled(canceled)).toBe(true)
  })

  it('rejects an unexpected response envelope instead of displaying empty success', async () => {
    request.mockResolvedValueOnce({ status: 200, data: '<html>Not an API</html>' })
    await expect(api.getTask(1)).rejects.toMatchObject({ name: 'ApiError', status: 200 })
  })
})
