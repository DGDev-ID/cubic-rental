<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { Download, TrendingUp, Users, BarChart2, ChevronLeft, ChevronRight, Filter, X, Calendar, StickyNote, Trash2, Gamepad2, UtensilsCrossed, RotateCcw, ExternalLink, Receipt, Clock } from 'lucide-vue-next'

interface RentalRow {
  id: number; transaction_code: string; customer_name: string; rental_type: string;
  status: string; started_at: string; ended_at: string | null; total_amount: number;
  rental_amount?: number; fnb_amount?: number; extra_amount?: number; paid_amount?: number;
  notes?: string | null;
  console: { name: string; type: string; price_per_hour?: number }
  employee: { name: string }
  payments: { method: string; amount: number; notes?: string }[]
  fnb_items?: { id: number; qty: number; unit_price: number; subtotal: number; fnb_item?: { name: string } }[]
  extensions?: { id: number; added_minutes: number; additional_price: number }[]
}

const props = defineProps<{
  rentals: { data: RentalRow[]; links: any[]; total: number }
  fnb_orders?: { data: any[]; links: any[]; total: number }
  filters: { search: string; date: string; month?: string; year?: string; employee_id: string; console_id: string; payment_method: string }
  employees: { id: number; name: string }[]
  consoles: { id: number; name: string }[]
  today_revenue: number
  today_customers: number
  summary_revenue: number
  summary_rental_revenue?: number
  summary_fnb_revenue?: number
  summary_customers: number
  yearly_stats: { year: number; revenue: number; rental_revenue?: number; fnb_revenue?: number; customers: number }[]
  monthly_stats: { month: number; revenue: number; rental_revenue?: number; fnb_revenue?: number; customers: number }[]
  daily_stats: { day: number; revenue: number; rental_revenue?: number; fnb_revenue?: number; customers: number }[]
  stats_year: number
  stats_month: number
}>()

const activeTab = ref<'rental' | 'fnb'>('rental')

const filters = ref({
  search: props.filters?.search ?? '',
  date: props.filters?.date ?? '',
  month: props.filters?.month ?? '',
  year: props.filters?.year ?? '',
  employee_id: props.filters?.employee_id ?? '',
  console_id: props.filters?.console_id ?? '',
  payment_method: props.filters?.payment_method ?? ''
})

const localStatsYear = ref(props.stats_year)
const localStatsMonth = ref(props.stats_month)
const summaryMode = ref<'yearly' | 'monthly' | 'daily'>('monthly')
const showSummary = ref(true)

// Export modal
const showExportModal = ref(false)
const exportType = ref<'all' | 'year' | 'month' | 'day'>('all')
const exportYear = ref(new Date().getFullYear())
const exportMonth = ref('')
const exportDate = ref('')

let debounce: ReturnType<typeof setTimeout>
watch(filters, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    router.get(route('rentals.history'), {
      ...filters.value,
      stats_year: localStatsYear.value,
      stats_month: localStatsMonth.value,
    } as any, { preserveState: true, replace: true })
  }, 350)
}, { deep: true })

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']

const hasActiveFilter = computed(() =>
  !!(filters.value.search || filters.value.date || filters.value.month || filters.value.year ||
     filters.value.employee_id || filters.value.console_id || filters.value.payment_method)
)

const filterLabel = computed(() => {
  const parts: string[] = []
  if (filters.value.date) {
    const d = new Date(filters.value.date + 'T00:00:00')
    parts.push(d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }))
  }
  if (filters.value.month) {
    const [y, m] = filters.value.month.split('-')
    parts.push(`${monthNames[parseInt(m) - 1]} ${y}`)
  }
  if (filters.value.year) parts.push(`Tahun ${filters.value.year}`)
  if (filters.value.search) parts.push(`"${filters.value.search}"`)
  return parts.length ? parts.join(', ') : null
})

const activePeriodLabel = computed(() => {
  if (filters.value.date) {
    const d = new Date(filters.value.date + 'T00:00:00')
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
  }
  if (filters.value.month) {
    const [y, m] = filters.value.month.split('-')
    return `${monthNames[parseInt(m) - 1]} ${y}`
  }
  if (filters.value.year) {
    return `Tahun ${filters.value.year}`
  }
  return 'Semua Periode (Keseluruhan)'
})

function clearPeriodFilter() {
  filters.value.date = ''
  filters.value.month = ''
  filters.value.year = ''
}

// --- Year view ---
function selectYear(y: number) {
  if (filters.value.year === String(y)) {
    filters.value.year = ''
  } else {
    filters.value.date = ''
    filters.value.month = ''
    filters.value.year = String(y)
  }
}
function isSelectedYear(y: number) {
  return filters.value.year === String(y)
}

// --- Month view ---
function selectMonth(m: number) {
  const monthStr = `${localStatsYear.value}-${String(m).padStart(2, '0')}`
  if (filters.value.month === monthStr) {
    filters.value.month = ''
  } else {
    filters.value.date = ''
    filters.value.year = ''
    filters.value.month = monthStr
  }
}
function isSelectedMonth(m: number) {
  return filters.value.month === `${localStatsYear.value}-${String(m).padStart(2, '0')}`
}

// --- Day view ---
function selectDay(d: number) {
  const dateStr = `${localStatsYear.value}-${String(localStatsMonth.value).padStart(2, '0')}-${String(d).padStart(2, '0')}`
  if (filters.value.date === dateStr) {
    filters.value.date = ''
  } else {
    filters.value.month = ''
    filters.value.year = ''
    filters.value.date = dateStr
  }
}
function isSelectedDay(d: number) {
  const dateStr = `${localStatsYear.value}-${String(localStatsMonth.value).padStart(2, '0')}-${String(d).padStart(2, '0')}`
  return filters.value.date === dateStr
}

function changeYear(delta: number) {
  localStatsYear.value += delta
  filters.value.month = ''
  clearTimeout(debounce)
  router.get(route('rentals.history'), {
    ...filters.value,
    stats_year: localStatsYear.value,
    stats_month: localStatsMonth.value,
  } as any, { preserveState: true, replace: true })
}

