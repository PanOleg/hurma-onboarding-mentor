<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useChatStore } from '../stores/chat.js';
import MessageBubble from '../components/MessageBubble.vue';
import CitationPanel from '../components/CitationPanel.vue';

const auth = useAuthStore();
const chat = useChatStore();
const router = useRouter();
const draft = ref('');
const selectedCitation = ref(null);

onMounted(() => chat.loadConversations());

async function submit() {
  const text = draft.value.trim();
  if (!text || chat.streaming) return;
  draft.value = '';
  await chat.send(text);
}

function onEnter(e) {
  if (!e.shiftKey) {
    e.preventDefault();
    submit();
  }
}

async function open(id) {
  selectedCitation.value = null;
  await chat.openConversation(id);
}

async function create() {
  selectedCitation.value = null;
  await chat.newConversation();
}

async function logout() {
  await auth.logout();
  router.push('/login');
}
</script>

<template>
  <v-navigation-drawer permanent width="280">
    <v-list>
      <v-list-item>
        <v-btn block color="primary" @click="create">Нова розмова</v-btn>
      </v-list-item>
      <v-list-item
        v-for="c in chat.conversations"
        :key="c.id"
        :active="chat.current?.id === c.id"
        :title="c.title ?? `Розмова #${c.id}`"
        @click="open(c.id)"
      />
    </v-list>
  </v-navigation-drawer>

  <v-app-bar flat border>
    <v-app-bar-title>Ментор онбордингу</v-app-bar-title>
    <span class="mr-2">{{ auth.user?.name }}</span>
    <v-btn @click="logout">Вийти</v-btn>
  </v-app-bar>

  <v-navigation-drawer location="right" permanent width="320">
    <CitationPanel :citation="selectedCitation" />
  </v-navigation-drawer>

  <v-container class="d-flex flex-column" style="height: calc(100vh - 64px)">
    <div class="flex-grow-1 overflow-y-auto">
      <MessageBubble v-for="(m, i) in chat.messages" :key="m.id ?? `t${i}`" :message="m" @cite="selectedCitation = $event" />
    </div>
    <v-alert v-if="chat.error" type="error" density="compact" class="my-2">{{ chat.error }}</v-alert>
    <div class="d-flex align-start ga-2">
      <v-textarea
        v-model="draft"
        label="Ваше питання"
        rows="2"
        auto-grow
        hide-details
        @keydown.enter="onEnter"
      />
      <v-btn color="primary" :disabled="chat.streaming" @click="submit">Надіслати</v-btn>
    </div>
  </v-container>
</template>
