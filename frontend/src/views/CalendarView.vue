<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { useRouter } from 'vue-router';
import useApi from '../use/api';
import type { ContractCalendar } from '../types/api/ContractCalendar';
import FullCalendar, {
  type CalendarOptions,
  type EventSourceFuncInfo,
  type EventInput,
} from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/vue3/daygrid';
import listPlugin from '@fullcalendar/vue3/list';
import themePlugin from '@fullcalendar/vue3/themes/classic';
import ruLocale from '@fullcalendar/vue3/locales/ru';
import '@fullcalendar/vue3/skeleton.css';
import '@fullcalendar/vue3/themes/classic/theme.css';
import '@fullcalendar/vue3/themes/classic/palette.css';

const router = useRouter();
const { api, errorText } = useApi();
const error = ref('');
const calendar = ref<InstanceType<typeof FullCalendar> | null>(null);
let controller: AbortController | undefined;

async function events(info: EventSourceFuncInfo): Promise<EventInput[]> {
  controller?.abort();
  const current = new AbortController();

  controller = current;
  error.value = '';

  try {
    const query = new URLSearchParams({
      start: info.startStr.slice(0, 10),
      end: info.endStr.slice(0, 10),
    });
    const results = await Promise.all([
      api<ContractCalendar>('/api/calendar/meters?' + query, { signal: current.signal }),
      api<ContractCalendar>('/api/calendar/contracts?' + query, { signal: current.signal }),
      api<ContractCalendar>('/api/notifications/calendar?' + query, { signal: current.signal }),
    ]);

    return current.signal.aborted
      ? []
      : results
          .flatMap((result) => result.items)
          .map((event) => ({
            ...event,
            url: event.url ? router.resolve(event.url.replace(/^#/, '')).href : undefined,
          }));
  } catch (e) {
    if (!current.signal.aborted) error.value = errorText(e);

    return [];
  }
}

function retry() {
  calendar.value?.getApi().refetchEvents();
}

onBeforeUnmount(() => controller?.abort());

const calendarOptions: CalendarOptions = {
  plugins: [themePlugin, dayGridPlugin, listPlugin],
  locale: ruLocale,
  initialView: 'dayGridMonth',
  height: 'auto',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,listMonth',
  },
  listText: 'Список',
  noEventsText: 'Событий пока нет',
  events,
};
</script>

<template>
  <div class="page-heading">
    <div>
      <h1>
        Календарь
      </h1>
      <p>
        Сроки договоров, показаний, поверок и личные уведомления.
      </p>
    </div>
  </div>
  <div
    v-if="error"
    class="alert alert-danger"
    role="alert"
  >
    {{ error }}
    <button
      class="btn ms-2"
      @click="retry"
    >
      Повторить
    </button>
  </div>
  <div
    class="d-flex flex-wrap gap-3 mb-3"
    aria-label="Статусы уведомлений"
  >
    <span class="d-inline-flex align-items-center gap-2">
      <span
        class="status-dot bg-blue"
        aria-hidden="true"
      >
      </span>
      Новое уведомление
    </span>
    <span class="d-inline-flex align-items-center gap-2">
      <span
        class="status-dot bg-green"
        aria-hidden="true"
      >
      </span>
      Прочитано / исполнено
    </span>
  </div>
  <p class="text-secondary">
    Показания: оранжевый — ожидаются, зелёный — переданы вовремя, красный — переданы с просрочкой.
    Поверка — красный.
  </p>
  <section class="card calendar-surface">
    <FullCalendar
      ref="calendar"
      :options="calendarOptions"
    />
  </section>
</template>
