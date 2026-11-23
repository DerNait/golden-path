<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/client';
import AvatarSvg from '../components/avatar/AvatarSvg.vue';
import LoadingSkeleton from '../components/common/LoadingSkeleton.vue';
import EmptyState from '../components/common/EmptyState.vue';
import { useNotificationStore } from '../stores/notifications';
import { errorMessage } from '../utils/errors';
import { formatDay } from '../utils/dates';

const data = ref(null); const loading = ref(true); const router = useRouter(); const notifications = useNotificationStore();
const game = computed(() => data.value?.game || {});
const activePhase = computed(() => game.value.active_phase || game.value.activePhase || { name: 'Fase 1 - Iniciado', position: 1 });
const maxPhase = computed(() => game.value.maximum_phase || game.value.maximumPhase || { name: 'Fase 1 - Iniciado' });
const phasePosition = computed(() => activePhase.value.position || 1);
const xpNext = computed(() => 100 * (game.value.level + 1) * game.value.level / 2);
const xpCurrentLevel = computed(() => 100 * game.value.level * (game.value.level - 1) / 2);
const xpPercent = computed(() => Math.min(100, Math.max(0, (game.value.total_xp - xpCurrentLevel.value) / Math.max(1, xpNext.value - xpCurrentLevel.value) * 100)));
const xpMissing = computed(() => Math.max(0, Math.ceil(xpNext.value - game.value.total_xp)));
const isTraining = computed(() => data.value?.today?.day_type === 'training');
const weekdayLabel = computed(() => new Date().toLocaleDateString('es-GT', { weekday: 'long' }));
const heroMeta = computed(() => (isTraining.value
  ? `${data.value.today.exercises?.length || 0} ejercicios · ${data.value.today.estimated_minutes || 75} min estimados`
  : 'El descanso planificado también sostiene tu progreso. Puedes recuperar otra sesión si lo necesitas.'));
const ctaLabel = computed(() => (data.value?.active_workout_id ? 'Continuar entrenamiento' : isTraining.value ? 'Iniciar entrenamiento' : 'Ver entrenamientos'));
const phaseDates = computed(() => {
  const phase = data.value?.active_phase;
  if (!phase) return '';
  return `${formatDay(phase.starts_on)} - ${formatDay(phase.ends_on)}`;
});

