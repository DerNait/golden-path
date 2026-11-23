<script setup>
import { useRoute } from 'vue-router';
import BrandMark from '../components/common/BrandMark.vue';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const auth = useAuthStore();
const navigation = [
  { name: 'dashboard', icon: 'fa-house', label: 'Inicio' },
  { name: 'routine', icon: 'fa-list-check', label: 'Rutina' },
  { name: 'workout', icon: 'fa-dumbbell', label: 'Entrenar', primary: true },
  { name: 'progress', icon: 'fa-chart-line', label: 'Progreso' },
  { name: 'history', icon: 'fa-clock-rotate-left', label: 'Historial' },
];
const isActive = (item) => route.name === item.name || (item.name === 'history' && route.name === 'history-detail');
const initial = () => auth.user?.name?.slice(0, 1) || '?';
</script>
<template>
  <div class="app-shell">
    <aside class="desktop-sidebar">
      <div class="brand-lockup"><span class="brand-mark"><BrandMark /></span><div><strong>Golden Path</strong><small>Disciplina medible</small></div></div>
      <nav aria-label="Principal">
        <RouterLink v-for="item in navigation" :key="item.name" :to="{ name: item.name }" :class="{ active: isActive(item) }" :aria-current="isActive(item) ? 'page' : undefined"><i class="fa-solid" :class="item.icon" /><span>{{ item.label }}</span></RouterLink>
      </nav>
      <RouterLink class="profile-link" :to="{ name: 'profile' }"><span class="user-avatar">{{ initial() }}</span><span><strong>{{ auth.user?.name }}</strong><small>Mi perfil</small></span><i class="fa-solid fa-chevron-right" /></RouterLink>
    </aside>
    <div class="app-main">
      <header class="mobile-header">
        <RouterLink to="/" class="mobile-brand"><BrandMark /><span>Golden Path</span></RouterLink>
        <RouterLink class="user-avatar" title="Mi perfil" aria-label="Mi perfil" :to="{ name: 'profile' }">{{ initial() }}</RouterLink>
      </header>
      <main><RouterView /></main>
    </div>
    <nav class="mobile-bottom-nav" aria-label="Principal">
      <RouterLink v-for="item in navigation" :key="item.name" :to="{ name: item.name }" :class="{ active: isActive(item), primary: item.primary }" :aria-current="isActive(item) ? 'page' : undefined"><i class="fa-solid" :class="item.icon" /><span>{{ item.label }}</span></RouterLink>
    </nav>
  </div>
</template>
