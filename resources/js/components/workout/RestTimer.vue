<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
const props=defineProps({defaultSeconds:{type:Number,default:90}});
const remaining=ref(0); const running=ref(false); const hasStarted=ref(false); let interval=null; const key='golden-path-rest-timer';
const display=computed(()=>`${String(Math.floor(remaining.value/60)).padStart(2,'0')}:${String(remaining.value%60).padStart(2,'0')}`);
function persist(){ localStorage.setItem(key,JSON.stringify({remaining:remaining.value,running:running.value,hasStarted:hasStarted.value,updatedAt:Date.now()})); }
function tick(){ if(!running.value)return; if(remaining.value>0){remaining.value--;persist();}else{running.value=false;persist(); if('vibrate'in navigator)navigator.vibrate([150,80,150]); try{new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACAgICAgICAgICAgICA').play();}catch{}} }
function start(seconds=props.defaultSeconds){remaining.value=seconds;running.value=true;hasStarted.value=true;persist();}
function pause(){running.value=false;persist();} function resume(){if(remaining.value>0){running.value=true;persist();}} function add(){remaining.value+=15;persist();} function reset(){start(props.defaultSeconds);} function skip(){remaining.value=0;running.value=false;hasStarted.value=false;persist();}
onMounted(()=>{const saved=JSON.parse(localStorage.getItem(key)||'null'); if(saved){const elapsed=saved.running?Math.floor((Date.now()-saved.updatedAt)/1000):0;remaining.value=Math.max(0,saved.remaining-elapsed);running.value=saved.running&&remaining.value>0;hasStarted.value=Boolean(saved.hasStarted);} interval=setInterval(tick,1000);}); onBeforeUnmount(()=>clearInterval(interval));
defineExpose({start});
</script>
<template>
  <div class="timer-panel" :class="{'timer-finished':hasStarted&&remaining===0&&!running}"><div><small class="text-secondary d-block">Descanso</small><div class="timer-display" :class="{'text-warning':remaining<=15&&remaining>0}">{{ display }}</div></div><div class="timer-controls"><button class="icon-button compact" :title="running?'Pausar':'Reanudar'" @click="running?pause():resume()"><i class="fa-solid" :class="running?'fa-pause':'fa-play'"/></button><button class="icon-button compact" title="Anadir 15 segundos" @click="add"><i class="fa-solid fa-plus"/></button><button class="icon-button compact" title="Reiniciar" @click="reset"><i class="fa-solid fa-rotate-right"/></button><button class="icon-button compact" title="Omitir" @click="skip"><i class="fa-solid fa-forward"/></button></div></div>
</template>
