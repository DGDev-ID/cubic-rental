<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import {
  ArrowLeft, CreditCard, Clock, Star, Crown, Gem, Zap,
  Wallet, CalendarDays, User, Gamepad2, UtensilsCrossed, ChevronRight,
} from 'lucide-vue-next'

interface Deposit {
  id: number
  created_at: string
  package: { name: string }
  amount: number
  minutes_credited: number
  payment_method: string
  employee: { name: string }
}

interface MemberRental {
  id: number
  transaction_code: string
  started_at: string
  ended_at: string | null
  console: { name: string }
  member_deducted_minutes: number
  fnb_amount: number
  total_amount: number
  status: string
}

const props = defineProps<{
  member: {
    id: number
    member_number: string
    name: string
    phone: string
    console_type: string
    remaining_minutes: number
    status: string
    created_at: string
  }
  deposits: Deposit[]
  rentals: MemberRental[]
  packages: { id: number; name: string; console_type: string; price: number; duration_minutes: number }[]
  employees: { id: number; name: string }[]
}>()

// ─── Top-Up Modal ────────────────────────────────────────────────────────────
const showTopUpModal = ref(false)

const topUpForm = useForm({
  member_id: props.member.id,
  package_id: null as number | null,
  payment_method: 'cash',
  employee_id: null as number | null,
  notes: '',
})

const selectedPackage = computed(() =>
  props.packages.find(p => p.id === topUpForm.package_id) ?? null
)

function openTopUp() {
  topUpForm.reset()
  topUpForm.member_id = props.member.id
  topUpForm.payment_method = 'cash'
  showTopUpModal.value = true
}

function submitTopUp() {
  topUpForm.post(route('members.deposit', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      showTopUpModal.value = false
      topUpForm.reset()
    },
  })
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}

function formatMinutes(m: number) {
  const h = Math.floor(m / 60)
  const min = m % 60
  if (h === 0) return `${min} mnt`
  if (min === 0) return `${h} jam`
  return `${h}j ${min}m`
}

