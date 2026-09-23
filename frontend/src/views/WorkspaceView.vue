<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'
import { session, profileKey } from '../lib/session'
import ApiError from '../components/ApiError.vue'
import TaskCard from '../components/TaskCard.vue'
import Icon from '../components/Icon.vue'

const items = ref([])
const loading = ref(false)
const error = ref(null)
let requestId = 0
const isCustomer = computed(() => session.profile?.role === 'customer')
const firstDraft = computed(() => isCustomer.value ? items.value.find(item => item.status === 'draft') : null)
const primaryCount = computed(() => items.value.filter(item => isCustomer.value ? item.status === 'published' : item.decision === 'pending').length)
const secondaryCount = computed(() => items.value.filter(item => isCustomer.value ? item.status === 'draft' : item.decision === 'selected').length)
const offerCount = computed(() => items.value.reduce((total, task) => total + (task.offersCount ?? 0), 0))
const identity = computed(() => session.profile ? profileKey(session.profile) : '')
const decisionLabels = { pending: 'Ожидает решения', selected: 'Команда выбрана', rejected: 'Отклонён' }
const dateFormatter = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })
function formattedDate(value) {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : dateFormatter.format(date)
}
function safeLink(value) { return typeof value === 'string' && /^https?:\/\//i.test(value) }

async function load() {
  const current = ++requestId
  items.value = []
  error.value = null
  loading.value = false
  if (!session.profile) return
  loading.value = true
  try {
    const result = isCustomer.value ? await api.myTasks() : await api.myOffers()
    if (current === requestId) items.value = result
  } catch (cause) {
    if (current === requestId) error.value = cause
  } finally {
    if (current === requestId) loading.value = false
  }
}

watch(identity, load, { immediate: true })
onBeforeUnmount(() => { requestId++ })
</script>

<template>
  <section class="stack" :aria-busy="loading || session.loading">
    <header class="page-heading">
      <div>
        <p class="eyebrow">РАБОЧЕЕ ПРОСТРАНСТВО</p>
        <h1>{{ isCustomer ? 'Мои задачи' : session.profile ? 'Мои отклики' : 'Ваше рабочее пространство' }}</h1>
        <p class="muted">{{ session.profile ? (isCustomer ? 'Управляйте черновиками и опубликованными задачами от имени ' : 'Следите за предложениями и решениями заказчиков для команды ') + session.profile.name + '.' : 'Выберите профиль в меню, чтобы продолжить.' }}</p>
      </div>
      <RouterLink v-if="isCustomer" class="button" to="/tasks/new"><Icon name="plus" />Создать задачу</RouterLink>
      <RouterLink v-else-if="session.profile" class="button" to="/catalog">Найти задачу <Icon name="arrow-up-right" /></RouterLink>
    </header>
    <p v-if="loading || session.loading" class="panel loading-state" role="status">Загружаем {{ isCustomer ? 'задачи' : 'отклики' }}…</p>
    <ApiError v-else-if="error" :error="error" retry @retry="load" />
    <div v-else-if="!session.profile" class="panel empty-state">
      <span class="workspace-empty-icon"><Icon name="users" /></span>
      <h2>Выберите демопрофиль</h2>
      <p class="muted">Заказчику доступны его задачи и черновики. Команде — отправленные отклики и решения заказчиков.</p>
      <RouterLink class="button button-secondary" to="/catalog">Посмотреть задачи <Icon name="arrow-right" /></RouterLink>
    </div>
    <template v-else>
      <section class="workspace-metrics" :aria-label="isCustomer ? 'Статистика моих задач' : 'Статистика моих откликов'">
        <div class="panel workspace-metric"><span class="workspace-metric-icon"><Icon :name="isCustomer ? 'file-text' : 'send'" /></span><div><p>{{ isCustomer ? 'Всего задач' : 'Отправлено откликов' }}</p><strong>{{ items.length }}</strong></div></div>
        <div class="panel workspace-metric"><span class="workspace-metric-icon"><Icon :name="isCustomer ? 'grid' : 'clock'" /></span><div><p>{{ isCustomer ? 'В каталоге' : 'Ожидают решения' }}</p><strong>{{ primaryCount }}</strong></div></div>
        <div class="panel workspace-metric"><span class="workspace-metric-icon"><Icon :name="isCustomer ? 'file-text' : 'check-circle'" /></span><div><p>{{ isCustomer ? 'Черновики' : 'Команда выбрана' }}</p><strong>{{ secondaryCount }}</strong></div></div>
      </section>

      <div v-if="isCustomer" class="workspace-offer-summary"><Icon name="users" /><span>Отклики на ваши задачи: <strong>{{ offerCount }}</strong></span></div>
      <section v-if="!isCustomer || !offerCount" class="workspace-guide">
        <span class="workspace-guide-icon"><Icon :name="isCustomer ? 'sparkles' : 'search'" /></span>
        <div>
          <p class="eyebrow">СЛЕДУЮЩИЙ ШАГ</p>
          <h2>{{ isCustomer ? (firstDraft ? 'Подготовьте черновик к публикации' : 'Опишите задачу для команды') : 'Найдите проект для вашей команды' }}</h2>
          <p class="muted">{{ isCustomer ? (firstDraft ? 'Дополните описание, проверьте рейтинг и опубликуйте задачу. После этого команды смогут предложить решения.' : 'Расскажите о проблеме, уточните детали с помощником и опубликуйте задачу в каталоге.') : 'Выберите задачу по теме и региону. Изучите требования и отправьте идею, план и сроки.' }}</p>
          <RouterLink class="text-link" :to="isCustomer ? (firstDraft ? '/tasks/' + firstDraft.id + '/edit' : '/tasks/new') : '/catalog'">{{ isCustomer ? (firstDraft ? 'Продолжить черновик' : 'Начать с описания') : 'Перейти к каталогу' }}<Icon name="arrow-right" /></RouterLink>
        </div>
      </section>

      <div class="results-heading"><h2 class="workspace-list-title">{{ isCustomer ? 'Ваши задачи' : 'История откликов' }} <span>{{ items.length }}</span></h2><button class="button button-secondary" type="button" @click="load">Обновить</button></div>
      <div v-if="!items.length" class="panel empty-state">
        <span class="workspace-empty-icon"><Icon :name="isCustomer ? 'file-text' : 'send'" /></span>
        <h2>{{ isCustomer ? 'Пока нет задач' : 'Пока нет откликов' }}</h2>
        <p class="muted">{{ isCustomer ? 'Начните с короткого описания проблемы. Сначала задача сохранится как черновик, видимый только вам.' : 'Отправьте первое предложение из карточки задачи. Здесь появится ваш отклик и его статус.' }}</p>
        <RouterLink class="button" :to="isCustomer ? '/tasks/new' : '/catalog'">{{ isCustomer ? 'Создать первую задачу' : 'Открыть каталог' }}<Icon name="arrow-right" /></RouterLink>
      </div>
      <div v-else-if="isCustomer" class="task-grid"><TaskCard v-for="task in items" :key="task.id" :task="task" /></div>
      <div v-else class="stack">
        <article v-for="offer in items" :key="offer.id" class="panel offer-card">
          <div class="page-heading offer-card-heading">
            <div>
              <p class="eyebrow">ОТКЛИК #{{ offer.id }} · {{ formattedDate(offer.createdAt) }}</p>
              <h2><RouterLink :to="'/tasks/' + offer.taskId">Задача #{{ offer.taskId }} <Icon name="arrow-up-right" /></RouterLink></h2>
            </div>
            <span class="tag" :class="'decision-' + offer.decision"><span class="decision-dot"></span>{{ decisionLabels[offer.decision] || offer.decision }}</span>
          </div>
          <div class="prose"><h3>Идея решения</h3><p>{{ offer.idea }}</p></div>
          <details class="offer-details"><summary>План и сроки</summary><div class="prose"><h3>План</h3><p>{{ offer.plan }}</p><h3>Сроки</h3><p>{{ offer.timeline }}</p><p v-if="safeLink(offer.prototypeLink)"><a :href="offer.prototypeLink" target="_blank" rel="noopener noreferrer">Открыть прототип <Icon name="arrow-up-right" /></a></p></div></details>
          <p v-if="offer.decidedAt" class="muted offer-updated"><Icon name="clock" />Решение обновлено {{ formattedDate(offer.decidedAt) }}</p>
        </article>
      </div>
    </template>
  </section>
</template>

<style scoped>
.workspace-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
.workspace-offer-summary { display: flex; align-items: center; gap: 10px; padding-block: 16px; border-block: 1px solid var(--border); color: var(--primary); }
.workspace-metric { display: flex; align-items: center; gap: .9rem; padding: 1.2rem 1.4rem; }
.workspace-metric-icon { display: inline-flex; padding: .7rem; color: var(--primary); border-radius: 10px; background: #eff6f2; }
.workspace-metric p { margin: 0 0 .45rem; font-size: .75rem; color: var(--muted); }
.workspace-metric strong { font-size: 1.8rem; line-height: 1; font-weight: 600; letter-spacing: 0; }
.workspace-guide { display: flex; align-items: flex-start; gap: 1rem; padding: 1.6rem 1.75rem; background: #f1f8f4; border-color: #dce9e1; }
.workspace-guide-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 42px; height: 42px; background: white; border: 1px solid #dce9e1; border-radius: 10px; color: var(--primary); }
.workspace-guide .eyebrow { margin: 0 0 .35rem; color: var(--primary); }
.workspace-guide h2 { font-size: 1.1rem; margin: 0; }
.workspace-guide .muted { font-size: .82rem; line-height: 1.65; max-width: 730px; margin: .6rem 0 .75rem; }
.workspace-guide .text-link { font-size: .78rem; }
.workspace-list-title { display: flex; align-items: center; gap: .6rem; font-size: 1.05rem; margin: 0; }
.workspace-list-title span { padding: .2rem .45rem; border-radius: 5px; color: var(--muted); background: #edf1ee; font-size: .7rem; font-weight: 500; }
.workspace-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; margin-bottom: .8rem; color: var(--primary); border-radius: 12px; background: #eaf5ef; }
.offer-card-heading { align-items: flex-start; margin-bottom: 1.5rem; }
.offer-card-heading h2 { font-size: 1.15rem; }
.offer-card-heading h2 a { display: inline-flex; align-items: center; gap: .35rem; text-decoration: none; }
.offer-card .prose h3 { font-size: .78rem; margin: 0 0 .4rem; }
.offer-card .prose p { font-size: .85rem; line-height: 1.7; overflow-wrap: anywhere; }
.offer-card .tag { display: inline-flex; align-items: center; gap: .4rem; }
.decision-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.offer-details summary { cursor: pointer; font-size: .8rem; font-weight: 550; padding: .85rem 0; color: var(--primary); }
.offer-details { border-top: 1px solid var(--border); margin-top: 1rem; }
.offer-details .prose { padding-top: .4rem; }
.offer-updated { display: flex; align-items: center; gap: .35rem; font-size: .7rem; margin-bottom: 0; }
.offer-updated :deep(svg) { width: 14px; height: 14px; }
@media (max-width: 700px) {
  .workspace-metrics { gap: .65rem; }
  .workspace-metric { padding: 1rem .75rem; display: block; }
  .workspace-metric-icon { display: none; }
  .workspace-metric p { font-size: .68rem; min-height: 2.8em; line-height: 1.4; }
  .workspace-metric strong { font-size: 1.6rem; }
  .workspace-guide { padding: 1.2rem; gap: .8rem; }
  .workspace-guide-icon { display: none; }
}
</style>
