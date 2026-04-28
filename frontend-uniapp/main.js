import Vue from 'vue'
import App from './App'
import RootShell from './RootShell'
import RouteChunkLoading from './components/RouteChunkLoading.vue'
import LoadingInlineSpinner from './components/loading/LoadingInlineSpinner.vue'
import Nc2cCenterSheet from './components/nc2c/Nc2cCenterSheet.vue'
import LanguageSelector from './components/LanguageSelector.vue'
import i18n, { i18nReady, phrase } from './common/i18n'
import { scheduleTranslate } from './common/domTranslator'
import { loadSharedScript } from './utils/loadSharedScript'

function renderI18nBootLoading() {
  try {
    const root = document.getElementById('app')
    if (!root) return
    root.innerHTML = '<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#eefaff;color:#1a4a6e;font-family:Manrope,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;"><div style="width:34px;height:34px;border-radius:999px;border:3px solid rgba(26,74,110,.18);border-top-color:#1677ff;animation:eurnyse-i18n-spin .8s linear infinite" aria-label="Loading"></div><style>@keyframes eurnyse-i18n-spin{to{transform:rotate(360deg)}}</style></div>'
  } catch (e) {}
}

Vue.config.productionTip = false
Vue.component('RouteChunkLoading', RouteChunkLoading)
Vue.component('LoadingInlineSpinner', LoadingInlineSpinner)
Vue.component('Nc2cCenterSheet', Nc2cCenterSheet)
Vue.component('LanguageSelector', LanguageSelector)
Vue.mixin({
  mounted() {
    scheduleTranslate(this.$el)
  },
  updated() {
    scheduleTranslate(this.$el)
  },
  methods: {
    loadSharedScript,
    $phrase(key, fallback) {
      return phrase(key, fallback)
    }
  }
})
Vue.component('App', RootShell)

App.mpType = 'app'

renderI18nBootLoading()

function mountApp() {
  const app = new Vue({
    i18n,
    ...App
  })
  app.$mount('#app')
}

i18nReady.then(mountApp).catch(function () {
  mountApp()
})
