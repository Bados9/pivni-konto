<script setup>
import { ref, computed } from 'vue'

const emit = defineEmits(['add'])
const props = defineProps({
  disabled: Boolean
})

const animState = ref('idle')
const isAnimating = computed(() => animState.value !== 'idle')

function handleClick() {
  if (props.disabled || isAnimating.value) return

  animState.value = 'draining'

  setTimeout(() => {
    animState.value = 'refilling'
    setTimeout(() => {
      animState.value = 'idle'
      emit('add')
    }, 300)
  }, 1000)
}
</script>

<template>
  <button
    @click="handleClick"
    :disabled="disabled"
    class="relative w-48 h-48 rounded-full bg-gradient-to-br from-beer-400 to-beer-600 shadow-2xl transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
  >
    <div class="absolute inset-2 rounded-full bg-gradient-to-br from-beer-300 to-beer-500 flex items-center justify-center">
      <div class="text-center">
        <!-- Beer Glass SVG -->
        <svg
          viewBox="0 0 58 72"
          class="w-20 h-24 mx-auto"
          :class="animState"
          aria-hidden="true"
        >
          <defs>
            <linearGradient id="bb-beer" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#fbbf24" />
              <stop offset="100%" stop-color="#b45309" />
            </linearGradient>
            <clipPath id="bb-glass">
              <path d="M12 14 L10 62 Q10 68 16 68 L38 68 Q44 68 44 62 L42 14 Z" />
            </clipPath>
          </defs>

          <!-- Beer liquid -->
          <g clip-path="url(#bb-glass)">
            <rect class="beer-level" x="8" y="14" width="38" height="56" fill="url(#bb-beer)" />
            <!-- Bubbles -->
            <circle class="bubble b1" cx="22" cy="52" r="1.5" fill="#fef3c7" opacity="0.6" />
            <circle class="bubble b2" cx="32" cy="58" r="1" fill="#fef3c7" opacity="0.5" />
            <circle class="bubble b3" cx="27" cy="45" r="1.2" fill="#fef3c7" opacity="0.55" />
          </g>

          <!-- Foam -->
          <g class="foam">
            <ellipse cx="17" cy="18" rx="7" ry="5" fill="#fefce8" />
            <ellipse cx="27" cy="16" rx="8" ry="5.5" fill="#fffbeb" />
            <ellipse cx="37" cy="18" rx="7" ry="5" fill="#fefce8" />
            <ellipse cx="22" cy="15" rx="5" ry="3.5" fill="white" opacity="0.7" />
            <ellipse cx="32" cy="15" rx="5" ry="3.5" fill="white" opacity="0.7" />
          </g>

          <!-- Glass outline -->
          <path
            d="M12 12 L10 62 Q10 68 16 68 L38 68 Q44 68 44 62 L42 12"
            fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round"
          />
          <line x1="12" y1="12" x2="42" y2="12" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round" />

          <!-- Handle -->
          <path
            d="M44 24 C52 24, 54 33, 54 40 C54 47, 52 54, 44 54"
            fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round"
          />
        </svg>
        <span class="block text-white text-xl font-bold mt-1">+ 1</span>
      </div>
    </div>

    <!-- Ripple effect during animation -->
    <div
      v-if="isAnimating"
      class="absolute inset-0 rounded-full bg-white/10 animate-ping"
    ></div>
  </button>
</template>

<style scoped>
.beer-level {
  transform: translateY(0);
}

/* Draining state */
.draining .beer-level {
  animation: drain 1s ease-in forwards;
}

.draining .foam {
  animation: foam-out 0.6s ease-in forwards;
}

.draining .bubble {
  animation: bubble-rise 0.8s ease-out forwards;
}

.draining .b2 {
  animation-delay: 0.15s;
}

.draining .b3 {
  animation-delay: 0.3s;
}

/* Refilling state */
.refilling .beer-level {
  animation: refill 0.3s ease-out forwards;
}

.refilling .foam {
  animation: foam-in 0.3s ease-out forwards;
}

@keyframes drain {
  0% { transform: translateY(0); }
  100% { transform: translateY(56px); }
}

@keyframes refill {
  0% { transform: translateY(56px); }
  100% { transform: translateY(0); }
}

@keyframes foam-out {
  0% { opacity: 1; transform: translateY(0); }
  100% { opacity: 0; transform: translateY(8px); }
}

@keyframes foam-in {
  0% { opacity: 0; }
  100% { opacity: 1; }
}

@keyframes bubble-rise {
  0% {
    transform: translateY(0);
    opacity: 0.6;
  }
  100% {
    transform: translateY(-35px);
    opacity: 0;
  }
}
</style>
