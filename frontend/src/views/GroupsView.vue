<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useGroupsStore } from '../stores/groups'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/api'
import GroupSelector from '../components/GroupSelector.vue'

const groups = useGroupsStore()
const auth = useAuthStore()

const showCreateModal = ref(false)
const showJoinModal = ref(false)
const newGroupName = ref('')
const joinCode = ref('')
const error = ref('')
const loading = ref(false)

// Leaderboard
const leaderboard = ref([])
const leaderboardLoading = ref(false)

// Period tabs
const periods = [
  { id: 'today', label: 'Dnes' },
  { id: 'week', label: 'Týden' },
  { id: 'month', label: 'Měsíc' },
  { id: 'year', label: 'Rok' },
]
const activePeriod = ref('today')

// Computed max value for chart scaling
const maxBeers = computed(() => {
  if (leaderboard.value.length === 0) return 1
  return Math.max(...leaderboard.value.map(e => e.totalBeers), 1)
})

// Total beers in group for the period
const totalGroupBeers = computed(() => {
  return leaderboard.value.reduce((sum, e) => sum + e.totalBeers, 0)
})

async function fetchLeaderboard() {
  if (!groups.activeGroup) {
    leaderboard.value = []
    return
  }

  leaderboardLoading.value = true
  try {
    const data = await api.getLeaderboard(groups.activeGroup.id, activePeriod.value)
    leaderboard.value = data.leaderboard || []
  } catch (err) {
    console.error('Failed to fetch leaderboard:', err)
    leaderboard.value = []
  }
  leaderboardLoading.value = false
}

function setPeriod(period) {
  activePeriod.value = period
  fetchLeaderboard()
}

function getMedal(index) {
  if (index === 0) return '1.'
  if (index === 1) return '2.'
  if (index === 2) return '3.'
  return `${index + 1}.`
}

function getBarWidth(beers) {
  return `${(beers / maxBeers.value) * 100}%`
}

function isCurrentUser(userId) {
  return auth.user?.id === userId
}

async function createGroup() {
  if (!newGroupName.value.trim()) {
    return
  }

  loading.value = true
  error.value = ''

  const result = await groups.createGroup(newGroupName.value)

  loading.value = false

  if (result.success) {
    showCreateModal.value = false
    newGroupName.value = ''
    fetchLeaderboard()
    return
  }

  error.value = result.error
}

async function joinGroup() {
  if (!joinCode.value.trim()) {
    return
  }

  loading.value = true
  error.value = ''

  const result = await groups.joinGroup(joinCode.value)

  loading.value = false

  if (result.success) {
    showJoinModal.value = false
    joinCode.value = ''
    fetchLeaderboard()
    return
  }

  error.value = result.error
}

function copyInviteCode(code) {
  navigator.clipboard.writeText(code)
}

watch(() => groups.activeGroupId, fetchLeaderboard)

onMounted(async () => {
  await groups.fetchGroups()
  await fetchLeaderboard()
})
</script>

