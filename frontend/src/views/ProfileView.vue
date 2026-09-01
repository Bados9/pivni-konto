<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useGroupsStore } from '../stores/groups'
import { api } from '../services/api'
import { pushService } from '../services/push'
import BeerSelect from '../components/BeerSelect.vue'
import AchievementsPanel from '../components/AchievementsPanel.vue'

const router = useRouter()
const auth = useAuthStore()
const groups = useGroupsStore()

const achievements = ref({ summary: null, achievements: [], grouped: {} })

const beers = ref([])
const defaultBeerId = ref(auth.user?.defaultBeerId || null)
const defaultBeerLoading = ref(false)

const editingName = ref(false)
const editName = ref('')
const editNameError = ref('')
const editNameLoading = ref(false)

function startEditingName() {
  editName.value = auth.user.name
  editNameError.value = ''
  editingName.value = true
}

function cancelEditingName() {
  editingName.value = false
  editNameError.value = ''
}

async function saveName() {
  const trimmed = editName.value.trim()
  if (trimmed.length < 2 || trimmed.length > 100) {
    editNameError.value = 'Jm\u00e9no mus\u00ed m\u00edt 2 a\u017e 100 znak\u016f'
    return
  }

  editNameLoading.value = true
  const result = await auth.updateProfile({ name: trimmed })
  editNameLoading.value = false

  if (!result.success) {
    editNameError.value = result.error
    return
  }

  editingName.value = false
}

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

async function fetchBeers() {
  try {
    const response = await api.getBeers()
    beers.value = response['hydra:member'] || response.member || response
  } catch (error) {
    console.error('Failed to fetch beers:', error)
  }
}

async function saveDefaultBeer(beerId) {
  defaultBeerId.value = beerId
  defaultBeerLoading.value = true
  try {
    await auth.updateProfile({ defaultBeerId: beerId })
  } catch (error) {
    console.error('Failed to save default beer:', error)
  } finally {
    defaultBeerLoading.value = false
  }
}

// Push notifications
const pushSupported = ref(false)
const pushEnabled = ref(false)
const pushLoading = ref(false)
const testLoading = ref(false)
const testSuccess = ref(false)

async function checkPushStatus() {
  pushSupported.value = pushService.isSupported()
  if (!pushSupported.value) {
    return
  }
  pushEnabled.value = await pushService.isSubscribed()
}

async function togglePush() {
  pushLoading.value = true
  try {
    if (pushEnabled.value) {
      await pushService.unsubscribe()
      pushEnabled.value = false
      return
    }
    pushEnabled.value = await pushService.subscribe()
  } catch (error) {
    console.error('Push toggle failed:', error)
  } finally {
    pushLoading.value = false
  }
}

async function testNotification() {
  testLoading.value = true
  testSuccess.value = false
  try {
    await pushService.sendTest()
    testSuccess.value = true
    setTimeout(() => { testSuccess.value = false }, 3000)
  } catch (error) {
    console.error('Test notification failed:', error)
  } finally {
    testLoading.value = false
  }
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
  fetchBeers()
  checkPushStatus()
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
        <div class="flex-1 min-w-0">
          <div v-if="!editingName" class="flex items-center gap-2">
            <h2 class="text-xl font-bold truncate">{{ auth.user.name }}</h2>
            <button
              @click="startEditingName"
              class="text-gray-400 hover:text-beer-500 transition-colors flex-shrink-0"
              title="Upravit jméno"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
            </button>
          </div>
          <div v-if="editingName" class="space-y-2">
            <div class="flex items-center gap-2">
              <input
                v-model="editName"
                type="text"
                class="input text-sm py-1.5"
                maxlength="100"
                @keyup.enter="saveName"
                @keyup.escape="cancelEditingName"
              />
              <button
                @click="saveName"
                :disabled="editNameLoading"
                class="btn btn-primary text-xs py-1.5 px-3 flex-shrink-0"
              >
                {{ editNameLoading ? '...' : 'Uložit' }}
              </button>
              <button
                @click="cancelEditingName"
                class="btn btn-secondary text-xs py-1.5 px-3 flex-shrink-0"
              >
                Zrušit
              </button>
            </div>
            <p v-if="editNameError" class="text-red-500 text-xs">{{ editNameError }}</p>
          </div>
          <p class="text-gray-400 text-sm">{{ auth.user.email }}</p>
        </div>
      </div>

      <div class="flex items-center justify-between text-sm text-gray-400 border-t border-gray-700 pt-4">
        <span>Účet vytvořen</span>
        <span>{{ formatDate(auth.user.createdAt) }}</span>
      </div>
    </div>

    <!-- Default beer setting -->
    <div class="card mb-6">
      <h3 class="font-semibold flex items-center gap-2 mb-3">
        <span>🍺</span>
        <span>Výchozí pivo</span>
      </h3>
      <p class="text-sm text-gray-400 mb-3">
        Pivo, které se předvyplní při prvním záznamu dne
      </p>
      <BeerSelect
        :modelValue="defaultBeerId"
        @update:modelValue="saveDefaultBeer"
        :beers="beers"
        @beersUpdated="fetchBeers"
      />
      <p v-if="defaultBeerLoading" class="text-xs text-gray-500 mt-2">Ukládám...</p>
    </div>

    <!-- Push notifications -->
    <div v-if="pushSupported" class="card mb-6">
      <h3 class="font-semibold flex items-center gap-2 mb-3">
        <span>🔔</span>
        <span>Notifikace</span>
      </h3>
      <p class="text-sm text-gray-400 mb-3">
        Buďte v obraze o dění ve vašich skupinách
      </p>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-sm">Push notifikace</span>
          <button
            @click="togglePush"
            :disabled="pushLoading"
            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
            :class="pushEnabled ? 'bg-beer-500' : 'bg-gray-600'"
          >
            <span
              class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
              :class="pushEnabled ? 'translate-x-6' : 'translate-x-1'"
            />
          </button>
        </div>

        <button
          v-if="pushEnabled"
          @click="testNotification"
          :disabled="testLoading"
          class="btn btn-secondary text-sm w-full"
        >
          {{ testLoading ? 'Odesílám...' : 'Otestovat notifikaci' }}
        </button>
        <p v-if="testSuccess" class="text-green-500 text-xs text-center">Testovací notifikace odeslána!</p>
      </div>
    </div>

    <!-- Achievements -->
    <AchievementsPanel :achievements="achievements" highlight="recent" />

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
