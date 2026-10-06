<script setup>
import { computed } from 'vue';

const props = defineProps({ message: { type: Object, required: true } });
const emit = defineEmits(['cite']);

const isUser = computed(() => props.message.role === 'user');

// Splits content into text and [n] marker parts; a marker is clickable only when a matching citation exists.
const parts = computed(() => {
  const content = props.message.content ?? '';
  const citations = props.message.citations ?? [];
  return content.split(/(\[\d+\])/).filter((s) => s !== '').map((s) => {
    const m = s.match(/^\[(\d+)\]$/);
    if (!m) return { text: s };
    const citation = citations.find((c) => c.marker === Number(m[1]));
    return citation ? { marker: m[1], citation } : { text: s };
  });
});
</script>

<template>
  <div class="d-flex mb-3" :class="isUser ? 'justify-end' : 'justify-start'">
    <v-sheet :color="isUser ? 'primary' : 'grey-lighten-4'" rounded="lg" class="pa-3" max-width="80%">
      <div style="white-space: pre-wrap">
        <template v-for="(p, i) in parts" :key="i">
          <v-chip v-if="p.marker" size="x-small" color="primary" class="mx-1" @click="emit('cite', p.citation)">{{ p.marker }}</v-chip>
          <span v-else>{{ p.text }}</span>
        </template>
      </div>
      <div v-if="!isUser" class="mt-2">
        <span v-if="message.status === 'no_answer'" class="text-grey text-caption">Передано HR</span>
        <v-chip v-if="message.grounded === false" size="x-small" color="warning">Потребує перевірки</v-chip>
        <v-icon v-if="message.status === 'failed'" color="error" icon="mdi-alert-circle" />
      </div>
    </v-sheet>
  </div>
</template>
