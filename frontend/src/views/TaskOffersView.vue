<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError as RequestError } from '../lib/api'
import { session, profileKey } from '../lib/session'
import { scopeLabel } from '../lib/fields'
import ApiError from '../components/ApiError.vue'
import Icon from '../components/Icon.vue'

const route = useRoute()
const task = ref(null)
const offers = ref([])
const loading = ref(false)
const error = ref(null)
const saving = ref({})
const decisionErrors = ref({})
const announcement = ref('')
const filter = ref('all')
let requestId = 0
const identity = computed(() => profileKey(session.profile))
const isCustomer = computed(() => session.profile?.role === 'customer')
const counts = computed(() => ({
  all: offers.value.length,
  pending: offers.value.filter(offer => offer.decision === 'pending').length,
  selected: offers.value.filter(offer => offer.decision === 'selected').length,
  rejected: offers.value.filter(offer => offer.decision === 'rejected').length,
}))
const visibleOffers = computed(() => offers.value.filter(offer => filter.value === 'all' || offer.decision === filter.value))
const labels = { pending: 'Ожидает решения', selected: 'Команда выбрана', rejected: 'Отклонён' }
const hasPending = computed(() => Object.keys(saving.value).length > 0)
const safeLink = value => typeof value === 'string' && /^https?:\/\//i.test(value)

async function load() {
  const current = ++requestId
  task.value = null
  offers.value = []
  error.value = null
  saving.value = {}
  decisionErrors.value = {}
  announcement.value = ''
  loading.value = false
  if (!isCustomer.value) return
  loading.value = true
  try {
    const result = await api.getTask(route.params.id)
    if (current !== requestId) return
    if (Number(result.ownerId) !== Number(session.profile.id)) {
      throw new RequestError('Отклики доступны только заказчику этой задачи.', 403)
    }
    task.value = result
    const resultOffers = await api.taskOffers(result.id)
    if (current === requestId) offers.value = resultOffers
  } catch (cause) {
    if (current === requestId) error.value = cause
  } finally {
    if (current === requestId) loading.value = false
  }
}

async function decide(offer, decision) {
  if (saving.value[offer.id] || loading.value || !isCustomer.value || offer.decision === decision) return
  const current = requestId
  saving.value[offer.id] = decision
  delete decisionErrors.value[offer.id]
  announcement.value = ''
  try {
    const saved = await api.decideOffer(offer.id, decision)
    if (current !== requestId) return
    offers.value = offers.value.map(item => item.id === saved.id ? saved : item)
    announcement.value = `${saved.team?.name || 'Отклик #' + saved.id}: ${labels[saved.decision]}.`
  } catch (cause) {
    if (current === requestId) decisionErrors.value[offer.id] = cause
  } finally {
    if (current === requestId) delete saving.value[offer.id]
  }
}

watch([() => route.params.id, identity], () => { filter.value = 'all'; load() }, { immediate: true })
onBeforeUnmount(() => { requestId++ })
</script>

