<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch, watchEffect } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import { api } from '../lib/api.js'
import { session, profileKey, selectProfile } from '../lib/session.js'
import { contentFields } from '../lib/fields.js'
import ApiError from '../components/ApiError.vue'
import ScorePanel from '../components/ScorePanel.vue'
import Icon from '../components/Icon.vue'
import { editorState } from '../lib/editorState.js'

const route = useRoute()
const router = useRouter()
const task = ref(null)
const form = reactive({})
const loading = ref(false)
const busy = ref(false)
const aiLoading = ref(false)
const error = ref(null)
const errorSummary = ref(null)
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
watch(error, async failure => {
  if (!failure) return
  await nextTick()
  errorSummary.value?.focus({ preventScroll: true })
  errorSummary.value?.scrollIntoView({ block: 'center' })
})
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
      <p class="eyebrow">РАБОЧЕЕ ПРОСТРАНСТВО / {{ isNew ? 'НОВАЯ ЗАДАЧА' : 'РЕДАКТОР ЗАДАЧИ' }}</p>
      <h1>{{ isNew ? 'Создать задачу' : 'Редактировать задачу' }}</h1>
      <p class="muted editor-lead">{{ isNew ? 'Расскажите о проблеме. Сначала сохраните личный черновик, затем уточните детали и опубликуйте задачу для команд.' : 'Дополните описание, сохраните изменения и проверьте готовность. Опубликованную задачу увидят команды в каталоге.' }}</p>
    </div>
    <div class="editor-header-actions"><RouterLink class="button button-secondary" to="/workspace">Мои задачи <Icon name="arrow-up-right" /></RouterLink><a v-if="task && canEdit" class="text-link" href="#task-readiness">К рейтингу и публикации <Icon name="arrow-right" /></a></div>
  </div>
  <ol class="editor-steps" aria-label="Этапы создания задачи">
    <li :class="{ active: isNew, complete: !isNew }" :aria-current="isNew ? 'step' : undefined"><span class="editor-step-number"><Icon v-if="!isNew" name="check" /><template v-else>1</template></span><div><strong>Опишите проблему</strong><small>Сохраните черновик</small></div></li>
    <li :class="{ active: !isNew && task?.status !== 'published', complete: task?.status === 'published' }" :aria-current="!isNew && task?.status !== 'published' ? 'step' : undefined"><span class="editor-step-number"><Icon v-if="task?.status === 'published'" name="check" /><template v-else>2</template></span><div><strong>Уточните детали</strong><small>Вопросы и рейтинг</small></div></li>
    <li :class="{ active: task?.status === 'published' }" :aria-current="task?.status === 'published' ? 'step' : undefined"><span class="editor-step-number">3</span><div><strong>Опубликуйте задачу</strong><small>Получайте отклики команд</small></div></li>
  </ol>
  <div v-if="session.loading || loading" class="panel loading-state" role="status">Загружаем карточку…</div>
  <div v-else-if="!isCustomer" class="panel empty-state">
    <span class="editor-empty-icon"><Icon name="briefcase" /></span>
    <h2>Создавайте задачи от имени заказчика</h2>
    <p class="muted">Заказчик описывает проблему, а команда предлагает решение. Для создания задачи выберите профиль заказчика.</p>
    <button v-if="session.profiles.customers.length" type="button" class="button" @click="selectProfile(profileKey(session.profiles.customers[0]))">Выбрать заказчика <Icon name="arrow-right" /></button>
    <RouterLink class="text-link" to="/catalog">Перейти к каталогу</RouterLink>
  </div>
  <template v-else>
    <div v-if="error" ref="errorSummary" class="editor-error-summary" tabindex="-1" aria-label="Ошибка сохранения задачи"><ApiError :error="error" :retry="!task && !isNew" @retry="load" /></div>
    <div v-if="!isNew && task && !canEdit" class="panel empty-state">
      <h2>Редактировать может владелец задачи</h2>
      <RouterLink class="text-link" :to="'/tasks/' + task.id">Посмотреть задачу</RouterLink>
    </div>
    <div v-if="canEdit" class="editor-layout">
      <form class="stack" @submit.prevent="save(false)">
        <fieldset class="panel editor-panel" :disabled="busy">
          <div class="editor-section-title">
            <div class="section-heading"><span class="section-number">01</span><div><h2>Основная информация</h2><p class="muted">{{ isNew ? 'Для черновика достаточно названия. Остальное можно добавить позже.' : 'Эти сведения помогут командам найти вашу задачу.' }}</p></div></div>
            <span class="editor-status" :class="{ published: task?.status === 'published' }"><span></span>{{ task?.status === 'published' ? 'Опубликована' : 'Черновик' }}</span>
          </div>
          <div class="form-grid">
            <div v-for="field in basicFields" :key="field.key" class="form-field" :class="{ 'full-width': field.key === 'title' }">
              <label :for="'basic-' + field.key">{{ field.label }} <span v-if="field.key === 'title'" class="required">*</span></label>
              <input :id="'basic-' + field.key" v-model="form[field.key]" :name="field.key" :maxlength="field.max" :placeholder="field.placeholder" :required="field.key === 'title'" :aria-describedby="'help-' + field.key" @input="publishConfirmed = false" />
              <small :id="'help-' + field.key" class="field-help">{{ field.key === 'title' ? 'Коротко и конкретно: что нужно создать или улучшить.' : 'Понадобится для публикации в каталоге.' }}</small>
            </div>
            <div class="form-field"><label for="basic-scope">Охват</label>
              <select id="basic-scope" v-model="form.scope" name="scope" aria-describedby="help-scope" @change="publishConfirmed = false">
                <option value="kazakhstan">Весь Казахстан</option>
                <option value="institution">Одно учреждение</option>
              </select>
              <small id="help-scope" class="field-help">Где планируется использовать решение.</small>
            </div>
          </div>
          <div v-if="isNew" class="form-field description-field">
            <label for="initial-context">Описание проблемы</label>
            <textarea id="initial-context" v-model="form.context" name="context" aria-describedby="help-context" rows="6" maxlength="10000" placeholder="Например: первокурсники не знают, когда проходят консультации преподавателей. Сейчас расписание приходится искать в разных чатах. Хотим собрать его в одном месте." @input="changed('context')"></textarea>
            <small id="help-context" class="field-help">Опишите, кому нужна помощь и что сейчас не получается. Помощник задаст уточняющие вопросы после сохранения.</small>
          </div>
        </fieldset>

        <template v-if="!isNew">
          <section class="panel ai-panel" aria-label="AI-помощник">
            <div class="section-heading"><span class="editor-ai-icon"><Icon name="sparkles" /></span><div><p class="eyebrow">ПОМОЩНИК ПО ОПИСАНИЮ</p><h2>Уточните задачу с помощником</h2></div></div>
            <p class="muted">Выберите вопрос, чтобы перейти к нужному полю. Добавьте ответ в описание и сохраните карточку.</p>
            <p v-if="assistance?.mode === 'mock'" class="ai-notice"><Icon name="info" /> <span>Демо: вопросы формирует локальная AI-заглушка.</span></p>
            <ol v-if="assistance" class="question-list">
              <li v-for="question in assistance.questions" :key="question.id"><a :href="'#field-' + question.field">{{ question.question }}<Icon name="arrow-up-right" /></a></li>
            </ol>
            <ApiError v-if="aiError" :error="aiError" />
            <button type="button" class="button button-secondary" :disabled="aiLoading || busy || form.context.trim().length < 3" @click="askAI">
              <Icon name="sparkles" />{{ aiLoading ? 'Готовим вопросы…' : assistance ? 'Обновить вопросы' : 'Получить вопросы AI' }}
            </button>
            <p v-if="form.context.trim().length < 3" class="muted"><small>Начните с поля «Контекст и потребность» — добавьте хотя бы 3 символа.</small></p>
            <span v-if="aiLoading" role="status" class="sr-only">Готовим уточняющие вопросы</span>
          </section>
          <fieldset class="panel editor-panel" :disabled="busy">
            <div class="section-heading"><span class="section-number">02</span><div><h2>Детали для команды</h2><p class="muted">Понятное описание поможет получить подходящие отклики. Отмечайте только проверенные сведения.</p></div></div>
            <div v-for="field in contentFields" :id="'field-' + field.key" :key="field.key" class="content-field">
              <label class="form-field" :for="'input-' + field.key"><span>{{ field.label }}</span></label>
              <p v-if="questionMap[field.key]" :id="'question-' + field.key" class="field-question"><Icon name="sparkles" />{{ questionMap[field.key] }}</p>
              <textarea :id="'input-' + field.key" v-model="form[field.key]" :aria-describedby="questionMap[field.key] ? 'question-' + field.key : undefined" :name="field.key" rows="3" maxlength="10000" :placeholder="field.placeholder" @input="changed(field.key)"></textarea>
              <label class="checkbox-label">
                <input v-model="form.confirmedFields" type="checkbox" :value="field.key" :disabled="!form[field.key]?.trim()" :aria-label="'Подтверждаю: ' + field.label" />
                Сведения проверены
              </label>
            </div>
          </fieldset>
        </template>
        <div v-if="savedNotice" class="success-message" role="status"><Icon name="check-circle" />{{ savedNotice }}</div>
        <a v-if="task" class="text-link editor-rating-link" href="#task-readiness"><Icon name="chart" />К рейтингу и публикации <Icon name="arrow-right" /></a>
        <div class="editor-actions">
          <span class="muted">{{ dirty ? 'Есть несохранённые изменения' : isNew ? 'Черновик будет виден только вам' : 'Все изменения сохранены' }}</span>
          <button class="button" type="submit" :disabled="busy">{{ busy ? 'Сохраняем…' : isNew ? 'Создать черновик' : 'Сохранить изменения' }}<Icon v-if="!busy" :name="isNew ? 'arrow-right' : 'check'" /></button>
        </div>
      </form>

      <aside id="task-readiness" class="stack editor-aside">
        <ScorePanel v-if="task" :task="task" />
        <section v-else class="panel starter-note">
          <span class="editor-note-icon"><Icon name="file-text" /></span>
          <h2>От идеи до первого отклика</h2>
          <p class="muted">Вам не нужно составлять идеальное техническое задание с первого раза.</p>
          <ol class="editor-guide">
            <li><span>1</span><div><strong>Сохраните черновик</strong><p>Название и несколько предложений о проблеме — хорошее начало.</p></div></li>
            <li><span>2</span><div><strong>Дополните описание</strong><p>Помощник предложит вопросы, а рейтинг покажет полноту сведений.</p></div></li>
            <li><span>3</span><div><strong>Откройте задачу командам</strong><p>После публикации она появится в каталоге, и команды смогут откликнуться.</p></div></li>
          </ol>
          <div class="editor-private"><Icon name="shield-check" /><span>Черновик доступен только вашему профилю. Публикация — отдельный шаг.</span></div>
        </section>
        <p v-if="task && dirty" class="save-hint" role="status"><Icon name="info" /><span>Рейтинг относится к последнему сохранению. Сохраните изменения, чтобы обновить оценку.</span></p>
        <section v-if="task" class="panel publish-panel">
          <p class="eyebrow">{{ task.status === 'published' ? 'ЗАДАЧА В КАТАЛОГЕ' : 'ШАГ 3 · ПУБЛИКАЦИЯ' }}</p>
          <h2>{{ task.status === 'published' ? 'Обновите опубликованную задачу' : 'Готовы получить отклики?' }}</h2>
          <p class="muted">{{ task.status === 'published' ? 'Проверьте изменения перед обновлением карточки в каталоге.' : 'После публикации команды увидят задачу и смогут предложить решение.' }}</p>
          <p class="publish-requirements">Нужны название, организация, регион и тема. При публикации все заполненные сведения будут подтверждены.</p>
          <label class="checkbox-label"><input v-model="publishConfirmed" type="checkbox" :disabled="busy" />Я проверил сведения и подтверждаю публикацию</label>
          <button type="button" class="button full-width" :disabled="busy || !publishConfirmed" @click="save(true)">{{ busy ? 'Сохраняем…' : task.status === 'published' ? 'Подтвердить и обновить' : 'Опубликовать задачу' }}<Icon v-if="!busy" name="arrow-up-right" /></button>
          <RouterLink v-if="task.status === 'published'" class="text-link" :to="'/tasks/' + task.id">Открыть страницу задачи <Icon name="arrow-up-right" /></RouterLink>
        </section>
      </aside>
    </div>
  </template>
