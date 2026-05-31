<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import GamingModal from './GamingModal.vue'
import { CreditCard } from 'lucide-vue-next'

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
  paid_amount: number
  console: { id: number; name: string; type: string }
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
  console: { id: number; name: string; type: string }
  employee: { id: number; name: string }
}

const props = defineProps<{
  initialRentals?: ActiveRental[]
  finishedRentals?: FinishedRental[]
}>()

const rentals = ref<ActiveRental[]>(props.initialRentals ?? [])
const finishedList = ref<FinishedRental[]>(props.finishedRentals ?? [])
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

const statusConfig = {
  running:       { label: 'Running',      bg: 'rgba(16,185,129,.15)', text: '#34d399', border: 'rgba(16,185,129,.4)',  dot: '#34d399' },
  finishing_soon:{ label: 'Hampir Habis', bg: 'rgba(245,158,11,.15)', text: '#fbbf24', border: 'rgba(245,158,11,.4)',  dot: '#fbbf24' },
  overtime:      { label: 'Overtime',     bg: 'rgba(239,68,68,.15)',  text: '#f87171', border: 'rgba(239,68,68,.4)',   dot: '#ef4444' },
}

const typeLabels: Record<string, string> = {
  regular: 'Regular', vip: 'VIP', vvip: 'VVIP', suite: 'Suite',
}

// Live current total with correct pricing
function getLiveTotal(rental: ActiveRental): number {
  if (rental.is_fixed_duration) {
    // Fixed duration: price is set, just add FNB
    return rental.fixed_rental_amount + rental.fnb_amount
  }
  // Open time: ceil to nearest 30-min block, min 30
  const elapsed = getLiveMinutes(rental)
  const billed = Math.ceil(Math.max(elapsed, 1) / 30) * 30
  const pricePerMin = rental.current_total > 0
    ? rental.current_total / Math.max(elapsed, 1)
    : 0
  // Recalculate from console price — use current_total as approximation since console price not in interface
  // Use the server-calculated value directly (it's already correctly billed)
  return rental.current_total
}

// Poll for updates
async function fetchActive() {
  try {
    const { data } = await axios.get(route('dashboard.active-rentals'))
    rentals.value = data
  } catch {}
}

onMounted(() => {
  clockInterval = setInterval(() => { now.value = new Date() }, 1000)
  pollInterval  = setInterval(fetchActive, 10000)
})

onUnmounted(() => {
  clearInterval(clockInterval)
  clearInterval(pollInterval)
})

// Actions
const showFinishConfirm = ref<number | null>(null)

function finishRental(id: number) {
  router.post(route('rentals.finish', id), {}, {
    onSuccess: () => { showFinishConfirm.value = null; fetchActive() }
  })
}
</script>

