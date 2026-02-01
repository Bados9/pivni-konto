import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AchievementToast from '@/components/AchievementToast.vue'

describe('AchievementToast', () => {
  let wrapper

  beforeEach(() => {
    vi.useFakeTimers()
    // Mock Teleport by using a stub
    const teleportTarget = document.createElement('div')
    teleportTarget.id = 'teleport-target'
    document.body.appendChild(teleportTarget)
  })

  afterEach(() => {
    vi.useRealTimers()
    wrapper?.unmount()
    document.body.innerHTML = ''
  })

  it('renders nothing when no achievements', () => {
    wrapper = mount(AchievementToast, {
      props: { achievements: [] },
      global: {
        stubs: { Teleport: true }
      }
    })

    expect(wrapper.find('.fixed').exists()).toBe(false)
  })

  it('displays achievement when passed', async () => {
    wrapper = mount(AchievementToast, {
      props: {
        achievements: [
          { id: 'first_beer', name: 'První doušek', icon: '🍼' }
        ]
      },
      global: {
        stubs: { Teleport: true }
      }
    })

    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('První doušek')
    expect(wrapper.text()).toContain('🍼')
  })

  it('queues multiple achievements', async () => {
    wrapper = mount(AchievementToast, {
      props: {
        achievements: [
          { id: 'first_beer', name: 'První', icon: '🍼' },
          { id: 'volume_10l', name: 'Druhý', icon: '🪣' }
        ]
      },
      global: {
        stubs: { Teleport: true }
      }
    })

    await wrapper.vm.$nextTick()

    // First achievement should be shown
    expect(wrapper.text()).toContain('První')

    // Wait for first to finish (4000ms show + 300ms hide)
    vi.advanceTimersByTime(4300)
    await wrapper.vm.$nextTick()

    // Second achievement should now be shown
    expect(wrapper.text()).toContain('Druhý')
  })

  it('emits clear after all achievements shown', async () => {
    wrapper = mount(AchievementToast, {
      props: {
        achievements: [
          { id: 'first_beer', name: 'První', icon: '🍼' }
        ]
      },
      global: {
        stubs: { Teleport: true }
      }
    })

    await wrapper.vm.$nextTick()

    // Wait for achievement to finish displaying
    vi.advanceTimersByTime(4300)
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('clear')).toHaveLength(1)
  })

})
