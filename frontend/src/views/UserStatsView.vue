<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import StatsCard from '../components/StatsCard.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const stats = ref(null)
const loading = ref(true)
const error = ref('')

const chartHeight = 120

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

const chartData = computed(() => {
  if (!stats.value) return []
  const countsMap = {}
  ;(stats.value.dailyCounts || []).forEach(d => {
    countsMap[d.date] = d.count
  })
  return last30Days.value.map(date => ({
    date,
    count: countsMap[date] || 0
  }))
})

const maxCount = computed(() => Math.max(...chartData.value.map(d => d.count), 1))

function formatVolume(ml) {
  return (ml / 1000).toFixed(1)
}

function formatShortDate(dateStr) {
  const date = new Date(dateStr)
  return `${date.getDate()}.${date.getMonth() + 1}.`
}

async function fetchUserStats() {
  const userId = route.params.userId

  if (userId === 'me' || userId === auth.user?.id) {
    router.replace({ name: 'stats' })
    return
  }

  loading.value = true
  error.value = ''

  try {
    stats.value = await api.getUserStats(userId)
  } catch (err) {
    error.value = err.message
    stats.value = null
  }

  loading.value = false
}

watch(() => route.params.userId, fetchUserStats)
onMounted(fetchUserStats)
</script>

<template>
  <div class="max-w-lg mx-auto px-4 py-6 pb-24">
    <!-- Back button -->
    <button @click="router.back()" class="flex items-center gap-2 text-gray-400 hover:text-white mb-4">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
      Zpět
    </button>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-beer-500"></div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="card text-center py-8">
      <p class="text-4xl mb-4">:(</p>
      <p class="text-red-400">{{ error }}</p>
      <router-link to="/groups" class="btn btn-primary mt-4">
        Zpět na skupiny
      </router-link>
    </div>

    <!-- Stats content -->
    <template v-else-if="stats">
      <header class="mb-6">
        <h1 class="text-2xl font-bold">{{ stats.userName }}</h1>
        <p class="text-gray-400">Statistiky uživatele</p>
      </header>

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
            <div class="absolute left-0 top-0 bottom-5 w-6 flex flex-col justify-between text-xs text-gray-500">
              <span>{{ maxCount }}</span>
              <span>0</span>
            </div>
            <div class="absolute left-7 right-0 top-2 bottom-5 flex items-end gap-px">
              <div
                v-for="day in chartData"
                :key="day.date"
                class="flex-1 bg-beer-500/80 rounded-t transition-all hover:bg-beer-400"
                :style="{ height: (day.count / maxCount) * 100 + '%', minHeight: day.count > 0 ? '4px' : '0' }"
                :title="`${formatShortDate(day.date)}: ${day.count} piv`"
              ></div>
            </div>
            <div class="absolute left-7 right-0 bottom-0 flex justify-between text-xs text-gray-500">
              <span>{{ formatShortDate(chartData[0]?.date) }}</span>
              <span>{{ formatShortDate(chartData[chartData.length - 1]?.date) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Top beers and breweries -->
      <div class="grid grid-cols-2 gap-4 mb-6">
        <section>
          <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Top piva</h2>
          <div class="card space-y-2">
            <div
              v-for="(beer, index) in stats.topBeers"
              :key="index"
              class="flex items-center justify-between"
            >
              <span class="text-sm truncate flex-1 mr-2">{{ beer.name }}</span>
              <span class="text-beer-500 font-bold text-sm">{{ Number.isInteger(beer.count) ? beer.count : beer.count.toFixed(1) }}</span>
            </div>
            <p v-if="!stats.topBeers || stats.topBeers.length === 0" class="text-gray-500 text-sm text-center py-2">
              Žádná data
            </p>
          </div>
        </section>

        <section>
          <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Top pivovary</h2>
          <div class="card space-y-2">
            <div
              v-for="(brewery, index) in stats.topBreweries"
              :key="index"
              class="flex items-center justify-between"
            >
              <span class="text-sm truncate flex-1 mr-2">{{ brewery.name }}</span>
              <span class="text-beer-500 font-bold text-sm">{{ Number.isInteger(brewery.count) ? brewery.count : brewery.count.toFixed(1) }}</span>
            </div>
            <p v-if="!stats.topBreweries || stats.topBreweries.length === 0" class="text-gray-500 text-sm text-center py-2">
              Žádná data
            </p>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
