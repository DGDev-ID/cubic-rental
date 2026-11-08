<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import {
  Clock, UtensilsCrossed, CheckCircle, Plus, Trash2, Wallet,
  ArrowLeftRight, StickyNote, ArrowLeft, Receipt, Pencil
} from 'lucide-vue-next'

interface FnbAddon { id: number; name: string; price: number }
interface FnbItem { id: number; name: string; category: string; price: number; is_available: boolean }
interface Extension { id: number; added_minutes: number; additional_price: number; created_at: string }
interface FnbOrderItem {
  id: number; fnb_item_id: number; fnb_item: { name: string }; qty: number;
  unit_price: number; subtotal: number; addons: { name: string; price: number }[] | null
}
interface ConsoleItem {
  id: number; name: string; type: string; price_per_hour: number; status: string
}

const props = defineProps<{
  rental: {
    id: number; transaction_code: string; customer_name: string; rental_type: string;
    status: string; started_at: string; scheduled_end_at: string | null; ended_at?: string | null;
    rental_amount: number; fnb_amount: number; extra_amount: number; total_amount: number;
    paid_amount: number;
    notes: string | null; duration_minutes: number; remaining_minutes: number; is_overtime: boolean;
    console: { id: number; name: string; type: string; price_per_hour: number }
    employee: { id: number; name: string }
    extensions: Extension[]
    fnb_items: FnbOrderItem[]
  }
  fnb_items: FnbItem[]
  fnb_addons: FnbAddon[]
  available_consoles?: ConsoleItem[]
}>()

const now = ref(new Date())
let timer: ReturnType<typeof setInterval>
onMounted(() => { timer = setInterval(() => { now.value = new Date() }, 1000) })
onUnmounted(() => clearInterval(timer))

// Rental dianggap selesai jika ended_at sudah tercatat atau status finished
const isFinishedOrPaid = computed(() => !!props.rental.ended_at || props.rental.status === 'finished')

// FNB bisa ditambah / diedit / dihapus jika rental belum dibatalkan dan belum lunas tanpa sisa
const canManageFnb = computed(() => {
  if (props.rental.status === 'cancelled') return false
  if (props.rental.status === 'paid' && remainingPayment.value <= 0) return false
  return true
})

const liveDuration = computed(() => {
  const endTime = props.rental.ended_at
    ? new Date(props.rental.ended_at).getTime()
    : now.value.getTime()
  const diff = Math.max(0, Math.floor((endTime - new Date(props.rental.started_at).getTime()) / 60000))
  return `${Math.floor(diff / 60)}j ${diff % 60}m`
})

const liveRemaining = computed(() => {
  if (isFinishedOrPaid.value) return null
  if (!props.rental.scheduled_end_at) return null
  const diff = Math.floor((new Date(props.rental.scheduled_end_at).getTime() - now.value.getTime()) / 60000)
  if (diff < 0) return `Overtime ${Math.abs(diff)}m`
  return `${Math.floor(diff / 60)}j ${diff % 60}m`
})

// Hitung estimasi harga rental yang sedang berjalan (tidak boleh Rp 0)
const currentRentalAmount = computed(() => {
  const rate = Number(props.rental.console?.price_per_hour || 0)
  if (props.rental.rental_type === 'member' || (props.rental as any).member_id) {
    return 0
  }
  // Jika rental sudah ada nilai rental_amount di database (> 0), gunakan itu
  if (Number(props.rental.rental_amount) > 0) {
    return Number(props.rental.rental_amount)
  }

  // Waktu Tetap (Durasi)
  if (props.rental.scheduled_end_at) {
    const schedMin = Math.round((new Date(props.rental.scheduled_end_at).getTime() - new Date(props.rental.started_at).getTime()) / 60000)
    const extMin = (props.rental.extensions || []).reduce((acc, curr) => acc + (Number(curr.added_minutes) || 0), 0)
    const baseMin = Math.max(0, schedMin - extMin)
    return Math.round(((rate / 60) * baseMin) / 1000) * 1000
  }

  // Open Time: minimal 60 menit, dibulatkan per kelipatan 10 menit
  const elapsed = Math.floor((now.value.getTime() - new Date(props.rental.started_at).getTime()) / 60000)
  const billed = Math.ceil(Math.max(elapsed, 60) / 10) * 10
  return Math.round(((rate / 60) * billed) / 1000) * 1000
})

// Total tagihan aktif = Rental + FNB + Extra Waktu (Overtime tidak menambah biaya)
const currentTotalAmount = computed(() => {
  return currentRentalAmount.value + Number(props.rental.fnb_amount || 0) + Number(props.rental.extra_amount || 0)
})

