import { createApp } from 'vue';
import { createPinia } from 'pinia';
import '@fontsource/barlow/400.css';
import '@fontsource/barlow/500.css';
import '@fontsource/barlow/600.css';
import '@fontsource/barlow-condensed/600.css';
import '@fontsource/barlow-condensed/700.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'bootstrap';
import App from './App.vue';
import router from './router';
import { registerPushServiceWorker } from './services/pushNotifications';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.use(router);

registerPushServiceWorker().catch(() => {});
useAuthStore(pinia).restore().finally(() => app.mount('#app'));
