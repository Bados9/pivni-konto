<script setup>
import { ref, onMounted, computed } from 'vue'
import { useGroupsStore } from '../stores/groups'
import { api } from '../services/api'
import BeerButton from '../components/BeerButton.vue'
import BeerSelect from '../components/BeerSelect.vue'
import StatsCard from '../components/StatsCard.vue'
import EntryList from '../components/EntryList.vue'

const groups = useGroupsStore()

const stats = ref({ today: 0, thisWeek: 0, todayEntries: [] })
const beers = ref([])
const selectedBeerId = ref(null)
const beerSize = ref('large') // 'large' = 500ml, 'small' = 300ml
const loading = ref(false)
const adding = ref(false)

// Retroactive entry
const showRetroForm = ref(false)
const retroDate = ref(formatDateForInput(new Date()))
const retroQuantity = ref(1)
const retroBeerId = ref(null)
const retroSize = ref('large')
const addingRetro = ref(false)

function formatDateForInput(date) {
  return date.toISOString().split('T')[0]
}

const retroBeer = computed(() => {
  if (!retroBeerId.value) return null
  return beers.value.find(b => b.id === retroBeerId.value)
})

const retroVolumeMl = computed(() => retroSize.value === 'large' ? 500 : 300)

const selectedBeer = computed(() => {
  if (!selectedBeerId.value) return null
  return beers.value.find(b => b.id === selectedBeerId.value)
})

const selectedBeerName = computed(() => {
  if (!selectedBeer.value) return 'Pivo'
  return selectedBeer.value.name
})

const volumeMl = computed(() => beerSize.value === 'large' ? 500 : 300)

async function fetchBeers() {
  try {
    const response = await api.getBeers()
    beers.value = response['hydra:member'] || response.member || response
  } catch (error) {
    console.error('Failed to fetch beers:', error)
  }
}

async function fetchStats() {
  loading.value = true
  try {
    stats.value = await api.getMyStats()
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  } finally {
    loading.value = false
  }
}

async function addBeer() {
  adding.value = true
  try {
    const options = {
      volumeMl: volumeMl.value
    }
    if (selectedBeerId.value) {
      options.beerId = selectedBeerId.value
    }
    // Pivo se přidá k uživateli, statistiky se zobrazí ve všech skupinách
    await api.quickAdd(options)
    await fetchStats()
  } catch (error) {
    console.error('Failed to add beer:', error)
  } finally {
    adding.value = false
  }
}

async function addRetroEntry() {
  addingRetro.value = true
  try {
    // Nastavíme čas na 20:00 vybraného dne
    const consumedAt = `${retroDate.value}T20:00:00`

    const options = {
      volumeMl: retroVolumeMl.value,
      quantity: retroQuantity.value,
      consumedAt
    }
    if (retroBeerId.value) {
      options.beerId = retroBeerId.value
    }

    await api.quickAdd(options)
    await fetchStats()

    // Reset formu
    retroQuantity.value = 1
    showRetroForm.value = false
  } catch (error) {
    console.error('Failed to add retro entry:', error)
  } finally {
    addingRetro.value = false
  }
}

async function deleteEntry(id) {
  try {
    await api.deleteEntry(id)
    await fetchStats()
  } catch (error) {
    console.error('Failed to delete entry:', error)
  }
}

onMounted(async () => {
  await groups.fetchGroups()
  await Promise.all([fetchBeers(), fetchStats()])
})
</script>

