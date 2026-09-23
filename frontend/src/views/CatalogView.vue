<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../lib/api'
import { session } from '../lib/session'
import Icon from '../components/Icon.vue'
import ApiError from '../components/ApiError.vue'
import TaskCard from '../components/TaskCard.vue'

const route = useRoute()
const router = useRouter()
const filters = reactive({ q: '', category: '', region: '', scope: '', sort: 'score' })
const tasks = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)
const categories = ref([])
const regions = ref([])
const optionsError = ref(false)
let requestId = 0
let controller
let disposed = false
const optionController = new AbortController()
const activeFilters = computed(() => Boolean(filters.q || filters.category || filters.region || filters.scope || filters.sort !== 'score'))
const queryText = (value) => typeof value === 'string' ? value : ''

function readQuery() {
  filters.q = queryText(route.query.q)
  filters.category = queryText(route.query.category)
  filters.region = queryText(route.query.region)
  filters.scope = ['institution', 'kazakhstan'].includes(route.query.scope) ? route.query.scope : ''
  filters.sort = route.query.sort === 'newest' ? 'newest' : 'score'
}

function queryParams() {
  const params = { sort: filters.sort }
  for (const field of ['q', 'category', 'region', 'scope']) {
    const value = filters[field].trim()
    if (value) params[field] = value
  }
  return params
}

async function load() {
  const current = ++requestId
  controller?.abort()
  controller = new AbortController()
  loading.value = true
  error.value = null
  try {
    const result = await api.listTasks(queryParams(), { signal: controller.signal })
    if (current !== requestId || disposed) return
    tasks.value = result.data
    total.value = result.meta?.total ?? result.data.length
  } catch (cause) {
    if (current !== requestId || disposed) return
    error.value = cause
    tasks.value = []
  } finally {
    if (current === requestId && !disposed) loading.value = false
  }
}

async function applyFilters() {
  const query = queryParams()
  if (JSON.stringify(query) === JSON.stringify(route.query)) await load()
  else await router.replace({ path: '/catalog', query })
}

async function resetFilters() {
  Object.assign(filters, { q: '', category: '', region: '', scope: '', sort: 'score' })
  const hadQuery = Object.keys(route.query).length > 0
  await router.replace({ path: '/catalog', query: {} })
  // If the URL did not change, the route watcher does not run.
  if (!hadQuery) await load()
}

async function loadOptions() {
  try {
    const result = await api.listTasks({}, { signal: optionController.signal })
    if (disposed) return
    categories.value = [...new Set(result.data.map(task => task.category).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'ru'))
    regions.value = [...new Set(result.data.map(task => task.region).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'ru'))
  } catch {
    if (!disposed) optionsError.value = true
  }
}

