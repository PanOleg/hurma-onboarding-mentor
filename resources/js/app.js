import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createVuetify } from 'vuetify';
import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import App from './App.vue';
import { router } from './router.js';

createApp(App).use(createPinia()).use(router).use(createVuetify()).mount('#app');
