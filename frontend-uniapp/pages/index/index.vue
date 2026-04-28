<template>
  <div>
    <!-- TopAppBar (from JSON) -->
    <header class="eurnyse-top-bar eurnyse-top-bar--home">
      <div class="eurnyse-top-bar__title-wrap eurnyse-top-bar__title-wrap--home">
        <div class="flex items-center gap-3">
          <img alt="EURNYSE 商標" class="h-10 w-auto max-w-[76px] shrink-0 object-contain" :src="logoSrc" />
          <span class="eurnyse-top-bar__title eurnyse-top-bar__title--home">EURNYSE</span>
        </div>
      </div>
    </header>
    <main class="eurnyse-page-home__main eurnyse-page-home__main--with-tab">
      <!-- Asset Card -->
      <!-- Service Grid：左側理財中心占 2×2；右側 2×2 為邀請／客服／綁定／更多 -->
      <section class="eurnyse-page-home__shortcut-grid" aria-label="首頁快捷功能">
        <a class="home-fin-card eurnyse-page-home__fin-card" href="#/pages/index/financial" :aria-label="'理財中心，參考年化 ' + homeFinancialApr">
          <div class="eurnyse-page-home__fin-inner">
            <div class="eurnyse-page-home__fin-head">
              <span class="eurnyse-page-home__fin-symbol" style="color:#26a17e">₮</span>
              <div class="eurnyse-page-home__fin-head-text">
                <p class="home-fin-title">EARN · 定期</p>
                <p class="home-fin-subtitle">理財中心精選</p>
              </div>
            </div>
            <div class="eurnyse-page-home__fin-rate-block">
              <p class="home-fin-rate font-headline font-bold leading-none tracking-tight">{{ homeFinancialApr }}</p>
              <p class="home-fin-rate-note eurnyse-page-home__fin-rate-note-line">參考年化 APR</p>
            </div>
            <div class="eurnyse-page-home__fin-foot-row">
              <span class="home-fin-footnote eurnyse-page-home__fin-footnote">USDT · 約定期限 · 到期贖回</span>
              <span class="home-fin-cta eurnyse-page-home__fin-cta">前往<span class="material-symbols-outlined text-[15px] leading-none">arrow_forward</span></span>
            </div>
          </div>
          <svg class="eurnyse-page-home__fin-deco" viewBox="0 0 130 110" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="38" y="36" width="62" height="44" rx="9" fill="currentColor" opacity="0.35" transform="rotate(-10 69 58)"/>
            <rect x="48" y="44" width="62" height="44" rx="9" fill="currentColor" opacity="0.45" transform="rotate(-4 79 66)"/>
            <rect x="56" y="52" width="62" height="44" rx="9" fill="currentColor" opacity="0.55"/>
            <circle cx="102" cy="28" r="11" fill="currentColor" opacity="0.4"/>
            <circle cx="114" cy="62" r="7" fill="currentColor" opacity="0.35"/>
          </svg>
        </a>
        <a class="group eurnyse-page-home__shortcut eurnyse-page-home__shortcut--p31" href="#/pages/setting/inviteTeam">
          <div class="home-shortcut-icon eurnyse-page-home__shortcut-icon-wrap">
            <span class="material-symbols-outlined eurnyse-page-home__shortcut-icon--primary">group_add</span>
          </div>
          <span class="eurnyse-page-home__shortcut-label">邀請團隊</span>
        </a>
        <a class="group eurnyse-page-home__shortcut eurnyse-page-home__shortcut--p41" href="#/pages/index/serviceCenter">
          <div class="home-shortcut-icon eurnyse-page-home__shortcut-icon-wrap">
            <span class="material-symbols-outlined eurnyse-page-home__shortcut-icon--primary">support_agent</span>
          </div>
          <span class="eurnyse-page-home__shortcut-label">線上客服</span>
        </a>
        <a class="group eurnyse-page-home__shortcut eurnyse-page-home__shortcut--p32" href="#/pages/setting/bindinfo">
          <div class="home-shortcut-icon eurnyse-page-home__shortcut-icon-wrap">
            <span class="material-symbols-outlined eurnyse-page-home__shortcut-icon--primary">link</span>
          </div>
          <span class="eurnyse-page-home__shortcut-label">帳號綁定</span>
        </a>
        <a class="group eurnyse-page-home__shortcut eurnyse-page-home__shortcut--p42" href="#/pages/index/more">
          <div class="home-shortcut-icon eurnyse-page-home__shortcut-icon-wrap">
            <span class="material-symbols-outlined eurnyse-page-home__shortcut-icon--dim">apps</span>
          </div>
          <span class="eurnyse-page-home__shortcut-label">更多</span>
        </a>
      </section>
      <!-- 行情：快捷入口下方先「交易動態」（與交易大廳同源），再「幣價」 -->
      <section class="eurnyse-page-home__section-feed" aria-label="行情與動態">
        <div class="eurnyse-page-home__feed-card">
          <div class="eurnyse-page-home__feed-head">
            <div class="eurnyse-page-home__feed-title-row">
              <span class="eurnyse-page-home__feed-dot" aria-hidden="true"></span>
              <h2 class="eurnyse-page-home__feed-h2">交易動態</h2>
            </div>
            <a class="eurnyse-page-home__feed-hall" href="#/pages/index/hall">前往大廳</a>
          </div>
          <LoadingInlineSpinner v-if="feedLoading" aria-label="交易動態載入" />
          <div
            v-else
            class="eurnyse-page-home__feed-marquee"
            role="region"
            aria-label="交易動態"
          >
            <div class="eurnyse-page-home__feed-marquee-track" aria-hidden="true">
              <div
                v-for="(item, idx) in feedItemsDoubled"
                :key="'feed-' + item.rowKey + '-' + idx"
                class="eurnyse-page-home__feed-row"
                :class="item.muted ? 'eurnyse-page-home__feed-row--muted' : 'eurnyse-page-home__feed-row--bordered'"
              >
                <template v-if="item.kind === 'trade'">
                  <span class="eurnyse-page-home__feed-user">{{ item.maskedActor }}</span>
                  <span class="eurnyse-page-home__feed-action">
                    {{ item.feedSide === 'buy' ? $t('phrases.feedTradeBought') : $t('phrases.feedTradeSold') }}
                    <span class="eurnyse-page-home__feed-strong"> {{ item.amount }} {{ item.asset }}</span>
                  </span>
                </template>
                <template v-else>
                  <span class="eurnyse-page-home__feed-user">{{ item.name }}</span>
                  <span class="eurnyse-page-home__feed-action">{{ item.quoteHint }}</span>
                </template>
              </div>
            </div>
          </div>
        </div>
        <div>
          <h2 class="eurnyse-page-home__market-heading">即時行情</h2>
          <LoadingInlineSpinner v-if="marketLoading" aria-label="行情載入" />
          <div v-else class="eurnyse-page-home__market-list">
            <div
              v-for="row in marketRows"
              :key="row.cgId"
              class="eurnyse-page-home__market-row"
            >
              <div class="eurnyse-page-home__market-row-inner">
                <div
                  class="eurnyse-page-home__market-icon-wrap"
                  :class="'eurnyse-page-home__market-icon-wrap--' + row.iconIndex"
                >
                  <span
                    class="eurnyse-page-home__market-badge-text"
                    :class="
                      row.iconIndex === 2
                        ? 'eurnyse-page-home__market-badge-text--secondary'
                        : 'eurnyse-page-home__market-badge-text--primary'
                    "
                    >{{ row.glyph }}</span
                  >
                </div>
                <div>
                  <div class="eurnyse-page-home__market-pair">
                    {{ row.base }} <span class="eurnyse-page-home__market-pair-suffix">/ USDT</span>
                  </div>
                  <div class="eurnyse-page-home__market-vol">成交量 {{ row.volLabel }}</div>
                </div>
              </div>
              <div class="eurnyse-page-home__market-spark-wrap">
                <svg
                  class="eurnyse-page-home__market-spark-svg home-market-sparkline"
                  :class="row.up ? 'up' : 'down'"
                  fill="none"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  viewBox="0 0 100 30"
                  preserveAspectRatio="none"
                  aria-hidden="true"
                >
                  <defs>
                    <clipPath :id="'eurnyse-home-spark-clip-' + row.cgId">
                      <rect x="0.75" y="0.75" width="98.5" height="28.5" rx="1" />
                    </clipPath>
                  </defs>
                  <path
                    :clip-path="'url(#eurnyse-home-spark-clip-' + row.cgId + ')'"
                    :d="row.pathD"
                  />
                </svg>
              </div>
              <div class="eurnyse-page-home__market-price-col">
                <div class="eurnyse-page-home__market-price">{{ row.priceLabel }}</div>
                <div
                  class="eurnyse-page-home__market-change home-market-change"
                  :class="row.up ? 'up' : 'down'"
                >
                  {{ row.changeLabel }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      <!-- Campaign Banner -->
      <a class="eurnyse-page-home__promo" href="#/pages/index/financial">
        <div class="eurnyse-page-home__promo-inner">
          <span class="eurnyse-page-home__promo-badge">{{ $t('phrases.活動') }}</span>
          <h3 class="eurnyse-page-home__promo-h3">{{ $t('phrases.平台公告與活動') }}</h3>
          <p class="eurnyse-page-home__promo-desc">{{ $t('phrases.最新活動以公告與理財產品配置為準。') }}</p>
        </div>
        <div class="eurnyse-page-home__promo-art" data-alt="abstract glowing blue and purple digital geometric shapes representing cryptocurrency"></div>
      </a>
      <!-- 最新消息（CoinDesk 中文站，後端快取約 6 小時） -->
      <section class="eurnyse-page-home__news-section">
        <div class="eurnyse-page-home__news-head">
          <h2 class="eurnyse-page-home__news-h2">最新消息</h2>
        </div>
        <div class="eurnyse-page-home__news-card">
          <LoadingInlineSpinner v-if="coindeskNewsLoading" aria-label="新聞載入" />
          <p v-else-if="coindeskNewsError" class="eurnyse-page-home__news-empty">{{ coindeskNewsError }}</p>
          <p v-else-if="!coindeskNews.length" class="eurnyse-page-home__news-empty">暫無新聞（請稍後再試或確認後端可連線 CoinDesk）</p>
          <div v-else class="eurnyse-page-home__news-stack">
            <div
              v-for="(article, idx) in coindeskNews"
              :key="article.url || 'coindesk-news-' + idx"
            >
              <a
                class="group eurnyse-page-home__news-link"
                :href="article.url"
                target="_blank"
                rel="noopener noreferrer"
              >
                <div class="eurnyse-page-home__news-thumb">
                  <img
                    class="eurnyse-page-home__news-img"
                    :src="article.image_url"
                    :alt="article.title"
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer-when-downgrade"
                  />
                </div>
                <div class="eurnyse-page-home__news-body">
                  <h4 class="eurnyse-page-home__news-title">{{ article.title }}</h4>
                  <span class="eurnyse-page-home__news-meta">{{ article.time_label || 'CoinDesk' }}</span>
                </div>
              </a>
              <div
                v-if="idx < coindeskNews.length - 1"
                class="eurnyse-page-home__news-divider"
              ></div>
            </div>
          </div>
        </div>
      </section>
    </main>
    <!-- BottomNavBar（與交易大廳、訂單等頁一致：選中項 bg-primary-container/60，由 eurnyse-style-refresh 轉為反白漸層） -->
    <nav
      class="eurnyse-home-bottom-nav fixed bottom-0 left-0 w-full z-50 rounded-t-2xl bg-white/78 backdrop-blur-lg shadow-[0px_-8px_24px_rgba(33,79,131,0.08)] font-['Manrope']"
      role="navigation"
      aria-label="主頁底部導航"
    >
      <div class="flex justify-around items-center h-20 px-4 pb-safe">
        <a class="flex flex-col items-center justify-center text-primary-dim bg-primary-container/60 rounded-xl px-3 py-1 transition-all active:scale-90" href="#/pages/index/index">
          <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">home</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">主頁</span>
        </a>
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/index/hall">
          <span class="material-symbols-outlined">swap_horizontal_circle</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">交易大廳</span>
        </a>
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/setting/myTask">
          <span class="material-symbols-outlined">receipt_long</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">訂單</span>
        </a>
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/setting/user">
          <span class="material-symbols-outlined">person</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">個人中心</span>
        </a>
      </div>
    </nav>
  </div>
</template>

<script>
import { brandLogo } from '@/assets/images'
import { getStoredLang, LANG_CHANGE_EVENT } from '@/common/langStorage'
import { userApi } from '@/utils/api'
import { pricesToSparkPathD } from '../../utils/coingeckoMarkets'

/**
 * EURNYSE - 首頁 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/home.html 為唯一基準逐字遷移
 * 原稿無內聯 JS，僅外部 back-nav.js
 * 商標 LOGO 使用 :src 綁定避開建置期資產 URL 轉換問題（比照 login-h5.vue 策略）
 *
 * 即時行情：走後端 market-snapshot 代理/快取，避免瀏覽器直接打第三方 API 造成 CORS。
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--home",
  "eurnyse-page-home"
]

function defaultSparkPathD() {
  return pricesToSparkPathD([1, 1, 1, 1], 100, 30, 4)
}

function sparkPathForMarketRow(code, up) {
  var samples = {
    BTC: [1, 1.01, 1.005, 1.018, 1.03, 1.025, 1.04, 1.052, 1.048, 1.06],
    ETH: [1, 0.998, 1.01, 1.006, 1.02, 1.027, 1.03, 1.045, 1.05, 1.065],
    XRP: [1, 0.992, 1.006, 0.998, 1.012, 1.004, 1.018, 1.011, 1.025, 1.02]
  }
  var values = samples[String(code || '').toUpperCase()] || samples.BTC
  if (!up) values = values.slice().reverse()
  return pricesToSparkPathD(values, 100, 30, 4)
}

function formatFeedAmountDisplay(raw) {
  var n = Number(raw)
  if (!isFinite(n)) return '—'
  return n.toLocaleString('zh-Hant', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

/** 郵箱：axpay168@gmail.com → axp****68@gmail.com；非郵箱字串做簡短遮罩 */
function maskTradeFeedActor(raw) {
  var s = String(raw || '').trim()
  if (!s) return '——'
  var at = s.indexOf('@')
  if (at > 0 && at < s.length - 1) {
    var local = s.slice(0, at)
    var domain = s.slice(at + 1)
    if (local.length >= 5) {
      return local.slice(0, 3) + '****' + local.slice(-2) + '@' + domain
    }
    if (local.length === 4) {
      return local.slice(0, 2) + '****' + local.slice(-2) + '@' + domain
    }
    if (local.length >= 2) {
      return local.charAt(0) + '****' + local.slice(-1) + '@' + domain
    }
    return '****@' + domain
  }
  if (s.length >= 6) {
    return s.slice(0, 3) + '****' + s.slice(-2)
  }
  if (s.length >= 4) {
    return s.slice(0, 2) + '****' + s.slice(-2)
  }
  return s
}

function mapTradeFeedApiItems(items) {
  var rows = []
  var list = items && items.length ? items : []
  for (var i = 0; i < list.length; i++) {
    var it = list[i] || {}
    var action = String(it.action_type || '').toLowerCase()
    var muted = rows.length % 3 === 2
    var rowKey = it.id != null ? String(it.id) : 'r' + i
    if (action === 'completed_buy' || action === 'completed_sell') {
      var actorRaw = String(it.actor_name || '').trim()
      rows.push({
        rowKey: rowKey,
        kind: 'trade',
        maskedActor: maskTradeFeedActor(actorRaw),
        feedSide: action === 'completed_buy' ? 'buy' : 'sell',
        amount: formatFeedAmountDisplay(it.amount),
        asset: String(it.asset_code || 'USDT').toUpperCase(),
        muted: muted
      })
    } else {
      var quoteLabel = String(it.actor_name || it.display_title || '商戶').trim() || '商戶'
      var quoteDisplay = quoteLabel.indexOf('@') >= 0 ? maskTradeFeedActor(quoteLabel) : quoteLabel
      var dt = String(it.display_title || '').trim()
      var quoteHint = dt ? (dt.length > 28 ? dt.slice(0, 28) + '…' : dt) : '新報價'
      rows.push({
        rowKey: rowKey,
        kind: 'quote',
        name: quoteDisplay,
        quoteHint: quoteHint,
        muted: muted
      })
    }
  }
  return rows
}

function normalizeSparkPoints(raw, fallbackCode) {
  if (!Array.isArray(raw)) return default_market_points(fallbackCode)
  var points = raw
    .map(function (it) { return Number(it) })
    .filter(function (n) { return isFinite(n) && n > 0 })
  if (points.length < 4) return default_market_points(fallbackCode)
  return points
}

function default_market_points(code) {
  var upper = String(code || '').toUpperCase()
  if (upper === 'BTC') return [1.00, 1.01, 1.00, 1.02, 1.03, 1.04, 1.03, 1.05, 1.04, 1.06, 1.07, 1.08]
  if (upper === 'ETH') return [1.00, 1.00, 1.01, 1.00, 1.02, 1.03, 1.02, 1.04, 1.05, 1.06, 1.05, 1.07]
  return [1.00, 0.99, 1.00, 1.01, 1.00, 1.02, 1.01, 1.02, 1.03, 1.02, 1.04, 1.03]
}

function runWhenIdle(task, timeout) {
  if (typeof window !== 'undefined' && typeof window.requestIdleCallback === 'function') {
    return window.requestIdleCallback(task, { timeout: timeout || 1200 })
  }
  return setTimeout(task, timeout || 120)
}

function cancelIdleTask(id) {
  if (id == null) return
  if (typeof window !== 'undefined' && typeof window.cancelIdleCallback === 'function') {
    window.cancelIdleCallback(id)
    return
  }
  clearTimeout(id)
}

export default {
  name: "HomeH5",
  data: function () {
    return {
      logoSrc: brandLogo,
      feedItems: [],
      feedLoading: false,
      feedPage: 1,
      feedPageSize: 7,
      feedHasMore: true,
      feedRefreshTimer: null,
      deferredLoadTimers: [],
      coindeskNews: [],
      coindeskNewsLoading: false,
      marketLoading: false,
      coindeskNewsError: "",
      coindeskNewsLang: getStoredLang(),
      homeFinancialApr: "—",
      marketRows: [
        {
          cgId: 'bitcoin',
          base: 'BTC',
          glyph: '₿',
          iconIndex: 0,
          pathD: defaultSparkPathD(),
          priceLabel: '—',
          changeLabel: '—',
          volLabel: '—',
          up: true
        },
        {
          cgId: 'ethereum',
          base: 'ETH',
          glyph: '⟠',
          iconIndex: 1,
          pathD: defaultSparkPathD(),
          priceLabel: '—',
          changeLabel: '—',
          volLabel: '—',
          up: true
        },
        {
          cgId: 'ripple',
          base: 'XRP',
          glyph: '✕',
          iconIndex: 2,
          pathD: defaultSparkPathD(),
          priceLabel: '—',
          changeLabel: '—',
          volLabel: '—',
          up: false
        }
      ]
    }
  },
  computed: {
    feedItemsDoubled: function () {
      if (!this.feedItems.length) return [];
      return this.feedItems.concat(this.feedItems);
    }
  },
  methods: {
    loadTradeFeed: function () {
      this.feedPage = 1;
      this.feedHasMore = true;
      this.loadTradeFeedPage(true);
    },
    loadTradeFeedPage: function (reset) {
      var self = this;
      var first = !self._tradeFeedHydrated;
      if (first) self.feedLoading = true;
      var page = reset ? 1 : this.feedPage + 1;
      userApi
        .tradeFeed('?page=' + encodeURIComponent(page) + '&page_size=' + encodeURIComponent(this.feedPageSize))
        .then(function (data) {
          var rows = mapTradeFeedApiItems((data && data.items) || []);
          self.feedItems = reset ? rows : self.feedItems.concat(rows);
          self.feedPage = page;
          self.feedHasMore = !!(data && data.has_more);
        })
        .catch(function () {
          if (reset) self.feedItems = [];
          self.feedHasMore = false;
        })
        .then(function () {
          self.feedLoading = false;
          self._tradeFeedHydrated = true;
          if (self.feedHasMore) {
            self.scheduleMoreTradeFeed();
          }
        });
    },
    scheduleMoreTradeFeed: function () {
      var self = this;
      this.deferredLoadTimers.push(runWhenIdle(function () {
        if (self.feedHasMore) self.loadTradeFeedPage(false);
      }, 1600));
    },
    loadCoindeskNews: function () {
      var self = this;
      var lang = getStoredLang();
      self.coindeskNewsLang = lang;
      self.coindeskNewsLoading = true;
      self.coindeskNewsError = "";
      userApi
        .coindeskNews(lang)
        .then(function (data) {
          self.coindeskNews = (data && data.items) || [];
        })
        .catch(function () {
          self.coindeskNews = [];
          self.coindeskNewsError = "無法載入新聞，請稍後再試";
        })
        .then(function () {
          self.coindeskNewsLoading = false;
        });
    },
    loadHomeMarkets: function () {
      var rows = this.marketRows
      userApi
        .marketSnapshot()
        .then(function (simple) {
          var list = (simple && simple.items) || []
          var byCode = {}
          for (var i = 0; i < list.length; i++) {
            var item = list[i] || {}
            var code = String(item.code || '').toUpperCase()
            if (code) byCode[code] = item
          }
          for (var j = 0; j < rows.length; j++) {
            var row = rows[j]
            var market = byCode[row.base]
            if (!market) continue
            row.priceLabel = String(market.price || '—')
            row.changeLabel = String(market.delta || '—')
            row.up = Number(market.change) >= 0
            row.volLabel = String(market.volume || '—')
            var sparkPoints = normalizeSparkPoints(market.spark, row.base)
            row.pathD = pricesToSparkPathD(sparkPoints, 100, 30, 4) || sparkPathForMarketRow(row.base, row.up)
          }
        })
        .catch(function () {
          for (var k = 0; k < rows.length; k++) {
            rows[k].priceLabel = '—'
            rows[k].changeLabel = '—'
            rows[k].volLabel = '—'
            rows[k].pathD = defaultSparkPathD()
          }
        })
        .then(function () {
          this.marketLoading = false
        }.bind(this))
    },
    loadFinancialTeaser: function () {
      var self = this;
      userApi
        .financialProducts()
        .then(function (data) {
          var rows = (data && data.items) || [];
          var rates = rows.map(function (p) { return Number(p.apr_rate); }).filter(function (n) { return isFinite(n); });
          if (!rates.length) {
            self.homeFinancialApr = "—";
            return;
          }
          var max = Math.max.apply(Math, rates);
          self.homeFinancialApr = max.toFixed(max % 1 === 0 ? 0 : 2).replace(/\.?0+$/, "") + "%";
        })
        .catch(function () {
          self.homeFinancialApr = "—";
        })
    },
    scheduleDeferredHomeLoads: function () {
      var self = this;
      this.deferredLoadTimers.push(runWhenIdle(function () {
        self.loadFinancialTeaser();
      }, 300));
      this.deferredLoadTimers.push(runWhenIdle(function () {
        self.prefetchHallRoute();
      }, 500));
      this.deferredLoadTimers.push(runWhenIdle(function () {
        self.loadCoindeskNews();
      }, 1200));
    },
    prefetchHallRoute: function () {
      import(/* webpackChunkName: "page-hall" */ './hall.vue').catch(function () {});
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "home");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.loadHomeMarkets();
    this.loadTradeFeed();
    this.scheduleDeferredHomeLoads();
    var self = this;
    this.__onHomeLangChange = function () {
      self.loadCoindeskNews();
    };
    window.addEventListener(LANG_CHANGE_EVENT, this.__onHomeLangChange);
    this.feedRefreshTimer = setInterval(function () {
      self.loadTradeFeed();
    }, 120000);
  },
  beforeDestroy: function () {
    if (this.__onHomeLangChange) {
      window.removeEventListener(LANG_CHANGE_EVENT, this.__onHomeLangChange);
      this.__onHomeLangChange = null;
    }
    if (this.feedRefreshTimer) {
      clearInterval(this.feedRefreshTimer);
      this.feedRefreshTimer = null;
    }
    if (this.deferredLoadTimers && this.deferredLoadTimers.length) {
      for (var j = 0; j < this.deferredLoadTimers.length; j++) {
        cancelIdleTask(this.deferredLoadTimers[j]);
      }
      this.deferredLoadTimers = [];
    }
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");

/* 交易動態標題旁：綠色發光圓點 */
.eurnyse-page-home__feed-dot {
  display: inline-block;
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  margin-top: 0.125rem;
  border-radius: 9999px;
  background: #22c55e;
  box-shadow:
    0 0 0 0 rgba(34, 197, 94, 0.55),
    0 0 10px 2px rgba(34, 197, 94, 0.45);
  animation: eurnyse-feed-dot-live 2.2s ease-in-out infinite;
}

@keyframes eurnyse-feed-dot-live {
  0%,
  100% {
    box-shadow:
      0 0 0 0 rgba(34, 197, 94, 0.5),
      0 0 8px 1px rgba(34, 197, 94, 0.4);
    transform: scale(1);
  }
  50% {
    box-shadow:
      0 0 0 7px rgba(34, 197, 94, 0),
      0 0 14px 3px rgba(74, 222, 128, 0.65);
    transform: scale(1.12);
  }
}

@media (prefers-reduced-motion: reduce) {
  .eurnyse-page-home__feed-dot {
    animation: none;
    box-shadow: 0 0 8px 2px rgba(34, 197, 94, 0.5);
  }
}

.eurnyse-page-home__news-empty {
  margin: 0;
  padding: 1rem 1.25rem;
  font-size: 0.85rem;
  line-height: 1.5;
  color: var(--ac-text-3, #6b7c90);
}

.eurnyse-page-home__feed-marquee .eurnyse-page-home__feed-user {
  min-width: 0;
  flex: 0 1 46%;
  max-width: 46%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}

.eurnyse-page-home__feed-marquee .eurnyse-page-home__feed-action {
  min-width: 0;
  flex: 1 1 auto;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* 交易動態列表：垂直跑馬燈，由上往下無接縫滾動（軌跡以 reverse 實作） */
.eurnyse-page-home__feed-marquee {
  --eurnyse-feed-item-h: 2.75rem;
  max-height: calc(3 * var(--eurnyse-feed-item-h));
  overflow: hidden;
  position: relative;
}

.eurnyse-page-home__feed-marquee-track {
  display: flex;
  flex-direction: column;
  width: 100%;
  will-change: transform;
  /* reverse：列表由上往下無接縫滾動 */
  animation: eurnyse-home-feed-ticker-down 14s linear infinite reverse;
}

.eurnyse-page-home__feed-marquee .eurnyse-page-home__feed-row {
  min-height: var(--eurnyse-feed-item-h);
  flex: 0 0 auto;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
}

.eurnyse-page-home__feed-marquee:hover .eurnyse-page-home__feed-marquee-track {
  animation-play-state: paused;
}

/* 0→-50% 往上移；reverse ＝ 由上往下滑入的視覺 */
@keyframes eurnyse-home-feed-ticker-down {
  0% {
    transform: translateY(0);
  }
  100% {
    transform: translateY(-50%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .eurnyse-page-home__feed-marquee .eurnyse-page-home__feed-marquee-track {
    animation: none;
  }
}

/* 走勢線：裁切筆畫與路徑，避免超出欄位（設計寬 5rem × 高 2rem） */
.eurnyse-page-home__market-spark-wrap {
  overflow: hidden;
  position: relative;
  flex-shrink: 0;
}

.eurnyse-page-home__market-spark-svg {
  display: block;
  overflow: hidden;
  vector-effect: non-scaling-stroke;
}
</style>
