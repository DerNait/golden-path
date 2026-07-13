<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/client';
import BaseModal from '../components/common/BaseModal.vue';
import ExerciseDetailModal from '../components/exercises/ExerciseDetailModal.vue';
import LoadingSkeleton from '../components/common/LoadingSkeleton.vue';
import ExerciseLibrary from '../components/exercises/ExerciseLibrary.vue';
import RoutineDayCard from '../components/routine/RoutineDayCard.vue';
import PlannedExerciseModal from '../components/routine/PlannedExerciseModal.vue';
import { useNotificationStore } from '../stores/notifications';
import { errorMessage } from '../utils/errors';

const router=useRouter(); const notifications=useNotificationStore(); const routine=ref(null); const exercises=ref([]); const loading=ref(true); const tab=ref('week'); const quickMode=ref(false);
const plannedOpen=ref(false); const detailOpen=ref(false); const detailExercise=ref(null); const selectedItem=ref(null); const selectedDay=ref(null); const dayOpen=ref(false); const addOpen=ref(false); const routineOpen=ref(false);
const dayForm=reactive({}); const addForm=reactive({exercise_id:null,priority:'essential',target_sets:3,minimum_reps:8,maximum_reps:12,progression_target_reps:12,progression_target_total_reps:null,target_duration_seconds:null,target_weight:null,weight_unit:'kg',target_rir_min:1,target_rir_max:2,rest_seconds:120,weight_increment:2.5,progression_type:'double_progression',superset_group:null,notes:null});
const routineForm=reactive({name:'',description:''});
async function load(){ loading.value=true; try { routine.value=(await api.get('/routine')).data.data; const response=await api.get('/exercises',{params:{per_page:100}}); exercises.value=response.data.data; } catch(e){ notifications.push(errorMessage(e),'error'); } finally { loading.value=false; } }
function editPlanned(item,day){ selectedItem.value=item; selectedDay.value=day; plannedOpen.value=true; }
async function savePlanned(form){ try { await api.put(`/routine-exercises/${selectedItem.value.id}`,form); notifications.push('Configuracion actualizada.'); plannedOpen.value=false; await load(); } catch(e){ notifications.push(errorMessage(e),'error'); } }
async function removePlanned(){ if(!confirm('Eliminar este ejercicio del dia?')) return; try { await api.delete(`/routine-exercises/${selectedItem.value.id}`); plannedOpen.value=false; notifications.push('Ejercicio retirado de la rutina.'); await load(); } catch(e){ notifications.push(errorMessage(e),'error'); } }
function editDay(day){ selectedDay.value=day; Object.assign(dayForm,{name:day.name,weekday:day.weekday,day_type:day.day_type,estimated_minutes:day.estimated_minutes,notes:day.notes}); dayOpen.value=true; }
async function saveDay(){ try { await api.put(`/routine-days/${selectedDay.value.id}`,dayForm); dayOpen.value=false; notifications.push('Dia actualizado.'); await load(); } catch(e){ notifications.push(errorMessage(e),'error'); } }
function addExercise(day){ selectedDay.value=day; addForm.exercise_id=exercises.value.find(item=>!day.exercises.some(existing=>existing.exercise.id===item.id))?.id || null; addOpen.value=true; }
async function saveAdded(){ try { await api.post(`/routine-days/${selectedDay.value.id}/exercises`,addForm); addOpen.value=false; notifications.push('Ejercicio agregado.'); await load(); } catch(e){ notifications.push(errorMessage(e),'error'); } }
async function move(day,index,direction){ const next=index+direction; if(next<0||next>=day.exercises.length)return; const ids=day.exercises.map(item=>item.id); [ids[index],ids[next]]=[ids[next],ids[index]]; try { await api.post('/routine-exercises/reorder',{routine_day_id:day.id,ids}); await load(); } catch(e){ notifications.push(errorMessage(e),'error'); } }
function editRoutine(){ Object.assign(routineForm,{name:routine.value.name,description:routine.value.description}); routineOpen.value=true; }
async function saveRoutine(){ try { await api.put('/routine',routineForm); routineOpen.value=false; notifications.push('Rutina actualizada.'); await load(); } catch(e){ notifications.push(errorMessage(e),'error'); } }
function viewExercise(exercise){ detailExercise.value=exercise; detailOpen.value=true; }
function start(day){ router.push({name:'workout',query:{day:day.id}}); }
onMounted(load);
</script>
<template>
  <div>
    <header class="page-header"><div><div class="eyebrow">Plan semanal</div><h1>{{ routine?.name || 'Rutina' }}</h1><p>Cuatro sesiones, progresion separada y flexibilidad para reprogramar.</p></div><button class="icon-button" title="Editar rutina" :disabled="!routine" @click="editRoutine"><i class="fa-solid fa-pen"/></button></header>
    <div class="segmented-control mb-4"><button :class="{active:tab==='week'}" @click="tab='week'"><i class="fa-solid fa-calendar-week me-2"/>Semana</button><button :class="{active:tab==='library'}" @click="tab='library'"><i class="fa-solid fa-book me-2"/>Biblioteca</button></div>
    <LoadingSkeleton v-if="loading"/>
    <template v-else-if="tab==='week' && routine">
      <section class="surface panel mb-3 d-flex align-items-center justify-content-between gap-3"><div><strong>Entrenamiento rapido</strong><small class="d-block text-secondary">Conserva esenciales y oculta opcionales sin cambiar tu rutina.</small></div><div class="form-check form-switch m-0"><input v-model="quickMode" class="form-check-input" type="checkbox" role="switch" aria-label="Entrenamiento rapido"/></div></section>
      <div class="day-list"><template v-for="day in routine.days" :key="day.id"><RoutineDayCard :day="day" :quick-mode="quickMode" @start="start" @edit-day="editDay" @edit-exercise="editPlanned" @delete-exercise="removePlanned" @view-exercise="viewExercise" @move="move"/><button v-if="day.day_type==='training'" class="btn btn-outline-light w-100" @click="addExercise(day)"><i class="fa-solid fa-plus"/>Agregar a {{ day.name }}</button></template></div>
    </template>
    <ExerciseLibrary v-else-if="tab==='library'" @changed="load"/>

    <ExerciseDetailModal :open="detailOpen" :exercise="detailExercise" @close="detailOpen=false"/>
    <PlannedExerciseModal :open="plannedOpen" :item="selectedItem" @close="plannedOpen=false" @save="savePlanned" @remove="removePlanned"/>
    <BaseModal :open="dayOpen" title="Editar dia" @close="dayOpen=false"><form id="day-form" class="field-grid" @submit.prevent="saveDay"><div><label class="form-label">Nombre</label><input v-model="dayForm.name" class="form-control" required/></div><div><label class="form-label">Tipo</label><select v-model="dayForm.day_type" class="form-select"><option value="training">Entrenamiento</option><option value="rest">Descanso</option></select></div><div><label class="form-label">Dia de semana</label><select v-model.number="dayForm.weekday" class="form-select"><option v-for="(name,index) in ['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo']" :key="name" :value="index+1">{{ name }}</option></select></div><div><label class="form-label">Minutos estimados</label><input v-model.number="dayForm.estimated_minutes" class="form-control" type="number" min="15" max="240"/></div><div class="grid-column-full"><label class="form-label">Notas</label><textarea v-model="dayForm.notes" class="form-control" rows="3"/></div></form><template #footer><button class="btn btn-outline-light" @click="dayOpen=false">Cancelar</button><button form="day-form" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"/>Guardar</button></template></BaseModal>
    <BaseModal :open="addOpen" :title="`Agregar a ${selectedDay?.name || ''}`" @close="addOpen=false"><form id="add-form" class="field-grid" @submit.prevent="saveAdded"><div class="grid-column-full"><label class="form-label">Ejercicio</label><select v-model.number="addForm.exercise_id" class="form-select" required><option v-for="item in exercises.filter(x=>!selectedDay?.exercises.some(p=>p.exercise.id===x.id))" :key="item.id" :value="item.id">{{ item.name }}</option></select></div><div><label class="form-label">Prioridad</label><select v-model="addForm.priority" class="form-select"><option value="essential">Esencial</option><option value="recommended">Recomendado</option><option value="optional">Opcional</option></select></div><div><label class="form-label">Series</label><input v-model.number="addForm.target_sets" class="form-control" type="number" min="1" max="10"/></div><div><label class="form-label">Reps minimas</label><input v-model.number="addForm.minimum_reps" class="form-control" type="number" min="0"/></div><div><label class="form-label">Reps maximas</label><input v-model.number="addForm.maximum_reps" class="form-control" type="number" min="0"/></div><div><label class="form-label">Meta para subir</label><input v-model.number="addForm.progression_target_reps" class="form-control" type="number" min="0"/></div><div><label class="form-label">Descanso (s)</label><input v-model.number="addForm.rest_seconds" class="form-control" type="number" min="0"/></div></form><template #footer><button class="btn btn-outline-light" @click="addOpen=false">Cancelar</button><button form="add-form" class="btn btn-primary"><i class="fa-solid fa-plus"/>Agregar</button></template></BaseModal>
    <BaseModal :open="routineOpen" title="Editar rutina" @close="routineOpen=false"><form id="routine-form" @submit.prevent="saveRoutine"><div class="mb-3"><label class="form-label">Nombre</label><input v-model="routineForm.name" class="form-control" required/></div><div><label class="form-label">Descripcion</label><textarea v-model="routineForm.description" class="form-control" rows="4"/></div></form><template #footer><button class="btn btn-outline-light" @click="routineOpen=false">Cancelar</button><button form="routine-form" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"/>Guardar</button></template></BaseModal>
  </div>
</template>
