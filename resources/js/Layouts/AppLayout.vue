<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  HomeIcon, ComputerDesktopIcon, UserGroupIcon, CubeIcon,
  ShoppingBagIcon, ClockIcon, BanknotesIcon, ListBulletIcon,
  Bars3Icon, XMarkIcon, ChevronDownIcon, PowerIcon, SignalIcon
} from '@heroicons/vue/24/outline'

const page = usePage()
const sidebarOpen = ref(true)
const mobileOpen = ref(false)

const user = computed(() => page.props.auth?.user)

const navItems = [
  { label: 'Dashboard',     route: 'dashboard',            icon: HomeIcon },
  { label: 'Room Monitor',  route: 'room-monitor',         icon: SignalIcon },
  { label: 'Rental Baru',   route: 'rentals.index',        icon: ClockIcon },
  { label: 'Riwayat',       route: 'rentals.history',      icon: ListBulletIcon },
  { label: 'Employees',     route: 'employees.index',      icon: UserGroupIcon },
  { label: 'Console/Room',  route: 'consoles.index',       icon: ComputerDesktopIcon },
  { label: 'Game',          route: 'games.index',          icon: CubeIcon },
  { label: 'FNB',           route: 'fnb.index',            icon: ShoppingBagIcon },
  { label: 'Pengeluaran',   route: 'cash-outbounds.index', icon: BanknotesIcon },
]

function isActive(routeName: string): boolean {
  return route().current(routeName) || route().current(routeName + '.*') || false
}
</script>

<template>
  <div class="flex h-screen overflow-hidden" style="background-color: #0a0a0f; color: #e2e8f0;">
    <!-- Mobile overlay -->
    <div v-if="mobileOpen" @click="mobileOpen = false"
      class="fixed inset-0 z-20 bg-black/60 lg:hidden" />

    <!-- Sidebar -->
    <aside
      :class="[
        'z-30 flex flex-col transition-all duration-300 shrink-0',
        'lg:relative lg:translate-x-0',
        mobileOpen ? 'fixed inset-y-0 left-0 translate-x-0' : 'fixed -translate-x-full lg:translate-x-0',
        sidebarOpen ? 'w-64' : 'w-16'
      ]"
      style="background-color: #12121a; border-right: 1px solid #2a2a3a;"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 px-4 py-5 border-b" style="border-color: #2a2a3a;">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
          style="background: linear-gradient(135deg,#8b5cf6,#3b82f6);">
          <span class="text-white font-bold text-sm">PS</span>
        </div>
        <div v-if="sidebarOpen" class="overflow-hidden">
          <p class="font-bold text-white leading-tight">Cubic</p>
          <p class="text-xs" style="color:#94a3b8;">Gaming Lounge</p>
        </div>
        <button class="ml-auto lg:flex hidden text-slate-400 hover:text-white"
          @click="sidebarOpen = !sidebarOpen">
          <Bars3Icon class="w-5 h-5" />
        </button>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
        <Link
          v-for="item in navItems" :key="item.route"
          :href="route(item.route)"
          :class="[
            'flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group',
            isActive(item.route)
              ? 'text-white' : 'hover:text-white'
          ]"
          :style="isActive(item.route)
            ? 'background: linear-gradient(135deg,rgba(139,92,246,.25),rgba(59,130,246,.15)); border:1px solid rgba(139,92,246,.4); color:#a78bfa;'
            : 'color:#94a3b8; border:1px solid transparent;'"
        >
          <component :is="item.icon" class="w-5 h-5 shrink-0" />
          <span v-if="sidebarOpen" class="text-sm font-medium truncate">{{ item.label }}</span>
        </Link>
      </nav>

      <!-- User -->
      <div class="px-3 py-4 border-t" style="border-color:#2a2a3a;">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
            style="background: linear-gradient(135deg,#8b5cf6,#3b82f6);">
            <span class="text-white text-xs font-bold">{{ user?.name?.[0]?.toUpperCase() }}</span>
          </div>
          <div v-if="sidebarOpen" class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white truncate">{{ user?.name }}</p>
            <p class="text-xs truncate" style="color:#94a3b8;">Superadmin</p>
          </div>
          <Link v-if="sidebarOpen" :href="route('logout')" method="post" as="button"
            class="text-slate-400 hover:text-red-400 transition-colors">
            <PowerIcon class="w-4 h-4" />
          </Link>
        </div>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Top bar -->
      <header class="flex items-center gap-4 px-4 py-3 shrink-0"
        style="background-color:#12121a; border-bottom:1px solid #2a2a3a;">
        <button class="lg:hidden text-slate-400 hover:text-white" @click="mobileOpen = true">
          <Bars3Icon class="w-6 h-6" />
        </button>
        <div class="flex-1 min-w-0">
          <slot name="header-title">
            <h1 class="font-semibold text-white text-lg truncate">Dashboard</h1>
          </slot>
        </div>
        <slot name="header-actions" />
      </header>

      <!-- Page Content -->
      <main class="flex-1 overflow-y-auto p-4 lg:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
