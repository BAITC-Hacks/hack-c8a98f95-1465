<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../lib/api'
import { session, profileKey } from '../lib/session'
import { contentFields, scopeLabel } from '../lib/fields'
import ApiError from '../components/ApiError.vue'
import ScorePanel from '../components/ScorePanel.vue'

const route = useRoute()
const task = ref(null)
const loading = ref(false)
const error = ref(null)
const sending = ref(false)
const offerError = ref(null)
const receipt = ref(null)
const offer = reactive({ idea: '', plan: '', timeline: '', prototypeLink: '' })
let requestId = 0
let offerRequestId = 0
const identity = computed(() => session.profile ? profileKey(session.profile) : '')
const isOwner = computed(() => session.profile?.role === 'customer' && Number(session.profile.id) === Number(task.value?.ownerId))
const isTeam = computed(() => session.profile?.role === 'team')

async function load() {
  const current = ++requestId
  offerRequestId++
  task.value = null
  loading.value = true
  error.value = null
  offerError.value = null
  receipt.value = null
  sending.value = false
  Object.assign(offer, { idea: '', plan: '', timeline: '', prototypeLink: '' })
  try {
    const result = await api.getTask(route.params.id)
    if (current === requestId) task.value = result
  } catch (cause) {
    if (current === requestId) error.value = cause
  } finally {
    if (current === requestId) loading.value = false
  }
}

async function submitOffer() {
  if (sending.value || !isTeam.value || task.value?.status !== 'published') return
  const payload = {
    idea: offer.idea.trim(),
    plan: offer.plan.trim(),
    timeline: offer.timeline.trim(),
    prototypeLink: offer.prototypeLink.trim() || null,
  }
  if (!payload.idea || !payload.plan || !payload.timeline) {
    offerError.value = new Error('Заполните идею, план и сроки: поля не могут состоять только из пробелов.')
    return
  }
  if (payload.prototypeLink && !/^https?:\/\//i.test(payload.prototypeLink)) {
    offerError.value = new Error('Ссылка на прототип должна начинаться с http:// или https://.')
    return
  }
  const current = ++offerRequestId
  sending.value = true
  offerError.value = null
  try {
    const result = await api.createOffer(task.value.id, payload)
    if (current === offerRequestId) receipt.value = result
  } catch (cause) {
    if (current === offerRequestId) offerError.value = cause
  } finally {
    if (current === offerRequestId) sending.value = false
  }
}

watch([() => route.params.id, identity], load, { immediate: true })
onBeforeUnmount(() => { requestId++; offerRequestId++ })
</script>

<template>
  <section class="stack" :aria-busy="loading">
    <RouterLink class="text-link back-link" to="/catalog"><span aria-hidden="true">←</span> Каталог задач</RouterLink>
    <p v-if="loading" class="panel loading-state" role="status">Загружаем задачу…</p>
    <ApiError v-else-if="error" :error="error" retry @retry="load" />
    <template v-else-if="task">
      <header class="page-heading">
        <div>
          <div class="task-card-meta"><span class="eyebrow">ЗАДАЧА #{{ task.id }}</span><span class="tag">{{ task.status === 'published' ? 'Опубликована' : 'Черновик' }}</span></div>
          <h1>{{ task.title }}</h1>
          <p class="muted">{{ task.organization || 'Организация пока не указана' }}</p>
          <div class="task-card-meta">
            <span class="tag">{{ task.category || 'Тема не указана' }}</span>
            <span>{{ task.region || 'Регион не указан' }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ scopeLabel(task.scope) }}</span>
          </div>
        </div>
        <RouterLink v-if="isOwner" class="button button-secondary" :to="`/tasks/${task.id}/edit`">Редактировать карточку</RouterLink>
        <a v-else-if="isTeam && task.status === 'published'" class="button" href="#offer">Предложить решение <span aria-hidden="true">↗</span></a>
      </header>

      <div class="detail-layout">
        <div class="stack">
          <article class="panel prose task-content">
            <section v-for="field in contentFields" :key="field.key" class="detail-field">
              <h2>{{ field.label }}</h2>
              <p :class="{ muted: !task[field.key] }">{{ task[field.key] || 'Заказчик пока не добавил информацию.' }}</p>
            </section>
          </article>

          <section id="offer" class="panel stack offer-panel" aria-labelledby="offer-title">
            <div>
              <p class="eyebrow">СЛЕДУЮЩИЙ ШАГ</p>
              <h2 id="offer-title">Предложите своё решение</h2>
            </div>
            <div v-if="task.status !== 'published'" class="empty-state">
              <p>Это черновик. Команды смогут откликнуться после публикации задачи.</p>
              <RouterLink v-if="isOwner" class="button" :to="`/tasks/${task.id}/edit`">Подготовить к публикации</RouterLink>
            </div>
            <div v-else-if="receipt" class="success-message" role="status">
              <h3>Отклик отправлен</h3>
              <p>Отклик №{{ receipt.id }} от команды {{ receipt.team?.name || session.profile?.name }} получен. Статус: ожидает решения.</p>
              <RouterLink class="button button-secondary" to="/workspace">Посмотреть мои отклики</RouterLink>
            </div>
            <form v-else-if="isTeam" class="stack" @submit.prevent="submitOffer" :aria-busy="sending">
              <p class="muted">Вы откликаетесь от команды <strong>{{ session.profile.name }}</strong>. Все поля обязательны, кроме ссылки на прототип.</p>
              <div class="form-field">
                <label for="offer-idea">Идея решения</label>
                <textarea id="offer-idea" v-model="offer.idea" required maxlength="10000" rows="4" placeholder="Что вы предлагаете и как это поможет пользователям?" :disabled="sending" />
              </div>
              <div class="form-field">
                <label for="offer-plan">План работы</label>
                <textarea id="offer-plan" v-model="offer.plan" required maxlength="10000" rows="4" placeholder="Основные этапы: исследование, прототип, проверка решения…" :disabled="sending" />
              </div>
              <div class="form-field">
                <label for="offer-timeline">Сроки</label>
                <input id="offer-timeline" v-model="offer.timeline" required maxlength="255" placeholder="Например, прототип за 4 недели" :disabled="sending" />
              </div>
              <div class="form-field">
                <label for="offer-prototype">Ссылка на прототип <span class="muted">(необязательно)</span></label>
                <input id="offer-prototype" v-model="offer.prototypeLink" type="url" maxlength="2048" placeholder="https://…" :disabled="sending" />
              </div>
              <ApiError v-if="offerError" :error="offerError" />
              <div class="form-actions"><button class="button" type="submit" :disabled="sending">{{ sending ? 'Отправляем отклик…' : 'Отправить отклик' }}</button></div>
            </form>
            <div v-else class="role-notice">
              <p>Чтобы отправить отклик, выберите профиль команды в переключателе вверху страницы.</p>
              <p class="muted">Заказчики создают задачи, а команды предлагают решения и план работы.</p>
            </div>
          </section>
        </div>
        <aside class="stack detail-sidebar" aria-label="Готовность задачи">
          <ScorePanel :task="task" />
          <div class="panel">
            <h2>Работа над задачей</h2>
            <p class="muted">Откликов: {{ task.offersCount ?? 0 }}</p>
            <p>Изучите контекст, предложите план и договоритесь с заказчиком о следующем шаге.</p>
          </div>
        </aside>
      </div>
    </template>
  </section>
</template>