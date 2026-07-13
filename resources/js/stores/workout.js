import { defineStore } from 'pinia';
import api from '../api/client';

export const useWorkoutStore = defineStore('workout', {
  state: () => ({ session: null, currentIndex: 0, loading: false }),
  getters: {
    currentExercise: (state) => state.session?.exercises?.[state.currentIndex] || null,
    exerciseCount: (state) => state.session?.exercises?.length || 0,
  },
  actions: {
    async restore() { this.loading = true; try { this.session = (await api.get('/workouts/current')).data.data; } finally { this.loading = false; } },
    async start(payload) { this.session = (await api.post('/workouts/start', payload)).data.data; this.currentIndex = 0; },
    async addSet(exerciseId, payload) { const result = (await api.post(`/workout-exercises/${exerciseId}/sets`, payload)).data; this.currentExercise.sets.push(result.set); return result; },
    async updateSet(setId, payload) { const result = (await api.put(`/workout-sets/${setId}`, payload)).data; const index = this.currentExercise.sets.findIndex((set) => set.id === setId); this.currentExercise.sets[index] = result.set; return result; },
    async deleteSet(setId) { await api.delete(`/workout-sets/${setId}`); this.currentExercise.sets = this.currentExercise.sets.filter((set) => set.id !== setId); },
    async substitute(exerciseId, payload) { const updated = (await api.post(`/workout-exercises/${exerciseId}/substitute`, payload)).data.data; this.session.exercises.splice(this.currentIndex, 1, updated); },
    async finish(status, feedback = {}) { const endpoint = status === 'partial' ? 'mark-partial' : 'finish'; const completed = (await api.post(`/workouts/${this.session.id}/${endpoint}`, feedback)).data.data; this.session = null; return completed; },
    async cancel() { await api.post(`/workouts/${this.session.id}/cancel`); this.session = null; },
    next() { if (this.currentIndex < this.exerciseCount - 1) this.currentIndex++; },
    previous() { if (this.currentIndex > 0) this.currentIndex--; },
  },
});
