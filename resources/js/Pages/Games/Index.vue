<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

interface Game { id: number; name: string; genre: string | null; is_multiplayer: boolean; deleted_at: string | null }

const props = defineProps<{ games: Game[] }>()
const showModal = ref(false)
const editing = ref<Game | null>(null)
const form = useForm({ name: '', genre: '', is_multiplayer: false })

function openCreate() { editing.value = null; form.reset(); showModal.value = true }
function openEdit(g: Game) {
  editing.value = g
  form.name = g.name; form.genre = g.genre ?? ''; form.is_multiplayer = g.is_multiplayer
  showModal.value = true
}
function submit() {
  const opts = { onSuccess: () => { showModal.value = false; form.reset() } }
  editing.value ? form.put(route('games.update', editing.value.id), opts) : form.post(route('games.store'), opts)
}
function destroy(g: Game) {
  if (confirm(`Hapus game "${g.name}"?`)) useForm({}).delete(route('games.destroy', g.id))
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Game List</h1></template>
    <template #header-actions>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">
        <PlusIcon class="w-4 h-4" /> Tambah Game
      </button>
    </template>

    <div class="rounded-xl overflow-hidden" style="background-color:#1a1a26; border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead>
          <tr style="border-bottom:1px solid #2a2a3a;">
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">#</th>
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Nama Game</th>
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Genre</th>
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Multiplayer</th>
            <th class="text-right px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(g, i) in games.filter(x => !x.deleted_at)" :key="g.id"
            class="border-b transition-colors hover:bg-white/[.02]" style="border-color:#2a2a3a;">
            <td class="px-4 py-3" style="color:#94a3b8;">{{ i + 1 }}</td>
            <td class="px-4 py-3 font-medium text-white">{{ g.name }}</td>
            <td class="px-4 py-3" style="color:#e2e8f0;">{{ g.genre ?? '-' }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs"
                :style="g.is_multiplayer ? 'background:rgba(16,185,129,.15); color:#34d399;' : 'background:rgba(100,100,120,.15); color:#94a3b8;'">
                {{ g.is_multiplayer ? 'Ya' : 'Tidak' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <button @click="openEdit(g)" class="p-1.5 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><PencilIcon class="w-4 h-4" /></button>
                <button @click="destroy(g)" class="p-1.5 rounded-lg hover:bg-red-500/10" style="color:#94a3b8;"><TrashIcon class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <GamingModal :show="showModal" :title="editing ? 'Edit Game' : 'Tambah Game'" @close="showModal = false">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama Game *</label>
          <input v-model="form.name" required class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" placeholder="Nama game" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Genre</label>
          <input v-model="form.genre" class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" placeholder="Action, Sports, RPG..." />
        </div>
        <div class="flex items-center gap-3">
          <input v-model="form.is_multiplayer" type="checkbox" id="mp" class="w-4 h-4 rounded accent-purple-500" />
          <label for="mp" class="text-sm text-white">Multiplayer</label>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showModal = false" class="flex-1 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">Batal</button>
          <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 rounded-xl text-sm font-medium disabled:opacity-60"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">
            {{ editing ? 'Simpan' : 'Tambah' }}
          </button>
        </div>
      </form>
    </GamingModal>
  </AppLayout>
</template>
