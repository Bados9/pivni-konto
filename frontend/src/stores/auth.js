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
      const refreshToken = api.getRefreshToken()
      if (!refreshToken) {
        return
      }

      const refreshed = await api.refreshAccessToken()
      if (!refreshed) {
        return
      }
    } else {
      api.setToken(token)
    }

    try {
      loading.value = true
      user.value = await api.getMe()
    } catch {
      const refreshed = await api.refreshAccessToken()
      if (refreshed) {
        try {
          user.value = await api.getMe()
          return
        } catch {}
      }

      api.setToken(null)
      api.setRefreshToken(null)
      user.value = null
    } finally {
      loading.value = false
    }
  }

  async function login(email, password, rememberMe = false) {
    loading.value = true
    try {
      const response = await api.login(email, password)
      api.setToken(response.token)

      if (rememberMe && response.refreshToken) {
        api.setRefreshToken(response.refreshToken)
      } else {
        api.setRefreshToken(null)
      }

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
      return await login(email, password, false)
    } catch (error) {
      return { success: false, error: error.message }
    } finally {
      loading.value = false
    }
  }

  async function updateProfile(data) {
    try {
      user.value = await api.updateProfile(data)
      return { success: true }
    } catch (error) {
      return { success: false, error: error.message }
    }
  }

  function logout() {
    api.setToken(null)
    api.setRefreshToken(null)
    user.value = null
  }

  return {
    user,
    loading,
    isAuthenticated,
    init,
    login,
    register,
    updateProfile,
    logout
  }
})
