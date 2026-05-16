<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Banknote, QrCode, Wallet } from 'lucide-vue-next'

const props = defineProps<{
  rental: {
    id: number; transaction_code: string; customer_name: string;
    started_at: string; ended_at: string | null;
    rental_amount: number; fnb_amount: number; extra_amount: number; total_amount: number;
    console: { name: string; type: string }
    payments: { method: string; amount: number }[]
    paid_amount: number; remaining_amount: number
    is_running?: boolean
  }
}>()

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDt(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

const method = ref<'cash' | 'qris'>('cash')
const amount = ref(props.rental.remaining_amount)
const submitting = ref(false)

const change = computed(() => amount.value - props.rental.remaining_amount)
const canSubmit = computed(() => amount.value >= 1 && !submitting.value)

function submit() {
  submitting.value = true
  router.post(route('rentals.pay', props.rental.id), {
    payments: [{ method: method.value, amount: amount.value }]
  }, { onFinish: () => { submitting.value = false } })
}
</script>

<template>
  <AppLayout>
    <template #header-title>
      <div>
        <h1 class="font-semibold text-white text-lg">
          {{ rental.is_running ? 'Bayar DP / Titip Uang' : 'Pembayaran' }}
        </h1>
        <p class="text-xs" style="color:#94a3b8;">{{ rental.transaction_code }}</p>
      </div>
    </template>

    <div class="max-w-xl mx-auto space-y-4">

      <!-- Info banner kalau masih running -->
      <div v-if="rental.is_running"
        class="rounded-xl px-5 py-4 flex items-start gap-3"
        style="background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.3);">
        <span style="color:#fbbf24;" class="text-lg mt-0.5">⚠</span>
        <div class="text-sm" style="color:#fef3c7;">
          Rental <strong>masih berjalan</strong>. Pembayaran ini akan dicatat sebagai <strong>DP / titip uang</strong>.
          Sisa tagihan diselesaikan saat rental berakhir.
        </div>
      </div>

      <!-- Ringkasan -->
      <div class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <h3 class="text-sm font-semibold text-white mb-4">Ringkasan</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span style="color:#94a3b8;">Customer</span>
            <span class="text-white">{{ rental.customer_name }}</span>
          </div>
          <div class="flex justify-between">
            <span style="color:#94a3b8;">Console</span>
            <span class="text-white">{{ rental.console.name }}</span>
          </div>
          <div class="flex justify-between">
            <span style="color:#94a3b8;">Mulai</span>
            <span class="text-white">{{ formatDt(rental.started_at) }}</span>
          </div>
          <div v-if="rental.ended_at" class="flex justify-between">
            <span style="color:#94a3b8;">Selesai</span>
            <span class="text-white">{{ formatDt(rental.ended_at) }}</span>
          </div>
          <div class="mt-2 pt-2 space-y-2" style="border-top:1px solid #2a2a3a;">
            <div class="flex justify-between">
              <span style="color:#94a3b8;">Biaya Rental</span>
              <span class="text-white">{{ formatCurrency(rental.rental_amount) }}</span>
            </div>
            <div class="flex justify-between">
              <span style="color:#94a3b8;">FNB</span>
              <span class="text-white">{{ formatCurrency(rental.fnb_amount) }}</span>
            </div>
            <div v-if="rental.extra_amount" class="flex justify-between">
              <span class="text-red-400">Overtime</span>
              <span class="text-red-400">{{ formatCurrency(rental.extra_amount) }}</span>
            </div>
            <div class="flex justify-between font-bold text-base pt-1" style="border-top:1px solid #2a2a3a;">
              <span class="text-white">Total</span>
              <span style="color:#a78bfa;">{{ formatCurrency(rental.total_amount) }}</span>
            </div>
            <div v-if="rental.paid_amount > 0" class="flex justify-between">
              <span style="color:#94a3b8;">Sudah DP</span>
              <span class="text-green-400 font-semibold">– {{ formatCurrency(rental.paid_amount) }}</span>
            </div>
            <div class="flex justify-between font-semibold">
              <span style="color:#fbbf24;">Sisa Tagihan</span>
              <span class="text-yellow-400">{{ formatCurrency(rental.remaining_amount) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Form Bayar -->
      <div class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <h3 class="text-sm font-semibold text-white mb-4">Metode Pembayaran</h3>

        <!-- Pilih metode -->
        <div class="grid grid-cols-2 gap-3 mb-5">
          <button type="button" @click="method = 'cash'"
            class="py-4 rounded-xl flex flex-col items-center gap-2 transition-all"
            :style="method === 'cash'
              ? 'background:rgba(139,92,246,.2); border:2px solid rgba(139,92,246,.7);'
              : 'background:#12121a; border:2px solid #2a2a3a;'">
            <Banknote :size="24" :style="method === 'cash' ? 'color:#a78bfa;' : 'color:#64748b;'" />
            <span class="text-sm font-semibold" :style="method === 'cash' ? 'color:#a78bfa;' : 'color:#94a3b8;'">Cash</span>
          </button>
          <button type="button" @click="method = 'qris'"
            class="py-4 rounded-xl flex flex-col items-center gap-2 transition-all"
            :style="method === 'qris'
              ? 'background:rgba(34,197,94,.2); border:2px solid rgba(34,197,94,.6);'
              : 'background:#12121a; border:2px solid #2a2a3a;'">
            <QrCode :size="24" :style="method === 'qris' ? 'color:#22c55e;' : 'color:#64748b;'" />
            <span class="text-sm font-semibold" :style="method === 'qris' ? 'color:#22c55e;' : 'color:#94a3b8;'">QRIS</span>
          </button>
        </div>

        <!-- Nominal -->
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nominal Dibayar</label>
          <input v-model.number="amount" type="number" min="1"
            class="w-full px-4 py-3 rounded-xl text-white text-lg font-semibold outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>

        <!-- Kembalian / kurang (hanya cash) -->
        <div v-if="method === 'cash' && amount > 0 && change >= 0"
          class="mt-3 flex justify-between items-center px-4 py-3 rounded-xl"
          style="background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3);">
          <span class="text-sm" style="color:#94a3b8;">Kembalian</span>
          <span class="text-green-400 font-bold text-base">{{ formatCurrency(change) }}</span>
        </div>
        <div v-else-if="method === 'cash' && amount > 0 && change < 0"
          class="mt-3 flex justify-between items-center px-4 py-3 rounded-xl"
          style="background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3);">
          <span class="text-sm" style="color:#94a3b8;">Kurang</span>
          <span class="text-red-400 font-bold text-base">{{ formatCurrency(Math.abs(change)) }}</span>
        </div>
      </div>

      <button @click="submit" :disabled="!canSubmit"
        class="w-full py-4 rounded-xl text-white font-semibold text-base transition-all disabled:opacity-50 flex items-center justify-center gap-2"
        style="background:linear-gradient(135deg,#22c55e,#16a34a);">
        <Wallet :size="18" />
        {{ rental.is_running ? 'Simpan DP' : (change < 0 ? 'Bayar Sebagian' : 'Bayar Lunas') }}
      </button>

    </div>
  </AppLayout>
</template>
