<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../lib/api'
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
  <section class="stack">
    <header class="page-heading">
      <div>
        <p class="eyebrow">ОТ ИДЕИ К РЕШЕНИЮ</p>
        <h1>Каталог задач</h1>
        <p class="muted">Найдите задачу, в которой опыт вашей команды будет полезен.</p>
      </div>
      <RouterLink class="button" to="/tasks/new">Создать задачу <span aria-hidden="true">+</span></RouterLink>
    </header>

    <form class="panel catalog-filters" aria-label="Поиск и фильтры каталога" @submit.prevent="applyFilters">
      <div class="catalog-search form-field">
        <label for="catalog-search">Поиск по задачам</label>
        <input id="catalog-search" v-model="filters.q" type="search" placeholder="Название, организация или описание" maxlength="200" />
      </div>
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
      <div class="form-actions catalog-filter-actions">
        <button class="button" type="submit">Найти задачи</button>
        <button v-if="activeFilters" class="button button-secondary" type="button" @click="resetFilters">Сбросить</button>
      </div>
      <p class="muted filter-hint">Тема и регион ищутся по точному названию. Выберите подсказку или введите значение.</p>
      <p v-if="optionsError" class="muted filter-hint">Подсказки недоступны. Поиск и ввод фильтров остаются доступны.</p>
    </form>

    <div :aria-busy="loading">
      <p v-if="loading" class="loading-state" role="status">Загружаем задачи…</p>
      <ApiError v-else-if="error" :error="error" retry @retry="load" />
      <template v-else>
        <div class="results-heading"><p role="status">Найдено задач: <strong>{{ total }}</strong></p><span class="muted">Рейтинг готовности — из 100</span></div>
        <div v-if="tasks.length" class="task-grid">
          <TaskCard v-for="task in tasks" :key="task.id" :task="task" />
        </div>
        <div v-else class="panel empty-state">
          <h2>Задачи не найдены</h2>
          <p class="muted">Попробуйте другой запрос или измените фильтры.</p>
          <button v-if="activeFilters" class="button button-secondary" type="button" @click="resetFilters">Показать все задачи</button>
          <RouterLink v-else class="button" to="/tasks/new">Создать первую задачу</RouterLink>
        </div>
      </template>
    </div>
  </section>
</template>