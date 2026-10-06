<script setup>
import { ref, watch } from 'vue';
import { http, errorMessage } from '../api.js';

const props = defineProps({ citation: { type: Object, default: null } });

const content = ref(null);
const loading = ref(false);
const error = ref(null);

watch(() => props.citation, () => { content.value = null; error.value = null; });

async function openChunk() {
  loading.value = true;
  error.value = null;
  try {
    content.value = (await http.get(`/chunks/${props.citation.chunk_id}`)).data.data.content;
  } catch (e) {
    error.value = errorMessage(e);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <v-card v-if="citation" variant="outlined" class="ma-2">
    <v-card-title class="text-subtitle-1">{{ citation.document_title }}</v-card-title>
    <v-card-subtitle v-if="citation.page">Сторінка {{ citation.page }}</v-card-subtitle>
    <v-card-text>
      <blockquote>{{ citation.quote }}</blockquote>
      <v-alert v-if="error" type="error" density="compact" class="mt-2">{{ error }}</v-alert>
      <div v-if="content" class="mt-3" style="white-space: pre-wrap">{{ content }}</div>
    </v-card-text>
    <v-card-actions>
      <v-btn :loading="loading" @click="openChunk">Відкрити фрагмент</v-btn>
    </v-card-actions>
  </v-card>
  <div v-else class="pa-4 text-grey">Оберіть цитату, щоб побачити джерело.</div>
</template>
