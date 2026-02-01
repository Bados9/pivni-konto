<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useGroupsStore } from '../stores/groups'
import { api } from '../services/api'

const router = useRouter()
const auth = useAuthStore()
const groups = useGroupsStore()

const achievements = ref({ summary: null, grouped: {} })
const showAllAchievements = ref(false)
const selectedCategory = ref('all')

const categoryNames = {
  milestones: '🎯 Milníky',
  volume: '🍺 Objem',
  variety: '🔍 Rozmanitost',
  time: '⏰ Čas',
  performance: '💪 Výkony',
  special: '✨ Speciální',
}

const filteredAchievements = computed(() => {
  if (selectedCategory.value === 'all') {
    return Object.values(achievements.value.grouped).flat()
  }
  return achievements.value.grouped[selectedCategory.value] || []
})

const unlockedCount = computed(() => {
  return achievements.value.summary?.unlocked || 0
})

const totalCount = computed(() => {
  return achievements.value.summary?.total || 0
})

function logout() {
  auth.logout()
  router.push('/login')
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString('cs-CZ', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

async function fetchAchievements() {
  try {
    achievements.value = await api.getMyAchievements()
  } catch (error) {
    console.error('Failed to fetch achievements:', error)
  }
}

onMounted(() => {
  groups.fetchGroups()
  fetchAchievements()
})
</script>

<template>
  <div class="max-w-lg mx-auto px-4 py-6 pb-24">
    <header class="mb-6">
      <h1 class="text-2xl font-bold">👤 Profil</h1>
    </header>

    <!-- User info -->
    <div v-if="auth.user" class="card mb-6">
      <div class="flex items-center gap-4 mb-4">
        <div class="w-16 h-16 rounded-full bg-beer-500 flex items-center justify-center text-2xl font-bold">
          {{ auth.user.name.charAt(0).toUpperCase() }}
        </div>
        <div>
          <h2 class="text-xl font-bold">{{ auth.user.name }}</h2>
          <p class="text-gray-400 text-sm">{{ auth.user.email }}</p>
        </div>
      </div>

      <div class="flex items-center justify-between text-sm text-gray-400 border-t border-gray-700 pt-4">
        <span>Účet vytvořen</span>
        <span>{{ formatDate(auth.user.createdAt) }}</span>
      </div>
    </div>

    <!-- Achievements summary -->
    <div class="card mb-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold flex items-center gap-2">
          <span>🏆</span>
          <span>Achievementy</span>
        </h3>
        <button
          @click="showAllAchievements = !showAllAchievements"
          class="text-beer-500 text-sm hover:underline"
        >
          {{ showAllAchievements ? 'Skrýt' : 'Zobrazit vše' }}
        </button>
      </div>

      <!-- Progress bar -->
      <div v-if="achievements.summary" class="mb-4">
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="text-gray-400">Odemčeno</span>
          <span class="font-medium">{{ unlockedCount }} / {{ totalCount }}</span>
        </div>
        <div class="h-3 bg-gray-700 rounded-full overflow-hidden">
          <div
            class="h-full bg-gradient-to-r from-beer-600 to-yellow-400 rounded-full transition-all duration-500"
            :style="{ width: `${achievements.summary.percentage}%` }"
          ></div>
        </div>
      </div>

      <!-- Recent achievements -->
      <div v-if="!showAllAchievements && achievements.summary?.recent?.length > 0" class="space-y-2">
        <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Poslední odemčené</p>
        <div
          v-for="achievement in achievements.summary.recent"
          :key="achievement.id"
          class="flex items-center gap-3 py-2 px-3 bg-gray-700/50 rounded-lg"
        >
          <span class="text-2xl">{{ achievement.icon }}</span>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="font-medium text-sm truncate">{{ achievement.name }}</p>
              <span v-if="achievement.timesUnlocked > 1" class="text-xs bg-beer-600 text-white px-1.5 py-0.5 rounded-full font-medium">
                {{ achievement.timesUnlocked }}×
              </span>
            </div>
            <p class="text-xs text-gray-400 truncate">{{ achievement.description }}</p>
          </div>
          <span class="text-green-500">✓</span>
        </div>
      </div>

      <!-- No achievements yet -->
      <div v-if="!showAllAchievements && (!achievements.summary?.recent || achievements.summary.recent.length === 0)" class="text-center py-4">
        <p class="text-4xl mb-2">🎯</p>
        <p class="text-gray-400 text-sm">Zatím žádné achievementy</p>
        <p class="text-gray-500 text-xs">Začni přidávat piva!</p>
      </div>
    </div>

    <!-- All achievements (expanded) -->
    <div v-if="showAllAchievements" class="card mb-6">
      <!-- Category filter -->
      <div class="flex flex-wrap gap-2 mb-4 pb-4 border-b border-gray-700">
        <button
          @click="selectedCategory = 'all'"
          class="px-3 py-1.5 text-xs font-medium rounded-full transition-colors"
          :class="selectedCategory === 'all'
            ? 'bg-beer-500 text-white'
            : 'bg-gray-700 text-gray-400 hover:text-white'"
        >
          Vše
        </button>
        <button
          v-for="(name, key) in categoryNames"
          :key="key"
          @click="selectedCategory = key"
          class="px-3 py-1.5 text-xs font-medium rounded-full transition-colors"
          :class="selectedCategory === key
            ? 'bg-beer-500 text-white'
            : 'bg-gray-700 text-gray-400 hover:text-white'"
        >
          {{ name }}
        </button>
      </div>

      <!-- Achievement list -->
      <div class="space-y-3">
        <div
          v-for="achievement in filteredAchievements"
          :key="achievement.id"
          class="flex items-center gap-3 py-3 px-3 rounded-lg transition-colors"
          :class="achievement.unlocked ? 'bg-gray-700/50' : 'bg-gray-800/50 opacity-60'"
        >
          <span class="text-3xl" :class="{ 'grayscale': !achievement.unlocked }">
            {{ achievement.icon }}
          </span>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="font-medium text-sm">{{ achievement.name }}</p>
              <span v-if="achievement.unlocked && achievement.timesUnlocked > 1" class="text-xs bg-beer-600 text-white px-1.5 py-0.5 rounded-full font-medium">
                {{ achievement.timesUnlocked }}×
              </span>
              <span v-if="achievement.unlocked" class="text-green-500 text-xs">✓</span>
            </div>
            <p class="text-xs text-gray-400 mb-1">{{ achievement.description }}</p>
            <!-- Progress bar for locked achievements -->
            <div v-if="!achievement.unlocked && achievement.target > 1" class="flex items-center gap-2">
              <div class="flex-1 h-1.5 bg-gray-600 rounded-full overflow-hidden">
                <div
                  class="h-full bg-beer-500/50 rounded-full"
                  :style="{ width: `${achievement.percentage}%` }"
                ></div>
              </div>
              <span class="text-xs text-gray-500">{{ achievement.progress }}/{{ achievement.target }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Groups section -->
    <div class="card mb-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold flex items-center gap-2">
          <span>👥</span>
          <span>Moje skupiny</span>
        </h3>
        <router-link to="/groups" class="text-beer-500 text-sm hover:underline">
          Spravovat
        </router-link>
      </div>

      <div v-if="groups.groups.length > 0" class="space-y-2">
        <div
          v-for="group in groups.groups"
          :key="group.id"
          class="flex items-center justify-between py-2 px-3 bg-gray-700/50 rounded-lg"
        >
          <span>{{ group.name }}</span>
          <span class="text-xs text-gray-400">{{ group.memberCount }} členů</span>
        </div>
      </div>
      <div v-else class="text-center py-4">
        <p class="text-gray-400 text-sm mb-3">Nejste členem žádné skupiny</p>
        <router-link to="/groups" class="btn btn-primary text-sm">
          Vytvořit nebo se připojit
        </router-link>
      </div>
    </div>

    <button @click="logout" class="btn btn-secondary w-full">
      Odhlásit se
    </button>

    <div class="mt-8 text-center text-gray-500 text-sm">
      <p>Pivní Konto v1.0.0</p>
      <p class="mt-1">🍺 Na zdraví!</p>
    </div>
  </div>
</template>
