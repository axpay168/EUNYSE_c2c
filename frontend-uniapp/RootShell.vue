<template>
  <div class="eurforex-shell">
    <component :is="currentView" />
    <nav
      v-if="showMainNav"
      class="eurforex-home-bottom-nav eurforex-shell-tabbar"
      aria-label="主頁底部導航"
    >
      <a
        v-for="item in mainNavItems"
        :key="item.path"
        class="eurforex-shell-tabbar__item"
        :class="{ 'eurforex-shell-tabbar__item--active': activeMainNavPath === item.path }"
        :href="item.path"
        :aria-current="activeMainNavPath === item.path ? 'page' : null"
        @click.prevent="navigate(item.path, false)"
      >
        <img
          v-if="item.brandIcon"
          class="eurforex-shell-tabbar__brand-icon"
          :src="item.brandIcon"
          alt=""
          aria-hidden="true"
        />
        <span
          v-else
          class="material-symbols-outlined eurforex-shell-tabbar__icon"
          :style="activeMainNavPath === item.path ? iconFillStyle : null"
          aria-hidden="true"
        >{{ item.icon }}</span>
        <span class="eurforex-shell-tabbar__label">{{ $phrase(item.label, item.label) }}</span>
      </a>
    </nav>
  </div>
</template>

<script>
import { hasSession } from '@/utils/session'
import { brandMarkLogo } from '@/assets/images'
import RouteChunkLoading from './components/RouteChunkLoading.vue'

function createRouteLoadingComponent() {
  return {
    render: function (h) {
      return h(RouteChunkLoading)
    }
  }
}

function defineAsyncPage(importFn) {
  return () => ({
    component: importFn(),
    loading: createRouteLoadingComponent(),
    delay: 60,
    timeout: 120000
  })
}

const DEFAULT_ROUTE = '#/pages/common/login'
const AUTH_ROUTE = '#/pages/common/login'
const HOME_ROUTE = '#/pages/index/index'

