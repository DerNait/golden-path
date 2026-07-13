<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api/client';
import AvatarSvg from '../components/avatar/AvatarSvg.vue';
import LoadingSkeleton from '../components/common/LoadingSkeleton.vue';
import EmptyState from '../components/common/EmptyState.vue';
import { useNotificationStore } from '../stores/notifications';
import { errorMessage } from '../utils/errors';

const data=ref(null); const loading=ref(true); const router=useRouter(); const notifications=useNotificationStore();
const game=computed(()=>data.value?.game||{}); const phasePosition=computed(()=>game.value.active_phase?.position||game.value.activePhase?.position||1);
const xpNext=computed(()=>100*(game.value.level+1)*game.value.level/2); const xpCurrentLevel=computed(()=>100*game.value.level*(game.value.level-1)/2);
const xpPercent=computed(()=>Math.min(100,Math.max(0,(game.value.total_xp-xpCurrentLevel.value)/Math.max(1,xpNext.value-xpCurrentLevel.value)*100)));
async function load(){ try { data.value=(await api.get('/dashboard')).data; } catch(e){ notifications.push(errorMessage(e),'error'); } finally { loading.value=false; } }
function train(){ router.push({name:'workout',query:data.value?.today?.day_type==='training'?{day:data.value.today.id}:{}}); }
function signed(value,unit){return value==null?'-':`${Number(value)>0?'+':''}${Number(value).toFixed(1)} ${unit}`;}
const recordLabels={heaviest_weight:'Mayor peso',most_repetitions:'Mas repeticiones',best_set_volume:'Mejor volumen de serie',estimated_one_rep_max:'Mejor 1RM estimado'};
const recordLabel=type=>recordLabels[type]||type;
const confidenceLabels={low:'Baja',medium:'Media',high:'Alta'};
const confidenceLabel=value=>confidenceLabels[value]||value;
onMounted(load);
</script>
<template>
  <div>
    <header class="page-header"><div><div class="eyebrow">Panel personal</div><h1>Hola, {{ data?.user?.name || 'DerNait' }}</h1><p>La siguiente accion importa mas que la sesion perfecta.</p></div><RouterLink class="icon-button" title="Editar perfil" :to="{name:'profile'}"><i class="fa-solid fa-user-gear"/></RouterLink></header>
    <LoadingSkeleton v-if="loading" />
    <template v-else-if="data">
      <section class="surface hero-dashboard">
        <div class="hero-content">
          <div class="d-flex gap-2 flex-wrap"><span class="badge-soft green"><i class="fa-solid fa-bolt"/>Forma activa: {{ game.active_phase?.name || game.activePhase?.name || 'Fase 1 - Iniciado' }}</span><span class="badge-soft blue"><i class="fa-solid fa-trophy"/>Fase maxima: {{ game.maximum_phase?.name || game.maximumPhase?.name || 'Fase 1 - Iniciado' }}</span></div>
          <p v-if="game.energy<70" class="small text-secondary mt-2 mb-0">Tu energia ha bajado por la actividad reciente, pero tus fases desbloqueadas siguen siendo tuyas. Manten la constancia para recuperar tu forma activa.</p>
          <h2>{{ data.today?.day_type === 'training' ? data.today.name : 'Dia de recuperacion' }}</h2>
          <p>{{ data.today?.day_type === 'training' ? `${data.today.exercises?.length || 0} ejercicios · ${data.today.estimated_minutes || 75} min estimados` : 'El descanso planificado tambien sostiene tu progreso. Puedes recuperar otra sesion si lo necesitas.' }}</p>
          <div class="d-flex gap-2 flex-wrap mt-2"><button class="btn btn-primary" @click="train"><i class="fa-solid fa-dumbbell"/>{{ data.active_workout_id?'Continuar entrenamiento':data.today?.day_type==='training'?'Iniciar entrenamiento':'Ver entrenamientos' }}</button><RouterLink class="btn btn-outline-light" :to="{name:'routine'}"><i class="fa-solid fa-list-check"/>Ver rutina</RouterLink></div>
          <div class="mt-4"><div class="d-flex justify-content-between small mb-2"><span>Nivel {{ game.level }}</span><span>{{ game.total_xp }} XP</span></div><div class="progress-track"><span :style="{width:`${xpPercent}%`}"/></div></div>
        </div>
        <div class="hero-avatar"><AvatarSvg :phase="phasePosition" :energy="game.energy" :size="270"/></div>
      </section>

      <section class="grid stats-grid section-block">
        <article class="stat-card energy" :title="data.energy_explanation"><span class="stat-icon"><i class="fa-solid fa-bolt"/></span><strong>{{ game.energy }}</strong><small>Energia reciente</small></article>
        <article class="stat-card power"><span class="stat-icon"><i class="fa-solid fa-fire"/></span><strong>{{ game.combat_power?.toLocaleString() }}</strong><small>Poder de combate</small></article>
        <article class="stat-card streak"><span class="stat-icon"><i class="fa-solid fa-calendar-check"/></span><strong>{{ game.current_weekly_streak }}</strong><small>Semanas de racha</small></article>
        <article class="stat-card"><span class="stat-icon"><i class="fa-solid fa-bullseye"/></span><strong>{{ data.weekly.completed }}/{{ data.weekly.goal }}</strong><small>Meta de esta semana</small></article>
      </section>
      <p class="small text-secondary mt-3 mb-0"><i class="fa-solid fa-circle-info me-2"/>{{ data.energy_explanation }}</p>
      <section class="grid stats-grid section-block">
        <article class="stat-card"><span class="stat-icon"><i class="fa-solid fa-calendar-days"/></span><strong>{{ data.sessions_this_month }}</strong><small>Sesiones este mes</small></article>
        <article class="stat-card"><span class="stat-icon"><i class="fa-solid fa-chart-line"/></span><strong>{{ Number(game.adherence_last_28_days||0).toFixed(0) }}%</strong><small>Adherencia de 28 dias</small></article>
        <article class="stat-card"><span class="stat-icon"><i class="fa-solid fa-weight-scale"/></span><strong>{{ signed(data.body_changes?.weight_kg,'kg') }}</strong><small>Cambio de peso</small></article>
        <article class="stat-card"><span class="stat-icon"><i class="fa-solid fa-ruler"/></span><strong>{{ signed(data.body_changes?.waist_cm,'cm') }}</strong><small>Cambio de cintura</small></article>
      </section>

      <section class="surface panel section-block"><div class="section-heading"><div><h2>{{ data.active_phase?.name || 'Fase de entrenamiento' }}</h2><p v-if="data.active_phase">{{ new Date(`${data.active_phase.starts_on}T12:00:00`).toLocaleDateString('es-GT') }} - {{ new Date(`${data.active_phase.ends_on}T12:00:00`).toLocaleDateString('es-GT') }}</p></div><span class="badge-soft green">{{ data.active_phase?.minimum_target_sessions || 40 }} sesiones meta</span></div><div class="grid stats-grid"><div><small class="text-secondary d-block">Peso reciente</small><strong>{{ data.latest_measurement?.body_weight_kg ?? data.profile.current_body_weight_kg ?? 'Pendiente' }} <small v-if="data.latest_measurement?.body_weight_kg || data.profile.current_body_weight_kg">kg</small></strong></div><div><small class="text-secondary d-block">Cintura reciente</small><strong>{{ data.latest_measurement?.waist_cm ?? data.profile.current_waist_cm ?? 'Pendiente de registrar' }} <small v-if="data.latest_measurement?.waist_cm || data.profile.current_waist_cm">cm</small></strong></div><div><small class="text-secondary d-block">Proteina orientativa</small><strong>{{ data.profile.protein_goal_min_grams }}-{{ data.profile.protein_goal_max_grams }} g</strong></div><div><small class="text-secondary d-block">Sueno orientativo</small><strong>{{ data.profile.sleep_goal_hours }}+ h</strong></div></div></section>

      <section class="grid two-column section-block">
        <div><div class="section-heading"><div><h2>Recomendaciones pendientes</h2><p>Decisiones explicadas por tu rendimiento.</p></div><RouterLink class="icon-button compact" title="Ver progreso" :to="{name:'progress'}"><i class="fa-solid fa-arrow-right"/></RouterLink></div>
          <div v-if="data.pending_recommendations.length" class="recommendation-list"><article v-for="item in data.pending_recommendations" :key="item.id" class="recommendation-row"><div class="d-flex justify-content-between gap-2"><strong>{{ item.exercise.name }}</strong><span class="badge-soft blue">{{ confidenceLabel(item.confidence) }}</span></div><p class="small text-secondary mb-0 mt-2">{{ item.reason }}</p></article></div>
          <EmptyState v-else title="Sin recomendaciones pendientes" text="Las recomendaciones apareceran despues de registrar exposiciones validas." icon="fa-lightbulb" />
        </div>
        <div><div class="section-heading"><div><h2>Ultimos records</h2><p>Tus mejores marcas por ejercicio.</p></div></div>
          <div v-if="data.latest_records.length" class="record-list"><article v-for="record in data.latest_records" :key="record.id" class="record-row"><div class="d-flex align-items-center gap-3"><span class="stat-icon mb-0"><i class="fa-solid fa-trophy"/></span><div><strong>{{ record.exercise.name }}</strong><small class="d-block text-secondary">{{ recordLabel(record.record_type) }} · {{ record.value }} {{ record.weight_unit || '' }}</small></div></div></article></div>
          <EmptyState v-else title="Tu primera marca esta cerca" text="Los calentamientos no cuentan; completa una serie efectiva para comenzar." icon="fa-trophy" />
        </div>
      </section>
    </template>
  </div>
</template>
