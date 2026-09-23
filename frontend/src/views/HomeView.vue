<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, isRequestCanceled } from '../lib/api'
import { session, loadProfiles, selectProfile, profileKey } from '../lib/session'
import Icon from '../components/Icon.vue'
import TaskCard from '../components/TaskCard.vue'
import ApiError from '../components/ApiError.vue'

const route = useRoute()
const router = useRouter()
const requestedRole = ['customer', 'team'].includes(route.query.role) ? route.query.role : null
const role = ref(requestedRole || session.profile?.role || 'customer')
const roleChosen = ref(Boolean(requestedRole))
const starting = ref(false)
const tasks = ref([])
const loading = ref(true)
const error = ref(null)
let controller
let requestId = 0
let disposed = false

const isCustomer = computed(() => role.value === 'customer')
const availableProfiles = computed(() => isCustomer.value ? session.profiles.customers : session.profiles.teams)
const canStart = computed(() => !session.loading && !session.error && availableProfiles.value.length > 0 && !starting.value)
const steps = computed(() => isCustomer.value ? [
  { icon: 'file-text', title: 'Опишите потребность', text: 'Расскажите, какую проблему нужно решить в вашей организации.' },
  { icon: 'sparkles', title: 'Уточните детали', text: 'Ответьте на вопросы помощника и проверьте полноту описания.' },
  { icon: 'users', title: 'Получите отклики', text: 'Опубликуйте задачу, чтобы команды предложили свои решения.' },
] : [
  { icon: 'search', title: 'Найдите свою задачу', text: 'Выберите тему, регион и задачу, в которой пригодятся ваши навыки.' },
  { icon: 'file-text', title: 'Изучите условия', text: 'Посмотрите ожидаемый результат, сроки и критерии успеха.' },
  { icon: 'send', title: 'Предложите решение', text: 'Отправьте идею, план и сроки. Отклик сохранится в вашем кабинете.' },
])

watch(() => session.profile?.role, (nextRole) => {
  if (!roleChosen.value && ['customer', 'team'].includes(nextRole)) role.value = nextRole
})

function chooseRole(nextRole) {
  role.value = nextRole
  roleChosen.value = true
}

async function start() {
  if (!canStart.value) return
  starting.value = true
  try {
    if (session.profile?.role !== role.value) selectProfile(profileKey(availableProfiles.value[0]))
    await router.push(isCustomer.value ? '/tasks/new' : '/catalog')
  } finally {
    starting.value = false
  }
}

async function loadTasks() {
  const current = ++requestId
  controller?.abort()
  controller = new AbortController()
  loading.value = true
  error.value = null
  try {
    const result = await api.listTasks({ sort: 'newest' }, { signal: controller.signal })
    if (disposed || current !== requestId) return
    tasks.value = result.data.slice(0, 3)
  } catch (cause) {
    if (!disposed && current === requestId && !isRequestCanceled(cause)) error.value = cause
  } finally {
    if (!disposed && current === requestId) loading.value = false
  }
}

onMounted(() => {
  if (!session.profile && !session.error) loadProfiles()
  loadTasks()
})
onBeforeUnmount(() => { disposed = true; requestId++; controller?.abort() })
</script>

