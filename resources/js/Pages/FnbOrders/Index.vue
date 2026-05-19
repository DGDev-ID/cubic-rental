<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ShoppingCart, Plus, Minus, Trash2, CreditCard, UtensilsCrossed, Search, ChevronRight } from 'lucide-vue-next'

interface FnbItem {
  id: number; name: string; category: string; price: number; is_available: boolean
}
interface FnbAddon {
  id: number; name: string; price: number
}
interface Employee { id: number; name: string }
interface OrderItem {
  id: number; fnb_item_id: number; qty: number; unit_price: number; subtotal: number
  addons: { addon_id: number; name: string; price: number }[] | null; addons_price: number
  fnb_item: FnbItem
}
interface Order {
  id: number; code: string; customer_name: string | null; status: string; payment_method: string | null
  total_amount: number; created_at: string; employee: Employee; items: OrderItem[]
}

const props = defineProps<{
  orders: { data: Order[]; last_page: number; current_page: number }
  fnbItems: FnbItem[]
  fnbAddons: FnbAddon[]
  employees: Employee[]
}>()

// POS State
const searchItem   = ref('')
const filterCat    = ref('all')
const cartItems    = ref<{ item: FnbItem; qty: number; selectedAddons: FnbAddon[] }[]>([])
const showCart     = ref(false)
const showCheckout = ref(false)
const showDetail   = ref<Order | null>(null)

const checkoutForm = useForm({
  employee_id:    null as number | null,
  customer_name:  '',
  notes:          '',
  payment_method: 'cash' as string,
  items:          [] as { fnb_item_id: number; qty: number; addons: { addon_id: number; name: string; price: number }[] }[],
})

const categories = computed(() => {
  const cats = [...new Set(props.fnbItems.map(i => i.category))]
  return cats
})

const filteredItems = computed(() => {
  return props.fnbItems.filter(i => {
    const matchCat    = filterCat.value === 'all' || i.category === filterCat.value
    const matchSearch = i.name.toLowerCase().includes(searchItem.value.toLowerCase())
    return matchCat && matchSearch
  })
})

const cartCount = computed(() => cartItems.value.reduce((s, c) => s + c.qty, 0))

const cartTotal = computed(() => {
  return cartItems.value.reduce((s, c) => {
    const addonsPrice = c.selectedAddons.reduce((a, ad) => a + ad.price, 0)
    return s + (c.item.price + addonsPrice) * c.qty
  }, 0)
})

function addToCart(item: FnbItem) {
  const existing = cartItems.value.find(c => c.item.id === item.id)
  if (existing) {
    existing.qty++
  } else {
    cartItems.value.push({ item, qty: 1, selectedAddons: [] })
  }
}

function removeFromCart(idx: number) {
  cartItems.value.splice(idx, 1)
}

function changeQty(idx: number, delta: number) {
  cartItems.value[idx].qty += delta
  if (cartItems.value[idx].qty <= 0) removeFromCart(idx)
}

function toggleAddon(idx: number, addon: FnbAddon) {
  const existing = cartItems.value[idx].selectedAddons.findIndex(a => a.id === addon.id)
  if (existing >= 0) {
    cartItems.value[idx].selectedAddons.splice(existing, 1)
  } else {
    cartItems.value[idx].selectedAddons.push(addon)
  }
}

function openCheckout() {
  if (cartItems.value.length === 0) return
  showCart.value = false
  showCheckout.value = true
}

function submitOrder() {
  checkoutForm.items = cartItems.value.map(c => ({
    fnb_item_id: c.item.id,
    qty: c.qty,
    addons: c.selectedAddons.map(a => ({ addon_id: a.id, name: a.name, price: a.price })),
  }))

  checkoutForm.post(route('fnb-orders.store'), {
    onSuccess: () => {
      cartItems.value = []
      showCheckout.value = false
      checkoutForm.reset()
    },
  })
}

function payOrder(order: Order | null, method: string) {
  if (!order) return
  router.post(route('fnb-orders.pay', order.id), { payment_method: method }, {
    onSuccess: () => { showDetail.value = null },
  })
}

