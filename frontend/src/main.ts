import { createApp } from 'vue';
import '@tabler/core/dist/css/tabler.min.css';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import './style.css';
import App from './App.vue';
import { router } from './router';

createApp(App).use(router).mount('#app');
