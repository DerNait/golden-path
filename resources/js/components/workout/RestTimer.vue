<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
  cancelRestNotification,
  enablePushNotifications,
  notificationState,
  scheduleRestNotification,
  syncPushSubscription,
} from '../../services/pushNotifications';

const props = defineProps({ defaultSeconds: { type: Number, default: 90 } });
const remaining = ref(0);
const running = ref(false);
const hasStarted = ref(false);
const endsAt = ref(null);
const pushState = ref({
  supported: true,
  permission: 'default',
  subscribed: false,
  configured: true,
  iosNeedsInstall: false,
});
const pushBusy = ref(false);
const pushMessage = ref('');
let interval = null;
let hasAlerted = false;
const key = 'golden-path-rest-timer';

const display = computed(() => `${String(Math.floor(remaining.value / 60)).padStart(2, '0')}:${String(remaining.value % 60).padStart(2, '0')}`);
const pushLabel = computed(() => {
  if (pushState.value.iosNeedsInstall) return 'En iPhone, agrega Golden Path a inicio para recibir avisos';
  if (!pushState.value.supported) return 'Este navegador no admite avisos';
  if (pushState.value.subscribed) return 'Avisos de descanso activos';
  if (pushState.value.permission === 'denied') return 'Avisos bloqueados en el navegador';
  return 'Activar avisos de descanso';
});

function persist() {
  localStorage.setItem(key, JSON.stringify({
    remaining: remaining.value,
    running: running.value,
    hasStarted: hasStarted.value,
    endsAt: endsAt.value,
    updatedAt: Date.now(),
  }));
}

function alertFinished() {
  if (hasAlerted) return;
  hasAlerted = true;

  if ('vibrate' in navigator) navigator.vibrate([180, 80, 180]);

  try {
    new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACAgICAgICAgICAgICAgICA').play();
  } catch {
    // Native Web Push remains the reliable background alert.
  }
}

function finishLocally() {
  remaining.value = 0;
  running.value = false;
  endsAt.value = null;
  persist();
  alertFinished();
}

function reconcile() {
  if (!running.value || !endsAt.value) return;

  const next = Math.max(0, Math.ceil((endsAt.value - Date.now()) / 1000));
  remaining.value = next;

  if (next === 0) finishLocally();
}

function scheduleCurrentNotification() {
  if (!running.value || !endsAt.value) return;

  scheduleRestNotification(endsAt.value).catch(() => {
    // The timestamp timer still works if push scheduling is temporarily unavailable.
  });
}

function start(seconds = props.defaultSeconds) {
  const duration = Math.max(1, Math.round(Number(seconds) || props.defaultSeconds));
  remaining.value = duration;
  endsAt.value = Date.now() + duration * 1000;
  running.value = true;
  hasStarted.value = true;
  hasAlerted = false;
  persist();
  scheduleCurrentNotification();
}

function pause() {
  reconcile();
  running.value = false;
  endsAt.value = null;
  persist();
  cancelRestNotification().catch(() => {});
}

function resume() {
  if (remaining.value <= 0) return;

  endsAt.value = Date.now() + remaining.value * 1000;
  running.value = true;
  hasAlerted = false;
  persist();
  scheduleCurrentNotification();
}

function add() {
  if (running.value && endsAt.value) {
    endsAt.value += 15000;
    reconcile();
    persist();
    scheduleCurrentNotification();
    return;
  }

  remaining.value += 15;
  persist();
}

function reset() {
  start(props.defaultSeconds);
}

function skip() {
  remaining.value = 0;
  running.value = false;
  hasStarted.value = false;
  endsAt.value = null;
  hasAlerted = false;
  persist();
  cancelRestNotification().catch(() => {});
}

async function refreshPushState() {
  try {
    pushState.value = await notificationState();
    if (pushState.value.subscribed) await syncPushSubscription();
  } catch {
    pushState.value.configured = false;
  }
}

async function activatePush() {
  pushBusy.value = true;
  pushMessage.value = '';

  try {
    await enablePushNotifications();
    pushState.value = await notificationState();
    pushMessage.value = 'Listo. Te avisaremos aunque cambies de app.';
    scheduleCurrentNotification();
  } catch (error) {
    pushMessage.value = error.message || 'No fue posible activar los avisos.';
    await refreshPushState();
  } finally {
    pushBusy.value = false;
  }
}

function handleVisibilityChange() {
  if (!document.hidden) reconcile();
}

onMounted(() => {
  const saved = JSON.parse(localStorage.getItem(key) || 'null');

  if (saved) {
    remaining.value = Math.max(0, Number(saved.remaining) || 0);
    running.value = Boolean(saved.running);
    hasStarted.value = Boolean(saved.hasStarted);

    if (saved.endsAt) {
      endsAt.value = Number(saved.endsAt);
    } else if (running.value) {
      const elapsed = Math.floor((Date.now() - Number(saved.updatedAt || Date.now())) / 1000);
      remaining.value = Math.max(0, remaining.value - elapsed);
      endsAt.value = Date.now() + remaining.value * 1000;
    }

    reconcile();
  }

  interval = window.setInterval(reconcile, 500);
  document.addEventListener('visibilitychange', handleVisibilityChange);
  window.addEventListener('focus', reconcile);
  window.addEventListener('pageshow', reconcile);
  refreshPushState();
});

onBeforeUnmount(() => {
  window.clearInterval(interval);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
  window.removeEventListener('focus', reconcile);
  window.removeEventListener('pageshow', reconcile);
});

defineExpose({ start, skip });
</script>

<template>
  <div class="timer-panel" :class="{ 'timer-finished': hasStarted && remaining === 0 && !running }">
    <div>
      <small class="text-secondary d-block">Descanso</small>
      <div class="timer-display" :class="{ 'text-warning': remaining <= 15 && remaining > 0 }">{{ display }}</div>
    </div>

    <div class="timer-actions">
      <div class="timer-controls">
        <button class="icon-button compact" :title="running ? 'Pausar' : 'Reanudar'" @click="running ? pause() : resume()">
          <i class="fa-solid" :class="running ? 'fa-pause' : 'fa-play'"/>
        </button>
        <button class="icon-button compact" title="Anadir 15 segundos" @click="add"><i class="fa-solid fa-plus"/></button>
        <button class="icon-button compact" title="Reiniciar" @click="reset"><i class="fa-solid fa-rotate-right"/></button>
        <button class="icon-button compact" title="Omitir" @click="skip"><i class="fa-solid fa-forward"/></button>
      </div>

      <button
        v-if="!pushState.subscribed"
        class="timer-notification-button"
        type="button"
        :disabled="pushBusy || !pushState.supported || pushState.permission === 'denied'"
        @click="activatePush"
      >
        <i class="fa-solid fa-bell"/>
        {{ pushBusy ? 'Activando...' : pushLabel }}
      </button>
      <small v-else class="timer-notification-status"><i class="fa-solid fa-bell"/> {{ pushLabel }}</small>
      <small v-if="pushMessage" class="timer-notification-message">{{ pushMessage }}</small>
    </div>
  </div>
</template>
