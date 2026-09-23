import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import TaskOffersView from '../TaskOffersView.vue'
import { api } from '../../lib/api'
import { session } from '../../lib/session'

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '2' } }),
  RouterLink: { template: '<a><slot /></a>' },
}))
vi.mock('../../lib/api', () => ({
  api: { getTask: vi.fn(), taskOffers: vi.fn(), decideOffer: vi.fn() },
  ApiError: class extends Error {},
}))
vi.mock('../../lib/session', async () => {
  const { reactive } = await import('vue')
  return { session: reactive({ profile: null, loading: false }), profileKey: p => p ? `${p.role}:${p.id}` : '' }
})

const task = { id: 2, ownerId: 1, title: 'Расписание', status: 'published', score: 0, scope: 'institution' }
const proposal = { id: 1, taskId: 2, teamId: 2, team: { name: 'Qadam' }, idea: 'Предложение', plan: 'План', timeline: 'Неделя', decision: 'pending' }
let wrapper
const button = text => wrapper.findAll('button').find(item => item.text() === text)
const render = async () => {
  wrapper = mount(TaskOffersView, { global: { stubs: { Icon: true } } })
  await flushPromises()
}

beforeEach(() => {
  vi.resetAllMocks()
  session.profile = { role: 'customer', id: 1 }
  api.getTask.mockResolvedValue(task)
  api.taskOffers.mockResolvedValue([{ ...proposal }])
})
afterEach(() => wrapper?.unmount())

describe('customer decisions', () => {
  it('keeps the old decision on failure and allows a successful retry', async () => {
    await render()
    api.decideOffer.mockRejectedValueOnce(new Error('Нет связи с API'))
    await button('Выбрать').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toContain('Нет связи с API')
    expect(wrapper.find('.review-offer .tag').text()).toBe('Ожидает решения')
    api.decideOffer.mockResolvedValueOnce({ ...proposal, decision: 'selected' })
    await button('Выбрать').trigger('click')
    await flushPromises()
    expect(wrapper.find('.review-offer .tag').text()).toBe('Команда выбрана')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(api.decideOffer).toHaveBeenLastCalledWith(1, 'selected')
  })

  it('disables duplicate decisions and ignores a response after switching profile', async () => {
    let resolveDecision
    api.decideOffer.mockImplementation(() => new Promise(resolve => { resolveDecision = resolve }))
    await render()
    await button('Выбрать').trigger('click')
    expect(button('Отклонить').attributes('disabled')).toBeDefined()
    session.profile = { role: 'team', id: 2 }
    await flushPromises()
    resolveDecision({ ...proposal, decision: 'selected' })
    await flushPromises()
    expect(wrapper.findAll('.review-offer')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Команда выбрана')
    expect(wrapper.text()).toContain('Отклики доступны заказчику')
    expect(api.decideOffer).toHaveBeenCalledTimes(1)
  })

  it('does not request private offers for a different customer', async () => {
    session.profile = { role: 'customer', id: 2 }
    await render()
    expect(api.taskOffers).not.toHaveBeenCalled()
    expect(wrapper.find('[role="alert"]').text()).toContain('только заказчику')
  })
})
