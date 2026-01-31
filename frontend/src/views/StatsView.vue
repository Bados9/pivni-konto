<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../services/api'
import StatsCard from '../components/StatsCard.vue'

const stats = ref({
  today: 0,
  thisWeek: 0,
  thisMonth: 0,
  thisYear: 0,
  totalBeers: 0,
  totalVolume: 0,
  dailyCounts: [],
  topBeers: [],
  topBreweries: [],
  currentStreak: 0,
  averagePerDay: 0
})
const loading = ref(true)

// Chart dimensions
const chartHeight = 120
const chartPadding = { top: 10, right: 10, bottom: 20, left: 30 }

// Generate last 30 days for chart
const last30Days = computed(() => {
  const days = []
  const today = new Date()
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today)
    date.setDate(date.getDate() - i)
    days.push(date.toISOString().split('T')[0])
  }
  return days
})

// Merge daily counts with all days (fill zeros)
const chartData = computed(() => {
  const countsMap = {}
  stats.value.dailyCounts.forEach(d => {
    countsMap[d.date] = d.count
  })
  return last30Days.value.map(date => ({
    date,
    count: countsMap[date] || 0
  }))
})

const maxCount = computed(() => Math.max(...chartData.value.map(d => d.count), 1))

// SVG path for the line chart
const chartPath = computed(() => {
  const data = chartData.value
  const width = 100 // percentage
  const barWidth = width / data.length

  return data.map((d, i) => {
    const x = i * barWidth + barWidth / 2
    const y = chartHeight - chartPadding.bottom - (d.count / maxCount.value) * (chartHeight - chartPadding.top - chartPadding.bottom)
    return `${i === 0 ? 'M' : 'L'} ${x}% ${y}`
  }).join(' ')
})

// Format volume in liters
function formatVolume(ml) {
  return (ml / 1000).toFixed(1)
}

// Format date for display
function formatShortDate(dateStr) {
  const date = new Date(dateStr)
  return `${date.getDate()}.${date.getMonth() + 1}.`
}

async function fetchStats() {
  loading.value = true
  try {
    const data = await api.getMyStats()
    stats.value = {
      today: data.today || 0,
      thisWeek: data.thisWeek || 0,
      thisMonth: data.thisMonth || 0,
      thisYear: data.thisYear || 0,
      totalBeers: data.totalBeers || 0,
      totalVolume: data.totalVolume || 0,
      dailyCounts: data.dailyCounts || [],
      topBeers: data.topBeers || [],
      topBreweries: data.topBreweries || [],
      currentStreak: data.currentStreak || 0,
      averagePerDay: data.averagePerDay || 0
    }
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
  loading.value = false
}

onMounted(fetchStats)
</script>

<template>
  <div class="max-w-lg mx-auto px-4 py-6 pb-24">
    <header class="mb-6">
      <h1 class="text-2xl font-bold">Moje statistiky</h1>
    </header>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-beer-500"></div>
    </div>

    <template v-else>
      <!-- Period stats -->
      <section class="mb-6">
        <div class="grid grid-cols-5 gap-2">
          <StatsCard title="Dnes" :value="stats.today" />
          <StatsCard title="Týden" :value="stats.thisWeek" />
          <StatsCard title="Měsíc" :value="stats.thisMonth" />
          <StatsCard title="Rok" :value="stats.thisYear" />
          <StatsCard title="Celkem" :value="stats.totalBeers" />
        </div>
      </section>

      <!-- Interesting numbers -->
      <section class="mb-6">
        <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Čísla</h2>
        <div class="grid grid-cols-3 gap-3">
          <div class="card text-center">
            <p class="text-2xl font-bold text-beer-500">{{ stats.currentStreak }}</p>
            <p class="text-xs text-gray-400">Dnů v řadě</p>
          </div>
          <div class="card text-center">
            <p class="text-2xl font-bold text-beer-500">{{ stats.averagePerDay }}</p>
            <p class="text-xs text-gray-400">Průměr/den</p>
          </div>
          <div class="card text-center">
            <p class="text-2xl font-bold text-beer-500">{{ formatVolume(stats.totalVolume) }}l</p>
            <p class="text-xs text-gray-400">Objem</p>
          </div>
        </div>
      </section>

      <!-- Daily chart -->
      <section class="mb-6">
        <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Posledních 30 dní</h2>
        <div class="card">
          <div class="relative" :style="{ height: chartHeight + 'px' }">
            <!-- Y-axis labels -->
            <div class="absolute left-0 top-0 bottom-5 w-6 flex flex-col justify-between text-xs text-gray-500">
              <span>{{ maxCount }}</span>
              <span>0</span>
            </div>

            <!-- Bars -->
            <div class="absolute left-7 right-0 top-2 bottom-5 flex items-end gap-px">
              <div
                v-for="(day, index) in chartData"
                :key="day.date"
                class="flex-1 bg-beer-500/80 rounded-t transition-all hover:bg-beer-400"
                :style="{ height: (day.count / maxCount) * 100 + '%', minHeight: day.count > 0 ? '4px' : '0' }"
                :title="`${formatShortDate(day.date)}: ${day.count} piv`"
              ></div>
            </div>

            <!-- X-axis labels -->
            <div class="absolute left-7 right-0 bottom-0 flex justify-between text-xs text-gray-500">
              <span>{{ formatShortDate(chartData[0]?.date) }}</span>
              <span>{{ formatShortDate(chartData[chartData.length - 1]?.date) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Top beers and breweries -->
      <div class="grid grid-cols-2 gap-4 mb-6">
        <!-- Top beers -->
        <section>
          <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Top piva</h2>
          <div class="card space-y-2">
            <div
              v-for="(beer, index) in stats.topBeers"
              :key="index"
              class="flex items-center justify-between"
            >
              <span class="text-sm truncate flex-1 mr-2">{{ beer.name }}</span>
              <span class="text-beer-500 font-bold text-sm">{{ beer.count }}</span>
            </div>
            <p v-if="stats.topBeers.length === 0" class="text-gray-500 text-sm text-center py-2">
              Žádná data
            </p>
          </div>
        </section>

        <!-- Top breweries -->
        <section>
          <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Top pivovary</h2>
          <div class="card space-y-2">
            <div
              v-for="(brewery, index) in stats.topBreweries"
              :key="index"
              class="flex items-center justify-between"
            >
              <span class="text-sm truncate flex-1 mr-2">{{ brewery.name }}</span>
              <span class="text-beer-500 font-bold text-sm">{{ brewery.count }}</span>
            </div>
            <p v-if="stats.topBreweries.length === 0" class="text-gray-500 text-sm text-center py-2">
              Žádná data
            </p>
          </div>
        </section>
      </div>

      <!-- Empty state -->
      <div v-if="stats.totalBeers === 0" class="card text-center py-8">
        <p class="text-4xl mb-4">🍺</p>
        <p class="text-gray-400">Zatím žádné pivo. Čas přidat první!</p>
        <router-link to="/" class="btn btn-primary mt-4">
          Přidat pivo
        </router-link>
      </div>
    </template>
  </div>
</template>
