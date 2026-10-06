<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const email = ref('');
const password = ref('');
const loading = ref(false);

async function submit() {
  loading.value = true;
  const ok = await auth.login(email.value, password.value);
  loading.value = false;
  if (ok) router.push('/');
}
</script>

<template>
  <v-container class="fill-height" fluid>
    <v-row justify="center" align="center">
      <v-col cols="12" sm="8" md="4">
        <v-card class="pa-4">
          <v-card-title>Вхід</v-card-title>
          <v-card-text>
            <v-alert v-if="auth.error" type="error" class="mb-4" density="compact">{{ auth.error }}</v-alert>
            <v-form @submit.prevent="submit">
              <v-text-field v-model="email" label="Email" type="email" autocomplete="username" />
              <v-text-field v-model="password" label="Пароль" type="password" autocomplete="current-password" />
              <v-btn type="submit" color="primary" block :loading="loading">Увійти</v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>
