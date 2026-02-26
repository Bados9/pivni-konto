<script setup>
import { ref } from 'vue'
import { api } from '../services/api'

const email = ref('')
const error = ref('')
const success = ref(false)
const loading = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true

  try {
    await api.forgotPassword(email.value)
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
        <span class="text-6xl">🔑</span>
        <h1 class="text-2xl font-bold text-beer-500 mt-4">Zapomenuté heslo</h1>
        <p class="text-gray-400 mt-2">Zadejte email pro obnovení hesla</p>
      </div>

      <div v-if="success" class="text-center">
        <div class="card mb-6">
          <p class="text-green-400 mb-2">Email odeslán!</p>
          <p class="text-gray-400 text-sm">
            Pokud účet s tímto emailem existuje, odeslali jsme vám odkaz pro obnovení hesla.
            Zkontrolujte svou schránku.
          </p>
        </div>
        <router-link to="/login" class="text-beer-500 hover:underline">
          Zpět na přihlášení
        </router-link>
      </div>

      <form v-if="!success" @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <input
            v-model="email"
            type="email"
            placeholder="Email"
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
          {{ loading ? 'Odesílání...' : 'Odeslat odkaz' }}
        </button>
      </form>

      <p v-if="!success" class="text-center text-gray-400 mt-6">
        <router-link to="/login" class="text-beer-500 hover:underline">
          Zpět na přihlášení
        </router-link>
      </p>
    </div>
  </div>
</template>