watch(() => route.query, () => { readQuery(); load() }, { immediate: true })
loadOptions()
onBeforeUnmount(() => { disposed = true; requestId++; controller?.abort(); optionController.abort() })
</script>
<template>
  <section class="stack catalogue-page">
    <header class="page-heading">
      <div>
        <p class="eyebrow">РЕАЛЬНЫЕ ПРОЕКТЫ В ОБРАЗОВАНИИ</p>
        <h1>Каталог задач</h1>
        <p class="muted">Задачи учебных заведений, которые ваша команда может превратить в полезные решения.</p>
      </div>
      <RouterLink v-if="session.profile?.role !== 'team'" class="button" to="/tasks/new">Создать задачу <Icon name="arrow-up-right" :size="18" /></RouterLink>
      <RouterLink v-else class="button button-secondary" to="/workspace">Мои отклики <Icon name="arrow-right" :size="18" /></RouterLink>
    </header>

    <div class="catalogue-guide">
      <div class="catalogue-guide-intro"><Icon name="briefcase" :size="20" /><strong>Найдите свой следующий проект</strong></div>
      <ol aria-label="Как откликнуться на задачу">
        <li><span>1</span> Выберите задачу</li>
        <li><span>2</span> Изучите детали</li>
        <li><span>3</span> Предложите решение</li>
      </ol>
      <RouterLink class="text-link" to="/guide">Как это работает <Icon name="arrow-up-right" :size="16" /></RouterLink>
    </div>

    <form class="panel catalog-toolbar" aria-label="Поиск и фильтры каталога" @submit.prevent="applyFilters">
      <div class="catalog-search-row">
        <div class="search-control">
          <label class="sr-only" for="catalog-search">Поиск по задачам</label>
          <Icon name="search" :size="20" />
          <input id="catalog-search" v-model="filters.q" type="search" placeholder="Найдите задачу по названию, организации или описанию" maxlength="200" />
        </div>
        <button class="button" type="submit">Найти задачи <Icon name="arrow-right" :size="18" /></button>
      </div>
      <div class="filter-grid">
        <div class="form-field">
          <label for="catalog-category">Тема</label>
          <input id="catalog-category" v-model="filters.category" list="catalog-categories" placeholder="Все темы" maxlength="100" />
          <datalist id="catalog-categories"><option v-for="category in categories" :key="category" :value="category" /></datalist>
        </div>
        <div class="form-field">
          <label for="catalog-region">Регион</label>
          <input id="catalog-region" v-model="filters.region" list="catalog-regions" placeholder="Все регионы" maxlength="100" />
          <datalist id="catalog-regions"><option v-for="region in regions" :key="region" :value="region" /></datalist>
        </div>
        <div class="form-field">
          <label for="catalog-scope">Охват</label>
          <select id="catalog-scope" v-model="filters.scope">
            <option value="">Любой охват</option>
            <option value="institution">Учреждение</option>
            <option value="kazakhstan">Весь Казахстан</option>
          </select>
        </div>
        <div class="form-field">
          <label for="catalog-sort">Сортировка</label>
          <select id="catalog-sort" v-model="filters.sort">
            <option value="score">По рейтингу ↓</option>
            <option value="newest">Сначала новые</option>
          </select>
        </div>
      </div>
      <div class="catalog-filter-note">
        <p class="muted filter-hint">Выберите тему и регион из подсказок или введите точное название.</p>
        <button v-if="activeFilters" class="text-link filter-reset" type="button" @click="resetFilters">Сбросить фильтры</button>
      </div>
      <p v-if="optionsError" class="muted filter-hint">Подсказки временно недоступны. Можно ввести тему и регион вручную.</p>
    </form>

    <div :aria-busy="loading">
      <p v-if="loading" class="panel loading-state" role="status">Загружаем задачи…</p>
      <ApiError v-else-if="error" :error="error" retry @retry="load" />
      <template v-else>
        <div class="results-heading">
          <div class="results-count"><p role="status">Найдено задач: <strong>{{ total }}</strong></p><span class="muted">{{ route.query.sort === 'newest' ? 'Сначала новые' : 'Сначала высокий рейтинг' }}</span></div>
          <RouterLink class="rating-explainer" to="/guide#rating"><Icon name="info" :size="16" /> Рейтинг — полнота описания <Icon name="arrow-up-right" :size="14" /></RouterLink>
        </div>
        <div v-if="tasks.length" class="task-grid">
          <TaskCard v-for="task in tasks" :key="task.id" :task="task" />
        </div>
        <div v-else class="panel empty-state">
          <span class="empty-icon"><Icon name="search" :size="28" /></span>
          <h2>Задачи не найдены</h2>
          <p class="muted">Попробуйте более короткий запрос или уберите один из фильтров, чтобы увидеть больше задач.</p>
          <button v-if="activeFilters" class="button button-secondary" type="button" @click="resetFilters">Показать все задачи</button>
          <RouterLink v-else-if="session.profile?.role !== 'team'" class="button" to="/tasks/new">Создать первую задачу</RouterLink>
          <RouterLink v-else class="button button-secondary" to="/guide">Как работает платформа</RouterLink>
        </div>
      </template>
    </div>
  </section>
</template>