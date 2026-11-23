<script setup>
import { computed } from 'vue';
import { parseDay } from '../../utils/dates';

const props = defineProps({ items: { type: Array, default: () => [] } });
const labels = { completed: 'Completo', partial: 'Parcial', planned_rest: 'Descanso planificado', skipped: 'Omitido', in_progress: 'En curso', none: 'Sin actividad' };
// Cada columna es una semana (lunes a domingo): se rellena el hueco inicial.
const leading = computed(() => {
  if (!props.items.length) return 0;
  const first = parseDay(props.items[0].date);
  return first ? (first.getDay() + 6) % 7 : 0;
});
const title = (item) => `${item.date}: ${labels[item.state] || item.state}${item.record ? ' + récord' : ''}`;
</script>
<template>
  <div>
    <div class="activity-calendar" role="img" aria-label="Calendario de actividad de las últimas 16 semanas">
      <span v-for="n in leading" :key="`gap-${n}`" class="activity-cell" style="visibility:hidden" />
      <span v-for="item in items" :key="item.date" class="activity-cell" :class="[item.state, { record: item.record }]" :title="title(item)" />
    </div>
    <div class="activity-legend">
      <span><i class="completed" />Completo</span><span><i class="partial" />Parcial</span><span><i class="planned_rest" />Descanso</span><span><i class="skipped" />Omitido</span><span><i />Sin actividad</span><span><i class="record" />Récord</span>
    </div>
  </div>
</template>