const ROUTE_COMPONENTS = {
  '#/pages/common/login': defineAsyncPage(() => import(/* webpackChunkName: "page-login" */ './pages/common/login.vue')),
  '#/pages/common/register': defineAsyncPage(() => import(/* webpackChunkName: "page-register" */ './pages/common/register.vue')),
  '#/pages/common/resetpwd': defineAsyncPage(() => import(/* webpackChunkName: "page-resetpwd" */ './pages/common/resetpwd.vue')),
  '#/pages/common/article': defineAsyncPage(() => import(/* webpackChunkName: "page-article" */ './pages/common/article.vue')),
  '#/pages/common/webView': defineAsyncPage(() => import(/* webpackChunkName: "page-webwebview" */ './pages/common/webView.vue')),
  '#/pages/index/index': defineAsyncPage(() => import(/* webpackChunkName: "page-home" */ './pages/index/index.vue')),
  '#/pages/index/hall': defineAsyncPage(() => import(/* webpackChunkName: "page-hall" */ './pages/index/hall.vue')),
  '#/pages/index/buy': defineAsyncPage(() => import(/* webpackChunkName: "page-buy" */ './pages/index/buy.vue')),
  '#/pages/index/sell': defineAsyncPage(() => import(/* webpackChunkName: "page-sell" */ './pages/index/sell.vue')),
  '#/pages/index/financial': defineAsyncPage(() => import(/* webpackChunkName: "page-financial" */ './pages/index/financial.vue')),
  '#/pages/index/tutorial': defineAsyncPage(() => import(/* webpackChunkName: "page-tutorial" */ './pages/index/tutorial.vue')),
  '#/pages/index/more': defineAsyncPage(() => import(/* webpackChunkName: "page-more" */ './pages/index/more.vue')),
  '#/pages/index/serviceCenter': defineAsyncPage(() => import(/* webpackChunkName: "page-serviceCenter" */ './pages/index/serviceCenter.vue')),
  '#/pages/setting/myTask': defineAsyncPage(() => import(/* webpackChunkName: "page-myTask" */ './pages/setting/myTask.vue')),
  '#/pages/setting/orderDetail': defineAsyncPage(() => import(/* webpackChunkName: "page-orderDetail" */ './pages/setting/orderDetail.vue')),
  '#/pages/setting/wallet': defineAsyncPage(() => import(/* webpackChunkName: "page-wallet" */ './pages/setting/wallet.vue')),
  '#/pages/setting/mixrecharge': defineAsyncPage(() => import(/* webpackChunkName: "page-mixrecharge" */ './pages/setting/mixrecharge.vue')),
  '#/pages/setting/eurDeposit': defineAsyncPage(() => import(/* webpackChunkName: "page-eurDeposit" */ './pages/setting/eurDeposit.vue')),
  '#/pages/setting/recharge': defineAsyncPage(() => import(/* webpackChunkName: "page-recharge" */ './pages/setting/recharge.vue')),
  '#/pages/setting/rechargeDetail': defineAsyncPage(() => import(/* webpackChunkName: "page-rechargeDetail" */ './pages/setting/rechargeDetail.vue')),
  '#/pages/setting/withdraw': defineAsyncPage(() => import(/* webpackChunkName: "page-withdraw" */ './pages/setting/withdraw.vue')),
  '#/pages/setting/withdrawUsdt': defineAsyncPage(() => import(/* webpackChunkName: "page-withdrawUsdt" */ './pages/setting/withdrawUsdt.vue')),
  '#/pages/setting/eurSwapWithdraw': defineAsyncPage(() => import(/* webpackChunkName: "page-eurSwapWithdraw" */ './pages/setting/eurSwapWithdraw.vue')),
  '#/pages/setting/withdrawDetail': defineAsyncPage(() => import(/* webpackChunkName: "page-withdrawDetail" */ './pages/setting/withdrawDetail.vue')),
  '#/pages/setting/withdrawDetailFiat': defineAsyncPage(() => import(/* webpackChunkName: "page-withdrawDetailFiat" */ './pages/setting/withdrawDetailFiat.vue')),
  '#/pages/setting/fundRecord': defineAsyncPage(() => import(/* webpackChunkName: "page-fundRecord" */ './pages/setting/fundRecord.vue')),
  '#/pages/setting/user': defineAsyncPage(() => import(/* webpackChunkName: "page-user" */ './pages/setting/user.vue')),
  '#/pages/setting/changePassword': defineAsyncPage(() => import(/* webpackChunkName: "page-changePassword" */ './pages/setting/changePassword.vue')),
  '#/pages/setting/securityCenter': defineAsyncPage(() => import(/* webpackChunkName: "page-securityCenter" */ './pages/setting/securityCenter.vue')),
  '#/pages/setting/kycVerification': defineAsyncPage(() => import(/* webpackChunkName: "page-kycVerification" */ './pages/setting/kycVerification.vue')),
  '#/pages/setting/verificationCenter': defineAsyncPage(() => import(/* webpackChunkName: "page-verificationCenter" */ './pages/setting/verificationCenter.vue')),
  '#/pages/setting/bindinfo': defineAsyncPage(() => import(/* webpackChunkName: "page-bindinfo" */ './pages/setting/bindinfo.vue')),
  '#/pages/setting/withdrawAddressBinding': defineAsyncPage(() => import(/* webpackChunkName: "page-withdrawAddressBinding" */ './pages/setting/withdrawAddressBinding.vue')),
  '#/pages/setting/merchantAuth': defineAsyncPage(() => import(/* webpackChunkName: "page-merchantAuth" */ './pages/setting/merchantAuth.vue')),
  '#/pages/setting/inviteTeam': defineAsyncPage(() => import(/* webpackChunkName: "page-inviteTeam" */ './pages/setting/inviteTeam.vue')),
  '#/pages/setting/financialHoldings': defineAsyncPage(() => import(/* webpackChunkName: "page-financialHoldings" */ './pages/setting/financialHoldings.vue'))
}

