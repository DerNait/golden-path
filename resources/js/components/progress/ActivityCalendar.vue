<script setup>
defineProps({items:{type:Array,default:()=>[]}});
const labels={completed:'Completo',partial:'Parcial',planned_rest:'Descanso planificado',skipped:'Omitido',in_progress:'En curso',none:'Sin actividad'};
</script>
<template>
  <div>
    <div class="activity-calendar"><span v-for="item in items" :key="item.date" class="activity-cell" :class="[item.state,{record:item.record}]" :title="`${item.date}: ${labels[item.state]||item.state}${item.record?' + record':''}`"/></div>
    <div class="d-flex gap-3 flex-wrap mt-3 small text-secondary"><span><i class="fa-solid fa-square text-success me-1"/>Completo</span><span><i class="fa-solid fa-square text-warning me-1"/>Parcial</span><span><i class="fa-solid fa-square me-1" style="color:#344154"/>Descanso</span><span><i class="fa-solid fa-square text-danger me-1"/>Omitido</span><span><i class="fa-regular fa-square me-1"/>Sin actividad</span><span><i class="fa-solid fa-square me-1" style="color:#60a5fa"/>Borde azul: record</span></div>
  </div>
</template>
