<script setup>
import { onBeforeUnmount, watch } from 'vue';
const props = defineProps({ open: Boolean, title: { type: String, required: true }, size: { type: String, default: '' } });
const emit = defineEmits(['close']);
const onKey = (event) => { if (event.key === 'Escape') emit('close'); };
watch(() => props.open, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) document.addEventListener('keydown', onKey); else document.removeEventListener('keydown', onKey);
}, { immediate: true });
onBeforeUnmount(() => { document.body.style.overflow = ''; document.removeEventListener('keydown', onKey); });
</script>
<template>
  <Teleport to="body">
    <div v-if="open" class="modal-backdrop-custom" @click.self="$emit('close')">
      <section class="modal-panel" :class="size" role="dialog" aria-modal="true" :aria-label="title">
        <header><h2>{{ title }}</h2><button class="icon-button" title="Cerrar" aria-label="Cerrar" @click="$emit('close')"><i class="fa-solid fa-xmark" /></button></header>
        <div class="modal-body-custom"><slot /></div>
        <footer v-if="$slots.footer"><slot name="footer" /></footer>
      </section>
    </div>
  </Teleport>
</template>