const remainingPayment = computed(() => {
  return Math.max(0, currentTotalAmount.value - Number(props.rental.paid_amount || 0))
})

function formatCurrency(v: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v)
}
function formatTime(d: string) {
  return new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

// Add Time form
const showAddTime = ref(false)
const addTimeForm = useForm({ added_minutes: 60, additional_price: 0, notes: '' })

function openAddTimeModal() {
  const rate = Number(props.rental.console?.price_per_hour || 0)
  addTimeForm.added_minutes = 60
  addTimeForm.additional_price = Math.round(((rate / 60) * 60) / 1000) * 1000
  addTimeForm.notes = ''
  showAddTime.value = true
}

function setAddTimeMinutes(d: number) {
  addTimeForm.added_minutes = d
  const rate = Number(props.rental.console?.price_per_hour || 0)
  addTimeForm.additional_price = Math.round(((rate / 60) * d) / 1000) * 1000
}

function onAddTimeMinutesInput() {
  const rate = Number(props.rental.console?.price_per_hour || 0)
  const d = Number(addTimeForm.added_minutes) || 0
  addTimeForm.additional_price = Math.round(((rate / 60) * d) / 1000) * 1000
}

function submitAddTime() {
  addTimeForm.post(route('rentals.add-time', props.rental.id), {
    onSuccess: () => { showAddTime.value = false; addTimeForm.reset() },
  })
}

// Add FNB form
const showAddFnb = ref(false)
interface FnbLine { fnb_item_id: number | null; quantity: number; notes: string; addon_ids: number[] }
const fnbLines = ref<FnbLine[]>([{ fnb_item_id: null, quantity: 1, notes: '', addon_ids: [] }])
function addFnbLine() { fnbLines.value.push({ fnb_item_id: null, quantity: 1, notes: '', addon_ids: [] }) }
function removeFnbLine(i: number) { fnbLines.value.splice(i, 1) }
const submittingFnb = ref(false)
function submitAddFnb() {
  submittingFnb.value = true
  const items = fnbLines.value
    .filter(l => l.fnb_item_id)
    .map(l => ({
      fnb_item_id: l.fnb_item_id,
      qty: l.quantity,
      addons: l.addon_ids.map(id => {
        const ad = props.fnb_addons.find(a => a.id === id)!
        return { addon_id: id, name: ad.name, price: ad.price }
      }),
    }))
  router.post(route('rentals.add-fnb', props.rental.id),
    { items },
    { onSuccess: () => { showAddFnb.value = false; fnbLines.value = [{ fnb_item_id: null, quantity: 1, notes: '', addon_ids: [] }] },
      onFinish: () => { submittingFnb.value = false } }
  )
}

// Finish
const showFinish = ref(false)
const finishing = ref(false)
function doFinish() {
  finishing.value = true
  router.post(route('rentals.finish', props.rental.id), {}, {
    onFinish: () => { finishing.value = false; showFinish.value = false }
  })
}

// Edit FNB item
interface EditingFnb {
  id: number
  fnb_item_id: number
  qty: number
  addon_ids: number[]
}
const showEditFnb = ref(false)
const editingFnb = ref<EditingFnb | null>(null)
const submittingEditFnb = ref(false)

function openEditFnb(item: FnbOrderItem) {
  const matchedAddonIds: number[] = []
  if (item.addons && Array.isArray(item.addons)) {
    item.addons.forEach(ad => {
      const found = props.fnb_addons.find(a => (ad as any).addon_id === a.id || a.name === ad.name)
      if (found && !matchedAddonIds.includes(found.id)) {
        matchedAddonIds.push(found.id)
      }
    })
  }

  editingFnb.value = {
    id: item.id,
    fnb_item_id: item.fnb_item_id,
    qty: item.qty,
    addon_ids: matchedAddonIds,
  }
  showEditFnb.value = true
}

const editingSubtotal = computed(() => {
  if (!editingFnb.value || !editingFnb.value.fnb_item_id) return 0
  const selectedItem = props.fnb_items.find(i => i.id === editingFnb.value?.fnb_item_id)
  if (!selectedItem) return 0
  const addonsTotal = editingFnb.value.addon_ids.reduce((sum, id) => {
    const ad = props.fnb_addons.find(a => a.id === id)
    return sum + (ad ? Number(ad.price) : 0)
  }, 0)
  return (Number(selectedItem.price) + addonsTotal) * (editingFnb.value.qty || 1)
})

function submitEditFnb() {
  if (!editingFnb.value || !editingFnb.value.fnb_item_id) return
  submittingEditFnb.value = true

  const selectedAddons = editingFnb.value.addon_ids.map(id => {
    const ad = props.fnb_addons.find(a => a.id === id)!
    return { addon_id: id, name: ad.name, price: Number(ad.price) }
  })

  router.patch(route('rentals.update-fnb', { rental: props.rental.id, fnbItem: editingFnb.value.id }), {
    fnb_item_id: editingFnb.value.fnb_item_id,
    qty: editingFnb.value.qty,
    addons: selectedAddons,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showEditFnb.value = false
      editingFnb.value = null
    },
    onFinish: () => {
      submittingEditFnb.value = false
    }
  })
}

