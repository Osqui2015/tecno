import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/themeStore';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
useThemeStore(pinia).initTheme();
app.use(router);

app.mount('#app');