<template>
  <div class="home-page stack">
    <section class="home-hero" aria-labelledby="home-title">
      <div class="hero-copy">
        <p class="eyebrow"><span class="hero-dot" aria-hidden="true"></span> ОТ ПОТРЕБНОСТИ К РЕШЕНИЮ</p>
        <h1 id="home-title">Реальные задачи.<br /><span>Возможности для команд.</span></h1>
        <p class="hero-description">Учебные заведения делятся своими задачами.<br class="desktop-break" /> Студенческие команды предлагают решения.<br class="desktop-break" /> AlemEdu помогает им найти друг друга.</p>
        <div class="hero-actions">
          <a class="button" href="#start">Начать работу <Icon name="arrow-right" /></a>
          <RouterLink class="button button-secondary" to="/catalog">Посмотреть задачи</RouterLink>
        </div>
        <p class="hero-footnote"><Icon name="check-circle" /> От первого описания до отклика команды — в одном месте</p>
      </div>
      <div class="hero-visual" aria-label="Как идея становится совместным проектом">
        <div class="hero-orbit hero-orbit-one" aria-hidden="true"></div>
        <div class="hero-orbit hero-orbit-two" aria-hidden="true"></div>
        <div class="hero-visual-heading"><span class="hero-small-dot"></span> Здесь начинается сотрудничество</div>
        <div class="visual-project-card">
          <div class="visual-card-top"><span class="visual-icon"><Icon name="briefcase" /></span><span class="visual-card-tag">Задача от организации</span></div>
          <strong>Есть потребность?<br />Дайте ей форму.</strong>
          <p>Проблема, ожидаемый результат<br />и понятные условия для команды.</p>
          <div class="visual-check"><Icon name="check-circle" /> Описание готово к работе</div>
        </div>
        <div class="visual-connector" aria-hidden="true"><span></span><Icon name="arrow-right" /><span></span></div>
        <div class="visual-team-card">
          <div class="visual-team-avatars" aria-hidden="true"><span><Icon name="users" /></span><span><Icon name="sparkles" /></span></div>
          <div><strong>Команда предлагает решение</strong><p>Идея + план + сроки</p></div>
          <Icon class="visual-arrow" name="arrow-up-right" />
        </div>
        <div class="hero-visual-footer"><Icon name="layers" /><span>Образование объединяет</span></div>
      </div>
    </section>

    <section id="start" class="journey-card panel" aria-labelledby="journey-title">
      <div class="journey-heading">
        <div><p class="eyebrow">ВАШ ПЕРВЫЙ ШАГ</p><h2 id="journey-title">С чего начнём?</h2><p class="muted">Выберите свою роль — покажем следующий шаг.</p></div>
        <div class="role-switch" role="group" aria-label="Ваша роль на площадке">
          <button class="role-choice" :class="{ active: isCustomer }" type="button" :aria-pressed="isCustomer" @click="chooseRole('customer')"><Icon name="briefcase" /> Я заказчик</button>
          <button class="role-choice" :class="{ active: !isCustomer }" type="button" :aria-pressed="!isCustomer" @click="chooseRole('team')"><Icon name="users" /> Я в команде</button>
        </div>
      </div>
      <div class="journey-steps" aria-live="polite">
        <div v-for="(step, index) in steps" :key="step.title" class="journey-step">
          <span class="journey-step-number">0{{ index + 1 }}</span>
          <div><h3>{{ step.title }}</h3><p class="muted">{{ step.text }}</p></div>
        </div>
      </div>
      <div class="journey-bottom">
        <div><p class="journey-role-context">{{ isCustomer ? 'Для школ, колледжей и университетов' : 'Для студенческих и проектных команд' }}</p><p class="muted journey-demo-note">Демо: можно попробовать обе роли.</p></div>
        <button class="button" type="button" :disabled="!canStart" @click="start">{{ session.loading ? 'Загружаем профили…' : starting ? 'Открываем…' : isCustomer ? 'Создать задачу' : 'Найти задачу' }}<Icon name="arrow-right" /></button>
      </div>
      <div v-if="!session.loading && (session.error || !availableProfiles.length)" class="home-profile-hint" role="status">
        <p>{{ session.error ? 'Не удалось загрузить демопрофили. Повторите попытку, чтобы начать работу.' : 'Для этой роли пока нет доступных демопрофилей. Каталог можно посмотреть без выбора роли.' }}</p>
        <button class="button button-secondary" type="button" @click="loadProfiles">Повторить загрузку</button>
      </div>
    </section>

    <section class="home-catalog" aria-labelledby="recent-title" :aria-busy="loading">
      <div class="home-section-heading"><div><p class="eyebrow">НАЙДИТЕ ТО, ЧТО ВАМ БЛИЗКО</p><h2 id="recent-title">Новые задачи на площадке</h2></div><RouterLink class="home-text-link" to="/catalog">Весь каталог <Icon name="arrow-up-right" /></RouterLink></div>
      <div v-if="loading" class="home-task-loading" role="status"><span class="home-loading-dot"></span> Загружаем опубликованные задачи…</div>
      <ApiError v-else-if="error" :error="error" retry @retry="loadTasks" />
      <div v-else-if="tasks.length" class="task-grid"><TaskCard v-for="task in tasks" :key="task.id" :task="task" /></div>
      <div v-else class="home-empty panel"><Icon name="layers" /><div><h3>Здесь появятся реальные задачи</h3><p class="muted">Станьте первым заказчиком: опишите потребность и пригласите команды предложить решение.</p></div><a class="button button-secondary" href="#start">Начать с задачи <Icon name="arrow-right" /></a></div>
    </section>

    <section class="home-rating-note" aria-labelledby="rating-note-title">
      <div class="home-rating-icon"><Icon name="chart" /></div>
      <div><h2 id="rating-note-title">Что означает рейтинг задачи?</h2><p>Он показывает, насколько полно описана задача: понятны ли пользователи, условия и ожидаемый результат. Это не оценка сложности проекта или качества команды.</p></div>
      <RouterLink class="home-text-link" to="/guide#rating">Как это работает <Icon name="arrow-right" /></RouterLink>
    </section>
  </div>
