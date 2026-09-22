<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../services/api'
import { useNotificationsStore } from '../stores/notifications'

const notifications = useNotificationsStore()
const queue = ref([])
const dismissing = ref(false)

// a notification opened from the bell takes priority over the unread-announcement queue
const current = computed(() => notifications.viewed || queue.value[0] || null)

const typeIcons = {
  announcement: '📣',
  group_award: '🍻',
}
const currentIcon = computed(() => typeIcons[current.value?.type] || '🔔')

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

  if (notifications.viewed) {
    notifications.closeViewed()
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
      <div class="w-full max-w-sm bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl p-6">
        <p class="text-4xl mb-3 text-center">{{ currentIcon }}</p>
        <h2 class="text-lg font-bold text-white mb-3 text-center">{{ current.title }}</h2>
        <p class="text-sm text-gray-300 whitespace-pre-line leading-relaxed text-left mb-6 max-h-[50vh] overflow-y-auto">{{ current.message }}</p>
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
