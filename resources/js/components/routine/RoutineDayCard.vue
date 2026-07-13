<script setup>
defineProps({ day:Object, quickMode:Boolean });
defineEmits(['start','edit-day','edit-exercise','delete-exercise','view-exercise','move']);
const statusLabels={completed:'Completado',partial:'Parcial',in_progress:'En curso',cancelled:'Cancelado',pending:'Pendiente',scheduled:'Programado'};
const statusClasses={completed:'green',partial:'yellow',in_progress:'blue',cancelled:'red',pending:'yellow'};
</script>
<template>
  <article class="day-card" :class="{rest:day.day_type==='rest'}">
    <header><div><div class="d-flex align-items-center gap-2 flex-wrap"><h3>{{ day.name }}</h3><span class="badge-soft" :class="day.day_type==='training'?'green':''">{{ day.day_type==='training'?'Entrenamiento':'Descanso' }}</span><span v-if="day.day_type==='training'" class="badge-soft" :class="statusClasses[day.week_status]">{{ statusLabels[day.week_status]||day.week_status }}</span></div><small>{{ ['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'][day.weekday-1] }}<template v-if="day.estimated_minutes"> · {{ quickMode?Math.round(day.estimated_minutes*.78):day.estimated_minutes }} min{{ quickMode?' aprox.':'' }}</template></small></div><div class="d-flex gap-1"><button class="icon-button compact" title="Editar dia" @click="$emit('edit-day',day)"><i class="fa-solid fa-pen"/></button><button v-if="day.day_type==='training'" class="icon-button compact" title="Iniciar entrenamiento" @click="$emit('start',day)"><i class="fa-solid fa-play"/></button></div></header>
    <div v-if="day.day_type==='training'" class="planned-exercises">
      <div v-for="(item,index) in day.exercises" v-show="!quickMode || item.priority!=='optional'" :key="item.id" class="planned-item">
        <span class="position">{{ index+1 }}</span><button class="text-start border-0 bg-transparent p-0" @click="$emit('edit-exercise',item,day)"><strong>{{ item.exercise.name }}</strong><small>{{ item.target_sets }} x {{ item.minimum_reps }}-{{ item.maximum_reps }} · {{ item.target_weight_label }} · {{ item.rest_seconds }} s</small></button>
        <div class="d-flex gap-1"><button class="icon-button compact" :title="`Ver ${item.exercise.name}`" @click="$emit('view-exercise',item.exercise)"><i class="fa-solid fa-eye"/></button><button class="icon-button compact" title="Subir" :disabled="index===0" @click="$emit('move',day,index,-1)"><i class="fa-solid fa-arrow-up"/></button><button class="icon-button compact" title="Bajar" :disabled="index===day.exercises.length-1" @click="$emit('move',day,index,1)"><i class="fa-solid fa-arrow-down"/></button></div>
      </div>
      <p v-if="quickMode && day.exercises.some(item=>item.priority==='optional')" class="small text-secondary mb-0"><i class="fa-solid fa-stopwatch me-1"/>Los ejercicios opcionales estan ocultos en modo rapido.</p>
    </div>
  </article>
</template>
