<template>
  <div>
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden"><div class="absolute top-[-20%] left-[-10%] w-[70vw] h-[70vw] rounded-full bg-[radial-gradient(circle,var(--tw-gradient-stops))] from-primary/10 to-transparent blur-3xl"></div><div class="absolute bottom-[-10%] right-[-10%] w-[60vw] h-[60vw] rounded-full bg-[radial-gradient(circle,var(--tw-gradient-stops))] from-secondary/5 to-transparent blur-3xl"></div></div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="buy-nav-icon-btn flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/index/hall" @click="backGo($event, '#/pages/index/hall')"><span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 0;">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">購買 USDT</div>
      <a class="buy-nav-icon-btn flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/index/serviceCenter"><span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 0;">help</span></a>
    </header>
    <main class="relative z-10 pt-20 pb-32 px-6 max-w-lg mx-auto flex flex-col gap-8 h-full">
      <section class="bg-surface-container-high/80 backdrop-blur-2xl rounded-2xl p-5 shadow-[0_12px_32px_rgba(33,79,131,0.08)] border border-primary/10 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-50"></div>
        <div class="relative z-10 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-surface-bright flex items-center justify-center border border-primary/10 shadow-[0_0_15px_rgba(86,175,232,0.12)]">
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
      <!-- 與賣出頁一致：輸入 + 緊湊估價（置中）+ 主按鈕 -->
      <section class="flex flex-col gap-3">
        <div class="flex justify-between items-center px-1">
          <label class="font-body text-sm text-on-surface-variant">我要買入</label>
          <span class="font-body text-xs text-on-surface-variant"
            >餘額：<span id="buy-eur-balance" class="text-on-surface font-semibold">{{ formatAmount(maxEurBalance, 2) }} EUR</span></span
          >
        </div>
        <div
          class="bg-surface-container-highest/55 rounded-2xl border border-outline-variant/25 p-1 relative overflow-hidden group focus-within:border-primary/50 focus-within:shadow-[0_0_20px_rgba(86,175,232,0.12)] transition-all duration-300"
        >
          <div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-primary/30 to-transparent opacity-0 group-focus-within:opacity-100 transition-opacity"></div>
          <div class="flex items-center h-20 px-4">
            <input
              id="buy-amount-input"
              class="bg-transparent border-none outline-none text-4xl font-headline font-bold text-on-surface w-full placeholder:text-on-surface-variant/30"
              placeholder="0.00"
              type="text"
              inputmode="decimal"
              autocomplete="off"
              v-model="amountInput"
            />
            <div class="flex items-center gap-3 shrink-0 ml-4">
              <button
                type="button"
                class="bg-primary/10 hover:bg-primary/20 text-primary font-label text-xs uppercase tracking-widest font-bold px-3 py-1.5 rounded-full transition-colors"
                @click="fillBuyMax"
              >
                全部
              </button>
              <div class="h-8 w-px bg-outline-variant/30"></div>
              <button
                type="button"
                id="buy-amount-currency"
                class="font-headline min-w-[3.25rem] text-center text-lg font-bold tabular-nums text-on-surface transition-colors hover:text-primary-dim"
                aria-label="切換輸入幣種 EUR 或 USDT"
                @click="toggleMode"
              >
                {{ mode }}
              </button>
            </div>
          </div>
        </div>
        <div class="buy-estimate-inline w-full pt-0.5">
          <span class="buy-estimate-inline__label">{{ estimateSectionLabel }}</span>
          <div class="buy-estimate-inline__value">
            <span>{{ estimatePrimaryDisplay }}</span>
            <small>{{ estimatePrimaryUnit }}</small>
          </div>
        </div>
        <div class="buy-inline-cta">
          <button
            id="buy-submit-btn"
            type="button"
            class="sell-submit-btn w-full h-14 rounded-full font-headline font-bold text-base tracking-wide flex items-center justify-center transition-all duration-300 relative overflow-hidden group"
            :disabled="submitting || !listing.id"
            @click="submitBuy"
          >
            <div
              class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-in-out"
            ></div>
            {{ submitting ? '提交中...' : '確認買入' }}
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
 * EURFOREX - 購買 USDT (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/buy.html 為唯一基準逐字遷移
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--buy",
  "bg-surface",
  "text-on-surface",
  "min-h-screen",
  "relative",
  "overflow-x-hidden",
  "antialiased"
];

