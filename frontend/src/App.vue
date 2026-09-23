<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { session, loadProfiles, selectProfile, profileKey } from './lib/session.js'
import ApiError from './components/ApiError.vue'
import Icon from './components/Icon.vue'
import { editorState } from './lib/editorState.js'

const route = useRoute()
const selected = computed(() => session.profile ? profileKey(session.profile) : '')
const role = computed(() => session.profile?.role === 'customer' ? 'Заказчик' : session.profile?.role === 'team' ? 'Команда' : 'Гость')
const profileInitial = computed(() => (session.profile?.name || 'Г').charAt(0).toUpperCase())
function changeProfile(event) {
  if (editorState.dirty && !window.confirm('Есть несохранённые изменения. Сменить профиль и закрыть форму?')) {
    event.target.value = selected.value
    return
  }
  selectProfile(event.target.value)
}
onMounted(loadProfiles)
</script>

<template>
  <a class="skip-link" href="#main">Перейти к содержимому</a>
  <div class="app-layout">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <RouterLink to="/" class="brand" aria-label="AlemEdu — главная">
          <span class="brand-mark" aria-hidden="true">a<span>·</span></span>
          <span>Alem<span class="brand-accent">Edu</span><span class="brand-period">.</span></span>
        </RouterLink>
        <RouterLink class="mobile-help icon-button" to="/guide" aria-label="Как это работает"><Icon name="help" /></RouterLink>
      </div>
      <div class="workspace-label"><span class="workspace-symbol"><Icon name="layers" :size="17" /></span><div><strong>Образовательные проекты</strong><span>Рабочее пространство</span></div></div>
      <p class="nav-caption">ПЛАТФОРМА</p>
      <nav class="main-nav" aria-label="Главная навигация">
        <RouterLink to="/" exact-active-class="nav-active"><Icon name="home" /><span>Главная</span></RouterLink>
        <RouterLink to="/catalog" active-class="nav-active"><Icon name="grid" /><span>Каталог задач</span></RouterLink>
        <RouterLink to="/workspace" active-class="nav-active"><Icon name="briefcase" /><span>Мой кабинет</span></RouterLink>
        <RouterLink to="/tasks/new" active-class="nav-active" class="nav-create"><Icon name="plus" /><span><span class="sr-only">+ </span>Создать задачу</span></RouterLink>
        <RouterLink to="/guide" active-class="nav-active" class="nav-help"><Icon name="book-open" /><span>Как это работает</span></RouterLink>
      </nav>
      <div class="sidebar-guide">
        <span class="guide-card-icon"><Icon name="sparkles" :size="22" /></span>
        <h2>С чего начать?</h2>
        <p>От первого описания до отклика команды — всего несколько шагов.</p>
        <RouterLink to="/guide">Разобраться за минуту <Icon name="arrow-right" :size="16" /></RouterLink>
      </div>
      <div class="sidebar-bottom"><span class="status-dot"></span><span>Демонстрационная версия</span></div>
    </aside>

    <div class="app-body">
      <header class="topbar">
        <div class="breadcrumb"><span>AlemEdu</span><Icon name="chevron-right" :size="14" /><strong>{{ route.meta.title || 'Образовательные задачи' }}</strong></div>
        <div class="account-area">
          <span class="role-badge">{{ role }}</span>
          <span class="profile-avatar" aria-hidden="true">{{ profileInitial }}</span>
          <label class="profile-picker">
            <span>Демопрофиль</span>
            <select :value="selected" :disabled="session.loading || editorState.busy" @change="changeProfile">
              <option value="">{{ session.loading ? 'Загрузка профилей…' : 'Гость · просмотр' }}</option>
              <optgroup label="Заказчики">
                <option v-for="p in session.profiles.customers" :key="'customer:' + p.id" :value="profileKey(p)">{{ p.name }}</option>
              </optgroup>
              <optgroup label="Команды">
                <option v-for="p in session.profiles.teams" :key="'team:' + p.id" :value="profileKey(p)">{{ p.name }}</option>
              </optgroup>
            </select>
          </label>
        </div>
      </header>
      <main id="main" class="page-container" tabindex="-1">
        <ApiError v-if="session.error" :error="session.error" retry @retry="loadProfiles" />
        <RouterView />
      </main>
      <footer class="site-footer">
        <span>Создаём полезные решения вместе.</span>
        <RouterLink to="/guide">Помощь и первые шаги <Icon name="arrow-up-right" :size="14" /></RouterLink>
        <span class="footer-brand">AlemEdu<span>© 2026</span></span>
      </footer>
    </div>
  </div>
</template>
