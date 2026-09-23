<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'
import { session, profileKey } from '../lib/session'
import ApiError from '../components/ApiError.vue'
import TaskCard from '../components/TaskCard.vue'

const items = ref([])
const loading = ref(false)
const error = ref(null)
let requestId = 0
const isCustomer = computed(() => session.profile?.role === 'customer')
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
        <p class="eyebrow">ЛИЧНЫЙ КАБИНЕТ</p>
        <h1>{{ isCustomer ? 'Мои задачи' : session.profile ? 'Мои отклики' : 'Ваше рабочее пространство' }}</h1>
        <p class="muted">{{ session.profile?.name || 'Выберите профиль вверху страницы, чтобы продолжить.' }}</p>
      </div>
      <RouterLink v-if="isCustomer" class="button" to="/tasks/new">Создать задачу <span aria-hidden="true">+</span></RouterLink>
      <RouterLink v-else-if="session.profile" class="button" to="/catalog">Найти задачу <span aria-hidden="true">↗</span></RouterLink>
    </header>
    <p v-if="loading || session.loading" class="panel loading-state" role="status">Загружаем {{ isCustomer ? 'задачи' : 'отклики' }}…</p>
    <ApiError v-else-if="error" :error="error" retry @retry="load" />
    <div v-else-if="!session.profile" class="panel empty-state">
      <h2>Выберите демопрофиль</h2>
      <p class="muted">Заказчику доступны его задачи и черновики. Команде — отправленные отклики и решения заказчиков.</p>
    </div>
    <div v-else-if="!items.length" class="panel empty-state">
      <h2>{{ isCustomer ? 'Пока нет задач' : 'Пока нет откликов' }}</h2>
      <p class="muted">{{ isCustomer ? 'Опишите потребность — AI поможет уточнить детали.' : 'Выберите интересную задачу в каталоге и предложите своё решение.' }}</p>
      <RouterLink class="button" :to="isCustomer ? '/tasks/new' : '/catalog'">{{ isCustomer ? 'Создать задачу' : 'Открыть каталог' }}</RouterLink>
    </div>
    <template v-else>
      <div class="results-heading"><p role="status">{{ isCustomer ? 'Всего задач' : 'Всего откликов' }}: <strong>{{ items.length }}</strong></p><button class="button button-secondary" type="button" @click="load">Обновить</button></div>
      <div v-if="isCustomer" class="task-grid"><TaskCard v-for="task in items" :key="task.id" :task="task" /></div>
      <div v-else class="stack">
        <article v-for="offer in items" :key="offer.id" class="panel offer-card">
          <div class="page-heading">
            <div>
              <p class="eyebrow">ОТКЛИК #{{ offer.id }} · {{ formattedDate(offer.createdAt) }}</p>
              <h2><RouterLink :to="`/tasks/${offer.taskId}`">Задача #{{ offer.taskId }} <span aria-hidden="true">↗</span></RouterLink></h2>
            </div>
            <span class="tag" :class="`decision-${offer.decision}`">{{ decisionLabels[offer.decision] || offer.decision }}</span>
          </div>
          <div class="prose"><h3>Идея решения</h3><p>{{ offer.idea }}</p></div>
          <details class="offer-details"><summary>План и сроки</summary><div class="prose"><h3>План</h3><p>{{ offer.plan }}</p><h3>Сроки</h3><p>{{ offer.timeline }}</p><p v-if="safeLink(offer.prototypeLink)"><a :href="offer.prototypeLink" target="_blank" rel="noopener noreferrer">Открыть прототип <span aria-hidden="true">↗</span></a></p></div></details>
          <p v-if="offer.decidedAt" class="muted">Решение обновлено {{ formattedDate(offer.decidedAt) }}</p>
        </article>
      </div>
    </template>
  </section>
</template>