import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/',
    name: 'home',
    component: () => import('../views/HomeView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { guest: true }
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('../views/RegisterView.vue'),
    meta: { guest: true }
  },
  {
    path: '/stats',
    name: 'stats',
    component: () => import('../views/StatsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/stats/:userId',
    name: 'user-stats',
    component: () => import('../views/UserStatsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/groups',
    name: 'groups',
    component: () => import('../views/GroupsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/profile',
    name: 'profile',
    component: () => import('../views/ProfileView.vue'),
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

let authInitialized = false

// Listen for auth:unauthorized event from API service
window.addEventListener('auth:unauthorized', () => {
  const auth = useAuthStore()
  auth.logout()
  router.push({ name: 'login' })
})

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()

  // Initialize auth on first navigation
  if (!authInitialized) {
    authInitialized = true
    await auth.init()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    next({ name: 'login' })
    return
  }

  if (to.meta.guest && auth.isAuthenticated) {
    next({ name: 'home' })
    return
  }

  next()
})

export default router
