import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { api } from '@/services/api'

vi.mock('@/services/api', () => ({
  api: {
    setToken: vi.fn(),
    setRefreshToken: vi.fn(),
    getRefreshToken: vi.fn().mockReturnValue(null),
    refreshAccessToken: vi.fn().mockResolvedValue(false),
    getMe: vi.fn(),
    login: vi.fn(),
    register: vi.fn()
  }
}))

describe('Auth Store', () => {
  let authStore

  beforeEach(() => {
    setActivePinia(createPinia())
    authStore = useAuthStore()
    vi.clearAllMocks()
  })

  describe('initial state', () => {
    it('has null user by default', () => {
      expect(authStore.user).toBeNull()
    })

    it('has loading false by default', () => {
      expect(authStore.loading).toBe(false)
    })

    it('isAuthenticated returns false when no user', () => {
      expect(authStore.isAuthenticated).toBe(false)
    })
  })

  describe('init', () => {
    it('does nothing when no token and no refresh token', async () => {
      localStorage.getItem.mockReturnValue(null)
      api.getRefreshToken.mockReturnValue(null)

      await authStore.init()

      expect(api.setToken).not.toHaveBeenCalled()
      expect(api.getMe).not.toHaveBeenCalled()
    })

    it('fetches user when token exists', async () => {
      localStorage.getItem.mockReturnValue('existing-token')
      api.getMe.mockResolvedValue({ id: '1', name: 'Test User' })

      await authStore.init()

      expect(api.setToken).toHaveBeenCalledWith('existing-token')
      expect(api.getMe).toHaveBeenCalled()
      expect(authStore.user).toEqual({ id: '1', name: 'Test User' })
    })

    it('clears token and refresh token on fetch error', async () => {
      localStorage.getItem.mockReturnValue('invalid-token')
      api.getMe.mockRejectedValue(new Error('Unauthorized'))
      api.refreshAccessToken.mockResolvedValue(false)

      await authStore.init()

      expect(api.setToken).toHaveBeenCalledWith(null)
      expect(api.setRefreshToken).toHaveBeenCalledWith(null)
      expect(authStore.user).toBeNull()
    })

    it('sets loading during fetch', async () => {
      localStorage.getItem.mockReturnValue('token')
      let resolvePromise
      api.getMe.mockReturnValue(new Promise(resolve => {
        resolvePromise = resolve
      }))

      const initPromise = authStore.init()
      expect(authStore.loading).toBe(true)

      resolvePromise({ id: '1' })
      await initPromise

      expect(authStore.loading).toBe(false)
    })
  })

  describe('login', () => {
    it('returns success and sets user on successful login', async () => {
      api.login.mockResolvedValue({ token: 'new-token' })
      api.getMe.mockResolvedValue({ id: '1', name: 'Logged User' })

      const result = await authStore.login('test@example.com', 'password')

      expect(result.success).toBe(true)
      expect(api.login).toHaveBeenCalledWith('test@example.com', 'password')
      expect(api.setToken).toHaveBeenCalledWith('new-token')
      expect(api.setRefreshToken).toHaveBeenCalledWith(null)
      expect(authStore.user).toEqual({ id: '1', name: 'Logged User' })
      expect(authStore.isAuthenticated).toBe(true)
    })

    it('stores refresh token when rememberMe is true', async () => {
      api.login.mockResolvedValue({ token: 'new-token', refreshToken: 'refresh-123' })
      api.getMe.mockResolvedValue({ id: '1', name: 'Logged User' })

      const result = await authStore.login('test@example.com', 'password', true)

      expect(result.success).toBe(true)
      expect(api.setRefreshToken).toHaveBeenCalledWith('refresh-123')
    })

    it('returns error on failed login', async () => {
      api.login.mockRejectedValue(new Error('Invalid credentials'))

      const result = await authStore.login('test@example.com', 'wrong')

      expect(result.success).toBe(false)
      expect(result.error).toBe('Invalid credentials')
      expect(authStore.user).toBeNull()
    })

    it('sets loading during login', async () => {
      let resolveLogin
      api.login.mockReturnValue(new Promise(resolve => {
        resolveLogin = resolve
      }))
      api.getMe.mockResolvedValue({ id: '1' })

      const loginPromise = authStore.login('test@example.com', 'password')
      expect(authStore.loading).toBe(true)

      resolveLogin({ token: 'token' })
      await loginPromise

      expect(authStore.loading).toBe(false)
    })
  })

  describe('register', () => {
    it('registers and logs in user on success', async () => {
      api.register.mockResolvedValue({ message: 'OK' })
      api.login.mockResolvedValue({ token: 'token' })
      api.getMe.mockResolvedValue({ id: '1', name: 'New User' })

      const result = await authStore.register('New User', 'new@example.com', 'password')

      expect(result.success).toBe(true)
      expect(api.register).toHaveBeenCalledWith('New User', 'new@example.com', 'password')
      expect(authStore.user).toEqual({ id: '1', name: 'New User' })
    })

    it('returns error on failed registration', async () => {
      api.register.mockRejectedValue(new Error('Email already exists'))

      const result = await authStore.register('User', 'existing@example.com', 'password')

      expect(result.success).toBe(false)
      expect(result.error).toBe('Email already exists')
    })
  })

  describe('logout', () => {
    it('clears token, refresh token and user', () => {
      authStore.user = { id: '1', name: 'Test' }

      authStore.logout()

      expect(api.setToken).toHaveBeenCalledWith(null)
      expect(api.setRefreshToken).toHaveBeenCalledWith(null)
      expect(authStore.user).toBeNull()
      expect(authStore.isAuthenticated).toBe(false)
    })
  })
})
