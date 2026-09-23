<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../lib/api'
import { session, profileKey, selectProfile } from '../lib/session'
import { contentFields, scopeLabel } from '../lib/fields'
import ApiError from '../components/ApiError.vue'
import ScorePanel from '../components/ScorePanel.vue'
import Icon from '../components/Icon.vue'

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
    if (current === offerRequestId) {
      receipt.value = result
      task.value.offersCount = (task.value.offersCount ?? 0) + 1
    }
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
    <RouterLink class="text-link back-link" to="/catalog"><Icon class="detail-back-icon" name="arrow-right" /> Каталог задач</RouterLink>
    <p v-if="loading" class="panel loading-state" role="status">Загружаем задачу…</p>
    <ApiError v-else-if="error" :error="error" retry @retry="load" />
    <template v-else-if="task">
      <header class="page-heading detail-heading">
        <div>
          <div class="task-card-meta"><span class="eyebrow">ЗАДАЧА #{{ task.id }}</span><span class="detail-status" :class="{ published: task.status === 'published' }"><span></span>{{ task.status === 'published' ? 'Открыта для откликов' : 'Черновик' }}</span></div>
          <h1>{{ task.title }}</h1>
          <p class="detail-organization"><Icon name="briefcase" />{{ task.organization || 'Организация пока не указана' }}</p>
          <div class="task-card-meta detail-meta">
            <span class="tag">{{ task.category || 'Тема не указана' }}</span>
            <span><Icon name="map-pin" />{{ task.region || 'Регион не указан' }}</span>
            <span><Icon name="layers" />{{ scopeLabel(task.scope) }}</span>
          </div>
        </div>
        <div v-if="isOwner" class="form-actions detail-owner-actions">
          <RouterLink v-if="task.status === 'published'" class="button" :to="'/tasks/' + task.id + '/offers'">Отклики ({{ task.offersCount ?? 0 }}) <Icon name="users" /></RouterLink>
          <RouterLink class="button button-secondary" :to="'/tasks/' + task.id + '/edit'">Редактировать карточку <Icon name="arrow-up-right" /></RouterLink>
        </div>
        <a v-else-if="isTeam && task.status === 'published'" class="button" href="#offer">Предложить решение <Icon name="arrow-up-right" /></a>
      </header>

      <div class="detail-layout">
        <div class="stack">
          <article class="panel prose task-content">
            <div class="detail-content-heading"><Icon name="file-text" /><h2>О задаче</h2></div>
            <section v-for="field in contentFields" :key="field.key" class="detail-field">
              <h2>{{ field.label }}</h2>
              <p :class="{ muted: !task[field.key] }">{{ task[field.key] || 'Заказчик пока не добавил информацию.' }}</p>
            </section>
          </article>

          <p v-if="task.scope === 'institution' && task.status === 'published'" class="institution-access"><Icon name="users" />Задача для своего учреждения открыта всем командам.</p>
          <section v-if="!isOwner" id="offer" class="panel stack offer-panel" aria-labelledby="offer-title">
            <div class="offer-heading">
              <span class="offer-heading-icon"><Icon :name="receipt ? 'check-circle' : 'send'" /></span>
              <div><p class="eyebrow">СЛЕДУЮЩИЙ ШАГ</p><h2 id="offer-title">Предложите своё решение</h2></div>
            </div>
            <div v-if="task.status !== 'published'" class="role-notice">
              <p>Это черновик. Команды смогут откликнуться после публикации задачи.</p>
              <RouterLink v-if="isOwner" class="button" :to="'/tasks/' + task.id + '/edit'">Подготовить к публикации <Icon name="arrow-right" /></RouterLink>
            </div>
            <div v-else-if="receipt" class="success-message offer-receipt" role="status">
              <h3>Отклик отправлен</h3>
              <p>Предложение №{{ receipt.id }} от команды {{ receipt.team?.name || session.profile?.name }} получено. Заказчик увидит вашу идею и план работы.</p>
              <p>Статус: <strong>ожидает решения</strong>. Следите за решением в разделе «Мои отклики».</p>
              <RouterLink class="button button-secondary" to="/workspace">Посмотреть мои отклики <Icon name="arrow-right" /></RouterLink>
            </div>
            <form v-else-if="isTeam" class="stack" @submit.prevent="submitOffer" :aria-busy="sending">
              <div class="offer-team"><Icon name="users" /><div><small>Отклик от команды</small><strong>{{ session.profile.name }}</strong></div></div>
              <p class="muted offer-lead">Расскажите, как вы решите задачу. Заказчик получит предложение, а статус отклика появится в вашем кабинете. Все поля обязательны, кроме ссылки на прототип.</p>
              <div class="form-field">
                <label for="offer-idea">Идея решения</label>
                <textarea id="offer-idea" v-model="offer.idea" required maxlength="10000" rows="4" placeholder="Что вы предлагаете и как это поможет пользователям?" :disabled="sending" />
              </div>
              <div class="form-field">
                <label for="offer-plan">План работы</label>
                <textarea id="offer-plan" v-model="offer.plan" required maxlength="10000" rows="4" placeholder="Например: поговорим с пользователями, соберём прототип и проверим его на реальных сценариях." :disabled="sending" />
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
              <div class="offer-submit"><p class="muted"><Icon name="info" />Отклик передаётся заказчику на рассмотрение.</p><button class="button" type="submit" :disabled="sending">{{ sending ? 'Отправляем отклик…' : 'Отправить отклик' }}<Icon v-if="!sending" name="send" /></button></div>
            </form>
            <div v-else class="role-notice">
              <h3>{{ isOwner ? 'Ваша задача готова принимать предложения' : 'Откликайтесь от имени команды' }}</h3>
              <p class="muted">{{ isOwner ? 'Команды изучат описание и предложат свои идеи. Для демонстрации отклика можно переключиться на профиль команды.' : 'Команда может предложить идею, план и сроки. Выберите команду, чтобы открыть форму отклика.' }}</p>
              <button v-if="session.profiles.teams.length" type="button" class="button button-secondary" @click="selectProfile(profileKey(session.profiles.teams[0]))">Выбрать команду <Icon name="arrow-right" /></button>
              <p v-else class="muted">Профили команд пока недоступны. Попробуйте обновить список профилей.</p>
            </div>
          </section>
        </div>
        <aside class="stack detail-sidebar" aria-label="Готовность задачи">
          <section class="panel detail-next-step">
            <div class="detail-next-icon"><Icon :name="isOwner ? 'briefcase' : 'users'" /></div>
            <h2>{{ isOwner ? 'Ваша задача' : 'Есть идея решения?' }}</h2>
            <p class="muted">{{ isOwner ? (task.status === 'published' ? 'Карточка опубликована в каталоге. Актуальное описание поможет командам предложить подходящее решение.' : 'Проверьте описание и опубликуйте задачу, чтобы получить предложения от команд.') : 'Изучите описание и подготовьте отклик: идею, план работы и сроки.' }}</p>
            <div class="detail-offer-count"><span>Получено откликов</span><strong>{{ task.offersCount ?? 0 }}</strong></div>
            <RouterLink v-if="isOwner && task.status === 'published'" class="button full-width" :to="'/tasks/' + task.id + '/offers'">Рассмотреть отклики <Icon name="arrow-right" /></RouterLink>
            <RouterLink v-if="isOwner" class="button button-secondary full-width" :to="'/tasks/' + task.id + '/edit'">{{ task.status === 'published' ? 'Дополнить описание' : 'Продолжить подготовку' }}<Icon name="arrow-right" /></RouterLink>
            <a v-else-if="task.status === 'published'" class="button full-width" href="#offer">{{ isTeam ? 'Перейти к отклику' : 'Как отправить отклик' }}<Icon name="arrow-right" /></a>
          </section>
          <ScorePanel :task="task" />
        </aside>
      </div>
    </template>
  </section>
