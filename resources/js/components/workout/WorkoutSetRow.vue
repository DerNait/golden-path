<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({ set: Object, number: Number, unit: String, metricType: String });
const emit = defineEmits(['save', 'remove']);
const form = reactive({ set_number: props.number, set_type: 'working', weight: null, weight_unit: props.unit, repetitions: null, duration_seconds: null, rir: null, technique_rating: null, rest_seconds_actual: null, completed: true, notes: null });
const details = ref(false);
watch(() => props.set, (set) => { if (set) Object.assign(form, set); }, { immediate: true });
watch(() => props.number, (number) => { if (!props.set) form.set_number = number; });

const hasWeight = computed(() => !['duration', 'reps_only', 'bodyweight_reps'].includes(props.metricType));
const usesReps = computed(() => !['duration', 'weight_duration'].includes(props.metricType));
const columns = computed(() => 1 + Number(hasWeight.value) + 1);
const typeLabels = { warmup: 'Calentamiento', working: 'Efectiva', backoff: 'Descarga', drop: 'Drop set' };
</script>
<template>
  <li class="set-row" :class="{ saved: !!set, new: !set, warmup: form.set_type === 'warmup' }">
    <div class="set-main" :style="{ '--cols': columns }">
      <span class="set-num" :title="typeLabels[form.set_type]">{{ form.set_number }}</span>
      <label v-if="hasWeight" class="set-field"><span>Peso ({{ form.weight_unit || unit }})</span><input v-model.number="form.weight" class="form-control" type="number" inputmode="decimal" min="0" step=".25" placeholder="0" /></label>
      <label v-if="usesReps" class="set-field"><span>Reps</span><input v-model.number="form.repetitions" class="form-control" type="number" inputmode="numeric" min="0" placeholder="0" /></label>
      <label v-else class="set-field"><span>Segundos</span><input v-model.number="form.duration_seconds" class="form-control" type="number" inputmode="numeric" min="0" placeholder="0" /></label>
      <label class="set-field"><span>RIR</span><input v-model.number="form.rir" class="form-control" type="number" inputmode="numeric" min="0" max="5" placeholder="-" /></label>
      <button class="set-save" type="button" :title="set ? 'Actualizar serie' : 'Guardar serie'" :aria-label="set ? 'Actualizar serie' : 'Guardar serie'" @click="emit('save', { ...form })"><i class="fa-solid fa-check" /></button>
    </div>
    <div class="set-meta">
      <button class="link-button" type="button" title="Tipo de serie, técnica, descanso y notas" :aria-expanded="details" @click="details = !details"><i class="fa-solid fa-sliders" />{{ typeLabels[form.set_type] }}</button>
      <button v-if="set" class="link-button danger" type="button" @click="emit('remove', set.id)"><i class="fa-solid fa-trash" />Eliminar</button>
    </div>
    <div v-if="details" class="set-details">
      <div class="field-grid">
        <div><label class="form-label">Tipo de serie</label><select v-model="form.set_type" class="form-select"><option value="warmup">Calentamiento</option><option value="working">Efectiva</option><option value="backoff">Descarga</option><option value="drop">Drop set</option></select></div>
        <div><label class="form-label">Unidad</label><select v-model="form.weight_unit" class="form-select"><option value="lb">lb</option><option value="kg">kg</option></select></div>
        <div><label class="form-label">Técnica (1-5)</label><input v-model.number="form.technique_rating" class="form-control" type="number" inputmode="numeric" min="1" max="5" placeholder="Opcional" /></div>
        <div><label class="form-label">Descanso real (s)</label><input v-model.number="form.rest_seconds_actual" class="form-control" type="number" inputmode="numeric" min="0" max="3600" placeholder="Opcional" /></div>
        <div class="grid-column-full"><label class="form-label">Notas</label><input v-model="form.notes" class="form-control" maxlength="1000" placeholder="Ejecución, tempo o contexto" /></div>
        <div class="grid-column-full form-check"><input :id="`done-${form.set_number}`" v-model="form.completed" class="form-check-input" type="checkbox" /><label :for="`done-${form.set_number}`" class="form-check-label">Serie completada</label></div>
      </div>
    </div>
  </li>
</template>
