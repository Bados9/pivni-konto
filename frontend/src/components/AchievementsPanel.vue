<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  achievements: {
    type: Object,
    required: true // { summary, achievements, grouped }
  },
  highlight: {
    type: String,
    default: 'recent' // 'recent' = last unlocked, 'top' = most valuable unlocked
  }
})

const showAll = ref(false)
const selectedCategory = ref('all')

const categoryNames = {
  milestones: '🎯 Milníky',
  volume: '🍺 Objem',
  variety: '🔍 Rozmanitost',
  time: '⏰ Čas',
  performance: '💪 Výkony',
  special: '✨ Speciální',
  group: '👥 Skupinové',
}

const filteredAchievements = computed(() => {
  if (selectedCategory.value === 'all') {
    return Object.values(props.achievements.grouped || {}).flat()
  }
  return props.achievements.grouped?.[selectedCategory.value] || []
})

const unlockedCount = computed(() => props.achievements.summary?.unlocked || 0)
const totalCount = computed(() => props.achievements.summary?.total || 0)

// Hardest achieved tier per category (definitions are ordered by difficulty),
// topped up with the most recently unlocked ones
const topUnlocked = computed(() => {
  const top = []
  for (const list of Object.values(props.achievements.grouped || {})) {
    const unlocked = list.filter(a => a.unlocked)
    if (unlocked.length > 0) {
      top.push(unlocked[unlocked.length - 1])
    }
  }

  const rest = (props.achievements.achievements || [])
    .filter(a => a.unlocked && !top.includes(a))
    .sort((a, b) => (b.unlockedAt || '').localeCompare(a.unlockedAt || ''))

  return [...top, ...rest].slice(0, 6)
})

const highlighted = computed(() => {
  if (props.highlight === 'top') {
    return topUnlocked.value
  }
  return props.achievements.summary?.recent || []
})

const highlightLabel = computed(() => {
  return props.highlight === 'top' ? 'Nejcennější' : 'Poslední odemčené'
})
</script>

<template>
  <!-- Achievements summary -->
  <div class="card mb-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold flex items-center gap-2">
        <span>🏆</span>
        <span>Achievementy</span>
      </h3>
      <button
        @click="showAll = !showAll"
        class="text-beer-500 text-sm hover:underline"
      >
        {{ showAll ? 'Skrýt' : 'Zobrazit vše' }}
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

    <!-- Highlighted achievements -->
    <div v-if="!showAll && highlighted.length > 0" class="space-y-2">
      <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">{{ highlightLabel }}</p>
      <div
        v-for="achievement in highlighted"
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
    <div v-if="!showAll && highlighted.length === 0" class="text-center py-4">
      <p class="text-4xl mb-2">🎯</p>
      <p class="text-gray-400 text-sm">Zatím žádné achievementy</p>
      <p class="text-gray-500 text-xs">Začni přidávat piva!</p>
    </div>
  </div>

  <!-- All achievements (expanded) -->
  <div v-if="showAll" class="card mb-6">
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
</template>
