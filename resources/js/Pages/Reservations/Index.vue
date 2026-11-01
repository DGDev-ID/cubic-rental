<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { CalendarDays, Plus, Pencil, Trash2, ArrowRightCircle, Phone, Clock } from 'lucide-vue-next'

interface Console  { id: number; name: string; type: string; price_per_hour: number }
interface Employee { id: number; name: string }
interface Reservation {
  id: number
  console_id: number; employee_id: number
  customer_name: string; customer_phone: string | null
  reserved_at: string; duration_hours: number | null
  notes: string | null; status: 'pending' | 'confirmed' | 'cancelled' | 'converted'
  console: Console; employee: Employee
}

const props = defineProps<{
  reservations: Reservation[]
  consoles: Console[]
  employees: Employee[]
  filters: { status?: string; date?: string }
}>()

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}

function formatDateTime(d: string) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

function formatToLocalInput(d: string | null) {
  const dateObj = d ? new Date(d) : new Date()
  const y = dateObj.getFullYear()
  const m = String(dateObj.getMonth() + 1).padStart(2, '0')
  const day = String(dateObj.getDate()).padStart(2, '0')
  const h = d ? String(dateObj.getHours()).padStart(2, '0') : '10'
  const min = d ? String(dateObj.getMinutes()).padStart(2, '0') : '00'
  return {
    date: `${y}-${m}-${day}`,
    time: `${h}:${min}`,
    datetime: `${y}-${m}-${day}T${h}:${min}`
  }
}

// Status config
const statusConfig = {
  pending:   { label: 'Menunggu', bg: 'rgba(245,158,11,.15)', text: '#fbbf24', border: 'rgba(245,158,11,.4)' },
  confirmed: { label: 'Dikonfirmasi', bg: 'rgba(16,185,129,.15)', text: '#34d399', border: 'rgba(16,185,129,.4)' },
  cancelled: { label: 'Dibatalkan', bg: 'rgba(239,68,68,.15)', text: '#f87171', border: 'rgba(239,68,68,.4)' },
  converted: { label: 'Sudah Main', bg: 'rgba(139,92,246,.15)', text: '#a78bfa', border: 'rgba(139,92,246,.4)' },
}

// Filter
const filterDate = ref(props.filters.date ?? '')
const filterStatus = ref(props.filters.status ?? '')
function applyFilter() {
  router.get(route('reservations.index'), { date: filterDate.value, status: filterStatus.value }, { preserveState: true })
}

const timeError = ref('')

function validateOperatingHours(datetimeStr: string): boolean {
  if (!datetimeStr) return true
  const time = datetimeStr.split('T')[1] || ''
  if (!time) return true
  const [h, m] = time.split(':').map(Number)
  const totalMin = h * 60 + (m || 0)
  if (totalMin < 600 || totalMin > 1320) {
    timeError.value = 'Waktu reservasi harus antara jam 10:00 s/d 22:00 (Jam operasional).'
    return false
  }
  timeError.value = ''
  return true
}

// Add modal
const showAdd = ref(false)
const addForm = useForm({
  console_id: null as number | null,
  employee_id: null as number | null,
  customer_name: '',
  customer_phone: '',
  reserved_at: '',
  duration_hours: '' as string | number,
  notes: '',
})

function openAdd() {
  const init = formatToLocalInput(null)
  addForm.reset()
  addForm.reserved_at = init.datetime
  timeError.value = ''
  showAdd.value = true
}

function submitAdd() {
  if (!validateOperatingHours(addForm.reserved_at)) return
  addForm.post(route('reservations.store'), {
    onSuccess: () => { showAdd.value = false; addForm.reset() },
  })
}

// Edit modal
const showEdit = ref(false)
const editForm = useForm({
  id: 0,
  console_id: null as number | null,
  employee_id: null as number | null,
  customer_name: '',
  customer_phone: '',
  reserved_at: '',
  duration_hours: '' as string | number,
  notes: '',
  status: 'pending' as string,
})

