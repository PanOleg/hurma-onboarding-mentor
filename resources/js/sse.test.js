import { describe, it, expect } from 'vitest';
import { parseSseChunk } from './sse.js';

describe('parseSseChunk', () => {
  it('parses complete frames and keeps the incomplete tail', () => {
    const input = 'event: message\ndata: {"id":1}\n\nevent: token\ndata: {"text":"Прив"}\n\nevent: tok';
    const { events, rest } = parseSseChunk(input);
    expect(events).toEqual([{ event: 'message', data: { id: 1 } }, { event: 'token', data: { text: 'Прив' } }]);
    expect(rest).toBe('event: tok');
  });

  it('returns no events for an empty buffer', () => {
    expect(parseSseChunk('')).toEqual({ events: [], rest: '' });
  });
});
