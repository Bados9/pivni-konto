<script setup>
const props = defineProps({
  title: String,
  value: [String, Number],
  icon: String
})

function formatValue(val) {
  if (typeof val !== 'number') return val
  if (Number.isInteger(val)) return val
  return parseFloat(val.toFixed(1))
}
</script>

<template>
  <div v-if="icon" class="card">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-gray-400 text-sm">{{ title }}</p>
        <p class="text-3xl font-bold text-white mt-1">{{ formatValue(value) }}</p>
      </div>
      <span class="text-4xl">{{ icon }}</span>
    </div>
  </div>
  <div v-else class="bg-gray-800 rounded-xl p-2.5 shadow-lg min-w-0">
    <p class="text-gray-400 text-xs truncate">{{ title }}</p>
    <p class="text-lg font-bold text-white mt-0.5 truncate">{{ formatValue(value) }}</p>
  </div>
</template>
