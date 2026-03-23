<template>
  <ion-app>
    <ion-router-outlet />
  </ion-app>
</template>

<script setup lang="ts">
import { IonApp, IonRouterOutlet } from '@ionic/vue'
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { setupEcho } from '@/services/api'

const authStore = useAuthStore()

onMounted(async () => {
  await authStore.fetchMe()
  if (authStore.token) {
    setupEcho(authStore.token)
  }
})
</script>
