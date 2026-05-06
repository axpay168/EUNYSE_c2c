<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/user" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">我的資產</div>
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/setting/fundRecord" @click="openFundRecords"><span class="material-symbols-outlined">history</span></a>
    </header>
    <main class="pt-20 px-4 max-w-3xl mx-auto space-y-5">
      <section class="glass-panel rounded-[24px] p-5 overflow-hidden relative">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-fixed/70 via-white/60 to-primary-container/70 opacity-90"></div>
        <div class="relative z-10">
          <div class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">總資產估值</div>
          <div id="wallet-total-estimate" class="font-headline text-4xl mt-2 tabular-nums">{{ totalEstimateDisplay }}</div>
          <div v-if="walletLoading" class="mt-2 inline-flex items-center gap-2 text-xs text-on-surface-variant" aria-label="資產同步中">
            <span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-primary/30 border-t-primary"></span>
          </div>
          <div v-else-if="walletError" class="mt-2 text-xs text-error">{{ walletError }}</div>
        </div>
      </section>
      <section class="space-y-2 sm:space-y-3" aria-label="快捷充值與提領">
        <div class="grid grid-cols-2 gap-2 sm:gap-3">
          <a class="glass-panel rounded-[24px] p-4 sm:p-5 text-center" href="#/pages/setting/mixrecharge" title="USDT 鏈上充值"><span class="material-symbols-outlined text-primary text-[1.85rem] leading-none sm:text-4xl">south_west</span><div class="font-semibold mt-2 sm:mt-2.5 text-base sm:text-lg leading-snug">USDT 充值</div></a>
          <button type="button" class="glass-panel rounded-[24px] p-4 sm:p-5 text-center w-full font-body border-0 cursor-pointer appearance-none text-on-surface" title="法幣充值" @click="openFiatRechargeModal"><span class="material-symbols-outlined text-primary text-[1.85rem] leading-none sm:text-4xl">savings</span><div class="font-semibold mt-2 sm:mt-2.5 text-base sm:text-lg leading-snug">法幣充值</div></button>
        </div>
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
          <button type="button" class="glass-panel rounded-[24px] p-4 sm:p-5 text-center w-full font-body border-0 cursor-pointer appearance-none text-on-surface" :title="$t('phrases.USDT 鏈上提領')" @click="openUsdtWithdrawModal"><span class="material-symbols-outlined text-primary text-[1.85rem] leading-none sm:text-4xl">currency_bitcoin</span><div class="font-semibold mt-2 sm:mt-2.5 text-[15px] sm:text-lg leading-snug">USDT 提領</div></button>
          <a class="glass-panel rounded-[24px] p-4 sm:p-5 text-center" href="#/pages/setting/withdraw" title="法幣提現"><span class="material-symbols-outlined text-primary text-[1.85rem] leading-none sm:text-4xl">payments</span><div class="font-semibold mt-2 sm:mt-2.5 text-[15px] sm:text-lg leading-snug">法幣提現</div></a>
          <a class="glass-panel rounded-[24px] p-4 sm:p-5 text-center ring-1 ring-primary/20" href="#/pages/setting/eurSwapWithdraw" title="EUR 換 USDT 提領"><span class="material-symbols-outlined text-primary text-[1.85rem] leading-none sm:text-4xl">currency_exchange</span><div class="font-semibold mt-2 sm:mt-2.5 text-[15px] sm:text-lg leading-snug">換匯提領</div></a>
        </div>
      </section>
      <div id="fiat-recharge-service-modal" class="fixed inset-0 z-[120] items-center justify-center bg-black/45 p-4" :class="fiatRechargeModalOpen ? 'flex' : 'hidden'" role="dialog" aria-modal="true" aria-labelledby="fiat-recharge-service-title" @click.self="closeFiatRechargeModal">
        <div class="relative w-full max-w-sm rounded-2xl border border-primary/10 bg-white p-5 pt-6 shadow-[0_12px_40px_rgba(33,79,131,0.12)]" @click.stop>
          <button
            type="button"
            class="absolute right-2 top-2 rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-container-low"
            aria-label="關閉"
            @click="closeFiatRechargeModal"
          >
            <span class="material-symbols-outlined text-[22px] leading-none">close</span>
          </button>
          <h2 id="fiat-recharge-service-title" class="font-headline px-8 text-center text-lg font-bold text-on-surface">法幣充值</h2>
          <p class="mt-4 text-center text-sm leading-relaxed text-on-surface">請聯繫客服</p>
          <button type="button" class="mt-6 w-full rounded-full border border-primary/25 bg-primary-container/60 py-3 text-sm font-bold text-primary-dim transition-colors hover:bg-primary-container" @click="closeFiatRechargeModal">知道了</button>
        </div>
      </div>
      <div id="usdt-withdraw-limit-modal" class="fixed inset-0 z-[120] items-center justify-center bg-black/45 p-4" :class="usdtWithdrawModalOpen ? 'flex' : 'hidden'" role="dialog" aria-modal="true" aria-labelledby="usdt-withdraw-limit-title" @click.self="closeUsdtWithdrawModal">
        <div class="relative w-full max-w-sm rounded-2xl border border-primary/10 bg-white p-5 pt-6 shadow-[0_12px_40px_rgba(33,79,131,0.12)]" @click.stop>
          <button
            type="button"
            class="absolute right-2 top-2 rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-container-low"
            aria-label="關閉"
            @click="closeUsdtWithdrawModal"
          >
            <span class="material-symbols-outlined text-[22px] leading-none">close</span>
          </button>
          <h2 id="usdt-withdraw-limit-title" class="sr-only">{{ $t('phrases.usdtWithdrawLimitTitle') }}</h2>
          <p class="mt-3 px-4 text-center text-base font-semibold leading-relaxed text-on-surface">
            <span class="block">{{ $t('phrases.usdtWithdrawLimitLine1') }}</span>
            <span class="block">{{ $t('phrases.usdtWithdrawLimitLine2') }}</span>
          </p>
          <button type="button" class="mt-6 w-full rounded-full border border-primary/25 bg-primary-container/60 py-3 text-sm font-bold text-primary-dim transition-colors hover:bg-primary-container" @click="closeUsdtWithdrawModal">{{ $t('phrases.知道了') }}</button>
        </div>
      </div>
      <section class="glass-panel rounded-[24px] p-5">
        <div class="flex items-center justify-between mb-4"><h2 class="font-headline text-xl">資產分布</h2><a class="text-sm text-primary-dim font-semibold" href="#/pages/setting/fundRecord" @click="openFundRecords">資金明細</a></div>
        <div class="space-y-3">
          <div class="rounded-2xl bg-surface-bright px-4 py-4">
            <div class="flex items-center justify-between gap-3">
              <div class="font-semibold">USDT</div>
              <div class="text-right"><div id="wallet-usdt-available" class="font-headline text-xl">{{ walletAmount('cash_usdt') }}</div><div class="text-xs text-on-surface-variant">凍結 {{ walletReserved('cash_usdt') }}</div></div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
              <a class="wallet-asset-action flex h-11 items-center justify-center rounded-xl border border-primary/25 bg-primary-container/50 text-base font-bold text-primary-dim transition-colors hover:bg-primary-container" href="#/pages/setting/mixrecharge">充值</a>
              <button type="button" class="wallet-asset-action wallet-asset-action--secondary flex h-11 items-center justify-center rounded-xl text-base font-bold transition-all border-0 cursor-pointer font-body" @click="openUsdtWithdrawModal">提領</button>
            </div>
          </div>
          <div class="rounded-2xl bg-surface-bright px-4 py-4">
            <div class="flex items-center justify-between gap-3">
              <div class="font-semibold">EUR</div>
              <div class="text-right"><div id="wallet-eur-available" class="font-headline text-xl">{{ walletAmount('eur') }}</div><div class="text-xs text-on-surface-variant">凍結 {{ walletReserved('eur') }}</div></div>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2">
              <a class="wallet-asset-action flex h-11 items-center justify-center rounded-xl border border-primary/25 bg-primary-container/50 text-sm sm:text-base font-bold text-primary-dim transition-colors hover:bg-primary-container px-1" href="#/pages/setting/eurDeposit">充值</a>
              <a class="wallet-asset-action wallet-asset-action--secondary flex h-11 items-center justify-center rounded-xl text-sm sm:text-base font-bold transition-all px-1" href="#/pages/setting/withdraw">提領</a>
              <a class="wallet-asset-action wallet-asset-action--outline flex h-11 items-center justify-center rounded-xl text-sm sm:text-base font-bold transition-all px-1" href="#/pages/setting/eurSwapWithdraw" title="EUR→USDT">換匯</a>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURFOREX - 我的資產 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/wallet.html 為唯一基準逐字遷移
 * 原稿無內聯 JS，僅外部 marble-bg.js、back-nav.js
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--wallet",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-24"
];