function changeMonth(delta: number) {
  let m = localStatsMonth.value + delta
  let y = localStatsYear.value
  if (m < 1) { m = 12; y-- }
  if (m > 12) { m = 1; y++ }
  localStatsMonth.value = m
  localStatsYear.value = y
  clearTimeout(debounce)
  router.get(route('rentals.history'), {
    ...filters.value,
    stats_year: y,
    stats_month: m,
  } as any, { preserveState: true, replace: true })
}

// --- Export modal ---
function openExportModal() {
  if (filters.value.date) { exportType.value = 'day'; exportDate.value = filters.value.date }
  else if (filters.value.month) { exportType.value = 'month'; exportMonth.value = filters.value.month }
  else if (filters.value.year) { exportType.value = 'year'; exportYear.value = parseInt(filters.value.year) }
  else exportType.value = 'all'
  showExportModal.value = true
}

function doExport() {
  const q = new URLSearchParams()
  if (filters.value.search) q.append('search', filters.value.search)
  if (filters.value.employee_id) q.append('employee_id', filters.value.employee_id)
  if (filters.value.console_id) q.append('console_id', filters.value.console_id)
  if (filters.value.payment_method) q.append('payment_method', filters.value.payment_method)
  if (exportType.value === 'day' && exportDate.value) q.append('date', exportDate.value)
  else if (exportType.value === 'month' && exportMonth.value) q.append('month', exportMonth.value)
  else if (exportType.value === 'year') q.append('year', String(exportYear.value))
  window.location.href = route('rentals.export') + '?' + q.toString()
  showExportModal.value = false
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}

function formatCurrencyShort(v: number) {
  if (v >= 1_000_000) return 'Rp\u202f' + (v / 1_000_000).toFixed(1).replace('.', ',') + 'jt'
  if (v >= 1_000) return 'Rp\u202f' + Math.round(v / 1_000) + 'rb'
  return formatCurrency(v)
}

function formatDt(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatDuration(start: string, end: string | null) {
  if (!start) return '-'
  const s = new Date(start).getTime()
  const e = end ? new Date(end).getTime() : Date.now()
  const diff = Math.max(0, Math.floor((e - s) / 60000))
  const h = Math.floor(diff / 60)
  const m = diff % 60
  return h > 0 ? `${h} jam ${m} mnt` : `${m} mnt`
}

const availableYears = computed(() => {
  const years = props.yearly_stats.map(y => y.year)
  if (!years.length) return [new Date().getFullYear()]
  return years
})

const summaryModes = ['yearly', 'monthly', 'daily'] as const
const exportTypes = ['all', 'year', 'month', 'day'] as const

const statusColor: Record<string, string> = {
  running: '#22d3ee', finished: '#22c55e', paid: '#a78bfa', cancelled: '#ef4444', half_paid: '#fbbf24',
}
const typeColors: Record<string, string> = { regular: '#60a5fa', vip: '#a78bfa', vvip: '#22d3ee', suite: '#fbbf24' }

const statusLabels: Record<string, string> = {
  running: 'Berlangsung', finished: 'Selesai', paid: 'Lunas', cancelled: 'Dibatalkan', half_paid: 'Bayar Sebagian',
}
const statusBadgeStyle: Record<string, string> = {
  running: 'background:rgba(34,211,238,.15); color:#22d3ee; border:1px solid rgba(34,211,238,.3);',
  finished: 'background:rgba(34,197,94,.15); color:#22c55e; border:1px solid rgba(34,197,94,.3);',
  paid: 'background:rgba(167,139,250,.15); color:#a78bfa; border:1px solid rgba(167,139,250,.3);',
  cancelled: 'background:rgba(239,68,68,.15); color:#ef4444; border:1px solid rgba(239,68,68,.3);',
  half_paid: 'background:rgba(251,191,36,.15); color:#fbbf24; border:1px solid rgba(251,191,36,.3);',
}

const detail = ref<RentalRow | null>(null)

// Detail Modals
const showDetailModal = ref(false)
const selectedDetail = ref<RentalRow | null>(null)

const showFnbDetailModal = ref(false)
const selectedFnbDetail = ref<any | null>(null)

function openDetailRental(r: RentalRow) {
  selectedDetail.value = r
  showDetailModal.value = true
}

function openDetailFnb(r: any) {
  selectedFnbDetail.value = r
  showFnbDetailModal.value = true
}

// Modals: Edit Catatan & Hapus Transaksi
const showNotesModal = ref(false)
const showDeleteModal = ref(false)
const targetType = ref<'rental' | 'fnb'>('rental')
const editingRental = ref<RentalRow | null>(null)
const editingFnb = ref<any | null>(null)
const deletingRental = ref<RentalRow | null>(null)
const deletingFnb = ref<any | null>(null)
const isDeleting = ref(false)

const notesForm = useForm({
  notes: '',
})

function openEditNotesRental(r: RentalRow) {
  targetType.value = 'rental'
  editingRental.value = r
  editingFnb.value = null
  notesForm.notes = r.notes || ''
  showNotesModal.value = true
}

function openEditNotesFnb(r: any) {
  targetType.value = 'fnb'
  editingFnb.value = r
  editingRental.value = null
  notesForm.notes = r.notes || ''
  showNotesModal.value = true
}

function saveNotes() {
  if (targetType.value === 'rental' && editingRental.value) {
    notesForm.patch(route('rentals.update-notes', editingRental.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        if (editingRental.value) {
          editingRental.value.notes = notesForm.notes
        }
        if (detail.value && detail.value.id === editingRental.value?.id) {
          detail.value.notes = notesForm.notes
        }
        showNotesModal.value = false
      }
    })
  } else if (targetType.value === 'fnb' && editingFnb.value) {
    notesForm.patch(route('fnb-orders.update-notes', editingFnb.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        if (editingFnb.value) {
          editingFnb.value.notes = notesForm.notes
        }
        showNotesModal.value = false
      }
    })
  }
}

function openDeleteRental(r: RentalRow) {
  targetType.value = 'rental'
  deletingRental.value = r
  deletingFnb.value = null
  showDeleteModal.value = true
}

function openDeleteFnb(r: any) {
  targetType.value = 'fnb'
  deletingFnb.value = r
  deletingRental.value = null
  showDeleteModal.value = true
}

