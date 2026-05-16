<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import ActiveRoomMonitor from '@/Components/ActiveRoomMonitor.vue'

const props = defineProps<{
  activeRentals: any[]
}>()
</script>

<template>
  <AppLayout>
    <template #header-title>
      <div class="flex items-center gap-2">
        <span class="relative flex h-2.5 w-2.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
            style="background:#22c55e;"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5"
            style="background:#22c55e;"></span>
        </span>
        <h1 class="font-semibold text-white text-lg">Room Monitor</h1>
      </div>
    </template>

    <ActiveRoomMonitor :initial-rentals="activeRentals" />
  </AppLayout>
</template>
