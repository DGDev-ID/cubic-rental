<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface RentalRow {
  id: number; transaction_code: string; customer_name: string; rental_type: string;
  status: string; started_at: string; ended_at: string | null; total_amount: number;
  console: { name: string; type: string }
  employee: { name: string }
  payments: { method: string; amount: number }[]
}

const props = defineProps<{
  rentals: { data: RentalRow[]; links: any[]; total: number }
  filters: { search: string; date: string; employee_id: string; console_id: string; payment_method: string }
  employees: { id: number; name: string }[]
  consoles: { id: number; name: string }[]
}>()

const filters = ref({ ...props.filters })
let debounce: ReturnType<typeof setTimeout>
watch(filters, (v) => {
  clearTimeout(debounce)
  debounce = setTimeout(() => {
    router.get(route('rentals.history'), v as any, { preserveState: true, replace: true })
  }, 350)
}, { deep: true })

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDt(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusColor: Record<string, string> = {
  running: '#22d3ee', finished: '#22c55e', paid: '#a78bfa', cancelled: '#ef4444', half_paid: '#fbbf24',
}
const typeColors: Record<string, string> = { regular: '#60a5fa', vip: '#a78bfa', vvip: '#22d3ee', suite: '#fbbf24' }

const detail = ref<RentalRow | null>(null)
</script>

<template>
  <AppLayout>
    <template #header-title><h1 class="font-semibold text-white text-lg">Riwayat Transaksi</h1></template>

    <!-- Filters -->
    <div class="rounded-xl p-4 mb-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3"
      style="background:#1a1a26; border:1px solid #2a2a3a;">
      <input v-model="filters.search" placeholder="Cari customer/kode..."
        class="px-3 py-2 rounded-xl text-sm text-white outline-none col-span-2 sm:col-span-1"
        style="background:#12121a; border:1px solid #2a2a3a;" />
      <input v-model="filters.date" type="date"
        class="px-3 py-2 rounded-xl text-sm text-white outline-none"
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

    <!-- Total -->
    <p class="text-xs mb-3" style="color:#64748b;">{{ rentals.total }} transaksi</p>

    <!-- Table -->
    <div class="rounded-xl overflow-hidden" style="border:1px solid #2a2a3a;">
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

    <!-- Pagination -->
    <div class="flex gap-1 mt-4 justify-center flex-wrap">
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

    <!-- Detail Drawer -->
    <div v-if="detail" class="mt-4 rounded-xl p-5" style="background:#1a1a26; border:1px solid rgba(139,92,246,.3);">
      <h3 class="text-sm font-semibold text-white mb-3">Detail – {{ detail.transaction_code }}</h3>
      <div class="grid grid-cols-2 gap-y-2 text-sm">
        <div><span style="color:#94a3b8;">Operator</span><p class="text-white">{{ detail.employee.name }}</p></div>
        <div><span style="color:#94a3b8;">Tipe</span><p class="text-white">{{ detail.rental_type }}</p></div>
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
</template>
