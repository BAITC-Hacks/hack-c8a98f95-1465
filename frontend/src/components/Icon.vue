<script setup>
import { computed } from 'vue'
import {
  House, LayoutGrid, Layers, Briefcase, Users, Plus, ArrowRight, ArrowUpRight,
  ChevronRight, Search, ListFilter, FileText, Sparkles, Check, CircleCheck,
  ShieldCheck, MapPin, BookOpen, ChartNoAxesColumn, Clock, Send, Info,
  CircleHelp, Menu, X, RefreshCw,
} from '@lucide/vue'

const props = defineProps({ name: { type: String, default: 'file-text' }, size: { type: [Number, String], default: 20 } })
const icons = {
  home: House, grid: LayoutGrid, layers: Layers, briefcase: Briefcase, users: Users,
  plus: Plus, 'arrow-right': ArrowRight, 'arrow-up-right': ArrowUpRight,
  'chevron-right': ChevronRight, search: Search, filter: ListFilter, 'file-text': FileText,
  sparkles: Sparkles, check: Check, 'check-circle': CircleCheck, 'shield-check': ShieldCheck,
  'map-pin': MapPin, 'book-open': BookOpen, chart: ChartNoAxesColumn, clock: Clock,
  send: Send, info: Info, help: CircleHelp, menu: Menu, x: X, 'refresh-cw': RefreshCw,
}
const icon = computed(() => icons[props.name] || FileText)
</script>

<template>
  <component :is="icon" class="icon" :size="size" :stroke-width="1.65" aria-hidden="true" focusable="false" />
</template>