export default {
  name: "BuyH5",
  data: function () {
    return {
      amountInput: "",
      mode: "EUR",
      listing: {},
      loadErrorKey: "",
      submitFeedbackKey: "",
      submitFeedbackRaw: "",
      submitError: false,
      submitting: false,
      /** 與商家卡片「單價」一致：每 1 USDT 標價 EUR */
      unitEurPerUsdt: 0.95,
      maxEurBalance: 0,
      maxTradableUsdt: 0,
      minAmount: 0,
      maxAmount: 0
    };
  },
  computed: {
    avatarLetter: function () {
      return String(this.listing.nickname || "A").charAt(0).toUpperCase();
    },
    parsedBuyAmount: function () {
      var raw = this.amountInput != null ? String(this.amountInput).replace(/,/g, "").trim() : "";
      var n = parseFloat(raw);
      return isNaN(n) || n < 0 ? 0 : n;
    },
    /** EUR 輸入 → 可得 USDT；USDT 輸入 → 約需支付 EUR */
    estimatePrimaryDisplay: function () {
      var p = this.parsedBuyAmount;
      if (!p) return "0.00";
      if (this.mode === "EUR") {
        var usdt = p / this.unitEurPerUsdt;
        if (!isFinite(usdt)) return "0.00";
        return usdt.toLocaleString("en-GB", { minimumFractionDigits: 2, maximumFractionDigits: 6 });
      }
      var eur = p * this.unitEurPerUsdt;
      return eur.toLocaleString("en-GB", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    estimatePrimaryUnit: function () {
      return this.mode === "EUR" ? "USDT" : "EUR";
    },
    estimateSectionLabel: function () {
      return this.mode === "EUR" ? "預估可得" : "約需支付";
    },
    orderUsdtAmount: function () {
      var p = this.parsedBuyAmount;
      if (!p) return 0;
      return this.mode === "EUR" ? p / this.unitEurPerUsdt : p;
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "buy");
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
    toggleMode: function () {
      this.mode = this.mode === "EUR" ? "USDT" : "EUR";
    },
    fillBuyMax: function () {
      if (this.mode === "EUR") {
        this.amountInput = this.maxEurBalance.toFixed(2);
        return;
      }
      var cap = Math.min(this.maxTradableUsdt, this.maxEurBalance / this.unitEurPerUsdt);
      this.amountInput = (Math.floor(cap * 100) / 100).toFixed(2);
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
          self.unitEurPerUsdt = parseFloat(L.price || "0.95") || 0.95;
          self.maxTradableUsdt = parseFloat(L.available_amount || "0") || 0;
          self.minAmount = parseFloat(L.min_amount || "0") || 0;
          self.maxAmount = parseFloat(L.max_amount || "0") || self.maxTradableUsdt;
          self.loadErrorKey = "";
        } else {
          self.listing = {};
          self.loadErrorKey = "orderBuyListingNotFound";
        }
      }

      function applyEurWallet(overview) {
        var map = (overview && overview.wallet_map) || {};
        var eur = map.eur || {};
        self.maxEurBalance = parseFloat(eur.available_balance || "0") || 0;
      }

      userApi
        .overview()
        .then(function (overview) {
          var ov = overview || {};
          applyEurWallet(ov);
          var uid = (ov.user && ov.user.id) || 0;

          if (listingId) {
            return userApi.listingDetail(listingId).then(function (data) {
              applyListingFromDetail((data && data.listing) || {});
            });
          }
          return userApi.listings("?side=buy").then(function (data) {
            var items = (data && data.items) || [];
            var best = pickBestOneClickListing(items, "buy", uid);
            if (best && best.id) {
              return userApi.listingDetail(String(best.id)).then(function (d) {
                applyListingFromDetail((d && d.listing) || {});
              });
            }
            self.listing = {};
            self.loadErrorKey = "orderBuyNoListingAvailable";
          });
        })
        .catch(function () {
          self.listing = {};
          self.loadErrorKey = "orderBuyLoadFailed";
          self.maxEurBalance = 0;
        });
    },
    submitBuy: function () {
      var amount = this.orderUsdtAmount;
      this.submitFeedbackKey = "";
      this.submitFeedbackRaw = "";
      if (!this.listing.id) {
        this.submitError = true;
        this.submitFeedbackKey = "orderBuyNoListingSubmit";
        return;
      }
      if (!amount || amount <= 0) {
        this.submitError = true;
        this.submitFeedbackKey = "orderBuyEnterAmount";
        return;
      }
      if (this.minAmount && amount < this.minAmount) {
        this.submitError = true;
        this.submitFeedbackKey = "orderBuyBelowMin";
        return;
      }
      if (this.maxAmount && amount > this.maxAmount) {
        this.submitError = true;
        this.submitFeedbackKey = "orderBuyAboveMax";
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
          self.submitFeedbackKey = "orderBuyCreated";
          window.location.href = "#/pages/setting/orderDetail?id=" + data.order_id;
        })
        .catch(function (error) {
          self.submitError = true;
          self.submitFeedbackKey = "";
          self.submitFeedbackRaw = (error && error.message) || self.$t("phrases.orderBuyCreateFailed");
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

.buy-nav-icon-btn .material-symbols-outlined {
  font-size: 1.45rem;
  line-height: 1;
}

/* 與 sell.vue 估價列一致：緊湊、無外框、水平置中 */
.buy-estimate-inline {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
  text-align: center;
}

.buy-estimate-inline__label {
  color: #6f8198;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.buy-estimate-inline__value {
  display: flex;
  align-items: baseline;
  gap: 0.28rem;
  color: #172a41;
  font-family: var(--font-headline, inherit);
  font-weight: 800;
}

.buy-estimate-inline__value span {
  font-size: 1.35rem;
  line-height: 1.1;
  letter-spacing: -0.03em;
  font-variant-numeric: tabular-nums;
}

.buy-estimate-inline__value small {
  color: #708299;
  font-size: 0.72rem;
  font-weight: 700;
}

.buy-inline-cta {
  margin-top: 0;
}

/* 與賣出頁主按鈕同款 */
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
