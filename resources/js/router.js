import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth.js';
import LoginView from './views/LoginView.vue';
import ChatView from './views/ChatView.vue';

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView },
    { path: '/', name: 'chat', component: ChatView },
  ],
});

let meLoaded = false;

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!meLoaded) {
    await auth.fetchMe();
    meLoaded = true;
  }
  if (to.name !== 'login' && !auth.user) return { name: 'login' };
  if (to.name === 'login' && auth.user) return { name: 'chat' };
});
