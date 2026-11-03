<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import {
  Users, UserCheck, Clock, Wallet, Plus, Search, X,
  Eye, CreditCard, ChevronRight, Crown, Star, Gem, Zap,
} from 'lucide-vue-next'

interface MemberPackage {
  id: number
  name: string
  console_type: string
  price: number
  duration_minutes: number
}

interface Member {
  id: number
  member_number: string
  name: string
  phone: string
  console_type: string
  remaining_minutes: number
  status: string
  total_deposits_count: number
  total_deposited_amount: number
  created_at: string
}

const props = defineProps<{
  members: { data: Member[]; links: any[]; total: number }
  packages: MemberPackage[]
  employees: { id: number; name: string }[]
  stats: { total: number; active: number; exhausted: number; total_revenue: number }
  filters: { search: string; status: string }
}>()

// ─── Filters ────────────────────────────────────────────────────────────────
const filters = ref({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
})

let debounce: ReturnType<typeof setTimeout>
watch(filters, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    router.get(route('members.index'), filters.value as any, { preserveState: true, replace: true })
  }, 350)
}, { deep: true })

function clearFilters() {
  filters.value.search = ''
  filters.value.status = ''
}

const hasActiveFilter = computed(() => !!(filters.value.search || filters.value.status))

// ─── Top-Up Modal ────────────────────────────────────────────────────────────
const showTopUpModal = ref(false)
const selectedMember = ref<Member | null>(null)

const topUpForm = useForm({
  member_id: null as number | null,
  package_id: null as number | null,
  payment_method: 'cash',
  employee_id: null as number | null,
  notes: '',
})

const selectedPackage = computed(() =>
  props.packages.find(p => p.id === topUpForm.package_id) ?? null
)

function openTopUp(m: Member) {
  selectedMember.value = m
  topUpForm.reset()
  topUpForm.member_id = m.id
  topUpForm.payment_method = 'cash'
  showTopUpModal.value = true
}

function submitTopUp() {
  if (!selectedMember.value) return
  topUpForm.post(route('members.deposit', selectedMember.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      showTopUpModal.value = false
      topUpForm.reset()
      selectedMember.value = null
    },
  })
}

// ─── Daftar Member Baru Modal ─────────────────────────────────────────────────
const showRegisterModal = ref(false)

const registerForm = useForm({
  name: '',
  phone: '',
  package_id: null as number | null,
})

const registerSelectedPackage = computed(() =>
  props.packages.find(p => p.id === registerForm.package_id) ?? null
)

