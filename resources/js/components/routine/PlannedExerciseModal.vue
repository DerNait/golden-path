<script setup>
import { reactive, watch } from 'vue';
import BaseModal from '../common/BaseModal.vue';
const props=defineProps({open:Boolean,item:Object}); const emit=defineEmits(['close','save','remove']);
const form=reactive({});
watch(()=>props.item,(item)=>Object.assign(form,item?{priority:item.priority,target_sets:item.target_sets,minimum_reps:item.minimum_reps,maximum_reps:item.maximum_reps,progression_target_reps:item.progression_target_reps,progression_target_total_reps:item.progression_target_total_reps,target_duration_seconds:item.target_duration_seconds,target_weight:item.target_weight,weight_unit:item.weight_unit,target_rir_min:item.target_rir_min,target_rir_max:item.target_rir_max,rest_seconds:item.rest_seconds,weight_increment:item.weight_increment,progression_type:item.progression_type,superset_group:item.superset_group,notes:item.notes}:{}),{immediate:true});
</script>
<template>
  <BaseModal :open="open" :title="item?.exercise?.name || 'Editar ejercicio'" @close="$emit('close')">
    <form id="planned-form" class="field-grid" @submit.prevent="$emit('save',{...form})">
      <div><label class="form-label">Prioridad</label><select v-model="form.priority" class="form-select"><option value="essential">Esencial</option><option value="recommended">Recomendado</option><option value="optional">Opcional</option></select></div>
      <div><label class="form-label">Series</label><input v-model.number="form.target_sets" class="form-control" type="number" min="1" max="10" required/></div>
      <div><label class="form-label">Repeticiones minimas</label><input v-model.number="form.minimum_reps" class="form-control" type="number" min="0"/></div>
      <div><label class="form-label">Repeticiones maximas</label><input v-model.number="form.maximum_reps" class="form-control" type="number" min="0"/></div>
      <div><label class="form-label">Meta para progresar</label><input v-model.number="form.progression_target_reps" class="form-control" type="number" min="0"/></div>
      <div><label class="form-label">Meta total proxima sesion</label><input v-model.number="form.progression_target_total_reps" class="form-control" type="number" min="0" placeholder="Se calcula al aceptar"/></div>
      <div><label class="form-label">Peso objetivo</label><div class="input-group"><input v-model.number="form.target_weight" class="form-control" type="number" min="0" step="0.25" placeholder="Por calibrar"/><select v-model="form.weight_unit" class="form-select" style="max-width:90px"><option value="lb">lb</option><option value="kg">kg</option></select></div></div>
      <div><label class="form-label">RIR objetivo</label><div class="field-row"><input v-model.number="form.target_rir_min" class="form-control" type="number" min="0" max="5"/><input v-model.number="form.target_rir_max" class="form-control" type="number" min="0" max="5"/></div></div>
      <div><label class="form-label">Descanso (segundos)</label><input v-model.number="form.rest_seconds" class="form-control" type="number" min="0" max="900" required/></div>
      <div><label class="form-label">Incremento</label><input v-model.number="form.weight_increment" class="form-control" type="number" min="0.01" step="0.25"/></div>
      <div><label class="form-label">Progresion</label><select v-model="form.progression_type" class="form-select"><option value="double_progression">Doble progresion</option><option value="manual">Manual</option></select></div>
      <div><label class="form-label">Grupo de superset</label><input v-model="form.superset_group" class="form-control" maxlength="50"/></div>
      <div class="grid-column-full"><label class="form-label">Notas</label><textarea v-model="form.notes" class="form-control" rows="3"/></div>
    </form>
    <template #footer><button class="btn btn-danger-soft me-auto" @click="$emit('remove')"><i class="fa-solid fa-trash"/>Eliminar</button><button class="btn btn-outline-light" @click="$emit('close')">Cancelar</button><button form="planned-form" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"/>Guardar</button></template>
  </BaseModal>
</template>
