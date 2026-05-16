<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

interface Employee {
  id: number
  name: string
  phone: string | null
  address: string | null
  status: 'active' | 'inactive'
  deleted_at: string | null
}

const props = defineProps<{ employees: Employee[] }>()

const showModal = ref(false)
const editing = ref<Employee | null>(null)

const form = useForm({
  name: '',
  phone: '',
  address: '',
  status: 'active' as 'active' | 'inactive',
})

function openCreate() {
  editing.value = null
  form.reset()
  form.status = 'active'
  showModal.value = true
}

function openEdit(e: Employee) {
  editing.value = e
  form.name = e.name
  form.phone = e.phone ?? ''
  form.address = e.address ?? ''
  form.status = e.status
  showModal.value = true
}

function submit() {
  if (editing.value) {
    form.put(route('employees.update', editing.value.id), {
      onSuccess: () => { showModal.value = false; form.reset() }
    })
  } else {
    form.post(route('employees.store'), {
      onSuccess: () => { showModal.value = false; form.reset() }
    })
  }
}

function destroy(e: Employee) {
  if (confirm(`Hapus karyawan "${e.name}"?`)) {
    useForm({}).delete(route('employees.destroy', e.id))
  }
}

const active = computed(() => props.employees.filter(e => !e.deleted_at && e.status === 'active'))
const inactive = computed(() => props.employees.filter(e => !e.deleted_at && e.status === 'inactive'))
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Karyawan</h1></template>
    <template #header-actions>
      <button @click="openCreate"
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">
        <PlusIcon class="w-4 h-4" /> Tambah
      </button>
    </template>

    <!-- Stats row -->
    <div class="flex gap-3 mb-5">
      <div class="px-4 py-2 rounded-lg text-sm" style="background:rgba(16,185,129,.1); color:#34d399; border:1px solid rgba(16,185,129,.2);">
        {{ active.length }} Aktif
      </div>
      <div class="px-4 py-2 rounded-lg text-sm" style="background:rgba(239,68,68,.1); color:#f87171; border:1px solid rgba(239,68,68,.2);">
        {{ inactive.length }} Tidak Aktif
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl overflow-hidden" style="background-color:#1a1a26; border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead>
          <tr style="border-bottom:1px solid #2a2a3a;">
            <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color:#94a3b8;">#</th>
            <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color:#94a3b8;">Nama</th>
            <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color:#94a3b8;">Telepon</th>
            <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color:#94a3b8;">Status</th>
            <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color:#94a3b8;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(emp, i) in employees.filter(e => !e.deleted_at)" :key="emp.id"
            class="border-b transition-colors hover:bg-white/[0.02]" style="border-color:#2a2a3a;">
            <td class="px-4 py-3" style="color:#94a3b8;">{{ i + 1 }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                  style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
                  {{ emp.name[0].toUpperCase() }}
                </div>
                <div>
                  <p class="font-medium text-white">{{ emp.name }}</p>
                  <p class="text-xs" style="color:#94a3b8;">{{ emp.address ?? '-' }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3" style="color:#e2e8f0;">{{ emp.phone ?? '-' }}</td>
            <td class="px-4 py-3">
              <span class="px-2.5 py-1 rounded-full text-xs font-medium"
                :style="emp.status === 'active'
                  ? 'background:rgba(16,185,129,.15); color:#34d399;'
                  : 'background:rgba(239,68,68,.15); color:#f87171;'">
                {{ emp.status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <button @click="openEdit(emp)"
                  class="p-1.5 rounded-lg transition-colors hover:bg-white/10"
                  style="color:#94a3b8;">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="destroy(emp)"
                  class="p-1.5 rounded-lg transition-colors hover:bg-red-500/10"
                  style="color:#94a3b8;">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <GamingModal :show="showModal" :title="editing ? 'Edit Karyawan' : 'Tambah Karyawan'" @close="showModal = false">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama *</label>
          <input v-model="form.name" type="text" required
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none transition-colors"
            style="background:#12121a; border:1px solid #2a2a3a;"
            :class="{ 'border-red-500': form.errors.name }"
            placeholder="Nama karyawan" />
          <p v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Telepon</label>
          <input v-model="form.phone" type="text"
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="08xxxxxxxxxx" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Alamat</label>
          <textarea v-model="form.address" rows="2"
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none resize-none"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="Alamat lengkap" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Status</label>
          <select v-model="form.status"
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option value="active">Aktif</option>
            <option value="inactive">Tidak Aktif</option>
          </select>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showModal = false"
            class="flex-1 py-2.5 rounded-xl text-sm"
            style="background:#2a2a3a; color:#e2e8f0;">Batal</button>
          <button type="submit" :disabled="form.processing"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium transition-opacity disabled:opacity-60"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;">
            {{ editing ? 'Simpan' : 'Tambah' }}
          </button>
        </div>
      </form>
    </GamingModal>
  </AppLayout>
</template>
