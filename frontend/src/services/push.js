import { api } from './api'

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = atob(base64)
  return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)))
}

export const pushService = {
  isSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
  },

  async isSubscribed() {
    if (!this.isSupported()) {
      return false
    }
    const registration = await navigator.serviceWorker.ready
    const subscription = await registration.pushManager.getSubscription()
    return subscription !== null
  },

  async subscribe() {
    if (!this.isSupported()) {
      return false
    }

    const permission = await Notification.requestPermission()
    if (permission !== 'granted') {
      return false
    }

    const registration = await navigator.serviceWorker.ready
    const { publicKey } = await api.get('/push/vapid-key')

    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicKey),
    })

    const json = subscription.toJSON()
    await api.post('/push/subscribe', {
      endpoint: json.endpoint,
      keys: { p256dh: json.keys.p256dh, auth: json.keys.auth },
    })

    return true
  },

  async unsubscribe() {
    const registration = await navigator.serviceWorker.ready
    const subscription = await registration.pushManager.getSubscription()
    if (!subscription) {
      return
    }

    const json = subscription.toJSON()
    await subscription.unsubscribe()
    await api.post('/push/unsubscribe', { endpoint: json.endpoint })
  },

  async sendTest() {
    return api.post('/push/test', {})
  },
}
