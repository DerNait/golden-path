import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
  {
    path: '/', component: () => import('../layouts/AppLayout.vue'), meta: { auth: true }, children: [
      { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
      { path: 'routine', name: 'routine', component: () => import('../views/RoutineView.vue') },
      { path: 'workout', name: 'workout', component: () => import('../views/WorkoutView.vue') },
      { path: 'progress', name: 'progress', component: () => import('../views/ProgressView.vue') },
      { path: 'history', name: 'history', component: () => import('../views/HistoryView.vue') },
      { path: 'history/:id', name: 'history-detail', component: () => import('../views/HistoryDetailView.vue') },
      { path: 'profile', name: 'profile', component: () => import('../views/ProfileView.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: () => ({ top: 0 }) });
router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.ready) await auth.restore();
  if (to.meta.auth && !auth.authenticated) return { name: 'login', query: { redirect: to.fullPath } };
  if (to.meta.guest && auth.authenticated) return { name: 'dashboard' };
});
window.addEventListener('auth:expired', () => { const auth = useAuthStore(); auth.user = null; router.push({ name: 'login' }); });
export default router;