</template>

<style scoped>
.home-page { gap: 2.25rem; }
.home-hero { display: grid; grid-template-columns: 1.25fr 1fr; gap: 2rem; align-items: center; padding: .75rem 0 .35rem; }
.hero-copy .eyebrow { display: flex; align-items: center; gap: .55rem; margin-bottom: 1.25rem; }
.hero-dot, .hero-small-dot { display: inline-block; flex: 0 0 auto; width: 7px; height: 7px; border-radius: 50%; background: var(--primary, #147d64); }
.hero-copy h1 { max-width: 650px; font-size: 3.15rem; line-height: 1.15; letter-spacing: 0; font-weight: 750; margin: 0 0 1.1rem; }
.hero-copy h1 span { color: var(--primary, #147d64); }
.hero-description { font-size: 1rem; color: var(--muted, #68756f); line-height: 1.8; max-width: 530px; margin-bottom: 1.6rem; }
.hero-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem; }
.hero-footnote { display: flex; align-items: flex-start; gap: .45rem; color: var(--muted, #68756f); font-size: .73rem; line-height: 1.6; margin: 1.05rem 0 0; }
.hero-footnote svg { flex: 0 0 auto; width: 15px; height: 15px; margin-top: 2px; color: var(--primary, #147d64); }
.hero-visual { position: relative; isolation: isolate; overflow: hidden; background: #e7f2e9; border: 1px solid #dceadf; border-radius: 22px; padding: 1.45rem 1.7rem 1rem; min-width: 0; }
.hero-visual-heading { position: relative; display: flex; align-items: center; gap: .5rem; font-size: .68rem; font-weight: 600; color: #47695a; margin-bottom: 1.3rem; }
.hero-small-dot { width: 5px; height: 5px; }
.hero-orbit { position: absolute; z-index: -1; width: 340px; height: 340px; border: 1px solid #d2e5d7; border-radius: 50%; top: 36px; right: -145px; }
.hero-orbit-two { width: 440px; height: 440px; top: -14px; right: -195px; }
.visual-project-card { position: relative; background: #fff; border: 1px solid #deebe2; box-shadow: 0 10px 30px #29503e09; padding: 1.1rem 1.2rem .95rem; border-radius: 14px; max-width: 330px; margin: 0 1rem 0 0; }
.visual-card-top { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .85rem; }
.visual-icon { width: 35px; height: 35px; display: grid; place-items: center; border-radius: 10px; color: #147d64; background: #eaf4ee; }
.visual-card-tag { font-size: .59rem; font-weight: 600; color: #78877d; }
.visual-project-card > strong { display: block; font-size: 1.3rem; line-height: 1.35; letter-spacing: 0; color: #203c30; }
.visual-project-card > p { font-size: .75rem; line-height: 1.6; color: #738276; margin: .55rem 0 .9rem; }
.visual-check { border-top: 1px solid #eff3f0; padding-top: .75rem; display: flex; align-items: center; gap: .45rem; color: #147d64; font-size: .68rem; font-weight: 600; }
.visual-check svg { width: 15px; height: 15px; }
.visual-connector { height: 28px; display: flex; justify-content: center; align-items: center; color: #81a58d; }
.visual-connector svg { transform: rotate(90deg); width: 17px; height: 17px; }
.visual-team-card { display: flex; align-items: center; gap: .75rem; background: #fff; border: 1px solid #dce9de; border-radius: 12px; padding: .9rem; margin-left: .7rem; box-shadow: 0 6px 20px #29503e05; }
.visual-team-card strong { font-size: .73rem; font-weight: 650; color: #203c30; display: block; }
.visual-team-card p { margin: .25rem 0 0; font-size: .65rem; color: #78877d; }
.visual-team-avatars { display: flex; padding-left: 0; flex-shrink: 0; }
.visual-team-avatars span { width: 31px; height: 31px; display: grid; place-items: center; border-radius: 50%; background: #edf2fa; color: #6d7ea0; border: 2px solid #fff; }
.visual-team-avatars span + span { margin-left: -8px; color: #b98c37; background: #faf3df; }
.visual-team-avatars svg { width: 14px; height: 14px; }
.visual-arrow { width: 16px; height: 16px; flex-shrink: 0; margin-left: auto; color: #147d64; }
.hero-visual-footer { display: flex; align-items: center; justify-content: center; gap: .35rem; color: #6f8b7a; font-size: .59rem; margin-top: .95rem; }
.hero-visual-footer svg { width: 13px; height: 13px; }
.journey-card { padding: 1.65rem 1.8rem 1.35rem; scroll-margin-top: 1.5rem; }
.journey-heading { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; }
.journey-heading .eyebrow { margin-bottom: .45rem; font-size: .6rem; }
.journey-heading h2 { margin: 0; font-size: 1.45rem; letter-spacing: 0; }
.journey-heading .muted { margin: .4rem 0 0; font-size: .82rem; }
.role-switch { display: flex; gap: .2rem; background: var(--surface-muted, #f4f6f5); border: 1px solid var(--border, #e3e8e5); padding: .3rem; border-radius: 11px; flex-shrink: 0; }
.role-choice { appearance: none; display: flex; align-items: center; justify-content: center; gap: .5rem; border: 1px solid transparent; padding: .65rem .9rem; border-radius: 8px; background: transparent; color: var(--muted, #68756f); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; transition: background .15s, color .15s, box-shadow .15s; }
.role-choice svg { width: 17px; height: 17px; }
.role-choice.active { color: var(--primary, #147d64); background: var(--surface, #fff); border-color: #e0e7e1; box-shadow: 0 2px 5px #143b2910; }
.role-choice:hover { color: var(--primary, #147d64); }
.journey-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 1.6rem 0 1.35rem; }
.journey-step { display: flex; gap: .85rem; align-items: flex-start; }
.journey-step-number { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 9px; background: #edf5f0; color: var(--primary, #147d64); font-size: .69rem; font-weight: 700; flex-shrink: 0; }
.journey-step h3 { font-size: .84rem; line-height: 1.5; margin: .25rem 0 .35rem; font-weight: 650; }
.journey-step p { font-size: .75rem; line-height: 1.75; margin: 0; }
.journey-bottom { border-top: 1px solid var(--border, #e3e8e5); padding-top: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.journey-role-context { font-size: .76rem; font-weight: 600; margin: 0; }
.journey-demo-note { font-size: .7rem; margin: .3rem 0 0; }
.home-profile-hint { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 1rem; color: #876336; font-size: .8rem; }
.home-profile-hint p { margin: 0; }
.home-profile-hint button { flex-shrink: 0; }
.home-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1.2rem; }
.home-section-heading .eyebrow { font-size: .59rem; margin-bottom: .5rem; }
.home-section-heading h2 { margin: 0; font-size: 1.4rem; letter-spacing: 0; }
.home-text-link { display: inline-flex; align-items: center; gap: .5rem; color: var(--primary, #147d64); text-decoration: none; white-space: nowrap; font-size: .76rem; font-weight: 600; }
.home-text-link:hover { text-decoration: underline; text-underline-offset: 3px; }
.home-text-link svg { width: 16px; height: 16px; }
.home-catalog .task-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.home-task-loading { padding: 2.5rem; border: 1px dashed var(--border, #e3e8e5); border-radius: 14px; display: flex; justify-content: center; align-items: center; gap: .65rem; font-size: .85rem; color: var(--muted, #68756f); }
.home-loading-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary, #147d64); }
.home-empty { display: flex; align-items: center; gap: 1rem; padding: 1.5rem; }
.home-empty > svg { color: var(--primary, #147d64); width: 28px; height: 28px; flex-shrink: 0; }
.home-empty h3 { margin: 0; font-size: .95rem; }
.home-empty p { margin: .4rem 0 0; font-size: .8rem; }
.home-empty .button { flex-shrink: 0; margin-left: auto; }
.home-rating-note { display: flex; align-items: center; gap: 1rem; background: #eef3f0; border: 1px solid #e1e8e3; border-radius: 14px; padding: 1.35rem 1.5rem; }
.home-rating-icon { width: 40px; height: 40px; border: 1px solid #d5e2d9; background: #f8fbf9; display: grid; place-items: center; border-radius: 11px; color: var(--primary, #147d64); flex-shrink: 0; }
.home-rating-note h2 { font-size: .9rem; margin: 0 0 .4rem; font-weight: 650; }
.home-rating-note p { max-width: 630px; color: var(--muted, #68756f); font-size: .76rem; line-height: 1.7; margin: 0; }
.home-rating-note .home-text-link { margin-left: auto; }
@media (max-width: 1200px) {
  .home-hero { grid-template-columns: 1.1fr 1fr; gap: 1.3rem; }
  .hero-visual { padding: 1.2rem; }
  .hero-copy h1 { font-size: 2.35rem; }
  .hero-description { font-size: .87rem; }
  .journey-card { padding: 1.35rem; }
  .journey-steps { gap: 1rem; }
  .home-catalog .task-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .home-catalog .task-grid > :last-child:nth-child(odd) { grid-column: 1 / -1; }
}
@media (max-width: 900px) {
  .home-hero { grid-template-columns: 1fr; }
  .hero-copy h1 { font-size: 3rem; }
  .hero-description { font-size: 1rem; }
  .hero-visual { display: none; }
  .journey-heading { align-items: flex-start; flex-direction: column; gap: 1.1rem; }
  .journey-steps { gap: 1.2rem; }
  .journey-step { flex-direction: column; gap: .35rem; }
  .home-rating-note { flex-wrap: wrap; align-items: flex-start; }
  .home-rating-note > div:nth-child(2) { flex: 1; }
  .home-rating-note .home-text-link { margin-left: 56px; width: 100%; }
}
@media (max-width: 600px) {
  .home-page { gap: 1.8rem; }
  .home-hero { padding-top: .2rem; }
  .hero-copy .eyebrow { font-size: .58rem; }
  .hero-copy h1 { font-size: 2.2rem; }
  .hero-description { font-size: .9rem; }
  .desktop-break { display: none; }
  .hero-actions { align-items: stretch; }
  .hero-actions .button { flex: 1 1 150px; }
  .hero-footnote { font-size: .69rem; }
  .journey-card { padding: 1.2rem; }
  .role-switch { width: 100%; }
  .role-choice { flex: 1; padding: .65rem .5rem; font-size: .75rem; }
  .journey-heading { gap: 1rem; }
  .journey-steps { grid-template-columns: 1fr; gap: 1.15rem; padding: 1.3rem 0; }
  .journey-step { flex-direction: row; gap: .8rem; }
  .journey-step h3 { margin-top: .2rem; }
  .journey-step p { font-size: .78rem; }
  .journey-bottom { flex-direction: column; align-items: stretch; }
  .journey-bottom .button { width: 100%; }
  .home-profile-hint { flex-direction: column; align-items: stretch; }
  .home-section-heading { flex-wrap: wrap; gap: .8rem; }
  .home-section-heading h2 { font-size: 1.3rem; }
  .home-catalog .task-grid { grid-template-columns: 1fr; }
  .home-empty { flex-direction: column; align-items: flex-start; }
  .home-empty .button { margin-left: 0; }
  .home-rating-note { padding: 1.15rem; gap: .85rem; }
  .home-rating-icon { width: 33px; height: 33px; border-radius: 9px; }
  .home-rating-note .home-text-link { margin-left: 47px; }
}
</style>
