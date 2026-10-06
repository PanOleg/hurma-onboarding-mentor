import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('../api.js', () => ({ http: { get: vi.fn(), post: vi.fn() }, errorMessage: (e) => e.message }));
vi.mock('../sse.js', () => ({ streamMessage: vi.fn() }));

import { http } from '../api.js';
import { streamMessage } from '../sse.js';
import { useChatStore } from './chat.js';

beforeEach(() => setActivePinia(createPinia()));

describe('chat store send', () => {
  it('appends user message, streams assistant text, then citations and done', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 7, title: null } } });
    streamMessage.mockImplementation(async (_id, _content, { onEvent }) => {
      onEvent({ event: 'message', data: { id: 42, conversation_id: 7 } });
      onEvent({ event: 'token', data: { text: 'Так ' } });
      onEvent({ event: 'token', data: { text: '[1].' } });
      onEvent({ event: 'citations', data: [{ marker: 1, chunk_id: 5, document_title: 'Док', page: 2, quote: 'цитата' }] });
      onEvent({ event: 'done', data: { status: 'completed', grounded: true } });
    });
    const store = useChatStore();
    await store.newConversation();
    await store.send('Питання?');

    expect(store.messages.map((m) => m.role)).toEqual(['user', 'assistant']);
    expect(store.messages[1].content).toBe('Так [1].');
    expect(store.messages[1].citations[0].document_title).toBe('Док');
    expect(store.messages[1].status).toBe('completed');
    expect(store.streaming).toBe(false);
  });

  it('marks the assistant message failed on error event', async () => {
    http.post.mockResolvedValue({ data: { data: { id: 7 } } });
    streamMessage.mockImplementation(async (_i, _c, { onEvent }) => {
      onEvent({ event: 'message', data: { id: 1 } });
      onEvent({ event: 'error', data: { code: 'llm_unavailable', message: 'Сервіс недоступний' } });
    });
    const store = useChatStore();
    await store.newConversation();
    await store.send('x');

    expect(store.messages[1].status).toBe('failed');
    expect(store.error).toBe('Сервіс недоступний');
  });
});
