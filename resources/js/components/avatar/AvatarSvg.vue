<script setup>
import { computed } from 'vue';
const props=defineProps({ phase:{type:Number,default:1}, energy:{type:Number,default:100}, size:{type:Number,default:240} });
const colors=['#60a5fa','#4ade80','#fbbf24','#a78bfa','#f87171'];
const color=computed(()=>colors[Math.max(0,Math.min(4,props.phase-1))]);
const auraOpacity=computed(()=>Math.max(.12,props.energy/140));
const posture=computed(()=>['','rotate(1 120 160)','rotate(-1 120 160)','rotate(-3 120 160)','translate(0 -3)'][props.phase-1]||'');
const hairPath=computed(()=>[
  'M80 103c-2-35 13-59 40-59 25 0 43 22 40 59-9-8-17-18-22-31-16 17-35 23-58 31z',
  'M84 75L73 45l28 14 4-34 17 25 20-30 3 37 27-17-14 37c-25-15-49-16-74-2z',
  'M82 76L62 47l34 10 1-40 24 31 24-38-2 45 36-22-20 46c-25-17-52-18-77-3z',
  'M80 78L58 40l37 15-3-45 29 37 27-43-5 51 41-26-24 51c-26-18-54-19-80-2z',
  'M78 80L51 35l43 18-5-50 32 43 31-46-7 54 46-29-30 57c-27-19-56-20-83-2z',
][props.phase-1]);
const clothing=computed(()=>['#27364b','#244033','#4a3d22','#352c54','#542d35'][props.phase-1]);
</script>
<template>
  <svg class="avatar-svg" :width="size" :height="size" viewBox="0 0 240 240" role="img" :aria-label="`Avatar de disciplina, fase ${phase}`">
    <defs>
      <radialGradient id="aura"><stop offset="0" :stop-color="color" stop-opacity=".48"/><stop offset=".7" :stop-color="color" stop-opacity=".12"/><stop offset="1" :stop-color="color" stop-opacity="0"/></radialGradient>
      <filter id="eye-glow"><feGaussianBlur stdDeviation="2" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
    </defs>
    <circle cx="120" cy="120" r="112" fill="url(#aura)" :opacity="auraOpacity" class="avatar-aura"/>
    <circle v-if="phase>=3" cx="120" cy="120" :r="88+phase*3" fill="none" :stroke="color" :stroke-width="phase===5?3:1.5" opacity=".35" class="avatar-aura-ring"/>
    <path v-if="phase>=4" d="M27 154Q53 115 30 75M213 154q-26-39-3-79" fill="none" :stroke="color" stroke-width="3" opacity=".55"/>
    <g class="avatar-particles" :fill="color" v-if="phase>1"><circle cx="44" cy="74" r="3"/><circle cx="196" cy="92" r="2"/><circle v-if="phase>2" cx="60" cy="176" r="2"/><circle v-if="phase>2" cx="181" cy="55" r="3"/><circle v-if="phase>3" cx="182" cy="164" r="4"/><circle v-if="phase>3" cx="34" cy="127" r="2"/><circle v-if="phase===5" cx="207" cy="128" r="3"/><circle v-if="phase===5" cx="91" cy="25" r="2"/></g>
    <ellipse cx="120" cy="215" :rx="62+phase*2" ry="10" fill="#05070a" opacity=".65"/>
    <g :transform="posture">
      <path :d="phase>=4?'M62 207c6-45 23-69 58-69s53 24 59 69z':'M72 206c4-43 18-67 48-67s45 24 49 67z'" fill="#182333" :stroke="color" :stroke-width="phase>=3?4:3"/>
      <path d="M87 155l33 30 34-30 9 18-43 36-43-36z" :fill="clothing"/>
      <path v-if="phase>=3" d="M72 171l19-17 9 15-23 17M168 171l-19-17-9 15 23 17" :fill="color" opacity=".72"/>
      <path v-if="phase>=4" d="M83 196h74" :stroke="color" stroke-width="5" stroke-linecap="round"/>
      <path d="M94 141c-9-8-14-21-13-39 1-28 17-48 39-48s38 20 39 48c1 18-4 31-13 39-8 8-17 12-26 12s-18-4-26-12z" fill="#d6a47f"/>
      <path :d="hairPath" :fill="phase>=2?'#182333':'#141b27'" :stroke="phase>=2?color:'none'" :stroke-width="phase>=2?2:0"/>
      <path v-if="phase<=2" d="M98 111l12-3M142 111l-12-3" stroke="#17202c" stroke-width="4" stroke-linecap="round"/>
      <path v-else-if="phase===3" d="M97 109l13 1M143 109l-13 1" stroke="#17202c" stroke-width="4" stroke-linecap="round"/>
      <g v-else :fill="color" filter="url(#eye-glow)"><ellipse cx="104" cy="110" rx="6" ry="3"/><ellipse cx="136" cy="110" rx="6" ry="3"/></g>
      <path :d="phase===1?'M109 132q11 8 22 0':phase<4?'M108 133q12 4 24 0':'M109 132h22'" fill="none" stroke="#754d43" stroke-width="3" stroke-linecap="round"/>
      <path v-if="phase>=2" d="M78 184l12 6-8 12-12-8z" :fill="color" opacity=".8"/>
      <path v-if="phase>2" d="M75 187l-18-23M165 188l18-24" :stroke="color" :stroke-width="phase===5?7:5" stroke-linecap="round" class="avatar-energy-line"/>
      <circle v-if="phase===5" cx="120" cy="191" r="8" :fill="color" class="avatar-core"/>
    </g>
  </svg>
</template>
