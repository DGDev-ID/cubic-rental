<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Download, TrendingUp, Users, BarChart2, ChevronLeft, ChevronRight, Filter, X, Calendar } from 'lucide-vue-next'

interface RentalRow {
  id: number; transaction_code: string; customer_name: string; rental_type: string;
  status: string; started_at: string; ended_at: string | null; total_amount: number;
  console: { name: string; type: string }
  employee: { name: string }
  payments: { method: string; amount: number }[]
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
  summary_customers: number
  yearly_stats: { year: number; revenue: number; customers: number }[]
  monthly_stats: { month: number; revenue: number; customers: number }[]
  daily_stats: { day: number; revenue: number; customers: number }[]
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

const detail = ref<RentalRow | null>(null)
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
          class="px-4 py-3 rounded-xl text-left transition-all min-w-[90px]"
          :style="isSelectedYear(stat.year)
            ? 'background:rgba(139,92,246,.25); border:1px solid rgba(139,92,246,.5);'
            : 'background:#12121a; border:1px solid #2a2a3a;'">
          <p class="text-xs font-semibold mb-1"
            :style="isSelectedYear(stat.year) ? 'color:#c4b5fd;' : 'color:#64748b;'">{{ stat.year }}</p>
          <p class="text-xs font-bold text-white leading-tight">{{ formatCurrencyShort(stat.revenue) }}</p>
          <p class="text-xs mt-0.5" style="color:#94a3b8;">{{ stat.customers }} cust</p>
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
          <p class="text-xs mt-0.5" style="color:#94a3b8;">{{ stat.customers }} cust</p>
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
          <p class="text-[10px] mt-0.5" style="color:#94a3b8;">{{ stat.customers > 0 ? stat.customers + 'c' : '' }}</p>
        </button>
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
    <div v-if="hasActiveFilter" class="rounded-xl px-4 py-3 mb-4 flex items-center justify-between gap-4"
      style="background:rgba(139,92,246,.08); border:1px solid rgba(139,92,246,.3);">
      <div class="flex items-center gap-2 min-w-0">
        <Filter :size="13" style="color:#a78bfa;" />
        <span class="text-xs truncate" style="color:#94a3b8;">
          {{ filterLabel ? filterLabel : 'Filter aktif' }}
        </span>
      </div>
      <div class="flex items-center gap-5 shrink-0">
        <div class="text-right">
          <p class="text-xs" style="color:#64748b;">Omset</p>
          <p class="text-sm font-bold" style="color:#a78bfa;">{{ formatCurrency(summary_revenue) }}</p>
        </div>
        <div class="text-right">
          <p class="text-xs" style="color:#64748b;">Transaksi</p>
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
            <td class="px-4 py-3 text-white">{{ r.customer_name }}</td>
            <td class="px-4 py-3">
              <span class="text-white">{{ r.console.name }}</span>
              <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full" :style="`background:rgba(99,102,241,.15); color:${typeColors[r.console.type]}`">
                {{ r.console.type }}
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
              <div class="flex gap-2 justify-end">
                <button @click="detail = detail?.id === r.id ? null : r"
                  class="text-xs px-3 py-1.5 rounded-xl transition-all"
                  style="background:rgba(139,92,246,.2); border:1px solid rgba(139,92,246,.3); color:#a78bfa;">
                  Detail
                </button>
                <a :href="route('rentals.receipt', r.id)"
                  class="text-xs px-3 py-1.5 rounded-xl transition-all"
                  style="background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.3); color:#22c55e; text-decoration:none;">
                  Struk
                </a>
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
            <td class="px-4 py-3 text-white">{{ r.customer_name || '-' }}</td>
            <td class="px-4 py-3 text-white">{{ r.console?.name || '-' }}</td>
            <td class="px-4 py-3 text-xs" style="color:#94a3b8;">{{ formatDt(r.paid_at) }}</td>
            <td class="px-4 py-3 font-semibold" style="color:#10b981;">{{ formatCurrency(r.total_amount) }}</td>
            <td class="px-4 py-3">
              <span class="text-xs px-2 py-1 rounded-full" style="background:rgba(34,197,94,.15); color:#22c55e;">
                Lunas
              </span>
            </td>
            <td class="px-4 py-3"></td>
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

    <!-- Detail Drawer -->
    <div v-if="detail" class="mt-4 rounded-xl p-5" style="background:#1a1a26; border:1px solid rgba(139,92,246,.3);">
      <h3 class="text-sm font-semibold text-white mb-3">Detail – {{ detail.transaction_code }}</h3>
      <div class="grid grid-cols-2 gap-y-2 text-sm">
        <div><span style="color:#94a3b8;">Operator</span><p class="text-white">{{ detail.employee.name }}</p></div>
        <div><span style="color:#94a3b8;">Tipe</span><p class="text-white capitalize">{{ detail.rental_type.replace('_', ' ') }}</p></div>
        <div><span style="color:#94a3b8;">Mulai</span><p class="text-white">{{ formatDt(detail.started_at) }}</p></div>
        <div><span style="color:#94a3b8;">Selesai</span><p class="text-white">{{ formatDt(detail.ended_at) }}</p></div>
        <div class="col-span-2 mt-2">
          <span style="color:#94a3b8;">Pembayaran</span>
          <div class="flex flex-wrap gap-2 mt-1">
            <span v-for="p in detail.payments" :key="p.method + p.amount"
              class="text-xs px-2 py-1 rounded-full"
              style="background:rgba(34,197,94,.15); color:#22c55e;">
              {{ p.method }}: {{ formatCurrency(p.amount) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>

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
