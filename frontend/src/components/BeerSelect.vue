<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { onClickOutside } from '@vueuse/core'

const props = defineProps({
  modelValue: String,
  beers: Array
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const dropdownRef = ref(null)
const searchInputRef = ref(null)
const searchQuery = ref('')

// Favorites stored in localStorage
const favorites = ref(new Set(JSON.parse(localStorage.getItem('favoriteBeers') || '[]')))

watch(favorites, (newFavorites) => {
  localStorage.setItem('favoriteBeers', JSON.stringify([...newFavorites]))
}, { deep: true })

onClickOutside(dropdownRef, () => {
  isOpen.value = false
})

// Focus search input when dropdown opens
watch(isOpen, async (open) => {
  if (open) {
    searchQuery.value = ''
    await nextTick()
    searchInputRef.value?.focus()
  }
})

const selectedBeer = computed(() => {
  if (!props.modelValue) return null
  return props.beers.find(b => b.id === props.modelValue)
})

const displayText = computed(() => {
  if (!selectedBeer.value) return 'Obecné pivo'
  return selectedBeer.value.name
})

// Filter beers by search query
const filteredBeers = computed(() => {
  if (!props.beers) return []
  if (!searchQuery.value.trim()) return props.beers

  const query = searchQuery.value.toLowerCase().trim()
  return props.beers.filter(beer =>
    beer.name.toLowerCase().includes(query) ||
    (beer.brewery && beer.brewery.toLowerCase().includes(query))
  )
})

// Sort beers: favorites first, then alphabetically
const sortedBeers = computed(() => {
  return [...filteredBeers.value].sort((a, b) => {
    const aFav = favorites.value.has(a.id)
    const bFav = favorites.value.has(b.id)
    if (aFav && !bFav) return -1
    if (!aFav && bFav) return 1
    return a.name.localeCompare(b.name, 'cs')
  })
})

const favoriteBeers = computed(() => sortedBeers.value.filter(b => favorites.value.has(b.id)))
const otherBeers = computed(() => sortedBeers.value.filter(b => !favorites.value.has(b.id)))

function selectBeer(beerId) {
  emit('update:modelValue', beerId)
  isOpen.value = false
}

function toggleFavorite(event, beerId) {
  event.stopPropagation()
  const newFavorites = new Set(favorites.value)
  if (newFavorites.has(beerId)) {
    newFavorites.delete(beerId)
  } else {
    newFavorites.add(beerId)
  }
  favorites.value = newFavorites
}

function isFavorite(beerId) {
  return favorites.value.has(beerId)
}
</script>

<template>
  <div ref="dropdownRef" class="relative">
    <!-- Trigger button -->
    <button
      @click="isOpen = !isOpen"
      type="button"
      class="w-full px-4 py-3 bg-gray-700 border-2 border-gray-600 rounded-xl text-white text-left
             transition-all duration-200 flex items-center justify-between
             hover:border-beer-500/50 focus:border-beer-500 focus:outline-none focus:ring-2 focus:ring-beer-500/20"
      :class="{ 'border-beer-500': isOpen }"
    >
      <div class="flex items-center gap-3 min-w-0">
        <span class="text-xl flex-shrink-0">{{ selectedBeer && isFavorite(selectedBeer.id) ? '⭐' : '🍺' }}</span>
        <div class="min-w-0">
          <span class="block truncate font-medium">{{ displayText }}</span>
          <span v-if="selectedBeer" class="block text-sm text-gray-400 truncate">
            {{ selectedBeer.brewery }}
          </span>
        </div>
      </div>
      <svg
        class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <!-- Dropdown menu -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="isOpen"
        class="absolute z-50 w-full mt-2 bg-gray-800 border border-gray-700 rounded-xl shadow-2xl overflow-hidden"
      >
        <!-- Search input -->
        <div class="p-3 border-b border-gray-700">
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              ref="searchInputRef"
              v-model="searchQuery"
              type="text"
              placeholder="Hledat pivo..."
              class="w-full pl-9 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white text-sm
                     placeholder-gray-500 focus:outline-none focus:border-beer-500 focus:ring-1 focus:ring-beer-500"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="max-h-64 overflow-y-auto custom-scrollbar">
          <!-- No results -->
          <div v-if="filteredBeers.length === 0 && searchQuery" class="px-4 py-8 text-center text-gray-500">
            <span class="text-2xl block mb-2">🔍</span>
            Žádné pivo nenalezeno
          </div>

          <template v-else>
            <!-- Default option (only show when not searching) -->
            <button
              v-if="!searchQuery"
              @click="selectBeer(null)"
              class="w-full px-4 py-3 flex items-center gap-3 text-left transition-colors
                     hover:bg-gray-700"
              :class="{ 'bg-beer-500/20 text-beer-400': !modelValue }"
            >
              <span class="text-xl">🍺</span>
              <div class="flex-1">
                <span class="block font-medium">Obecné pivo</span>
                <span class="block text-sm text-gray-500">Bez specifikace</span>
              </div>
              <svg v-if="!modelValue" class="w-5 h-5 text-beer-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
            </button>

            <!-- Favorites section -->
            <template v-if="favoriteBeers.length > 0">
              <div class="px-4 py-2 bg-gray-900/50 border-y border-gray-700">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">⭐ Oblíbené</span>
              </div>
              <button
                v-for="beer in favoriteBeers"
                :key="beer.id"
                @click="selectBeer(beer.id)"
                class="w-full px-4 py-3 flex items-center gap-3 text-left transition-colors
                       hover:bg-gray-700 group"
                :class="{ 'bg-beer-500/20 text-beer-400': modelValue === beer.id }"
              >
                <button
                  @click="toggleFavorite($event, beer.id)"
                  class="text-xl flex-shrink-0 hover:scale-110 transition-transform"
                >
                  ⭐
                </button>
                <div class="min-w-0 flex-1">
                  <span class="block font-medium truncate">{{ beer.name }}</span>
                  <span class="block text-sm text-gray-500 truncate">
                    {{ beer.brewery }}
                    <span v-if="beer.abv" class="text-beer-600">· {{ beer.abv }}%</span>
                  </span>
                </div>
                <svg v-if="modelValue === beer.id" class="w-5 h-5 flex-shrink-0 text-beer-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
              </button>
            </template>

            <!-- Other beers section -->
            <template v-if="otherBeers.length > 0">
              <div class="px-4 py-2 bg-gray-900/50 border-y border-gray-700">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {{ searchQuery ? 'Výsledky hledání' : 'Všechna piva' }}
                </span>
              </div>
              <button
                v-for="beer in otherBeers"
                :key="beer.id"
                @click="selectBeer(beer.id)"
                class="w-full px-4 py-3 flex items-center gap-3 text-left transition-colors
                       hover:bg-gray-700 group"
                :class="{ 'bg-beer-500/20 text-beer-400': modelValue === beer.id }"
              >
                <button
                  @click="toggleFavorite($event, beer.id)"
                  class="text-xl flex-shrink-0 hover:scale-110 transition-transform opacity-30 group-hover:opacity-100"
                  title="Přidat do oblíbených"
                >
                  ☆
                </button>
                <div class="min-w-0 flex-1">
                  <span class="block font-medium truncate">{{ beer.name }}</span>
                  <span class="block text-sm text-gray-500 truncate">
                    {{ beer.brewery }}
                    <span v-if="beer.abv" class="text-beer-600">· {{ beer.abv }}%</span>
                  </span>
                </div>
                <svg v-if="modelValue === beer.id" class="w-5 h-5 flex-shrink-0 text-beer-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
              </button>
            </template>
          </template>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: rgba(245, 158, 11, 0.3) transparent;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(245, 158, 11, 0.3);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(245, 158, 11, 0.5);
}
</style>
