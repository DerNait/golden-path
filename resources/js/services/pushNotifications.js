import api from '../api/client';

let registrationPromise;
let configPromise;

function isIos() {
  return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function applicationServerKey(value) {
  const padding = '='.repeat((4 - (value.length % 4)) % 4);
  const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
}

export function pushSupported() {
  return window.isSecureContext
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window;
}

export function registerPushServiceWorker() {
  if (!pushSupported()) return Promise.resolve(null);

  registrationPromise ||= navigator.serviceWorker
    .register('/sw.js')
    .then(() => navigator.serviceWorker.ready);

  return registrationPromise;
}

async function pushConfig() {
  configPromise ||= api.get('/push/config').then((response) => response.data);
  return configPromise;
}

async function saveSubscription(subscription) {
  const payload = subscription.toJSON();

  await api.post('/push/subscriptions', {
    endpoint: payload.endpoint,
    keys: payload.keys,
    content_encoding: 'aes128gcm',
  });
}

export async function notificationState() {
  const base = {
    supported: pushSupported(),
    permission: typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
    subscribed: false,
    configured: false,
    iosNeedsInstall: isIos() && !isStandalone(),
  };

  if (!base.supported || base.iosNeedsInstall) return base;

  const [registration, configuration] = await Promise.all([
    registerPushServiceWorker(),
    pushConfig(),
  ]);
  const subscription = await registration.pushManager.getSubscription();

  return {
    ...base,
    permission: Notification.permission,
    subscribed: Boolean(subscription),
    configured: Boolean(configuration.enabled),
  };
}

export async function enablePushNotifications() {
  if (!pushSupported()) throw new Error('Este navegador no permite notificaciones web.');
  if (isIos() && !isStandalone()) {
    throw new Error('En iPhone, agrega Golden Path a la pantalla de inicio y abre esa app para activar avisos.');
  }

  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    throw new Error('Debes permitir las notificaciones para recibir el aviso de descanso.');
  }

  const [registration, configuration] = await Promise.all([
    registerPushServiceWorker(),
    pushConfig(),
  ]);

  if (!configuration.enabled || !configuration.public_key) {
    throw new Error('Las notificaciones todavía no están configuradas en el servidor.');
  }

  let subscription = await registration.pushManager.getSubscription();
  subscription ||= await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: applicationServerKey(configuration.public_key),
  });

  await saveSubscription(subscription);

  return subscription;
}

export async function syncPushSubscription() {
  if (!pushSupported() || Notification.permission !== 'granted') return false;

  const registration = await registerPushServiceWorker();
  const subscription = await registration.pushManager.getSubscription();
  if (!subscription) return false;

  await saveSubscription(subscription);
  return true;
}

export async function scheduleRestNotification(endsAt) {
  if (!pushSupported() || Notification.permission !== 'granted') return false;

  const registration = await registerPushServiceWorker();
  const subscription = await registration.pushManager.getSubscription();
  if (!subscription) return false;

  await api.post('/rest-timer/notifications', {
    ends_at: new Date(endsAt).toISOString(),
  });

  return true;
}

export async function cancelRestNotification() {
  await api.delete('/rest-timer/notifications/current');
}
