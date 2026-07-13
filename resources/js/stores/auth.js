import { defineStore } from 'pinia';
import api, { csrf } from '../api/client';

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null, ready: false }),
  getters: { authenticated: (state) => Boolean(state.user) },
  actions: {
    async restore() {
      try { this.user = (await api.get('/me')).data.user; } catch { this.user = null; }
      finally { this.ready = true; }
    },
    async login(credentials) {
      await csrf();
      this.user = (await api.post('/login', credentials)).data.user;
    },
    async logout() {
      try { await api.post('/logout'); } finally { this.user = null; }
    },
  },
});
