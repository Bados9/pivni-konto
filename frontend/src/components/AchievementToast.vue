<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  achievements: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['clear'])

const queue = ref([])
const current = ref(null)
const visible = ref(false)

watch(() => props.achievements, (newAchievements) => {
  if (!newAchievements || newAchievements.length === 0) return

  queue.value = [...queue.value, ...newAchievements]
  showNext()
}, { immediate: true })

function showNext() {
  if (current.value !== null) return
  if (queue.value.length === 0) {
    emit('clear')
    return
  }

  current.value = queue.value.shift()
  visible.value = true

  setTimeout(() => {
    visible.value = false
    setTimeout(() => {
      current.value = null
      showNext()
    }, 300)
  }, 4000)
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-300 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-4"
    >
      <div
        v-if="visible && current"
        class="fixed top-4 left-1/2 -translate-x-1/2 z-50"
      >
        <div class="bg-gradient-to-r from-beer-600 to-beer-500 rounded-2xl shadow-2xl px-6 py-4 flex items-center gap-4 border border-beer-400/30">
          <div class="text-4xl animate-bounce">
            {{ current.icon }}
          </div>
          <div>
            <p class="text-xs text-beer-200 uppercase tracking-wider font-medium">
              {{ current.timesUnlocked > 1 ? `Achievement ${current.timesUnlocked}×!` : 'Novy achievement!' }}
            </p>
            <p class="text-lg font-bold text-white flex items-center gap-2">
              {{ current.name }}
              <span v-if="current.timesUnlocked > 1" class="text-sm bg-white/20 px-2 py-0.5 rounded-full">
                {{ current.timesUnlocked }}×
              </span>
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