<template>
  <div class="max-w-lg mx-auto px-4 py-6">
    <header class="text-center mb-6">
      <h1 class="text-2xl font-bold text-beer-500">🍺 Pivní Konto</h1>
    </header>

    <!-- Groups info -->
    <div v-if="groups.groups.length === 0" class="card text-center mb-6">
      <p class="text-gray-400 mb-3">Připojte se ke skupině pro porovnání statistik s přáteli</p>
      <router-link to="/groups" class="btn btn-primary text-sm">
        Vytvořit nebo se připojit
      </router-link>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <StatsCard title="Dnes" :value="stats.today" icon="🍺" />
      <StatsCard title="Tento týden" :value="stats.thisWeek" icon="📊" />
    </div>

    <!-- Beer selector card -->
    <div class="card mb-6">
      <!-- Beer type select -->
      <div class="mb-4">
        <label class="block text-sm text-gray-400 mb-2">Typ piva</label>
        <BeerSelect v-model="selectedBeerId" :beers="beers" />
        <p v-if="selectedBeer" class="mt-2 text-sm text-beer-400">
          {{ selectedBeer.style }}{{ selectedBeer.abv ? ` · ${selectedBeer.abv}% alk.` : '' }}
        </p>
      </div>

      <!-- Size toggle -->
      <div>
        <label class="block text-sm text-gray-400 mb-2">Velikost</label>
        <div class="flex bg-gray-700 rounded-xl p-1">
          <button
            @click="beerSize = 'small'"
            class="flex-1 py-3 px-4 rounded-lg font-medium transition-all duration-200 flex items-center justify-center gap-2"
            :class="beerSize === 'small'
              ? 'bg-beer-500 text-white shadow-lg'
              : 'text-gray-400 hover:text-white'"
          >
            <span class="text-lg">🥃</span>
            <span>Malé</span>
            <span class="text-xs opacity-70">0,3l</span>
          </button>
          <button
            @click="beerSize = 'large'"
            class="flex-1 py-3 px-4 rounded-lg font-medium transition-all duration-200 flex items-center justify-center gap-2"
            :class="beerSize === 'large'
              ? 'bg-beer-500 text-white shadow-lg'
              : 'text-gray-400 hover:text-white'"
          >
            <span class="text-lg">🍺</span>
            <span>Velké</span>
            <span class="text-xs opacity-70">0,5l</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Big beer button -->
    <div class="flex flex-col items-center mb-8">
      <BeerButton @add="addBeer" :disabled="adding" />
      <p class="mt-4 text-gray-400 text-center">
        Přidat: <span class="text-white font-medium">{{ selectedBeerName }}</span>
        <span class="text-beer-400 ml-1">({{ volumeMl }}ml)</span>
      </p>
    </div>

    <!-- Retroactive entry toggle -->
    <div class="mb-6">
      <button
        @click="showRetroForm = !showRetroForm"
        class="w-full py-3 px-4 bg-gray-800 border border-gray-700 rounded-xl text-gray-400 hover:text-white hover:border-gray-600 transition-colors flex items-center justify-center gap-2"
      >
        <span>📅</span>
        <span>{{ showRetroForm ? 'Skrýt zpětný záznam' : 'Přidat zpětný záznam' }}</span>
      </button>
    </div>

    <!-- Retroactive entry form -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div v-if="showRetroForm" class="card mb-6 border-2 border-beer-500/30">
        <h3 class="font-semibold mb-4 flex items-center gap-2">
          <span>📅</span>
          <span>Zpětný záznam</span>
        </h3>

        <!-- Date picker -->
        <div class="mb-4">
          <label class="block text-sm text-gray-400 mb-2">Datum</label>
          <input
            v-model="retroDate"
            type="date"
            :max="formatDateForInput(new Date())"
            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-xl text-white focus:border-beer-500 focus:outline-none focus:ring-2 focus:ring-beer-500/20"
          />
        </div>

        <!-- Quantity -->
        <div class="mb-4">
          <label class="block text-sm text-gray-400 mb-2">Počet piv</label>
          <div class="flex items-center gap-3">
            <button
              @click="retroQuantity = Math.max(1, retroQuantity - 1)"
              class="w-12 h-12 bg-gray-700 rounded-xl text-xl font-bold hover:bg-gray-600 transition-colors"
              :disabled="retroQuantity <= 1"
            >
              -
            </button>
            <div class="flex-1 text-center">
              <span class="text-3xl font-bold text-beer-500">{{ retroQuantity }}</span>
              <span class="text-gray-400 ml-2">{{ retroQuantity === 1 ? 'pivo' : retroQuantity < 5 ? 'piva' : 'piv' }}</span>
            </div>
            <button
              @click="retroQuantity++"
              class="w-12 h-12 bg-gray-700 rounded-xl text-xl font-bold hover:bg-gray-600 transition-colors"
            >
              +
            </button>
          </div>
        </div>

        <!-- Beer type -->
        <div class="mb-4">
          <label class="block text-sm text-gray-400 mb-2">Typ piva</label>
          <BeerSelect v-model="retroBeerId" :beers="beers" />
        </div>

        <!-- Size toggle -->
        <div class="mb-4">
          <label class="block text-sm text-gray-400 mb-2">Velikost</label>
          <div class="flex bg-gray-700 rounded-xl p-1">
            <button
              @click="retroSize = 'small'"
              class="flex-1 py-2 px-3 rounded-lg font-medium transition-all duration-200 flex items-center justify-center gap-2 text-sm"
              :class="retroSize === 'small'
                ? 'bg-beer-500 text-white shadow-lg'
                : 'text-gray-400 hover:text-white'"
            >
              <span>🥃</span>
              <span>0,3l</span>
            </button>
            <button
              @click="retroSize = 'large'"
              class="flex-1 py-2 px-3 rounded-lg font-medium transition-all duration-200 flex items-center justify-center gap-2 text-sm"
              :class="retroSize === 'large'
                ? 'bg-beer-500 text-white shadow-lg'
                : 'text-gray-400 hover:text-white'"
            >
              <span>🍺</span>
              <span>0,5l</span>
            </button>
          </div>
        </div>

        <!-- Submit button -->
        <button
          @click="addRetroEntry"
          :disabled="addingRetro"
          class="w-full py-3 px-4 bg-beer-500 hover:bg-beer-600 disabled:bg-gray-600 rounded-xl font-semibold transition-colors flex items-center justify-center gap-2"
        >
          <span v-if="addingRetro">Přidávám...</span>
          <template v-else>
            <span>📅</span>
            <span>Přidat {{ retroQuantity }} {{ retroQuantity === 1 ? 'pivo' : retroQuantity < 5 ? 'piva' : 'piv' }}</span>
          </template>
        </button>
      </div>
    </Transition>

    <!-- Today's entries -->
    <div>
      <h2 class="text-lg font-semibold mb-4">Dnešní záznamy</h2>
      <EntryList :entries="stats.todayEntries" @delete="deleteEntry" />
    </div>
  </div>
</template>
