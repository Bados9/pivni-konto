<script setup>
import { ref, onMounted, watch } from 'vue'
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

function formatVolume(ml) {
  return (ml / 1000).toFixed(1)
}

async function fetchUserStats() {
  const userId = route.params.userId

  // Redirect to own stats page if viewing self
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

      <!-- Total volume -->
      <section class="mb-6">
        <div class="card">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm">Celkový objem</p>
              <p class="text-3xl font-bold text-beer-500">{{ formatVolume(stats.totalVolume) }}l</p>
            </div>
            <div class="text-right">
              <p class="text-gray-400 text-sm">Celkem piv</p>
              <p class="text-2xl font-bold">{{ stats.totalBeers }}</p>
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
