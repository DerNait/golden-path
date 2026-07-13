<script setup>
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
const route=useRoute(); const router=useRouter(); const auth=useAuthStore();
const navigation=[['dashboard','fa-house','Inicio'],['routine','fa-list-check','Rutina'],['workout','fa-dumbbell','Entrenar'],['progress','fa-chart-line','Progreso'],['history','fa-clock-rotate-left','Historial']];
async function logout(){ await auth.logout(); router.push({name:'login'}); }
</script>
<template>
  <div class="app-shell">
    <aside class="desktop-sidebar">
      <div class="brand-lockup"><span class="brand-mark"><i class="fa-solid fa-bolt" /></span><div><strong>Golden Path</strong><small>Disciplina medible</small></div></div>
      <nav><RouterLink v-for="item in navigation" :key="item[0]" :to="{name:item[0]}" :class="{active:route.name===item[0] || (item[0]==='history'&&route.name==='history-detail')}"><i class="fa-solid" :class="item[1]"/><span>{{ item[2] }}</span></RouterLink></nav>
      <RouterLink class="profile-link" :to="{name:'profile'}"><span class="user-avatar">{{ auth.user?.name?.slice(0,1) }}</span><span><strong>{{ auth.user?.name }}</strong><small>Mi perfil</small></span><i class="fa-solid fa-chevron-right"/></RouterLink>
    </aside>
    <div class="app-main">
      <header class="mobile-header"><RouterLink to="/" class="mobile-brand"><i class="fa-solid fa-bolt"/><strong>Golden Path</strong></RouterLink><RouterLink class="icon-button" title="Perfil" :to="{name:'profile'}"><i class="fa-solid fa-user"/></RouterLink></header>
      <main><RouterView /></main>
    </div>
    <nav class="mobile-bottom-nav">
      <RouterLink v-for="item in navigation" :key="item[0]" :to="{name:item[0]}" :class="{active:route.name===item[0] || (item[0]==='history'&&route.name==='history-detail')}"><i class="fa-solid" :class="item[1]"/><span>{{ item[2] }}</span></RouterLink>
    </nav>
  </div>
</template>