<template>
  <div class="max-w-lg mx-auto px-4 py-6 pb-24">
    <header class="mb-6">
      <h1 class="text-2xl font-bold">Skupiny</h1>
    </header>

    <!-- Group selector (if has groups) -->
    <div v-if="groups.groups.length > 0" class="mb-6">
      <GroupSelector />
    </div>

    <!-- No group message -->
    <div v-if="groups.groups.length === 0" class="card text-center py-8 mb-6">
      <p class="text-4xl mb-4">:(</p>
      <p class="text-gray-400 mb-4">Nejste členem žádné skupiny</p>
      <div class="flex gap-3 justify-center">
        <button @click="showCreateModal = true" class="btn btn-primary">
          Vytvořit skupinu
        </button>
        <button @click="showJoinModal = true" class="btn btn-secondary">
          Připojit se
        </button>
      </div>
    </div>

    <!-- Leaderboard section -->
    <template v-if="groups.activeGroup">
      <!-- Period tabs -->
      <div class="flex bg-gray-800 rounded-xl p-1 mb-6">
        <button
          v-for="period in periods"
          :key="period.id"
          @click="setPeriod(period.id)"
          class="flex-1 py-2.5 px-3 rounded-lg font-medium text-sm transition-all duration-200"
          :class="activePeriod === period.id
            ? 'bg-beer-500 text-white shadow-lg'
            : 'text-gray-400 hover:text-white'"
        >
          {{ period.label }}
        </button>
      </div>

      <!-- Stats summary -->
      <div class="card mb-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-400 text-sm">Celkem ve skupině</p>
            <p class="text-3xl font-bold text-beer-500">{{ totalGroupBeers }}</p>
          </div>
          <div class="text-right">
            <p class="text-gray-400 text-sm">Členů</p>
            <p class="text-2xl font-bold">{{ leaderboard.length }}</p>
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="leaderboardLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-beer-500"></div>
      </div>

      <template v-else>
        <!-- Bar chart with clickable names -->
        <section v-if="leaderboard.length > 0" class="mb-6">
          <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Žebříček</h2>
          <div class="card">
            <div class="space-y-4">
              <div
                v-for="(entry, index) in leaderboard"
                :key="entry.userId"
                class="group"
              >
                <div class="flex items-center justify-between mb-1.5">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="text-lg w-8 text-gray-400 shrink-0">{{ getMedal(index) }}</span>
                    <router-link
                      v-if="!isCurrentUser(entry.userId)"
                      :to="{ name: 'user-stats', params: { userId: entry.userId } }"
                      class="font-medium text-sm hover:text-beer-400 transition-colors truncate"
                    >
                      {{ entry.userName }}
                    </router-link>
                    <span v-else class="font-medium text-sm text-beer-400 truncate">
                      {{ entry.userName }} (vy)
                    </span>
                  </div>
                  <span class="text-beer-500 font-bold shrink-0 ml-2">{{ entry.totalBeers }}</span>
                </div>
                <div class="h-6 bg-gray-700 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-gradient-to-r from-beer-600 to-beer-400 rounded-full transition-all duration-500 flex items-center justify-end pr-2"
                    :style="{ width: getBarWidth(entry.totalBeers) }"
                  >
                    <span v-if="entry.totalBeers > 0 && totalGroupBeers > 0" class="text-xs font-medium text-white/80">
                      {{ Math.round((entry.totalBeers / totalGroupBeers) * 100) }}%
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Empty leaderboard -->
        <div v-else class="card text-center py-8 mb-6">
          <p class="text-4xl mb-4">:(</p>
          <p class="text-gray-400">Zatím žádná data pro toto období</p>
        </div>

      </template>
    </template>

    <!-- Group management section -->
    <section v-if="groups.groups.length > 0" class="mb-6">
      <h2 class="text-sm font-medium mb-3 text-gray-400 uppercase tracking-wider">Správa skupin</h2>

      <!-- Actions -->
      <div class="flex gap-3 mb-4">
        <button @click="showCreateModal = true" class="btn btn-primary flex-1">
          + Nová skupina
        </button>
        <button @click="showJoinModal = true" class="btn btn-secondary flex-1">
          Připojit se
        </button>
      </div>

      <!-- Groups list -->
      <div class="space-y-3">
        <div
          v-for="group in groups.groups"
          :key="group.id"
          class="card"
          :class="{ 'ring-2 ring-beer-500': group.id === groups.activeGroupId }"
        >
          <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold">{{ group.name }}</h3>
            <button
              v-if="group.id !== groups.activeGroupId"
              @click="groups.setActiveGroup(group.id)"
              class="text-sm text-beer-500"
            >
              Aktivovat
            </button>
            <span v-else class="text-sm text-green-500">Aktivní</span>
          </div>

          <div class="flex items-center justify-between text-sm text-gray-400">
            <span>{{ group.memberCount }} členů</span>
            <button
              @click="copyInviteCode(group.inviteCode)"
              class="flex items-center gap-2 hover:text-white transition-colors"
            >
              <span class="font-mono bg-gray-700 px-2 py-1 rounded">{{ group.inviteCode }}</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Create modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
      <div class="card w-full max-w-sm">
        <h2 class="text-xl font-bold mb-4">Vytvořit skupinu</h2>

        <form @submit.prevent="createGroup">
          <input
            v-model="newGroupName"
            type="text"
            placeholder="Název skupiny"
            class="input mb-4"
            required
          />

          <div v-if="error" class="text-red-500 text-sm mb-4">{{ error }}</div>

          <div class="flex gap-3">
            <button type="button" @click="showCreateModal = false" class="btn btn-secondary flex-1">
              Zrušit
            </button>
            <button type="submit" :disabled="loading" class="btn btn-primary flex-1">
              {{ loading ? 'Vytváření...' : 'Vytvořit' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Join modal -->
    <div v-if="showJoinModal" class="fixed inset-0 bg-black/70 flex items-center justify-center p-4 z-50">
      <div class="card w-full max-w-sm">
        <h2 class="text-xl font-bold mb-4">Připojit se ke skupině</h2>

        <form @submit.prevent="joinGroup">
          <input
            v-model="joinCode"
            type="text"
            placeholder="Zadejte kód skupiny"
            class="input mb-4 uppercase"
            maxlength="16"
            required
          />

          <div v-if="error" class="text-red-500 text-sm mb-4">{{ error }}</div>

          <div class="flex gap-3">
            <button type="button" @click="showJoinModal = false" class="btn btn-secondary flex-1">
              Zrušit
            </button>
            <button type="submit" :disabled="loading" class="btn btn-primary flex-1">
              {{ loading ? 'Připojování...' : 'Připojit' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
