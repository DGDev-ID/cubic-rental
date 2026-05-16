<script setup lang="ts">
import { Printer, ArrowLeft } from 'lucide-vue-next'
const printPage = () => window.print()

defineProps<{
  rental: {
    id: number; transaction_code: string; customer_name: string;
    started_at: string; ended_at: string; notes: string | null;
    rental_amount: number; fnb_amount: number; extra_amount: number; total_amount: number;
    console: { name: string; type: string }
    employee: { name: string }
    fnb_items: { fnb_item_name: string; quantity: number; unit_price: number; subtotal: number; addons: { name: string; price: number }[] }[]
    payments: { method: string; amount: number; created_at: string }[]
  }
  store_name: string
}>()

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDt(d: string) {
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
        <tr><td>Tipe</td><td>: Open Time</td></tr>
        <tr><td>Mulai</td><td>: {{ formatDt(rental.started_at) }}</td></tr>
        <tr><td>Selesai</td><td>: {{ formatDt(rental.ended_at) }}</td></tr>
      </table>

      <div class="receipt-divider">--------------------------------</div>

      <!-- FNB -->
      <div v-if="rental.fnb_items.length">
        <p class="receipt-section-title">PESANAN FNB</p>
        <div v-for="fi in rental.fnb_items" :key="fi.fnb_item_name + fi.quantity" class="receipt-item">
          <div class="receipt-item-name">{{ fi.fnb_item_name }} x{{ fi.quantity }}</div>
          <div v-for="ad in fi.addons" :key="ad.name" class="receipt-addon">
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
.receipt-container { max-width: 400px; margin: 0 auto; padding: 1rem; font-family: 'Courier New', monospace; }
.receipt-paper { background: white; color: #000; padding: 1rem; border-radius: 8px; font-size: 12px; line-height: 1.6; }
.receipt-header { text-align: center; }
.receipt-header h1 { font-size: 16px; font-weight: bold; margin: 0; }
.receipt-header p { margin: 2px 0; font-size: 11px; }
.receipt-divider { font-size: 11px; color: #666; margin: 6px 0; letter-spacing: -1px; }
.receipt-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.receipt-table td { padding: 1px 2px; vertical-align: top; }
.receipt-section-title { font-weight: bold; font-size: 11px; margin: 4px 0 2px; }
.receipt-item { margin: 2px 0; font-size: 11px; }
.receipt-item-name { font-weight: 600; }
.receipt-addon { padding-left: 8px; color: #666; }
.receipt-item-price { text-align: right; }
.receipt-total-row td { padding-top: 4px; border-top: 1px solid #000; }
.text-right { text-align: right; }
.receipt-footer { text-align: center; font-size: 11px; margin-top: 4px; }
@media print {
  .no-print { display: none !important; }
  body { background: white !important; }
  .receipt-container { max-width: 58mm; margin: 0; padding: 0; }
  .receipt-paper { padding: 4px; border-radius: 0; box-shadow: none; }
}
</style>
