<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  title?: string
  value: string | number
  subtitle?: string
  color?: 'purple' | 'blue' | 'green' | 'yellow' | 'red' | 'cyan'
}>()

const colorMap: Record<string, { bg: string; text: string }> = {
  purple: { bg: 'rgba(139,92,246,.15)', text: '#a78bfa' },
  blue:   { bg: 'rgba(59,130,246,.15)',  text: '#60a5fa' },
  green:  { bg: 'rgba(16,185,129,.15)',  text: '#34d399' },
  yellow: { bg: 'rgba(245,158,11,.15)',  text: '#fbbf24' },
  red:    { bg: 'rgba(239,68,68,.15)',   text: '#f87171' },
  cyan:   { bg: 'rgba(6,182,212,.15)',   text: '#22d3ee' },
}

const colorBg   = computed(() => colorMap[props.color ?? 'purple']?.bg)
const colorText = computed(() => colorMap[props.color ?? 'purple']?.text)
</script>

<template>
  <div class="rounded-xl p-4 transition-all duration-200 hover:-translate-y-0.5 cursor-default"
    style="background-color:#1a1a26; border:1px solid #2a2a3a;">
    <div class="flex items-start justify-between gap-3">
      <div class="flex-1 min-w-0">
        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color:#94a3b8;">
          {{ title }}
        </p>
        <p class="text-2xl font-bold text-white truncate">{{ value }}</p>
        <p v-if="subtitle" class="text-xs mt-1" style="color:#94a3b8;">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.icon" class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
        :style="`background: ${colorBg}; color: ${colorText};`">
        <slot name="icon" />
      </div>
    </div>
    <div class="mt-3 h-0.5 rounded-full"
      :style="`background: linear-gradient(90deg, ${colorText}, transparent);`" />
  </div>
</template>

