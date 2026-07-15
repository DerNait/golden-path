const CACHE_NAME = 'golden-path-shell-v1';
const SHELL_FILES = ['/manifest.webmanifest'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_FILES)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let data = {};

  try {
    data = event.data?.json() || {};
  } catch {
    data = { body: event.data?.text() };
  }

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const visible = windows.some((client) => client.visibilityState === 'visible');

    windows.forEach((client) => client.postMessage({ type: 'rest-timer-finished' }));

    if (visible) return;

    await self.registration.showNotification(data.title || 'Descanso terminado', {
      body: data.body || 'Es momento de comenzar tu siguiente serie.',
      icon: '/icons/golden-path.svg',
      badge: '/icons/golden-path.svg',
      tag: data.tag || 'golden-path-rest-timer',
      renotify: true,
      vibrate: [180, 80, 180],
      data: { url: data.url || '/workout' },
    });
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  event.waitUntil((async () => {
    const url = new URL(event.notification.data?.url || '/workout', self.location.origin);
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    for (const client of windows) {
      if (new URL(client.url).origin === url.origin) {
        await client.focus();
        client.navigate(url.href);
        return;
      }
    }

    await self.clients.openWindow(url.href);
  })());
});
