<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import GamingModal from './GamingModal.vue'
import {
  CreditCard, ArrowLeftRight, StickyNote, PlusCircle,
  CheckCircle2, Clock
} from 'lucide-vue-next'

interface ActiveRental {
  id: number
  transaction_code: string
  customer_name: string
  started_at: string
  scheduled_end_at: string | null
  duration_minutes: number
  remaining_minutes: number | null
  live_status: 'running' | 'finishing_soon' | 'overtime'
  current_total: number
  is_fixed_duration: boolean
  fixed_rental_amount: number
  is_dp: boolean
  is_paid?: boolean
  paid_amount: number
  notes: string | null
  console: { id: number; name: string; type: string; price_per_hour: number }
  employee: { id: number; name: string }
  fnb_amount: number
}

interface FinishedRental {
  id: number
  transaction_code: string
  customer_name: string
  ended_at: string
  total_amount: number
  paid_amount: number
  remaining_amount: number
  notes: string | null
  console: { id: number; name: string; type: string; price_per_hour: number }
  employee: { id: number; name: string }
}

const props = defineProps<{
  initialRentals?: ActiveRental[]
  finishedRentals?: FinishedRental[]
  initialAvailableConsoles?: any[]
}>()

const rentals = ref<ActiveRental[]>(props.initialRentals ?? [])
const finishedList = ref<FinishedRental[]>(props.finishedRentals ?? [])
const availableConsoles = ref<any[]>(props.initialAvailableConsoles ?? [])

const now = ref(new Date())
let clockInterval: ReturnType<typeof setInterval>
let pollInterval: ReturnType<typeof setInterval>

function formatDuration(minutes: number): string {
  const h = Math.floor(Math.abs(minutes) / 60)
  const m = Math.abs(minutes) % 60
  const sign = minutes < 0 ? '+' : ''
  return `${sign}${h > 0 ? h + 'j ' : ''}${m}m`
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value)
}

