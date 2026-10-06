import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http, errorMessage } from '../api.js';
import { streamMessage } from '../sse.js';

export const useChatStore = defineStore('chat', () => {
  const conversations = ref([]);
  const current = ref(null);
  const messages = ref([]);
  const streaming = ref(false);
  const error = ref(null);

  async function loadConversations() {
    conversations.value = (await http.get('/conversations')).data.data;
  }
  async function openConversation(id) {
    current.value = conversations.value.find((c) => c.id === id) ?? { id };
    messages.value = (await http.get(`/conversations/${id}/messages`)).data.data;
  }
  async function newConversation() {
    const { data } = await http.post('/conversations');
    current.value = data.data;
    conversations.value.unshift(data.data);
    messages.value = [];
  }
  async function send(content) {
    if (!current.value) await newConversation();
    error.value = null;
    streaming.value = true;
    messages.value.push({ role: 'user', content, status: 'completed', citations: [] });
    messages.value.push({ id: null, role: 'assistant', content: '', status: 'streaming', citations: [], grounded: null });
    // Mutate the reactive proxy (not the plain object) so token updates re-render.
    const assistant = messages.value[messages.value.length - 1];
    try {
      await streamMessage(current.value.id, content, {
        onEvent: ({ event, data }) => {
          if (event === 'message') assistant.id = data.id;
          else if (event === 'token') assistant.content += data.text;
          else if (event === 'citations') assistant.citations = data;
          else if (event === 'done') { assistant.status = data.status; assistant.grounded = data.grounded; }
          else if (event === 'error') { assistant.status = 'failed'; error.value = data.message; }
        },
      });
      if (assistant.status === 'streaming') {
        assistant.status = 'failed';
        error.value = 'З’єднання обірвалося, відповідь не завершена.';
      }
    } catch (e) {
      assistant.status = 'failed';
      error.value = errorMessage(e);
    } finally {
      streaming.value = false;
    }
  }

  return { conversations, current, messages, streaming, error, loadConversations, openConversation, newConversation, send };
});
