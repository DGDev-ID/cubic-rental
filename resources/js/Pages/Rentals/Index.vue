<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Timer, Gamepad2, Play } from 'lucide-vue-next'

interface Console { id: number; name: string; type: string; price_per_hour: number }
interface Employee { id: number; name: string }

const props = defineProps<{
  consoles: Console[]
  employees: Employee[]
}>()

const form = useForm({
  customer_name: '',
  console_id: null as number | null,
  employee_id: null as number | null,
  duration_hours: null as number | null,
  notes: '',
})

const selectedConsole = computed(() => props.consoles.find(c => c.id === form.console_id))

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
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <button v-for="c in consoles" :key="c.id" type="button"
                @click="form.console_id = c.id"
                class="p-3 rounded-xl text-left transition-all"
                :style="form.console_id === c.id
                  ? 'background:rgba(139,92,246,.2); border:1px solid rgba(139,92,246,.6);'
                  : 'background:#12121a; border:1px solid #2a2a3a;'">
                <p class="font-medium text-xs text-white truncate">{{ c.name }}</p>
                <p class="text-xs mt-0.5" :style="`color: ${typeColors[c.type]}`">{{ c.type.toUpperCase() }}</p>
                <p class="text-xs mt-1 font-medium" style="color:#a78bfa;">{{ formatCurrency(c.price_per_hour) }}/jam</p>
              </button>
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
