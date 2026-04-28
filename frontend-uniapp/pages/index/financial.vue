<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/index/index" @click="backGo($event, '#/pages/index/index')" aria-label="返回"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">理財中心</div>
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/setting/wallet" aria-label="資產"><span class="material-symbols-outlined">account_balance_wallet</span></a>
    </header>

    <main class="mx-auto max-w-4xl space-y-8 px-4 pt-20">
      <section class="relative overflow-hidden rounded-[24px] fin-hero-glow px-4 py-4 text-white shadow-[0_14px_40px_rgba(40,88,142,0.12)] md:rounded-[28px] md:px-5 md:py-5">
        <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10 blur-3xl md:h-40 md:w-40"></div>
        <div class="pointer-events-none absolute bottom-0 left-1/2 h-24 w-56 -translate-x-1/2 rounded-full bg-black/10 blur-2xl md:h-32 md:w-64"></div>
        <div class="relative z-10">
          <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-white/75">EURNYSE · 一站式收益配置</p>
          <h1 class="font-headline mt-1.5 text-xl font-bold leading-tight md:text-2xl">讓閒置資金為你工作</h1>
          <div class="mt-4 grid grid-cols-3 gap-2 border-t border-white/15 pt-3 text-center md:mt-5 md:gap-3 md:pt-4">
            <div>
              <p class="text-[10px] uppercase tracking-wider text-white/65">參考區間 APR</p>
              <p class="font-headline mt-0.5 text-base font-bold md:mt-1 md:text-lg">{{ aprRange }}</p>
            </div>
            <a class="block rounded-xl border-x border-white/15 px-1 py-1 text-center text-white no-underline outline-none transition-colors hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white/45" href="#/pages/setting/financialHoldings" aria-label="查看我的持倉">
              <p class="text-[10px] uppercase tracking-wider text-white/65">我的持倉</p>
              <p class="font-headline mt-0.5 text-base font-bold md:mt-1 md:text-lg">{{ holdingCount }}</p>
            </a>
            <div>
              <p class="text-[10px] uppercase tracking-wider text-white/65">累計收益</p>
              <p class="font-headline mt-0.5 text-base font-bold md:mt-1 md:text-lg">+{{ totalEstimatedInterestDisplay }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- 分類導覽（New_C2C 同款篩選） -->
      <section class="glass-panel rounded-2xl p-2">
        <div class="flex flex-wrap gap-2" id="nc2c-category-bar" role="tablist" aria-label="商品分類">
          <button type="button" class="nc2c-cat-btn" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">全部商品</button>
          <button type="button" class="nc2c-cat-btn" :class="{ 'is-active': filter === 'flex' }" @click="filter = 'flex'">活期型</button>
          <button type="button" class="nc2c-cat-btn" :class="{ 'is-active': filter === 'fixed' }" @click="filter = 'fixed'">定期型</button>
        </div>
      </section>

      <!-- New_C2C 風格產品列表（pages/index/financial.vue） -->
      <section id="products" class="space-y-4">
        <div class="flex items-end justify-between px-1">
          <h2 class="font-headline text-xl font-bold text-on-surface">精選商品</h2>
          <span class="text-xs text-on-surface-variant">共 {{ filteredProducts.length }} 檔</span>
        </div>

        <div class="nc2c-products" id="nc2c-product-list">
          <LoadingInlineSpinner v-if="loading" aria-label="理財商品載入" />
          <p v-else-if="errorMessage" class="rounded-2xl bg-error/5 px-4 py-8 text-center text-sm text-error">{{ errorMessage }}</p>
          <p v-else-if="!filteredProducts.length" class="rounded-2xl bg-surface-container-lowest/80 px-4 py-8 text-center text-sm text-on-surface-variant">目前沒有可申購的理財商品。</p>
          <template v-else>
            <div
              v-for="product in filteredProducts"
              :key="product.product_code"
              class="nc2c-product"
              :class="{ 'nc2c-product--active': currentProduct && currentProduct.product_code === product.product_code }"
              role="button"
              tabindex="0"
              :aria-label="product.display_name || product.product_code"
              @click="openDetail(product)"
              @keydown.enter.prevent="openDetail(product)"
              @keydown.space.prevent="openDetail(product)"
            >
              <div class="nc2c-product__head">
                <div>
                  <div class="nc2c-product__title">{{ product.display_name || product.product_code }}</div>
                  <div class="nc2c-product__sub">{{ product.subtitle || productTermText(product) }}</div>
                </div>
                <div class="nc2c-product__apr">參考 APR {{ formatPercent(product.apr_rate) }}</div>
              </div>
              <div class="nc2c-product__meta">
                <span class="nc2c-chip">最低申購 {{ formatAmount(product.min_subscribe_amount, 2) }} {{ product.asset_code }}</span>
                <span class="nc2c-chip">期限 {{ product.term_days }}D</span>
                <span class="nc2c-chip">剩餘額度 {{ formatAmount(product.remaining_quota_amount, 2) }} {{ product.asset_code }}</span>
              </div>
              <div class="nc2c-product__foot">
                <p class="nc2c-product__hint">{{ product.detail_note || '收益與結算規則以後台配置為準。' }}</p>
                <button type="button" class="nc2c-btn-detail" @click.stop="openDetail(product)">產品詳情</button>
              </div>
            </div>
          </template>
        </div>
      </section>

      <!-- 風險揭露 -->
      <section class="rounded-2xl border border-error/20 bg-error-container/35 px-4 py-4 md:px-5">
        <div class="flex gap-3">
          <span class="material-symbols-outlined shrink-0 text-error">gpp_maybe</span>
          <div class="min-w-0 text-sm leading-relaxed text-on-surface">
            <p class="font-semibold text-on-surface">風險提示</p>
            <p class="mt-1 text-on-surface-variant">理財產品非存款，不受存款保險保障；市場波動、對手方與流動性均可能影響實際收益與本金。投資前請詳閱產品說明與風險揭露，僅投資您能承受損失的資金。</p>
          </div>
        </div>
      </section>

      <!-- New_C2C 產品詳情底部彈層（financial.vue financial-detail-sheet） -->
      <div id="nc2c-detail-backdrop" class="nc2c-modal-backdrop" :class="{ 'is-open': detailOpen }" :aria-hidden="detailOpen ? 'false' : 'true'" @click.self="closeDetail">
        <div class="nc2c-detail-sheet" role="dialog" aria-modal="true" aria-labelledby="nc2c-detail-title" id="nc2c-detail-sheet">
          <div class="nc2c-detail-head">
            <div class="nc2c-detail-brand">
                <div class="nc2c-detail-brand__coin" id="nc2c-detail-asset">{{ currentProduct ? currentProduct.asset_code : 'USDT' }}</div>
              <div>
                <div class="nc2c-detail-brand__title" id="nc2c-detail-title">{{ currentProduct ? (currentProduct.display_name || currentProduct.product_code) : '理財商品' }}</div>
                <div class="nc2c-detail-brand__sub" id="nc2c-detail-sub">{{ currentProduct ? (currentProduct.subtitle || productTermText(currentProduct)) : '' }}</div>
              </div>
            </div>
            <button type="button" class="nc2c-icon-close" id="nc2c-detail-close" aria-label="關閉" @click="closeDetail">×</button>
          </div>

          <div class="nc2c-detail-terms">
            <div class="nc2c-term-card">
              <div class="nc2c-term-card__label">期限</div>
              <div class="nc2c-term-card__value" id="nc2c-detail-term">{{ currentProduct ? currentProduct.term_days + 'D' : '—' }}</div>
            </div>
            <div class="nc2c-term-card">
              <div class="nc2c-term-card__label">參考 APR</div>
              <div class="nc2c-term-card__value" id="nc2c-detail-apr">{{ currentProduct ? formatPercent(currentProduct.apr_rate) : '—' }}</div>
            </div>
            <div class="nc2c-term-card">
              <div class="nc2c-term-card__label">最低申購</div>
              <div class="nc2c-term-card__value" id="nc2c-detail-min">{{ currentProduct ? formatAmount(currentProduct.min_subscribe_amount, 2) + ' ' + currentProduct.asset_code : '—' }}</div>
            </div>
          </div>

          <div class="nc2c-detail-summary">
            <div class="nc2c-detail-summary__row"><span>可用餘額</span><span id="nc2c-detail-balance">{{ currentProduct ? formatAmount(currentProduct.available_balance, 2) + ' ' + currentProduct.asset_code : '—' }}</span></div>
            <div class="nc2c-detail-summary__row"><span>個人剩餘額度</span><span id="nc2c-detail-quota">{{ currentProduct ? formatAmount(currentProduct.user_remaining_quota_amount, 2) + ' ' + currentProduct.asset_code : '—' }}</span></div>
          </div>

          <div class="nc2c-subscribe-card">
            <div class="nc2c-subscribe-card__head">
              <div class="nc2c-subscribe-card__title">立即申購</div>
              <div class="nc2c-subscribe-card__yield" id="nc2c-estimated-yield">預估收益 {{ estimatedInterestDisplay }}</div>
            </div>
            <div class="nc2c-field-amount">
              <input type="text" inputmode="decimal" autocomplete="off" id="nc2c-subscribe-input" class="font-body" placeholder="請輸入申購金額" v-model="subscribeAmount" />
              <button type="button" class="nc2c-amount-all" id="nc2c-subscribe-all" @click="fillSubscribeMax">ALL</button>
              <span class="nc2c-field-suffix" id="nc2c-subscribe-suffix">{{ currentProduct ? currentProduct.asset_code : 'USDT' }}</span>
            </div>
            <div class="nc2c-subscribe-meta">
              <div class="nc2c-subscribe-meta__row"><span>可用餘額</span><span id="nc2c-sub-meta-balance">{{ currentProduct ? formatAmount(currentProduct.available_balance, 2) + ' ' + currentProduct.asset_code : '—' }}</span></div>
              <div class="nc2c-subscribe-meta__row"><span>個人剩餘額度</span><span id="nc2c-sub-meta-quota">{{ currentProduct ? formatAmount(currentProduct.user_remaining_quota_amount, 2) + ' ' + currentProduct.asset_code : '—' }}</span></div>
            </div>
            <button type="button" class="nc2c-subscribe-submit" id="nc2c-subscribe-submit" :disabled="submitting" @click="submitSubscription">{{ submitting ? '提交中...' : '確認申購' }}</button>
          </div>

          <div class="nc2c-detail-tabs" role="tablist">
            <button type="button" :class="{ 'is-active': detailTab === 'overview' }" id="nc2c-tab-overview" role="tab" :aria-selected="detailTab === 'overview' ? 'true' : 'false'" @click="detailTab = 'overview'">概覽</button>
            <button type="button" :class="{ 'is-active': detailTab === 'rules' }" id="nc2c-tab-rules" role="tab" :aria-selected="detailTab === 'rules' ? 'true' : 'false'" @click="detailTab = 'rules'">規則</button>
          </div>

          <div class="nc2c-detail-panel" id="nc2c-panel-overview" :class="{ 'nc2c-panel-hidden': detailTab !== 'overview' }">
            <div class="nc2c-detail-section">
              <div class="nc2c-detail-section__title">預估收益</div>
              <div class="nc2c-detail-section__meta" id="nc2c-overview-apr-line">參考 APR {{ currentProduct ? formatPercent(currentProduct.apr_rate) : '—' }}</div>
            </div>
            <div class="nc2c-timeline" id="nc2c-timeline">
              <div class="nc2c-timeline__row"><div class="nc2c-timeline__label">起息日</div><div class="nc2c-timeline__value">申購成功後 T+1</div></div>
              <div class="nc2c-timeline__row"><div class="nc2c-timeline__label">到期日</div><div class="nc2c-timeline__value">{{ currentProduct ? currentProduct.term_days + ' 天後' : '—' }}</div></div>
            </div>
            <div class="nc2c-detail-highlight" id="nc2c-detail-highlight">{{ currentProduct ? currentProduct.term_days + 'D · 參考 APR ' + formatPercent(currentProduct.apr_rate) : '—' }}</div>
            <div id="nc2c-overview-notes"><p class="nc2c-detail-note">{{ currentProduct && currentProduct.detail_note ? currentProduct.detail_note : '實際收益與結算依後台產品配置與審核結果為準。' }}</p></div>
          </div>

          <div class="nc2c-detail-panel" id="nc2c-panel-rules" :class="{ 'nc2c-panel-hidden': detailTab !== 'rules' }">
            <div id="nc2c-rule-blocks">
              <div class="nc2c-rule-block"><div class="nc2c-rule-block__label">申購下限</div><div class="nc2c-rule-block__value">{{ currentProduct ? formatAmount(currentProduct.min_subscribe_amount, 2) + ' ' + currentProduct.asset_code : '—' }}</div></div>
              <div class="nc2c-rule-block"><div class="nc2c-rule-block__label">個人剩餘額度</div><div class="nc2c-rule-block__value">{{ currentProduct ? formatAmount(currentProduct.user_remaining_quota_amount, 2) + ' ' + currentProduct.asset_code : '—' }}</div></div>
            </div>
            <div id="nc2c-rule-notes"><p class="nc2c-detail-note">申購會立即從對應錢包扣除可用餘額，持倉可在「我的持倉」查看。</p></div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
import { scheduleTranslate } from '@/common/domTranslator'
import { userApi } from '@/utils/api'

var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--financial",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-10"
];

