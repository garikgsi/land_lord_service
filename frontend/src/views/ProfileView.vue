<script setup lang="ts">
import Badge from '../components/ui/Badge.vue';
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { useRolesStore } from '../store/roles';
import UserAvatar from '../components/UserAvatar.vue';

const auth = useAuthStore();
const roles = useRolesStore();
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        Мой профиль
      </h1>
    </div>
  </div>
  <section
    v-if="auth.user"
    class="card"
  >
    <div class="card-body">
      <div class="d-flex align-items-center gap-3 mb-4">
        <UserAvatar
          :url="auth.user.avatarUrl"
          :first-name="auth.user.firstName"
          :last-name="auth.user.lastName"
        />
        <strong class="text-break">
          {{ auth.user.email }}
        </strong>
      </div>
      <dl class="row mb-0">
        <dt class="col-sm-3 mb-2">
          Имя
        </dt>
        <dd class="col-sm-9">
          {{ auth.user.firstName || 'Не указано' }}
        </dd>
        <dt class="col-sm-3 mb-2">
          Фамилия
        </dt>
        <dd class="col-sm-9">
          {{ auth.user.lastName || 'Не указана' }}
        </dd>
        <dt class="col-sm-3 mb-2">
          Телефон
        </dt>
        <dd class="col-sm-9">
          {{ auth.user.phone || 'Не указан' }}
        </dd>
        <dt class="col-sm-3 mb-2">
          Должность
        </dt>
        <dd class="col-sm-9">
          {{ auth.user.position || 'Не указана' }}
        </dd>
        <dt class="col-sm-3 mb-2">
          Роли
        </dt>
        <dd class="col-sm-9">
          <Badge
            v-for="role in auth.user.roles"
            :key="role"
            class="me-2 mb-2"
            :color="roles.colors[role]"
          >
            {{ roles.labels[role] ?? role }}
          </Badge>
          <span v-if="!auth.user.roles.length">
            Не назначены
          </span>
        </dd>
      </dl>
      <RouterLink
        v-if="auth.isAdmin"
        :to="'/admin/users/' + auth.user.id"
        class="btn btn-primary mt-3"
      >
        Изменить свой профиль
      </RouterLink>
      <p
        v-else
        class="text-secondary mt-3 mb-0"
      >
        Для изменения данных обратитесь к администратору системы.
      </p>
    </div>
  </section>
</template>