const PUBLIC_ROUTES = {
  '#/pages/common/login': true,
  '#/pages/common/register': true,
  '#/pages/common/resetpwd': true
}

const AUTH_ENTRY_ROUTES = {
  '#/pages/common/login': true,
  '#/pages/common/register': true
}

const MAIN_NAV_ITEMS = [
  { path: '#/pages/index/index', label: '主頁', brandIcon: brandMarkLogo },
  { path: '#/pages/index/hall', label: '交易大廳', icon: 'swap_horizontal_circle' },
  { path: '#/pages/setting/myTask', label: '訂單', icon: 'receipt_long' },
  { path: '#/pages/setting/user', label: '個人中心', icon: 'person' }
]

const MAIN_NAV_ROUTES = {
  '#/pages/index/index': true,
  '#/pages/index/hall': true,
  '#/pages/index/more': true,
  '#/pages/setting/myTask': true,
  '#/pages/setting/user': true
}

function toHash(url) {
  if (!url) return DEFAULT_ROUTE
  if (url.indexOf('#/') === 0) return url
  if (url.charAt(0) === '/') return '#' + url
  return '#/' + url.replace(/^\/?/, '')
}

function normalizeHash(hash) {
  const target = hash ? (hash.charAt(0) === '#' ? hash : '#' + hash) : DEFAULT_ROUTE
  const path = target.split('?')[0]
  return ROUTE_COMPONENTS[path] ? target : DEFAULT_ROUTE
}

function routePath(hash) {
  return hash.split('?')[0]
}

export default {
  name: 'RootShell',
  data() {
    return {
      currentRoute: DEFAULT_ROUTE,
      iconFillStyle: { fontVariationSettings: "'FILL' 1" },
      mainNavItems: MAIN_NAV_ITEMS
    }
  },
  computed: {
    currentView() {
      const path = routePath(this.currentRoute)
      return ROUTE_COMPONENTS[path] || ROUTE_COMPONENTS[DEFAULT_ROUTE]
    },
    currentPath() {
      return routePath(this.currentRoute)
    },
    showMainNav() {
      return Boolean(MAIN_NAV_ROUTES[this.currentPath])
    },
    activeMainNavPath() {
      const path = this.currentPath
      return MAIN_NAV_ITEMS.some(item => item.path === path) ? path : ''
    }
  },
  created() {
    if (typeof window === 'undefined') return
    this.syncRoute()
    this.ensureDefaultRoute()
    this.installUniNavigationShim()
    window.addEventListener('hashchange', this.syncRoute)
  },
  beforeDestroy() {
    if (typeof window === 'undefined') return
    window.removeEventListener('hashchange', this.syncRoute)
  },
  methods: {
    ensureDefaultRoute() {
      if (!window.location.hash) window.location.hash = hasSession() ? HOME_ROUTE : DEFAULT_ROUTE
    },
    syncRoute() {
      const nextRoute = normalizeHash(window.location.hash)
      const path = routePath(nextRoute)
      if (AUTH_ENTRY_ROUTES[path] && hasSession()) {
        this.currentRoute = HOME_ROUTE
        if (window.location.hash !== HOME_ROUTE) window.location.hash = HOME_ROUTE
        return
      }
      if (!PUBLIC_ROUTES[path] && !hasSession()) {
        this.currentRoute = AUTH_ROUTE
        if (window.location.hash !== AUTH_ROUTE) window.location.hash = AUTH_ROUTE
        return
      }
      this.currentRoute = nextRoute
    },
    navigate(url, replace) {
      const nextHash = normalizeHash(toHash(url))
      if (replace) {
        const base = window.location.pathname + window.location.search
        window.history.replaceState(null, '', base + nextHash)
        this.syncRoute()
        return
      }
      window.location.hash = nextHash
    },
    installUniNavigationShim() {
      const api = window.uni || (window.uni = {})
      api.navigateTo = options => this.navigate(options && options.url, false)
      api.redirectTo = options => this.navigate(options && options.url, true)
      api.reLaunch = options => this.navigate(options && options.url, true)
      api.switchTab = options => this.navigate(options && options.url, true)
      api.navigateBack = () => {
        if (window.history.length > 1) {
          window.history.back()
          return
        }
        this.navigate(DEFAULT_ROUTE, true)
      }
    }
  }
}
</script>

