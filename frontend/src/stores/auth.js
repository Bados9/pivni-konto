import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)

  async function init() {
    const token = localStorage.getItem('token')
    if (!token) {
      return
    }

    api.setToken(token)

    try {
      loading.value = true
      user.value = await api.getMe()
    } catch (error) {
      api.setToken(null)
      user.value = null
    } finally {
      loading.value = false
    }
  }

  async function login(email, password) {
    loading.value = true
    try {
      const response = await api.login(email, password)
      api.setToken(response.token)
      user.value = await api.getMe()
      return { success: true }
    } catch (error) {
      return { success: false, error: error.message }
    } finally {
      loading.value = false
    }
  }

  async function register(name, email, password) {
    loading.value = true
    try {
      await api.register(name, email, password)
      return await login(email, password)
    } catch (error) {
      return { success: false, error: error.message }
    } finally {
      loading.value = false
    }
  }

  function logout() {
    api.setToken(null)
    user.value = null
  }

  return {
    user,
    loading,
    isAuthenticated,
    init,
    login,
    register,
    logout
  }
})
