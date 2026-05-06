<template>
  <div>
    <!-- Ambient Lighting Gradients -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
      <div class="absolute top-[-20%] left-[-10%] w-[70vw] h-[70vw] rounded-full bg-gradient-radial from-primary/10 to-transparent blur-3xl"></div>
      <div class="absolute bottom-[-10%] right-[-10%] w-[60vw] h-[60vw] rounded-full bg-gradient-radial from-secondary/5 to-transparent blur-3xl"></div>
    </div>
    <!-- Top Navigation (Transactional intent -> no bottom nav, top nav adapted to back/close) -->
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="sell-nav-icon-btn flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/index/hall" @click="backGo($event, '#/pages/index/hall')"><span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 0;">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">出售 USDT</div>
      <a class="sell-nav-icon-btn flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/index/serviceCenter">
        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 0;">help</span>
      </a>
    </header>
    <!-- Main Canvas -->
    <main class="relative z-10 pt-20 pb-32 px-6 max-w-lg mx-auto flex flex-col gap-8 h-full">
      <!-- Merchant Info Card -->
      <section class="bg-surface-container-high/80 backdrop-blur-2xl rounded-2xl p-5 shadow-[0_12px_32px_rgba(33,79,131,0.08)] border border-primary/10 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-50"></div>
        <div class="relative z-10 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-surface-container-lowest flex items-center justify-center border border-primary/10 shadow-[0_0_15px_rgba(86,175,232,0.12)]">
              <span class="font-headline text-primary font-bold text-xl">{{ avatarLetter }}</span>
            </div>
            <div>
              <h2 class="font-headline font-bold text-lg text-on-surface tracking-tight">{{ listing.nickname || '商家' }}</h2>
              <div class="flex items-center gap-2 mt-0.5">
                <span class="material-symbols-outlined text-primary text-[14px]" style="font-variation-settings: 'FILL' 1;">verified</span>
                <span class="font-body text-xs text-on-surface-variant">商家 · 成交率 {{ listing.completion_rate || '—' }}</span>
              </div>
            </div>
          </div>
          <div class="text-right">
            <div class="font-body text-xs text-on-surface-variant mb-1 uppercase tracking-widest">單價</div>
            <div class="font-headline font-bold text-primary text-lg">{{ formatAmount(unitEurPerUsdt, 2) }} EUR</div>
          </div>
        </div>
        <div class="relative z-10 mt-5 pt-4 border-t border-outline-variant/15 flex justify-between">
          <div class="flex flex-col gap-1">
            <span class="font-body text-[10px] text-on-surface-variant uppercase tracking-widest">可交易</span>
            <span class="font-headline text-sm font-semibold">{{ formatAmount(maxTradableUsdt, 2) }} USDT</span>
          </div>
          <div class="flex flex-col gap-1 text-right">
            <span class="font-body text-[10px] text-on-surface-variant uppercase tracking-widest">限制</span>
            <span class="font-headline text-sm font-semibold">{{ formatAmount(minAmount, 2) }} - {{ formatAmount(maxAmount, 2) }} USDT</span>
          </div>
        </div>
        <p v-if="loadErrorKey" class="relative z-10 mt-4 rounded-xl bg-error/5 px-3 py-2 text-xs text-error">{{ $t('phrases.' + loadErrorKey) }}</p>
      </section>
      <!-- Trading Input + 估價 + 送出（緊湊排版，估價無外框） -->
      <section class="flex flex-col gap-3">
        <div class="flex justify-between items-center px-1">
          <label class="font-body text-sm text-on-surface-variant">我要賣出</label>
          <span class="font-body text-xs text-on-surface-variant">餘額： <span id="sell-usdt-balance" class="text-on-surface font-semibold">{{ formatAmount(maxUsdtBalance, 2) }} USDT</span></span>
        </div>
        <div class="bg-surface-container-highest/55 rounded-2xl border border-outline-variant/25 p-1 relative overflow-hidden group focus-within:border-primary/50 focus-within:shadow-[0_0_20px_rgba(86,175,232,0.12)] transition-all duration-300">
          <div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-primary/30 to-transparent opacity-0 group-focus-within:opacity-100 transition-opacity"></div>
          <div class="flex items-center h-20 px-4">
            <input id="sell-amount-input" class="bg-transparent border-none outline-none text-4xl font-headline font-bold text-on-surface w-full placeholder:text-on-surface-variant/30" placeholder="0.00" type="text" inputmode="decimal" autocomplete="off" v-model="amount" />
            <div class="flex items-center gap-3 shrink-0 ml-4">
              <button type="button" class="bg-primary/10 hover:bg-primary/20 text-primary font-label text-xs uppercase tracking-widest font-bold px-3 py-1.5 rounded-full transition-colors" @click="fillMaxSell">全部</button>
              <div class="h-8 w-px bg-outline-variant/30"></div>
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-full bg-primary-container flex items-center justify-center">
                  <span class="text-[10px] font-bold text-primary-dim">₮</span>
                </div>
                <span class="font-headline font-bold text-lg">USDT</span>
              </div>
            </div>
          </div>
        </div>
        <div class="sell-estimate-inline w-full pt-0.5">
          <span class="sell-estimate-inline__label">Estimate</span>
          <div class="sell-estimate-inline__value">
            <span>{{ estimatedEurDisplay }}</span>
            <small>EUR</small>
          </div>
        </div>
        <div class="sell-inline-cta">
          <button id="sell-submit-btn" type="button" class="sell-submit-btn w-full h-14 rounded-full font-headline font-bold text-base tracking-wide flex items-center justify-center transition-all duration-300 relative overflow-hidden group" :disabled="submitting || !listing.id" @click="submitSell">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-in-out"></div>
            {{ submitting ? '提交中...' : '確認賣出' }}
          </button>
          <p v-if="submitFeedbackKey || submitFeedbackRaw" class="mt-3 text-center text-xs" :class="submitError ? 'text-error' : 'text-primary-dim'">
          <template v-if="submitFeedbackKey">{{ $t('phrases.' + submitFeedbackKey) }}</template>
          <template v-else>{{ submitFeedbackRaw }}</template>
        </p>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'
