import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ScorePanel from '../ScorePanel.vue'

describe('ScorePanel', () => {
  it('renders arbitrary backend values and updates them without deriving a rating locally', async () => {
    const task = {
      score: 43,
      readinessLabel: 'Уровень из API',
      scoreBreakdown: {
        customCriterion: { label: 'Новый критерий сервера', points: 7, maxPoints: 13, filled: true, confirmed: false, hint: 'Проверьте материал' },
        context: { label: 'Контекст', points: 2, maxPoints: 8, filled: true, confirmed: true, hint: 'Проверено заказчиком' },
      },
    }
    const wrapper = mount(ScorePanel, { props: { task } })
    expect(wrapper.get('.score-panel__number strong').text()).toBe('43')
    expect(wrapper.get('.score-panel__level').text()).toBe('Уровень из API')
    expect(wrapper.text()).toContain('Новый критерий сервера')
    expect(wrapper.text()).toContain('Проверьте материал')
    const progress = wrapper.findAll('progress')
    expect(progress).toHaveLength(2)
    expect(progress[0].attributes()).toMatchObject({ value: '7', max: '13', 'aria-label': 'Новый критерий сервера' })
    expect(progress[1].attributes()).toMatchObject({ value: '2', max: '8' })

    await wrapper.setProps({ task: { ...task, score: 19, readinessLabel: 'Другой уровень' } })
    expect(wrapper.get('.score-panel__number strong').text()).toBe('19')
    expect(wrapper.get('.score-panel__level').text()).toBe('Другой уровень')
    expect(wrapper.findAll('progress')[0].attributes('value')).toBe('7')
  })

  it('shows an unavailable rating as unknown instead of inventing a zero score', () => {
    const wrapper = mount(ScorePanel)
    expect(wrapper.get('.score-panel__number strong').text()).toBe('—')
    expect(wrapper.findAll('progress')).toHaveLength(0)
    expect(wrapper.find('.score-panel__level').exists()).toBe(false)
  })
})
