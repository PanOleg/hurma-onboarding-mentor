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

  it('parses CRLF-framed input', () => {
    const { events, rest } = parseSseChunk('event: token\r\ndata: {"text":"a"}\r\n\r\n');
    expect(events).toEqual([{ event: 'token', data: { text: 'a' } }]);
    expect(rest).toBe('');
  });

  it('reassembles a frame split across two chunks', () => {
    const first = parseSseChunk('event: token\ndata: {"te');
    expect(first.events).toEqual([]);
    const second = parseSseChunk(first.rest + 'xt":"b"}\n\n');
    expect(second.events).toEqual([{ event: 'token', data: { text: 'b' } }]);
    expect(second.rest).toBe('');
  });
});