<template>
  <div class="space-y-8">
    <!-- ===== LIVE ROOM MONITOR ===== -->
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse" />
          <h2 class="font-semibold text-white">Live Room Monitor</h2>
          <span class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(139,92,246,.15); color:#a78bfa;">
            {{ rentals.length }} Aktif
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
          class="rounded-xl p-4 transition-all duration-300"
          :style="`background-color:#1a1a26; border:1px solid ${statusConfig[getLiveStatus(rental)].border};`"
        >
          <!-- Row 1: Console + Status -->
          <div class="flex items-start justify-between gap-2 mb-3">
            <div>
              <p class="font-bold text-white text-sm leading-tight">{{ rental.console.name }}</p>
              <span class="text-xs px-1.5 py-0.5 rounded" style="background:rgba(139,92,246,.15); color:#a78bfa;">
                {{ typeLabels[rental.console.type] ?? rental.console.type }}
              </span>
            </div>
            <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium"
              :style="`background:${statusConfig[getLiveStatus(rental)].bg}; color:${statusConfig[getLiveStatus(rental)].text};`">
              <div class="w-1.5 h-1.5 rounded-full animate-pulse"
                :style="`background:${statusConfig[getLiveStatus(rental)].dot};`" />
              {{ statusConfig[getLiveStatus(rental)].label }}
            </div>
          </div>

          <!-- DP Badge -->
          <div v-if="rental.is_dp" class="flex items-center justify-between mb-3 px-3 py-1.5 rounded-lg text-xs font-medium"
            style="background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.3); color:#fbbf24;">
            <span>💰 DP Dibayar</span>
            <span class="font-bold">{{ formatCurrency(rental.paid_amount) }}</span>
          </div>

          <!-- Customer + Employee -->
          <div class="flex items-center justify-between text-xs mb-3">
            <div>
              <p style="color:#94a3b8;">Customer</p>
              <p class="font-medium text-white">{{ rental.customer_name }}</p>
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
            <div class="text-center py-2 rounded-lg" style="background:#12121a;">
              <p class="text-lg font-mono font-bold"
                :style="`color: ${statusConfig[getLiveStatus(rental)].text};`">
                {{ getRemainingMinutes(rental) !== null ? formatDuration(Number(getRemainingMinutes(rental))) : '∞' }}
              </p>
              <p class="text-xs" style="color:#94a3b8;">Sisa</p>
            </div>
          </div>

          <!-- Total -->
          <div class="flex items-center justify-between mb-3 px-3 py-2 rounded-lg" style="background:#12121a;">
            <span class="text-xs" style="color:#94a3b8;">
              {{ rental.is_fixed_duration ? 'Total (Harga Tetap)' : 'Total Sementara*' }}
            </span>
            <span class="font-bold" style="color:#a78bfa;">{{ formatCurrency(rental.current_total) }}</span>
          </div>
          <p v-if="!rental.is_fixed_duration" class="text-xs mb-2" style="color:#64748b;">
            *Dibulatkan per 10 menit, minimal main 1 jam
          </p>

          <!-- Actions -->
          <div class="flex gap-2">
            <a :href="route('rentals.show', rental.id)"
              class="flex-1 text-center text-xs py-1.5 rounded-lg font-medium transition-colors"
              style="background:rgba(59,130,246,.15); color:#60a5fa; border:1px solid rgba(59,130,246,.3);">
              + Waktu/FNB
            </a>
            <button
              @click="showFinishConfirm = rental.id"
              class="flex-1 text-xs py-1.5 rounded-lg font-medium transition-colors"
              style="background:rgba(239,68,68,.15); color:#f87171; border:1px solid rgba(239,68,68,.3);">
              Selesai
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== MENUNGGU PEMBAYARAN ===== -->
    <div v-if="finishedList.length > 0">
      <div class="flex items-center gap-2 mb-4">
        <div class="w-2 h-2 rounded-full" style="background:#fbbf24;" />
        <h2 class="font-semibold text-white">Menunggu Pembayaran</h2>
        <span class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(251,191,36,.15); color:#fbbf24;">
          {{ finishedList.length }} Selesai
        </span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-for="r in finishedList" :key="r.id"
          class="rounded-xl p-4"
          style="background:#1a1a26; border:1px solid rgba(251,191,36,.4);">
          <div class="flex items-start justify-between gap-2 mb-3">
            <div>
              <p class="font-bold text-white text-sm">{{ r.console.name }}</p>
              <p class="text-xs" style="color:#94a3b8;">{{ r.transaction_code }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
              style="background:rgba(251,191,36,.15); color:#fbbf24; border:1px solid rgba(251,191,36,.4);">
              Selesai
            </span>
          </div>

          <div class="flex items-center justify-between text-xs mb-3">
            <div>
              <p style="color:#94a3b8;">Customer</p>
              <p class="font-medium text-white">{{ r.customer_name }}</p>
            </div>
            <div class="text-right">
              <p style="color:#94a3b8;">Operator</p>
              <p class="font-medium text-white">{{ r.employee.name }}</p>
            </div>
          </div>

          <div class="px-3 py-2 rounded-lg mb-3 text-xs space-y-1.5" style="background:#12121a;">
            <div class="flex justify-between">
              <span style="color:#94a3b8;">Total Tagihan</span>
              <span class="font-bold text-white">{{ formatCurrency(r.total_amount) }}</span>
            </div>
            <div v-if="r.paid_amount > 0" class="flex justify-between">
              <span style="color:#94a3b8;">Sudah Dibayar</span>
              <span style="color:#34d399;">{{ formatCurrency(r.paid_amount) }}</span>
            </div>
            <div class="flex justify-between pt-1" style="border-top:1px solid #2a2a3a;">
              <span style="color:#94a3b8;">Sisa</span>
              <span class="font-bold" style="color:#f87171;">{{ formatCurrency(r.remaining_amount) }}</span>
            </div>
          </div>

          <a :href="route('rentals.payment', r.id)"
            class="w-full flex items-center justify-center gap-2 py-2 rounded-xl text-sm font-medium text-white"
            style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            <CreditCard :size="14" /> Bayar Sekarang
          </a>
        </div>
      </div>
    </div>

    <!-- Finish Confirm Modal -->
    <GamingModal :show="showFinishConfirm !== null" title="Konfirmasi Selesai" @close="showFinishConfirm = null">
      <p class="text-slate-300 mb-6">Yakin ingin mengakhiri rental ini? Sistem akan menghitung total otomatis.</p>
      <div class="flex gap-3 justify-end">
        <button @click="showFinishConfirm = null"
          class="px-4 py-2 rounded-lg text-sm" style="background:#2a2a3a; color:#e2e8f0;">
          Batal
        </button>
        <button @click="showFinishConfirm && finishRental(showFinishConfirm)"
          class="px-4 py-2 rounded-lg text-sm font-medium"
          style="background:linear-gradient(135deg,#ef4444,#dc2626); color:white;">
          Ya, Selesaikan
        </button>
      </div>
    </GamingModal>
  </div>
</template>