<template>
  <section class="stack review-page" :aria-busy="loading || session.loading">
    <RouterLink class="text-link back-link" to="/workspace">Мой кабинет</RouterLink>
    <header class="page-heading">
      <h1>Отклики на задачу</h1>
      <button v-if="isCustomer" class="icon-button" type="button" aria-label="Обновить отклики" title="Обновить отклики" :disabled="loading || hasPending" @click="load"><Icon name="refresh-cw" /></button>
    </header>
    <p v-if="session.loading || loading" role="status" class="loading-state">Загружаем отклики...</p>
    <div v-else-if="!isCustomer" class="empty-state">
      <h2>Отклики доступны заказчику</h2>
      <p class="muted">Выберите профиль заказчика этой задачи.</p>
      <RouterLink class="button button-secondary" :to="`/tasks/${route.params.id}`">Открыть задачу <Icon name="arrow-right" /></RouterLink>
    </div>
    <ApiError v-else-if="error" :error="error" retry @retry="load" />
    <template v-else-if="task">
      <div class="review-task">
        <h2><RouterLink :to="`/tasks/${task.id}`">{{ task.title }} <Icon name="arrow-up-right" /></RouterLink></h2>
        <dl class="review-task-facts">
          <div><dt>Учреждение</dt><dd>{{ task.organization || 'Не указано' }}</dd></div>
          <div><dt>Регион</dt><dd>{{ task.region || 'Не указан' }}</dd></div>
          <div><dt>Охват</dt><dd>{{ scopeLabel(task.scope) }}</dd></div>
          <div><dt>Готовность описания</dt><dd>{{ task.score }} / 100 · {{ task.readinessLabel }}</dd></div>
        </dl>
      </div>
      <div class="review-controls">
        <dl class="review-metrics" aria-label="Статистика откликов">
        <div><dt>Всего</dt><dd>{{ counts.all }}</dd></div>
        <div><dt>Ожидают решения</dt><dd>{{ counts.pending }}</dd></div>
        <div><dt>Выбраны</dt><dd>{{ counts.selected }}</dd></div>
        <div><dt>Отклонены</dt><dd>{{ counts.rejected }}</dd></div>
        </dl>
        <div class="form-field review-filter">
          <label for="offer-filter">Решение</label>
          <select id="offer-filter" v-model="filter"><option value="all">Все отклики</option><option value="pending">Ожидают решения</option><option value="selected">Выбраны</option><option value="rejected">Отклонены</option></select>
        </div>
      </div>
      <p v-if="announcement" role="status" class="success-message">{{ announcement }}</p>
      <div v-if="!offers.length" class="empty-state">
        <Icon name="users" :size="32" /><h2>Откликов пока нет</h2>
        <p class="muted">{{ task.status === 'draft' ? 'Задача ещё не опубликована.' : 'Предложения команд появятся здесь.' }}</p>
        <RouterLink v-if="task.status === 'draft'" class="button" :to="`/tasks/${task.id}/edit`">Редактировать задачу <Icon name="arrow-right" /></RouterLink>
      </div>
      <div v-else-if="!visibleOffers.length" class="empty-state"><h2>Нет откликов с этим решением</h2><button type="button" class="button button-secondary" @click="filter = 'all'">Все отклики</button></div>
      <div v-else class="stack">
        <article v-for="offer in visibleOffers" :key="offer.id" class="panel review-offer" :aria-label="`Отклик команды ${offer.team?.name || offer.teamId}`" :aria-busy="Boolean(saving[offer.id])">
          <header class="review-offer-heading">
            <div><p class="eyebrow">Отклик #{{ offer.id }}</p><h2>{{ offer.team?.name || 'Команда #' + offer.teamId }}</h2><p class="muted">{{ offer.team?.organization }}</p></div>
            <span class="tag" :class="'decision-' + offer.decision">{{ labels[offer.decision] || offer.decision }}</span>
          </header>
          <div class="review-proposal">
            <div><h3>Идея решения</h3><p>{{ offer.idea }}</p></div>
            <div><h3>План работы</h3><p>{{ offer.plan }}</p></div>
            <div><h3>Сроки</h3><p>{{ offer.timeline }}</p><a v-if="safeLink(offer.prototypeLink)" class="text-link" :href="offer.prototypeLink" target="_blank" rel="noopener noreferrer">Открыть прототип <Icon name="arrow-up-right" /></a></div>
          </div>
          <details v-if="offer.team" class="review-team"><summary>Компетенции команды</summary><dl><div><dt>Интересы</dt><dd>{{ offer.team.interests?.join(', ') || 'Не указаны' }}</dd></div><div><dt>Навыки</dt><dd>{{ offer.team.skills?.join(', ') || 'Не указаны' }}</dd></div><div><dt>Технологии</dt><dd>{{ offer.team.technologies?.join(', ') || 'Не указаны' }}</dd></div></dl></details>
          <ApiError v-if="decisionErrors[offer.id]" :error="decisionErrors[offer.id]" />
          <footer class="review-actions">
            <button class="button" type="button" :disabled="Boolean(saving[offer.id]) || offer.decision === 'selected'" @click="decide(offer, 'selected')"><Icon name="check" />{{ saving[offer.id] === 'selected' ? 'Сохраняем...' : 'Выбрать' }}</button>
            <button class="button button-secondary button-reject" type="button" :disabled="Boolean(saving[offer.id]) || offer.decision === 'rejected'" @click="decide(offer, 'rejected')"><Icon name="x" />{{ saving[offer.id] === 'rejected' ? 'Сохраняем...' : 'Отклонить' }}</button>
          </footer>
        </article>
      </div>
    </template>
  </section>
</template>

<style scoped>
.review-page { gap: 12px; }
.review-page .page-heading { margin-bottom: 0; align-items: center; flex-direction: row; }
.review-page h1 { margin: 0; font-size: 1.8rem; }
.review-task { padding: 12px 0; border-block: 1px solid var(--border); }
.review-task h2 { font-size: 1.25rem; overflow-wrap: anywhere; }
.review-task h2 a:hover { color: var(--primary); }
.review-task-facts { display: grid; grid-template-columns: 2fr 1fr 1fr 1.4fr; gap: 16px; margin: 16px 0 0; }
.review-task-facts dt, .review-team dt { color: var(--muted); font-size: .8rem; }
.review-task-facts dd, .review-team dd { margin: 4px 0 0; overflow-wrap: anywhere; }
.review-controls { display: grid; grid-template-columns: minmax(0, 1fr) 230px; gap: 24px; align-items: end; padding-block: 4px 12px; }
.review-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin: 0; }
.review-metrics dt { color: var(--muted); font-size: .85rem; }
.review-metrics dd { margin: 8px 0 0; font-size: 1.8rem; line-height: 1; font-weight: 600; font-variant-numeric: tabular-nums; }
.review-offer { border-radius: 8px; padding: 20px; }
.review-offer-heading { display: flex; align-items: start; justify-content: space-between; gap: 16px; }
.review-offer-heading h2 { font-size: 1.2rem; overflow-wrap: anywhere; }
.review-offer-heading .eyebrow { margin-bottom: 4px; }
.review-offer-heading .tag { flex-shrink: 0; }
.review-offer-heading .muted { margin-bottom: 0; font-size: .85rem; }
.review-proposal { display: grid; grid-template-columns: 1.3fr 1.3fr 1fr; gap: 24px; margin: 14px 0; }
.review-proposal h3 { font-size: .85rem; margin-bottom: 8px; }
.review-proposal p { margin-bottom: 8px; white-space: pre-wrap; overflow-wrap: anywhere; font-size: .95rem; }
.review-team { border-top: 1px solid var(--border); padding: 8px 0; }
.review-team summary { cursor: pointer; color: var(--primary); font-size: .85rem; }
.review-team dl { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.review-actions { display: flex; flex-wrap: wrap; gap: 12px; border-top: 1px solid var(--border); padding-top: 16px; }
.review-actions .button { min-width: 145px; }
.button-reject { color: #a03a46; border-color: #dfbdc3; }
@media (max-width: 1100px) {
  .review-controls { grid-template-columns: 1fr; }
  .review-filter { max-width: 300px; }
  .review-task-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .review-proposal { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
  .review-page h1 { font-size: 1.5rem; }
  .review-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .review-task-facts, .review-proposal, .review-team dl { grid-template-columns: 1fr; }
  .review-offer-heading { flex-direction: column; }
  .review-actions { flex-direction: column; }
  .review-actions .button { width: 100%; }
}
</style>
