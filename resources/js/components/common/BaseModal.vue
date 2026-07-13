<script setup>
defineProps({ open: Boolean, title: { type: String, required: true }, size: { type: String, default: '' } });
defineEmits(['close']);
</script>
<template>
  <Teleport to="body">
    <div v-if="open" class="modal-backdrop-custom" @click.self="$emit('close')">
      <section class="modal-panel" :class="size" role="dialog" aria-modal="true" :aria-label="title">
        <header><h2>{{ title }}</h2><button class="icon-button" title="Cerrar" @click="$emit('close')"><i class="fa-solid fa-xmark" /></button></header>
        <div class="modal-body-custom"><slot /></div>
        <footer v-if="$slots.footer"><slot name="footer" /></footer>
      </section>
    </div>
  </Teleport>
</template>
