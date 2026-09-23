<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({ task: { type: Object, default: null } })
const criteria = computed(() => Object.entries(props.task?.scoreBreakdown || {}))
</script>

<template>
  <section class="panel score-panel" aria-label="Рейтинг готовности задачи">
    <div class="score-panel__heading"><Icon name="chart" /><h2>Готовность описания</h2></div>
    <p class="score-panel__intro muted">Насколько полно описана задача и подтверждены сведения для команды.</p>
    <div class="score-panel__top">
      <p class="score-panel__number"><strong>{{ task?.score ?? '—' }}</strong><span v-if="task?.score != null">баллов</span></p>
      <span v-if="task?.readinessLabel" class="score-panel__level">{{ task.readinessLabel }}</span>
    </div>
    <p class="score-panel__caption muted">Это оценка описания, а не сложности или важности проекта.</p>
    <ul v-if="criteria.length" class="score-panel__criteria">
      <li v-for="[key, criterion] in criteria" :key="key">
        <div class="score-panel__criterion-heading">
          <span>{{ criterion.label }}</span>
          <strong>{{ criterion.points }} <span class="muted">/ {{ criterion.maxPoints }}</span></strong>
        </div>
        <progress :value="criterion.points" :max="criterion.maxPoints" :aria-label="criterion.label" />
        <p class="muted">{{ criterion.hint }}</p>
      </li>
    </ul>
    <p v-else class="muted score-panel__empty">Сохраните задачу, чтобы получить рейтинг и рекомендации.</p>
    <p class="score-panel__source"><Icon name="shield-check" /><span>Сохраните изменения, чтобы увидеть актуальную оценку и подсказки.</span></p>
  </section>
</template>

<style scoped>
.score-panel { padding: 1.4rem; }
.score-panel__heading { display: flex; align-items: center; gap: .55rem; color: var(--primary); }
.score-panel__heading h2 { margin: 0; color: var(--text); font-size: 1rem; }
.score-panel__heading :deep(svg) { width: 20px; height: 20px; }
.score-panel__intro { margin: .75rem 0 1rem; font-size: .8rem; line-height: 1.6; }
.score-panel__top { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
.score-panel__number { display: flex; align-items: baseline; gap: .45rem; margin: 0; flex-shrink: 0; }
.score-panel__number strong { font-size: 3.4rem; line-height: 1.1; font-weight: 650; letter-spacing: 0; color: var(--primary); }
.score-panel__number > span { font-size: .8rem; color: var(--muted); }
.score-panel__level { padding: .4rem .6rem; border-radius: 7px; color: #24634e; background: #eaf5ef; font-size: .72rem; font-weight: 600; line-height: 1.4; }
.score-panel__caption { margin: .9rem 0 1.2rem; font-size: .75rem; line-height: 1.55; }
.score-panel__criteria { margin: 0; padding: 1.15rem 0 0; list-style: none; border-top: 1px solid var(--border); }
.score-panel__criteria li + li { margin-top: 1rem; }
.score-panel__criterion-heading { display: flex; justify-content: space-between; gap: 1rem; font-size: .78rem; }
.score-panel__criterion-heading strong { flex-shrink: 0; font-weight: 600; }
.score-panel__criteria progress { display: block; width: 100%; height: 5px; margin: .6rem 0 .4rem; border: none; border-radius: 99px; accent-color: var(--primary); background: #edf1ed; overflow: hidden; }
.score-panel__criteria progress::-webkit-progress-bar { background: #edf1ed; border-radius: 99px; }
.score-panel__criteria progress::-webkit-progress-value { background: var(--primary); border-radius: 99px; }
.score-panel__criteria progress::-moz-progress-bar { background: var(--primary); border-radius: 99px; }
.score-panel__criteria p { margin: 0; font-size: .72rem; line-height: 1.5; }
.score-panel__empty { font-size: .85rem; line-height: 1.6; }
.score-panel__source { display: flex; align-items: flex-start; gap: .5rem; margin: 1.25rem 0 0; padding-top: 1rem; border-top: 1px solid var(--border); font-size: .7rem; line-height: 1.5; color: var(--muted); }
.score-panel__source :deep(svg) { width: 15px; height: 15px; flex-shrink: 0; color: var(--primary); margin-top: 2px; }
</style>
