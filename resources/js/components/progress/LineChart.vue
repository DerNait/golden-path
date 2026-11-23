<script setup>
import { Chart } from 'chart.js/auto';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatDay } from '../../utils/dates';

const props = defineProps({
  labels: { type: Array, default: () => [] },
  datasets: { type: Array, default: () => [] },
  emptyText: { type: String, default: 'Aún no hay datos suficientes.' },
});
const canvas = ref(null);
let chart = null;

// Paleta del sistema: oro, marfil, bronce y verde salvia (distinguibles también por trazo).
const colors = ['#d4a84b', '#efe8d8', '#c98a5b', '#8fbf8a'];
const dashes = [[], [], [6, 4], [2, 4]];
const muted = '#a39a88';
const grid = '#262219';
const shortDate = (value) => formatDay(value, { day: 'numeric', month: 'short' });

function scale(position = 'left', extra = {}) {
  return { position, ticks: { color: muted, font: { family: 'Barlow' } }, grid: { color: grid }, border: { display: false }, beginAtZero: false, ...extra };
}

function render() {
  chart?.destroy();
  chart = null;
  if (!props.labels.length) return;
  nextTick(() => {
    if (!canvas.value) return;
    const secondAxis = props.datasets.some((item) => item.yAxisID === 'y1');
    chart = new Chart(canvas.value, {
      type: 'line',
      data: {
        labels: props.labels,
        datasets: props.datasets.map((item, index) => ({
          borderColor: colors[index % colors.length],
          backgroundColor: colors[index % colors.length],
          borderDash: dashes[index % dashes.length],
          borderWidth: 2,
          pointRadius: 3,
          pointHoverRadius: 5,
          tension: 0.25,
          ...item,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { align: 'start', labels: { color: muted, usePointStyle: true, boxWidth: 8, boxHeight: 8, font: { family: 'Barlow', size: 13 } } },
          tooltip: { backgroundColor: '#1c1915', borderColor: '#42392b', borderWidth: 1, titleColor: '#f2ebdd', bodyColor: '#f2ebdd', titleFont: { family: 'Barlow' }, bodyFont: { family: 'Barlow' }, callbacks: { title: (items) => shortDate(items[0]?.label) } },
        },
        scales: {
          x: { ticks: { color: muted, maxTicksLimit: 6, maxRotation: 0, font: { family: 'Barlow' }, callback(value) { return shortDate(this.getLabelForValue(value)); } }, grid: { display: false }, border: { color: '#42392b' } },
          y: scale('left'),
          ...(secondAxis ? { y1: scale('right', { grid: { drawOnChartArea: false } }) } : {}),
        },
      },
    });
  });
}

watch(() => [props.labels, props.datasets], render, { deep: true });
onMounted(render);
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
  <div class="chart-wrap">
    <canvas v-if="labels.length" ref="canvas" role="img" aria-label="Gráfica de tendencia" />
    <div v-else class="empty-state"><i class="fa-solid fa-chart-line" /><strong>{{ emptyText }}</strong></div>
  </div>
</template>
