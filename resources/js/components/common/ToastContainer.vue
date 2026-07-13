<script setup>
import { useNotificationStore } from '../../stores/notifications';
const store = useNotificationStore();
</script>
<template>
  <div class="toast-stack" aria-live="polite">
    <button v-for="item in store.items" :key="item.id" class="app-toast" :class="`is-${item.type}`" @click="store.remove(item.id)">
      <i :class="item.type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check'" />
      <span>{{ item.message }}</span>
    </button>
  </div>
</template>
