<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GamingModal from '@/Components/GamingModal.vue'
import { Clock, UtensilsCrossed, CheckCircle, Plus, Trash2, Wallet } from 'lucide-vue-next'

interface FnbAddon { id: number; name: string; price: number }
interface FnbItem { id: number; name: string; category: string; price: number; is_available: boolean }
interface Extension { id: number; added_minutes: number; additional_price: number; created_at: string }
interface FnbOrderItem {
  id: number; fnb_item_id: number; fnb_item: { name: string }; qty: number;
  unit_price: number; subtotal: number; addons: { name: string; price: number }[] | null
}

const props = defineProps<{
  rental: {
    id: number; transaction_code: string; customer_name: string; rental_type: string;
    status: string; started_at: string; scheduled_end_at: string | null;
    rental_amount: number; fnb_amount: number; extra_amount: number; total_amount: number;
    paid_amount: number;
    notes: string | null; duration_minutes: number; remaining_minutes: number; is_overtime: boolean;
    console: { id: number; name: string; type: string }
    employee: { id: number; name: string }
    extensions: Extension[]
    fnb_items: FnbOrderItem[]
  }
  fnb_items: FnbItem[]
  fnb_addons: FnbAddon[]
}>()

const now = ref(new Date())
let timer: ReturnType<typeof setInterval>
onMounted(() => { timer = setInterval(() => { now.value = new Date() }, 1000) })
onUnmounted(() => clearInterval(timer))

const liveDuration = computed(() => {
  const diff = Math.floor((now.value.getTime() - new Date(props.rental.started_at).getTime()) / 60000)
  return `${Math.floor(diff / 60)}j ${diff % 60}m`
})

