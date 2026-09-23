<script setup>
import { RouterLink } from 'vue-router'
import Icon from '../components/Icon.vue'

const customerSteps = [
  { title: 'Опишите проблему', text: 'Откройте «Создать задачу». Укажите название, организацию, тему, регион и охват. Расскажите, что сейчас не работает и кому нужно помочь.' },
  { title: 'Уточните задачу с помощником', text: 'Ответьте на предложенные вопросы. Дополните пользователей, материалы, ограничения, ожидаемый результат и критерии успеха.' },
  { title: 'Проверьте и опубликуйте', text: 'Сохраните карточку, посмотрите рейтинг и его расшифровку. Проверьте сведения и подтвердите публикацию — задача появится в каталоге.' },
  { title: 'Вернитесь к своей задаче', text: 'Откройте «Мой кабинет», чтобы найти черновики и опубликованные задачи. В карточке можно дополнить описание и посмотреть количество полученных откликов.' },
]
const teamSteps = [
  { title: 'Найдите подходящую задачу', text: 'Откройте каталог. Используйте поиск и фильтры по теме, региону и охвату. Сортировка по рейтингу помогает найти задачи с более полным описанием.' },
  { title: 'Изучите условия', text: 'Откройте карточку и прочитайте, кому нужно решение, что должно получиться и какие есть ограничения. Проверьте критерии успеха.' },
  { title: 'Подготовьте предложение', text: 'В карточке задачи нажмите «Перейти к отклику». Заполните идею решения, план работы и сроки. Если есть прототип, добавьте ссылку.' },
  { title: 'Отправьте отклик', text: 'Нажмите «Отправить отклик» и дождитесь подтверждения. Предложение сохранится в разделе «Мой кабинет» — там можно посмотреть его содержание и статус.' },
]
</script>

<template>
  <div class="guide-page stack">
    <header class="guide-heading">
      <p class="eyebrow">ПОНЯТНЫЙ ПУТЬ К СОВМЕСТНОЙ РАБОТЕ</p>
      <h1>Как работает AlemEdu</h1>
      <p>Помогаем учебным заведениям сформулировать реальные задачи,<br class="guide-desktop-break" /> а студенческим командам — найти проект и предложить решение.</p>
    </header>

    <div class="guide-grid">
      <section class="guide-role-panel panel" aria-labelledby="customer-guide-title">
        <div class="guide-role-heading"><span class="guide-role-icon"><Icon name="briefcase" /></span><div><p class="eyebrow">ЕСТЬ ПОТРЕБНОСТЬ</p><h2 id="customer-guide-title">Я заказчик</h2></div></div>
        <p class="guide-role-intro muted">Для школ, колледжей и университетов, которым нужно решение конкретной проблемы.</p>
        <ol class="guide-steps">
          <li v-for="(step, index) in customerSteps" :key="step.title" class="guide-step"><span class="guide-step-index" aria-hidden="true">{{ index + 1 }}</span><div><h3>{{ step.title }}</h3><p>{{ step.text }}</p></div></li>
        </ol>
        <RouterLink class="button" to="/?role=customer#start">Начать как заказчик <Icon name="arrow-right" /></RouterLink>
      </section>

      <section class="guide-role-panel panel" aria-labelledby="team-guide-title">
        <div class="guide-role-heading"><span class="guide-role-icon guide-role-icon-team"><Icon name="users" /></span><div><p class="eyebrow">ЕСТЬ ИДЕИ И НАВЫКИ</p><h2 id="team-guide-title">Я в команде</h2></div></div>
        <p class="guide-role-intro muted">Для студенческих и проектных команд, готовых применить свои навыки на реальной задаче.</p>
        <ol class="guide-steps">
          <li v-for="(step, index) in teamSteps" :key="step.title" class="guide-step"><span class="guide-step-index" aria-hidden="true">{{ index + 1 }}</span><div><h3>{{ step.title }}</h3><p>{{ step.text }}</p></div></li>
        </ol>
        <RouterLink class="button button-secondary" to="/?role=team#start">Начать как команда <Icon name="arrow-right" /></RouterLink>
      </section>
    </div>

    <section id="rating" class="guide-rating-panel" aria-labelledby="guide-rating-title">
      <div class="guide-section-icon"><Icon name="chart" /></div>
      <div class="guide-rating-copy"><p class="eyebrow">ПОНЯТНАЯ ЗАДАЧА — УВЕРЕННЫЙ СТАРТ</p><h2 id="guide-rating-title">Рейтинг показывает полноту описания</h2><p>Оценка от 0 до 100 помогает понять, достаточно ли сведений для начала работы. В карточке задачи есть расшифровка: какие части описания заполнены и что ещё можно уточнить.</p><p>Рейтинг и его расшифровку рассчитывает сервер после сохранения карточки. Интерфейс показывает готовую оценку.</p><div class="guide-rating-note"><Icon name="info" /><span>Высокий рейтинг не означает, что задача простая или важнее других. Он не оценивает качество будущего решения и навыки команды.</span></div></div>
    </section>

    <section class="guide-demo-panel panel" aria-labelledby="demo-guide-title">
      <div class="guide-section-icon"><Icon name="sparkles" /></div>
      <div><h2 id="demo-guide-title">Попробуйте обе стороны в демоверсии</h2><p>Выберите роль на главной или смените демопрофиль в меню. Можно создать задачу от организации, затем открыть её от имени команды и отправить отклик.</p><p class="muted">AI-помощник сейчас работает в деморежиме: уточняющие вопросы формирует локальная программная заглушка. Внешняя AI-модель пока не подключена.</p></div>
      <RouterLink class="guide-start-link" to="/">Перейти к началу <Icon name="arrow-up-right" /></RouterLink>
    </section>
  </div>
