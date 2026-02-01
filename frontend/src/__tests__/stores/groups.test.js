import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useGroupsStore } from '@/stores/groups'
import { api } from '@/services/api'

vi.mock('@/services/api', () => ({
  api: {
    getMyGroups: vi.fn(),
    createGroup: vi.fn(),
    joinGroup: vi.fn()
  }
}))

describe('Groups Store', () => {
  let groupsStore

  beforeEach(() => {
    setActivePinia(createPinia())
    groupsStore = useGroupsStore()
    vi.clearAllMocks()
    localStorage.clear()
  })

  describe('initial state', () => {
    it('has empty groups array', () => {
      expect(groupsStore.groups).toEqual([])
    })

    it('has loading false', () => {
      expect(groupsStore.loading).toBe(false)
    })

    it('loads activeGroupId from localStorage', () => {
      localStorage.getItem.mockReturnValue('stored-group-id')
      setActivePinia(createPinia())
      const store = useGroupsStore()

      expect(store.activeGroupId).toBe('stored-group-id')
    })
  })

  describe('activeGroup computed', () => {
    it('returns null when no groups', () => {
      expect(groupsStore.activeGroup).toBeNull()
    })

    it('returns first group when no activeGroupId', () => {
      groupsStore.groups = [
        { id: '1', name: 'Group 1' },
        { id: '2', name: 'Group 2' }
      ]
      groupsStore.activeGroupId = null

      expect(groupsStore.activeGroup).toEqual({ id: '1', name: 'Group 1' })
    })

    it('returns group matching activeGroupId', () => {
      groupsStore.groups = [
        { id: '1', name: 'Group 1' },
        { id: '2', name: 'Group 2' }
      ]
      groupsStore.activeGroupId = '2'

      expect(groupsStore.activeGroup).toEqual({ id: '2', name: 'Group 2' })
    })

    it('falls back to first group when activeGroupId not found', () => {
      groupsStore.groups = [
        { id: '1', name: 'Group 1' }
      ]
      groupsStore.activeGroupId = 'non-existent'

      expect(groupsStore.activeGroup).toEqual({ id: '1', name: 'Group 1' })
    })
  })

  describe('setActiveGroup', () => {
    it('sets activeGroupId and stores in localStorage', () => {
      groupsStore.setActiveGroup('new-group-id')

      expect(groupsStore.activeGroupId).toBe('new-group-id')
      expect(localStorage.setItem).toHaveBeenCalledWith('activeGroupId', 'new-group-id')
    })
  })

  describe('fetchGroups', () => {
    it('fetches groups from API', async () => {
      const mockGroups = [
        { id: '1', name: 'Group 1' },
        { id: '2', name: 'Group 2' }
      ]
      api.getMyGroups.mockResolvedValue(mockGroups)

      await groupsStore.fetchGroups()

      expect(api.getMyGroups).toHaveBeenCalled()
      expect(groupsStore.groups).toEqual(mockGroups)
    })

    it('sets loading during fetch', async () => {
      let resolvePromise
      api.getMyGroups.mockReturnValue(new Promise(resolve => {
        resolvePromise = resolve
      }))

      const fetchPromise = groupsStore.fetchGroups()
      expect(groupsStore.loading).toBe(true)

      resolvePromise([])
      await fetchPromise

      expect(groupsStore.loading).toBe(false)
    })

    it('sets activeGroup to first group when none selected', async () => {
      api.getMyGroups.mockResolvedValue([
        { id: 'first', name: 'First Group' }
      ])
      groupsStore.activeGroupId = null

      await groupsStore.fetchGroups()

      expect(groupsStore.activeGroupId).toBe('first')
      expect(localStorage.setItem).toHaveBeenCalledWith('activeGroupId', 'first')
    })

    it('keeps existing activeGroupId when already set', async () => {
      api.getMyGroups.mockResolvedValue([
        { id: 'first', name: 'First Group' }
      ])
      groupsStore.activeGroupId = 'existing'

      await groupsStore.fetchGroups()

      expect(groupsStore.activeGroupId).toBe('existing')
    })

    it('handles fetch error gracefully', async () => {
      api.getMyGroups.mockRejectedValue(new Error('Network error'))

      await groupsStore.fetchGroups()

      expect(groupsStore.groups).toEqual([])
      expect(groupsStore.loading).toBe(false)
    })
  })

  describe('createGroup', () => {
    it('creates group and adds to list', async () => {
      const newGroup = { id: 'new', name: 'New Group', inviteCode: 'ABC' }
      api.createGroup.mockResolvedValue(newGroup)

      const result = await groupsStore.createGroup('New Group')

      expect(result.success).toBe(true)
      expect(result.group).toEqual(newGroup)
      expect(groupsStore.groups).toContainEqual(newGroup)
      expect(groupsStore.activeGroupId).toBe('new')
    })

    it('returns error on failure', async () => {
      api.createGroup.mockRejectedValue(new Error('Creation failed'))

      const result = await groupsStore.createGroup('New Group')

      expect(result.success).toBe(false)
      expect(result.error).toBe('Creation failed')
    })
  })

  describe('joinGroup', () => {
    it('joins group and refreshes list', async () => {
      api.joinGroup.mockResolvedValue({
        message: 'Joined',
        group: { id: 'joined', name: 'Joined Group' }
      })
      api.getMyGroups.mockResolvedValue([
        { id: 'joined', name: 'Joined Group' }
      ])

      const result = await groupsStore.joinGroup('INVITE123')

      expect(result.success).toBe(true)
      expect(result.group).toEqual({ id: 'joined', name: 'Joined Group' })
      expect(api.getMyGroups).toHaveBeenCalled()
      expect(groupsStore.activeGroupId).toBe('joined')
    })

    it('returns error on failure', async () => {
      api.joinGroup.mockRejectedValue(new Error('Invalid code'))

      const result = await groupsStore.joinGroup('BADCODE')

      expect(result.success).toBe(false)
      expect(result.error).toBe('Invalid code')
    })
  })
})
