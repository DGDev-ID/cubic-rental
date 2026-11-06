<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Printer, ArrowLeft, CreditCard } from 'lucide-vue-next'
import GamingModal from '@/Components/GamingModal.vue'

const printPage = () => window.print()

const props = defineProps<{
  order: {
    id: number
    code: string
    customer_name: string | null
    status: string
    payment_method: string
    total_amount: number
    notes: string | null
    paid_at: string | null
    created_at: string
    employee?: { name: string }
    console?: { name: string }
    items: {
      id: number
      qty: number
      unit_price: number
      subtotal: number
      addons?: { name: string; price: number }[] | null
      fnb_item?: { name: string; category?: string }
    }[]
  }
  store_name: string
}>()

const showEditPayment = ref(false)
const selectedMethod = ref<'cash' | 'qris' | 'transfer' | 'debit'>((props.order.payment_method as any) || 'cash')
const paymentForm = useForm({
  method: (props.order.payment_method as any) || 'cash',
})

function submitPaymentMethod() {
  paymentForm.method = selectedMethod.value
  paymentForm.patch(route('fnb-orders.update-payment-method', props.order.id), {
    preserveScroll: true,
    onSuccess: () => {
      showEditPayment.value = false
    },
  })
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDt(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function methodLabel(m: string | null) {
  if (!m) return 'Cash'
  return { cash: 'Cash', qris: 'QRIS', transfer: 'Transfer', debit: 'Debit' }[m] ?? m.toUpperCase()
}
</script>

<template>
  <div class="receipt-container">
    <div class="no-print mb-4 flex gap-2">
      <button @click="printPage"
        class="px-6 py-2 rounded-xl text-white font-medium flex items-center gap-2"
        style="background:linear-gradient(135deg,#10b981,#059669);">
        <Printer :size="15" /> Cetak Struk
      </button>
      <button type="button" @click="showEditPayment = true"
        class="px-4 py-2 rounded-xl font-medium flex items-center gap-2 transition-all hover:bg-amber-500/20"
        style="background:#1a1a26; border:1px solid rgba(245,158,11,.45); color:#fbbf24;">
        <CreditCard :size="15" /> Ganti Metode Bayar
      </button>
      <a :href="route('rentals.history')"
        class="px-6 py-2 rounded-xl font-medium flex items-center gap-2"
        style="background:#1a1a26; border:1px solid #2a2a3a; color:#94a3b8; text-decoration:none;">
        <ArrowLeft :size="15" /> Riwayat
      </a>
    </div>

    <div class="receipt-paper">
      <!-- Header -->
      <div class="receipt-header">
        <h1>{{ store_name }}</h1>
        <p>Gaming Lounge & PS Cafe</p>
        <div class="receipt-divider">================================</div>
      </div>

      <!-- Info -->
      <table class="receipt-table">
        <tr><td>No. Trx</td><td>: {{ order.code }}</td></tr>
        <tr><td>Customer</td><td>: {{ order.customer_name || 'Walk-in Customer' }}</td></tr>
        <tr><td>Kasir</td><td>: {{ order.employee?.name || '-' }}</td></tr>
        <tr v-if="order.console"><td>Table/Room</td><td>: {{ order.console.name }}</td></tr>
        <tr><td>Waktu</td><td>: {{ formatDt(order.paid_at || order.created_at) }}</td></tr>
        <tr><td>Status</td><td>: LUNAS</td></tr>
      </table>

      <div class="receipt-divider">--------------------------------</div>

      <!-- Items -->
      <p class="receipt-section-title">PESANAN FNB</p>
      <div v-for="it in order.items" :key="it.id" class="receipt-item">
        <div class="receipt-item-name">{{ it.fnb_item?.name || 'Item F&B' }} x{{ it.qty }}</div>
        <div v-for="ad in (it.addons || [])" :key="ad.name" class="receipt-addon">
          + {{ ad.name }}
        </div>
        <div class="receipt-item-price">{{ formatCurrency(it.subtotal) }}</div>
      </div>

      <div class="receipt-divider">--------------------------------</div>

      <!-- Totals -->
      <table class="receipt-table">
        <tr class="receipt-total-row">
          <td><strong>TOTAL</strong></td>
          <td class="text-right"><strong>{{ formatCurrency(order.total_amount) }}</strong></td>
        </tr>
      </table>

      <div class="receipt-divider">--------------------------------</div>

      <!-- Payment -->
      <p class="receipt-section-title">PEMBAYARAN</p>
      <table class="receipt-table">
        <tr>
          <td>{{ methodLabel(order.payment_method) }}</td>
          <td class="text-right">{{ formatCurrency(order.total_amount) }}</td>
        </tr>
      </table>

      <!-- Notes jika ada -->
      <template v-if="order.notes">
        <div class="receipt-divider">--------------------------------</div>
        <p class="receipt-notes">Catatan: {{ order.notes }}</p>
      </template>

      <div class="receipt-divider">================================</div>
      <div class="receipt-footer">
        <p>Terima kasih atas pesanannya!</p>
        <p>Have fun & enjoy your meal 🎮☕</p>
      </div>
    </div>

    <!-- Modal Ubah Metode Pembayaran FnB -->
    <GamingModal :show="showEditPayment" title="Ubah Metode Pembayaran" @close="showEditPayment = false">
      <form @submit.prevent="submitPaymentMethod" class="space-y-4">
        <p class="text-xs text-slate-400">
          Ubah metode pembayaran jika customer berganti metode (misal batal QRIS dan bayar tunai/Cash). Data di struk dan riwayat akan langsung disesuaikan secara otomatis.
        </p>

        <div class="space-y-1.5">
          <label class="block text-xs font-medium text-slate-300">Pilih Metode Baru:</label>
          <div class="grid grid-cols-3 gap-2">
            <button v-for="m in [
              { key: 'cash', label: 'Cash (Tunai)' },
              { key: 'qris', label: 'QRIS' },
              { key: 'transfer', label: 'Transfer' },
            ]" :key="m.key"
              type="button"
              @click="selectedMethod = m.key as any"
              class="py-2.5 px-2 rounded-xl text-xs font-bold transition-all text-center flex flex-col items-center justify-center gap-1"
              :style="selectedMethod === m.key
                ? 'background:rgba(245,158,11,.2); border:1.5px solid #f59e0b; color:#fbbf24;'
                : 'background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;'">
              <span>{{ m.label }}</span>
            </button>
          </div>
        </div>

        <div class="flex gap-2 justify-end pt-2">
          <button type="button" @click="showEditPayment = false"
            class="px-4 py-2 rounded-xl text-xs font-medium"
            style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
            Batal
          </button>
          <button type="submit" :disabled="paymentForm.processing"
            class="px-5 py-2 rounded-xl text-xs font-semibold text-white flex items-center gap-1.5 disabled:opacity-50"
            style="background:linear-gradient(135deg,#f59e0b,#d97706);">
            Simpan Perubahan
          </button>
        </div>
      </form>
    </GamingModal>
  </div>
</template>

<style>
.receipt-container { 
  max-width: 400px; 
  margin: 0 auto; 
  padding: 1rem; 
  font-family: 'Consolas', 'Courier New', Courier, monospace; 
  color: #000;
}
.receipt-paper { 
  background: white; 
  color: #000; 
  padding: 1.25rem 1rem; 
  border-radius: 8px; 
  font-size: 13px; 
  font-weight: 700; 
  line-height: 1.5; 
}
.receipt-header { 
  text-align: center; 
  color: #000;
}
.receipt-header h1 { 
  font-size: 18px; 
  font-weight: 900; 
  margin: 0; 
  color: #000;
  letter-spacing: 0.5px;
}
.receipt-header p { 
  margin: 2px 0; 
  font-size: 12px; 
  font-weight: 700;
  color: #000;
}
.receipt-divider { 
  font-size: 12px; 
  font-weight: 900; 
  color: #000; 
  margin: 6px 0; 
  letter-spacing: -0.5px; 
  overflow: hidden; 
  white-space: nowrap; 
}
.receipt-table { 
  width: 100%; 
  border-collapse: collapse; 
  font-size: 12px; 
  color: #000;
  font-weight: 700;
}
.receipt-table td { 
  padding: 2px 1px; 
  vertical-align: top; 
  color: #000;
  font-weight: 700;
}
.receipt-section-title { 
  font-weight: 900; 
  font-size: 12px; 
  margin: 6px 0 2px; 
  color: #000;
  letter-spacing: 0.5px;
}
.receipt-item { 
  margin: 3px 0; 
  font-size: 12px; 
  color: #000;
  font-weight: 700;
}
.receipt-item-name { 
  font-weight: 800; 
  color: #000;
}
.receipt-addon { 
  padding-left: 8px; 
  color: #000; 
  font-weight: 700;
  font-size: 11px;
}
.receipt-item-price { 
  text-align: right; 
  font-weight: 800;
  color: #000;
}
.receipt-notes {
  font-size: 11px;
  font-weight: 700;
  color: #000;
  margin: 4px 0;
}
.receipt-total-row td { 
  padding-top: 6px; 
  border-top: 1.5px solid #000; 
  font-size: 14px;
  font-weight: 900;
  color: #000;
}
.text-right { 
  text-align: right; 
}
.receipt-footer { 
  text-align: center; 
  font-size: 11px; 
  font-weight: 700;
  margin-top: 6px; 
  color: #000;
}

@media print {
  @page {
    margin: 0;
    size: auto;
  }
  html, body { 
    background: white !important; 
    color: #000 !important;
    margin: 0 !important;
    padding: 0 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .no-print { 
    display: none !important; 
  }
  .receipt-container { 
    max-width: 58mm !important; 
    width: 100% !important;
    margin: 0 !important; 
    padding: 0 !important; 
    font-family: 'Consolas', 'Courier New', Courier, monospace !important;
  }
  .receipt-paper { 
    padding: 6px 2px !important; 
    border-radius: 0 !important; 
    box-shadow: none !important; 
    color: #000 !important;
    font-weight: 800 !important;
    -webkit-text-stroke: 0.35px #000 !important;
  }
  .receipt-paper * {
    color: #000 !important;
    font-weight: 800 !important;
    -webkit-text-stroke: 0.35px #000 !important;
  }
  .receipt-header h1 {
    font-size: 18px !important;
    font-weight: 900 !important;
    -webkit-text-stroke: 0.5px #000 !important;
  }
  .receipt-total-row td {
    font-size: 14px !important;
    font-weight: 900 !important;
    -webkit-text-stroke: 0.5px #000 !important;
    border-top: 2px solid #000 !important;
  }
}
</style>

