import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../services/api'

export const useGroupsStore = defineStore('groups', () => {
  const groups = ref([])
  const activeGroupId = ref(localStorage.getItem('activeGroupId'))
  const loading = ref(false)

  const activeGroup = computed(() => {
    if (!activeGroupId.value) {
      return groups.value[0] || null
    }
    return groups.value.find(g => g.id === activeGroupId.value) || groups.value[0] || null
  })

  function setActiveGroup(groupId) {
    activeGroupId.value = groupId
    localStorage.setItem('activeGroupId', groupId)
  }

  async function fetchGroups() {
    loading.value = true
    try {
      groups.value = await api.getMyGroups()
      if (!activeGroupId.value && groups.value.length > 0) {
        setActiveGroup(groups.value[0].id)
      }
    } catch (error) {
      console.error('Failed to fetch groups:', error)
    } finally {
      loading.value = false
    }
  }

  async function createGroup(name) {
    try {
      const group = await api.createGroup(name)
      groups.value.push(group)
      setActiveGroup(group.id)
      return { success: true, group }
    } catch (error) {
      return { success: false, error: error.message }
    }
  }

  async function joinGroup(code) {
    try {
      const result = await api.joinGroup(code)
      await fetchGroups()
      setActiveGroup(result.group.id)
      return { success: true, group: result.group }
    } catch (error) {
      return { success: false, error: error.message }
    }
  }

  return {
    groups,
    activeGroup,
    activeGroupId,
    loading,
    setActiveGroup,
    fetchGroups,
    createGroup,
    joinGroup
  }
})
