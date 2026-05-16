<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import { Line, Bar } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale, PointElement,
  LineElement, BarElement, Title, Tooltip, Legend, Filler
} from 'chart.js'
import {
  HomeIcon, CurrencyDollarIcon, ComputerDesktopIcon,
  ShoppingCartIcon, ArrowDownCircleIcon, PlayIcon,
  BuildingStorefrontIcon, CalendarDaysIcon
} from '@heroicons/vue/24/outline'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, Filler)

const props = defineProps<{
  summary: {
    total_service_today: number
    total_omzet_today: number
    total_omzet_rental: number
    total_omzet_fnb: number
    total_cash_outbound: number
    total_active_rentals: number
    total_active_rooms: number
    total_omzet_bulan_ini: number
  }
  dailyChart: { labels: string[]; rentalData: number[]; fnbData: number[] }
  monthlyChart: { labels: string[]; data: number[] }
}>()

function formatCurrency(val: number): string {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val)
}

const dailyChartData = {
  labels: props.dailyChart.labels,
  datasets: [
    {
      label: 'Rental',
      data: props.dailyChart.rentalData,
      borderColor: '#8b5cf6',
      backgroundColor: 'rgba(139,92,246,.15)',
      fill: true,
      tension: 0.4,
    },
    {
      label: 'FNB',
      data: props.dailyChart.fnbData,
      borderColor: '#06b6d4',
      backgroundColor: 'rgba(6,182,212,.1)',
      fill: true,
      tension: 0.4,
    },
  ],
}

const monthlyChartData = {
  labels: props.monthlyChart.labels,
  datasets: [{
    label: 'Omzet Bulanan',
    data: props.monthlyChart.data,
    backgroundColor: 'rgba(139,92,246,.6)',
    borderColor: '#8b5cf6',
    borderRadius: 8,
  }],
}

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { labels: { color: '#94a3b8' } } },
  scales: {
    x: { grid: { color: '#1e1e2e' }, ticks: { color: '#94a3b8' } },
    y: { grid: { color: '#1e1e2e' }, ticks: { color: '#94a3b8' } },
  },
}
</script>

<template>
  <AppLayout>
    <template #header-title>
      <h1 class="font-semibold text-white text-lg">Dashboard</h1>
    </template>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 mb-6">
      <StatCard title="Service Hari Ini" :value="summary.total_service_today" color="blue">
        <template #icon><HomeIcon class="w-5 h-5" /></template>
      </StatCard>
      <StatCard title="Omzet Hari Ini" :value="formatCurrency(summary.total_omzet_today)" color="purple">
        <template #icon><CurrencyDollarIcon class="w-5 h-5" /></template>
      </StatCard>
      <StatCard title="Omzet Rental" :value="formatCurrency(summary.total_omzet_rental)" color="cyan">
        <template #icon><ComputerDesktopIcon class="w-5 h-5" /></template>
      </StatCard>
      <StatCard title="Omzet FNB" :value="formatCurrency(summary.total_omzet_fnb)" color="green">
        <template #icon><ShoppingCartIcon class="w-5 h-5" /></template>
      </StatCard>
      <StatCard title="Pengeluaran" :value="formatCurrency(summary.total_cash_outbound)" color="red">
        <template #icon><ArrowDownCircleIcon class="w-5 h-5" /></template>
      </StatCard>
      <StatCard title="Transaksi Aktif" :value="summary.total_active_rentals" color="yellow">
        <template #icon><PlayIcon class="w-5 h-5" /></template>
      </StatCard>
      <StatCard title="Room Aktif" :value="summary.total_active_rooms" color="purple">
        <template #icon><BuildingStorefrontIcon class="w-5 h-5" /></template>
      </StatCard>
      <StatCard title="Omzet Bulan Ini" :value="formatCurrency(summary.total_omzet_bulan_ini)" color="blue">
        <template #icon><CalendarDaysIcon class="w-5 h-5" /></template>
      </StatCard>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="rounded-xl p-4" style="background-color:#1a1a26; border:1px solid #2a2a3a;">
        <h3 class="font-semibold text-white mb-4 text-sm">Omzet 7 Hari Terakhir</h3>
        <div class="h-52">
          <Line :data="dailyChartData" :options="chartOptions" />
        </div>
      </div>
      <div class="rounded-xl p-4" style="background-color:#1a1a26; border:1px solid #2a2a3a;">
        <h3 class="font-semibold text-white mb-4 text-sm">Omzet 6 Bulan Terakhir</h3>
        <div class="h-52">
          <Bar :data="monthlyChartData" :options="chartOptions" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
