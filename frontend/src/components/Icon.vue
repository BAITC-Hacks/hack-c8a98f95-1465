<script setup>
defineProps({ name: { type: String, default: 'file-text' }, size: { type: [Number, String], default: 20 } })
const paths = {
  home: ['m3 10 9-7 9 7', 'M5 9v12h5v-7h4v7h5V9'],
  grid: ['M3 3h7v7H3z', 'M14 3h7v7h-7z', 'M3 14h7v7H3z', 'M14 14h7v7h-7z'],
  layers: ['m12 3 10 5-10 5L2 8z', 'm2 12 10 5 10-5', 'm2 16 10 5 10-5'],
  briefcase: ['M8 6V4h8v2', 'M3 7h18v14H3z', 'M3 11a19 19 0 0 0 18 0', 'M10 12v3h4v-3'],
  users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M17 4a4 4 0 0 1 0 8', 'M22 21v-2a4 4 0 0 0-3-3.87'],
  plus: ['M12 5v14', 'M5 12h14'],
  'arrow-right': ['M4 12h16', 'm14 6 6 6-6 6'],
  'arrow-up-right': ['M7 17 17 7', 'M7 7h10v10'],
  'chevron-right': ['m9 5 7 7-7 7'],
  search: ['M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15', 'm16 16 5 5'],
  filter: ['M4 6h16', 'M7 12h10', 'M10 18h4'],
  'file-text': ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6', 'M8 13h8', 'M8 17h5'],
  sparkles: ['m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z', 'M20 2v4', 'M18 4h4'],
  check: ['m5 12 4 4L19 6'],
  'check-circle': ['M22 11.1V12a10 10 0 1 1-5.9-9.1', 'm22 4-10 10-3-3'],
  'shield-check': ['M12 2 3 6v6c0 5 9 10 9 10s9-5 9-10V6z', 'm8 12 3 3 5-6'],
  'map-pin': ['M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z', 'M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0'],
  'book-open': ['M12 5c-3-2-6-2-10-1v16c4-1 7-1 10 1 3-2 6-2 10-1V4c-4-1-7-1-10 1Z', 'M12 5v16'],
  chart: ['M3 3v18h18', 'M8 16v-5', 'M13 16V7', 'M18 16v-9'],
  clock: ['M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0', 'M12 6v6l4 2'],
  send: ['m22 2-7 20-4-9-9-4Z', 'm22 2-11 11'],
  info: ['M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0', 'M12 11v6', 'M12 7h.01'],
  help: ['M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0', 'M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2-3 4', 'M12 17h.01'],
  menu: ['M3 6h18', 'M3 12h18', 'M3 18h18'],
  x: ['m6 6 12 12', 'M6 18 18 6'],
}
</script>
<template>
  <svg class="icon" :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path v-for="(d, i) in (paths[name] || paths['file-text'])" :key="i" :d="d" />
  </svg>
</template>