import { pickBestOneClickListing } from '@/utils/listingPick'

/**
 * EURFOREX - 出售 USDT (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/sell.html 為唯一基準逐字遷移
 * 原稿無內聯 JS，僅外部 back-nav.js
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--sell",
  "bg-surface",
  "text-on-surface",
  "min-h-screen",
  "relative",
  "overflow-x-hidden",
  "antialiased"
];

export default {
  name: "SellH5",
  data: function () {
    return {
      amount: "",
      listing: {},
      loadErrorKey: "",
      submitFeedbackKey: "",
      submitFeedbackRaw: "",
      submitError: false,
      submitting: false,
      /** 與商家卡片「單價」一致：每 1 USDT 可得 EUR（原型頁） */
      unitEurPerUsdt: 0.93,
      /** 與「餘額」展示一致，供「全部」帶入 */
      maxUsdtBalance: 0,
      maxTradableUsdt: 0,
      minAmount: 0,
      maxAmount: 0
    };
  },
  computed: {
    avatarLetter: function () {
      return String(this.listing.nickname || "P").charAt(0).toUpperCase();
    },
    parsedSellUsdt: function () {
      var raw = this.amount != null ? String(this.amount).replace(/,/g, "").trim() : "";
      var n = parseFloat(raw);
      if (isNaN(n) || n < 0) return 0;
      return n;
    },
    estimatedEur: function () {
      return this.parsedSellUsdt * this.unitEurPerUsdt;
    },
    estimatedEurDisplay: function () {
      var v = this.estimatedEur;
      if (!v || v <= 0) return "0.00";
      return v.toLocaleString("en-GB", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "sell");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.fetchPageData();
  },
  beforeDestroy: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
  },
  methods: {
    backGo: function (event, fallback) {
      if (window.EurforexBack && typeof window.EurforexBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurforexBack.go(fallback);
      }
    },
    fillMaxSell: function () {
      var cap = Math.min(this.maxUsdtBalance, this.maxTradableUsdt || this.maxUsdtBalance);
      this.amount = (Math.floor(cap * 100) / 100).toFixed(2);
    },
    formatAmount: function (value, digits) {
      return Number(value || 0).toLocaleString("en-GB", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    },
    currentListingId: function () {
      var hash = window.location.hash || "";
      var query = hash.split("?")[1] || "";
      var params = new URLSearchParams(query);
      return params.get("listing_id") || "";
    },
    fetchPageData: function () {
      var self = this;
      var listingId = this.currentListingId();
      this.loadErrorKey = "";

      function applyListingFromDetail(listing) {
        var L = listing || {};
        if (L.id) {
          self.listing = L;
          self.unitEurPerUsdt = parseFloat(L.price || "0.93") || 0.93;
          self.maxTradableUsdt = parseFloat(L.available_amount || "0") || 0;
          self.minAmount = parseFloat(L.min_amount || "0") || 0;
          self.maxAmount = parseFloat(L.max_amount || "0") || self.maxTradableUsdt;
          self.loadErrorKey = "";
        } else {
          self.listing = {};
          self.loadErrorKey = "orderSellListingNotFound";
        }
      }

      function applyUsdtWallet(overview) {
        var map = (overview && overview.wallet_map) || {};
        var cashUsdt = map.cash_usdt || {};
        self.maxUsdtBalance = parseFloat(cashUsdt.available_balance || "0") || 0;
      }

      userApi
        .overview()
        .then(function (overview) {
          var ov = overview || {};
          applyUsdtWallet(ov);
          var uid = (ov.user && ov.user.id) || 0;

          if (listingId) {
            return userApi.listingDetail(listingId).then(function (data) {
              applyListingFromDetail((data && data.listing) || {});
            });
          }
          return userApi.listings("?side=sell").then(function (data) {
            var items = (data && data.items) || [];
            var best = pickBestOneClickListing(items, "sell", uid);
            if (best && best.id) {
              return userApi.listingDetail(String(best.id)).then(function (d) {
                applyListingFromDetail((d && d.listing) || {});
              });
            }
            self.listing = {};
            self.loadErrorKey = "orderSellNoListingAvailable";
          });
        })
        .catch(function () {
          self.listing = {};
          self.loadErrorKey = "orderSellLoadFailed";
          self.maxUsdtBalance = 0;
        });
    },
    submitSell: function () {
      var amount = this.parsedSellUsdt;
      this.submitFeedbackKey = "";
      this.submitFeedbackRaw = "";
      if (!this.listing.id) {
        this.submitError = true;
        this.submitFeedbackKey = "orderSellNoListingSubmit";
        return;
      }
      if (!amount || amount <= 0) {
        this.submitError = true;
        this.submitFeedbackKey = "orderSellEnterAmount";
        return;
      }
      if (this.minAmount && amount < this.minAmount) {
        this.submitError = true;
        this.submitFeedbackKey = "orderSellBelowMin";
        return;
      }
      if (this.maxAmount && amount > this.maxAmount) {
        this.submitError = true;
        this.submitFeedbackKey = "orderSellAboveMax";
        return;
      }
      var self = this;
      this.submitting = true;
      userApi
        .createOrder({
          listing_id: this.listing.id,
          amount: amount.toFixed(8)
        })
        .then(function (data) {
          self.submitError = false;
          self.submitFeedbackKey = "orderSellCreated";
          window.location.href = "#/pages/setting/orderDetail?id=" + data.order_id;
        })
        .catch(function (error) {
          self.submitError = true;
          self.submitFeedbackKey = "";
          self.submitFeedbackRaw = (error && error.message) || self.$t("phrases.orderSellCreateFailed");
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

.sell-nav-icon-btn .material-symbols-outlined {
  font-size: 1.45rem;
  line-height: 1;
}

/* 估價：無卡片外框、字級縮小、水平置中 */
.sell-estimate-inline {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
  text-align: center;
}

.sell-estimate-inline__label {
  color: #6f8198;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.sell-estimate-inline__value {
  display: flex;
  align-items: baseline;
  gap: 0.28rem;
  color: #172a41;
  font-family: var(--font-headline, inherit);
  font-weight: 800;
}

.sell-estimate-inline__value span {
  font-size: 1.35rem;
  line-height: 1.1;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
}

.sell-estimate-inline__value small {
  color: #708299;
  font-size: 0.72rem;
  font-weight: 700;
}

.sell-inline-cta {
  margin-top: 0;
}

.sell-submit-btn {
  background: linear-gradient(135deg, #56afe8 0%, #2f73cd 100%);
  color: #f9fdff;
  box-shadow: 0 14px 30px rgba(47, 115, 205, 0.24);
}

.sell-submit-btn:hover {
  filter: brightness(1.04);
  box-shadow: 0 16px 36px rgba(47, 115, 205, 0.3);
}

.sell-submit-btn:disabled {
  opacity: 0.72;
  cursor: not-allowed;
}
</style>
