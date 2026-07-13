<script setup>
import BaseModal from '../common/BaseModal.vue';

defineProps({
  open: Boolean,
  exercise: { type: Object, default: null },
});
defineEmits(['close']);

const metricLabels={
  weight_reps:'Peso y repeticiones',
  reps_only:'Solo repeticiones',
  duration:'Duracion',
  weight_duration:'Peso y duracion',
  bodyweight_reps:'Peso corporal',
  bodyweight_added_weight:'Peso corporal + carga',
};
const weightModeLabels={
  total:'Peso total',
  per_dumbbell:'Por mancuerna',
  per_side:'Por lado',
  machine_stack:'Pila de maquina',
  added_weight:'Peso anadido',
  bodyweight:'Peso corporal',
  not_applicable:'No aplica',
};
</script>

<template>
  <BaseModal :open="open" :title="exercise?.name || 'Detalle del ejercicio'" size="wide" @close="$emit('close')">
    <div v-if="exercise" class="exercise-detail-grid">
      <section class="exercise-detail-media" aria-label="Referencias visuales">
        <div class="exercise-detail-frame">
          <img v-if="exercise.image_url" :src="exercise.image_url" :alt="exercise.name"/>
          <div v-else class="exercise-detail-empty">
            <i class="fa-solid fa-image"/>
            <span>Sin imagen</span>
          </div>
        </div>

        <div v-if="exercise.youtube_embed_url" class="exercise-detail-video">
          <iframe
            :src="exercise.youtube_embed_url"
            :title="`Video de referencia de ${exercise.name}`"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
          />
        </div>
        <div v-else class="exercise-detail-frame exercise-detail-empty">
          <i class="fa-brands fa-youtube"/>
          <span>Sin video de referencia</span>
        </div>

        <a
          v-if="exercise.youtube_url"
          class="btn btn-outline-light justify-content-center"
          :href="exercise.youtube_url"
          target="_blank"
          rel="noopener noreferrer"
        >
          <i class="fa-brands fa-youtube"/>
          Abrir en YouTube
        </a>
      </section>

      <section class="exercise-detail-content">
        <div class="exercise-detail-facts">
          <div><small>Equipo</small><strong>{{ exercise.equipment || 'Sin equipo' }}</strong></div>
          <div><small>Musculos</small><strong>{{ exercise.muscle_groups?.map(group=>group.name).join(', ') || 'Sin grupo muscular' }}</strong></div>
          <div><small>Metrica</small><strong>{{ metricLabels[exercise.metric_type] || exercise.metric_type }}</strong></div>
          <div><small>Modo de peso</small><strong>{{ weightModeLabels[exercise.weight_mode] || exercise.weight_mode }}</strong></div>
          <div><small>Unidad</small><strong>{{ exercise.default_weight_unit || 'No aplica' }}</strong></div>
          <div><small>Incremento</small><strong>{{ exercise.default_increment ? `${exercise.default_increment} ${exercise.default_weight_unit || ''}` : 'No definido' }}</strong></div>
        </div>

        <div class="exercise-detail-copy">
          <h3>Descripcion</h3>
          <p>{{ exercise.description || 'Sin descripcion.' }}</p>
        </div>
        <div class="exercise-detail-copy">
          <h3>Instrucciones</h3>
          <p>{{ exercise.instructions || 'Sin instrucciones.' }}</p>
        </div>
      </section>
    </div>
    <template #footer>
      <button class="btn btn-outline-light" @click="$emit('close')">Cerrar</button>
    </template>
  </BaseModal>
</template>
