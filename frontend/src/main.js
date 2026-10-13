import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { registerSW } from 'virtual:pwa-register'
import App from './App.vue'
import router from './router'
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

// PWA na iOS se při návratu z pozadí znovu nenačítá, takže by novou verzi
// nikdy nehledala. Kontrolujeme ji při každém zobrazení aplikace a jednou
// za hodinu; po aktivaci nového service workeru registerSW stránku obnoví.
const UPDATE_INTERVAL_MS = 60 * 60 * 1000

registerSW({
  immediate: true,
  onRegisteredSW(swUrl, registration) {
    if (!registration) {
      return
    }
    const checkForUpdate = () => registration.update().catch(() => {})
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        checkForUpdate()
      }
    })
    setInterval(checkForUpdate, UPDATE_INTERVAL_MS)
  },
})