function cancelOrder(order: Order | null) {
  if (!order) return
  if (!confirm(`Batalkan order ${order.code}?`)) return
  router.delete(route('fnb-orders.destroy', order.id))
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatDate(d: string) {
  return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const categoryColors: Record<string, string> = {
  food: '#f97316', drink: '#06b6d4', snack: '#eab308', dessert: '#ec4899', other: '#8b5cf6'
}
const catColor = (cat: string) => categoryColors[cat] ?? '#8b5cf6'
</script>

<template>
  <AppLayout>
    <template #header-title>
      <h1 class="font-semibold text-white text-lg">Transaksi FnB</h1>
    </template>

    <div class="flex flex-col lg:flex-row gap-4">

      <!-- LEFT: Item Grid -->
      <div class="flex-1 min-w-0">
        <!-- Search + Filter -->
        <div class="flex flex-col sm:flex-row gap-2 mb-4">
          <div class="relative flex-1">
            <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2" style="color:#64748b;" />
            <input v-model="searchItem" type="text" placeholder="Cari item..."
              class="w-full pl-8 pr-3 py-2.5 rounded-xl text-white text-sm outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div class="flex gap-1.5 flex-wrap">
            <button type="button" @click="filterCat = 'all'"
              class="px-3 py-2 rounded-xl text-xs font-medium transition-all capitalize"
              :style="filterCat === 'all' ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;' : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
              Semua
            </button>
            <button v-for="cat in categories" :key="cat" type="button" @click="filterCat = cat"
              class="px-3 py-2 rounded-xl text-xs font-medium transition-all capitalize"
              :style="filterCat === cat
                ? `background:${catColor(cat)}22; color:${catColor(cat)}; border:1px solid ${catColor(cat)}88;`
                : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
              {{ cat }}
            </button>
          </div>
        </div>

        <!-- Items -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
          <button v-for="item in filteredItems" :key="item.id" type="button"
            @click="addToCart(item)"
            class="rounded-xl p-3 text-left transition-all hover:scale-[1.02] active:scale-[0.98]"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-2"
              :style="`background:${catColor(item.category)}22;`">
              <UtensilsCrossed :size="16" :style="`color:${catColor(item.category)}`" />
            </div>
            <p class="font-medium text-white text-sm leading-tight truncate">{{ item.name }}</p>
            <span class="text-xs capitalize mt-0.5" :style="`color:${catColor(item.category)}`">{{ item.category }}</span>
            <p class="font-bold mt-1 text-sm" style="color:#a78bfa;">{{ formatCurrency(item.price) }}</p>
          </button>
          <div v-if="filteredItems.length === 0" class="col-span-full text-center py-12 text-sm" style="color:#64748b;">
            Tidak ada item ditemukan.
          </div>
        </div>
      </div>

      <!-- RIGHT: Cart Button (mobile) + Order History -->
      <div class="lg:w-80 space-y-4">

        <!-- Cart Summary Card -->
        <div class="rounded-xl p-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <ShoppingCart :size="16" style="color:#a78bfa;" />
              <span class="font-semibold text-white text-sm">Keranjang</span>
              <span v-if="cartCount > 0" class="px-2 py-0.5 rounded-full text-xs font-bold"
                style="background:rgba(139,92,246,.3); color:#a78bfa;">{{ cartCount }}</span>
            </div>
            <button v-if="cartItems.length > 0" @click="showCart = true"
              class="text-xs" style="color:#60a5fa;">Lihat detail</button>
          </div>

          <div v-if="cartItems.length === 0" class="text-xs text-center py-4" style="color:#64748b;">
            Belum ada item dipilih.<br>Klik item di kiri untuk menambahkan.
          </div>

          <template v-else>
            <div class="space-y-1 mb-3 max-h-48 overflow-y-auto">
              <div v-for="(c, idx) in cartItems" :key="c.item.id"
                class="flex items-center justify-between text-sm">
                <span class="text-white truncate flex-1">{{ c.item.name }}</span>
                <div class="flex items-center gap-1.5 ml-2 shrink-0">
                  <button @click="changeQty(idx, -1)"
                    class="w-5 h-5 rounded flex items-center justify-center"
                    style="background:#2a2a3a; color:#94a3b8;">
                    <Minus :size="10" />
                  </button>
                  <span class="text-white w-5 text-center text-xs">{{ c.qty }}</span>
                  <button @click="changeQty(idx, 1)"
                    class="w-5 h-5 rounded flex items-center justify-center"
                    style="background:#2a2a3a; color:#94a3b8;">
                    <Plus :size="10" />
                  </button>
                </div>
              </div>
            </div>
            <div class="flex justify-between items-center pt-2 mb-3" style="border-top:1px solid #2a2a3a;">
              <span class="text-sm" style="color:#94a3b8;">Total</span>
              <span class="font-bold text-base" style="color:#22d3ee;">{{ formatCurrency(cartTotal) }}</span>
            </div>
            <button @click="openCheckout"
              class="w-full py-2.5 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2"
              style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
              <CreditCard :size="14" /> Proses Pembayaran
            </button>
          </template>
        </div>

        <!-- Recent Orders -->
        <div class="rounded-xl" style="background:#1a1a26; border:1px solid #2a2a3a;">
          <div class="px-4 pt-4 pb-2">
            <p class="font-semibold text-white text-sm">Order Terbaru</p>
          </div>
          <div class="divide-y" style="border-color:#2a2a3a;">
            <div v-for="order in orders.data.slice(0, 8)" :key="order.id"
              class="px-4 py-3 flex items-center justify-between cursor-pointer hover:bg-white/5 transition-colors"
              @click="showDetail = order">
              <div class="min-w-0">
                <p class="text-xs font-mono font-medium" style="color:#60a5fa;">{{ order.code }}</p>
                <p class="text-xs truncate" style="color:#94a3b8;">{{ order.customer_name || 'Tanpa nama' }}</p>
                <p class="text-xs font-bold" style="color:#a78bfa;">{{ formatCurrency(order.total_amount) }}</p>
              </div>
              <div class="flex items-center gap-2 shrink-0 ml-2">
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                  :style="order.status === 'paid'
                    ? 'background:rgba(16,185,129,.15); color:#34d399;'
                    : 'background:rgba(251,191,36,.15); color:#fbbf24;'">
                  {{ order.status === 'paid' ? 'Lunas' : 'Belum Bayar' }}
                </span>
                <ChevronRight :size="14" style="color:#64748b;" />
              </div>
            </div>
            <div v-if="orders.data.length === 0" class="px-4 py-6 text-center text-xs" style="color:#64748b;">
              Belum ada order.
            </div>
          </div>
          <!-- Pagination link -->
          <div v-if="orders.last_page > 1" class="px-4 py-2 flex gap-2 justify-end">
            <a v-if="orders.current_page > 1" :href="route('fnb-orders.index') + '?page=' + (orders.current_page - 1)"
              class="text-xs px-3 py-1 rounded-lg" style="background:#2a2a3a; color:#94a3b8;">« Prev</a>
            <a v-if="orders.current_page < orders.last_page" :href="route('fnb-orders.index') + '?page=' + (orders.current_page + 1)"
              class="text-xs px-3 py-1 rounded-lg" style="background:#2a2a3a; color:#94a3b8;">Next »</a>
          </div>
        </div>
      </div>
    </div>

    <!-- ======= CART DETAIL MODAL ======= -->
    <div v-if="showCart" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" style="background:rgba(0,0,0,.7);">
      <div class="w-full max-w-md rounded-2xl p-5 space-y-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center justify-between">
          <h2 class="font-bold text-white">Keranjang</h2>
          <button @click="showCart = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
        </div>

        <div class="space-y-3 max-h-72 overflow-y-auto">
          <div v-for="(c, idx) in cartItems" :key="c.item.id"
            class="rounded-xl p-3" style="background:#12121a; border:1px solid #2a2a3a;">
            <div class="flex items-center justify-between mb-2">
              <span class="font-medium text-white text-sm">{{ c.item.name }}</span>
              <button @click="removeFromCart(idx)" class="text-red-400 hover:text-red-300">
                <Trash2 :size="14" />
              </button>
            </div>
            <!-- Addons -->
            <div v-if="fnbAddons.length > 0" class="flex flex-wrap gap-1.5 mb-2">
              <button v-for="addon in fnbAddons" :key="addon.id" type="button"
                @click="toggleAddon(idx, addon)"
                class="px-2 py-0.5 rounded-full text-xs transition-all"
                :style="c.selectedAddons.some(a => a.id === addon.id)
                  ? 'background:rgba(139,92,246,.3); color:#a78bfa; border:1px solid rgba(139,92,246,.5);'
                  : 'background:#1a1a26; color:#94a3b8; border:1px solid #3a3a4a;'">
                {{ addon.name }} +{{ formatCurrency(addon.price) }}
              </button>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <button @click="changeQty(idx, -1)"
                  class="w-7 h-7 rounded-lg flex items-center justify-center"
                  style="background:#2a2a3a; color:#94a3b8;">
                  <Minus :size="12" />
                </button>
                <span class="text-white font-bold w-6 text-center">{{ c.qty }}</span>
                <button @click="changeQty(idx, 1)"
                  class="w-7 h-7 rounded-lg flex items-center justify-center"
                  style="background:#2a2a3a; color:#94a3b8;">
                  <Plus :size="12" />
                </button>
              </div>
              <span class="font-bold text-sm" style="color:#a78bfa;">
                {{ formatCurrency((c.item.price + c.selectedAddons.reduce((s,a)=>s+a.price,0)) * c.qty) }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex justify-between items-center pt-1" style="border-top:1px solid #2a2a3a;">
          <span style="color:#94a3b8;">Total</span>
          <span class="font-bold text-lg" style="color:#22d3ee;">{{ formatCurrency(cartTotal) }}</span>
        </div>

        <button @click="openCheckout"
          class="w-full py-3 rounded-xl text-white font-semibold flex items-center justify-center gap-2"
          style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
          <CreditCard :size="15" /> Lanjut ke Pembayaran
        </button>
      </div>
    </div>

    <!-- ======= CHECKOUT MODAL ======= -->
    <div v-if="showCheckout" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.7);">
      <div class="w-full max-w-md rounded-2xl p-5 space-y-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center justify-between">
          <h2 class="font-bold text-white">Checkout</h2>
          <button @click="showCheckout = false; showCart = true" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
        </div>

        <!-- Order summary -->
        <div class="rounded-xl p-3 space-y-1" style="background:#12121a;">
          <div v-for="c in cartItems" :key="c.item.id" class="flex justify-between text-sm">
            <span style="color:#94a3b8;">{{ c.item.name }} x{{ c.qty }}</span>
            <span class="text-white">{{ formatCurrency((c.item.price + c.selectedAddons.reduce((s,a)=>s+a.price,0)) * c.qty) }}</span>
          </div>
          <div class="flex justify-between font-bold pt-1" style="border-top:1px solid #2a2a3a;">
            <span class="text-white">Total</span>
            <span style="color:#22d3ee;">{{ formatCurrency(cartTotal) }}</span>
          </div>
        </div>

        <!-- Form fields -->
        <div class="space-y-3">
          <div>
            <label class="text-xs mb-1 block" style="color:#94a3b8;">Operator *</label>
            <select v-model="checkoutForm.employee_id" required
              class="w-full px-3 py-2.5 rounded-xl text-white text-sm outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;">
              <option :value="null">-- Pilih Operator --</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:#94a3b8;">Nama Customer (opsional)</label>
            <input v-model="checkoutForm.customer_name" type="text" placeholder="Nama customer..."
              class="w-full px-3 py-2.5 rounded-xl text-white text-sm outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:#94a3b8;">Metode Pembayaran *</label>
            <div class="flex gap-2">
              <button v-for="m in ['cash', 'qris']" :key="m" type="button"
                @click="checkoutForm.payment_method = m"
                class="flex-1 py-2 rounded-xl text-xs font-semibold capitalize transition-all"
                :style="checkoutForm.payment_method === m
                  ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;'
                  : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
                {{ m.toUpperCase() }}
              </button>
            </div>
          </div>
          <div>
            <label class="text-xs mb-1 block" style="color:#94a3b8;">Catatan</label>
            <input v-model="checkoutForm.notes" type="text" placeholder="Catatan opsional..."
              class="w-full px-3 py-2.5 rounded-xl text-white text-sm outline-none"
              style="background:#12121a; border:1px solid #2a2a3a;" />
          </div>
        </div>

        <button @click="submitOrder" :disabled="checkoutForm.processing || !checkoutForm.employee_id"
          class="w-full py-3 rounded-xl text-white font-semibold flex items-center justify-center gap-2 disabled:opacity-60"
          style="background:linear-gradient(135deg,#10b981,#059669);">
          <CreditCard :size="15" /> Bayar & Selesai
        </button>
      </div>
    </div>

    <!-- ======= ORDER DETAIL MODAL ======= -->
    <div v-if="showDetail" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.7);">
      <div class="w-full max-w-md rounded-2xl p-5 space-y-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="font-bold text-white">{{ showDetail.code }}</h2>
            <p class="text-xs" style="color:#94a3b8;">{{ formatDate(showDetail.created_at) }}</p>
          </div>
          <button @click="showDetail = null" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
        </div>

        <!-- Status badge -->
        <div class="flex items-center gap-2">
          <span class="px-3 py-1 rounded-full text-xs font-semibold"
            :style="showDetail.status === 'paid'
              ? 'background:rgba(16,185,129,.15); color:#34d399;'
              : 'background:rgba(251,191,36,.15); color:#fbbf24;'">
            {{ showDetail.status === 'paid' ? '✓ Lunas' : '⏳ Belum Bayar' }}
          </span>
          <span v-if="showDetail.payment_method" class="text-xs uppercase font-semibold px-2 py-1 rounded-lg"
            style="background:#2a2a3a; color:#94a3b8;">
            {{ showDetail.payment_method }}
          </span>
        </div>

        <!-- Items -->
        <div class="rounded-xl p-3 space-y-1.5" style="background:#12121a;">
          <div v-for="item in showDetail.items" :key="item.id">
            <div class="flex justify-between text-sm">
              <span class="text-white">{{ item.fnb_item?.name }} x{{ item.qty }}</span>
              <span style="color:#a78bfa;">{{ formatCurrency(item.subtotal) }}</span>
            </div>
            <div v-if="item.addons?.length" class="text-xs pl-2" style="color:#64748b;">
              + {{ item.addons.map(a => a.name).join(', ') }}
            </div>
          </div>
          <div class="flex justify-between font-bold pt-1" style="border-top:1px solid #2a2a3a;">
            <span class="text-white">Total</span>
            <span style="color:#22d3ee;">{{ formatCurrency(showDetail.total_amount) }}</span>
          </div>
        </div>

        <div class="flex items-center justify-between text-sm">
          <span style="color:#94a3b8;">Operator: {{ showDetail.employee?.name }}</span>
          <span v-if="showDetail.customer_name" style="color:#94a3b8;">{{ showDetail.customer_name }}</span>
        </div>

        <!-- Actions -->
        <div v-if="showDetail.status === 'pending'" class="space-y-2">
          <p class="text-xs font-medium" style="color:#94a3b8;">Bayar dengan:</p>
          <div class="flex gap-2">
            <button v-for="m in ['cash','qris']" :key="m" type="button"
              @click="payOrder(showDetail, m)"
              class="flex-1 py-2 rounded-xl text-xs font-semibold uppercase"
              style="background:linear-gradient(135deg,#10b981,#059669); color:white;">
              {{ m }}
            </button>
          </div>
          <button @click="cancelOrder(showDetail)"
            class="w-full py-2 rounded-xl text-xs font-semibold"
            style="background:rgba(239,68,68,.15); color:#f87171; border:1px solid rgba(239,68,68,.3);">
            Batalkan Order
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
