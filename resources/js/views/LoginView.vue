<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AvatarSvg from '../components/avatar/AvatarSvg.vue';
import BrandMark from '../components/common/BrandMark.vue';
import { useAuthStore } from '../stores/auth';
import { errorMessage } from '../utils/errors';

const auth = useAuthStore(); const router = useRouter(); const route = useRoute();
const form = reactive({ email: '', password: '', remember: true });
const showPassword = ref(false); const loading = ref(false); const error = ref('');
async function submit() { loading.value = true; error.value = ''; try { await auth.login(form); router.push(route.query.redirect || '/'); } catch (e) { error.value = errorMessage(e, 'No se pudo iniciar sesión.'); } finally { loading.value = false; } }
</script>
<template>
  <div class="login-page">
    <section class="login-panel">
      <form class="login-form" @submit.prevent="submit">
        <div class="brand-lockup"><span class="brand-mark"><BrandMark /></span><div><strong>Golden Path</strong><small>Seguimiento personal</small></div></div>
        <h1>Continúa tu camino</h1>
        <p>Tu rutina, rendimiento y constancia en un solo lugar.</p>
        <div v-if="error" class="alert alert-danger py-2" role="alert">{{ error }}</div>
        <div class="mb-3"><label class="form-label" for="email">Correo</label><input id="email" v-model="form.email" class="form-control" type="email" inputmode="email" autocomplete="email" required autofocus /></div>
        <div class="mb-3"><label class="form-label" for="password">Contraseña</label><div class="password-field"><input id="password" v-model="form.password" class="form-control" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required /><button type="button" class="icon-button" :title="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="showPassword = !showPassword"><i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" /></button></div></div>
        <div class="form-check mb-4"><input id="remember" v-model="form.remember" class="form-check-input" type="checkbox" /><label class="form-check-label" for="remember">Recordarme</label></div>
        <button class="btn btn-primary w-100" :disabled="loading"><i class="fa-solid" :class="loading ? 'fa-spinner fa-spin' : 'fa-right-to-bracket'" />{{ loading ? 'Ingresando...' : 'Iniciar sesión' }}</button>
      </form>
    </section>
    <section class="login-visual" aria-hidden="true">
      <svg class="login-path" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice"><path d="M-40 820H180V640H360V460H540V280H720V100H860" fill="none" stroke="#d4a84b" stroke-width="2" stroke-linejoin="round" /><path d="M-40 860H140V680H320V500H500V320H680V140H860" fill="none" stroke="#9a7a2e" stroke-width="1" stroke-linejoin="round" opacity=".6" /></svg>
      <div class="login-visual-content"><AvatarSvg :phase="3" :energy="92" :size="280" /><h2>La constancia toma forma</h2><p>Registra lo que haces, entiende por qué progresas y vuelve mañana con un objetivo concreto.</p></div>
    </section>
  </div>
</template>