// Remove FNB item with confirmation
const showDeleteFnbModal = ref(false)
const itemToDelete = ref<FnbOrderItem | null>(null)
function confirmRemoveFnb(item: FnbOrderItem) {
  itemToDelete.value = item
  showDeleteFnbModal.value = true
}
function submitRemoveFnb() {
  if (!itemToDelete.value) return
  router.delete(route('rentals.remove-fnb', { rental: props.rental.id, fnbItem: itemToDelete.value.id }), {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteFnbModal.value = false
      itemToDelete.value = null
      if (showEditFnb.value) showEditFnb.value = false
    }
  })
}
function showDeleteFnbFromEdit() {
  if (!editingFnb.value) return
  const currentItem = props.rental.fnb_items.find(i => i.id === editingFnb.value?.id)
  if (currentItem) {
    confirmRemoveFnb(currentItem)
  }
}

// Pindah Ruangan (Switch Console)
const showSwitchModal = ref(false)
const switchForm = useForm({
  new_console_id: null as number | null,
})
function submitSwitch() {
  if (!switchForm.new_console_id) return
  switchForm.post(route('rentals.switch-console', props.rental.id), {
    onSuccess: () => { showSwitchModal.value = false; switchForm.reset() },
  })
}

// Catatan Console
const showNotesModal = ref(false)
const notesForm = useForm({
  notes: props.rental.notes || '',
})
function submitNotes() {
  notesForm.patch(route('rentals.update-notes', props.rental.id), {
    onSuccess: () => { showNotesModal.value = false },
  })
}
</script>

