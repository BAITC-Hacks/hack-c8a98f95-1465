import axios from 'axios'

const client = axios.create({
  baseURL: (import.meta.env.VITE_API_URL || 'http://127.0.0.1:18081/api').replace(/\/+$/, ''),
  timeout: 20000,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
})

let profileHeaders = {}

export function setProfile(profile) {
  profileHeaders = profile
    ? { 'X-Demo-Role': profile.role, 'X-Demo-Id': String(profile.id) }
    : {}
}

export class ApiError extends Error {
  constructor(message, status = null, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

export function isRequestCanceled(error) {
  return axios.isCancel(error) || error?.code === 'ERR_CANCELED'
}

async function request(method, url, { data, params, signal } = {}) {
  // Capture the active identity now, so switching profiles cannot alter an in-flight request.
  const headers = { ...profileHeaders }
  try {
    const response = await client.request({ method, url, data, params, signal, headers })
    if (!response.data || !Object.prototype.hasOwnProperty.call(response.data, 'data')) {
      throw new ApiError('Сервер вернул неожиданный ответ. Попробуйте ещё раз.', response.status)
    }
    return response.data
  } catch (error) {
    if (isRequestCanceled(error) || error instanceof ApiError) throw error
    const body = error.response?.data
    const message = typeof body?.message === 'string'
      ? body.message
      : error.code === 'ECONNABORTED'
        ? 'Сервер не ответил вовремя. Попробуйте ещё раз.'
        : error.response
          ? 'Не удалось выполнить запрос. Попробуйте ещё раз.'
          : 'Нет связи с API. Проверьте подключение и запуск backend.'
    throw new ApiError(message, error.response?.status ?? null, body?.errors || {})
  }
}

const unwrap = async (promise) => (await promise).data

export const api = {
  profiles: () => unwrap(request('get', '/demo/profiles')),
  listTasks: (params = {}, { signal } = {}) => request('get', '/tasks', { params, signal }),
  getTask: (id) => unwrap(request('get', `/tasks/${id}`)),
  createTask: (payload) => unwrap(request('post', '/tasks', { data: payload })),
  updateTask: (id, payload) => unwrap(request('put', `/tasks/${id}`, { data: payload })),
  publishTask: (id) => unwrap(request('post', `/tasks/${id}/publish`, { data: { confirmed: true } })),
  questions: (payload) => unwrap(request('post', '/ai/questions', { data: payload })),
  createOffer: (id, payload) => unwrap(request('post', `/tasks/${id}/offers`, { data: payload })),
  taskOffers: (id) => unwrap(request('get', `/tasks/${id}/offers`)),
  decideOffer: (id, decision) => unwrap(request('patch', `/offers/${id}/decision`, { data: { decision } })),
  myTasks: () => unwrap(request('get', '/my/tasks')),
  myOffers: () => unwrap(request('get', '/my/offers')),
}
