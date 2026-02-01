import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import BeerButton from '@/components/BeerButton.vue'

describe('BeerButton', () => {
  let wrapper

  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the beer emoji', () => {
    wrapper = mount(BeerButton)

    expect(wrapper.text()).toContain('🍺')
    expect(wrapper.text()).toContain('+ 1')
  })

  it('emits add event when clicked', async () => {
    wrapper = mount(BeerButton)

    await wrapper.find('button').trigger('click')

    expect(wrapper.emitted('add')).toHaveLength(1)
  })

  it('does not emit when disabled', async () => {
    wrapper = mount(BeerButton, {
      props: { disabled: true }
    })

    await wrapper.find('button').trigger('click')

    expect(wrapper.emitted('add')).toBeUndefined()
  })

  it('shows pressed state and resets after timeout', async () => {
    wrapper = mount(BeerButton)

    await wrapper.find('button').trigger('click')

    // Check that ripple effect appears
    expect(wrapper.find('.animate-ping').exists()).toBe(true)

    // Fast forward time
    vi.advanceTimersByTime(200)
    await wrapper.vm.$nextTick()

    // Ripple should be gone
    expect(wrapper.find('.animate-ping').exists()).toBe(false)
  })

  it('has correct disabled styling', () => {
    wrapper = mount(BeerButton, {
      props: { disabled: true }
    })

    const button = wrapper.find('button')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.classes()).toContain('disabled:opacity-50')
  })

  it('applies pressed class when clicked', async () => {
    wrapper = mount(BeerButton)

    await wrapper.find('button').trigger('click')

    expect(wrapper.find('button').classes()).toContain('scale-95')
  })
})
