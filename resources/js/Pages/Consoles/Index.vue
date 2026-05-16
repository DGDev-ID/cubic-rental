<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

interface Game { id: number; name: string }
interface Console {
  id: number; name: string; type: string; price_per_hour: number
  description: string | null; status: string; deleted_at: string | null
  games: Game[]
}

const props = defineProps<{ consoles: Console[]; games: Game[] }>()
const showModal = ref(false)
const editing = ref<Console | null>(null)

const form = useForm({
  name: '', type: 'regular', price_per_hour: 0,
  description: '', status: 'available', game_ids: [] as number[]
})

const typeColors: Record<string, string> = {
  regular: 'rgba(59,130,246,.15);color:#60a5fa',
  vip:     'rgba(139,92,246,.15);color:#a78bfa',
  vvip:    'rgba(6,182,212,.15);color:#22d3ee',
  suite:   'rgba(245,158,11,.15);color:#fbbf24',
}
const statusColors: Record<string, string> = {
  available:   'rgba(16,185,129,.15);color:#34d399',
  occupied:    'rgba(239,68,68,.15);color:#f87171',
  maintenance: 'rgba(245,158,11,.15);color:#fbbf24',
  inactive:    'rgba(100,100,120,.15);color:#94a3b8',
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}

function openCreate() {
  editing.value = null; form.reset()
  form.type = 'regular'; form.status = 'available'; form.game_ids = []
  showModal.value = true
}
function openEdit(c: Console) {
  editing.value = c
  form.name = c.name; form.type = c.type; form.price_per_hour = c.price_per_hour
  form.description = c.description ?? ''; form.status = c.status
  form.game_ids = c.games.map(g => g.id)
  showModal.value = true
}
function submit() {
  const opts = { onSuccess: () => { showModal.value = false; form.reset() } }
  editing.value ? form.put(route('consoles.update', editing.value.id), opts) : form.post(route('consoles.store'), opts)
}
function destroy(c: Console) {
  if (confirm(`Hapus console "${c.name}"?`)) useForm({}).delete(route('consoles.destroy', c.id))
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Console / Room</h1></template>
    <template #header-actions>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">
        <PlusIcon class="w-4 h-4" /> Tambah Console
      </button>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="c in consoles.filter(x => !x.deleted_at)" :key="c.id"
        class="rounded-xl p-4 transition-all hover:-translate-y-0.5"
        style="background-color:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-start justify-between mb-3">
          <div>
            <p class="font-bold text-white">{{ c.name }}</p>
            <div class="flex gap-2 mt-1.5">
              <span class="px-2 py-0.5 rounded text-xs" :style="`background:${typeColors[c.type]?.split(';')[0]}; ${typeColors[c.type]?.split(';')[1]}`">
                {{ c.type.toUpperCase() }}
              </span>
              <span class="px-2 py-0.5 rounded text-xs" :style="`background:${statusColors[c.status]?.split(';')[0]}; ${statusColors[c.status]?.split(';')[1]}`">
                {{ c.status }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="openEdit(c)" class="p-1.5 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><PencilIcon class="w-4 h-4" /></button>
            <button @click="destroy(c)" class="p-1.5 rounded-lg hover:bg-red-500/10" style="color:#94a3b8;"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
        <p class="text-xl font-bold mb-2" style="color:#a78bfa;">{{ formatCurrency(c.price_per_hour) }}<span class="text-xs font-normal" style="color:#94a3b8;">/jam</span></p>
        <p v-if="c.description" class="text-xs mb-3" style="color:#94a3b8;">{{ c.description }}</p>
        <div v-if="c.games.length" class="flex flex-wrap gap-1">
          <span v-for="g in c.games.slice(0,4)" :key="g.id" class="text-xs px-1.5 py-0.5 rounded"
            style="background:#2a2a3a; color:#94a3b8;">{{ g.name }}</span>
          <span v-if="c.games.length > 4" class="text-xs px-1.5 py-0.5 rounded" style="background:#2a2a3a; color:#94a3b8;">+{{ c.games.length - 4 }}</span>
        </div>
      </div>
    </div>

    <GamingModal :show="showModal" :title="editing ? 'Edit Console' : 'Tambah Console'" max-width="36rem" @close="showModal = false">
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama Console *</label>
            <input v-model="form.name" required class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Tipe</label>
            <select v-model="form.type" class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option value="regular">Regular</option>
              <option value="vip">VIP</option>
              <option value="vvip">VVIP</option>
              <option value="suite">Suite</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Harga/Jam (Rp)</label>
            <input v-model.number="form.price_per_hour" type="number" min="0" required
              class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Status</label>
            <select v-model="form.status" class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option value="available">Available</option>
              <option value="occupied">Occupied</option>
              <option value="maintenance">Maintenance</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Deskripsi</label>
            <input v-model="form.description" class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-2" style="color:#94a3b8;">Game List</label>
          <div class="grid grid-cols-2 gap-1.5 max-h-32 overflow-y-auto">
            <label v-for="g in games" :key="g.id" class="flex items-center gap-2 text-sm text-white cursor-pointer">
              <input type="checkbox" :value="g.id" v-model="form.game_ids" class="accent-purple-500" />
              {{ g.name }}
            </label>
          </div>
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
