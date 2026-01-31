<script setup>
import { ref } from 'vue'

const emit = defineEmits(['add'])
const props = defineProps({
  disabled: Boolean
})

const isPressed = ref(false)

function handleClick() {
  if (props.disabled) {
    return
  }
  isPressed.value = true
  emit('add')
  setTimeout(() => {
    isPressed.value = false
  }, 200)
}
</script>

<template>
  <button
    @click="handleClick"
    :disabled="disabled"
    class="relative w-48 h-48 rounded-full bg-gradient-to-br from-beer-400 to-beer-600 shadow-2xl transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
    :class="{ 'scale-95': isPressed }"
  >
    <div class="absolute inset-2 rounded-full bg-gradient-to-br from-beer-300 to-beer-500 flex items-center justify-center">
      <div class="text-center">
        <span class="text-6xl">🍺</span>
        <span class="block text-white text-xl font-bold mt-2">+ 1</span>
      </div>
    </div>

    <!-- Ripple effect -->
    <div
      v-if="isPressed"
      class="absolute inset-0 rounded-full bg-white/20 animate-ping"
    ></div>
  </button>
</template>
