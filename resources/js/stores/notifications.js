import { defineStore } from 'pinia';

let sequence = 0;
export const useNotificationStore = defineStore('notifications', {
  state: () => ({ items: [] }),
  actions: {
    push(message, type = 'success') {
      const id = ++sequence;
      this.items.push({ id, message, type });
      window.setTimeout(() => this.remove(id), 4000);
    },
    remove(id) { this.items = this.items.filter((item) => item.id !== id); },
  },
});
