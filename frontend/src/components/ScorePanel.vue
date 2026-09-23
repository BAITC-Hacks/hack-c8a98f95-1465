<script setup>
import { computed } from 'vue'

const props = defineProps({ task: { type: Object, default: null } })
const criteria = computed(() => Object.entries(props.task?.scoreBreakdown || {}))
</script>

<template>
  <section class="panel score-panel" aria-label="Рейтинг готовности задачи">
    <div class="score-panel__top">
      <div>
        <p class="eyebrow">РЕЙТИНГ ГОТОВНОСТИ</p>
        <p class="score-panel__number"><strong>{{ task?.score ?? '—' }}</strong><span v-if="task?.score != null">баллов</span></p>
      </div>
      <span v-if="task?.readinessLabel" class="score-panel__level">{{ task.readinessLabel }}</span>
    </div>
    <p class="score-panel__caption muted">Рейтинг и расшифровка обновляются после сохранения карточки.</p>
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
  </section>
</template>

<style scoped>
.score-panel { padding: 1.4rem; }
.score-panel__top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.score-panel__number { display: flex; align-items: baseline; gap: .5rem; margin: .45rem 0 0; }
.score-panel__number strong { font-size: 3.5rem; line-height: 1.05; font-weight: 600; letter-spacing: -.055em; color: #1c513f; }
.score-panel__number > span { font-size: .9rem; color: #6e7c75; }
.score-panel__level { padding: .4rem .7rem; border-radius: 999px; color: #23634b; background: #e7f1e9; font-size: .75rem; font-weight: 600; }
.score-panel__caption { margin: 1rem 0 1.25rem; font-size: .8rem; line-height: 1.6; }
.score-panel__criteria { margin: 0; padding: 1.1rem 0 0; list-style: none; border-top: 1px solid #e8ece7; }
.score-panel__criteria li + li { margin-top: 1rem; }
.score-panel__criterion-heading { display: flex; justify-content: space-between; gap: 1rem; font-size: .78rem; }
.score-panel__criterion-heading strong { flex-shrink: 0; font-weight: 600; }
.score-panel__criteria progress { display: block; width: 100%; height: 5px; margin: .55rem 0 .4rem; border: none; border-radius: 99px; accent-color: #54846a; background: #e9ede7; overflow: hidden; }
.score-panel__criteria progress::-webkit-progress-bar { background: #e9ede7; border-radius: 99px; }
.score-panel__criteria progress::-webkit-progress-value { background: #54846a; border-radius: 99px; }
.score-panel__criteria progress::-moz-progress-bar { background: #54846a; border-radius: 99px; }
.score-panel__criteria p { margin: 0; font-size: .72rem; line-height: 1.5; }
.score-panel__empty { font-size: .85rem; line-height: 1.6; }
</style>
