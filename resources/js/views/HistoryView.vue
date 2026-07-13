<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../api/client';
import EmptyState from '../components/common/EmptyState.vue';
import LoadingSkeleton from '../components/common/LoadingSkeleton.vue';
import { useNotificationStore } from '../stores/notifications';
import { errorMessage } from '../utils/errors';
const notifications=useNotificationStore(); const sessions=ref([]); const meta=ref(null); const loading=ref(true); const filters=reactive({from:'',to:'',status:'',name:''});
const labels={completed:'Completado',partial:'Parcial',cancelled:'Cancelado',in_progress:'En curso'};
function duration(seconds){if(!seconds)return '-';return `${Math.floor(seconds/60)} min`;}
async function load(page=1){loading.value=true;try{const response=(await api.get('/workouts',{params:{...filters,page}})).data;sessions.value=response.data;meta.value=response;}catch(e){notifications.push(errorMessage(e),'error');}finally{loading.value=false;}}
onMounted(()=>load());
</script>
<template>
  <div><header class="page-header"><div><div class="eyebrow">Registro permanente</div><h1>Historial</h1><p>Cada sesion conserva la rutina y los ejercicios tal como se realizaron.</p></div></header>
    <section class="surface panel mb-4"><form class="field-grid" @submit.prevent="load(1)"><div><label class="form-label">Desde</label><input v-model="filters.from" class="form-control" type="date"/></div><div><label class="form-label">Hasta</label><input v-model="filters.to" class="form-control" type="date"/></div><div><label class="form-label">Tipo de entrenamiento</label><input v-model.trim="filters.name" class="form-control" type="search" placeholder="Upper, Lower..."/></div><div><label class="form-label">Estado</label><select v-model="filters.status" class="form-select"><option value="">Todos</option><option value="completed">Completado</option><option value="partial">Parcial</option><option value="cancelled">Cancelado</option></select></div><div class="d-flex align-items-end"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter"/>Filtrar</button></div></form></section>
    <LoadingSkeleton v-if="loading"/><div v-else-if="sessions.length" class="history-list"><RouterLink v-for="item in sessions" :key="item.id" :to="{name:'history-detail',params:{id:item.id}}" class="history-row"><span class="stat-icon mb-0"><i class="fa-solid" :class="item.status==='completed'?'fa-circle-check':item.status==='partial'?'fa-circle-half-stroke':'fa-circle-xmark'"/></span><div class="flex-grow-1 min-width-0"><strong class="d-block">{{ item.name }}</strong><small>{{ new Date(item.started_at).toLocaleDateString('es-GT',{weekday:'short',day:'numeric',month:'short',year:'numeric'}) }} · {{ duration(item.duration_seconds) }}</small></div><div class="text-end"><span class="badge-soft" :class="item.status==='completed'?'green':item.status==='partial'?'yellow':'red'">{{ labels[item.status]||item.status }}</span><small class="d-block mt-1">{{ item.working_sets_count||0 }} series · {{ Number(item.total_volume||0).toFixed(0) }} vol.</small><small class="d-block mt-1"><i class="fa-solid fa-trophy me-1"/>{{ item.records_count||0 }} · <i class="fa-solid fa-bolt mx-1"/>{{ item.xp_earned||0 }} XP</small></div><i class="fa-solid fa-chevron-right text-secondary"/></RouterLink></div><EmptyState v-else title="Aun no hay sesiones" text="Tu primer entrenamiento aparecera aqui con sus series, sustituciones y resumen." icon="fa-clock-rotate-left"/>
    <nav v-if="meta?.last_page>1" class="d-flex justify-content-center gap-2 mt-4"><button class="btn btn-outline-light" :disabled="meta.current_page===1" @click="load(meta.current_page-1)"><i class="fa-solid fa-arrow-left"/>Anterior</button><button class="btn btn-outline-light" :disabled="meta.current_page===meta.last_page" @click="load(meta.current_page+1)">Siguiente<i class="fa-solid fa-arrow-right"/></button></nav>
  </div>
</template>