function openEdit(r: Reservation) {
  editForm.id           = r.id
  editForm.console_id   = r.console_id
  editForm.employee_id  = r.employee_id
  editForm.customer_name = r.customer_name
  editForm.customer_phone = r.customer_phone ?? ''
  editForm.reserved_at  = formatToLocalInput(r.reserved_at).datetime
  editForm.duration_hours = r.duration_hours ?? ''
  editForm.notes        = r.notes ?? ''
  editForm.status       = r.status
  timeError.value = ''
  showEdit.value = true
}

function submitEdit() {
  if (!validateOperatingHours(editForm.reserved_at)) return
  editForm.put(route('reservations.update', editForm.id), {
    onSuccess: () => { showEdit.value = false },
  })
}

// Delete
const confirmDelete = ref<number | null>(null)
function doDelete() {
  if (!confirmDelete.value) return
  router.delete(route('reservations.destroy', confirmDelete.value), {
    onSuccess: () => { confirmDelete.value = null },
  })
}

// Convert to rental
const confirmConvert = ref<Reservation | null>(null)
function doConvert() {
  if (!confirmConvert.value) return
  router.post(route('reservations.convert', confirmConvert.value.id), {}, {
    onSuccess: () => { confirmConvert.value = null },
  })
}

// Duration quick chips
const durationChips = [0.5, 1, 1.5, 2, 3, 4]

function updateAddDate(e: Event) {
  const date = (e.target as HTMLInputElement).value
  const time = addForm.reserved_at?.split('T')[1] || '10:00'
  addForm.reserved_at = date + 'T' + time
  validateOperatingHours(addForm.reserved_at)
}

function updateAddTime(e: Event) {
  const time = (e.target as HTMLInputElement).value
  const date = addForm.reserved_at?.split('T')[0] || formatToLocalInput(null).date
  addForm.reserved_at = date + 'T' + time
  validateOperatingHours(addForm.reserved_at)
}

function updateEditDate(e: Event) {
  const date = (e.target as HTMLInputElement).value
  const time = editForm.reserved_at?.split('T')[1] || '10:00'
  editForm.reserved_at = date + 'T' + time
  validateOperatingHours(editForm.reserved_at)
}

function updateEditTime(e: Event) {
  const time = (e.target as HTMLInputElement).value
  const date = editForm.reserved_at?.split('T')[0] || formatToLocalInput(null).date
  editForm.reserved_at = date + 'T' + time
  validateOperatingHours(editForm.reserved_at)
}

// Price helpers
const addSelectedConsole = computed(() => props.consoles.find(c => c.id === addForm.console_id) ?? null)
const editSelectedConsole = computed(() => props.consoles.find(c => c.id === editForm.console_id) ?? null)

function estimatedTotal(pricePerHour: number, duration: string | number) {
  const h = Number(duration)
  return h > 0 ? pricePerHour * h : null
}

function addEstimated() {
  if (!addSelectedConsole.value) return null
  return estimatedTotal(addSelectedConsole.value.price_per_hour, addForm.duration_hours)
}
function editEstimated() {
  if (!editSelectedConsole.value) return null
  return estimatedTotal(editSelectedConsole.value.price_per_hour, editForm.duration_hours)
}
</script>

