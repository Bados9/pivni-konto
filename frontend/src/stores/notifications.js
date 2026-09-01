import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../services/api'

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)

  async function fetchUnreadCount() {
    try {
      const data = await api.getUnreadNotificationsCount()
      unreadCount.value = data.count
    } catch (error) {
      console.error('Failed to fetch unread notifications count:', error)
    }
  }

  async function fetchNotifications() {
    loading.value = true
    try {
      const data = await api.getNotifications()
      items.value = data.notifications
      unreadCount.value = data.unreadCount
    } catch (error) {
      console.error('Failed to fetch notifications:', error)
    } finally {
      loading.value = false
    }
  }

  async function markAllRead() {
    if (unreadCount.value === 0) {
      return
    }
    try {
      await api.markAllNotificationsRead()
      unreadCount.value = 0
    } catch (error) {
      console.error('Failed to mark notifications read:', error)
    }
  }

  return {
    items,
    unreadCount,
    loading,
    fetchUnreadCount,
    fetchNotifications,
    markAllRead
  }
})