</template>

<style scoped>
.guide-page { gap: 1.6rem; }
.guide-heading { padding: .3rem 0 .6rem; }
.guide-heading .eyebrow { margin-bottom: .75rem; }
.guide-heading h1 { font-size: clamp(1.85rem, 3.1vw, 2.55rem); letter-spacing: -.04em; line-height: 1.2; margin: 0 0 .85rem; }
.guide-heading > p:last-child { color: var(--muted, #68756f); font-size: .95rem; line-height: 1.8; margin: 0; }
.guide-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.2rem; }
.guide-role-panel { display: flex; flex-direction: column; padding: 1.65rem; }
.guide-role-heading { display: flex; align-items: center; gap: .9rem; }
.guide-role-icon { display: grid; place-items: center; height: 45px; width: 45px; border-radius: 13px; color: var(--primary, #147d64); background: #eaf4ee; flex-shrink: 0; }
.guide-role-icon-team { color: #736cb0; background: #f0eef8; }
.guide-role-heading .eyebrow { font-size: .58rem; margin: 0 0 .35rem; }
.guide-role-heading h2 { font-size: 1.35rem; letter-spacing: -.025em; margin: 0; }
.guide-role-intro { font-size: .82rem; line-height: 1.75; margin: 1rem 0 0; padding-bottom: 1.2rem; border-bottom: 1px solid var(--border, #e3e8e5); }
.guide-steps { list-style: none; padding: 1.3rem 0 0; margin: 0 0 1.5rem; display: flex; flex-direction: column; gap: 1.3rem; }
.guide-step { display: flex; align-items: flex-start; gap: .85rem; }
.guide-step-index { width: 27px; height: 27px; display: grid; place-items: center; background: var(--surface-muted, #f4f6f5); color: var(--primary, #147d64); border: 1px solid var(--border, #e3e8e5); font-size: .72rem; font-weight: 650; border-radius: 50%; flex-shrink: 0; }
.guide-step h3 { font-size: .87rem; font-weight: 650; margin: .15rem 0 .4rem; line-height: 1.5; }
.guide-step p { font-size: .79rem; line-height: 1.8; margin: 0; color: var(--muted, #68756f); }
.guide-role-panel > .button { margin-top: auto; }
.guide-rating-panel { display: flex; align-items: flex-start; gap: 1.25rem; padding: 1.65rem; border-radius: 15px; background: #edf5ef; border: 1px solid #dce9e0; scroll-margin-top: 1.5rem; }
.guide-section-icon { width: 42px; height: 42px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; color: var(--primary, #147d64); background: #fff; border: 1px solid #dce8df; }
.guide-rating-copy .eyebrow { font-size: .6rem; margin: 0 0 .6rem; }
.guide-rating-copy h2 { font-size: 1.2rem; letter-spacing: -.02em; margin: 0 0 .8rem; }
.guide-rating-copy > p { font-size: .83rem; line-height: 1.8; max-width: 820px; color: #5e7365; margin: 0 0 .7rem; }
.guide-rating-note { border-top: 1px solid #dce8df; padding-top: .85rem; margin-top: 1rem; display: flex; gap: .55rem; font-size: .76rem; line-height: 1.75; color: #456453; }
.guide-rating-note svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px; }
.guide-demo-panel { padding: 1.4rem 1.65rem; display: flex; align-items: flex-start; gap: 1rem; }
.guide-demo-panel .guide-section-icon { background: #f6f5fb; color: #7b72a1; border-color: #ebe8f4; }
.guide-demo-panel h2 { font-size: .95rem; margin: .2rem 0 .6rem; }
.guide-demo-panel p { font-size: .79rem; line-height: 1.8; margin: 0 0 .6rem; color: var(--muted, #68756f); }
.guide-demo-panel p:last-child { margin-bottom: 0; }
.guide-start-link { display: inline-flex; align-items: center; gap: .5rem; font-size: .76rem; color: var(--primary, #147d64); text-decoration: none; white-space: nowrap; margin-left: auto; flex-shrink: 0; font-weight: 600; padding-top: .3rem; }
.guide-start-link:hover { text-decoration: underline; text-underline-offset: 3px; }
.guide-start-link svg { width: 16px; height: 16px; }
@media (max-width: 1100px) {
  .guide-role-panel { padding: 1.3rem; }
  .guide-demo-panel { display: grid; grid-template-columns: 42px minmax(0, 1fr); }
  .guide-demo-panel > div:nth-child(2) { min-width: 0; }
  .guide-start-link { grid-column: 2; width: auto; margin-left: 0; white-space: normal; }
}
@media (max-width: 750px) {
  .guide-grid { grid-template-columns: 1fr; }
  .guide-rating-panel, .guide-demo-panel { padding: 1.25rem; gap: .85rem; }
  .guide-rating-panel .guide-section-icon { display: none; }
  .guide-rating-copy h2 { font-size: 1.15rem; }
  .guide-rating-copy .eyebrow { line-height: 1.7; }
  .guide-desktop-break { display: none; }
  .guide-heading .eyebrow { font-size: .58rem; line-height: 1.7; }
  .guide-heading > p:last-child { font-size: .87rem; }
  .guide-start-link { margin-left: 0; }
}
</style>
