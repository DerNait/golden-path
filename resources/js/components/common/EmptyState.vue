<script setup>defineProps({ icon:{type:String,default:'fa-inbox'}, title:String, text:String });</script>
<template><div class="empty-state"><i class="fa-solid" :class="icon" /><strong>{{ title }}</strong><p>{{ text }}</p><slot /></div></template>
