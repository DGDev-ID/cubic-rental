<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Timer, Play, CalendarClock, Search, CheckCircle2, User, Award, AlertCircle } from 'lucide-vue-next'

interface Console { id: number; name: string; type: string; price_per_hour: number }
interface Employee { id: number; name: string }
interface Member {
  id: number; member_number: string; name: string; console_type: string; remaining_minutes: number
}
interface UpcomingReservation {
  id: number; customer_name: string; reserved_at: string; duration_hours: number | null; status: string
}

const props = defineProps<{
  consoles: Console[]
  employees: Employee[]
  upcoming_reservations: Record<number, UpcomingReservation[]>
  members?: Member[]
}>()

const customerType = ref<'regular' | 'member'>('regular')
const selectedMemberId = ref<number | null>(null)

const form = useForm({
  customer_name: '',
  console_id: null as number | null,
  employee_id: null as number | null,
  duration_hours: null as number | null,
  notes: '',
  pay_upfront: false,
  payment_method: 'cash',
  member_id: null as number | null,
})

const currentMember = computed(() => props.members?.find(m => m.id === selectedMemberId.value))

watch(customerType, (type) => {
  if (type === 'regular') {
    selectedMemberId.value = null
    form.member_id = null
    filterType.value = 'all'
  } else {
    form.pay_upfront = false
  }
})

watch(selectedMemberId, (id) => {
  form.member_id = id
  if (currentMember.value) {
    form.customer_name = currentMember.value.name
    filterType.value = currentMember.value.console_type
    // If currently selected console doesn't match member's type, clear it
    if (selectedConsole.value && selectedConsole.value.type !== currentMember.value.console_type) {
      form.console_id = null
    }
  }
})

const memberMaxHours = computed(() => {
  if (!currentMember.value) return 24
  return Math.floor(currentMember.value.remaining_minutes / 60)
})

const memberDurationExceeded = computed(() => {
  if (customerType.value !== 'member' || !currentMember.value || !form.duration_hours) return false
  return (form.duration_hours * 60) > currentMember.value.remaining_minutes
})

const selectedConsole = computed(() => props.consoles.find(c => c.id === form.console_id))

// Search & filter
const searchQuery = ref('')
const filterType = ref<string>('all')

const availableTypes = computed(() => {
  const types = [...new Set(props.consoles.map(c => c.type))]
  return types
})

const filteredConsoles = computed(() => {
  return props.consoles.filter(c => {
    const matchSearch = c.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchType = filterType.value === 'all' || c.type === filterType.value
    return matchSearch && matchType
  })
})

// Reservasi per console yang ada upcoming
const consoleReservations = computed(() => (consoleId: number) =>
  props.upcoming_reservations[consoleId] ?? []
)

const statusLabel: Record<string, string> = { pending: 'Menunggu', confirmed: 'Dikonfirmasi' }

const durationChips = [1, 2, 3, 4, 5]

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}

function submit() {
  form.post(route('rentals.store'))
}

