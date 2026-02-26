import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import BeerButton from '@/components/BeerButton.vue'

describe('BeerButton', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the beer glass SVG and + 1 text', () => {
    const wrapper = mount(BeerButton)

    expect(wrapper.find('svg').exists()).toBe(true)
    expect(wrapper.text()).toContain('+ 1')
  })

  it('emits add event after animation completes', async () => {
    const wrapper = mount(BeerButton)

    await wrapper.find('button').trigger('click')

    // Should not emit immediately
    expect(wrapper.emitted('add')).toBeUndefined()

    // Advance through drain animation
    vi.advanceTimersByTime(1000)
    await wrapper.vm.$nextTick()

    // Still should not have emitted (refill in progress)
    expect(wrapper.emitted('add')).toBeUndefined()

    // Advance through refill animation
    vi.advanceTimersByTime(300)
    await wrapper.vm.$nextTick()

    // Now it should have emitted
    expect(wrapper.emitted('add')).toHaveLength(1)
  })

  it('does not emit when disabled', async () => {
    const wrapper = mount(BeerButton, {
      props: { disabled: true }
    })

    await wrapper.find('button').trigger('click')

    vi.advanceTimersByTime(1300)
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('add')).toBeUndefined()
  })

  it('prevents clicks during animation', async () => {
    const wrapper = mount(BeerButton)

    await wrapper.find('button').trigger('click')

    // Click again during drain
    await wrapper.find('button').trigger('click')

    vi.advanceTimersByTime(1300)
    await wrapper.vm.$nextTick()

    // Should only emit once
    expect(wrapper.emitted('add')).toHaveLength(1)
  })

  it('has correct disabled styling', () => {
    const wrapper = mount(BeerButton, {
      props: { disabled: true }
    })

    const button = wrapper.find('button')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.classes()).toContain('disabled:opacity-50')
  })

  it('cycles through animation states on click', async () => {
    const wrapper = mount(BeerButton)
    const svg = wrapper.find('svg')

    // Initial state
    expect(svg.classes()).toContain('idle')

    await wrapper.find('button').trigger('click')

    // Draining
    expect(svg.classes()).toContain('draining')

    vi.advanceTimersByTime(1000)
    await wrapper.vm.$nextTick()

    // Refilling
    expect(svg.classes()).toContain('refilling')

    vi.advanceTimersByTime(300)
    await wrapper.vm.$nextTick()

    // Back to idle
    expect(svg.classes()).toContain('idle')
  })

  it('shows ripple during animation', async () => {
    const wrapper = mount(BeerButton)

    await wrapper.find('button').trigger('click')

    expect(wrapper.find('.animate-ping').exists()).toBe(true)

    vi.advanceTimersByTime(1300)
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.animate-ping').exists()).toBe(false)
  })
})
