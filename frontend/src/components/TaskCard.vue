<script setup>
import { RouterLink } from 'vue-router'
import { scopeLabel } from '../lib/fields'

defineProps({ task: { type: Object, required: true } })
</script>

<template>
  <article class="task-card panel">
    <div class="task-card-top">
      <span class="tag">{{ task.category || 'Тема не указана' }}</span>
      <span v-if="task.status === 'draft'" class="tag tag-neutral">Черновик</span>
      <span class="task-card-score" :aria-label="`Рейтинг готовности: ${task.score} из 100`">
        <strong>{{ task.score }}</strong><span class="muted"> / 100</span>
      </span>
    </div>
    <h2><RouterLink :to="`/tasks/${task.id}`">{{ task.title }}</RouterLink></h2>
    <p class="task-card-description">{{ task.context || 'Описание пока не добавлено.' }}</p>
    <p class="muted task-card-organization">{{ task.organization || 'Организация не указана' }}</p>
    <div class="task-card-meta">
      <span>{{ task.region || 'Регион не указан' }}</span>
      <span aria-hidden="true">·</span>
      <span>{{ scopeLabel(task.scope) }}</span>
    </div>
    <div class="task-card-footer">
      <span class="muted">{{ task.readinessLabel }}</span>
      <RouterLink class="text-link" :to="`/tasks/${task.id}`" :aria-label="`Открыть задачу: ${task.title}`">Подробнее <span aria-hidden="true">↗</span></RouterLink>
    </div>
  </article>
</template>