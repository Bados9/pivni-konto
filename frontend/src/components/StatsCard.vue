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
  <div class="card">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-gray-400 text-sm">{{ title }}</p>
        <p class="text-3xl font-bold text-white mt-1">{{ formatValue(value) }}</p>
      </div>
      <span class="text-4xl">{{ icon }}</span>
    </div>
  </div>
</template>
