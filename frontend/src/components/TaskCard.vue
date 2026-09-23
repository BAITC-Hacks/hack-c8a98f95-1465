<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { scopeLabel } from '../lib/fields'
import { session } from '../lib/session'
import Icon from './Icon.vue'

const props = defineProps({ task: { type: Object, required: true } })
const isOwner = computed(() => session.profile?.role === 'customer' && Number(session.profile.id) === Number(props.task.ownerId))
const ownDraft = computed(() => props.task.status === 'draft' && isOwner.value)
const destination = computed(() => `/tasks/${props.task.id}${ownDraft.value ? '/edit' : ''}`)
const topicStyle = computed(() => {
  const category = (props.task.category || '').toLocaleLowerCase('ru')
  if (/язык|англ|библиот|книг/.test(category)) return { tone: 'blue', icon: 'book-open' }
  if (/навигац/.test(category)) return { tone: 'violet', icon: 'map-pin' }
  if (/обратн|связ/.test(category)) return { tone: 'violet', icon: 'users' }
  if (/цифр|технолог|it|информ/.test(category)) return { tone: 'blue', icon: 'grid' }
  if (/эколог|устойчив|сред/.test(category)) return { tone: 'green', icon: 'layers' }
  if (/инклюз|социал|доступ|здоров/.test(category)) return { tone: 'violet', icon: 'users' }
  if (/управ|организ|процесс/.test(category)) return { tone: 'amber', icon: 'briefcase' }
  return { tone: 'green', icon: 'file-text' }
})
</script>

<template>
  <article class="task-card panel" :class="`task-tone-${topicStyle.tone}`">
    <div class="task-card-top">
      <div class="task-card-category"><span class="task-topic-icon"><Icon :name="topicStyle.icon" :size="20" /></span><span class="tag">{{ task.category || 'Тема не указана' }}</span></div>
      <span v-if="task.status === 'draft'" class="tag tag-neutral task-draft-label">Черновик</span>
    </div>
    <h2><RouterLink :to="destination">{{ task.title }}</RouterLink></h2>
    <p class="task-card-description">{{ task.context || 'Описание пока не добавлено.' }}</p>
    <div class="task-card-organization"><Icon name="briefcase" :size="15" /><span>{{ task.organization || 'Организация не указана' }}</span></div>
    <div class="task-card-meta">
      <span><Icon name="map-pin" :size="15" />{{ task.region || 'Регион не указан' }}</span>
      <span><Icon name="users" :size="15" />{{ scopeLabel(task.scope) }}</span>
    </div>
    <div class="task-card-rating">
      <div><span class="task-rating-label">Рейтинг готовности</span><span class="task-readiness-label">{{ task.readinessLabel || 'Полнота описания задачи' }}</span></div>
      <span class="task-card-score" :aria-label="`Рейтинг готовности: ${task.score} из 100`"><strong>{{ task.score }}</strong><span>/ 100</span></span>
    </div>
    <div class="task-card-footer">
      <RouterLink v-if="isOwner && task.status === 'published'" class="task-card-offers" :to="`/tasks/${task.id}/offers`" :aria-label="`Отклики на задачу: ${task.title}`"><Icon name="users" :size="18" />Отклики <strong>{{ task.offersCount ?? 0 }}</strong></RouterLink>
      <RouterLink class="task-card-open" :to="destination" :aria-label="`${ownDraft ? 'Редактировать черновик' : 'Открыть задачу'}: ${task.title}`">{{ ownDraft ? 'Редактировать черновик' : 'Открыть задачу' }}<Icon name="arrow-up-right" :size="18" /></RouterLink>
    </div>
  </article>
</template>

<style scoped>
.task-card { --topic-color: #147d64; --topic-bg: #e9f5ef; padding: 24px; display: flex; flex-direction: column; gap: 0; overflow: hidden; transition: border-color .18s, box-shadow .18s, transform .18s; }
.task-card:hover { border-color: #b2cebf; box-shadow: 0 8px 26px #182f2310; transform: translateY(-2px); }
.task-tone-blue { --topic-color: #4775bd; --topic-bg: #edf2fc; }
.task-tone-violet { --topic-color: #8861b3; --topic-bg: #f4effb; }
.task-tone-amber { --topic-color: #a07128; --topic-bg: #fbf3e6; }
.task-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 20px; }
.task-card-category { min-width: 0; display: flex; align-items: center; gap: 9px; }
.task-topic-icon { color: var(--topic-color); background: var(--topic-bg); display: grid; place-items: center; width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; }
.task-card-category .tag { color: var(--topic-color); background: none; padding: 0; font-size: .72rem; line-height: 1.4; }
.task-draft-label { font-size: .65rem; flex-shrink: 0; }
.task-card h2 { font-size: 1.12rem; font-weight: 600; line-height: 1.5; letter-spacing: 0; margin-bottom: 10px; }
.task-card h2 a:hover { color: var(--primary, #147d64); }
.task-card-description { color: var(--muted, #69766e); font-size: .81rem; line-height: 1.7; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 20px; }
.task-card-organization { display: flex; align-items: flex-start; gap: 7px; margin-top: auto; padding-top: 0; margin-bottom: 9px; font-size: .76rem; color: #59675f; }
.task-card-organization svg { margin-top: 3px; flex-shrink: 0; }
.task-card-meta { display: flex; flex-wrap: wrap; gap: 8px 13px; color: var(--muted, #69766e); font-size: .71rem; margin-bottom: 19px; }
.task-card-meta > span { display: inline-flex; align-items: center; gap: 5px; }
.task-card-meta svg { flex-shrink: 0; }
.task-card-rating { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-top: 1px solid var(--line, #e7ece8); }
.task-rating-label { display: block; color: #47584d; font-size: .72rem; font-weight: 500; }
.task-readiness-label { display: block; color: var(--muted, #69766e); font-size: .66rem; margin-top: 2px; }
.task-card-score { display: flex; align-items: baseline; gap: 4px; white-space: nowrap; color: var(--primary, #147d64); }
.task-card-score strong { font-size: 1.8rem; font-weight: 600; line-height: 1; letter-spacing: 0; }
.task-card-score > span { color: #89968d; font-size: .69rem; }
.task-card-footer { display: flex; flex-direction: column; gap: 8px; border-top: 0; padding-top: 0; }
.task-card-offers { display: flex; align-items: center; gap: 8px; padding: 10px 0; color: var(--primary); font-size: .85rem; }
.task-card-offers strong { margin-left: auto; font-variant-numeric: tabular-nums; }
.task-card-organization span, .task-card-meta > span { overflow-wrap: anywhere; min-width: 0; }
.task-card-open { display: flex; width: 100%; align-items: center; justify-content: space-between; padding: 11px 13px; border-radius: 8px; color: var(--primary, #147d64); background: var(--surface-muted, #f5f8f5); font-size: .78rem; font-weight: 550; transition: background .18s; }
.task-card-open:hover { background: #e8f2ec; }
@media (prefers-reduced-motion: reduce) { .task-card:hover { transform: none; } }
</style>