function confirmDelete() {
  isDeleting.value = true
  if (targetType.value === 'rental' && deletingRental.value) {
    router.delete(route('rentals.destroy', deletingRental.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        if (detail.value?.id === deletingRental.value?.id) {
          detail.value = null
        }
        showDeleteModal.value = false
        isDeleting.value = false
      },
      onError: () => {
        isDeleting.value = false
      }
    })
  } else if (targetType.value === 'fnb' && deletingFnb.value) {
    router.delete(route('fnb-orders.destroy', deletingFnb.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        showDeleteModal.value = false
        isDeleting.value = false
      },
      onError: () => {
        isDeleting.value = false
      }
    })
  }
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Riwayat Transaksi</h1></template>
    <template #header-actions>
      <button @click="openExportModal"
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors hover:opacity-90"
        style="background:linear-gradient(135deg,#10b981,#059669);">
        <Download :size="16" /> Export Excel
      </button>
    </template>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-4 mb-5">
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <TrendingUp :size="15" style="color:#10b981;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Omset Hari Ini</span>
        </div>
        <p class="text-xl font-bold truncate" style="color:#10b981;">{{ formatCurrency(today_revenue) }}</p>
      </div>
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <Users :size="15" style="color:#60a5fa;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Customer Hari Ini</span>
        </div>
        <p class="text-xl font-bold" style="color:#60a5fa;">{{ today_customers }} <span class="text-sm font-normal" style="color:#64748b;">customer</span></p>
      </div>
    </div>

    <!-- Summary Section -->
    <div class="rounded-xl p-4 mb-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
      <!-- Header row -->
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <BarChart2 :size="15" style="color:#a78bfa;" />
          <span class="text-sm font-semibold text-white">Ringkasan Periode</span>
        </div>
        <div class="flex items-center gap-2">
          <!-- Mode tabs -->
          <div class="flex gap-1 p-0.5 rounded-lg" style="background:#12121a; border:1px solid #2a2a3a;">
            <button
              v-for="mode in summaryModes" :key="mode"
              @click="summaryMode = mode"
              class="px-2.5 py-1 rounded-md text-xs font-medium transition-all"
              :style="summaryMode === mode
                ? 'background:rgba(139,92,246,.3); color:#c4b5fd; border:1px solid rgba(139,92,246,.4);'
                : 'color:#64748b; border:1px solid transparent;'">
              {{ mode === 'yearly' ? 'Tahunan' : mode === 'monthly' ? 'Bulanan' : 'Harian' }}
            </button>
          </div>
          <!-- Nav for monthly/daily -->
          <template v-if="summaryMode === 'monthly'">
            <button @click="changeYear(-1)" class="p-1 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><ChevronLeft :size="15" /></button>
            <span class="text-sm font-medium text-white px-0.5">{{ localStatsYear }}</span>
            <button @click="changeYear(1)" class="p-1 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><ChevronRight :size="15" /></button>
          </template>
          <template v-if="summaryMode === 'daily'">
            <button @click="changeMonth(-1)" class="p-1 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><ChevronLeft :size="15" /></button>
            <span class="text-xs font-medium text-white px-0.5">{{ monthNames[localStatsMonth - 1] }} {{ localStatsYear }}</span>
            <button @click="changeMonth(1)" class="p-1 rounded-lg hover:bg-white/10" style="color:#94a3b8;"><ChevronRight :size="15" /></button>
          </template>
          <button @click="showSummary = !showSummary"
            class="ml-1 px-2 py-1 rounded-lg text-xs hover:bg-white/10"
            style="color:#64748b;">
            {{ showSummary ? '▲' : '▼' }}
          </button>
        </div>
      </div>

      <!-- Yearly cards -->
      <div v-if="showSummary && summaryMode === 'yearly'" class="flex flex-wrap gap-2">
        <button
          v-for="stat in yearly_stats" :key="stat.year"
          @click="selectYear(stat.year)"
          class="px-4 py-3 rounded-xl text-left transition-all min-w-[100px]"
          :style="isSelectedYear(stat.year)
            ? 'background:rgba(139,92,246,.25); border:1px solid rgba(139,92,246,.5);'
            : 'background:#12121a; border:1px solid #2a2a3a;'">
          <p class="text-xs font-semibold mb-1"
            :style="isSelectedYear(stat.year) ? 'color:#c4b5fd;' : 'color:#64748b;'">{{ stat.year }}</p>
          <p class="text-xs font-bold text-white leading-tight">{{ formatCurrencyShort(stat.revenue) }}</p>
          <div v-if="stat.rental_revenue !== undefined || stat.fnb_revenue !== undefined" class="text-[10px] mt-1 flex items-center gap-1.5 text-slate-400">
            <span class="text-sky-300" title="Rental Saja">R:{{ formatCurrencyShort(stat.rental_revenue ?? 0).replace('Rp ', '') }}</span>
            <span class="text-slate-600">•</span>
            <span class="text-emerald-300" title="FnB Saja">F:{{ formatCurrencyShort(stat.fnb_revenue ?? 0).replace('Rp ', '') }}</span>
          </div>
          <p class="text-[10px] mt-0.5" style="color:#94a3b8;">{{ stat.customers }} cust</p>
        </button>
        <div v-if="!yearly_stats.length" class="text-xs py-2" style="color:#64748b;">Belum ada data</div>
      </div>

      <!-- Monthly cards -->
      <div v-if="showSummary && summaryMode === 'monthly'" class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
        <button
          v-for="stat in monthly_stats" :key="stat.month"
          @click="selectMonth(stat.month)"
          class="p-3 rounded-xl text-left transition-all"
          :style="isSelectedMonth(stat.month)
            ? 'background:rgba(139,92,246,.25); border:1px solid rgba(139,92,246,.5);'
            : 'background:#12121a; border:1px solid #2a2a3a;'">
          <p class="text-xs font-semibold mb-1"
            :style="isSelectedMonth(stat.month) ? 'color:#c4b5fd;' : 'color:#64748b;'">{{ monthNames[stat.month - 1] }}</p>
          <p class="text-xs font-bold text-white leading-tight">{{ formatCurrencyShort(stat.revenue) }}</p>
          <div v-if="stat.rental_revenue !== undefined || stat.fnb_revenue !== undefined" class="text-[10px] mt-1 flex items-center gap-1.5 text-slate-400">
            <span class="text-sky-300" title="Rental Saja">R:{{ formatCurrencyShort(stat.rental_revenue ?? 0).replace('Rp ', '') }}</span>
            <span class="text-slate-600">•</span>
            <span class="text-emerald-300" title="FnB Saja">F:{{ formatCurrencyShort(stat.fnb_revenue ?? 0).replace('Rp ', '') }}</span>
          </div>
          <p class="text-[10px] mt-0.5" style="color:#94a3b8;">{{ stat.customers }} cust</p>
        </button>
      </div>

      <!-- Daily cards -->
      <div v-if="showSummary && summaryMode === 'daily'" class="grid grid-cols-4 sm:grid-cols-7 lg:grid-cols-10 gap-1.5">
        <button
          v-for="stat in daily_stats" :key="stat.day"
          @click="selectDay(stat.day)"
          class="p-2 rounded-xl text-left transition-all"
          :style="isSelectedDay(stat.day)
            ? 'background:rgba(139,92,246,.25); border:1px solid rgba(139,92,246,.5);'
            : stat.revenue > 0 ? 'background:#12121a; border:1px solid #2a2a3a;' : 'background:#0d0d14; border:1px solid #1a1a26; opacity:.5;'">
          <p class="text-xs font-bold mb-0.5"
            :style="isSelectedDay(stat.day) ? 'color:#c4b5fd;' : 'color:#64748b;'">{{ stat.day }}</p>
          <p class="text-[10px] font-semibold text-white leading-tight truncate">{{ stat.revenue > 0 ? formatCurrencyShort(stat.revenue) : '-' }}</p>
          <div v-if="stat.revenue > 0" class="text-[9px] mt-0.5 flex items-center gap-1 truncate text-slate-400">
            <span class="text-sky-300">R:{{ formatCurrencyShort(stat.rental_revenue ?? 0).replace('Rp ', '') }}</span>
            <span class="text-emerald-300">F:{{ formatCurrencyShort(stat.fnb_revenue ?? 0).replace('Rp ', '') }}</span>
          </div>
          <p class="text-[10px] mt-0.5" style="color:#94a3b8;">{{ stat.customers > 0 ? stat.customers + 'c' : '' }}</p>
        </button>
      </div>

      <!-- Breakdown Omset Periode Terpilih -->
      <div v-if="showSummary" class="mt-4 pt-4 border-t border-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-medium">Ringkasan Omset Periode:</span>
            <span class="text-xs px-2.5 py-1 rounded-lg font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30 flex items-center gap-1.5">
              <Calendar :size="12" /> {{ activePeriodLabel }}
            </span>
            <button v-if="filters.year || filters.month || filters.date" @click="clearPeriodFilter"
              class="text-[11px] text-slate-400 hover:text-red-400 transition-colors underline flex items-center gap-1 ml-1"
              title="Reset ke semua periode">
              <RotateCcw :size="11" /> Reset Periode
            </button>
          </div>
          <span class="text-xs text-slate-400">
            Total <strong class="text-white">{{ summary_customers }}</strong> Transaksi
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <!-- Total Omset (Rental + FnB) -->
          <div class="rounded-xl p-3.5 flex flex-col justify-between"
            style="background:linear-gradient(135deg, rgba(139,92,246,0.18) 0%, rgba(139,92,246,0.06) 100%); border:1px solid rgba(139,92,246,0.4);">
            <div class="flex items-center justify-between mb-1.5">
              <span class="text-xs font-semibold text-purple-200 flex items-center gap-1.5">
                <BarChart2 :size="14" class="text-purple-400" /> Total Omset (Rental + FnB)
              </span>
              <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-purple-500/25 text-purple-300">Gabungan</span>
            </div>
            <p class="text-xl font-bold" style="color:#c4b5fd;">{{ formatCurrency(summary_revenue) }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Gabungan seluruh transaksi rental console & pesanan FnB</p>
          </div>

          <!-- Total Rental Saja -->
          <div class="rounded-xl p-3.5 flex flex-col justify-between"
            style="background:linear-gradient(135deg, rgba(56,189,248,0.18) 0%, rgba(56,189,248,0.06) 100%); border:1px solid rgba(56,189,248,0.4);">
            <div class="flex items-center justify-between mb-1.5">
              <span class="text-xs font-semibold text-sky-200 flex items-center gap-1.5">
                <Gamepad2 :size="14" class="text-sky-400" /> Total Rental Saja
              </span>
              <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-sky-500/25 text-sky-300">Console</span>
            </div>
            <p class="text-xl font-bold text-sky-400">{{ formatCurrency(summary_rental_revenue ?? 0) }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Hanya biaya durasi bermain & extra waktu PS</p>
          </div>

          <!-- Total FnB Saja -->
          <div class="rounded-xl p-3.5 flex flex-col justify-between"
            style="background:linear-gradient(135deg, rgba(52,211,153,0.18) 0%, rgba(52,211,153,0.06) 100%); border:1px solid rgba(52,211,153,0.4);">
            <div class="flex items-center justify-between mb-1.5">
              <span class="text-xs font-semibold text-emerald-200 flex items-center gap-1.5">
                <UtensilsCrossed :size="14" class="text-emerald-400" /> Total FnB Saja
              </span>
              <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-emerald-500/25 text-emerald-300">FnB</span>
            </div>
            <p class="text-xl font-bold text-emerald-400">{{ formatCurrency(summary_fnb_revenue ?? 0) }}</p>
            <p class="text-[11px] text-slate-400 mt-1">Penjualan makanan & minuman (room + kasir)</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl p-4 mb-4 grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3"
      style="background:#1a1a26; border:1px solid #2a2a3a;">
      <input v-model="filters.search" placeholder="Cari customer/kode..."
        class="px-3 py-2 rounded-xl text-sm text-white outline-none col-span-2 sm:col-span-1"
        style="background:#12121a; border:1px solid #2a2a3a;" />
      <select v-model="filters.employee_id"
        class="px-3 py-2 rounded-xl text-sm text-white outline-none"
        style="background:#12121a; border:1px solid #2a2a3a;">
        <option value="">Semua Operator</option>
        <option v-for="e in employees" :key="e.id" :value="String(e.id)">{{ e.name }}</option>
      </select>
      <select v-model="filters.console_id"
        class="px-3 py-2 rounded-xl text-sm text-white outline-none"
        style="background:#12121a; border:1px solid #2a2a3a;">
        <option value="">Semua Console</option>
        <option v-for="c in consoles" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
      </select>
      <select v-model="filters.payment_method"
        class="px-3 py-2 rounded-xl text-sm text-white outline-none"
        style="background:#12121a; border:1px solid #2a2a3a;">
        <option value="">Semua Metode</option>
        <option value="cash">Cash</option>
        <option value="qris">QRIS</option>
        <option value="transfer">Transfer</option>
        <option value="debit">Debit</option>
      </select>
    </div>

    <!-- Filtered Summary -->
    <div v-if="hasActiveFilter" class="rounded-xl px-4 py-3 mb-4 flex flex-wrap items-center justify-between gap-4"
      style="background:rgba(139,92,246,.08); border:1px solid rgba(139,92,246,.3);">
      <div class="flex items-center gap-2 min-w-0">
        <Filter :size="13" style="color:#a78bfa;" />
        <span class="text-xs truncate font-medium" style="color:#94a3b8;">
          Filter aktif: <strong class="text-white">{{ filterLabel ? filterLabel : 'Periode' }}</strong>
        </span>
      </div>
      <div class="flex items-center gap-5 shrink-0 flex-wrap">
        <div class="text-right">
          <p class="text-[11px]" style="color:#94a3b8;">Total Rental Saja</p>
          <p class="text-sm font-bold text-sky-400">{{ formatCurrency(summary_rental_revenue ?? 0) }}</p>
        </div>
        <div class="text-right">
          <p class="text-[11px]" style="color:#94a3b8;">Total FnB Saja</p>
          <p class="text-sm font-bold text-emerald-400">{{ formatCurrency(summary_fnb_revenue ?? 0) }}</p>
        </div>
        <div class="text-right">
          <p class="text-[11px]" style="color:#c4b5fd;">Total Omset (Rental + FnB)</p>
          <p class="text-sm font-bold" style="color:#a78bfa;">{{ formatCurrency(summary_revenue) }}</p>
        </div>
        <div class="text-right pl-3 border-l border-slate-700/60">
          <p class="text-[11px]" style="color:#64748b;">Transaksi</p>
          <p class="text-sm font-bold text-white">{{ summary_customers }}</p>
        </div>
      </div>
    </div>

    <div class="flex gap-1 mb-4 p-1 rounded-xl w-fit" style="background:#1a1a26; border:1px solid #2a2a3a;">
      <button @click="activeTab = 'rental'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
        :style="activeTab === 'rental' ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;' : 'color:#94a3b8;'">
        Rental ({{ rentals.total }})
      </button>
      <button @click="activeTab = 'fnb'" class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
        :style="activeTab === 'fnb' ? 'background:linear-gradient(135deg,#10b981,#059669); color:white;' : 'color:#94a3b8;'">
        FnB Only ({{ fnb_orders?.total || 0 }})
      </button>
    </div>

    <!-- Table Rental -->
    <div v-if="activeTab === 'rental'" class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead style="background:#12121a;">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Kode</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Customer</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Console</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Waktu</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Total</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="rentals.data.length === 0">
            <td colspan="7" class="px-4 py-10 text-center text-xs" style="color:#64748b;">Tidak ada data</td>
          </tr>
          <tr v-for="r in rentals.data" :key="r.id"
            class="transition-colors" style="border-top:1px solid #1a1a26;"
            :style="{ background: r.id === detail?.id ? 'rgba(139,92,246,.1)' : '' }">
            <td class="px-4 py-3 font-mono text-xs" style="color:#94a3b8;">{{ r.transaction_code }}</td>
            <td class="px-4 py-3">
              <span class="text-white">{{ r.customer_name }}</span>
              <div v-if="r.notes" class="text-[11px] text-blue-400/90 truncate max-w-[180px] flex items-center gap-1 mt-0.5" :title="r.notes">
                <StickyNote :size="11" class="shrink-0" />
                <span class="truncate">{{ r.notes }}</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="text-white">{{ r.console?.name ?? '(dihapus)' }}</span>
              <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full" :style="`background:rgba(99,102,241,.15); color:${typeColors[r.console?.type ?? 'regular']}`">
                {{ r.console?.type ?? '-' }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs" style="color:#94a3b8;">{{ formatDt(r.started_at) }}</td>
            <td class="px-4 py-3 font-semibold" style="color:#a78bfa;">{{ formatCurrency(r.total_amount) }}</td>
            <td class="px-4 py-3">
              <span class="text-xs px-2 py-1 rounded-full" :style="`background:${statusColor[r.status]}22; color:${statusColor[r.status]}`">
                {{ r.status }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5 justify-end">
                <button @click="openDetailRental(r)"
                  class="text-xs px-2.5 py-1.5 rounded-lg transition-all"
                  style="background:rgba(139,92,246,.2); border:1px solid rgba(139,92,246,.3); color:#a78bfa;">
                  Detail
                </button>
                <a :href="route('rentals.receipt', r.id)"
                  class="text-xs px-2.5 py-1.5 rounded-lg transition-all"
                  style="background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.3); color:#22c55e; text-decoration:none;">
                  Struk
                </a>
                <button @click="openEditNotesRental(r)"
                  class="text-xs p-1.5 rounded-lg transition-all hover:bg-blue-500/25"
                  style="background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#60a5fa;"
                  title="Edit Catatan">
                  <StickyNote :size="14" />
                </button>
                <button @click="openDeleteRental(r)"
                  class="text-xs p-1.5 rounded-lg transition-all hover:bg-red-500/25 text-red-400"
                  style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3);"
                  title="Hapus Transaksi">
                  <Trash2 :size="14" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Table FnB -->
    <div v-if="activeTab === 'fnb' && fnb_orders" class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead style="background:#12121a;">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Kode</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Customer</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Console</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Waktu</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Total</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="fnb_orders.data.length === 0">
            <td colspan="7" class="px-4 py-10 text-center text-xs" style="color:#64748b;">Tidak ada data</td>
          </tr>
          <tr v-for="r in fnb_orders.data" :key="r.id"
            class="transition-colors" style="border-top:1px solid #1a1a26;">
            <td class="px-4 py-3 font-mono text-xs" style="color:#94a3b8;">{{ r.code }}</td>
            <td class="px-4 py-3">
              <span class="text-white">{{ r.customer_name || '-' }}</span>
              <div v-if="r.notes" class="text-[11px] text-emerald-400/90 truncate max-w-[180px] flex items-center gap-1 mt-0.5" :title="r.notes">
                <StickyNote :size="11" class="shrink-0" />
                <span class="truncate">{{ r.notes }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-white">{{ r.console?.name || '-' }}</td>
            <td class="px-4 py-3 text-xs" style="color:#94a3b8;">{{ formatDt(r.paid_at) }}</td>
            <td class="px-4 py-3 font-semibold" style="color:#10b981;">{{ formatCurrency(r.total_amount) }}</td>
            <td class="px-4 py-3">
              <span class="text-xs px-2 py-1 rounded-full" style="background:rgba(34,197,94,.15); color:#22c55e;">
                Lunas
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5 justify-end">
                <button @click="openDetailFnb(r)"
                  class="text-xs px-2.5 py-1.5 rounded-lg transition-all"
                  style="background:rgba(139,92,246,.2); border:1px solid rgba(139,92,246,.3); color:#a78bfa;">
                  Detail
                </button>
                <button @click="openEditNotesFnb(r)"
                  class="text-xs p-1.5 rounded-lg transition-all hover:bg-blue-500/25"
                  style="background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3); color:#60a5fa;"
                  title="Edit Catatan">
                  <StickyNote :size="14" />
                </button>
                <button @click="openDeleteFnb(r)"
                  class="text-xs p-1.5 rounded-lg transition-all hover:bg-red-500/25 text-red-400"
                  style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3);"
                  title="Hapus Transaksi">
                  <Trash2 :size="14" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="activeTab === 'rental'" class="flex gap-1 mt-4 justify-center flex-wrap">
      <template v-for="link in rentals.links" :key="link.label">
        <a v-if="link.url" :href="link.url"
          class="px-3 py-1.5 rounded-xl text-xs"
          :style="link.active
            ? 'background:rgba(139,92,246,.3); color:white; border:1px solid rgba(139,92,246,.5);'
            : 'background:#1a1a26; color:#94a3b8; border:1px solid #2a2a3a;'"
          v-html="link.label" />
        <span v-else class="px-3 py-1.5 rounded-xl text-xs" style="color:#3a3a4a;" v-html="link.label" />
      </template>
    </div>
    <div v-if="activeTab === 'fnb' && fnb_orders" class="flex gap-1 mt-4 justify-center flex-wrap">
      <template v-for="link in fnb_orders.links" :key="link.label">
        <a v-if="link.url" :href="link.url"
          class="px-3 py-1.5 rounded-xl text-xs"
          :style="link.active
            ? 'background:rgba(16,185,129,.3); color:white; border:1px solid rgba(16,185,129,.5);'
            : 'background:#1a1a26; color:#94a3b8; border:1px solid #2a2a3a;'"
          v-html="link.label" />
        <span v-else class="px-3 py-1.5 rounded-xl text-xs" style="color:#3a3a4a;" v-html="link.label" />
      </template>
    </div>

    <!-- Detail Drawer Removed (Replaced by direct popup Modal) -->
  </AppLayout>

  <!-- Detail Rental Modal -->
  <GamingModal :show="showDetailModal" :title="`Detail Rental – ${selectedDetail?.transaction_code || ''}`" @close="showDetailModal = false">
    <div v-if="selectedDetail" class="space-y-4 max-h-[75vh] overflow-y-auto pr-1">
      <!-- Header status pill & console -->
      <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800">
        <div>
          <span class="text-xs uppercase font-mono px-2 py-0.5 rounded-full"
            :style="{ background: (typeColors[selectedDetail.console?.type] || '#60a5fa') + '22', color: typeColors[selectedDetail.console?.type] || '#60a5fa' }">
            {{ selectedDetail.console?.type }}
          </span>
          <h4 class="text-base font-bold text-white mt-1">{{ selectedDetail.console?.name }}</h4>
        </div>
        <div class="text-right">
          <span class="text-xs px-2.5 py-1 rounded-full font-medium"
            :style="statusBadgeStyle[selectedDetail.status]">
            {{ statusLabels[selectedDetail.status] || selectedDetail.status }}
          </span>
          <p class="text-xs text-slate-400 mt-1 capitalize">{{ selectedDetail.rental_type.replace('_', ' ') }}</p>
        </div>
      </div>

      <!-- Info grid -->
      <div class="grid grid-cols-2 gap-3 text-xs p-3 rounded-xl bg-slate-900/40 border border-slate-800/80">
        <div>
          <span class="text-slate-400">Customer</span>
          <p class="text-white font-semibold mt-0.5">{{ selectedDetail.customer_name || 'Customer' }}</p>
        </div>
        <div>
          <span class="text-slate-400">Operator</span>
          <p class="text-white font-semibold mt-0.5">{{ selectedDetail.employee?.name ?? '(dihapus)' }}</p>
        </div>
        <div>
          <span class="text-slate-400">Mulai</span>
          <p class="text-white font-medium mt-0.5">{{ formatDt(selectedDetail.started_at) }}</p>
        </div>
        <div>
          <span class="text-slate-400">Selesai</span>
          <p class="text-white font-medium mt-0.5">{{ formatDt(selectedDetail.ended_at) }}</p>
        </div>
        <div>
          <span class="text-slate-400">Durasi Bermain</span>
          <p class="text-purple-400 font-semibold mt-0.5">{{ formatDuration(selectedDetail.started_at, selectedDetail.ended_at) }}</p>
        </div>
        <div>
          <span class="text-slate-400">Tarif / Jam</span>
          <p class="text-white font-medium mt-0.5">{{ formatCurrency(selectedDetail.console?.price_per_hour || 0) }}</p>
        </div>
      </div>

      <!-- FnB Items (jika ada) -->
      <div v-if="selectedDetail.fnb_items && selectedDetail.fnb_items.length" class="p-3 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
        <div class="flex items-center justify-between text-xs font-semibold text-white border-b border-slate-800 pb-1.5">
          <span class="flex items-center gap-1.5 text-emerald-400"><UtensilsCrossed :size="13" /> Pesanan F&B</span>
          <span>{{ formatCurrency(selectedDetail.fnb_amount || 0) }}</span>
        </div>
        <div class="space-y-1 text-xs">
          <div v-for="fi in selectedDetail.fnb_items" :key="fi.id" class="flex justify-between items-center text-slate-300">
            <span>{{ fi.fnb_item?.name || 'Item F&B' }} <span class="text-slate-400">x{{ fi.qty }}</span></span>
            <span class="font-medium text-white">{{ formatCurrency(fi.subtotal) }}</span>
          </div>
        </div>
      </div>

      <!-- Extensions (jika ada) -->
      <div v-if="selectedDetail.extensions && selectedDetail.extensions.length" class="p-3 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-1.5">
        <div class="flex items-center justify-between text-xs font-semibold text-white border-b border-slate-800 pb-1.5">
          <span class="flex items-center gap-1.5 text-sky-400"><Clock :size="13" /> Penambahan Waktu</span>
          <span>{{ formatCurrency(selectedDetail.extra_amount || 0) }}</span>
        </div>
        <div class="space-y-1 text-xs">
          <div v-for="ext in selectedDetail.extensions" :key="ext.id" class="flex justify-between items-center text-slate-300">
            <span>+{{ ext.added_minutes }} Menit</span>
            <span class="font-medium text-white">{{ formatCurrency(ext.additional_price) }}</span>
          </div>
        </div>
      </div>

      <!-- Total & Payment Breakdown -->
      <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 space-y-2">
        <div class="flex justify-between text-xs text-slate-300">
          <span>Biaya Rental PS</span>
          <span class="text-white font-medium">{{ formatCurrency(selectedDetail.rental_amount || 0) }}</span>
        </div>
        <div v-if="selectedDetail.extra_amount" class="flex justify-between text-xs text-slate-300">
          <span>Tambahan Waktu</span>
          <span class="text-white font-medium">{{ formatCurrency(selectedDetail.extra_amount) }}</span>
        </div>
        <div v-if="selectedDetail.fnb_amount" class="flex justify-between text-xs text-slate-300">
          <span>Pesanan F&B</span>
          <span class="text-white font-medium">{{ formatCurrency(selectedDetail.fnb_amount) }}</span>
        </div>
        <div class="flex justify-between text-sm font-bold pt-2 border-t border-slate-800">
          <span class="text-white">Total Tagihan</span>
          <span class="text-purple-400">{{ formatCurrency(selectedDetail.total_amount) }}</span>
        </div>

        <!-- Payments -->
        <div class="pt-2 border-t border-slate-800">
          <span class="text-[11px] text-slate-400 block mb-1">Metode Pembayaran:</span>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="p in selectedDetail.payments" :key="p.method + p.amount"
              class="text-xs px-2.5 py-1 rounded-lg font-semibold flex items-center gap-1"
              style="background:rgba(34,197,94,.15); color:#22c55e; border:1px solid rgba(34,197,94,.3);">
              ✓ {{ p.method }}: {{ formatCurrency(p.amount) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Notes (jika ada) -->
      <div v-if="selectedDetail.notes" class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-xs">
        <span class="text-slate-400 font-medium block mb-1">Catatan:</span>
        <p class="text-slate-200">{{ selectedDetail.notes }}</p>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-800">
        <div class="flex items-center gap-2">
          <button type="button" @click="openEditNotesRental(selectedDetail); showDetailModal = false"
            class="text-xs px-3 py-2 rounded-xl text-blue-400 bg-blue-500/15 border border-blue-500/30 hover:bg-blue-500/25 flex items-center gap-1.5">
            <StickyNote :size="13" /> Edit Catatan
          </button>
          <a :href="route('rentals.receipt', selectedDetail.id)" target="_blank"
            class="text-xs px-3 py-2 rounded-xl text-emerald-400 bg-emerald-500/15 border border-emerald-500/30 hover:bg-emerald-500/25 flex items-center gap-1.5">
            <Receipt :size="13" /> Cetak Struk
          </a>
        </div>
        <div class="flex items-center gap-2">
          <a :href="route('rentals.show', selectedDetail.id)"
            class="text-xs px-3 py-2 rounded-xl text-white font-medium flex items-center gap-1.5 transition-all"
            style="background:linear-gradient(135deg,#7c3aed,#6d28d9);">
            <ExternalLink :size="13" /> Halaman Lengkap
          </a>
          <button type="button" @click="showDetailModal = false"
            class="text-xs px-3 py-2 rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </GamingModal>

  <!-- Detail FnB Modal -->
  <GamingModal :show="showFnbDetailModal" :title="`Detail Pesanan FNB – ${selectedFnbDetail?.code || ''}`" @close="showFnbDetailModal = false">
    <div v-if="selectedFnbDetail" class="space-y-4 max-h-[75vh] overflow-y-auto pr-1">
      <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800">
        <div>
          <span class="text-xs uppercase font-mono px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400">
            F&B POS
          </span>
          <h4 class="text-base font-bold text-white mt-1">{{ selectedFnbDetail.customer_name || 'Walk-in Customer' }}</h4>
        </div>
        <div class="text-right">
          <span class="text-xs px-2.5 py-1 rounded-full font-medium bg-emerald-500/20 text-emerald-400">
            Lunas
          </span>
          <p class="text-xs text-slate-400 mt-1">{{ formatDt(selectedFnbDetail.paid_at || selectedFnbDetail.created_at) }}</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 text-xs p-3 rounded-xl bg-slate-900/40 border border-slate-800/80">
        <div>
          <span class="text-slate-400">Kasir / Operator</span>
          <p class="text-white font-semibold mt-0.5">{{ selectedFnbDetail.employee?.name || '(dihapus)' }}</p>
        </div>
        <div>
          <span class="text-slate-400">Metode Bayar</span>
          <p class="text-emerald-400 font-bold uppercase mt-0.5">{{ selectedFnbDetail.payment_method }}</p>
        </div>
      </div>

      <!-- Items -->
      <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-800/80 space-y-2">
        <h5 class="text-xs font-semibold text-white border-b border-slate-800 pb-1.5 flex items-center gap-1.5 text-emerald-400">
          <UtensilsCrossed :size="13" /> Rincian Menu
        </h5>
        <div class="space-y-1.5 text-xs">
          <div v-for="it in selectedFnbDetail.items" :key="it.id" class="flex justify-between items-center text-slate-300">
            <div>
              <span class="text-white font-medium">{{ it.fnb_item?.name || 'Item F&B' }}</span>
              <span class="text-slate-400 ml-2">x{{ it.qty }} @ {{ formatCurrency(it.unit_price) }}</span>
            </div>
            <span class="font-bold text-white">{{ formatCurrency(it.subtotal) }}</span>
          </div>
        </div>
      </div>

      <div class="flex justify-between items-center p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-sm font-bold">
        <span class="text-white">Total Tagihan</span>
        <span class="text-emerald-400 text-base">{{ formatCurrency(selectedFnbDetail.total_amount) }}</span>
      </div>

      <div v-if="selectedFnbDetail.notes" class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-xs">
        <span class="text-slate-400 font-medium block mb-1">Catatan:</span>
        <p class="text-slate-200">{{ selectedFnbDetail.notes }}</p>
      </div>

      <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-800">
        <button type="button" @click="openEditNotesFnb(selectedFnbDetail); showFnbDetailModal = false"
          class="text-xs px-3 py-2 rounded-xl text-blue-400 bg-blue-500/15 border border-blue-500/30 hover:bg-blue-500/25 flex items-center gap-1.5">
          <StickyNote :size="13" /> Edit Catatan
        </button>
        <button type="button" @click="showFnbDetailModal = false"
          class="text-xs px-3 py-2 rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700">
          Tutup
        </button>
      </div>
    </div>
  </GamingModal>

  <!-- Edit Catatan Modal -->
  <GamingModal :show="showNotesModal" :title="`Edit Catatan – ${targetType === 'rental' ? editingRental?.transaction_code : editingFnb?.code}`" @close="showNotesModal = false">
    <form @submit.prevent="saveNotes" class="space-y-4">
      <div>
        <label class="block text-xs font-medium mb-1.5 text-slate-300">
          Catatan Transaksi untuk <strong class="text-white">{{ targetType === 'rental' ? (editingRental?.customer_name || 'Customer') : (editingFnb?.customer_name || 'Customer') }}</strong>
        </label>
        <textarea v-model="notesForm.notes" rows="4"
          class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none resize-none"
          style="background:#12121a; border:1px solid #2a2a3a;"
          placeholder="Tuliskan catatan transaksi di sini..." />
      </div>
      <div class="flex gap-3 justify-end pt-1">
        <button type="button" @click="showNotesModal = false"
          class="px-4 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">
          Batal
        </button>
        <button type="submit" :disabled="notesForm.processing"
          class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-50"
          style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
          Simpan Catatan
        </button>
      </div>
    </form>
  </GamingModal>

  <!-- Hapus Transaksi Modal -->
  <GamingModal :show="showDeleteModal" title="Hapus Riwayat Transaksi?" @close="showDeleteModal = false">
    <div class="space-y-4">
      <p class="text-sm" style="color:#94a3b8;">
        Apakah Anda yakin ingin menghapus data transaksi
        <strong class="text-white">{{ targetType === 'rental' ? deletingRental?.transaction_code : deletingFnb?.code }}</strong>?
        Data transaksi akan dihapus dari riwayat.
      </p>

      <div class="flex gap-3 pt-2">
        <button type="button" @click="showDeleteModal = false"
          class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
          Batal
        </button>
        <button type="button" @click="confirmDelete" :disabled="isDeleting"
          class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-60 flex items-center justify-center gap-1.5"
          style="background:linear-gradient(135deg,#ef4444,#dc2626);">
          <Trash2 :size="15" /> Ya, Hapus
        </button>
      </div>
    </div>
  </GamingModal>

  <!-- Export Modal -->
  <Teleport to="body">
    <div v-if="showExportModal"
      class="fixed inset-0 z-50 flex items-center justify-center"
      style="background:rgba(0,0,0,.65); backdrop-filter:blur(4px);"
      @click.self="showExportModal = false">
      <div class="rounded-2xl p-6 w-full max-w-sm mx-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <!-- Modal header -->
        <div class="flex items-center justify-between mb-5">
          <div class="flex items-center gap-2">
            <Download :size="16" style="color:#10b981;" />
            <span class="font-semibold text-white">Export Excel</span>
          </div>
          <button @click="showExportModal = false" class="p-1 rounded-lg hover:bg-white/10" style="color:#64748b;">
            <X :size="16" />
          </button>
        </div>

        <!-- Period type selector -->
        <p class="text-xs font-medium mb-2" style="color:#94a3b8;">Pilih Periode</p>
        <div class="grid grid-cols-2 gap-2 mb-4">
          <button v-for="t in exportTypes" :key="t"
            @click="exportType = t"
            class="py-2 px-3 rounded-xl text-xs font-medium transition-all"
            :style="exportType === t
              ? 'background:rgba(16,185,129,.2); border:1px solid rgba(16,185,129,.5); color:#10b981;'
              : 'background:#12121a; border:1px solid #2a2a3a; color:#64748b;'">
            {{ t === 'all' ? 'Semua Data' : t === 'year' ? 'Per Tahun' : t === 'month' ? 'Per Bulan' : 'Per Hari' }}
          </button>
        </div>

        <!-- Per Tahun -->
        <div v-if="exportType === 'year'" class="mb-4">
          <p class="text-xs mb-1" style="color:#94a3b8;">Tahun</p>
          <select v-model="exportYear"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <!-- Per Bulan -->
        <div v-if="exportType === 'month'" class="mb-4">
          <p class="text-xs mb-1" style="color:#94a3b8;">Bulan</p>
          <input v-model="exportMonth" type="month"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a; color-scheme:dark;" />
        </div>

        <!-- Per Hari -->
        <div v-if="exportType === 'day'" class="mb-4">
          <p class="text-xs mb-1" style="color:#94a3b8;">Tanggal</p>
          <input v-model="exportDate" type="date"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a; color-scheme:dark;" />
        </div>

        <!-- Actions -->
        <div class="flex gap-2 mt-2">
          <button @click="showExportModal = false"
            class="flex-1 py-2 rounded-xl text-sm font-medium transition-all"
            style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
            Batal
          </button>
          <button @click="doExport"
            class="flex-1 flex items-center justify-center gap-2 py-2 rounded-xl text-sm font-medium text-white transition-all hover:opacity-90"
            style="background:linear-gradient(135deg,#10b981,#059669);">
            <Download :size="14" /> Export
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