<style>
.eurforex-shell {
  --eurforex-tabbar-core-h: clamp(3.75rem, 8.6dvh, 5rem);
  --eurforex-tabbar-pad-x: clamp(0.625rem, 3.7vw, 1rem);
  --eurforex-tabbar-pad-y: clamp(0.25rem, 0.9dvh, 0.5rem);
  --eurforex-tabbar-icon: clamp(1.25rem, 3.9dvh, 1.5rem);
  --eurforex-tabbar-label: clamp(0.625rem, 1.45dvh, 0.6875rem);
  --eurforex-tabbar-item-x: clamp(0.5rem, 2.8vw, 0.75rem);
  min-height: 100vh;
  min-height: 100dvh;
}

/*
 * 真正上線用的主導航底欄只允許 RootShell 渲染一份。
 * 舊頁面內複製的底欄保留在模板中也不顯示，避免某頁 class / mounted / import 順序造成缺失。
 */
.eurforex-shell nav.eurforex-home-bottom-nav:not(.eurforex-shell-tabbar) {
  display: none !important;
}

.eurforex-shell-tabbar {
  position: fixed !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  z-index: 2147483000 !important;
  box-sizing: border-box;
  display: flex;
  justify-content: space-around;
  align-items: center;
  width: 100%;
  min-height: calc(var(--eurforex-tabbar-core-h) + env(safe-area-inset-bottom, 0px));
  padding: var(--eurforex-tabbar-pad-y) var(--eurforex-tabbar-pad-x) calc(var(--eurforex-tabbar-pad-y) + env(safe-area-inset-bottom, 0px));
  border-radius: 1rem 1rem 0 0;
  border-top: 1px solid rgba(86, 142, 196, 0.16);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(243, 250, 255, 0.76));
  box-shadow: 0 -8px 24px rgba(33, 79, 131, 0.1);
  backdrop-filter: blur(22px) saturate(118%);
  -webkit-backdrop-filter: blur(22px) saturate(118%);
  font-family: "Manrope", "PingFang TC", "Microsoft JhengHei", sans-serif;
}

.eurforex-shell-tabbar__item {
  display: flex;
  min-width: clamp(3.25rem, 18vw, 4rem);
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: clamp(0rem, 0.35dvh, 0.125rem);
  border-radius: 0.75rem;
  padding: clamp(0.1875rem, 0.5dvh, 0.25rem) var(--eurforex-tabbar-item-x);
  color: #617588;
  text-decoration: none;
  transition: color 0.16s ease, background-color 0.16s ease, transform 0.16s ease;
}

.eurforex-shell-tabbar__item--active {
  color: #2764ac;
  background: rgba(191, 220, 252, 0.62);
}

.eurforex-shell-tabbar__item:active {
  transform: scale(0.96);
}

.eurforex-shell-tabbar__icon {
  font-size: var(--eurforex-tabbar-icon);
  line-height: 1;
}

.eurforex-shell-tabbar__brand-icon {
  display: block;
  width: var(--eurforex-tabbar-icon);
  height: var(--eurforex-tabbar-icon);
  object-fit: contain;
}

.eurforex-shell-tabbar__label {
  margin-top: clamp(0rem, 0.3dvh, 0.125rem);
  font-size: var(--eurforex-tabbar-label);
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: 0.02em;
}

@media (max-height: 700px) {
  .eurforex-shell {
    --eurforex-tabbar-core-h: clamp(3.5rem, 9dvh, 4rem);
    --eurforex-tabbar-pad-y: 0.1875rem;
  }
}

@media (max-height: 560px) {
  .eurforex-shell {
    --eurforex-tabbar-core-h: 3.25rem;
    --eurforex-tabbar-icon: 1.125rem;
    --eurforex-tabbar-label: 0.5625rem;
  }
}
</style>
