<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api } from '../services/api'

const router = useRouter()
const route = useRoute()

const password = ref('')
const passwordConfirm = ref('')
const error = ref('')
const success = ref(false)
const loading = ref(false)
const token = ref('')

onMounted(() => {
  token.value = route.query.token || ''
  if (!token.value) {
    error.value = 'Chybí token pro obnovení hesla'
  }
})

async function handleSubmit() {
  error.value = ''

  if (password.value.length < 6) {
    error.value = 'Heslo musí mít alespoň 6 znaků'
    return
  }

  if (password.value !== passwordConfirm.value) {
    error.value = 'Hesla se neshodují'
    return
  }

  loading.value = true

  try {
    await api.resetPassword(token.value, password.value)
    success.value = true
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <span class="text-6xl">🔐</span>
        <h1 class="text-2xl font-bold text-beer-500 mt-4">Nové heslo</h1>
        <p class="text-gray-400 mt-2">Zadejte nové heslo</p>
      </div>

      <div v-if="success" class="text-center">
        <div class="card mb-6">
          <p class="text-green-400 mb-2">Heslo změněno!</p>
          <p class="text-gray-400 text-sm">
            Vaše heslo bylo úspěšně změněno. Nyní se můžete přihlásit.
          </p>
        </div>
        <router-link to="/login" class="btn btn-primary inline-block">
          Přihlásit se
        </router-link>
      </div>

      <form v-if="!success && token" @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <input
            v-model="password"
            type="password"
            placeholder="Nové heslo"
            class="input"
            required
            minlength="6"
          />
        </div>

        <div>
          <input
            v-model="passwordConfirm"
            type="password"
            placeholder="Potvrzení hesla"
            class="input"
            required
            minlength="6"
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
          {{ loading ? 'Ukládání...' : 'Nastavit nové heslo' }}
        </button>
      </form>

      <div v-if="!token" class="text-center">
        <p class="text-red-500 mb-4">{{ error }}</p>
        <router-link to="/forgot-password" class="text-beer-500 hover:underline">
          Požádat o nový odkaz
        </router-link>
      </div>
    </div>
  </div>
</template>
