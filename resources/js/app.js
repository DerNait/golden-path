import { createApp } from 'vue';
import { createPinia } from 'pinia';
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