function formatTime(d: string) {
  return new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function getLiveMinutes(rental: ActiveRental): number {
  const start = new Date(rental.started_at)
  return Math.floor((now.value.getTime() - start.getTime()) / 60000)
}

function getRemainingMinutes(rental: ActiveRental): number | null {
  if (!rental.scheduled_end_at) return null
  const end = new Date(rental.scheduled_end_at)
  return Math.floor((end.getTime() - now.value.getTime()) / 60000)
}

function getLiveStatus(rental: ActiveRental): 'running' | 'finishing_soon' | 'overtime' {
  const rem = getRemainingMinutes(rental)
  if (rem === null) return 'running'
  if (rem < 0) return 'overtime'
  if (rem <= 15) return 'finishing_soon'
  return 'running'
}

// Perhitungan 2 Alert:
// 1. Overtime: console waktu tetap yang waktunya habis (remaining_minutes < 0)
const overtimeRentals = computed(() => {
  return rentals.value.filter(r => r.scheduled_end_at && getRemainingMinutes(r) !== null && (getRemainingMinutes(r) as number) < 0)
})
const overtimeCount = computed(() => overtimeRentals.value.length)

// 2. Menunggu Pembayaran: console selesai yang belum lunas
const pendingPaymentCount = computed(() => finishedList.value.length)

const statusConfig = {
  running:       { label: 'Running',      bg: 'rgba(16,185,129,.15)', text: '#34d399', border: 'rgba(16,185,129,.4)',  dot: '#34d399' },
  finishing_soon:{ label: 'Hampir Habis', bg: 'rgba(245,158,11,.15)', text: '#fbbf24', border: 'rgba(245,158,11,.4)',  dot: '#fbbf24' },
  overtime:      { label: 'Overtime',     bg: 'rgba(239,68,68,.25)',  text: '#f87171', border: '#ef4444',             dot: '#ef4444' },
}

const typeLabels: Record<string, string> = {
  regular: 'Regular', vip: 'VIP', vvip: 'VVIP', suite: 'Suite',
}

// Poll for updates (monitor-data returns active, finished, and available consoles)
async function fetchActive() {
  try {
    const { data } = await axios.get(route('dashboard.monitor-data'))
    rentals.value = data.activeRentals
    finishedList.value = data.finishedRentals
    availableConsoles.value = data.availableConsoles
  } catch {
    try {
      const { data } = await axios.get(route('dashboard.active-rentals'))
      rentals.value = data
    } catch {}
  }
}

onMounted(() => {
  clockInterval = setInterval(() => { now.value = new Date() }, 1000)
  pollInterval  = setInterval(fetchActive, 10000)
})

onUnmounted(() => {
  clearInterval(clockInterval)
  clearInterval(pollInterval)
})

// Actions: Selesai
const showFinishConfirm = ref<ActiveRental | null>(null)

function finishRental(rental: ActiveRental) {
  router.post(route('rentals.finish', rental.id), {}, {
    onSuccess: () => {
      showFinishConfirm.value = null
      fetchActive()
    }
  })
}

// Actions: Switch Ruangan / Console
const showSwitchModal = ref<ActiveRental | null>(null)
const switchForm = useForm({
  new_console_id: null as number | null,
})

function openSwitch(rental: ActiveRental) {
  showSwitchModal.value = rental
  switchForm.new_console_id = null
}

function submitSwitch() {
  if (!showSwitchModal.value || !switchForm.new_console_id) return
  switchForm.post(route('rentals.switch-console', showSwitchModal.value.id), {
    onSuccess: () => {
      showSwitchModal.value = null
      fetchActive()
    }
  })
}

// Actions: Catatan Console
const showNotesModal = ref<ActiveRental | null>(null)
const notesForm = useForm({
  notes: '',
})

function openNotes(rental: ActiveRental) {
  showNotesModal.value = rental
  notesForm.notes = rental.notes || ''
}

function submitNotes() {
  if (!showNotesModal.value) return
  notesForm.patch(route('rentals.update-notes', showNotesModal.value.id), {
    onSuccess: () => {
      showNotesModal.value = null
      fetchActive()
    }
  })
}

// Actions: Tambah Waktu saat Menunggu Pembayaran (Resume)
const showResumeModal = ref<FinishedRental | null>(null)
const resumeForm = useForm({
  added_minutes: 60,
  notes: '',
})

const resumeDurationChips = [
  { label: '30 Menit', val: 30 },
  { label: '1 Jam', val: 60 },
  { label: '1.5 Jam', val: 90 },
  { label: '2 Jam', val: 120 },
  { label: '3 Jam', val: 180 },
]

function openResume(rental: FinishedRental) {
  showResumeModal.value = rental
  resumeForm.added_minutes = 60
  resumeForm.notes = ''
}

const resumeEstimatedPrice = computed(() => {
  if (!showResumeModal.value || !showResumeModal.value.console) return 0
  const rate = showResumeModal.value.console.price_per_hour || 0
  return Math.round((rate / 60) * resumeForm.added_minutes)
})

function submitResume() {
  if (!showResumeModal.value) return
  resumeForm.post(route('rentals.resume-time', showResumeModal.value.id), {
    onSuccess: () => {
      showResumeModal.value = null
      fetchActive()
    }
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- ===== DUA SISTEM ALERT ===== -->
    <div class="space-y-3">
      <!-- 1. ALERT MERAH: WAKTU TETAP HABIS (OVERTIME) -->
      <div v-if="overtimeCount > 0"
        class="p-4 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xl transition-all"
        style="background: linear-gradient(135deg, rgba(239,68,68,0.22), rgba(185,28,28,0.35)); border: 2px solid #ef4444; box-shadow: 0 0 25px rgba(239,68,68,0.25);">
        <div class="flex items-center gap-3">
          <div class="w-3.5 h-3.5 rounded-full bg-red-500 animate-ping shrink-0" />
          <div>
            <h3 class="text-sm font-black text-white flex items-center gap-2">
              <span>⚠️ Peringatan: Ada {{ overtimeCount }} Console Waktu Tetap yang OVERTIME!</span>
              <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-red-600 text-white animate-pulse">
                {{ overtimeCount }} Overtime
              </span>
            </h3>
            <p class="text-xs text-red-100 mt-0.5">
              Waktu sewa telah habis dan console sedang overtime. Overtime hanya sebagai notifikasi (tidak menambah biaya sewa).
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-950/60 text-red-300 border border-red-500/40">
            Harap konfirmasi ke customer atau klik "Selesai"
          </span>
        </div>
      </div>

      <!-- 2. ALERT KUNING: MENUNGGU PEMBAYARAN KASIR -->
      <div v-if="pendingPaymentCount > 0"
        class="p-4 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg transition-all"
        style="background: linear-gradient(135deg, rgba(245,158,11,0.18), rgba(217,119,6,0.28)); border: 1.5px solid rgba(245,158,11,0.6); box-shadow: 0 0 20px rgba(245,158,11,0.15);">
        <div class="flex items-center gap-3">
          <div class="w-3 h-3 rounded-full bg-amber-400 animate-ping shrink-0" />
          <div>
            <h3 class="text-sm font-bold text-amber-200 flex items-center gap-2">
              <span>💳 Menunggu Pembayaran: Ada {{ pendingPaymentCount }} Console Belum Lunas</span>
              <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-400 text-black">
                {{ pendingPaymentCount }} Tagihan
              </span>
            </h3>
            <p class="text-xs text-amber-100/90 mt-0.5">
              Console telah selesai bermain dan menunggu pelunasan tagihan pembayaran kasir.
            </p>
          </div>
        </div>
        <a href="#menunggu-pembayaran"
          class="px-4 py-2 rounded-xl text-xs font-bold text-black text-center transition-all bg-amber-400 hover:bg-amber-300 shrink-0 shadow-md">
          Ke Kasir Pembayaran ↓
        </a>
      </div>
    </div>

    <!-- ===== LIVE ROOM MONITOR ===== -->
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <div class="w-2.5 h-2.5 rounded-full bg-green-400 animate-pulse" />
          <h2 class="font-semibold text-white">Live Room Monitor</h2>
          <span class="text-xs px-2.5 py-0.5 rounded-full font-medium" style="background:rgba(139,92,246,.15); color:#a78bfa;">
            {{ rentals.length }} Aktif
          </span>
          <span v-if="overtimeCount > 0" class="text-xs px-2 py-0.5 rounded-full font-extrabold bg-red-600 text-white animate-pulse">
            {{ overtimeCount }} Overtime
          </span>
        </div>
        <span class="text-xs font-mono" style="color:#94a3b8;">
          {{ now.toLocaleTimeString('id-ID') }}
        </span>
      </div>

      <!-- Empty state -->
      <div v-if="rentals.length === 0"
        class="rounded-xl p-12 text-center"
        style="background-color:#1a1a26; border:1px dashed #2a2a3a;">
        <p class="text-4xl mb-3">🎮</p>
        <p class="text-white font-medium">Semua Room Kosong</p>
        <p class="text-sm mt-1" style="color:#94a3b8;">Belum ada rental yang sedang berjalan</p>
      </div>

      <!-- Room Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="rental in rentals" :key="rental.id"
          class="rounded-xl p-4 transition-all duration-300 flex flex-col justify-between"
          :style="getLiveStatus(rental) === 'overtime'
            ? 'background: linear-gradient(180deg, rgba(239,68,68,0.22) 0%, #1a1a26 40%); border: 2px solid #ef4444; box-shadow: 0 0 25px rgba(239,68,68,0.35);'
            : `background-color:#1a1a26; border:1px solid ${statusConfig[getLiveStatus(rental)].border};`"
        >
          <div>
            <!-- Row 1: Console + Status -->
            <div class="flex items-start justify-between gap-2 mb-3">
              <div>
                <p class="font-bold text-white text-base leading-tight">{{ rental.console.name }}</p>
                <span class="text-xs px-1.5 py-0.5 rounded mt-0.5 inline-block" style="background:rgba(139,92,246,.15); color:#a78bfa;">
                  {{ typeLabels[rental.console.type] ?? rental.console.type }}
                </span>
              </div>
              <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold"
                :style="`background:${statusConfig[getLiveStatus(rental)].bg}; color:${statusConfig[getLiveStatus(rental)].text};`">
                <div class="w-1.5 h-1.5 rounded-full animate-pulse"
                  :style="`background:${statusConfig[getLiveStatus(rental)].dot};`" />
                {{ statusConfig[getLiveStatus(rental)].label }}
              </div>
            </div>

            <!-- PERINGATAN MENCOLOK JIKA OVERTIME -->
            <div v-if="getLiveStatus(rental) === 'overtime'"
              class="mb-3 px-3 py-2.5 rounded-xl flex items-center gap-2.5 bg-red-600/30 border border-red-500 animate-pulse text-red-200 shadow-md">
              <span class="text-lg shrink-0">⚠️</span>
              <div class="text-xs leading-tight flex-1">
                <div class="flex items-center justify-between">
                  <span class="font-black text-red-300 uppercase tracking-wide">WAKTU SEWA HABIS!</span>
                  <span class="font-black text-white bg-red-600 px-2 py-0.5 rounded text-[11px]">
                    +{{ formatDuration(Math.abs(getRemainingMinutes(rental) ?? 0)) }}
                  </span>
                </div>
                <p class="text-[11px] text-slate-300 mt-1">
                  Console sedang overtime. Harap konfirmasi ke customer atau tekan tombol "Selesai" di bawah.
                </p>
              </div>
            </div>

            <!-- Status Bayar Awal / Lunas -->
            <div v-if="rental.is_paid" class="flex items-center justify-between mb-3 px-3 py-1.5 rounded-lg text-xs font-semibold"
              style="background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.4); color:#34d399;">
              <span class="flex items-center gap-1.5">
                <CheckCircle2 :size="14" /> Sudah Bayar di Awal (Lunas)
              </span>
              <span>{{ formatCurrency(rental.paid_amount) }}</span>
            </div>

            <!-- DP Badge -->
            <div v-else-if="rental.is_dp" class="flex items-center justify-between mb-3 px-3 py-1.5 rounded-lg text-xs font-medium"
              style="background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.3); color:#fbbf24;">
              <span>💰 DP Dibayar</span>
              <span class="font-bold">{{ formatCurrency(rental.paid_amount) }}</span>
            </div>

            <!-- Catatan Console (jika ada) -->
            <div v-if="rental.notes" class="mb-3 px-3 py-2 rounded-lg text-xs"
              style="background:rgba(59,130,246,.1); border:1px dashed rgba(59,130,246,.3); color:#93c5fd;">
              <div class="flex items-center justify-between mb-0.5">
                <span class="font-semibold flex items-center gap-1 text-blue-400">
                  <StickyNote :size="12" /> Catatan Room:
                </span>
                <button @click="openNotes(rental)" class="text-[11px] underline hover:text-white">Ubah</button>
              </div>
              <p class="text-slate-200 line-clamp-2">{{ rental.notes }}</p>
            </div>

            <!-- Customer + Employee -->
            <div class="flex items-center justify-between text-xs mb-3">
              <div>
                <p style="color:#94a3b8;">Customer</p>
                <p class="font-medium text-white">{{ rental.customer_name || 'Customer' }}</p>
              </div>
              <div class="text-right">
                <p style="color:#94a3b8;">Operator</p>
                <p class="font-medium text-white">{{ rental.employee.name }}</p>
              </div>
            </div>

            <!-- Rental type info -->
            <div class="text-xs mb-3 px-3 py-2 rounded-lg" style="background:#12121a;">
              <div class="flex justify-between">
                <span style="color:#94a3b8;">Jenis</span>
                <span class="text-white font-medium">
                  {{ rental.is_fixed_duration ? 'Waktu Tetap' : 'Open Time' }}
                </span>
              </div>
              <div class="flex justify-between mt-1">
                <span style="color:#94a3b8;">Mulai</span>
                <span class="text-white">{{ formatTime(rental.started_at) }}</span>
              </div>
              <div v-if="rental.is_fixed_duration" class="flex justify-between mt-1">
                <span style="color:#94a3b8;">Harga Tetap</span>
                <span class="font-semibold" style="color:#34d399;">{{ formatCurrency(rental.fixed_rental_amount) }}</span>
              </div>
            </div>

            <!-- Timer -->
            <div class="grid grid-cols-2 gap-2 mb-3">
              <div class="text-center py-2 rounded-lg" style="background:#12121a;">
                <p class="text-lg font-mono font-bold text-white">{{ formatDuration(getLiveMinutes(rental)) }}</p>
                <p class="text-xs" style="color:#94a3b8;">Durasi</p>
              </div>
              <div class="text-center py-2 rounded-lg transition-all"
                :style="getLiveStatus(rental) === 'overtime'
                  ? 'background: rgba(239,68,68,0.25); border: 1.5px solid #ef4444;'
                  : 'background:#12121a;'">
                <p class="text-lg font-mono font-bold"
                  :class="{ 'animate-pulse': getLiveStatus(rental) === 'overtime' }"
                  :style="`color: ${statusConfig[getLiveStatus(rental)].text};`">
                  {{ getRemainingMinutes(rental) !== null ? formatDuration(Number(getRemainingMinutes(rental))) : '∞' }}
                </p>
                <p class="text-xs font-bold" :style="getLiveStatus(rental) === 'overtime' ? 'color:#f87171;' : 'color:#94a3b8;'">
                  {{ getLiveStatus(rental) === 'overtime' ? 'OVERTIME' : 'Sisa' }}
                </p>
              </div>
            </div>

            <!-- Total -->
            <div class="flex items-center justify-between mb-3 px-3 py-2 rounded-lg" style="background:#12121a;">
              <span class="text-xs" style="color:#94a3b8;">
                {{ rental.is_fixed_duration ? 'Total (Harga Tetap)' : 'Total Sementara*' }}
              </span>
              <span class="font-bold" style="color:#a78bfa;">{{ formatCurrency(rental.current_total) }}</span>
            </div>
            <p v-if="!rental.is_fixed_duration" class="text-xs mb-3" style="color:#64748b;">
              *Dibulatkan per 10 menit, minimal main 1 jam
            </p>
          </div>

          <!-- Actions -->
          <div class="space-y-2 pt-2 border-t border-[#2a2a3a]">
            <!-- Action Bar Row 1: Fast Utilities -->
            <div class="grid grid-cols-3 gap-1.5 text-xs">
              <a :href="route('rentals.show', rental.id)"
                class="text-center py-1.5 px-2 rounded-lg font-medium transition-colors hover:opacity-80 flex items-center justify-center gap-1"
                style="background:rgba(59,130,246,.15); color:#60a5fa; border:1px solid rgba(59,130,246,.3);">
                <span>+ FnB</span>
              </a>

              <button @click="openSwitch(rental)"
                class="py-1.5 px-2 rounded-lg font-medium transition-colors hover:opacity-80 flex items-center justify-center gap-1"
                style="background:rgba(168,85,247,.15); color:#c084fc; border:1px solid rgba(168,85,247,.3);"
                title="Pindah Ruangan / Console">
                <ArrowLeftRight :size="12" />
                <span>Pindah</span>
              </button>

              <button @click="openNotes(rental)"
                class="py-1.5 px-2 rounded-lg font-medium transition-colors hover:opacity-80 flex items-center justify-center gap-1"
                style="background:rgba(234,179,8,.15); color:#facc15; border:1px solid rgba(234,179,8,.3);"
                title="Catatan Console">
                <StickyNote :size="12" />
                <span>Catatan</span>
              </button>
            </div>

            <!-- Action Bar Row 2: Selesai (Tetap ada untuk semua termasuk bayar di awal) -->
            <button
              @click="showFinishConfirm = rental"
              class="w-full text-xs py-2.5 rounded-lg font-bold transition-all flex items-center justify-center gap-1.5 shadow-md hover:opacity-95"
              :style="getLiveStatus(rental) === 'overtime'
                ? 'background:linear-gradient(135deg,#ef4444,#dc2626); color:white; box-shadow:0 4px 15px rgba(239,68,68,0.4);'
                : 'background:linear-gradient(135deg,rgba(239,68,68,.25),rgba(220,38,38,.35)); color:#f87171; border:1px solid rgba(239,68,68,.4);'">
              <span>{{ getLiveStatus(rental) === 'overtime' ? '⚠️ Selesaikan Rental (Overtime)' : 'Akhiri Sesi / Selesai' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MENUNGGU PEMBAYARAN ===== -->
    <div id="menunggu-pembayaran" v-if="finishedList.length > 0">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-full bg-amber-400 animate-ping" />
          <h2 class="font-semibold text-white">Menunggu Pembayaran</h2>
          <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-amber-400 text-black shadow-md shadow-amber-400/30">
            {{ finishedList.length }} Selesai
          </span>
        </div>
        <p class="text-xs text-slate-400">Pilih "Bayar Sekarang" untuk pelunasan atau "+ Tambah Waktu" jika ingin lanjut bermain</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-for="r in finishedList" :key="r.id"
          class="rounded-xl p-4 flex flex-col justify-between"
          style="background:#1a1a26; border:1px solid rgba(245,158,11,.45); box-shadow: 0 4px 20px rgba(245,158,11,.08);">
          <div>
            <div class="flex items-start justify-between gap-2 mb-3">
              <div>
                <p class="font-bold text-white text-base">{{ r.console.name }}</p>
                <p class="text-xs" style="color:#94a3b8;">{{ r.transaction_code }}</p>
              </div>
              <span class="text-xs px-2.5 py-0.5 rounded-full font-bold"
                style="background:rgba(245,158,11,.15); color:#fbbf24; border:1px solid rgba(245,158,11,.4);">
                Selesai
              </span>
            </div>

            <div class="flex items-center justify-between text-xs mb-3">
              <div>
                <p style="color:#94a3b8;">Customer</p>
                <p class="font-medium text-white">{{ r.customer_name || 'Customer' }}</p>
              </div>
              <div class="text-right">
                <p style="color:#94a3b8;">Operator</p>
                <p class="font-medium text-white">{{ r.employee.name }}</p>
              </div>
            </div>

            <!-- Notes jika ada -->
            <div v-if="r.notes" class="mb-3 px-3 py-1.5 rounded-lg text-xs" style="background:#12121a; color:#94a3b8;">
              <span class="text-slate-400">Catatan:</span> {{ r.notes }}
            </div>

            <div class="px-3 py-2.5 rounded-lg mb-3 text-xs space-y-1.5" style="background:#12121a;">
              <div class="flex justify-between">
                <span style="color:#94a3b8;">Total Tagihan</span>
                <span class="font-bold text-white">{{ formatCurrency(r.total_amount) }}</span>
              </div>
              <div v-if="r.paid_amount > 0" class="flex justify-between">
                <span style="color:#94a3b8;">Sudah Dibayar</span>
                <span style="color:#34d399;">{{ formatCurrency(r.paid_amount) }}</span>
              </div>
              <div class="flex justify-between pt-1" style="border-top:1px solid #2a2a3a;">
                <span style="color:#94a3b8;">Sisa Pembayaran</span>
                <span class="font-bold text-sm" style="color:#f87171;">{{ formatCurrency(r.remaining_amount) }}</span>
              </div>
            </div>
          </div>

          <!-- Actions: Bayar & Tambah Waktu (Lanjut Main) -->
          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-[#2a2a3a]">
            <!-- Tambah Waktu Kembali -->
            <button @click="openResume(r)"
              class="flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-semibold transition-all hover:opacity-90"
              style="background:rgba(59,130,246,.15); color:#60a5fa; border:1px solid rgba(59,130,246,.35);"
              title="Customer ingin lanjut main lagi">
              <PlusCircle :size="14" /> + Tambah Waktu
            </button>

            <!-- Bayar Sekarang -->
            <a :href="route('rentals.payment', r.id)"
              class="flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-semibold text-black transition-all hover:opacity-90 font-bold"
              style="background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow: 0 2px 10px rgba(245,158,11,.2);">
              <CreditCard :size="14" /> Bayar Sekarang
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MODAL KONFIRMASI SELESAI ===== -->
    <GamingModal :show="showFinishConfirm !== null" title="Konfirmasi Selesai Rental" @close="showFinishConfirm = null">
      <div v-if="showFinishConfirm" class="space-y-4">
        <p class="text-slate-300 text-sm">
          Apakah yakin ingin mengakhiri sesi bermain untuk console
          <strong class="text-white">{{ showFinishConfirm.console.name }}</strong> (Customer: {{ showFinishConfirm.customer_name }})?
        </p>

        <!-- Keterangan khusus jika bayar awal -->
        <div v-if="showFinishConfirm.is_paid" class="p-3 rounded-xl text-xs bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">
          ✓ <strong>Rental ini sudah lunas bayar di awal.</strong> Begitu diselesaikan, console akan langsung berstatus <em>available</em> kembali tanpa tagihan pembayaran.
        </div>
        <!-- Keterangan khusus jika overtime -->
        <div v-else-if="getLiveStatus(showFinishConfirm) === 'overtime'" class="p-3 rounded-xl text-xs bg-red-500/15 border border-red-500/40 text-red-200">
          ⚠️ <strong>Console dalam status overtime.</strong> Total tagihan tetap sesuai durasi yang dipesan (overtime tidak dikenakan biaya). Rental akan dialihkan ke daftar <strong>Menunggu Pembayaran</strong> untuk pelunasan.
        </div>
        <div v-else class="p-3 rounded-xl text-xs bg-amber-500/10 border border-amber-500/30 text-amber-300">
          ℹ Total tagihan akan dihitung otomatis dan console akan dialihkan ke daftar <strong>Menunggu Pembayaran</strong>.
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button @click="showFinishConfirm = null"
            class="px-4 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">
            Batal
          </button>
          <button @click="showFinishConfirm && finishRental(showFinishConfirm)"
            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-lg shadow-red-500/20"
            style="background:linear-gradient(135deg,#ef4444,#dc2626);">
            Ya, Selesaikan
          </button>
        </div>
      </div>
    </GamingModal>

    <!-- ===== MODAL SWITCH CONSOLE / RUANGAN ===== -->
    <GamingModal :show="showSwitchModal !== null" title="Pindah Ruangan / Console" @close="showSwitchModal = null">
      <form @submit.prevent="submitSwitch" v-if="showSwitchModal" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1 text-slate-400">Ruangan Saat Ini</label>
          <div class="p-3 rounded-xl text-sm font-semibold text-white bg-[#12121a] border border-[#2a2a3a]">
            {{ showSwitchModal.console.name }} ({{ typeLabels[showSwitchModal.console.type] ?? showSwitchModal.console.type }})
            <span class="text-xs text-slate-400 font-normal ml-2">— Customer: {{ showSwitchModal.customer_name }}</span>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium mb-1 text-slate-400">Pilih Ruangan Tujuan (Hanya yang Tersedia)</label>
          <select v-model.number="switchForm.new_console_id" required
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option :value="null">-- Pilih Ruangan Tersedia --</option>
            <option v-for="c in availableConsoles" :key="c.id" :value="c.id">
              {{ c.name }} ({{ typeLabels[c.type] ?? c.type }}) — {{ formatCurrency(c.price_per_hour) }}/jam
            </option>
          </select>
          <p v-if="availableConsoles.length === 0" class="text-xs text-amber-400 mt-1.5">
            ⚠️ Saat ini tidak ada ruangan lain yang available (kosong).
          </p>
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="showSwitchModal = null"
            class="px-4 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">
            Batal
          </button>
          <button type="submit" :disabled="!switchForm.new_console_id || switchForm.processing"
            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-50"
            style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">
            Pindahkan Sekarang
          </button>
        </div>
      </form>
    </GamingModal>

    <!-- ===== MODAL CATATAN CONSOLE ===== -->
    <GamingModal :show="showNotesModal !== null" title="Catatan Console / Rental" @close="showNotesModal = null">
      <form @submit.prevent="submitNotes" v-if="showNotesModal" class="space-y-4">
        <div>
          <p class="text-xs text-slate-400 mb-2">
            Catatan untuk <strong class="text-white">{{ showNotesModal.console.name }}</strong> (misal: "Stik 2 baterai lemah", "Minta dipanggil jam 16:00", dll):
          </p>
          <textarea v-model="notesForm.notes" rows="4"
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none resize-none"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="Ketik catatan di sini..." />
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="showNotesModal = null"
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

    <!-- ===== MODAL TAMBAH WAKTU SAAT SELESAI (RESUME) ===== -->
    <GamingModal :show="showResumeModal !== null" title="Lanjut Bermain / Tambah Waktu" @close="showResumeModal = null">
      <form @submit.prevent="submitResume" v-if="showResumeModal" class="space-y-4">
        <div class="p-3 rounded-xl text-xs bg-[#12121a] border border-[#2a2a3a] space-y-1">
          <p class="font-bold text-white text-sm">{{ showResumeModal.console.name }} — {{ showResumeModal.customer_name }}</p>
          <p class="text-slate-400">Tarif: {{ formatCurrency(showResumeModal.console.price_per_hour) }}/jam</p>
          <p class="text-emerald-400">Console akan diaktifkan kembali dan timer berjalan dari saat ini.</p>
        </div>

        <div>
          <label class="block text-xs font-medium mb-1.5 text-slate-300">Pilih Durasi Tambahan</label>
          <div class="flex flex-wrap gap-2 mb-2">
            <button v-for="c in resumeDurationChips" :key="c.val" type="button"
              @click="resumeForm.added_minutes = c.val"
              class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
              :style="resumeForm.added_minutes === c.val
                ? 'background:linear-gradient(135deg,#3b82f6,#2563eb); color:white; font-weight:bold;'
                : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
              {{ c.label }}
            </button>
          </div>
          <div class="flex items-center gap-2 mt-2">
            <span class="text-xs text-slate-400">Atau ketik menit:</span>
            <input v-model.number="resumeForm.added_minutes" type="number" min="10" step="10" required
              class="w-24 px-3 py-1.5 rounded-lg text-xs text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
            <span class="text-xs text-slate-400">menit</span>
          </div>
        </div>

        <div class="p-3 rounded-xl bg-purple-500/10 border border-purple-500/25 flex items-center justify-between text-xs">
          <span class="text-purple-300">Estimasi Tambahan Biaya:</span>
          <span class="font-bold text-purple-200 text-sm">{{ formatCurrency(resumeEstimatedPrice) }}</span>
        </div>

        <div>
          <label class="block text-xs font-medium mb-1 text-slate-400">Catatan Tambahan (opsional)</label>
          <input v-model="resumeForm.notes" type="text"
            class="w-full px-3 py-2 rounded-xl text-xs text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="Catatan perpanjangan waktu..." />
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="showResumeModal = null"
            class="px-4 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">
            Batal
          </button>
          <button type="submit" :disabled="resumeForm.processing"
            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-50 flex items-center gap-1.5"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
            <PlusCircle :size="15" /> Aktifkan & Tambah Waktu
          </button>
        </div>
      </form>
    </GamingModal>
  </div>
</template>
