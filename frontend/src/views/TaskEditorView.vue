<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch, watchEffect } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import { api } from '../lib/api.js'
import { session, profileKey } from '../lib/session.js'
import { contentFields } from '../lib/fields.js'
import ApiError from '../components/ApiError.vue'
import ScorePanel from '../components/ScorePanel.vue'
import { editorState } from '../lib/editorState.js'

const route = useRoute()
const router = useRouter()
const task = ref(null)
const form = reactive({})
const loading = ref(false)
const busy = ref(false)
const aiLoading = ref(false)
const error = ref(null)
const aiError = ref(null)
const assistance = ref(null)
const savedNotice = ref('')
const publishConfirmed = ref(false)
const baseline = ref('')
let generation = 0
const isNew = computed(() => !route.params.id)
const isCustomer = computed(() => session.profile?.role === 'customer')
const canEdit = computed(() => isCustomer.value && (isNew.value || task.value?.ownerId === session.profile?.id))
const dirty = computed(() => baseline.value !== JSON.stringify(form))
const questionMap = computed(() => Object.fromEntries((assistance.value?.questions || []).map(q => [q.field, q.question])))
const basicFields = [
  { key: 'title', label: 'Название задачи', max: 160, placeholder: 'Например, разговорный английский для первокурсников' },
  { key: 'organization', label: 'Организация', max: 255, placeholder: 'Университет, колледж или школа' },
  { key: 'region', label: 'Регион', max: 100, placeholder: 'Например, Алматы' },
  { key: 'category', label: 'Тема', max: 100, placeholder: 'Например, Языки' },
]
function fill(value = {}) {
  for (const field of [...basicFields, ...contentFields]) form[field.key] = value[field.key] || ''
  form.scope = value.scope || 'kazakhstan'
  form.confirmedFields = [...(value.confirmedFields || [])]
  baseline.value = JSON.stringify(form)
  publishConfirmed.value = false
}
function changed(key) {
  form.confirmedFields = form.confirmedFields.filter(field => field !== key)
  publishConfirmed.value = false
  savedNotice.value = ''
}
function payload() {
  return {
    ...Object.fromEntries([...basicFields, ...contentFields].map(({ key }) => [key, form[key].trim()])),
    scope: form.scope,
    confirmedFields: form.confirmedFields.filter(key => form[key]?.trim()),
  }
}
async function load() {
  const current = ++generation
  task.value = null
  error.value = null
  aiError.value = null
  assistance.value = null
  savedNotice.value = ''
  busy.value = false
  aiLoading.value = false
  loading.value = false
  fill()
  if (!isCustomer.value) return
  if (isNew.value) {
    form.organization = session.profile.organization || ''
    baseline.value = JSON.stringify(form)
    return
  }
  loading.value = true
  try {
    const result = await api.getTask(route.params.id)
    if (current !== generation) return
    task.value = result
    fill(result)
  } catch (failure) {
    if (current === generation) error.value = failure
  } finally {
    if (current === generation) loading.value = false
  }
  if (current === generation && task.value && route.query.assist === '1' && form.context.trim().length >= 3) {
    await askAI()
    if (current === generation) await router.replace({ path: route.path })
  }
}
async function askAI() {
  if (aiLoading.value || busy.value) return
  aiLoading.value = true
  aiError.value = null
  const current = generation
  try {
    const result = await api.questions({
      description: form.context.trim(),
      fields: Object.fromEntries(contentFields.map(({ key }) => [key, form[key].trim()])),
    })
    if (current === generation) assistance.value = result
  } catch (failure) {
    if (current === generation) aiError.value = failure
  } finally {
    if (current === generation) aiLoading.value = false
  }
}
async function save(publish = false) {
  if (busy.value || !canEdit.value || (publish && !publishConfirmed.value)) return
  busy.value = true
  error.value = null
  savedNotice.value = ''
  const current = generation
  const creating = isNew.value
  const shouldAssist = creating && form.context.trim().length >= 3
  try {
    const result = creating ? await api.createTask(payload()) : await api.updateTask(task.value.id, payload())
    if (current !== generation) return
    task.value = result
    fill(result)
    if (creating) {
      await router.replace({ path: '/tasks/' + result.id + '/edit', query: shouldAssist ? { assist: '1' } : {} })
      return
    }
    savedNotice.value = 'Изменения сохранены. Рейтинг обновлён.'
    if (publish) {
      const published = await api.publishTask(result.id)
      if (current !== generation) return
      task.value = published
      fill(published)
      await router.push('/tasks/' + published.id)
    }
  } catch (failure) {
    if (current === generation) error.value = failure
  } finally {
    if (current === generation) busy.value = false
  }
}
watchEffect(() => {
  editorState.dirty = dirty.value && canEdit.value
  editorState.busy = busy.value
})
function beforeUnload(event) {
  if (dirty.value && canEdit.value) { event.preventDefault(); event.returnValue = '' }
}
function confirmLeave() {
  if (dirty.value && canEdit.value && !window.confirm('Есть несохранённые изменения. Покинуть редактор?')) return false
}
onBeforeRouteLeave(confirmLeave)
onBeforeRouteUpdate((to, from) => {
  if (to.params.id !== from.params.id) return confirmLeave()
})
watch([() => route.params.id, () => profileKey(session.profile)], load, { immediate: true })
onMounted(() => window.addEventListener('beforeunload', beforeUnload))
onBeforeUnmount(() => { editorState.dirty = false; editorState.busy = false; generation++; window.removeEventListener('beforeunload', beforeUnload) })
</script>