function submitRegister() {
  registerForm.post(route('members.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showRegisterModal.value = false
      registerForm.reset()
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

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
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
</script>

<template>
  <AppLayout>
    <template #header-title>
      <h1 class="font-semibold text-white text-lg">Member Cubic</h1>
    </template>
    <template #header-actions>
      <button
        @click="showRegisterModal = true"
        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition-colors hover:opacity-90"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
        <Plus :size="16" /> Daftar Member Baru
      </button>
    </template>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
      <!-- Total Member -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <Users :size="15" style="color:#a78bfa;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Total Member</span>
        </div>
        <p class="text-xl font-bold" style="color:#a78bfa;">{{ stats.total }}</p>
        <p class="text-xs mt-1" style="color:#64748b;">member terdaftar</p>
      </div>

      <!-- Member Aktif -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <UserCheck :size="15" style="color:#22c55e;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Member Aktif</span>
        </div>
        <p class="text-xl font-bold" style="color:#22c55e;">{{ stats.active }}</p>
        <p class="text-xs mt-1" style="color:#64748b;">masih ada sisa jam</p>
      </div>

      <!-- Total Kuota Dipakai -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <Clock :size="15" style="color:#22d3ee;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Kuota Habis</span>
        </div>
        <p class="text-xl font-bold" style="color:#22d3ee;">{{ stats.exhausted }}</p>
        <p class="text-xs mt-1" style="color:#64748b;">member habis jam</p>
      </div>

      <!-- Pendapatan Deposit -->
      <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center gap-2 mb-2">
          <Wallet :size="15" style="color:#fbbf24;" />
          <span class="text-xs font-medium" style="color:#94a3b8;">Total Deposit</span>
        </div>
        <p class="text-xl font-bold truncate" style="color:#fbbf24;">{{ formatCurrency(stats.total_revenue) }}</p>
        <p class="text-xs mt-1" style="color:#64748b;">pendapatan deposit</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl p-4 mb-4 flex flex-wrap gap-3 items-center" style="background:#1a1a26; border:1px solid #2a2a3a;">
      <div class="relative flex-1 min-w-[200px]">
        <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2" style="color:#64748b;" />
        <input
          v-model="filters.search"
          placeholder="Cari nama / no. member / telp..."
          class="w-full pl-9 pr-3 py-2 rounded-xl text-sm text-white outline-none"
          style="background:#12121a; border:1px solid #2a2a3a;" />
      </div>

      <select
        v-model="filters.status"
        class="px-3 py-2 rounded-xl text-sm text-white outline-none"
        style="background:#12121a; border:1px solid #2a2a3a;">
        <option value="">Semua Status</option>
        <option value="active">Aktif</option>
        <option value="exhausted">Habis</option>
      </select>

      <button
        v-if="hasActiveFilter"
        @click="clearFilters"
        class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs transition-colors"
        style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3); color:#ef4444;">
        <X :size="13" /> Reset
      </button>
    </div>

    <!-- Table -->
    <div class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
      <table class="w-full text-sm">
        <thead style="background:#12121a;">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">No. Member</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Nama</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">No. Telp</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Tipe</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Sisa Jam</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Total Deposit</th>
            <th class="px-4 py-3 text-left text-xs font-medium" style="color:#64748b;">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="members.data.length === 0">
            <td colspan="8" class="px-4 py-12 text-center text-xs" style="color:#64748b;">
              Tidak ada data member
            </td>
          </tr>
          <tr
            v-for="m in members.data" :key="m.id"
            class="transition-colors hover:bg-white/[0.02]"
            style="border-top:1px solid #1a1a26;">
            <!-- No. Member -->
            <td class="px-4 py-3 font-mono text-xs font-semibold" style="color:#a78bfa;">
              {{ m.member_number }}
            </td>

            <!-- Nama -->
            <td class="px-4 py-3">
              <span class="text-white font-medium">{{ m.name }}</span>
              <p class="text-xs mt-0.5" style="color:#64748b;">Sejak {{ formatDate(m.created_at) }}</p>
            </td>

            <!-- Telp -->
            <td class="px-4 py-3 text-sm" style="color:#94a3b8;">{{ m.phone }}</td>

            <!-- Tipe -->
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded-lg font-semibold capitalize"
                :style="`background:${typeColor(m.console_type)}20; color:${typeColor(m.console_type)}; border:1px solid ${typeColor(m.console_type)}44;`">
                <component :is="typeIcon(m.console_type)" :size="11" />
                {{ m.console_type }}
              </span>
            </td>

            <!-- Sisa Jam -->
            <td class="px-4 py-3">
              <span
                class="font-semibold text-sm"
                :style="m.remaining_minutes > 0 ? 'color:#22d3ee;' : 'color:#ef4444;'">
                {{ formatMinutes(m.remaining_minutes) }}
              </span>
            </td>

            <!-- Total Deposit -->
            <td class="px-4 py-3">
              <span class="font-semibold text-sm" style="color:#a78bfa;">
                {{ formatCurrency(m.total_deposited_amount) }}
              </span>
              <p class="text-xs mt-0.5" style="color:#64748b;">{{ m.total_deposits_count }}x deposit</p>
            </td>

            <!-- Status -->
            <td class="px-4 py-3">
              <span
                class="text-xs px-2.5 py-1 rounded-full font-medium"
                :style="m.status === 'active'
                  ? 'background:rgba(34,197,94,.15); color:#22c55e; border:1px solid rgba(34,197,94,.3);'
                  : 'background:rgba(239,68,68,.15); color:#ef4444; border:1px solid rgba(239,68,68,.3);'">
                {{ m.status === 'active' ? 'Aktif' : 'Habis' }}
              </span>
            </td>

            <!-- Aksi -->
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5 justify-end">
                <Link
                  :href="route('members.show', m.id)"
                  class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg transition-all"
                  style="background:rgba(139,92,246,.2); border:1px solid rgba(139,92,246,.3); color:#a78bfa; text-decoration:none;">
                  <Eye :size="13" /> Detail
                </Link>
                <button
                  @click="openTopUp(m)"
                  class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg transition-all"
                  style="background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.3); color:#22c55e;">
                  <CreditCard :size="13" /> Top-Up
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex gap-1 mt-4 justify-center flex-wrap">
      <template v-for="link in members.links" :key="link.label">
        <a
          v-if="link.url"
          :href="link.url"
          class="px-3 py-1.5 rounded-xl text-xs"
          :style="link.active
            ? 'background:rgba(139,92,246,.3); color:white; border:1px solid rgba(139,92,246,.5);'
            : 'background:#1a1a26; color:#94a3b8; border:1px solid #2a2a3a;'"
          v-html="link.label" />
        <span
          v-else
          class="px-3 py-1.5 rounded-xl text-xs"
          style="color:#3a3a4a;"
          v-html="link.label" />
      </template>
    </div>

    <!-- ── Modal Top-Up Deposit ─────────────────────────────────────────────── -->
    <GamingModal
      :show="showTopUpModal"
      title="Top-Up Deposit Member"
      maxWidth="36rem"
      @close="showTopUpModal = false">

      <div v-if="selectedMember" class="space-y-5">
        <!-- Member Info -->
        <div class="rounded-xl px-4 py-3 flex items-center gap-3"
          style="background:rgba(139,92,246,.1); border:1px solid rgba(139,92,246,.3);">
          <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
            style="background:rgba(139,92,246,.25);">
            <span class="text-xs font-bold" style="color:#a78bfa;">{{ selectedMember.name[0] }}</span>
          </div>
          <div class="min-w-0">
            <p class="text-white font-semibold text-sm truncate">{{ selectedMember.name }}</p>
            <p class="text-xs" style="color:#94a3b8;">{{ selectedMember.member_number }} · Sisa: {{ formatMinutes(selectedMember.remaining_minutes) }}</p>
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

    <!-- ── Modal Daftar Member Baru ─────────────────────────────────────────── -->
    <GamingModal
      :show="showRegisterModal"
      title="Daftar Member Baru"
      maxWidth="32rem"
      @close="showRegisterModal = false">

      <div class="space-y-5">
        <!-- Nama -->
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama Lengkap *</label>
          <input
            v-model="registerForm.name"
            class="w-full px-4 py-2.5 rounded-xl text-white outline-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="Nama lengkap member..." />
          <p v-if="registerForm.errors.name" class="text-red-400 text-xs mt-1">{{ registerForm.errors.name }}</p>
        </div>

        <!-- No. Telp -->
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">No. Telepon *</label>
          <input
            v-model="registerForm.phone"
            type="tel"
            class="w-full px-4 py-2.5 rounded-xl text-white outline-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="08xxxxxxxxxx" />
          <p v-if="registerForm.errors.phone" class="text-red-400 text-xs mt-1">{{ registerForm.errors.phone }}</p>
        </div>

        <!-- Pilih Tipe Paket -->
        <div>
          <label class="block text-xs font-medium mb-2" style="color:#94a3b8;">Pilih Paket Awal *</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <button
              v-for="pkg in packages" :key="pkg.id"
              type="button"
              @click="registerForm.package_id = pkg.id"
              class="p-3 rounded-xl text-left transition-all"
              :style="registerForm.package_id === pkg.id
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
          <p v-if="registerForm.errors.package_id" class="text-red-400 text-xs mt-1">{{ registerForm.errors.package_id }}</p>
        </div>

        <!-- Console Type Info -->
        <div v-if="registerSelectedPackage"
          class="rounded-xl px-4 py-3 flex items-center gap-3"
          :style="`background:${typeColor(registerSelectedPackage.console_type)}15; border:1px solid ${typeColor(registerSelectedPackage.console_type)}44;`">
          <component :is="typeIcon(registerSelectedPackage.console_type)" :size="18" :style="`color:${typeColor(registerSelectedPackage.console_type)}`" />
          <div>
            <p class="text-xs font-medium text-white">
              Console Type: <span class="capitalize font-bold" :style="`color:${typeColor(registerSelectedPackage.console_type)}`">{{ registerSelectedPackage.console_type }}</span>
            </p>
            <p class="text-xs mt-0.5" style="color:#94a3b8;">Member ini hanya bisa bermain di room tipe {{ registerSelectedPackage.console_type }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 pt-1">
          <button
            type="button"
            @click="showRegisterModal = false"
            class="flex-1 py-3 rounded-xl text-sm font-medium transition-all"
            style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
            Batal
          </button>
          <button
            type="button"
            @click="submitRegister"
            :disabled="registerForm.processing || !registerForm.name || !registerForm.phone || !registerForm.package_id"
            class="flex-1 py-3 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50 hover:opacity-90"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
            {{ registerForm.processing ? 'Mendaftarkan...' : 'Daftar Member' }}
          </button>
        </div>
      </div>
    </GamingModal>
  </AppLayout>
</template>
