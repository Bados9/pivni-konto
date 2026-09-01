<script setup>
import { RouterView } from 'vue-router'
import NavBar from './components/NavBar.vue'
import NotificationBell from './components/NotificationBell.vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
auth.init()
</script>

<template>
  <div class="min-h-screen bg-gray-900 pb-20">
    <RouterView />
    <NotificationBell v-if="auth.isAuthenticated" />
    <NavBar v-if="auth.isAuthenticated" />
  </div>
</template>
