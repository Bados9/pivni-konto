import { describe, it, expect, vi, beforeEach } from 'vitest'
import { api } from '@/services/api'

describe('ApiService', () => {
  beforeEach(() => {
    api.token = null
    localStorage.clear()
  })

  describe('setToken', () => {
    it('stores token in localStorage when set', () => {
      api.setToken('test-token')

      expect(api.token).toBe('test-token')
      expect(localStorage.setItem).toHaveBeenCalledWith('token', 'test-token')
    })

    it('removes token from localStorage when set to null', () => {
      api.setToken(null)

      expect(api.token).toBeNull()
      expect(localStorage.removeItem).toHaveBeenCalledWith('token')
    })
  })

  describe('request', () => {
    it('adds Authorization header when token is set', async () => {
      api.setToken('test-token')
      global.fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: 'test' })
      })

      await api.get('/test')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/test',
        expect.objectContaining({
          headers: expect.objectContaining({
            'Authorization': 'Bearer test-token'
          })
        })
      )
    })

    it('does not add Authorization header when no token', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: 'test' })
      })

      await api.get('/test')

      const callArgs = global.fetch.mock.calls[0]
      expect(callArgs[1].headers['Authorization']).toBeUndefined()
    })

    it('handles 401 response by clearing token and dispatching event', async () => {
      api.setToken('test-token')
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: () => Promise.resolve({ error: 'Unauthorized' })
      })

      await expect(api.get('/test')).rejects.toThrow('Unauthorized')
      expect(api.token).toBeNull()
      expect(global.dispatchEvent).toHaveBeenCalled()
    })

    it('throws error with message from response', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: () => Promise.resolve({ error: 'Custom error message' })
      })

      await expect(api.get('/test')).rejects.toThrow('Custom error message')
    })
  })

  describe('HTTP methods', () => {
    it('get() makes GET request', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ data: 'test' })
      })

      await api.get('/endpoint')

      expect(global.fetch).toHaveBeenCalledWith('/api/endpoint', expect.any(Object))
    })

    it('post() makes POST request with body', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: () => Promise.resolve({ success: true })
      })

      await api.post('/endpoint', { name: 'test' })

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/endpoint',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ name: 'test' })
        })
      )
    })

    it('delete() makes DELETE request', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ success: true })
      })

      await api.delete('/endpoint/123')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/endpoint/123',
        expect.objectContaining({
          method: 'DELETE'
        })
      )
    })
  })

  describe('API endpoints', () => {
    beforeEach(() => {
      global.fetch.mockResolvedValue({
        ok: true,
        status: 200,
        json: () => Promise.resolve({})
      })
    })

    it('login() calls correct endpoint', async () => {
      await api.login('test@example.com', 'password')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/auth/login',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ email: 'test@example.com', password: 'password' })
        })
      )
    })

    it('register() calls correct endpoint', async () => {
      await api.register('Test', 'test@example.com', 'password')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/auth/register',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ name: 'Test', email: 'test@example.com', password: 'password' })
        })
      )
    })

    it('getMe() calls correct endpoint', async () => {
      await api.getMe()

      expect(global.fetch).toHaveBeenCalledWith('/api/auth/me', expect.any(Object))
    })

    it('getMyGroups() calls correct endpoint', async () => {
      await api.getMyGroups()

      expect(global.fetch).toHaveBeenCalledWith('/api/groups/my', expect.any(Object))
    })

    it('createGroup() calls correct endpoint', async () => {
      await api.createGroup('New Group')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/groups/create',
        expect.objectContaining({
          body: JSON.stringify({ name: 'New Group' })
        })
      )
    })

    it('joinGroup() calls correct endpoint', async () => {
      await api.joinGroup('ABC123')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/groups/join',
        expect.objectContaining({
          body: JSON.stringify({ code: 'ABC123' })
        })
      )
    })

    it('quickAdd() calls correct endpoint', async () => {
      await api.quickAdd({ volumeMl: 500, groupId: '123' })

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/entries/quick-add',
        expect.objectContaining({
          body: JSON.stringify({ volumeMl: 500, groupId: '123' })
        })
      )
    })

    it('deleteEntry() calls correct endpoint', async () => {
      await api.deleteEntry('entry-id')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/entries/entry-id',
        expect.objectContaining({ method: 'DELETE' })
      )
    })

    it('getMyStats() calls correct endpoint', async () => {
      await api.getMyStats()

      expect(global.fetch).toHaveBeenCalledWith('/api/stats/me', expect.any(Object))
    })

    it('getLeaderboard() calls correct endpoint with period', async () => {
      await api.getLeaderboard('group-id', 'month')

      expect(global.fetch).toHaveBeenCalledWith(
        '/api/stats/leaderboard/group-id?period=month',
        expect.any(Object)
      )
    })

    it('getMyAchievements() calls correct endpoint', async () => {
      await api.getMyAchievements()

      expect(global.fetch).toHaveBeenCalledWith('/api/achievements/me', expect.any(Object))
    })
  })
})
