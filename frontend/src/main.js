import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import './style.css'
import App from './App.vue'
import DashboardView from './views/DashboardView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', component: DashboardView }],
})

const pinia = createPinia()
const app = createApp(App)
app.use(pinia)
app.use(router)
app.mount('#app')