export default {
  name: "FinancialH5",
  data: function () {
    return {
      products: [],
      subscriptions: [],
      filter: "all",
      loading: false,
      errorMessage: "",
      detailOpen: false,
      detailTab: "overview",
      currentProduct: null,
      subscribeAmount: "",
      submitting: false
    };
  },
  computed: {
    filteredProducts: function () {
      var f = this.filter;
      if (f === "all") return this.products;
      return this.products.filter(function (p) {
        return Number(p.term_days || 0) <= 7 ? f === "flex" : f === "fixed";
      });
    },
    holdingCount: function () {
      return this.subscriptions.filter(function (it) {
        return ["active", "pending_settlement"].indexOf(String(it.status || "")) !== -1;
      }).length;
    },
    aprRange: function () {
      var nums = this.products.map(function (p) { return Number(p.apr_rate); }).filter(function (n) { return isFinite(n); });
      if (!nums.length) return "—";
      var min = Math.min.apply(Math, nums);
      var max = Math.max.apply(Math, nums);
      return min === max ? this.formatPercent(min) : this.formatPercent(min) + "–" + this.formatPercent(max);
    },
    totalEstimatedInterestDisplay: function () {
      var total = this.subscriptions.reduce(function (sum, it) {
        return sum + (parseFloat(it.estimated_interest || "0") || 0);
      }, 0);
      return this.formatAmount(total, 2);
    },
    parsedSubscribeAmount: function () {
      var raw = String(this.subscribeAmount || "").replace(/[^\d.]/g, "").replace(/(\..*)\./g, "$1");
      var n = parseFloat(raw);
      return isNaN(n) || n < 0 ? 0 : n;
    },
    estimatedInterestDisplay: function () {
      var p = this.currentProduct;
      if (!p) return "—";
      var amount = this.parsedSubscribeAmount;
      var apr = Number(p.apr_rate || 0);
      var days = Number(p.term_days || 0);
      var interest = amount * (apr / 100) * (days / 365);
      return this.formatAmount(interest, p.asset_code === "ETH" ? 4 : 2) + " " + p.asset_code;
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) document.body.classList.add(BODY_CLASSES[i]);
      document.body.setAttribute("data-nc2c-page", "financial");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.fetchFinancialData();
  },
  beforeDestroy: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) document.body.classList.remove(BODY_CLASSES[i]);
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
      document.body.style.overflow = "";
    } catch (e) {}
  },
  methods: {
    backGo: function (event, fallback) {
      if (window.EurnyseBack && typeof window.EurnyseBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurnyseBack.go(fallback);
      }
    },
    fetchFinancialData: function () {
      var self = this;
      this.loading = true;
      this.errorMessage = "";
      Promise.all([userApi.financialProducts(), userApi.financialSubscriptions()])
        .then(function (results) {
          self.products = (results[0] && results[0].items) || [];
          self.subscriptions = (results[1] && results[1].items) || [];
        })
        .catch(function (error) {
          self.products = [];
          self.subscriptions = [];
          self.errorMessage = (error && error.message) || "載入理財商品失敗";
        })
        .finally(function () {
          self.loading = false;
        });
    },
    productTermText: function (product) {
      return "期限 " + Number(product && product.term_days || 0) + "D · 到期結算";
    },
    formatAmount: function (value, digits) {
      return Number(value || 0).toLocaleString("en-GB", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    },
    formatPercent: function (value) {
      var n = Number(value || 0);
      return n.toLocaleString("en-GB", { minimumFractionDigits: 1, maximumFractionDigits: 2 }) + "%";
    },
    openDetail: function (product) {
      this.currentProduct = product;
      this.subscribeAmount = String(parseFloat(product.min_subscribe_amount || "0") || "");
      this.detailTab = "overview";
      this.detailOpen = true;
      try { document.body.style.overflow = "hidden"; } catch (e) {}
      this.$nextTick(function () {
        scheduleTranslate(document.getElementById("nc2c-detail-backdrop"));
      });
    },
    closeDetail: function () {
      this.detailOpen = false;
      try { document.body.style.overflow = ""; } catch (e) {}
    },
    fillSubscribeMax: function () {
      var p = this.currentProduct;
      if (!p) return;
      var max = Math.min(
        parseFloat(p.available_balance || "0") || 0,
        parseFloat(p.user_remaining_quota_amount || "0") || 0,
        parseFloat(p.remaining_quota_amount || "0") || 0
      );
      this.subscribeAmount = (Math.floor(max * 100) / 100).toFixed(2);
    },
    submitSubscription: function () {
      var p = this.currentProduct;
      var amount = this.parsedSubscribeAmount;
      if (!p) return;
      if (!amount) {
        alert("請輸入申購金額。");
        return;
      }
      if (amount < (parseFloat(p.min_subscribe_amount || "0") || 0)) {
        alert("不得低於最低申購。");
        return;
      }
      if (amount > (parseFloat(p.available_balance || "0") || 0)) {
        alert("可用餘額不足。");
        return;
      }
      if (amount > (parseFloat(p.user_remaining_quota_amount || "0") || 0)) {
        alert("超過個人剩餘額度。");
        return;
      }
      if (this.submitting) return;
      var self = this;
      this.submitting = true;
      userApi
        .createFinancialSubscription({
          product_code: p.product_code,
          amount: amount.toFixed(8)
        })
        .then(function () {
          self.closeDetail();
          return self.fetchFinancialData();
        })
        .catch(function (error) {
          alert((error && error.message) || "理財申購失敗");
        })
        .finally(function () {
          self.submitting = false;
        });
    }
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