<template>
  <div class="page-heading">
    <div>
      <p class="eyebrow">ОТ ПОТРЕБНОСТИ К ПРОЕКТУ</p>
      <h1>{{ isNew ? 'Дайте задаче начало' : 'Сделаем задачу понятнее' }}</h1>
      <p class="muted">{{ isNew ? 'Опишите проблему. Уточняющие вопросы помогут собрать понятную карточку для команды.' : 'Дополните описание, проверьте сведения и пригласите команды к работе.' }}</p>
    </div>
    <RouterLink class="text-link" to="/workspace">Мой кабинет ↗</RouterLink>
  </div>
  <ol class="steps" aria-label="Этапы создания задачи">
    <li :class="{ active: isNew }"><span>01</span> Описание</li>
    <li :class="{ active: !isNew }"><span>02</span> Карточка и рейтинг</li>
    <li><span>03</span> Публикация</li>
  </ol>
  <div v-if="session.loading || loading" class="panel loading-state" role="status">Загружаем карточку…</div>
  <div v-else-if="!isCustomer" class="panel empty-state">
    <h2>Создавайте задачи от имени заказчика</h2>
    <p class="muted">Выберите демопрофиль заказчика в верхней части страницы.</p>
    <RouterLink class="button button-secondary" to="/catalog">Открыть каталог</RouterLink>
  </div>
  <template v-else>
    <ApiError v-if="error" :error="error" :retry="!task && !isNew" @retry="load" />
    <div v-if="!isNew && task && !canEdit" class="panel empty-state">
      <h2>Редактировать может владелец задачи</h2>
      <RouterLink class="text-link" :to="'/tasks/' + task.id">Посмотреть задачу</RouterLink>
    </div>
    <div v-if="canEdit" class="editor-layout">
      <form class="stack" @submit.prevent="save(false)">
        <fieldset class="panel editor-panel" :disabled="busy">
          <div class="section-heading"><span class="section-number">01</span><div><h2>О задаче</h2><p class="muted">Начните с главного. Черновик виден только вам.</p></div></div>
          <div class="form-grid">
            <label v-for="field in basicFields" :key="field.key" class="form-field" :class="{ 'full-width': field.key === 'title' }">
              <span>{{ field.label }} <span v-if="field.key === 'title'" class="required">*</span></span>
              <input v-model="form[field.key]" :name="field.key" :maxlength="field.max" :placeholder="field.placeholder" :required="field.key === 'title'" @input="publishConfirmed = false" />
            </label>
            <label class="form-field"><span>Охват</span>
              <select v-model="form.scope" name="scope" @change="publishConfirmed = false">
                <option value="kazakhstan">Весь Казахстан</option>
                <option value="institution">Одно учреждение</option>
              </select>
            </label>
          </div>
          <label v-if="isNew" class="form-field description-field">
            <span>Описание проблемы</span>
            <textarea v-model="form.context" name="context" rows="5" maxlength="10000" placeholder="Что сейчас не получается? Кому и как это мешает?" @input="changed('context')"></textarea>
            <small class="muted">После создания черновика помощник предложит уточняющие вопросы к вашему описанию.</small>
          </label>
        </fieldset>

        <template v-if="!isNew">
          <section class="panel ai-panel" aria-label="AI-помощник">
            <div class="section-heading"><span class="ai-symbol" aria-hidden="true">✦</span><div><p class="eyebrow">AI-ПОМОЩНИК</p><h2>Хорошая задача начинается с вопросов</h2></div></div>
            <p class="muted">Помощник подскажет, каких сведений не хватает. Ответьте в соответствующих полях карточки ниже.</p>
            <p v-if="assistance?.mode === 'mock'" class="ai-notice">Демо: вопросы формирует локальная AI-заглушка.</p>
            <ol v-if="assistance" class="question-list">
              <li v-for="question in assistance.questions" :key="question.id"><a :href="'#field-' + question.field">{{ question.question }}</a></li>
            </ol>
            <ApiError v-if="aiError" :error="aiError" />
            <button type="button" class="button button-secondary" :disabled="aiLoading || busy || form.context.trim().length < 3" @click="askAI">
              {{ aiLoading ? 'Готовим вопросы…' : assistance ? 'Обновить вопросы' : 'Получить вопросы AI' }}
            </button>
            <p v-if="form.context.trim().length < 3" class="muted"><small>Добавьте хотя бы 3 символа в поле «Контекст и потребность».</small></p>
            <span v-if="aiLoading" role="status" class="sr-only">Готовим уточняющие вопросы</span>
          </section>
          <fieldset class="panel editor-panel" :disabled="busy">
            <div class="section-heading"><span class="section-number">02</span><div><h2>Содержание карточки</h2><p class="muted">Подтвердите сведения, которые вы проверили.</p></div></div>
            <div v-for="field in contentFields" :id="'field-' + field.key" :key="field.key" class="content-field">
              <label class="form-field" :for="'input-' + field.key">
                <span>{{ field.label }}</span>
                <small v-if="questionMap[field.key]" class="field-question">✦ {{ questionMap[field.key] }}</small>
              </label>
              <textarea :id="'input-' + field.key" v-model="form[field.key]" :name="field.key" rows="3" maxlength="10000" :placeholder="field.placeholder" @input="changed(field.key)"></textarea>
              <label class="checkbox-label">
                <input v-model="form.confirmedFields" type="checkbox" :value="field.key" :disabled="!form[field.key]?.trim()" :aria-label="'Подтверждаю: ' + field.label" />
                Сведения проверены
              </label>
            </div>
          </fieldset>
        </template>
        <div v-if="savedNotice" class="success-message" role="status">{{ savedNotice }}</div>
        <div class="editor-actions">
          <span class="muted">{{ dirty ? 'Есть несохранённые изменения' : isNew ? 'Начните с названия задачи' : 'Все изменения сохранены' }}</span>
          <button class="button" type="submit" :disabled="busy">{{ busy ? 'Сохраняем…' : isNew ? 'Создать черновик →' : 'Сохранить изменения' }}</button>
        </div>
      </form>

      <aside class="stack editor-aside">
        <ScorePanel v-if="task" :task="task" />
        <section v-else class="panel starter-note">
          <span class="note-art" aria-hidden="true">↗</span>
          <p class="eyebrow">БОЛЬШЕ ЯСНОСТИ — ЛЕГЧЕ НАЧАТЬ</p>
          <h2>Помогите команде понять вашу задачу</h2>
          <p class="muted">Рейтинг показывает, насколько полно вы описали потребность. После сохранения здесь появятся баллы и подсказки.</p>
        </section>
        <p v-if="task && dirty" class="save-hint" role="status">Рейтинг относится к последнему сохранению. Сохраните изменения, чтобы обновить его.</p>
        <section v-if="task" class="panel publish-panel">
          <p class="eyebrow">{{ task.status === 'published' ? 'ЗАДАЧА В КАТАЛОГЕ' : 'СЛЕДУЮЩИЙ ШАГ' }}</p>
          <h2>{{ task.status === 'published' ? 'Подтвердите обновления' : 'Пригласите команды' }}</h2>
          <p class="muted">Для публикации заполните название, организацию, регион и тему. Все заполненные сведения будут подтверждены.</p>
          <label class="checkbox-label"><input v-model="publishConfirmed" type="checkbox" :disabled="busy" />Я проверил сведения и подтверждаю публикацию</label>
          <button type="button" class="button full-width" :disabled="busy || !publishConfirmed" @click="save(true)">{{ busy ? 'Сохраняем…' : task.status === 'published' ? 'Подтвердить и обновить' : 'Опубликовать задачу' }}</button>
          <RouterLink v-if="task.status === 'published'" class="text-link" :to="'/tasks/' + task.id">Открыть страницу задачи ↗</RouterLink>
        </section>
      </aside>
    </div>
  </template>
</template>
