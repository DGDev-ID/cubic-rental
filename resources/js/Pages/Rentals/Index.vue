<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Timer, Gamepad2, Play, CalendarClock, Search } from 'lucide-vue-next'

interface Console { id: number; name: string; type: string; price_per_hour: number }
interface Employee { id: number; name: string }
interface UpcomingReservation {
  id: number; customer_name: string; reserved_at: string; duration_hours: number | null; status: string
}

const props = defineProps<{
  consoles: Console[]
  employees: Employee[]
  upcoming_reservations: Record<number, UpcomingReservation[]>
}>()

const form = useForm({
  customer_name: '',
  console_id: null as number | null,
  employee_id: null as number | null,
  duration_hours: null as number | null,
  notes: '',
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

const durationChips = [1, 1.5, 2, 3, 4, 5]

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

          <!-- Customer -->
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Nama Customer *</label>
            <input v-model="form.customer_name" required
              class="w-full px-4 py-3 rounded-xl text-white outline-none transition-colors"
              style="background:#12121a; border:1px solid #2a2a3a; font-size:15px;"
              placeholder="Nama customer..." />
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

          <!-- Duration (optional) -->
          <div>
            <label class="block text-xs font-medium mb-2" style="color:#94a3b8;">
              <Timer :size="13" class="inline mr-1" /> Durasi (opsional)
            </label>
            <div class="flex flex-wrap gap-2 mb-2">
              <button v-for="d in durationChips" :key="d" type="button"
                @click="form.duration_hours = form.duration_hours === d ? null : d"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                :style="form.duration_hours === d
                  ? 'background:linear-gradient(135deg,#8b5cf6,#3b82f6); color:white;'
                  : 'background:#12121a; color:#94a3b8; border:1px solid #2a2a3a;'">
                {{ d % 1 === 0 ? `${d}j` : `${d}j` }}
              </button>
              <input type="number" step="0.5" min="0.5" max="24"
                v-model.number="form.duration_hours"
                class="w-24 px-3 py-1.5 rounded-lg text-xs text-white outline-none"
                style="background:#12121a; border:1px solid #2a2a3a;"
                placeholder="Custom..." />
            </div>
            <p class="text-xs" style="color:#64748b;">Kosongkan untuk Open Time (meter jalan tanpa batas).</p>
            <p v-if="form.errors.duration_hours" class="text-red-400 text-xs mt-1">{{ form.errors.duration_hours }}</p>
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
            <div class="space-y-1 text-sm">
              <div class="flex justify-between">
                <span style="color:#94a3b8;">Console</span>
                <span class="text-white">{{ selectedConsole.name }}</span>
              </div>
              <div class="flex justify-between">
                <span style="color:#94a3b8;">Tarif</span>
                <span class="font-bold" style="color:#a78bfa;">{{ formatCurrency(selectedConsole.price_per_hour) }}/jam</span>
              </div>
              <div v-if="form.duration_hours" class="flex justify-between">
                <span style="color:#94a3b8;">Durasi</span>
                <span class="text-white">{{ form.duration_hours }} jam</span>
              </div>
              <div v-if="form.duration_hours" class="flex justify-between">
                <span style="color:#94a3b8;">Estimasi</span>
                <span class="font-bold" style="color:#22d3ee;">
                  {{ formatCurrency(selectedConsole.price_per_hour * form.duration_hours) }}
                </span>
              </div>
              <div v-else class="flex justify-between">
                <span style="color:#94a3b8;">Tipe</span>
                <span class="text-white">Open Time (meter jalan)</span>
              </div>
            </div>
          </div>

          <button type="submit" :disabled="form.processing"
            class="w-full py-4 rounded-xl text-white font-semibold text-base transition-all disabled:opacity-60 flex items-center justify-center gap-2"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6);">
            <Play :size="16" /> Mulai Rental
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
