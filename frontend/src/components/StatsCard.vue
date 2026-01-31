<script setup>
defineProps({
  title: String,
  value: [String, Number],
  icon: String
})
</script>

<template>
  <div class="card">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-gray-400 text-sm">{{ title }}</p>
        <p class="text-3xl font-bold text-white mt-1">{{ value }}</p>
      </div>
      <span class="text-4xl">{{ icon }}</span>
    </div>
  </div>
</template>
