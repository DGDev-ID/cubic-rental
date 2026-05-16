<script setup lang="ts">
defineProps<{
  show?: boolean
  maxWidth?: string
  title?: string
}>()
const emit = defineEmits(['close'])
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/70" style="backdrop-filter:blur(4px);" @click="emit('close')" />
        <div class="relative w-full rounded-2xl shadow-2xl overflow-hidden"
          :style="`max-width: ${maxWidth ?? '32rem'}; background-color:#1a1a26; border:1px solid #2a2a3a;`">
          <div v-if="title" class="flex items-center justify-between px-6 py-4"
            style="border-bottom:1px solid #2a2a3a;">
            <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
            <button @click="emit('close')"
              class="text-slate-400 hover:text-white transition-colors w-8 h-8 flex items-center justify-center rounded-lg"
              style="background:rgba(255,255,255,0.05);">?</button>
          </div>
          <div class="p-6"><slot /></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,.modal-leave-active{transition:all .2s ease}
.modal-enter-from,.modal-leave-to{opacity:0;transform:scale(.95)}
</style>
