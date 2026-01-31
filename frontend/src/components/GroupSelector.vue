<script setup>
import { useGroupsStore } from '../stores/groups'

const groups = useGroupsStore()
</script>

<template>
  <div class="relative">
    <select
      :value="groups.activeGroupId"
      @change="groups.setActiveGroup($event.target.value)"
      class="input appearance-none cursor-pointer pr-10"
    >
      <option v-for="group in groups.groups" :key="group.id" :value="group.id">
        {{ group.name }}
      </option>
    </select>
    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
      <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </div>
  </div>
</template>
