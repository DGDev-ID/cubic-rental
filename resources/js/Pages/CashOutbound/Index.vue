<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'

interface Outbound {
  id: number; nominal: number; notes: string; date: string;
  employee: { name: string } | null
}

defineProps<{
  outbounds: { data: Outbound[]; links: any[]; total: number }
  employees: { id: number; name: string }[]
  total_this_month: number
}>()

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

const showModal = ref(false)
const editId = ref<number | null>(null)
const form = useForm({ nominal: 0, notes: '', date: new Date().toISOString().slice(0, 10), employee_id: null as number | null })

function openCreate() { editId.value = null; form.reset(); showModal.value = true }
function openEdit(o: Outbound) {
  editId.value = o.id
  form.nominal = o.nominal
  form.notes = o.notes
  form.date = o.date.slice(0, 10)
  form.employee_id = o.employee ? (o.employee as any).id : null
  showModal.value = true
}
function closeModal() { showModal.value = false; editId.value = null; form.reset() }

function submit() {
  if (editId.value) {
    form.put(route('cash-outbounds.update', editId.value), { onSuccess: closeModal })
  } else {
    form.post(route('cash-outbounds.store'), { onSuccess: closeModal })
  }
}

function destroy(id: number) {
  if (confirm('Hapus pengeluaran ini?')) {
    useForm({}).delete(route('cash-outbounds.destroy', id))
  }
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Pengeluaran Kas</h1></template>
    <template #header-actions>
      <button @click="openCreate"
        class="px-4 py-2 rounded-xl text-sm font-medium text-white flex items-center gap-1.5"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
        <Plus :size="14" /> Tambah
      </button>
    </template>

    <!-- Summary -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
      <div class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <p class="text-xs" style="color:#94a3b8;">Total Pengeluaran Bulan Ini</p>
        <p class="text-2xl font-bold mt-1" style="color:#ef4444;">{{ formatCurrency(total_this_month) }}</p>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead style="background:#12121a;">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Tanggal</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Keterangan</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Operator</th>
            <th class="px-4 py-3 text-right text-xs font-medium" style="color:#64748b;">Nominal</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="outbounds.data.length === 0">
            <td colspan="5" class="px-4 py-10 text-center text-xs" style="color:#64748b;">Belum ada pengeluaran</td>
          </tr>
          <tr v-for="o in outbounds.data" :key="o.id" style="border-top:1px solid #1a1a26;">
            <td class="px-4 py-3 text-xs" style="color:#94a3b8;">{{ formatDate(o.date) }}</td>
            <td class="px-4 py-3 text-white">{{ o.notes }}</td>
            <td class="px-4 py-3 text-xs" style="color:#94a3b8;">{{ o.employee?.name ?? '-' }}</td>
            <td class="px-4 py-3 text-right font-bold" style="color:#ef4444;">{{ formatCurrency(o.nominal) }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-2 justify-end">
                <button @click="openEdit(o)"
                  class="text-xs px-3 py-1.5 rounded-xl flex items-center gap-1"
                  style="background:rgba(59,130,246,.2); border:1px solid rgba(59,130,246,.3); color:#60a5fa;">
                  <Pencil :size="11" /> Edit
                </button>
                <button @click="destroy(o.id)"
                  class="text-xs px-3 py-1.5 rounded-xl flex items-center gap-1"
                  style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3); color:#ef4444;">
                  <Trash2 :size="11" /> Hapus
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex gap-1 mt-4 justify-center flex-wrap">
      <template v-for="link in outbounds.links" :key="link.label">
        <a v-if="link.url" :href="link.url"
          class="px-3 py-1.5 rounded-xl text-xs"
          :style="link.active
            ? 'background:rgba(139,92,246,.3); color:white; border:1px solid rgba(139,92,246,.5);'
            : 'background:#1a1a26; color:#94a3b8; border:1px solid #2a2a3a;'"
          v-html="link.label" />
        <span v-else class="px-3 py-1.5 rounded-xl text-xs" style="color:#3a3a4a;" v-html="link.label" />
      </template>
    </div>

    <!-- Modal -->
    <GamingModal :show="showModal" :title="editId ? 'Edit Pengeluaran' : 'Tambah Pengeluaran'" @close="closeModal">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Keterangan *</label>
          <input v-model="form.notes" required
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" placeholder="Deskripsi pengeluaran..." />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nominal *</label>
          <input v-model.number="form.nominal" type="number" min="0" required
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" placeholder="0" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Tanggal *</label>
          <input v-model="form.date" type="date" required
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Operator</label>
          <select v-model="form.employee_id"
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option :value="null">-- Opsional --</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="closeModal"
            class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
          <button type="submit" :disabled="form.processing"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white disabled:opacity-60"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
            {{ editId ? 'Simpan' : 'Tambah' }}
          </button>
        </div>
      </form>
    </GamingModal>
  </AppLayout>
</template>
