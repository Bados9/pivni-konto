<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useNotificationsStore } from '../stores/notifications'

const notifications = useNotificationsStore()
const open = ref(false)

async function toggle() {
  open.value = !open.value
  if (!open.value) {
    return
  }
  await notifications.fetchNotifications()
  await notifications.markAllRead()
}

function close() {
  open.value = false
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString('cs-CZ', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric'
  })
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible' && !open.value) {
    notifications.fetchUnreadCount()
  }
}

onMounted(() => {
  notifications.fetchUnreadCount()
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <button
    class="fixed top-4 right-4 z-40 bg-gray-800 rounded-full p-2 shadow-lg text-gray-300 hover:text-beer-400 transition-colors"
    aria-label="Notifikace"
    @click="toggle"
  >
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
    </svg>
    <span
      v-if="notifications.unreadCount > 0"
      class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
    >
      {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
    </span>
  </button>

  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-40" @click="close"></div>
    <div
      v-if="open"
      class="fixed top-16 right-4 z-50 w-80 max-w-[calc(100vw-2rem)] bg-gray-800 border border-gray-700 rounded-xl shadow-xl overflow-hidden"
    >
      <div class="px-4 py-3 border-b border-gray-700 font-semibold text-white text-sm">
        Notifikace
      </div>

      <div class="max-h-96 overflow-y-auto">
        <div v-if="notifications.loading" class="p-4 text-center text-gray-400 text-sm">
          Načítám...
        </div>

        <div
          v-else-if="notifications.items.length === 0"
          class="p-6 text-center text-gray-400 text-sm"
        >
          Zatím žádné notifikace 🍺
        </div>

        <div
          v-for="notification in notifications.items"
          :key="notification.id"
          class="px-4 py-3 border-b border-gray-700 last:border-0"
          :class="{ 'bg-gray-700/40': !notification.read }"
        >
          <div class="flex items-start justify-between gap-2">
            <p class="text-sm font-medium text-white">{{ notification.title }}</p>
            <span
              v-if="!notification.read"
              class="mt-1 w-2 h-2 rounded-full bg-beer-500 shrink-0"
            ></span>
          </div>
          <p class="text-xs text-gray-400 mt-0.5">{{ notification.message }}</p>
          <p class="text-xs text-gray-500 mt-1">{{ formatDate(notification.createdAt) }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>