<template>
  <AppLayout>
    <template #header-title>
      <div class="flex items-center gap-3">
        <a :href="route('room-monitor')"
          class="p-2 rounded-xl text-slate-400 hover:text-white transition-colors bg-[#1a1a26] border border-[#2a2a3a]"
          title="Kembali ke Room Monitor">
          <ArrowLeft :size="16" />
        </a>
        <div>
          <h1 class="font-semibold text-white text-lg">{{ rental.console.name }}</h1>
          <p class="text-xs" style="color:#94a3b8;">{{ rental.transaction_code }}</p>
        </div>
      </div>
    </template>
    <template #header-actions>
      <div class="flex flex-wrap gap-2">
        <template v-if="isFinishedOrPaid">
          <span v-if="rental.status === 'paid'"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-1.5"
            style="background:rgba(34,197,94,.15); color:#22c55e; border:1px solid rgba(34,197,94,.3);">
            ✓ Selesai & Lunas
          </span>
          <span v-else
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-1.5"
            style="background:rgba(234,179,8,.15); color:#eab308; border:1px solid rgba(234,179,8,.3);">
            Selesai (Menunggu Pembayaran)
          </span>
          <button @click="showNotesModal = true"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-white transition-all flex items-center gap-1.5"
            style="background:rgba(234,179,8,.2); border:1px solid rgba(234,179,8,.4);"
            title="Catatan Transaksi">
            <StickyNote :size="14" /> Catatan
          </button>
          <button v-if="canManageFnb" @click="showAddFnb = true"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-white transition-all flex items-center gap-1.5"
            style="background:rgba(34,197,94,.2); border:1px solid rgba(34,197,94,.4);"
            title="Tambah Pesanan FNB">
            <UtensilsCrossed :size="14" /> + FNB
          </button>
          <a :href="route('rentals.receipt', rental.id)" target="_blank"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-emerald-400 transition-all flex items-center gap-1.5"
            style="background:rgba(16,185,129,.15); border:1px solid rgba(16,185,129,.3);">
            <Receipt :size="14" /> Cetak Struk
          </a>
          <a v-if="remainingPayment > 0" :href="route('rentals.payment', rental.id)"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white transition-all flex items-center gap-1.5 shadow-md shadow-emerald-500/20"
            style="background:linear-gradient(135deg,#10b981,#059669);">
            <Wallet :size="14" /> Bayar Sisa Tagihan
          </a>
        </template>
        <template v-else>
          <button @click="showSwitchModal = true"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-white transition-all flex items-center gap-1.5"
            style="background:rgba(168,85,247,.2); border:1px solid rgba(168,85,247,.4);"
            title="Pindah Ruangan / Console">
            <ArrowLeftRight :size="14" /> Pindah Room
          </button>
          <button @click="showNotesModal = true"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-white transition-all flex items-center gap-1.5"
            style="background:rgba(234,179,8,.2); border:1px solid rgba(234,179,8,.4);"
            title="Catatan Console">
            <StickyNote :size="14" /> Catatan
          </button>
          <button @click="openAddTimeModal"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-white transition-all flex items-center gap-1.5"
            style="background:rgba(59,130,246,.2); border:1px solid rgba(59,130,246,.4);">
            <Clock :size="14" /> + Waktu
          </button>
          <button @click="showAddFnb = true"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-white transition-all flex items-center gap-1.5"
            style="background:rgba(34,197,94,.2); border:1px solid rgba(34,197,94,.4);">
            <UtensilsCrossed :size="14" /> + FNB
          </button>
          <a :href="route('rentals.payment', rental.id)"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-white transition-all flex items-center gap-1.5"
            style="background:rgba(251,191,36,.2); border:1px solid rgba(251,191,36,.4);">
            <Wallet :size="14" /> Bayar DP
          </a>
          <button @click="showFinish = true"
            class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white transition-all flex items-center gap-1.5 shadow-md shadow-red-500/20"
            style="background:linear-gradient(135deg,#ef4444,#dc2626);">
            <CheckCircle :size="14" /> Selesai
          </button>
        </template>
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Left: Info -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Timer Card -->
        <div class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
          <div class="flex items-center gap-4">
            <div class="text-5xl font-mono font-bold" :style="rental.is_overtime ? 'color:#ef4444;' : 'color:#a78bfa;'">
              {{ liveDuration }}
            </div>
            <div class="ml-auto text-right">
              <p class="text-xs" style="color:#94a3b8;">Mulai</p>
              <p class="text-white font-medium">{{ formatTime(rental.started_at) }}</p>
              <template v-if="liveRemaining">
                <p class="text-xs mt-2" style="color:#94a3b8;">Sisa</p>
                <p class="font-bold" :style="rental.is_overtime ? 'color:#ef4444;' : 'color:#22d3ee;'">{{ liveRemaining }}</p>
              </template>
            </div>
          </div>
          <div class="mt-3 pt-3 flex flex-wrap gap-4 text-sm" style="border-top:1px solid #2a2a3a;">
            <span style="color:#94a3b8;">Customer: <span class="text-white font-medium">{{ rental.customer_name || 'Customer' }}</span></span>
            <span style="color:#94a3b8;">Operator: <span class="text-white font-medium">{{ rental.employee.name }}</span></span>
            <span style="color:#94a3b8;">Tipe:
              <span class="font-medium capitalize" style="color:#a78bfa;">
                {{ rental.rental_type === 'duration' ? `Durasi (${Math.round((new Date(rental.scheduled_end_at!).getTime() - new Date(rental.started_at).getTime()) / 3600000 * 10) / 10}j)` : rental.rental_type.replace('_', ' ') }}
              </span>
            </span>
            <span v-if="rental.paid_amount > 0" class="px-2 py-0.5 rounded-full text-xs font-semibold" style="background:rgba(16,185,129,.15); color:#34d399; border:1px solid rgba(16,185,129,.4);">
              ✓ Terbayar: {{ formatCurrency(rental.paid_amount) }}
            </span>
          </div>

          <!-- Catatan (jika ada) -->
          <div v-if="rental.notes" class="mt-3 pt-3 text-xs flex items-start gap-2 text-blue-300" style="border-top:1px dashed #2a2a3a;">
            <StickyNote :size="14" class="text-blue-400 shrink-0 mt-0.5" />
            <div>
              <span class="font-semibold text-blue-400">Catatan Console:</span>
              <p class="text-slate-300 mt-0.5">{{ rental.notes }}</p>
            </div>
          </div>
        </div>

        <!-- FNB Orders -->
        <div class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-white">Pesanan FNB</h3>
            <button v-if="canManageFnb" @click="showAddFnb = true" class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 font-semibold transition-colors">
              <Plus :size="13" /> Tambah FNB
            </button>
          </div>
          <div v-if="rental.fnb_items.length === 0" class="text-xs py-4 text-center" style="color:#64748b;">Belum ada pesanan FNB</div>
          <div v-else class="space-y-2">
            <div v-for="fi in rental.fnb_items" :key="fi.id"
              class="flex items-center justify-between p-3 rounded-xl"
              style="background:#12121a;">
              <div>
                <p class="text-sm text-white font-medium">{{ fi.fnb_item?.name || 'Item F&B' }} x{{ fi.qty }}</p>
                <p v-if="fi.addons?.length" class="text-xs mt-0.5" style="color:#94a3b8;">
                  + {{ fi.addons.map(a => a.name).join(', ') }}
                </p>
              </div>
              <div class="flex items-center gap-3">
                <p class="text-sm font-bold" style="color:#a78bfa;">{{ formatCurrency(fi.subtotal) }}</p>
                <div v-if="canManageFnb" class="flex items-center gap-1.5">
                  <button type="button" @click="openEditFnb(fi)"
                    class="p-1.5 rounded-lg text-blue-400 hover:text-blue-300 transition-colors"
                    style="background:rgba(59,130,246,.15); border:1px solid rgba(59,130,246,.3);"
                    title="Edit Item FNB (Ubah Qty / Menu / Add-on)">
                    <Pencil :size="13" />
                  </button>
                  <button type="button" @click="confirmRemoveFnb(fi)"
                    class="p-1.5 rounded-lg text-red-400 hover:text-red-300 transition-colors"
                    style="background:rgba(239,68,68,.15); border:1px solid rgba(239,68,68,.3);"
                    title="Hapus Item">
                    <Trash2 :size="13" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Extensions -->
        <div v-if="rental.extensions.length" class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
          <h3 class="text-sm font-semibold text-white mb-3">Riwayat Penambahan Waktu</h3>
          <div class="space-y-2">
            <div v-for="ext in rental.extensions" :key="ext.id"
              class="flex items-center justify-between p-3 rounded-xl" style="background:#12121a;">
              <p class="text-sm text-white">+{{ ext.added_minutes }} menit</p>
              <p class="text-sm font-bold" style="color:#60a5fa;">{{ formatCurrency(ext.additional_price) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Cost (Estimasi Tagihan) -->
      <div class="rounded-xl p-5 h-fit space-y-4" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <h3 class="text-sm font-semibold text-white">{{ isFinishedOrPaid ? 'Rincian Tagihan' : 'Estimasi Tagihan' }}</h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between items-center">
            <span style="color:#94a3b8;">Rental</span>
            <span class="text-white font-medium">{{ formatCurrency(currentRentalAmount) }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span style="color:#94a3b8;">FNB</span>
            <span class="text-white font-medium">{{ formatCurrency(rental.fnb_amount) }}</span>
          </div>
          <div v-if="rental.extra_amount" class="flex justify-between items-center">
            <span style="color:#60a5fa;">Tambahan Waktu</span>
            <span class="text-white font-medium">{{ formatCurrency(rental.extra_amount) }}</span>
          </div>

          <div class="pt-3 flex justify-between items-center text-base font-bold" style="border-top:1px solid #2a2a3a;">
            <span class="text-white">Total Tagihan</span>
            <span style="color:#a78bfa;">{{ formatCurrency(currentTotalAmount) }}</span>
          </div>

          <!-- Rincian Pembayaran Awal / Sisa Tagihan -->
          <div v-if="rental.paid_amount > 0" class="pt-2 border-t border-[#2a2a3a] space-y-1.5 text-xs">
            <div class="flex justify-between text-emerald-400">
              <span>Sudah Dibayar</span>
              <span class="font-semibold">{{ formatCurrency(rental.paid_amount) }}</span>
            </div>
            <div class="flex justify-between" :class="remainingPayment > 0 ? 'text-amber-400 font-bold' : 'text-slate-400'">
              <span>Sisa Tagihan</span>
              <span>{{ formatCurrency(remainingPayment) }}</span>
            </div>
          </div>
        </div>

        <template v-if="isFinishedOrPaid">
          <a v-if="remainingPayment > 0" :href="route('rentals.payment', rental.id)"
            class="w-full py-3 rounded-xl text-sm font-semibold text-white transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20"
            style="background:linear-gradient(135deg,#10b981,#059669);">
            <Wallet :size="16" /> Bayar Sisa Tagihan ({{ formatCurrency(remainingPayment) }})
          </a>
          <a v-else :href="route('rentals.receipt', rental.id)" target="_blank"
            class="w-full py-3 rounded-xl text-sm font-semibold text-white transition-all flex items-center justify-center gap-2 shadow-lg shadow-purple-500/20"
            style="background:linear-gradient(135deg,#7c3aed,#6d28d9);">
            <Receipt :size="16" /> Cetak / Lihat Struk
          </a>
        </template>
        <button v-else @click="showFinish = true"
          class="w-full py-3 rounded-xl text-sm font-semibold text-white transition-all shadow-lg shadow-red-500/20"
          style="background:linear-gradient(135deg,#ef4444,#dc2626);">
          Selesaikan Rental
        </button>
      </div>
    </div>

    <!-- Pindah Ruangan Modal -->
    <GamingModal :show="showSwitchModal" title="Pindah Ruangan / Console" @close="showSwitchModal = false">
      <form @submit.prevent="submitSwitch" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1 text-slate-400">Ruangan Saat Ini</label>
          <div class="p-3 rounded-xl text-sm font-semibold text-white bg-[#12121a] border border-[#2a2a3a]">
            {{ rental.console.name }} ({{ rental.console.type }})
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium mb-1 text-slate-400">Pilih Ruangan Tujuan (Yang Kosong)</label>
          <select v-model.number="switchForm.new_console_id" required
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option :value="null">-- Pilih Ruangan Tersedia --</option>
            <option v-for="c in (available_consoles || [])" :key="c.id" :value="c.id">
              {{ c.name }} ({{ c.type }}) — {{ formatCurrency(c.price_per_hour) }}/jam
            </option>
          </select>
          <p v-if="!available_consoles || available_consoles.length === 0" class="text-xs text-amber-400 mt-1.5">
            ⚠️ Saat ini tidak ada ruangan lain yang available (kosong).
          </p>
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="showSwitchModal = false"
            class="px-4 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">
            Batal
          </button>
          <button type="submit" :disabled="!switchForm.new_console_id || switchForm.processing"
            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-50"
            style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">
            Pindahkan Sekarang
          </button>
        </div>
      </form>
    </GamingModal>

    <!-- Catatan Modal -->
    <GamingModal :show="showNotesModal" title="Catatan Console / Rental" @close="showNotesModal = false">
      <form @submit.prevent="submitNotes" class="space-y-4">
        <div>
          <p class="text-xs text-slate-400 mb-2">
            Catatan untuk <strong class="text-white">{{ rental.console.name }}</strong>:
          </p>
          <textarea v-model="notesForm.notes" rows="4"
            class="w-full px-3 py-2.5 rounded-xl text-sm text-white outline-none resize-none"
            style="background:#12121a; border:1px solid #2a2a3a;"
            placeholder="Ketik catatan di sini..." />
        </div>

        <div class="flex gap-3 justify-end pt-2">
          <button type="button" @click="showNotesModal = false"
            class="px-4 py-2.5 rounded-xl text-sm" style="background:#2a2a3a; color:#e2e8f0;">
            Batal
          </button>
          <button type="submit" :disabled="notesForm.processing"
            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-50"
            style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
            Simpan Catatan
          </button>
        </div>
      </form>
    </GamingModal>

    <!-- Add Time Modal -->
    <GamingModal :show="showAddTime" title="Tambah Waktu" @close="showAddTime = false">
      <form @submit.prevent="submitAddTime" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Durasi Tambahan (menit)</label>
          <div class="grid grid-cols-4 gap-2 mb-2">
            <button v-for="d in [30,60,90,120]" :key="d" type="button"
              @click="setAddTimeMinutes(d)"
              class="py-2 rounded-xl text-xs font-medium transition-all"
              :style="addTimeForm.added_minutes === d
                ? 'background:rgba(139,92,246,.4); border:1px solid #8b5cf6; color:white;'
                : 'background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;'">
              {{ d }}m
            </button>
          </div>
          <input v-model.number="addTimeForm.added_minutes"
            @input="onAddTimeMinutesInput"
            type="number" min="15"
            class="w-full px-3 py-2 rounded-xl text-white outline-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-medium" style="color:#94a3b8;">Biaya Tambahan (Rp)</label>
            <span class="text-[11px] text-purple-400 font-semibold">
              Tarif: {{ formatCurrency(rental.console?.price_per_hour || 0) }}/jam
            </span>
          </div>
          <input v-model.number="addTimeForm.additional_price" type="number" min="0"
            class="w-full px-3 py-2 rounded-xl text-white outline-none text-sm font-semibold"
            style="background:#12121a; border:1px solid #2a2a3a; color:#a78bfa;" />
          <p class="text-[11px] text-slate-400 mt-1">
            Terisi otomatis sesuai tarif console, dapat disesuaikan jika ada diskon/penyesuaian khusus.
          </p>
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Catatan</label>
          <input v-model="addTimeForm.notes"
            class="w-full px-3 py-2 rounded-xl text-white outline-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showAddTime = false"
            class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
          <button type="submit" :disabled="addTimeForm.processing"
            class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white disabled:opacity-60"
            style="background:linear-gradient(135deg,#3b82f6,#8b5cf6);">Tambah Waktu</button>
        </div>
      </form>
    </GamingModal>

    <!-- Add FNB Modal -->
    <GamingModal :show="showAddFnb" title="Tambah FNB" max-width="38rem" @close="showAddFnb = false">
      <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
        <div v-for="(line, i) in fnbLines" :key="i"
          class="p-3 rounded-xl" style="background:#12121a; border:1px solid #2a2a3a;">
          <div class="flex gap-2 mb-2">
            <select v-model.number="line.fnb_item_id" class="flex-1 px-3 py-2 rounded-xl text-sm text-white outline-none"
              style="background:#1a1a26; border:1px solid #2a2a3a;">
              <option :value="null">-- Pilih Item --</option>
              <option v-for="fi in fnb_items" :key="fi.id" :value="fi.id">{{ fi.name }} – {{ formatCurrency(fi.price) }}</option>
            </select>
            <input v-model.number="line.quantity" type="number" min="1" placeholder="Qty"
              class="w-16 px-3 py-2 rounded-xl text-sm text-white outline-none text-center"
              style="background:#1a1a26; border:1px solid #2a2a3a;" />
            <button type="button" @click="removeFnbLine(i)" class="px-3 py-2 rounded-xl text-red-400 flex items-center"
              style="background:rgba(239,68,68,.1);"><Trash2 :size="13" /></button>
          </div>
          <div v-if="fnb_addons.length" class="flex flex-wrap gap-1">
            <label v-for="ad in fnb_addons" :key="ad.id"
              class="flex items-center gap-1 px-2 py-1 rounded-lg cursor-pointer text-xs"
              :style="line.addon_ids.includes(ad.id)
                ? 'background:rgba(34,197,94,.2); border:1px solid rgba(34,197,94,.5); color:#22c55e;'
                : 'background:#1a1a26; border:1px solid #2a2a3a; color:#94a3b8;'">
              <input type="checkbox" :value="ad.id" v-model="line.addon_ids" class="hidden" />
              {{ ad.name }} +{{ formatCurrency(ad.price) }}
            </label>
          </div>
        </div>
      </div>
      <div class="flex gap-3 mt-3">
        <button type="button" @click="addFnbLine"
          class="px-4 py-2 rounded-xl text-xs font-medium flex items-center gap-1"
          style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
          <Plus :size="12" /> Item
        </button>
        <button type="button" @click="showAddFnb = false"
          class="flex-1 py-2 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
        <button type="button" @click="submitAddFnb" :disabled="submittingFnb"
          class="flex-1 py-2 rounded-xl text-sm font-medium text-white disabled:opacity-60"
          style="background:linear-gradient(135deg,#22c55e,#16a34a);">Tambah FNB</button>
      </div>
    </GamingModal>

    <!-- Edit FNB Modal -->
    <GamingModal :show="showEditFnb" title="Edit Pesanan FNB" max-width="32rem" @close="showEditFnb = false">
      <div v-if="editingFnb" class="space-y-4">
        <!-- Select Item -->
        <div class="space-y-1.5">
          <label class="block text-xs font-medium text-slate-300">Pilih Menu F&B:</label>
          <select v-model.number="editingFnb.fnb_item_id"
            class="w-full px-3 py-2 rounded-xl text-sm text-white outline-none"
            style="background:#12121a; border:1px solid #2a2a3a;">
            <option v-for="fi in fnb_items" :key="fi.id" :value="fi.id">
              {{ fi.name }} – {{ formatCurrency(fi.price) }} ({{ fi.category }})
            </option>
          </select>
        </div>

        <!-- Quantity with counter buttons -->
        <div class="space-y-1.5">
          <label class="block text-xs font-medium text-slate-300">Jumlah (Qty):</label>
          <div class="flex items-center gap-2">
            <button type="button" @click="editingFnb.qty = Math.max(1, editingFnb.qty - 1)"
              class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold transition-all text-white hover:bg-slate-700"
              style="background:#12121a; border:1px solid #2a2a3a;">
              -
            </button>
            <input v-model.number="editingFnb.qty" type="number" min="1"
              class="w-20 h-10 px-3 py-2 rounded-xl text-sm text-white outline-none text-center font-bold"
              style="background:#12121a; border:1px solid #2a2a3a;" />
            <button type="button" @click="editingFnb.qty = editingFnb.qty + 1"
              class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold transition-all text-white hover:bg-slate-700"
              style="background:#12121a; border:1px solid #2a2a3a;">
              +
            </button>
            <span class="text-xs text-slate-400 ml-2">
              (Kurangi qty jika sebagian item dibayar meja lain)
            </span>
          </div>
        </div>

        <!-- Addons checkboxes/pills -->
        <div v-if="fnb_addons.length" class="space-y-1.5">
          <label class="block text-xs font-medium text-slate-300">Pilihan Add-on / Topping:</label>
          <div class="flex flex-wrap gap-1.5">
            <label v-for="ad in fnb_addons" :key="ad.id"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl cursor-pointer text-xs font-medium transition-all"
              :style="editingFnb.addon_ids.includes(ad.id)
                ? 'background:rgba(34,197,94,.2); border:1px solid rgba(34,197,94,.5); color:#22c55e;'
                : 'background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;'">
              <input type="checkbox" :value="ad.id" v-model="editingFnb.addon_ids" class="hidden" />
              <span>{{ ad.name }}</span>
              <span class="text-[11px] opacity-80">+{{ formatCurrency(ad.price) }}</span>
            </label>
          </div>
        </div>

        <!-- Subtotal Preview -->
        <div class="p-3 rounded-xl flex items-center justify-between" style="background:#12121a; border:1px solid #2a2a3a;">
          <span class="text-xs text-slate-400">Subtotal Item:</span>
          <span class="text-base font-bold text-emerald-400">{{ formatCurrency(editingSubtotal) }}</span>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between gap-2 pt-2 border-t border-[#2a2a3a]">
          <button type="button" @click="showDeleteFnbFromEdit"
            class="px-3.5 py-2 rounded-xl text-xs font-medium text-red-400 flex items-center gap-1 transition-all hover:bg-red-500/20"
            style="background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3);"
            title="Hapus item ini dari tagihan">
            <Trash2 :size="13" /> Hapus Item
          </button>
          <div class="flex items-center gap-2">
            <button type="button" @click="showEditFnb = false"
              class="px-4 py-2 rounded-xl text-xs font-medium"
              style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
              Batal
            </button>
            <button type="button" @click="submitEditFnb" :disabled="submittingEditFnb"
              class="px-5 py-2 rounded-xl text-xs font-semibold text-white flex items-center gap-1.5 disabled:opacity-60"
              style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
              Simpan Perubahan
            </button>
          </div>
        </div>
      </div>
    </GamingModal>

    <!-- Delete FNB Item Modal -->
    <GamingModal :show="showDeleteFnbModal" title="Hapus Item FNB?" @close="showDeleteFnbModal = false">
      <div class="space-y-4">
        <p class="text-sm text-slate-300">
          Apakah Anda yakin ingin menghapus item
          <strong class="text-white">{{ itemToDelete?.fnb_item?.name || 'Item F&B' }} (x{{ itemToDelete?.qty }})</strong>
          dari tagihan rental ini?
        </p>
        <p class="text-xs text-slate-400">
          Total tagihan F&B akan otomatis berkurang dan sisa tagihan kasir akan disesuaikan.
        </p>
        <div class="flex gap-3 pt-2">
          <button type="button" @click="showDeleteFnbModal = false"
            class="flex-1 py-2.5 rounded-xl text-sm"
            style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">
            Batal
          </button>
          <button type="button" @click="submitRemoveFnb"
            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white flex items-center justify-center gap-1.5"
            style="background:linear-gradient(135deg,#ef4444,#dc2626);">
            <Trash2 :size="15" /> Ya, Hapus
          </button>
        </div>
      </div>
    </GamingModal>

    <!-- Finish Confirm -->
    <GamingModal :show="showFinish" title="Selesaikan Rental?" @close="showFinish = false">
      <div class="space-y-4">
        <p class="text-sm" style="color:#94a3b8;">
          Rental <strong class="text-white">{{ rental.customer_name || 'Customer' }}</strong> akan diselesaikan.
          Total tagihan: <strong style="color:#a78bfa;">{{ formatCurrency(currentTotalAmount) }}</strong>
        </p>

        <div v-if="rental.paid_amount >= currentTotalAmount && rental.paid_amount > 0"
          class="p-3 rounded-xl text-xs bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">
          ✓ <strong>Rental sudah lunas terbayar.</strong> Ruangan akan langsung kembali <em>available</em> tanpa perlu pembayaran kasir.
        </div>
        <div v-else-if="rental.paid_amount > 0"
          class="p-3 rounded-xl text-xs bg-amber-500/10 border border-amber-500/30 text-amber-300">
          ℹ Sisa tagihan yang harus dilunasi di kasir: <strong>{{ formatCurrency(remainingPayment) }}</strong>.
        </div>

        <div class="flex gap-3">
          <button @click="showFinish = false"
            class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
          <button @click="doFinish" :disabled="finishing"
            class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white disabled:opacity-60"
            style="background:linear-gradient(135deg,#ef4444,#dc2626);">Ya, Selesaikan</button>
        </div>
      </div>
    </GamingModal>
  </AppLayout>
</template>