function formatDt(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDuration(start: string, end: string | null) {
  const s = new Date(start).getTime()
  const e = end ? new Date(end).getTime() : Date.now()
  const diff = Math.max(0, Math.floor((e - s) / 60000))
  const h = Math.floor(diff / 60)
  const m = diff % 60
  return h > 0 ? `${h} jam ${m} mnt` : `${m} mnt`
}

const consoleTypeColors: Record<string, string> = {
  regular: '#60a5fa',
  vip: '#a78bfa',
  vvip: '#22d3ee',
  suite: '#fbbf24',
}

const consoleTypeIcons: Record<string, any> = {
  regular: Star,
  vip: Crown,
  vvip: Gem,
  suite: Zap,
}

function typeColor(t: string) {
  return consoleTypeColors[t?.toLowerCase()] ?? '#94a3b8'
}

function typeIcon(t: string) {
  return consoleTypeIcons[t?.toLowerCase()] ?? Star
}

// Remaining minutes progress (based on 720 minutes = 12 jam per paket)
const remainingPercent = computed(() => {
  const pct = Math.min(100, Math.max(0, (props.member.remaining_minutes / 720) * 100))
  return Math.round(pct)
})

const totalDeposited = computed(() =>
  props.deposits.reduce((sum, d) => sum + d.amount, 0)
)

const rentalStatusColors: Record<string, string> = {
  running: '#22d3ee',
  finished: '#22c55e',
  paid: '#a78bfa',
  cancelled: '#ef4444',
  half_paid: '#fbbf24',
}

const rentalStatusLabels: Record<string, string> = {
  running: 'Berlangsung',
  finished: 'Selesai',
  paid: 'Lunas',
  cancelled: 'Dibatalkan',
  half_paid: 'Bayar Sebagian',
}
</script>

<template>
  <AppLayout>
    <template #header-title>
      <div class="flex items-center gap-3">
        <Link
          :href="route('members.index')"
          class="p-1.5 rounded-lg hover:bg-white/10 transition-colors"
          style="color:#94a3b8;">
          <ArrowLeft :size="18" />
        </Link>
        <div>
          <h1 class="font-semibold text-white text-lg leading-tight">{{ member.name }}</h1>
          <p class="text-xs font-mono" style="color:#a78bfa;">{{ member.member_number }}</p>
        </div>
        <span
          class="text-xs px-2.5 py-1 rounded-full font-medium"
          :style="member.status === 'active'
            ? 'background:rgba(34,197,94,.15); color:#22c55e; border:1px solid rgba(34,197,94,.3);'
            : 'background:rgba(239,68,68,.15); color:#ef4444; border:1px solid rgba(239,68,68,.3);'">
          {{ member.status === 'active' ? 'Aktif' : 'Habis' }}
        </span>
      </div>
    </template>
    <template #header-actions>
      <button
        @click="openTopUp"
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors hover:opacity-90"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
        <CreditCard :size="16" /> + Top-Up Deposit
      </button>
    </template>

    <!-- Info Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <!-- Tipe Console -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-3">
          <Gamepad2 :size="15" style="color:#94a3b8;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Tipe Console</span>
        </div>
        <div class="flex items-center gap-2">
          <component :is="typeIcon(member.console_type)" :size="20" :style="`color:${typeColor(member.console_type)}`" />
          <span class="text-lg font-bold capitalize" :style="`color:${typeColor(member.console_type)}`">
            {{ member.console_type }}
          </span>
        </div>
      </div>

      <!-- Sisa Jam -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <Clock :size="15" style="color:#22d3ee;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Sisa Jam</span>
        </div>
        <p class="text-xl font-bold mb-2"
          :style="member.remaining_minutes > 0 ? 'color:#22d3ee;' : 'color:#ef4444;'">
          {{ formatMinutes(member.remaining_minutes) }}
        </p>
        <!-- Progress bar -->
        <div class="w-full rounded-full h-1.5" style="background:#12121a;">
          <div
            class="h-1.5 rounded-full transition-all"
            :style="`width:${remainingPercent}%; background:${member.remaining_minutes > 0 ? '#22d3ee' : '#ef4444'};`" />
        </div>
        <p class="text-[11px] mt-1" style="color:#64748b;">{{ remainingPercent }}% dari 12 jam</p>
      </div>

      <!-- Total Deposit -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <Wallet :size="15" style="color:#fbbf24;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Total Deposit</span>
        </div>
        <p class="text-xl font-bold truncate" style="color:#fbbf24;">{{ formatCurrency(totalDeposited) }}</p>
        <p class="text-xs mt-1" style="color:#64748b;">{{ deposits.length }}x deposit</p>
      </div>

      <!-- Member Sejak -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <CalendarDays :size="15" style="color:#a78bfa;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Member Sejak</span>
        </div>
        <p class="text-base font-bold text-white">{{ formatDate(member.created_at) }}</p>
        <div class="flex items-center gap-1.5 mt-1">
          <User :size="11" style="color:#64748b;" />
          <p class="text-xs" style="color:#64748b;">{{ member.phone }}</p>
        </div>
      </div>
    </div>

    <!-- Section: Riwayat Deposit -->
    <div class="mb-6">
      <div class="flex items-center gap-2 mb-3">
        <Wallet :size="16" style="color:#a78bfa;" />
        <h2 class="text-sm font-semibold text-white">Riwayat Deposit</h2>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium" style="background:rgba(139,92,246,.2); color:#a78bfa;">
          {{ deposits.length }}
        </span>
      </div>

      <div class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
        <table class="w-full text-sm">
          <thead style="background:#12121a;">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Tgl Deposit</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Paket</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Bayar</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Kasir</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Metode</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Jam Didapat</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="deposits.length === 0">
              <td colspan="6" class="px-4 py-10 text-center text-xs" style="color:#64748b;">
                Belum ada riwayat deposit
              </td>
            </tr>
            <tr
              v-for="d in deposits" :key="d.id"
              class="transition-colors hover:bg-white/[0.02]"
              style="border-top:1px solid #1a1a26;">
              <td class="px-4 py-3 text-xs" style="color:#94a3b8;">{{ formatDt(d.created_at) }}</td>
              <td class="px-4 py-3 font-medium text-white">{{ d.package?.name ?? '-' }}</td>
              <td class="px-4 py-3 font-semibold" style="color:#a78bfa;">{{ formatCurrency(d.amount) }}</td>
              <td class="px-4 py-3 text-sm" style="color:#94a3b8;">{{ d.employee?.name ?? '-' }}</td>
              <td class="px-4 py-3">
                <span
                  class="text-xs px-2 py-0.5 rounded-full uppercase font-semibold"
                  :style="d.payment_method === 'cash'
                    ? 'background:rgba(34,197,94,.15); color:#22c55e;'
                    : 'background:rgba(34,211,238,.15); color:#22d3ee;'">
                  {{ d.payment_method }}
                </span>
              </td>
              <td class="px-4 py-3 font-semibold" style="color:#22d3ee;">
                {{ d.minutes_credited / 60 }} jam
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Section: Riwayat Sesi Main -->
    <div>
      <div class="flex items-center gap-2 mb-3">
        <Gamepad2 :size="16" style="color:#22d3ee;" />
        <h2 class="text-sm font-semibold text-white">Riwayat Sesi Main</h2>
        <span class="text-xs px-2 py-0.5 rounded-full font-medium" style="background:rgba(34,211,238,.2); color:#22d3ee;">
          {{ rentals.length }}
        </span>
      </div>

      <div class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
        <table class="w-full text-sm">
          <thead style="background:#12121a;">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Tanggal</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Console</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Durasi</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Jam Terpotong</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">FnB</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Total FnB</th>
              <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rentals.length === 0">
              <td colspan="7" class="px-4 py-10 text-center text-xs" style="color:#64748b;">
                Belum ada riwayat sesi main
              </td>
            </tr>
            <tr
              v-for="r in rentals" :key="r.id"
              class="transition-colors hover:bg-white/[0.02]"
              style="border-top:1px solid #1a1a26;">
              <td class="px-4 py-3">
                <p class="text-xs text-white">{{ formatDt(r.started_at) }}</p>
                <p class="text-[11px] font-mono mt-0.5" style="color:#64748b;">{{ r.transaction_code }}</p>
              </td>
              <td class="px-4 py-3 text-white font-medium">{{ r.console?.name ?? '-' }}</td>
              <td class="px-4 py-3 text-xs" style="color:#94a3b8;">
                {{ formatDuration(r.started_at, r.ended_at) }}
              </td>
              <td class="px-4 py-3 font-semibold" style="color:#22d3ee;">
                {{ r.member_deducted_minutes > 0 ? formatMinutes(r.member_deducted_minutes) : '-' }}
              </td>
              <td class="px-4 py-3">
                <span v-if="r.fnb_amount > 0">
                  <UtensilsCrossed :size="13" style="color:#10b981;" class="inline mr-1" />
                  <span class="text-xs" style="color:#10b981;">Ada</span>
                </span>
                <span v-else class="text-xs" style="color:#64748b;">-</span>
              </td>
              <td class="px-4 py-3 font-semibold" style="color:#10b981;">
                {{ r.fnb_amount > 0 ? formatCurrency(r.fnb_amount) : '-' }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="text-xs px-2 py-1 rounded-full"
                  :style="`background:${rentalStatusColors[r.status] ?? '#94a3b8'}22; color:${rentalStatusColors[r.status] ?? '#94a3b8'}; border:1px solid ${rentalStatusColors[r.status] ?? '#94a3b8'}44;`">
                  {{ rentalStatusLabels[r.status] ?? r.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modal Top-Up Deposit ─────────────────────────────────────────────── -->
    <GamingModal
      :show="showTopUpModal"
      title="Top-Up Deposit"
      maxWidth="36rem"
      @close="showTopUpModal = false">

      <div class="space-y-5">
        <!-- Member Info -->
        <div class="rounded-xl px-4 py-3 flex items-center gap-3"
          style="background:rgba(139,92,246,.1); border:1px solid rgba(139,92,246,.3);">
          <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
            style="background:rgba(139,92,246,.25);">
            <span class="text-xs font-bold" style="color:#a78bfa;">{{ member.name[0] }}</span>
          </div>
          <div class="min-w-0">
            <p class="text-white font-semibold text-sm truncate">{{ member.name }}</p>
            <p class="text-xs" style="color:#94a3b8;">{{ member.member_number }} · Sisa: {{ formatMinutes(member.remaining_minutes) }}</p>
          </div>
        </div>

        <!-- Pilih Paket -->
        <div>
          <label class="block text-xs font-medium mb-2" style="color:#94a3b8;">Pilih Paket *</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <button
              v-for="pkg in packages" :key="pkg.id"
              type="button"
              @click="topUpForm.package_id = pkg.id"
              class="p-3 rounded-xl text-left transition-all"
              :style="topUpForm.package_id === pkg.id
                ? 'background:rgba(139,92,246,.25); border:1px solid rgba(139,92,246,.6);'
                : 'background:#12121a; border:1px solid #2a2a3a;'">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-semibold text-white">{{ pkg.name }}</span>
                <span
                  class="text-[11px] px-2 py-0.5 rounded-full capitalize font-medium"
                  :style="`background:${typeColor(pkg.console_type)}20; color:${typeColor(pkg.console_type)};`">
                  {{ pkg.console_type }}
                </span>
              </div>
              <p class="text-xs" style="color:#94a3b8;">
                <Clock :size="11" class="inline mr-1" />{{ pkg.duration_minutes / 60 }} jam
              </p>
              <p class="text-sm font-bold mt-1" style="color:#a78bfa;">{{ formatCurrency(pkg.price) }}</p>
            </button>
          </div>
          <p v-if="topUpForm.errors.package_id" class="text-red-400 text-xs mt-1">{{ topUpForm.errors.package_id }}</p>
        </div>

        <!-- Kasir -->
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Kasir *</label>
          <select
            v-model="topUpForm.employee_id"
            class="w-full px-4 py-2.5 rounded-xl text-white outline-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option :value="null">-- Pilih Kasir --</option>
            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
          <p v-if="topUpForm.errors.employee_id" class="text-red-400 text-xs mt-1">{{ topUpForm.errors.employee_id }}</p>
        </div>

        <!-- Metode Pembayaran -->
        <div>
          <label class="block text-xs font-medium mb-2" style="color:#94a3b8;">Metode Pembayaran *</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="m in ['cash', 'qris']" :key="m"
              type="button"
              @click="topUpForm.payment_method = m"
              class="py-2.5 px-3 rounded-xl text-xs font-semibold uppercase transition-all"
              :style="topUpForm.payment_method === m
                ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white; border:1px solid rgba(139,92,246,.6);'
                : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
              {{ m }}
            </button>
          </div>
          <p v-if="topUpForm.errors.payment_method" class="text-red-400 text-xs mt-1">{{ topUpForm.errors.payment_method }}</p>
        </div>

        <!-- Catatan -->
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Catatan (opsional)</label>
          <textarea
            v-model="topUpForm.notes"
            rows="2"
            class="w-full px-4 py-2.5 rounded-xl text-white outline-none resize-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="Catatan opsional..." />
        </div>

        <!-- Summary -->
        <div v-if="selectedPackage" class="rounded-xl p-4" style="background:#12121a; border:1px solid rgba(139,92,246,.3);">
          <p class="text-xs font-medium mb-2" style="color:#94a3b8;">Ringkasan Top-Up</p>
          <div class="space-y-1.5 text-sm">
            <div class="flex justify-between">
              <span style="color:#94a3b8;">Paket</span>
              <span class="text-white font-medium">{{ selectedPackage.name }}</span>
            </div>
            <div class="flex justify-between">
              <span style="color:#94a3b8;">Jam Didapat</span>
              <span style="color:#22d3ee;" class="font-semibold">{{ selectedPackage.duration_minutes / 60 }} jam</span>
            </div>
            <div class="flex justify-between">
              <span style="color:#94a3b8;">Sisa Jam Setelah</span>
              <span style="color:#22c55e;" class="font-semibold">
                {{ formatMinutes(member.remaining_minutes + selectedPackage.duration_minutes) }}
              </span>
            </div>
            <div class="flex justify-between">
              <span style="color:#94a3b8;">Metode</span>
              <span class="text-white uppercase font-semibold">{{ topUpForm.payment_method }}</span>
            </div>
            <div class="flex justify-between pt-1.5 border-t border-slate-700/50">
              <span class="font-semibold" style="color:#a78bfa;">Total Bayar</span>
              <span class="font-bold text-lg" style="color:#a78bfa;">{{ formatCurrency(selectedPackage.price) }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 pt-1">
          <button
            type="button"
            @click="showTopUpModal = false"
            class="flex-1 py-3 rounded-xl text-sm font-medium transition-all"
            style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
            Batal
          </button>
          <button
            type="button"
            @click="submitTopUp"
            :disabled="topUpForm.processing || !topUpForm.package_id || !topUpForm.employee_id"
            class="flex-1 py-3 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 hover:opacity-90"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
            {{ topUpForm.processing ? 'Memproses...' : 'Konfirmasi Top-Up' }}
          </button>
        </div>
      </div>
    </GamingModal>
  </AppLayout>
</template>
