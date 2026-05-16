<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

interface Package { id: number; name: string; duration_hours: number; price: number; description: string | null; is_active: boolean; deleted_at: string | null }

const props = defineProps<{ packages: Package[] }>()
const showModal = ref(false)
const editing = ref<Package | null>(null)
const form = useForm({ name: '', duration_hours: 3, price: 0, description: '', is_active: true })

function openCreate() { editing.value = null; form.reset(); form.is_active = true; showModal.value = true }
function openEdit(p: Package) {
  editing.value = p
  form.name = p.name; form.duration_hours = p.duration_hours; form.price = p.price
  form.description = p.description ?? ''; form.is_active = p.is_active
  showModal.value = true
}
function submit() {
  const opts = { onSuccess: () => { showModal.value = false; form.reset() } }
  editing.value ? form.put(route('packages.update', editing.value.id), opts) : form.post(route('packages.store'), opts)
}
function destroy(p: Package) {
  if (confirm(`Hapus paket "${p.name}"?`)) useForm({}).delete(route('packages.destroy', p.id))
}
function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Paket Rental</h1></template>
    <template #header-actions>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">
        <PlusIcon class="w-4 h-4" /> Tambah Paket
      </button>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="p in packages.filter(x => !x.deleted_at)" :key="p.id"
        class="rounded-xl p-5 transition-all hover:-translate-y-0.5"
        style="background-color:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-start justify-between mb-3">
          <div>
            <p class="font-bold text-white text-lg">{{ p.name }}</p>
            <p class="text-xs mt-0.5" style="color:#94a3b8;">{{ p.duration_hours }} Jam</p>
          </div>
          <div class="flex gap-2">
            <span class="px-2 py-0.5 rounded-full text-xs"
              :style="p.is_active ? 'background:rgba(16,185,129,.15); color:#34d399;' : 'background:rgba(239,68,68,.15); color:#f87171;'">
              {{ p.is_active ? 'Aktif' : 'Non-Aktif' }}
            </span>
          </div>
        </div>
        <p class="text-2xl font-bold mb-2" style="color:#a78bfa;">{{ formatCurrency(p.price) }}</p>
        <p v-if="p.description" class="text-xs mb-4" style="color:#94a3b8;">{{ p.description }}</p>
        <div class="flex gap-2">
          <button @click="openEdit(p)" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition-colors"
            style="background:rgba(139,92,246,.15); color:#a78bfa; border:1px solid rgba(139,92,246,.3);">
            <PencilIcon class="w-3.5 h-3.5" /> Edit
          </button>
          <button @click="destroy(p)" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition-colors"
            style="background:rgba(239,68,68,.15); color:#f87171; border:1px solid rgba(239,68,68,.3);">
            <TrashIcon class="w-3.5 h-3.5" /> Hapus
          </button>
        </div>
      </div>
    </div>

    <GamingModal :show="showModal" :title="editing ? 'Edit Paket' : 'Tambah Paket'" @close="showModal = false">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama Paket *</label>
          <input v-model="form.name" required class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Durasi (Jam)</label>
            <input v-model.number="form.duration_hours" type="number" min="1" required
              class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Harga (Rp)</label>
            <input v-model.number="form.price" type="number" min="0" required
              class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none resize-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div class="flex items-center gap-3">
          <input v-model="form.is_active" type="checkbox" id="pkg_active" class="w-4 h-4 accent-purple-500" />
          <label for="pkg_active" class="text-sm text-white">Aktif</label>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showModal = false" class="flex-1 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">Batal</button>
          <button type="submit" :disabled="form.processing" class="flex-1 py-2.5 rounded-xl text-sm font-medium disabled:opacity-60"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">{{ editing ? 'Simpan' : 'Tambah' }}</button>
        </div>
      </form>
    </GamingModal>
  </AppLayout>
</template>
