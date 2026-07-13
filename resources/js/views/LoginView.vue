<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AvatarSvg from '../components/avatar/AvatarSvg.vue';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../utils/errors';

const auth=useAuthStore(); const router=useRouter(); const route=useRoute();
const form=reactive({email:'owner@example.com',password:'password',remember:true});
const showPassword=ref(false); const loading=ref(false); const error=ref('');
async function submit(){ loading.value=true; error.value=''; try { await auth.login(form); router.push(route.query.redirect || '/'); } catch(e){ error.value=errorMessage(e,'No se pudo iniciar sesion.'); } finally { loading.value=false; } }
</script>
<template>
  <div class="login-page">
    <section class="login-panel">
      <form class="login-form" @submit.prevent="submit">
        <div class="brand-lockup"><span class="brand-mark"><i class="fa-solid fa-bolt" /></span><div><strong>Golden Path</strong><small>Seguimiento personal</small></div></div>
        <h1>Continua tu camino</h1><p>Tu rutina, rendimiento y constancia en un solo lugar.</p>
        <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
        <div class="mb-3"><label class="form-label" for="email">Correo</label><input id="email" v-model="form.email" class="form-control" type="email" autocomplete="email" required /></div>
        <div class="mb-3"><label class="form-label" for="password">Contrasena</label><div class="password-field"><input id="password" v-model="form.password" class="form-control" :type="showPassword?'text':'password'" autocomplete="current-password" required /><button type="button" class="icon-button" :title="showPassword?'Ocultar contrasena':'Mostrar contrasena'" @click="showPassword=!showPassword"><i class="fa-solid" :class="showPassword?'fa-eye-slash':'fa-eye'" /></button></div></div>
        <div class="form-check mb-4"><input id="remember" v-model="form.remember" class="form-check-input" type="checkbox" /><label class="form-check-label" for="remember">Recordarme</label></div>
        <button class="btn btn-primary w-100" :disabled="loading"><i class="fa-solid" :class="loading?'fa-spinner fa-spin':'fa-right-to-bracket'"/>{{ loading?'Ingresando...':'Iniciar sesion' }}</button>
      </form>
    </section>
    <section class="login-visual"><div class="login-visual-content"><AvatarSvg :phase="2" :energy="92" :size="320"/><h2>La constancia toma forma</h2><p>Registra lo que haces, entiende por que progresas y vuelve manana con un objetivo concreto.</p></div></section>
  </div>
</template>
