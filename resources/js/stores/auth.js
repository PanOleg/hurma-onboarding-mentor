import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http, csrf, errorMessage } from '../api.js';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const error = ref(null);

  async function fetchMe() {
    try { user.value = (await http.get('/me')).data.data; } catch { user.value = null; }
    return user.value;
  }
  async function login(email, password) {
    error.value = null;
    try {
      await csrf();
      await http.post('/auth/login', { email, password });
      await fetchMe();
      return true;
    } catch (e) { error.value = errorMessage(e); return false; }
  }
  async function logout() { await http.post('/auth/logout'); user.value = null; }

  return { user, error, fetchMe, login, logout };
});