async function load() { try { data.value = (await api.get('/dashboard')).data; } catch (e) { notifications.push(errorMessage(e), 'error'); } finally { loading.value = false; } }
function train() { router.push({ name: 'workout', query: isTraining.value ? { day: data.value.today.id } : {} }); }
function signed(value, unit) { return value == null ? '-' : `${Number(value) > 0 ? '+' : ''}${Number(value).toFixed(1)} ${unit}`; }
const recordLabels = { heaviest_weight: 'Mayor peso', most_repetitions: 'Más repeticiones', best_set_volume: 'Mejor volumen de serie', estimated_one_rep_max: 'Mejor 1RM estimado' };
const recordLabel = (type) => recordLabels[type] || type;
const confidenceLabels = { low: 'Baja', medium: 'Media', high: 'Alta' };
const confidenceLabel = (value) => confidenceLabels[value] || value;
onMounted(load);
</script>
<template>
  <div>
    <header class="page-header">
      <div><div class="eyebrow">Panel personal</div><h1>Hola, {{ data?.user?.name || 'DerNait' }}</h1><p>La siguiente acción importa más que la sesión perfecta.</p></div>
    </header>
    <LoadingSkeleton v-if="loading" />
    <template v-else-if="data">
      <!-- Hoy: la acción principal va primero; el estado del avatar queda debajo en móvil -->
      <section class="surface hero" aria-label="Entrenamiento de hoy">
        <div class="hero-today">
          <div class="hero-kicker">Hoy · {{ weekdayLabel }}</div>
          <h2>{{ isTraining ? data.today.name : 'Día de recuperación' }}</h2>
          <p class="hero-meta">{{ heroMeta }}</p>
          <div class="hero-actions">
            <button class="btn btn-primary" @click="train"><i class="fa-solid fa-dumbbell" />{{ ctaLabel }}</button>
            <RouterLink class="btn btn-outline-light" :to="{ name: 'routine' }"><i class="fa-solid fa-list-check" />Ver rutina</RouterLink>
          </div>
        </div>
        <div class="hero-status">
          <AvatarSvg :phase="phasePosition" :energy="game.energy" :size="120" />
          <div>
            <div class="hero-phase"><span>Forma activa</span><strong>{{ activePhase.name }}</strong></div>
            <div class="xp-row"><span>Nivel {{ game.level }}</span><span>{{ xpMissing }} XP para el {{ game.level + 1 }}</span></div>
            <div class="progress-track" role="progressbar" :aria-valuenow="Math.round(xpPercent)" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso al siguiente nivel"><span :style="{ width: `${xpPercent}%` }" /></div>
          </div>
          <p v-if="game.energy < 70 && game.total_xp > 0" class="hero-note">Tu energía bajó por la actividad reciente, pero tus fases desbloqueadas siguen siendo tuyas (máxima: {{ maxPhase.name }}). Mantén la constancia para recuperar tu forma activa.</p>
        </div>
      </section>

      <section class="ledger section-block" aria-label="Estado actual">
        <article class="stat-card" :title="data.energy_explanation"><span class="stat-icon"><i class="fa-solid fa-bolt" /></span><strong>{{ game.energy }}</strong><small>Energía reciente</small></article>
        <article class="stat-card"><span class="stat-icon"><i class="fa-solid fa-fire" /></span><strong>{{ game.combat_power?.toLocaleString() }}</strong><small>Poder de combate</small></article>
        <article class="stat-card"><span class="stat-icon"><i class="fa-solid fa-calendar-check" /></span><strong>{{ game.current_weekly_streak }}</strong><small>Semanas de racha</small></article>
        <article class="stat-card accent"><span class="stat-icon"><i class="fa-solid fa-bullseye" /></span><strong>{{ data.weekly.completed }}<small>/ {{ data.weekly.goal }}</small></strong><small>Meta de esta semana</small></article>
      </section>
      <p v-if="data.energy_explanation" class="small text-secondary mt-2 mb-0"><i class="fa-solid fa-circle-info me-2" />{{ data.energy_explanation }}</p>

      <section class="surface panel section-block" aria-label="Fase de entrenamiento">
        <div class="section-heading">
          <div><h2>{{ data.active_phase?.name || 'Fase de entrenamiento' }}</h2><p v-if="data.active_phase">{{ phaseDates }}</p></div>
          <span class="badge-soft green">{{ data.active_phase?.minimum_target_sessions || 40 }} sesiones meta</span>
        </div>
        <dl class="facts">
          <div><dt>Sesiones este mes</dt><dd>{{ data.sessions_this_month }}</dd></div>
          <div><dt>Adherencia de 28 días</dt><dd>{{ Number(game.adherence_last_28_days || 0).toFixed(0) }}<small>%</small></dd></div>
          <div><dt>Cambio de peso</dt><dd>{{ signed(data.body_changes?.weight_kg, 'kg') }}</dd></div>
          <div><dt>Cambio de cintura</dt><dd>{{ signed(data.body_changes?.waist_cm, 'cm') }}</dd></div>
          <div><dt>Peso reciente</dt><dd><template v-if="data.latest_measurement?.body_weight_kg || data.profile.current_body_weight_kg">{{ data.latest_measurement?.body_weight_kg ?? data.profile.current_body_weight_kg }} <small>kg</small></template><template v-else>Pendiente</template></dd></div>
          <div><dt>Cintura reciente</dt><dd><template v-if="data.latest_measurement?.waist_cm || data.profile.current_waist_cm">{{ data.latest_measurement?.waist_cm ?? data.profile.current_waist_cm }} <small>cm</small></template><template v-else>Pendiente</template></dd></div>
          <div v-if="data.latest_measurement?.body_fat_percentage != null"><dt>Grasa corporal</dt><dd>{{ data.latest_measurement.body_fat_percentage }} <small>%</small></dd></div>
          <div v-if="data.latest_measurement?.skeletal_muscle_mass_kg != null"><dt>Músculo esquelético</dt><dd>{{ data.latest_measurement.skeletal_muscle_mass_kg }} <small>kg</small></dd></div>
          <div><dt>Proteína orientativa</dt><dd>{{ data.profile.protein_goal_min_grams }}-{{ data.profile.protein_goal_max_grams }} <small>g</small></dd></div>
          <div><dt>Sueño orientativo</dt><dd>{{ data.profile.sleep_goal_hours }}+ <small>h</small></dd></div>
        </dl>
      </section>

      <section class="grid two-column section-block">
        <div>
          <div class="section-heading"><div><h2>Recomendaciones de la IA</h2><p>Aplicadas en cuanto tu entrenador las publica.</p></div><RouterLink class="icon-button compact" title="Ver progreso" aria-label="Ver progreso" :to="{ name: 'progress' }"><i class="fa-solid fa-arrow-right" /></RouterLink></div>
          <div v-if="data.recent_recommendations.length" class="recommendation-list">
            <article v-for="item in data.recent_recommendations" :key="item.id" class="recommendation-row">
              <div class="d-flex justify-content-between align-items-start gap-2"><strong>{{ item.exercise.name }}<small v-if="item.target_sets" class="text-secondary fw-normal"> · {{ item.target_sets }} series</small></strong><span class="d-flex gap-1 flex-none"><span class="badge-soft green">IA</span><span class="badge-soft blue">{{ confidenceLabel(item.confidence) }}</span></span></div>
              <p class="small text-secondary mb-0 mt-2">{{ item.reason }}</p>
            </article>
          </div>
          <EmptyState v-else title="Sin recomendaciones de la IA" text="Pídele a tu entrenador IA un análisis para recibir recomendaciones." icon="fa-lightbulb" />
        </div>
        <div>
          <div class="section-heading"><div><h2>Últimos récords</h2><p>Tus mejores marcas por ejercicio.</p></div></div>
          <div v-if="data.latest_records.length" class="record-list">
            <article v-for="record in data.latest_records" :key="record.id" class="record-row">
              <div class="d-flex align-items-center gap-3"><span class="record-icon"><i class="fa-solid fa-trophy" /></span><div class="min-width-0"><strong>{{ record.exercise.name }}</strong><small class="d-block text-secondary">{{ recordLabel(record.record_type) }} · {{ record.value }} {{ record.weight_unit || '' }}</small></div></div>
            </article>
          </div>
          <EmptyState v-else title="Tu primera marca está cerca" text="Los calentamientos no cuentan; completa una serie efectiva para comenzar." icon="fa-trophy" />
        </div>
      </section>
    </template>
  </div>
</template>
