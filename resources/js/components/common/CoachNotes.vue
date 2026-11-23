<script setup>
import { formatDay } from '../../utils/dates';

defineProps({ notes: { type: Array, default: () => [] } });
const kinds = {
  note: { icon: 'fa-comment-dots', label: 'Nota' },
  summary: { icon: 'fa-clipboard-check', label: 'Resumen' },
  change: { icon: 'fa-arrows-rotate', label: 'Cambio de rutina' },
};
const kind = (note) => kinds[note.kind] || kinds.note;
</script>
<template>
  <ul class="coach-notes">
    <li v-for="note in notes" :key="note.id" :class="note.kind">
      <span class="coach-icon"><i class="fa-solid" :class="kind(note).icon" /></span>
      <div class="min-width-0">
        <div class="coach-meta"><strong>{{ kind(note).label }}</strong><span>{{ formatDay(note.for_date, { weekday: 'short', day: 'numeric', month: 'short' }) }}</span></div>
        <p>{{ note.body }}</p>
      </div>
    </li>
  </ul>
</template>
