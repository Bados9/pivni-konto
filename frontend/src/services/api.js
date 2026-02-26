const API_URL = import.meta.env.VITE_API_URL || '/api'

class ApiService {
  constructor() {
    this.token = localStorage.getItem('token')
    this._refreshPromise = null
  }

  setToken(token) {
    this.token = token
    if (token) {
      localStorage.setItem('token', token)
    } else {
      localStorage.removeItem('token')
    }
  }

  setRefreshToken(token) {
    if (token) {
      localStorage.setItem('refreshToken', token)
    } else {
      localStorage.removeItem('refreshToken')
    }
  }

  getRefreshToken() {
    return localStorage.getItem('refreshToken')
  }

  async refreshAccessToken() {
    const refreshToken = this.getRefreshToken()
    if (!refreshToken) {
      return false
    }

    if (this._refreshPromise) {
      return this._refreshPromise
    }

    this._refreshPromise = (async () => {
      try {
        const url = `${API_URL}/token/refresh`
        const response = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ refreshToken })
        })

        if (!response.ok) {
          this.setRefreshToken(null)
          return false
        }

        const data = await response.json()
        this.setToken(data.token)
        if (data.refreshToken) {
          this.setRefreshToken(data.refreshToken)
        }
        return true
      } catch {
        this.setRefreshToken(null)
        return false
      } finally {
        this._refreshPromise = null
      }
    })()

    return this._refreshPromise
  }

  async request(endpoint, options = {}) {
    const url = `${API_URL}${endpoint}`
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    }

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`
    }

    let response = await fetch(url, {
      ...options,
      headers
    })

    if (response.status === 401 && this.getRefreshToken()) {
      const refreshed = await this.refreshAccessToken()
      if (refreshed) {
        headers['Authorization'] = `Bearer ${this.token}`
        response = await fetch(url, { ...options, headers })
      }
    }

    if (response.status === 401) {
      this.setToken(null)
      this.setRefreshToken(null)
      window.dispatchEvent(new CustomEvent('auth:unauthorized'))
      throw new Error('Unauthorized')
    }

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || data.message || 'Něco se pokazilo')
    }

    return data
  }

  get(endpoint) {
    return this.request(endpoint)
  }

  post(endpoint, data) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(data)
    })
  }

  patch(endpoint, data) {
    return this.request(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data)
    })
  }

  delete(endpoint) {
    return this.request(endpoint, {
      method: 'DELETE'
    })
  }

  // Auth
  login(email, password) {
    return this.post('/auth/login', { email, password })
  }

  register(name, email, password) {
    return this.post('/auth/register', { name, email, password })
  }

  getMe() {
    return this.get('/auth/me')
  }

  updateProfile(data) {
    return this.patch('/auth/profile', data)
  }

  forgotPassword(email) {
    return this.post('/auth/forgot-password', { email })
  }

  resetPassword(token, password) {
    return this.post('/auth/reset-password', { token, password })
  }

  // Groups
  getMyGroups() {
    return this.get('/groups/my')
  }

  createGroup(name) {
    return this.post('/groups/create', { name })
  }

  joinGroup(code) {
    return this.post('/groups/join', { code })
  }

  // Entries
  quickAdd(options = {}) {
    return this.post('/entries/quick-add', options)
  }

  deleteEntry(id) {
    return this.delete(`/entries/${id}`)
  }

  // Stats
  getMyStats() {
    return this.get('/stats/me')
  }

  getUserStats(userId) {
    return this.get(`/stats/user/${userId}`)
  }

  getLeaderboard(groupId, period = 'week') {
    return this.get(`/stats/leaderboard/${groupId}?period=${period}`)
  }

  // Beers
  getBeers() {
    return this.get('/beers')
  }

  searchBeers(query) {
    return this.get(`/beers?name=${encodeURIComponent(query)}`)
  }

  // Achievements
  getMyAchievements() {
    return this.get('/achievements/me')
  }

  getAchievementsSummary() {
    return this.get('/achievements/summary')
  }
}

export const api = new ApiService()
