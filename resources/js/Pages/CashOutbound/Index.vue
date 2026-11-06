<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { Plus, Pencil, Trash2, Download } from 'lucide-vue-next'

interface Outbound {
  id: number; nominal: number; notes: string; date: string;
  employee: { name: string } | null
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

const showModal = ref(false)
const editId = ref<number | null>(null)
const form = useForm({ nominal: 0, notes: '', date: new Date().toISOString().slice(0, 10), employee_id: null as number | null })

const props = defineProps<{
  outbounds: { data: Outbound[]; links: any[]; total: number }
  employees: { id: number; name: string }[]
  total_this_month: number
  filters?: { date?: string }
}>()

const filterDate = ref(props.filters?.date || '')
watch(filterDate, (value) => {
  router.get(route('cash-outbounds.index'), { date: value }, { preserveState: true, replace: true })
})

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

// Export Excel
const showExportModal = ref(false)
const exportType = ref<'all' | 'year' | 'month' | 'day'>('month')
const exportYear = ref(new Date().getFullYear())
const exportMonth = ref(new Date().toISOString().slice(0, 7))
const exportDate = ref(props.filters?.date || new Date().toISOString().slice(0, 10))

function openExportModal() {
  if (filterDate.value) {
    exportType.value = 'day'
    exportDate.value = filterDate.value
  } else {
    exportType.value = 'month'
    exportMonth.value = new Date().toISOString().slice(0, 7)
  }
  showExportModal.value = true
}

function doExport() {
  const q = new URLSearchParams()
  if (exportType.value === 'day' && exportDate.value) {
    q.append('date', exportDate.value)
  } else if (exportType.value === 'month' && exportMonth.value) {
    q.append('month', exportMonth.value)
  } else if (exportType.value === 'year') {
    q.append('year', String(exportYear.value))
  }
  window.location.href = route('cash-outbounds.export') + (q.toString() ? '?' + q.toString() : '')
  showExportModal.value = false
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Pengeluaran Kas</h1></template>
    <template #header-actions>
      <div class="flex items-center gap-2">
        <button @click="openExportModal"
          class="px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-1.5 transition-all hover:bg-sky-500/10"
          style="background:#1a1a26; border:1px solid rgba(56,189,248,.4); color:#38bdf8;">
          <Download :size="14" /> Export Excel
        </button>
        <button @click="openCreate"
          class="px-4 py-2 rounded-xl text-sm font-medium text-white flex items-center gap-1.5"
          style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
          <Plus :size="14" /> Tambah
        </button>
      </div>
    </template>

    <!-- Summary and Filter -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
      <div class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <p class="text-xs" style="color:#94a3b8;">Total Pengeluaran Bulan Ini</p>
        <p class="text-2xl font-bold mt-1" style="color:#ef4444;">{{ formatCurrency(total_this_month) }}</p>
      </div>
      <div class="rounded-xl p-5 flex flex-col justify-center" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Filter Tanggal</label>
        <input v-model="filterDate" type="date"
          class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
          style="background:#12121a; border:1px solid #2a2a3a; color-scheme: dark;" />
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[650px]">
          <thead style="background:#12121a;">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium whitespace-nowrap" style="color:#64748b;">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-medium whitespace-nowrap" style="color:#64748b;">Keterangan</th>
              <th class="px-4 py-3 text-left text-xs font-medium whitespace-nowrap" style="color:#64748b;">Operator</th>
              <th class="px-4 py-3 text-right text-xs font-medium whitespace-nowrap" style="color:#64748b;">Nominal</th>
              <th class="px-4 py-3 whitespace-nowrap"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="outbounds.data.length === 0">
              <td colspan="5" class="px-4 py-10 text-center text-xs" style="color:#64748b;">Belum ada pengeluaran</td>
            </tr>
            <tr v-for="o in outbounds.data" :key="o.id" style="border-top:1px solid #1a1a26;">
              <td class="px-4 py-3 text-xs whitespace-nowrap" style="color:#94a3b8;">{{ formatDate(o.date) }}</td>
              <td class="px-4 py-3 text-white">{{ o.notes }}</td>
              <td class="px-4 py-3 text-xs whitespace-nowrap" style="color:#94a3b8;">{{ o.employee?.name ?? '-' }}</td>
              <td class="px-4 py-3 text-right font-bold whitespace-nowrap" style="color:#ef4444;">{{ formatCurrency(o.nominal) }}</td>
              <td class="px-4 py-3 whitespace-nowrap">
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

    <!-- Modal Form (Tambah/Edit) -->
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

    <!-- Modal Export Excel -->
    <GamingModal :show="showExportModal" title="Export Laporan Pengeluaran" @close="showExportModal = false">
      <div class="space-y-4">
        <p class="text-xs" style="color:#94a3b8;">Pilih cakupan periode data pengeluaran yang ingin diekspor ke Excel (.xlsx):</p>
        
        <!-- Filter type selection -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <button v-for="t in [
            { key: 'all', label: 'Semua' },
            { key: 'year', label: 'Per Tahun' },
            { key: 'month', label: 'Per Bulan' },
            { key: 'day', label: 'Per Hari' },
          ]" :key="t.key"
            type="button"
            @click="exportType = t.key as any"
            class="py-2.5 px-3 rounded-xl text-xs font-semibold transition-all text-center"
            :style="exportType === t.key
              ? 'background:rgba(56,189,248,0.2); border:1px solid rgba(56,189,248,0.5); color:#38bdf8;'
              : 'background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;'">
            {{ t.label }}
          </button>
        </div>

        <!-- Year picker -->
        <div v-if="exportType === 'year'" class="space-y-1">
          <label class="block text-xs font-medium" style="color:#94a3b8;">Tahun</label>
          <select v-model="exportYear"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option v-for="y in [2024, 2025, 2026, 2027, 2028]" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <!-- Month picker -->
        <div v-if="exportType === 'month'" class="space-y-1">
          <label class="block text-xs font-medium" style="color:#94a3b8;">Bulan</label>
          <input v-model="exportMonth" type="month"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a; color-scheme: dark;" />
        </div>

        <!-- Day picker -->
        <div v-if="exportType === 'day'" class="space-y-1">
          <label class="block text-xs font-medium" style="color:#94a3b8;">Tanggal</label>
          <input v-model="exportDate" type="date"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a; color-scheme: dark;" />
        </div>

        <div class="flex gap-2 justify-end pt-2">
          <button type="button" @click="showExportModal = false"
            class="px-4 py-2 rounded-xl text-xs font-medium"
            style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
            Batal
          </button>
          <button type="button" @click="doExport"
            class="px-5 py-2 rounded-xl text-xs font-semibold text-white flex items-center gap-1.5"
            style="background:linear-gradient(135deg,#0284c7,#0369a1);">
            <Download :size="13" /> Unduh File Excel
          </button>
        </div>
      </div>
    </GamingModal>
  </AppLayout>
</template>