<template>
  <AppLayout>
    <template #header-title>
      <div class="flex items-center gap-2">
        <CalendarDays :size="20" style="color:#a78bfa;" />
        <h1 class="font-semibold text-white text-lg">Reservasi</h1>
      </div>
    </template>
    <template #header-actions>
      <button @click="openAdd"
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-all hover:opacity-90"
        style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">
        <Plus :size="16" /> Tambah Reservasi
      </button>
    </template>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-5">
      <input type="date" v-model="filterDate" @change="applyFilter"
        class="px-3 py-2 rounded-xl text-sm text-white outline-none"
        style="background:#1a1a26; border:1px solid #2a2a3a;" />
      <select v-model="filterStatus" @change="applyFilter"
        class="px-3 py-2 rounded-xl text-sm text-white outline-none"
        style="background:#1a1a26; border:1px solid #2a2a3a;">
        <option value="">Aktif (Pending + Konfirmasi)</option>
        <option value="pending">Menunggu</option>
        <option value="confirmed">Dikonfirmasi</option>
        <option value="cancelled">Dibatalkan</option>
        <option value="converted">Sudah Main</option>
      </select>
    </div>

    <!-- Empty -->
    <div v-if="reservations.length === 0"
      class="rounded-xl p-14 text-center"
      style="background:#1a1a26; border:1px dashed #2a2a3a;">
      <p class="text-4xl mb-3">📅</p>
      <p class="text-white font-medium">Belum ada reservasi</p>
      <p class="text-sm mt-1" style="color:#94a3b8;">Klik "Tambah Reservasi" untuk membuat reservasi baru</p>
    </div>

    <!-- Table -->
    <div v-else class="rounded-xl overflow-hidden" style="background:#1a1a26; border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead>
          <tr style="background:#12121a; border-bottom:1px solid #2a2a3a;">
            <th class="text-left px-4 py-3 text-xs font-semibold" style="color:#94a3b8;">Waktu Reservasi</th>
            <th class="text-left px-4 py-3 text-xs font-semibold" style="color:#94a3b8;">Customer</th>
            <th class="text-left px-4 py-3 text-xs font-semibold" style="color:#94a3b8;">Room / Console</th>
            <th class="text-left px-4 py-3 text-xs font-semibold" style="color:#94a3b8;">Durasi</th>
            <th class="text-left px-4 py-3 text-xs font-semibold" style="color:#94a3b8;">Harga</th>
            <th class="text-left px-4 py-3 text-xs font-semibold" style="color:#94a3b8;">Status</th>
            <th class="text-right px-4 py-3 text-xs font-semibold" style="color:#94a3b8;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in reservations" :key="r.id"
            class="border-b transition-colors hover:bg-white/5"
            style="border-color:#2a2a3a;">
            <td class="px-4 py-3 text-white font-medium">{{ formatDateTime(r.reserved_at) }}</td>
            <td class="px-4 py-3">
              <p class="text-white font-medium">{{ r.customer_name }}</p>
              <p v-if="r.customer_phone" class="text-xs flex items-center gap-1 mt-0.5" style="color:#94a3b8;">
                <Phone :size="11" /> {{ r.customer_phone }}
              </p>
            </td>
            <td class="px-4 py-3">
              <p class="text-white">{{ r.console?.name ?? '(dihapus)' }}</p>
              <p class="text-xs" style="color:#94a3b8;">{{ r.employee?.name ?? '(dihapus)' }}</p>
            </td>
            <td class="px-4 py-3">
              <span v-if="r.duration_hours" class="text-white">{{ r.duration_hours }}j</span>
              <span v-else style="color:#64748b;">Open</span>
            </td>
            <td class="px-4 py-3">
              <p class="text-white text-xs">{{ formatCurrency(r.console?.price_per_hour ?? 0) }}<span style="color:#64748b;">/j</span></p>
              <p v-if="r.duration_hours && r.console" class="text-xs font-semibold" style="color:#a78bfa;">
                ≈ {{ formatCurrency((r.console?.price_per_hour ?? 0) * r.duration_hours) }}
              </p>
              <p v-else class="text-xs" style="color:#64748b;">Open end</p>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                :style="`background:${statusConfig[r.status].bg}; color:${statusConfig[r.status].text}; border:1px solid ${statusConfig[r.status].border};`">
                {{ statusConfig[r.status].label }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1.5">
                <!-- Convert to rental -->
                <button v-if="r.status !== 'converted' && r.status !== 'cancelled'"
                  @click="confirmConvert = r"
                  title="Mulai Rental"
                  class="p-1.5 rounded-lg transition-colors hover:opacity-80"
                  style="background:rgba(139,92,246,.15); color:#a78bfa; border:1px solid rgba(139,92,246,.3);">
                  <ArrowRightCircle :size="14" />
                </button>
                <!-- Edit -->
                <button v-if="r.status !== 'converted'"
                  @click="openEdit(r)"
                  title="Edit"
                  class="p-1.5 rounded-lg transition-colors hover:opacity-80"
                  style="background:rgba(59,130,246,.15); color:#60a5fa; border:1px solid rgba(59,130,246,.3);">
                  <Pencil :size="14" />
                </button>
                <!-- Delete -->
                <button v-if="r.status !== 'converted'"
                  @click="confirmDelete = r.id"
                  title="Hapus"
                  class="p-1.5 rounded-lg transition-colors hover:opacity-80"
                  style="background:rgba(239,68,68,.1); color:#f87171; border:1px solid rgba(239,68,68,.3);">
                  <Trash2 :size="14" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Modal -->
    <GamingModal :show="showAdd" title="Tambah Reservasi" @close="showAdd = false">
      <form @submit.prevent="submitAdd" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Console / Room</label>
            <select v-model.number="addForm.console_id" required
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option :value="null">-- Pilih Room --</option>
              <option v-for="c in consoles" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div v-if="addSelectedConsole" class="mt-1.5 px-3 py-1.5 rounded-lg flex items-center justify-between" style="background:rgba(139,92,246,.1); border:1px solid rgba(139,92,246,.25);">
              <span class="text-xs" style="color:#a78bfa;">{{ formatCurrency(addSelectedConsole.price_per_hour) }} / jam</span>
              <span v-if="addEstimated()" class="text-xs font-semibold text-white">
                ≈ {{ formatCurrency(addEstimated() ?? 0) }}
              </span>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Operator</label>
            <select v-model.number="addForm.employee_id" required
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option :value="null">-- Pilih Operator --</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama Customer</label>
            <input v-model="addForm.customer_name" required
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">No. HP (opsional)</label>
            <input v-model="addForm.customer_phone" type="tel"
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Tanggal & Waktu Reservasi</label>
          <div class="grid grid-cols-2 gap-3">
            <div class="relative">
              <input :value="addForm.reserved_at ? addForm.reserved_at.split('T')[0] : ''"
                @input="updateAddDate"
                type="date" required
                class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
                style="background:#12121a; border:1px solid #2a2a3a;" />
            </div>
            <div class="relative">
              <input :value="addForm.reserved_at ? addForm.reserved_at.split('T')[1] : '10:00'" 
                @input="updateAddTime"
                type="time"
                min="10:00"
                max="22:00"
                required
                class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
                style="background:#12121a; border:1px solid #2a2a3a;" />
            </div>
          </div>
          <div class="flex items-center gap-1.5 mt-1 text-[11px]" style="color:#a78bfa;">
            <Clock :size="12" />
            <span>Jam buka & tutup: <strong>10:00 - 22:00</strong></span>
          </div>
          <p v-if="timeError" class="text-red-400 text-xs mt-1">{{ timeError }}</p>
          <p v-if="addForm.errors.reserved_at" class="text-red-400 text-xs mt-1">{{ addForm.errors.reserved_at }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Durasi (opsional)</label>
          <div class="flex flex-wrap gap-2 mb-2">
            <button v-for="d in durationChips" :key="d" type="button"
              @click="addForm.duration_hours = addForm.duration_hours == d ? '' : d"
              class="px-3 py-1 rounded-lg text-xs font-medium transition-all"
              :style="addForm.duration_hours == d
                ? 'background:rgba(139,92,246,.4); border:1px solid #8b5cf6; color:white;'
                : 'background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;'">
              {{ d }}j
            </button>
          </div>
          <input v-model="addForm.duration_hours" type="number" min="0.5" step="0.5" placeholder="Atau ketik jam..."
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Catatan</label>
          <textarea v-model="addForm.notes" rows="2"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none resize-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div class="flex gap-3 pt-1">
          <button type="button" @click="showAdd = false"
            class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
          <button type="submit" :disabled="addForm.processing"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white disabled:opacity-60"
            style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">Simpan</button>
        </div>
      </form>
    </GamingModal>

    <!-- Edit Modal -->
    <GamingModal :show="showEdit" title="Edit Reservasi" @close="showEdit = false">
      <form @submit.prevent="submitEdit" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Console / Room</label>
            <select v-model.number="editForm.console_id" required
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option v-for="c in consoles" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div v-if="editSelectedConsole" class="mt-1.5 px-3 py-1.5 rounded-lg flex items-center justify-between" style="background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.25);">
              <span class="text-xs" style="color:#60a5fa;">{{ formatCurrency(editSelectedConsole.price_per_hour) }} / jam</span>
              <span v-if="editEstimated()" class="text-xs font-semibold text-white">
                ≈ {{ formatCurrency(editEstimated() ?? 0) }}
              </span>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Operator</label>
            <select v-model.number="editForm.employee_id" required
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama Customer</label>
            <input v-model="editForm.customer_name" required
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">No. HP</label>
            <input v-model="editForm.customer_phone" type="tel"
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Tanggal & Waktu Reservasi</label>
          <div class="grid grid-cols-2 gap-3">
            <div class="relative">
              <input :value="editForm.reserved_at ? editForm.reserved_at.split('T')[0] : ''"
                @input="updateEditDate"
                type="date" required
                class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
                style="background:#12121a; border:1px solid #2a2a3a;" />
            </div>
            <div class="relative">
              <input :value="editForm.reserved_at ? editForm.reserved_at.split('T')[1] : '10:00'" 
                @input="updateEditTime"
                type="time"
                min="10:00"
                max="22:00"
                required
                class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
                style="background:#12121a; border:1px solid #2a2a3a;" />
            </div>
          </div>
          <div class="flex items-center gap-1.5 mt-1 text-[11px]" style="color:#a78bfa;">
            <Clock :size="12" />
            <span>Jam buka & tutup: <strong>10:00 - 22:00</strong></span>
          </div>
          <p v-if="timeError" class="text-red-400 text-xs mt-1">{{ timeError }}</p>
          <p v-if="editForm.errors.reserved_at" class="text-red-400 text-xs mt-1">{{ editForm.errors.reserved_at }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Durasi (jam)</label>
            <input v-model="editForm.duration_hours" type="number" min="0.5" step="0.5"
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Status</label>
            <select v-model="editForm.status"
              class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option value="pending">Menunggu</option>
              <option value="confirmed">Dikonfirmasi</option>
              <option value="cancelled">Dibatalkan</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Catatan</label>
          <textarea v-model="editForm.notes" rows="2"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none resize-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div class="flex gap-3 pt-1">
          <button type="button" @click="showEdit = false"
            class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
          <button type="submit" :disabled="editForm.processing"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white disabled:opacity-60"
            style="background:linear-gradient(135deg,#3b82f6,#2563eb);">Simpan</button>
        </div>
      </form>
    </GamingModal>

    <!-- Delete Confirm -->
    <GamingModal :show="confirmDelete !== null" title="Hapus Reservasi?" @close="confirmDelete = null">
      <p class="text-sm mb-5" style="color:#94a3b8;">Yakin ingin menghapus reservasi ini? Data tidak bisa dikembalikan.</p>
      <div class="flex gap-3">
        <button @click="confirmDelete = null"
          class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
        <button @click="doDelete"
          class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white"
          style="background:linear-gradient(135deg,#ef4444,#dc2626);">Ya, Hapus</button>
      </div>
    </GamingModal>

    <!-- Convert Confirm -->
    <GamingModal :show="confirmConvert !== null" title="Mulai Rental dari Reservasi?" @close="confirmConvert = null">
      <div v-if="confirmConvert" class="space-y-3 mb-5">
        <p class="text-sm" style="color:#94a3b8;">Reservasi berikut akan dikonversi menjadi rental aktif:</p>
        <div class="p-3 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a;">
          <p class="text-white font-medium">{{ confirmConvert.customer_name }}</p>
          <p style="color:#94a3b8;">{{ confirmConvert.console?.name ?? '(dihapus)' }}</p>
          <p v-if="confirmConvert.duration_hours" style="color:#a78bfa;">{{ confirmConvert.duration_hours }} jam</p>
        </div>
      </div>
      <div class="flex gap-3">
        <button @click="confirmConvert = null"
          class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
        <button @click="doConvert"
          class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white"
          style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">Ya, Mulai Rental</button>
      </div>
    </GamingModal>
  </AppLayout>
</template>
