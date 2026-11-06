<script setup lang="ts">
import { Printer, ArrowLeft } from 'lucide-vue-next'
const printPage = () => window.print()

defineProps<{
  rental: {
    id: number; transaction_code: string; customer_name: string; rental_type: string;
    started_at: string; ended_at: string; notes: string | null;
    rental_amount: number; fnb_amount: number; extra_amount: number; total_amount: number;
    console: { name: string; type: string }
    employee: { name: string }
    cashier?: { name: string }
    fnb_items: { fnb_item?: { name: string }; fnb_item_name?: string; qty?: number; quantity?: number; unit_price: number; subtotal: number; addons: { name: string; price: number }[] }[]
    payments: { method: string; amount: number; created_at: string }[]
  }
  store_name: string
}>()

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDt(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function methodLabel(m: string) {
  return { cash: 'Cash', qris: 'QRIS', transfer: 'Transfer', debit: 'Debit' }[m] ?? m
}
</script>

<template>
  <div class="receipt-container">
    <div class="no-print mb-4 flex gap-2">
      <button @click="printPage"
        class="px-6 py-2 rounded-xl text-white font-medium flex items-center gap-2"
        style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
        <Printer :size="15" /> Cetak Struk
      </button>
      <a :href="route('rentals.show', rental.id)"
        class="px-6 py-2 rounded-xl font-medium flex items-center gap-2"
        style="background:#1a1a26; border:1px solid #2a2a3a; color:#94a3b8; text-decoration:none;">
        <ArrowLeft :size="15" /> Kembali
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
        <tr><td>No. Trx</td><td>: {{ rental.transaction_code }}</td></tr>
        <tr><td>Customer</td><td>: {{ rental.customer_name }}</td></tr>
        <tr><td>Console</td><td>: {{ rental.console.name }}</td></tr>
        <tr><td>Operator</td><td>: {{ rental.employee.name }}</td></tr>
        <tr v-if="rental.cashier && rental.cashier.name !== rental.employee.name"><td>Kasir</td><td>: {{ rental.cashier.name }}</td></tr>
        <tr><td>Tipe</td><td style="text-transform: capitalize;">: {{ rental.rental_type ? rental.rental_type.replace('_', ' ') : 'Open Time' }}</td></tr>
        <tr><td>Mulai</td><td>: {{ formatDt(rental.started_at) }}</td></tr>
        <tr><td>Selesai</td><td>: {{ rental.ended_at ? formatDt(rental.ended_at) : '-' }}</td></tr>
      </table>

      <div class="receipt-divider">--------------------------------</div>

      <!-- FNB -->
      <div v-if="rental.fnb_items && rental.fnb_items.length">
        <p class="receipt-section-title">PESANAN FNB</p>
        <div v-for="(fi, idx) in rental.fnb_items" :key="idx" class="receipt-item">
          <div class="receipt-item-name">{{ fi.fnb_item?.name || fi.fnb_item_name || 'Item F&B' }} x{{ fi.qty ?? fi.quantity ?? 1 }}</div>
          <div v-for="ad in (fi.addons || [])" :key="ad.name" class="receipt-addon">
            + {{ ad.name }}
          </div>
          <div class="receipt-item-price">{{ formatCurrency(fi.subtotal) }}</div>
        </div>
        <div class="receipt-divider">--------------------------------</div>
      </div>

      <!-- Totals -->
      <table class="receipt-table">
        <tr><td>Rental</td><td class="text-right">{{ formatCurrency(rental.rental_amount) }}</td></tr>
        <tr><td>FNB</td><td class="text-right">{{ formatCurrency(rental.fnb_amount) }}</td></tr>
        <tr v-if="rental.extra_amount"><td>Overtime</td><td class="text-right">{{ formatCurrency(rental.extra_amount) }}</td></tr>
        <tr class="receipt-total-row"><td><strong>TOTAL</strong></td><td class="text-right"><strong>{{ formatCurrency(rental.total_amount) }}</strong></td></tr>
      </table>

      <div class="receipt-divider">--------------------------------</div>

      <!-- Payments -->
      <p class="receipt-section-title">PEMBAYARAN</p>
      <table class="receipt-table">
        <tr v-for="p in rental.payments" :key="p.method + p.amount">
          <td>{{ methodLabel(p.method) }}</td>
          <td class="text-right">{{ formatCurrency(p.amount) }}</td>
        </tr>
      </table>

      <div class="receipt-divider">================================</div>
      <div class="receipt-footer">
        <p>Terima kasih sudah bermain!</p>
        <p>Have fun & come back again 🎮</p>
      </div>
    </div>
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
