<script setup lang="ts">
import { IconBuildingEstate } from '@tabler/icons-vue';
import type { AuthCardProps } from '../types/components/AuthCard';
defineProps<AuthCardProps>();
</script>

<template>
  <main class="auth-page">
    <div class="auth-container">
      <div class="workspace-brand justify-content-center">
        <span class="brand-symbol">
          <IconBuildingEstate :size="25" />
        </span>
        <span>
          Land Lord
          <span class="brand-service">
            Service
          </span>
        </span>
      </div>
      <section class="card">
        <div class="card-body p-4">
          <h1 class="h2 mb-3">
            {{ title }}
          </h1>
          <p
            v-if="description"
            class="text-secondary mb-4"
          >
            {{ description }}
          </p>
          <slot />
        </div>
      </section>
      <p class="text-secondary text-center mt-3 small">
        Управление арендой и эксплуатацией
        <br />
        <a
          href="./help/users.html"
          target="_blank"
          rel="noopener"
        >
          Инструкция пользователя
        </a>
        <a
          class="text-success ms-2"
          href="./server/api"
          target="_blank"
          rel="noopener"
        >
          API v0.1
        </a>
      </p>
    </div>
  </main>
</template>
