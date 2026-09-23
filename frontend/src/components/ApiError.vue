<script setup>
import { computed } from 'vue'
import { contentFields } from '../lib/fields'

const props = defineProps({
  error: { type: [Object, String], default: null },
  retry: { type: Boolean, default: false },
})
defineEmits(['retry'])

const labels = {
  ...Object.fromEntries(contentFields.map((field) => [field.key, field.label])),
  title: 'Название', organization: 'Организация', region: 'Регион', category: 'Тема',
  scope: 'Охват', confirmedFields: 'Подтверждение полей', confirmed: 'Подтверждение карточки',
  description: 'Описание проблемы', idea: 'Идея решения', plan: 'План работы',
  timeline: 'Сроки', prototypeLink: 'Ссылка на прототип', q: 'Поиск', sort: 'Сортировка',
}
const message = computed(() => typeof props.error === 'string'
  ? props.error
  : props.error?.message || 'Не удалось загрузить данные. Попробуйте ещё раз.')
const fieldErrors = computed(() => {
  const errors = props.error?.errors || props.error?.response?.data?.errors || {}
  return Object.entries(errors).flatMap(([key, messages]) => {
    const field = key.replace(/^fields\./, '').split('.')[0]
    return (Array.isArray(messages) ? messages : [messages]).map((text) => ({
      label: labels[field] || field,
      text,
    }))
  })
})
</script>

<template>
  <div v-if="error" class="api-error" role="alert">
    <div class="api-error__body">
      <strong>{{ message }}</strong>
      <ul v-if="fieldErrors.length">
        <li v-for="(item, index) in fieldErrors" :key="index">
          <span>{{ item.label }}:</span> {{ item.text }}
        </li>
      </ul>
    </div>
    <button v-if="retry" class="button button-secondary" type="button" @click="$emit('retry')">
      Повторить
    </button>
  </div>
</template>

<style scoped>
.api-error { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 1.15rem; border: 1px solid #f0c3c0; border-radius: 14px; background: #fff4f2; color: #8e302b; }
.api-error__body { min-width: 0; overflow-wrap: anywhere; }
.api-error strong { font-size: .9rem; font-weight: 600; }
.api-error ul { padding-left: 1.15rem; margin: .55rem 0 0; font-size: .85rem; line-height: 1.6; }
.api-error li span { font-weight: 600; }
.api-error button { flex-shrink: 0; }
@media (max-width: 560px) { .api-error { flex-direction: column; } }
</style>