const liveRemaining = computed(() => {
  if (!props.rental.scheduled_end_at) return null
  const diff = Math.floor((new Date(props.rental.scheduled_end_at).getTime() - now.value.getTime()) / 60000)
  if (diff < 0) return `Overtime ${Math.abs(diff)}m`
  return `${Math.floor(diff / 60)}j ${diff % 60}m`
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

// Remove FNB item
function removeFnbItem(fnbItemId: number) {
  router.delete(route('rentals.remove-fnb', { rental: props.rental.id, fnbItem: fnbItemId }))
}
</script>

<template>
  <AppLayout>
    <template #header-title>
      <div>
        <h1 class="font-semibold text-white text-lg">{{ rental.console.name }}</h1>
        <p class="text-xs" style="color:#94a3b8;">{{ rental.transaction_code }}</p>
      </div>
    </template>
    <template #header-actions>
      <div class="flex gap-2">
        <button @click="showAddTime = true"
          class="px-4 py-2 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-1.5"
          style="background:rgba(59,130,246,.2); border:1px solid rgba(59,130,246,.4);">
          <Clock :size="14" /> + Waktu
        </button>
        <button @click="showAddFnb = true"
          class="px-4 py-2 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-1.5"
          style="background:rgba(34,197,94,.2); border:1px solid rgba(34,197,94,.4);">
          <UtensilsCrossed :size="14" /> + FNB
        </button>
        <a :href="route('rentals.payment', rental.id)"
          class="px-4 py-2 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-1.5"
          style="background:rgba(251,191,36,.2); border:1px solid rgba(251,191,36,.4);">
          <Wallet :size="14" /> Bayar DP
        </a>
        <button @click="showFinish = true"
          class="px-4 py-2 rounded-xl text-sm font-medium text-white transition-all flex items-center gap-1.5"
          style="background:linear-gradient(135deg,#ef4444,#dc2626);">
          <CheckCircle :size="14" /> Selesai
        </button>
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
            <span style="color:#94a3b8;">Customer: <span class="text-white">{{ rental.customer_name }}</span></span>
            <span style="color:#94a3b8;">Operator: <span class="text-white">{{ rental.employee.name }}</span></span>
            <span style="color:#94a3b8;">Tipe:
              <span class="font-medium capitalize" style="color:#a78bfa;">
                {{ rental.rental_type === 'duration' ? `Durasi (${Math.round((new Date(rental.scheduled_end_at!).getTime() - new Date(rental.started_at).getTime()) / 3600000 * 10) / 10}j)` : rental.rental_type.replace('_', ' ') }}
              </span>
            </span>
            <span v-if="rental.status === 'half_paid'" class="px-2 py-0.5 rounded-full text-xs font-semibold" style="background:rgba(251,191,36,.2); color:#fbbf24; border:1px solid rgba(251,191,36,.4);">
              DP: {{ formatCurrency(rental.paid_amount) }}
            </span>
          </div>
        </div>

        <!-- FNB Orders -->
        <div class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
          <h3 class="text-sm font-semibold text-white mb-3">Pesanan FNB</h3>
          <div v-if="rental.fnb_items.length === 0" class="text-xs py-4 text-center" style="color:#64748b;">Belum ada pesanan FNB</div>
          <div v-else class="space-y-2">
            <div v-for="fi in rental.fnb_items" :key="fi.id"
              class="flex items-center justify-between p-3 rounded-xl"
              style="background:#12121a;">
              <div>
                <p class="text-sm text-white">{{ fi.fnb_item.name }} x{{ fi.qty }}</p>
                <p v-if="fi.addons?.length" class="text-xs mt-0.5" style="color:#94a3b8;">
                  + {{ fi.addons.map(a => a.name).join(', ') }}
                </p>
              </div>
              <div class="flex items-center gap-3">
                <p class="text-sm font-bold" style="color:#a78bfa;">{{ formatCurrency(fi.subtotal) }}</p>
                <button type="button" @click="removeFnbItem(fi.id)"
                  class="p-1.5 rounded-lg text-red-400 hover:text-red-300 transition-colors"
                  style="background:rgba(239,68,68,.1);">
                  <Trash2 :size="13" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Extensions -->
        <div v-if="rental.extensions.length" class="rounded-xl p-5" style="background:#1a1a26; border:1px solid #2a2a3a;">
          <h3 class="text-sm font-semibold text-white mb-3">Tambah Waktu</h3>
          <div class="space-y-2">
            <div v-for="ext in rental.extensions" :key="ext.id"
              class="flex items-center justify-between p-3 rounded-xl" style="background:#12121a;">
              <p class="text-sm text-white">+{{ ext.added_minutes }} menit</p>
              <p class="text-sm font-bold" style="color:#60a5fa;">{{ formatCurrency(ext.additional_price) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Cost -->
      <div class="rounded-xl p-5 h-fit" style="background:#1a1a26; border:1px solid #2a2a3a;">
        <h3 class="text-sm font-semibold text-white mb-4">Estimasi Tagihan</h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span style="color:#94a3b8;">Rental</span>
            <span class="text-white">{{ formatCurrency(rental.rental_amount) }}</span>
          </div>
          <div class="flex justify-between">
            <span style="color:#94a3b8;">FNB</span>
            <span class="text-white">{{ formatCurrency(rental.fnb_amount) }}</span>
          </div>
          <div v-if="rental.extra_amount" class="flex justify-between">
            <span style="color:#ef4444;">Overtime</span>
            <span class="text-red-400">{{ formatCurrency(rental.extra_amount) }}</span>
          </div>
          <div class="pt-3 flex justify-between text-base font-bold" style="border-top:1px solid #2a2a3a;">
            <span class="text-white">Total</span>
            <span style="color:#a78bfa;">{{ formatCurrency(rental.total_amount) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Time Modal -->
    <GamingModal :show="showAddTime" title="Tambah Waktu" @close="showAddTime = false">
      <form @submit.prevent="submitAddTime" class="space-y-4">
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Durasi Tambahan (menit)</label>
          <div class="grid grid-cols-4 gap-2 mb-2">
            <button v-for="d in [30,60,90,120]" :key="d" type="button"
              @click="addTimeForm.added_minutes = d"
              class="py-2 rounded-xl text-xs font-medium transition-all"
              :style="addTimeForm.added_minutes === d
                ? 'background:rgba(139,92,246,.4); border:1px solid #8b5cf6; color:white;'
                : 'background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;'">
              {{ d }}m
            </button>
          </div>
          <input v-model.number="addTimeForm.added_minutes" type="number" min="15"
            class="w-full px-3 py-2 rounded-xl text-white outline-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Biaya Tambahan (Rp)</label>
          <input v-model.number="addTimeForm.additional_price" type="number" min="0"
            class="w-full px-3 py-2 rounded-xl text-white outline-none text-sm"
            style="background:#12121a; border:1px solid #2a2a3a;" />
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

    <!-- Finish Confirm -->
    <GamingModal :show="showFinish" title="Selesaikan Rental?" @close="showFinish = false">
      <p class="text-sm mb-4" style="color:#94a3b8;">
        Rental <strong class="text-white">{{ rental.customer_name }}</strong> akan diselesaikan.
        Total tagihan: <strong style="color:#a78bfa;">{{ formatCurrency(rental.total_amount) }}</strong>
      </p>
      <div class="flex gap-3">
        <button @click="showFinish = false"
          class="flex-1 py-2.5 rounded-xl text-sm" style="background:#12121a; border:1px solid #2a2a3a; color:#94a3b8;">Batal</button>
        <button @click="doFinish" :disabled="finishing"
          class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white disabled:opacity-60"
          style="background:linear-gradient(135deg,#ef4444,#dc2626);">Ya, Selesaikan</button>
      </div>
    </GamingModal>
  </AppLayout>
</template>
