<script setup lang="ts">
import FullCalendar, { type CalendarOptions } from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/vue3/daygrid';
import listPlugin from '@fullcalendar/vue3/list';
import themePlugin from '@fullcalendar/vue3/themes/classic';
import ruLocale from '@fullcalendar/vue3/locales/ru';
import '@fullcalendar/vue3/skeleton.css';
import '@fullcalendar/vue3/themes/classic/theme.css';
import '@fullcalendar/vue3/themes/classic/palette.css';

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
  events: [],
};
</script>

<template>
  <div class="page-heading">
    <div>
      <div class="page-pretitle">
        Важные даты
      </div>
      <h1>
        Календарь
      </h1>
      <p>
        Сроки договоров и передачи показаний.
      </p>
    </div>
  </div>
  <section class="card calendar-surface">
    <FullCalendar :options="calendarOptions" />
  </section>
</template>
