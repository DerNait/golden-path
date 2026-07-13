<script setup>
import { Chart } from 'chart.js/auto';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
const props=defineProps({labels:{type:Array,default:()=>[]},datasets:{type:Array,default:()=>[]},emptyText:{type:String,default:'Aun no hay datos suficientes.'}}); const canvas=ref(null); let chart=null;
function render(){chart?.destroy(); if(!props.labels.length)return; nextTick(()=>{if(!canvas.value)return; chart=new Chart(canvas.value,{type:'line',data:{labels:props.labels,datasets:props.datasets.map((item,index)=>({borderColor:['#4ade80','#60a5fa','#fbbf24','#a78bfa'][index%4],backgroundColor:'transparent',pointRadius:3,tension:.25,...item}))},options:{responsive:true,maintainAspectRatio:false,interaction:{intersect:false,mode:'index'},plugins:{legend:{labels:{color:'#91a0b3'}}},scales:{x:{ticks:{color:'#91a0b3',maxTicksLimit:8},grid:{color:'#202a36'}},y:{ticks:{color:'#91a0b3'},grid:{color:'#202a36'},beginAtZero:false}}}});});}
watch(()=>[props.labels,props.datasets],render,{deep:true}); onMounted(render); onBeforeUnmount(()=>chart?.destroy());
</script>
<template><div class="chart-wrap"><canvas v-if="labels.length" ref="canvas"/><div v-else class="empty-state"><i class="fa-solid fa-chart-line"/><strong>{{ emptyText }}</strong></div></div></template>
