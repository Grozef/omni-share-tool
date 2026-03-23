import { createApp, defineCustomElement } from 'vue'
import { createPinia } from 'pinia'
import { IonicVue } from '@ionic/vue'
import { createRouter, createWebHistory } from '@ionic/vue-router'

import App from './App.vue'
import Dashboard from './views/Dashboard.vue'
import Compose from './views/Compose.vue'
import History from './views/History.vue'
import PlatformConnect from './views/PlatformConnect.vue'
import Portfolio from './views/Portfolio.vue'
import PortfolioWidget from './components/PortfolioWidget.vue'

/* Ionic CSS */
import '@ionic/vue/css/core.css'
import '@ionic/vue/css/normalize.css'
import '@ionic/vue/css/structure.css'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/',          component: Dashboard      },
    { path: '/compose',   component: Compose        },
    { path: '/history',   component: History        },
    { path: '/platforms', component: PlatformConnect },
    { path: '/portfolio', component: Portfolio      },
  ],
})

const pinia = createPinia()

const app = createApp(App)
app.use(IonicVue)
app.use(router)
app.use(pinia)

app.mount('#app')

// Register the portfolio widget as a native Custom Element
// This enables: <social-portfolio slug="..." theme="dark" limit="9"></social-portfolio>
if (!customElements.get('social-portfolio')) {
  const SocialPortfolioElement = defineCustomElement(PortfolioWidget)
  customElements.define('social-portfolio', SocialPortfolioElement)
}
