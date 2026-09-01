import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import axios from 'axios';

// Setup Axios CSRF token
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
window.axios = axios;

// Register Alpine plugins
Alpine.plugin(collapse);
Alpine.plugin(focus);

// Make Alpine available globally
window.Alpine = Alpine;

Alpine.start();
