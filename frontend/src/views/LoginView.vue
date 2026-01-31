<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true

  const result = await auth.login(email.value, password.value)

  loading.value = false

  if (result.success) {
    router.push('/')
    return
  }

  error.value = result.error
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <span class="text-6xl">🍺</span>
        <h1 class="text-2xl font-bold text-beer-500 mt-4">Pivní Konto</h1>
        <p class="text-gray-400 mt-2">Přihlaste se ke svému účtu</p>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <input
            v-model="email"
            type="email"
            placeholder="Email"
            class="input"
            required
          />
        </div>

        <div>
          <input
            v-model="password"
            type="password"
            placeholder="Heslo"
            class="input"
            required
          />
        </div>

        <div v-if="error" class="text-red-500 text-sm text-center">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="btn btn-primary w-full py-3"
        >
          {{ loading ? 'Přihlašování...' : 'Přihlásit se' }}
        </button>
      </form>

      <p class="text-center text-gray-400 mt-6">
        Nemáte účet?
        <router-link to="/register" class="text-beer-500 hover:underline">
          Zaregistrujte se
        </router-link>
      </p>
    </div>
  </div>
</template>