const typeColors: Record<string, string> = {
  regular: '#60a5fa', vip: '#a78bfa', vvip: '#22d3ee', suite: '#fbbf24',
}
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Buat Rental Baru</h1></template>

    <div>
      <form @submit.prevent="submit">
        <div class="rounded-xl p-6 space-y-5" style="background-color:#1a1a26; border:1px solid #2a2a3a;">

          <!-- Customer Type Tabs: Regular vs Member -->
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Jenis Customer *</label>
            <div class="grid grid-cols-2 gap-2">
              <button type="button" @click="customerType = 'regular'"
                class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center justify-center gap-2"
                :style="customerType === 'regular'
                  ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white; border:1px solid #a78bfa;'
                  : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
                <User :size="15" /> Customer Biasa
              </button>
              <button type="button" @click="customerType = 'member'"
                class="py-2.5 px-3 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center justify-center gap-2"
                :style="customerType === 'member'
                  ? 'background:linear-gradient(135deg,#10b981,#059669); color:white; border:1px solid #34d399;'
                  : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
                <Award :size="15" /> Member Cubic (Potong Kuota)
              </button>
            </div>
          </div>

          <!-- Member Selector (hanya jika jenis customer = member) -->
          <div v-if="customerType === 'member'" class="p-4 rounded-xl space-y-3" style="background:rgba(16,185,129,.06); border:1px solid rgba(16,185,129,.3);">
            <div>
              <label class="block text-xs font-semibold mb-1 text-emerald-400">Pilih Akun Member Cubic *</label>
              <select v-model="selectedMemberId" required
                class="w-full px-4 py-3 rounded-xl text-white outline-none"
                style="background:#12121a; border:1px solid #2a2a3a; font-size:14px;">
                <option :value="null">-- Pilih Member dari Daftar --</option>
                <option v-for="m in members" :key="m.id" :value="m.id">
                  {{ m.member_number }} - {{ m.name }} (Room: {{ m.console_type.toUpperCase() }} | Sisa: {{ Math.floor(m.remaining_minutes/60) }}j {{ m.remaining_minutes%60 }}m)
                </option>
              </select>
            </div>

            <div v-if="currentMember" class="p-3 rounded-xl bg-[#12121a] border border-[#2a2a3a] text-xs space-y-1">
              <div class="flex justify-between">
                <span class="text-slate-400">Nama Member</span>
                <span class="text-white font-semibold">{{ currentMember.name }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Tipe Console Diizinkan</span>
                <span class="font-bold uppercase" :style="`color: ${typeColors[currentMember.console_type] || '#10b981'}`">{{ currentMember.console_type }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-400">Sisa Kuota Deposit</span>
                <span class="font-bold text-emerald-400">{{ Math.floor(currentMember.remaining_minutes/60) }} jam {{ currentMember.remaining_minutes%60 }} menit</span>
              </div>
            </div>
          </div>

          <!-- Customer (opsional jika biasa) -->
          <div v-if="customerType === 'regular'">
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-medium" style="color:#94a3b8;">Nama Customer (opsional)</label>
              <span class="text-[11px]" style="color:#64748b;">Kosongkan jika tamu / anonym</span>
            </div>
            <input v-model="form.customer_name"
              class="w-full px-4 py-3 rounded-xl text-white outline-none transition-colors"
              style="background:#12121a; border:1px solid #2a2a3a; font-size:15px;"
              placeholder="Nama customer (default: Customer)..." />
            <p v-if="form.errors.customer_name" class="text-red-400 text-xs mt-1">{{ form.errors.customer_name }}</p>
          </div>

          <!-- Console -->
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Console / Room *</label>

            <!-- Search + Filter -->
            <div class="flex flex-col sm:flex-row gap-2 mb-3">
              <!-- Search bar -->
              <div class="relative flex-1">
                <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2" style="color:#64748b;" />
                <input v-model="searchQuery"
                  type="text"
                  placeholder="Cari nama room..."
                  class="w-full pl-8 pr-3 py-2 rounded-xl text-white text-xs outline-none transition-colors"
                  style="background:#12121a; border:1px solid #2a2a3a;" />
              </div>
              <!-- Category filter chips -->
              <div class="flex gap-1.5 flex-wrap items-center">
                <button type="button"
                  @click="filterType = 'all'"
                  class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                  :style="filterType === 'all'
                    ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;'
                    : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
                  Semua
                </button>
                <button v-for="t in availableTypes" :key="t" type="button"
                  @click="filterType = t"
                  class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all capitalize"
                  :style="filterType === t
                    ? `background:${typeColors[t] ?? '#8b5cf6'}22; color:${typeColors[t] ?? '#8b5cf6'}; border:1px solid ${typeColors[t] ?? '#8b5cf6'}99;`
                    : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
                  {{ t.toUpperCase() }}
                </button>
              </div>
            </div>

            <!-- No results -->
            <p v-if="filteredConsoles.length === 0" class="text-xs py-4 text-center" style="color:#64748b;">
              Tidak ada room ditemukan.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <button v-for="c in filteredConsoles" :key="c.id" type="button"
                @click="form.console_id = c.id"
                class="p-3 rounded-xl text-left transition-all relative"
                :style="form.console_id === c.id
                  ? 'background:rgba(139,92,246,.2); border:1px solid rgba(139,92,246,.6);'
                  : 'background:#12121a; border:1px solid #2a2a3a;'">
                <!-- Reservation indicator dot -->
                <span v-if="consoleReservations(c.id).length > 0"
                  class="absolute top-2 right-2 flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-medium"
                  style="background:rgba(251,191,36,.15); color:#fbbf24; border:1px solid rgba(251,191,36,.4);">
                  <CalendarClock :size="10" />
                  {{ consoleReservations(c.id).length }}
                </span>
                <p class="font-medium text-xs text-white truncate">{{ c.name }}</p>
                <p class="text-xs mt-0.5" :style="`color: ${typeColors[c.type]}`">{{ c.type.toUpperCase() }}</p>
                <p class="text-xs mt-1 font-medium" style="color:#a78bfa;">{{ formatCurrency(c.price_per_hour) }}/jam</p>
              </button>
            </div>

            <!-- Reservation notes: only for selected console -->
            <div v-if="form.console_id && consoleReservations(form.console_id).length > 0" class="mt-3">
              <div class="rounded-xl px-3 py-2.5 text-xs"
                style="background:rgba(251,191,36,.07); border:1px solid rgba(251,191,36,.25);">
                <div class="flex items-center gap-1.5 mb-1.5 font-semibold" style="color:#fbbf24;">
                  <CalendarClock :size="13" />
                  {{ selectedConsole?.name }} — {{ consoleReservations(form.console_id).length }} Reservasi Mendatang
                </div>
                <div class="space-y-1">
                  <div v-for="r in consoleReservations(form.console_id)" :key="r.id"
                    class="flex items-center justify-between pl-1"
                    style="color:#d1d5db;">
                    <span>{{ r.customer_name }}</span>
                    <div class="flex items-center gap-2">
                      <span v-if="r.duration_hours" style="color:#94a3b8;">{{ r.duration_hours }}j</span>
                      <span class="px-1.5 py-0.5 rounded text-xs"
                        :style="r.status === 'confirmed'
                          ? 'background:rgba(16,185,129,.15); color:#34d399;'
                          : 'background:rgba(251,191,36,.15); color:#fbbf24;'">
                        {{ statusLabel[r.status] ?? r.status }}
                      </span>
                      <span style="color:#94a3b8;">{{ r.reserved_at }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <p v-if="form.errors.console_id" class="text-red-400 text-xs mt-1">{{ form.errors.console_id }}</p>
          </div>

          <!-- Duration -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="block text-xs font-medium" style="color:#94a3b8;">
                <Timer :size="13" class="inline mr-1" /> Durasi Bermain {{ customerType === 'member' ? '*' : '(opsional)' }}
              </label>
              <span v-if="customerType === 'member'" class="text-[11px] text-emerald-400 font-medium">
                Maksimal {{ memberMaxHours }} jam (sesuai sisa kuota)
              </span>
            </div>
            <div class="flex flex-wrap gap-2 mb-2">
              <button v-for="d in durationChips" :key="d" type="button"
                @click="form.duration_hours = form.duration_hours === d ? null : d"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                :style="form.duration_hours === d
                  ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;'
                  : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
                {{ d }}j
              </button>
              <input type="number" step="0.5" min="0.5" :max="customerType === 'member' ? memberMaxHours : 24"
                v-model.number="form.duration_hours"
                class="w-24 px-3 py-1.5 rounded-lg text-xs text-white outline-none"
                style="background:#12121a; border:1px solid #2a2a3a;"
                placeholder="Custom..." />
            </div>

            <!-- Warning if member duration exceeded -->
            <div v-if="memberDurationExceeded" class="p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-xs text-red-400 flex items-start gap-2 mt-2">
              <AlertCircle :size="16" class="shrink-0 mt-0.5" />
              <div>
                <p class="font-semibold">Durasi melebihi sisa kuota!</p>
                <p class="mt-0.5">Sisa kuota member hanya {{ Math.floor((currentMember?.remaining_minutes || 0) / 60) }} jam {{ (currentMember?.remaining_minutes || 0) % 60 }} menit. Tidak ada pembiayaan overtime, silakan perpanjang deposit atau kurangi durasi bermain.</p>
              </div>
            </div>

            <p v-if="customerType !== 'member'" class="text-xs" style="color:#64748b;">Kosongkan untuk Open Time (meter jalan tanpa batas).</p>
            <p v-else class="text-xs text-slate-400">Member wajib menentukan durasi bermain yang dipotong dari deposit.</p>
            <p v-if="form.errors.duration_hours" class="text-red-400 text-xs mt-1">{{ form.errors.duration_hours }}</p>
          </div>

          <!-- Bayar di Awal (Prepaid) - Non Member -->
          <div v-if="customerType !== 'member' && form.duration_hours" class="rounded-xl p-4 transition-all"
            :style="form.pay_upfront
              ? 'background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.4);'
              : 'background:#12121a; border:1px solid #2a2a3a;'">
            <div class="flex items-center justify-between cursor-pointer" @click="form.pay_upfront = !form.pay_upfront">
              <div class="flex items-center gap-3">
                <input type="checkbox" v-model="form.pay_upfront" class="w-4 h-4 rounded text-emerald-500 focus:ring-0 focus:outline-none cursor-pointer" />
                <div>
                  <span class="text-sm font-semibold text-white flex items-center gap-1.5">
                    <CheckCircle2 v-if="form.pay_upfront" :size="16" class="text-emerald-400" />
                    Bayar di Awal (Prepaid / Lunas)
                  </span>
                  <p class="text-xs text-slate-400 mt-0.5">
                    Console akan langsung tercatat lunas di Room Monitor dan bermain hingga selesai tanpa tagihan akhir.
                  </p>
                </div>
              </div>
              <span v-if="selectedConsole && form.duration_hours" class="text-sm font-bold text-emerald-400">
                {{ formatCurrency(Math.round((selectedConsole.price_per_hour * form.duration_hours) / 1000) * 1000) }}
              </span>
            </div>

            <!-- Metode Pembayaran Awal -->
            <div v-if="form.pay_upfront" class="mt-4 pt-3 border-t border-emerald-500/20">
              <label class="block text-xs font-medium mb-2 text-slate-300">Pilih Metode Pembayaran Awal</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button v-for="m in ['cash', 'qris', 'transfer', 'debit']" :key="m" type="button"
                  @click="form.payment_method = m"
                  class="py-2 px-3 rounded-lg text-xs font-semibold uppercase transition-all"
                  :style="form.payment_method === m
                    ? 'background:linear-gradient(135deg,#10b981,#059669); color:white; border:1px solid #34d399;'
                    : 'background:#1a1a26; color:#94a3b8; border:1px solid #2a2a3a;'">
                  {{ m }}
                </button>
              </div>
            </div>
          </div>

          <!-- Member Potong Kuota Card (Member) -->
          <div v-if="customerType === 'member' && currentMember" class="rounded-xl p-4" style="background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.4);">
            <div class="flex items-center justify-between">
              <div>
                <span class="text-sm font-semibold text-white flex items-center gap-1.5">
                  <CheckCircle2 :size="16" class="text-emerald-400" />
                  Sesi Bermain Member (Potong Kuota)
                </span>
                <p class="text-xs text-slate-400 mt-0.5">
                  Biaya rental PS adalah Rp 0 karena memotong kuota deposit member. Sisa kuota akan otomatis berkurang setelah selesai main.
                </p>
              </div>
              <span class="text-base font-bold text-emerald-400">Rp 0</span>
            </div>
          </div>

          <!-- Employee -->
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Operator *</label>
            <select v-model="form.employee_id" required
              class="w-full px-4 py-3 rounded-xl text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option :value="null">-- Pilih Operator --</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
            <p v-if="form.errors.employee_id" class="text-red-400 text-xs mt-1">{{ form.errors.employee_id }}</p>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Catatan</label>
            <textarea v-model="form.notes" rows="2"
              class="w-full px-4 py-3 rounded-xl text-white outline-none resize-none"
              style="background:#12121a; border:1px solid #2a2a3a;"
              placeholder="Catatan opsional..." />
          </div>

          <!-- Summary -->
          <div v-if="selectedConsole" class="rounded-xl p-4" style="background:#12121a; border:1px solid rgba(139,92,246,.3);">
            <p class="text-xs font-medium mb-2" style="color:#94a3b8;">Ringkasan</p>
            <div class="space-y-1.5 text-sm">
              <div class="flex justify-between">
                <span style="color:#94a3b8;">Console</span>
                <span class="text-white">{{ selectedConsole.name }} ({{ selectedConsole.type.toUpperCase() }})</span>
              </div>
              <div v-if="customerType === 'member' && currentMember" class="flex justify-between">
                <span style="color:#94a3b8;">Status Member</span>
                <span class="text-emerald-400 font-semibold">{{ currentMember.name }} ({{ currentMember.member_number }})</span>
              </div>
              <div class="flex justify-between">
                <span style="color:#94a3b8;">Tarif Normal</span>
                <span :class="customerType === 'member' ? 'line-through text-slate-500' : 'font-bold text-[#a78bfa]'">
                  {{ formatCurrency(selectedConsole.price_per_hour) }}/jam
                </span>
              </div>
              <div v-if="form.duration_hours" class="flex justify-between">
                <span style="color:#94a3b8;">Durasi</span>
                <span class="text-white">{{ form.duration_hours }} jam</span>
              </div>
              <div v-if="customerType === 'member'" class="flex justify-between pt-1.5 border-t border-slate-700/50">
                <span class="text-emerald-400 font-semibold">Total Biaya Rental</span>
                <span class="font-bold text-emerald-400">Rp 0 (Potong Kuota Member)</span>
              </div>
              <div v-else-if="form.duration_hours" class="flex justify-between">
                <span style="color:#94a3b8;">Total Biaya Rental</span>
                <span class="font-bold" style="color:#22d3ee;">
                  {{ formatCurrency(Math.round((selectedConsole.price_per_hour * form.duration_hours) / 1000) * 1000) }}
                </span>
              </div>
              <div v-else class="flex justify-between">
                <span style="color:#94a3b8;">Tipe</span>
                <span class="text-white">Open Time (meter jalan)</span>
              </div>
              <div v-if="form.pay_upfront && form.duration_hours && customerType !== 'member'" class="flex justify-between pt-1.5 border-t border-slate-700/50">
                <span class="text-emerald-400 font-medium">Status Pembayaran</span>
                <span class="font-bold text-emerald-400">Bayar di Awal ({{ form.payment_method.toUpperCase() }})</span>
              </div>
            </div>
          </div>

          <button type="submit"
            :disabled="form.processing || memberDurationExceeded || (customerType === 'member' && (!selectedMemberId || !form.duration_hours))"
            class="w-full py-4 rounded-xl text-white font-semibold text-base transition-all disabled:opacity-60 flex items-center justify-center gap-2 hover:opacity-90"
            :style="customerType === 'member' ? 'background:linear-gradient(135deg,#10b981,#059669);' : 'background:linear-gradient(135deg,#8b5cf6,#3b82f6);'">
            <Play :size="16" /> {{ customerType === 'member' ? 'Mulai Rental Member' : 'Mulai Rental' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