</template>

<style scoped>
#task-readiness { scroll-margin-top: 100px; }
.editor-error-summary { margin-bottom: 1.25rem; border-radius: 12px; }
.editor-rating-link { display: inline-flex; align-self: flex-start; font-size: .8rem; }
.editor-header-actions { display: flex; flex-direction: column; gap: .75rem; align-items: flex-end; flex-shrink: 0; }
.editor-header-actions > .text-link { font-size: .8rem; }
.editor-lead { max-width: 760px; }
.editor-steps { display: grid; grid-template-columns: repeat(3, 1fr); padding: 0; margin: 0 0 1.75rem; list-style: none; border: 1px solid var(--border); border-radius: 12px; background: var(--surface); }
.editor-steps li { display: flex; align-items: center; gap: .7rem; padding: 1.1rem 1.3rem; color: var(--muted); }
.editor-steps li + li { border-left: 1px solid var(--border); }
.editor-step-number { display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; border: 1px solid var(--border); border-radius: 50%; font-size: .78rem; font-weight: 650; }
.editor-step-number :deep(svg) { width: 16px; height: 16px; }
.editor-steps strong { display: block; font-size: .82rem; font-weight: 600; }
.editor-steps small { display: block; margin-top: .2rem; font-size: .7rem; }
.editor-steps .active { color: var(--text); }
.editor-steps .active .editor-step-number { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 0 0 4px #eaf5ef; }
.editor-steps .complete .editor-step-number { background: #eaf5ef; color: var(--primary); border-color: #d4e8dd; }
.editor-section-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.editor-status { display: inline-flex; align-items: center; gap: .35rem; font-size: .68rem; white-space: nowrap; padding: .3rem .55rem; border-radius: 5px; background: #f1f3f2; color: var(--muted); }
.editor-status > span { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.editor-status.published { color: var(--primary); background: #eaf5ef; }
.field-help { color: var(--muted); font-size: .72rem; line-height: 1.5; font-weight: 400; }
.editor-ai-icon, .editor-note-icon, .editor-empty-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--primary); background: #eaf5ef; border-radius: 10px; width: 40px; height: 40px; }
.editor-ai-icon :deep(svg), .editor-note-icon :deep(svg) { width: 20px; height: 20px; }
.editor-note-icon { margin-bottom: 1.25rem; }
.starter-note h2 { margin-bottom: .7rem; font-size: 1.2rem; line-height: 1.4; }
.starter-note > p { font-size: .82rem; line-height: 1.65; }
.editor-guide { display: grid; gap: 1.35rem; padding: 0; margin: 1.4rem 0; list-style: none; }
.editor-guide li { display: flex; align-items: flex-start; gap: .75rem; }
.editor-guide li > span { width: 23px; height: 23px; display: grid; place-items: center; flex-shrink: 0; border: 1px solid var(--border); border-radius: 50%; font-size: .68rem; font-weight: 650; }
.editor-guide strong { font-size: .8rem; font-weight: 600; }
.editor-guide p { margin: .3rem 0 0; color: var(--muted); font-size: .76rem; line-height: 1.6; }
.editor-private { border-top: 1px solid var(--border); padding-top: 1rem; display: flex; align-items: flex-start; gap: .5rem; color: var(--muted); font-size: .72rem; line-height: 1.6; }
.editor-private :deep(svg), .save-hint :deep(svg), .ai-notice :deep(svg), .field-question :deep(svg) { width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px; }
.save-hint, .ai-notice, .field-question { display: flex; gap: .5rem; align-items: flex-start; }
.question-list { padding-left: 1.2rem; }
.question-list li { padding-left: .3rem; }
.question-list a { display: flex; align-items: flex-start; gap: .5rem; }
.question-list a :deep(svg) { width: 15px; height: 15px; flex-shrink: 0; margin-left: auto; }
.publish-panel h2 { font-size: 1.05rem; line-height: 1.45; }
.publish-panel .muted, .publish-requirements { font-size: .8rem; line-height: 1.6; }
.publish-requirements { padding: .8rem; border-radius: 8px; background: var(--surface-muted); color: var(--muted); }
.publish-panel .checkbox-label { margin: 1rem 0; line-height: 1.6; }
.publish-panel .button { white-space: normal; }
.publish-panel .text-link { display: inline-flex; margin-top: 1rem; }
.success-message { display: flex; gap: .5rem; align-items: center; }
@media (max-width: 680px) {
  .editor-header-actions { align-items: flex-start; }
  .editor-steps { gap: 0; }
  .editor-steps li { align-items: flex-start; flex-direction: column; gap: .65rem; padding: .85rem .6rem; }
  .editor-steps strong { font-size: .72rem; line-height: 1.4; }
  .editor-steps small { display: none; }
  .editor-step-number { width: 26px; height: 26px; }
  .editor-section-title { flex-wrap: wrap-reverse; gap: .8rem; }
}
</style>
