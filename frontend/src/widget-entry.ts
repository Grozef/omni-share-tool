import { defineCustomElement } from 'vue'
import PortfolioWidget from './components/PortfolioWidget.vue'

const SocialPortfolioElement = defineCustomElement(PortfolioWidget)

if (!customElements.get('social-portfolio')) {
  customElements.define('social-portfolio', SocialPortfolioElement)
}
