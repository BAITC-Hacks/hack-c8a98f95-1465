<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <section class="panel empty-state">
    <p class="eyebrow">404</p>
    <h1>Страница не найдена</h1>
    <p class="muted">Возможно, адрес изменился. Все опубликованные задачи доступны в каталоге.</p>
    <RouterLink class="button" to="/catalog">Открыть каталог</RouterLink>
  </section>
</template>