</template>

<style scoped>
.detail-back-icon { transform: rotate(180deg); }
.detail-heading { align-items: flex-start; padding-bottom: 1rem; }
.detail-heading h1 { max-width: 820px; font-size: 2rem; line-height: 1.25; overflow-wrap: anywhere; }
.detail-owner-actions { flex-shrink: 0; max-width: 290px; }
.institution-access { display: flex; align-items: flex-start; gap: 8px; margin: 0; padding-block: 14px; border-block: 1px solid var(--border); color: var(--primary); }
.detail-organization { display: flex; align-items: center; gap: .5rem; color: var(--muted); font-size: .88rem; margin: .85rem 0; }
.detail-organization :deep(svg), .detail-meta :deep(svg) { width: 16px; height: 16px; }
.detail-meta > span { display: inline-flex; align-items: center; gap: .35rem; font-size: .77rem; }
.detail-status { display: inline-flex; align-items: center; gap: .4rem; border-radius: 5px; padding: .3rem .55rem; color: var(--muted); background: #eef1ef; font-size: .7rem; }
.detail-status > span { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.detail-status.published { background: #eaf5ef; color: var(--primary); }
.detail-content-heading { display: flex; align-items: center; gap: .6rem; border-bottom: 1px solid var(--border); padding-bottom: 1.1rem; margin-bottom: 1.4rem; color: var(--primary); }
.detail-content-heading h2 { font-size: 1.1rem; color: var(--text); margin: 0; }
.detail-content-heading :deep(svg) { width: 20px; height: 20px; }
.detail-field + .detail-field { padding-top: 1.25rem; margin-top: 1.25rem; border-top: 1px solid var(--border); }
.detail-field h2 { font-size: .85rem; font-weight: 650; margin-bottom: .55rem; }
.detail-field p { font-size: .87rem; line-height: 1.75; overflow-wrap: anywhere; margin-bottom: 0; }
.offer-panel { scroll-margin-top: 100px; }
.offer-heading { display: flex; gap: .8rem; align-items: center; }
.offer-heading h2 { margin: 0; font-size: 1.25rem; }
.offer-heading .eyebrow { margin-bottom: .3rem; }
.offer-heading-icon, .detail-next-icon { width: 40px; height: 40px; border-radius: 10px; background: #eaf5ef; display: inline-flex; align-items: center; justify-content: center; color: var(--primary); flex-shrink: 0; }
.offer-team { display: flex; gap: .7rem; align-items: center; border: 1px solid var(--border); background: var(--surface-muted); border-radius: 8px; padding: .85rem 1rem; }
.offer-team > :deep(svg) { color: var(--primary); }
.offer-team small, .offer-team strong { display: block; }
.offer-team small { font-size: .7rem; color: var(--muted); margin-bottom: .2rem; }
.offer-team strong { font-size: .85rem; font-weight: 600; }
.offer-lead { font-size: .83rem; line-height: 1.65; margin: 0; }
.offer-submit { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: .5rem; }
.offer-submit p { display: flex; align-items: flex-start; gap: .4rem; margin: 0; font-size: .72rem; max-width: 245px; }
.offer-submit p :deep(svg) { flex-shrink: 0; width: 15px; height: 15px; }
.offer-submit button { flex-shrink: 0; }
.offer-receipt p { line-height: 1.65; font-size: .88rem; }
.offer-receipt .button { margin-top: .5rem; }
.role-notice h3 { font-size: 1rem; margin: 0 0 .65rem; }
.role-notice p { font-size: .85rem; line-height: 1.65; }
.detail-next-icon { margin-bottom: 1rem; }
.detail-next-step h2 { margin: 0 0 .6rem; font-size: 1.05rem; }
.detail-next-step > p { font-size: .8rem; line-height: 1.65; }
.detail-offer-count { display: flex; align-items: center; justify-content: space-between; padding: .85rem 0; border-top: 1px solid var(--border); margin: 1rem 0 .5rem; font-size: .77rem; color: var(--muted); }
.detail-offer-count strong { color: var(--text); font-size: 1.1rem; }
.detail-next-step .button { font-size: .78rem; }
@media (max-width: 600px) {
  .offer-submit { flex-direction: column; align-items: stretch; }
  .offer-submit p { max-width: none; }
  .detail-heading > .button { width: 100%; }
}
</style>
