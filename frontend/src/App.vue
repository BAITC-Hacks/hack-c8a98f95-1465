<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { session, loadProfiles, selectProfile, profileKey } from './lib/session.js'
import ApiError from './components/ApiError.vue'
import { editorState } from './lib/editorState.js'
const selected = computed(() => session.profile ? profileKey(session.profile) : '')
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
  <header class="site-header">
    <div class="header-inner">
      <RouterLink to="/catalog" class="brand" aria-label="AlemEdu — главная">
        <span class="brand-mark" aria-hidden="true">a<span>·</span></span>
        <span>Alem<span class="brand-accent">Edu</span></span>
      </RouterLink>
      <nav aria-label="Главная навигация">
        <RouterLink to="/catalog">Каталог задач</RouterLink>
        <RouterLink to="/workspace">Мой кабинет</RouterLink>
        <RouterLink to="/tasks/new" class="nav-create">+ Создать задачу</RouterLink>
      </nav>
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
    <RouterLink to="/catalog" class="brand footer-brand">AlemEdu<span class="brand-accent">.</span></RouterLink>
    <span>Реальные задачи. Совместные решения.</span>
    <span class="demo-label"><span class="status-dot"></span> Демонстрационная версия</span>
  </footer>
</template>
