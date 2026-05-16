<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Gamepad2 } from 'lucide-vue-next'

defineProps<{ canResetPassword?: boolean; status?: string }>()

const form = useForm({ email: '', password: '', remember: false })
function submit() {
  form.post(route('login'), { onFinish: () => form.reset('password') })
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-4"
    style="background:radial-gradient(ellipse at 20% 50%, rgba(139,92,246,.15) 0%, transparent 50%), radial-gradient(ellipse at 80% 20%, rgba(59,130,246,.1) 0%, transparent 50%), #0a0a0f;">

    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
          style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); box-shadow:0 0 40px rgba(139,92,246,.4);">
          <Gamepad2 :size="28" class="text-white" />
        </div>
        <h1 class="text-2xl font-bold text-white">Cubic</h1>
        <p class="text-xs mt-1" style="color:#64748b;">Gaming Lounge Management System</p>
      </div>

      <div class="rounded-2xl p-6" style="background:#1a1a26; border:1px solid #2a2a3a; box-shadow:0 0 60px rgba(0,0,0,.5);">
        <div v-if="status" class="text-green-400 text-xs mb-4 p-3 rounded-xl" style="background:rgba(34,197,94,.1);">
          {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Email</label>
            <input v-model="form.email" type="email" required autocomplete="email"
              class="w-full px-4 py-3 rounded-xl text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a; font-size:14px;"
              placeholder="admin@example.com" />
            <p v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium mb-1.5" style="color:#94a3b8;">Password</label>
            <input v-model="form.password" type="password" required autocomplete="current-password"
              class="w-full px-4 py-3 rounded-xl text-white outline-none"
              style="background:#12121a; border:1px solid #2a2a3a; font-size:14px;"
              placeholder="••••••••" />
            <p v-if="form.errors.password" class="text-red-400 text-xs mt-1">{{ form.errors.password }}</p>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="form.remember" type="checkbox" class="w-4 h-4 rounded accent-purple-500" />
              <span class="text-xs" style="color:#94a3b8;">Ingat saya</span>
            </label>
          </div>

          <button type="submit" :disabled="form.processing"
            class="w-full py-3.5 rounded-xl text-white font-semibold text-sm disabled:opacity-60"
            style="background:linear-gradient(135deg,#8b5cf6,#3b82f6); box-shadow:0 4px 20px rgba(139,92,246,.3);">
            {{ form.processing ? 'Loading...' : 'Masuk →' }}
          </button>
        </form>
      </div>

      <p class="text-center text-xs mt-4" style="color:#334155;">
        &copy; {{ new Date().getFullYear() }} Cubic
      </p>
    </div>
  </div>
</template>
