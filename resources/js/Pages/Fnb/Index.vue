<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

interface FnbItem { id: number; name: string; category: string; price: number; deleted_at: string | null }
interface FnbAddon { id: number; name: string; price: number; deleted_at: string | null }

const props = defineProps<{ items: FnbItem[]; addons: FnbAddon[] }>()

const activeTab = ref<'items' | 'addons'>('items')
const showModal = ref(false)
const modalType = ref<'item' | 'addon'>('item')
const editingItem = ref<FnbItem | null>(null)
const editingAddon = ref<FnbAddon | null>(null)

const itemForm = useForm({ name: '', category: 'food', price: 0 })
const addonForm = useForm({ name: '', price: 0 })

const categoryColors: Record<string, string> = {
  food:  'rgba(245,158,11,.15);color:#fbbf24',
  drink: 'rgba(6,182,212,.15);color:#22d3ee',
  snack: 'rgba(139,92,246,.15);color:#a78bfa',
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}

function openCreateItem() {
  editingItem.value = null; itemForm.reset()
  itemForm.category = 'food'
  modalType.value = 'item'; showModal.value = true
}
function openEditItem(item: FnbItem) {
  editingItem.value = item
  itemForm.name = item.name; itemForm.category = item.category
  itemForm.name = item.name; itemForm.category = item.category; itemForm.price = item.price
  modalType.value = 'item'; showModal.value = true
}
function submitItem() {
  const opts = { onSuccess: () => { showModal.value = false; itemForm.reset() } }
  editingItem.value ? itemForm.put(route('fnb.items.update', editingItem.value.id), opts) : itemForm.post(route('fnb.items.store'), opts)
}
function destroyItem(item: FnbItem) {
  if (confirm(`Hapus item "${item.name}"?`)) useForm({}).delete(route('fnb.items.destroy', item.id))
}

function openCreateAddon() {
  editingAddon.value = null; addonForm.reset()
  modalType.value = 'addon'; showModal.value = true
}
function openEditAddon(a: FnbAddon) {
  editingAddon.value = a
  addonForm.name = a.name; addonForm.price = a.price
  modalType.value = 'addon'; showModal.value = true
}
function submitAddon() {
  const opts = { onSuccess: () => { showModal.value = false; addonForm.reset() } }
  editingAddon.value ? addonForm.put(route('fnb.addons.update', editingAddon.value.id), opts) : addonForm.post(route('fnb.addons.store'), opts)
}
function destroyAddon(a: FnbAddon) {
  if (confirm(`Hapus add-on "${a.name}"?`)) useForm({}).delete(route('fnb.addons.destroy', a.id))
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">FNB Management</h1></template>
    <template #header-actions>
      <button v-if="activeTab === 'items'" @click="openCreateItem"
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">
        <PlusIcon class="w-4 h-4" /> Tambah Item
      </button>
      <button v-else @click="openCreateAddon"
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium"
        style="background:linear-gradient(135deg,#06b6d4,#3b82f6); color:white;">
        <PlusIcon class="w-4 h-4" /> Tambah Add-on
      </button>
    </template>

    <!-- Tabs -->
    <div class="flex gap-1 mb-5 p-1 rounded-xl w-fit" style="background:#1a1a26; border:1px solid #2a2a3a;">
      <button @click="activeTab = 'items'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
        :style="activeTab === 'items' ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;' : 'color:#94a3b8;'">
        Menu FNB ({{ items.filter(x => !x.deleted_at).length }})
      </button>
      <button @click="activeTab = 'addons'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
        :style="activeTab === 'addons' ? 'background:linear-gradient(135deg,#06b6d4,#3b82f6); color:white;' : 'color:#94a3b8;'">
        Add-ons ({{ addons.filter(x => !x.deleted_at).length }})
      </button>
    </div>

    <!-- Items Table -->
    <div v-if="activeTab === 'items'" class="rounded-xl overflow-hidden" style="background-color:#1a1a26; border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead>
          <tr style="border-bottom:1px solid #2a2a3a;">
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Nama</th>
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Kategori</th>
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Harga</th>
            <th class="text-right px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items.filter(x => !x.deleted_at)" :key="item.id"
            class="border-b hover:bg-white/[.02] transition-colors" style="border-color:#2a2a3a;">
            <td class="px-4 py-3 font-medium text-white">{{ item.name }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded text-xs"
                :style="`background:${categoryColors[item.category]?.split(';')[0]}; ${categoryColors[item.category]?.split(';')[1]}`">
                {{ item.category }}
              </span>
            </td>
            <td class="px-4 py-3" style="color:#a78bfa;">{{ formatCurrency(item.price) }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <button @click="openEditItem(item)" class="p-1.5 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><PencilIcon class="w-4 h-4" /></button>
                <button @click="destroyItem(item)" class="p-1.5 rounded-lg hover:bg-red-500/10" style="color:#94a3b8;"><TrashIcon class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Addons Table -->
    <div v-if="activeTab === 'addons'" class="rounded-xl overflow-hidden" style="background-color:#1a1a26; border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead>
          <tr style="border-bottom:1px solid #2a2a3a;">
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Nama Add-on</th>
            <th class="text-left px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Harga</th>
            <th class="text-right px-4 py-3 text-xs uppercase tracking-wider" style="color:#94a3b8;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in addons.filter(x => !x.deleted_at)" :key="a.id"
            class="border-b hover:bg-white/[.02]" style="border-color:#2a2a3a;">
            <td class="px-4 py-3 font-medium text-white">{{ a.name }}</td>
            <td class="px-4 py-3" style="color:#22d3ee;">{{ a.price > 0 ? formatCurrency(a.price) : 'Gratis' }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <button @click="openEditAddon(a)" class="p-1.5 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><PencilIcon class="w-4 h-4" /></button>
                <button @click="destroyAddon(a)" class="p-1.5 rounded-lg hover:bg-red-500/10" style="color:#94a3b8;"><TrashIcon class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Item Modal -->
    <GamingModal :show="showModal && modalType === 'item'" :title="editingItem ? 'Edit Item FNB' : 'Tambah Item FNB'" @close="showModal = false">
      <form @submit.prevent="submitItem" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama *</label>
          <input v-model="itemForm.name" required class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Kategori</label>
            <select v-model="itemForm.category" class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option value="food">Food</option>
              <option value="drink">Drink</option>
              <option value="snack">Snack</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Harga (Rp)</label>
            <input v-model.number="itemForm.price" type="number" min="0" required
              class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showModal = false" class="flex-1 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">Batal</button>
          <button type="submit" :disabled="itemForm.processing" class="flex-1 py-2.5 rounded-xl text-sm font-medium disabled:opacity-60"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">{{ editingItem ? 'Simpan' : 'Tambah' }}</button>
        </div>
      </form>
    </GamingModal>

    <!-- Addon Modal -->
    <GamingModal :show="showModal && modalType === 'addon'" :title="editingAddon ? 'Edit Add-on' : 'Tambah Add-on'" @close="showModal = false">
      <form @submit.prevent="submitAddon" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama *</label>
          <input v-model="addonForm.name" required class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Harga (Rp, 0 = Gratis)</label>
          <input v-model.number="addonForm.price" type="number" min="0"
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showModal = false" class="flex-1 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">Batal</button>
          <button type="submit" :disabled="addonForm.processing" class="flex-1 py-2.5 rounded-xl text-sm font-medium disabled:opacity-60"
            style="background:linear-gradient(135deg,#06b6d4,#3b82f6); color:white;">{{ editingAddon ? 'Simpan' : 'Tambah' }}</button>
        </div>
      </form>
    </GamingModal>
  </AppLayout>
</template>