export default {
  name: "WalletH5",
  data: function () {
    return {
      fiatRechargeModalOpen: false,
      usdtWithdrawModalOpen: false,
      walletLoading: false,
      walletError: "",
      walletMap: {},
      usdtToEurRate: 0
    };
  },
  computed: {
    totalEstimateDisplay: function () {
      var eur = this.walletNumber("eur", "available_balance") + this.walletNumber("eur", "reserved_balance");
      var usdt = this.walletNumber("cash_usdt", "available_balance") + this.walletNumber("cash_usdt", "reserved_balance");
      return "EUR " + this.formatAmount(eur + usdt * this.usdtToEurRate, 2);
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "wallet");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.fetchWallets();
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
    openFiatRechargeModal: function () {
      this.fiatRechargeModalOpen = true;
    },
    closeFiatRechargeModal: function () {
      this.fiatRechargeModalOpen = false;
    },
    openUsdtWithdrawModal: function () {
      this.usdtWithdrawModalOpen = true;
    },
    closeUsdtWithdrawModal: function () {
      this.usdtWithdrawModalOpen = false;
    },
    openFundRecords: function (event) {
      if (event && event.preventDefault) event.preventDefault();
      if (window.location && window.location.hash === "#/pages/setting/fundRecord") {
        window.location.hash = "#/pages/setting/wallet";
      }
      window.location.hash = "#/pages/setting/fundRecord";
    },
    fetchWallets: function () {
      var self = this;
      this.walletLoading = true;
      this.walletError = "";
      Promise.all([userApi.overview(), userApi.appConfig()])
        .then(function (results) {
          var data = results[0] || {};
          var config = results[1] || {};
          var map = data.wallet_map || {};
          if (!Object.keys(map).length && Array.isArray(data.wallets)) {
            data.wallets.forEach(function (item) {
              map[item.wallet_code] = item;
            });
          }
          self.walletMap = map;
          var finance = config.finance || {};
          var rate = parseFloat(finance.usdt_to_eur_rate || "0");
          self.usdtToEurRate = isNaN(rate) ? 0 : rate;
        })
        .catch(function (error) {
          self.walletError = (error && error.message) || "資產載入失敗";
        })
        .finally(function () {
          self.walletLoading = false;
        });
    },
    walletNumber: function (code, field) {
      var wallet = this.walletMap[code] || {};
      var value = parseFloat(wallet[field] || "0");
      return isNaN(value) ? 0 : value;
    },
    walletAmount: function (code) {
      return this.formatAmount(this.walletNumber(code, "available_balance"), 2);
    },
    walletReserved: function (code) {
      return this.formatAmount(this.walletNumber(code, "reserved_balance"), 2);
    },
    formatAmount: function (value, digits) {
      return Number(value || 0).toLocaleString("en-GB", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");

.wallet-asset-action {
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 8px 18px rgba(46, 100, 134, 0.08);
}

.wallet-asset-action--secondary {
  border: 1px solid rgba(46, 100, 134, 0.34);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(232, 246, 252, 0.96));
  color: #16374d;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.92),
    inset 0 -1px 0 rgba(46, 100, 134, 0.08),
    0 10px 20px rgba(46, 100, 134, 0.12);
}

.wallet-asset-action--secondary:hover {
  border-color: rgba(46, 100, 134, 0.5);
  background: linear-gradient(180deg, #ffffff, #dff4fb);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.95),
    0 12px 24px rgba(46, 100, 134, 0.16);
}

.wallet-asset-action--outline {
  border: 1px solid rgba(46, 100, 134, 0.42);
  background: linear-gradient(180deg, rgba(248, 253, 255, 0.96), rgba(234, 247, 252, 0.92));
  color: #1f5d81;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.9),
    0 8px 18px rgba(46, 100, 134, 0.1);
}

.wallet-asset-action--outline:hover {
  border-color: rgba(46, 100, 134, 0.58);
  background: linear-gradient(180deg, #ffffff, rgba(218, 241, 250, 0.95));
  color: #164a68;
}
</style>
