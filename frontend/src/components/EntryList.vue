<script setup>
defineProps({
  entries: Array
})

defineEmits(['delete'])

function formatTime(dateString) {
  const date = new Date(dateString)
  return date.toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="entry in entries"
      :key="entry.id"
      class="card flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <span class="text-2xl">🍺</span>
        <div>
          <p class="font-medium">{{ entry.beerName }}</p>
          <p class="text-sm text-gray-400">
            {{ entry.quantity }}× {{ entry.volumeMl }}ml · {{ formatTime(entry.consumedAt) }}
          </p>
        </div>
      </div>
      <button
        @click="$emit('delete', entry.id)"
        class="p-2 text-gray-400 hover:text-red-500 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
      </button>
    </div>

    <p v-if="!entries || entries.length === 0" class="text-center text-gray-500 py-8">
      Zatím žádné pivo dnes 😢
    </p>
  </div>
</template>
