import axios from 'axios';

export const http = axios.create({ baseURL: '/api/v1', withCredentials: true, withXSRFToken: true, headers: { Accept: 'application/json' } });

export async function csrf() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export function errorMessage(err) {
  return err?.response?.data?.error?.message ?? 'Щось пішло не так.';
}
