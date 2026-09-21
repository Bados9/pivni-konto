<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../services/api'
import { useNotificationsStore } from '../stores/notifications'

const notifications = useNotificationsStore()
const queue = ref([])
const dismissing = ref(false)

const current = computed(() => queue.value[0] || null)

onMounted(async () => {
  try {
    const { count } = await api.getUnreadNotificationsCount()
    if (count === 0) {
      return
    }
    const data = await api.getNotifications()
    queue.value = data.notifications.filter(n => n.type === 'announcement' && !n.read)
  } catch (error) {
    console.error('Failed to load announcements:', error)
  }
})

async function dismiss() {
  if (!current.value || dismissing.value) {
    return
  }
  dismissing.value = true
  const announcement = current.value
  try {
    await api.markNotificationRead(announcement.id)
  } catch (error) {
    console.error('Failed to mark announcement read:', error)
  } finally {
    queue.value = queue.value.slice(1)
    dismissing.value = false
    notifications.fetchUnreadCount()
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="current"
      class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60"
    >
      <div class="w-full max-w-sm bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl p-6 text-center">
        <p class="text-4xl mb-3">📣</p>
        <h2 class="text-lg font-bold text-white mb-2">{{ current.title }}</h2>
        <p class="text-sm text-gray-300 whitespace-pre-line mb-6">{{ current.message }}</p>
        <button
          class="btn btn-primary w-full"
          :disabled="dismissing"
          @click="dismiss"
        >
          Rozumím 🍺
        </button>
      </div>
    </div>
  </Teleport>
</template>
