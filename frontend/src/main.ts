import { createApp } from 'vue';
import '@tabler/core/dist/css/tabler.min.css';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import './style.css';
import App from './App.vue';
import { router } from './router';
import { pinia } from './store';

createApp(App).use(pinia).use(router).mount('#app');
