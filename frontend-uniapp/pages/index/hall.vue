<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/setting/wallet" aria-label="我的資產">
        <span class="material-symbols-outlined">account_balance_wallet</span>
      </a>
      <div class="font-headline text-lg font-bold text-on-surface">交易大廳</div>
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" aria-label="訂單通知" href="#/pages/setting/myTask">
        <span class="material-symbols-outlined">notifications</span>
      </a>
    </header>

    <main class="pt-20 px-4 md:px-8 max-w-7xl mx-auto flex flex-col gap-8 md:gap-12">
      <section class="flex flex-col gap-6">
        <div class="grid grid-cols-5 grid-rows-[auto_auto] gap-x-4 gap-y-3 md:grid-cols-3 md:gap-x-6 md:gap-y-3">
          <div class="relative col-span-3 col-start-1 row-start-1 flex flex-col overflow-hidden rounded-xl bg-gradient-to-br from-primary-fixed to-primary-fixed-dim pb-5 pl-2.5 pr-5 pt-2.5 shadow-[0px_12px_32px_rgba(44,52,55,0.04)] md:col-span-2 md:pb-5 md:pl-4 md:pr-8 md:pt-2.5">
            <div class="absolute -right-12 -top-12 h-48 w-48 rounded-full bg-white/10 blur-2xl"></div>
            <div class="leading-none">
              <h2 class="font-label text-xs md:text-sm font-semibold leading-tight text-on-primary-fixed-variant/80 mb-0">可用餘額</h2>
              <div class="font-headline mt-0.5 flex w-full flex-wrap items-end justify-between gap-2 leading-none text-3xl font-bold tracking-tight text-on-primary-fixed md:text-5xl">
                <span class="shrink-0 tabular-nums">{{ formatMoney(usdtAvailableBalance, 2) }}</span>
                <span class="font-body text-lg font-normal leading-none opacity-80 md:text-xl">USDT</span>
              </div>
            </div>
          </div>
          <div class="col-span-2 col-start-4 row-span-2 row-start-1 flex min-h-0 flex-col rounded-xl bg-surface-container-lowest p-5 shadow-[0px_12px_32px_rgba(44,52,55,0.04)] md:col-span-1 md:col-start-3 md:p-6">
            <h2 class="font-label shrink-0 text-xs md:text-sm font-semibold text-on-surface-variant">進行中訂單</h2>
            <div class="flex min-h-0 flex-1 flex-col items-start justify-center py-2">
              <div class="w-full text-left font-headline text-3xl font-bold leading-none text-on-surface">{{ activeOrderCount }}</div>
            </div>
            <a class="inline-flex shrink-0 items-center gap-1 self-start text-sm font-semibold text-primary transition-colors hover:text-primary-dim" href="#/pages/setting/myTask?filter=progress">
              查看詳情 <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
          </div>
          <div class="col-span-3 col-start-1 row-start-2 md:col-span-2">
            <a class="flex w-full items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-dim py-2.5 text-sm font-bold text-on-primary shadow-[0px_6px_16px_rgba(46,100,134,0.22)] transition-[transform,box-shadow] hover:shadow-[0px_8px_20px_rgba(46,100,134,0.28)] active:scale-[0.99]" href="#/pages/index/buy">一鍵買入</a>
          </div>
        </div>
      </section>

      <section class="flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex w-full rounded-full bg-surface-container-high p-1 shadow-sm md:w-auto" role="tablist" aria-label="篩選賣出或買入商家">
          <button type="button" id="lobby-tab-sell" class="lobby-mode-tab flex-1 rounded-full px-8 py-2 text-center text-sm font-semibold transition-all duration-200 md:flex-none"
            :class="mode === 'sell' ? 'bg-surface-container-lowest text-primary shadow-[0px_4px_12px_rgba(44,52,55,0.08)]' : 'text-on-surface-variant hover:text-on-surface'"
            role="tab" :aria-selected="mode === 'sell' ? 'true' : 'false'"
            @click="setMode('sell')">賣出</button>
          <button type="button" id="lobby-tab-buy" class="lobby-mode-tab flex-1 rounded-full px-8 py-2 text-center text-sm font-semibold transition-all duration-200 md:flex-none"
            :class="mode === 'buy' ? 'bg-surface-container-lowest text-primary shadow-[0px_4px_12px_rgba(44,52,55,0.08)]' : 'text-on-surface-variant hover:text-on-surface'"
            role="tab" :aria-selected="mode === 'buy' ? 'true' : 'false'"
            @click="setMode('buy')">買入</button>
        </div>
        <div class="flex gap-2 w-full md:w-auto">
          <div class="relative flex-1 md:w-64">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant/70 text-[20px]">search</span>
            <input class="w-full bg-surface-container-low border border-transparent focus:bg-surface-container-lowest focus:border-outline-variant/30 rounded-lg py-2 pl-10 pr-4 text-sm text-on-surface placeholder:text-on-surface-variant/50 transition-all outline-none focus:ring-0" placeholder="金額（USDT）" type="text" inputmode="decimal" v-model="amountFilter" />
          </div>
          <button type="button" class="bg-surface-container-low hover:bg-surface-container-highest text-on-surface p-2 rounded-lg transition-colors flex items-center justify-center border border-transparent hover:border-outline-variant/15" title="Refresh suppliers" aria-label="Refresh suppliers" @click="fetchListings">
            <span class="material-symbols-outlined text-[20px]">sync</span>
          </button>
        </div>
      </section>

      <section class="flex flex-col gap-6" aria-label="商家列表">
        <LoadingInlineSpinner v-if="listingsLoading && !listings.length" aria-label="掛單載入" />
        <p v-else-if="listingsError" class="text-sm text-red-600 px-1">{{ listingsError }}</p>
        <div id="lobby-offers-sell" class="flex flex-col gap-6" v-show="mode === 'sell' && !listingsError">
          <p v-if="!listingsLoading && !sellTabListings.length" class="text-sm text-on-surface-variant px-1 py-6 text-center rounded-xl bg-surface-container-lowest/80 border border-outline-variant/10">
            No suppliers
          </p>
          <article
            v-for="listing in sellTabListings"
            :key="'lobby-sell-' + listing.id"
            class="bg-surface-container-lowest rounded-xl p-6 shadow-[0px_12px_32px_rgba(44,52,55,0.04)] flex flex-col md:flex-row md:items-center justify-between gap-6 transition-all hover:shadow-[0px_16px_40px_rgba(44,52,55,0.06)] hover:-translate-y-0.5 duration-300 border border-outline-variant/5"
          >
            <div class="flex-1 flex gap-4 items-start">
              <div class="w-12 h-12 shrink-0 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-headline font-bold text-xl ring-2 ring-surface-container-high">{{ avatarLetter(listing.nickname) }}</div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-1 flex-wrap min-w-0">
                  <h4 class="font-bold text-on-surface text-base truncate min-w-0">{{ listing.nickname || '商戶' }}</h4>
                  <div class="flex items-center gap-1.5 shrink-0" aria-label="商戶認證標示">
                    <span v-if="listingShowVip(listing)" class="hall-tier hall-tier--vip">VIP</span>
                    <span v-if="listingShowPro(listing)" class="hall-tier hall-tier--pro">PRO</span>
                    <img
                      v-for="si in listingHallStarSlots(listing)"
                      :key="'sell-star-' + listing.id + '-' + si"
                      class="hall-certified-badge"
                      :src="certifiedBadgeSrc"
                      alt=""
                      aria-hidden="true"
                    />
                  </div>
                </div>
                <div class="flex items-center gap-3 text-xs text-on-surface-variant flex-wrap">
                  <span v-if="listing.completion_rate" class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">thumb_up</span> {{ listing.completion_rate }}</span>
                  <span class="text-on-surface-variant/80">庫存 {{ formatInventory(listing.available_amount) }} {{ listing.asset_code || 'USDT' }}</span>
                </div>
                <div class="mt-3 text-sm text-on-surface">
                  <span class="text-on-surface-variant mr-1">限制：</span> {{ formatListingAmount(listing.min_amount) }} - {{ formatListingAmount(listing.max_amount) }} {{ listing.asset_code || 'USDT' }}
                </div>
              </div>
            </div>
            <div class="hall-offer-actions flex w-full min-w-0 flex-row flex-nowrap items-center justify-between gap-3 rounded-lg bg-surface-container-low/50 p-4 md:w-auto md:min-w-[260px] md:shrink-0 md:bg-transparent md:p-0">
              <div class="min-w-0 flex-1 text-left">
                <div class="text-xs text-on-surface-variant mb-0.5">單價</div>
                <div class="font-headline text-2xl md:text-3xl font-bold text-on-surface tracking-tight">{{ formatListingAmount(listing.price) }} <span class="text-sm font-body font-normal text-on-surface-variant">{{ listing.fiat_code || 'EUR' }}</span></div>
              </div>
              <a class="lobby-trade-action inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-lg bg-gradient-to-br from-primary to-primary-dim px-5 py-2.5 text-center text-sm font-bold tracking-wide text-on-primary shadow-none transition-all hover:shadow-[0px_8px_16px_rgba(46,100,134,0.2)] sm:px-8" :href="'#/pages/index/sell?listing_id=' + listing.id">賣出</a>
            </div>
          </article>
        </div>

        <div id="lobby-offers-buy" class="flex flex-col gap-6" v-show="mode === 'buy' && !listingsError">
          <p v-if="!listingsLoading && !buyTabListings.length" class="text-sm text-on-surface-variant px-1 py-6 text-center rounded-xl bg-surface-container-lowest/80 border border-outline-variant/10">
            No suppliers
          </p>
          <article
            v-for="listing in buyTabListings"
            :key="'lobby-buy-' + listing.id"
            class="bg-surface-container-lowest rounded-xl p-6 shadow-[0px_12px_32px_rgba(44,52,55,0.04)] flex flex-col md:flex-row md:items-center justify-between gap-6 transition-all hover:shadow-[0px_16px_40px_rgba(44,52,55,0.06)] hover:-translate-y-0.5 duration-300 border border-outline-variant/5"
          >
            <div class="flex flex-1 items-start gap-4">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-tertiary-container font-headline text-xl font-bold text-on-tertiary-container ring-2 ring-surface-container-high">{{ avatarLetter(listing.nickname) }}</div>
              <div class="min-w-0 flex-1">
                <div class="mb-1 flex items-center gap-2 flex-wrap min-w-0">
                  <h4 class="text-base font-bold text-on-surface truncate min-w-0">{{ listing.nickname || '商戶' }}</h4>
                  <div class="flex items-center gap-1.5 shrink-0" aria-label="商戶認證標示">
                    <span v-if="listingShowVip(listing)" class="hall-tier hall-tier--vip">VIP</span>
                    <span v-if="listingShowPro(listing)" class="hall-tier hall-tier--pro">PRO</span>
                    <img
                      v-for="si in listingHallStarSlots(listing)"
                      :key="'buy-star-' + listing.id + '-' + si"
                      class="hall-certified-badge"
                      :src="certifiedBadgeSrc"
                      alt=""
                      aria-hidden="true"
                    />
                  </div>
                </div>
                <div class="flex items-center gap-3 text-xs text-on-surface-variant flex-wrap">
                  <span v-if="listing.completion_rate" class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">thumb_up</span> {{ listing.completion_rate }}</span>
                  <span class="text-on-surface-variant/80">庫存 {{ formatInventory(listing.available_amount) }} {{ listing.asset_code || 'USDT' }}</span>
                </div>
                <div class="mt-3 text-sm text-on-surface">
                  <span class="mr-1 text-on-surface-variant">限制：</span> {{ formatListingAmount(listing.min_amount) }} - {{ formatListingAmount(listing.max_amount) }} {{ listing.asset_code || 'USDT' }}
                </div>
              </div>
            </div>
            <div class="hall-offer-actions flex w-full min-w-0 flex-row flex-nowrap items-center justify-between gap-3 rounded-lg bg-surface-container-low/50 p-4 md:w-auto md:min-w-[260px] md:shrink-0 md:bg-transparent md:p-0">
              <div class="min-w-0 flex-1 text-left">
                <div class="mb-0.5 text-xs text-on-surface-variant">單價</div>
                <div class="font-headline text-2xl font-bold tracking-tight text-on-surface md:text-3xl">{{ formatListingAmount(listing.price) }} <span class="font-body text-sm font-normal text-on-surface-variant">{{ listing.fiat_code || 'EUR' }}</span></div>
              </div>
              <a class="lobby-trade-action inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-lg bg-gradient-to-br from-primary to-primary-dim px-5 py-2.5 text-center text-sm font-bold tracking-wide text-on-primary transition-all hover:shadow-[0px_8px_16px_rgba(46,100,134,0.2)] sm:px-8" :href="'#/pages/index/buy?listing_id=' + listing.id">買入</a>
            </div>
          </article>
        </div>
        <LoadingInlineSpinner v-if="listingsLoading && listings.length" aria-label="載入更多掛單" />
      </section>
    </main>

    <nav
      class="eurnyse-home-bottom-nav fixed bottom-0 left-0 w-full z-50 rounded-t-2xl bg-white/78 backdrop-blur-lg shadow-[0px_-8px_24px_rgba(33,79,131,0.08)] font-['Manrope']"
      aria-label="主頁底部導航"
    >
      <div class="flex justify-around items-center h-20 px-4 pb-safe">
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/index/index">
          <span class="material-symbols-outlined">home</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">主頁</span>
        </a>
        <a class="flex flex-col items-center justify-center text-primary-dim bg-primary-container/60 rounded-xl px-3 py-1 transition-all active:scale-90" href="#/pages/index/hall">
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
import { userApi } from '@/utils/api'
import { hallCertifiedBadge } from '@/assets/images'
/**
 * EURNYSE - 交易大廳 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/lody.html 為唯一基準逐字遷移
 * 原稿 IIFE：sell/buy 雙列切換 + aria-selected + class toggle，改以 Vue data.mode + :class 等價呈現
 * 供應商列表：GET /api/user/listings（需登入）；「賣出」分頁 = side sell，「買入」分頁 = side buy
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--lody",
  "bg-background",
  "text-on-surface",
  "font-body",
  "antialiased",
  "min-h-screen",
  "pb-24",
  "md:pb-0"
];

export default {
  name: "LodyH5",
  data: function () {
    return {
      mode: "sell",
      listings: [],
      listingsLoading: false,
      listingsError: "",
      listingsPage: 1,
      listingsPageSize: 7,
      listingsHasMore: true,
      amountFilter: "",
      usdtAvailableBalance: 0,
      activeOrderCount: 0,
      certifiedBadgeSrc: hallCertifiedBadge
    };
  },
  computed: {
    /** sell → 賣出分頁 */
    sellTabListings: function () {
      return this.filteredListings
        .filter(function (l) {
          return String(l.side || "").toLowerCase() === "sell";
        })
        .slice()
        .sort(function (a, b) {
          return (b.id || 0) - (a.id || 0);
        });
    },
    /** buy → 買入分頁 */
    buyTabListings: function () {
      return this.filteredListings
        .filter(function (l) {
          return String(l.side || "").toLowerCase() === "buy";
        })
        .slice()
        .sort(function (a, b) {
          return (b.id || 0) - (a.id || 0);
        });
    },
    filteredListings: function () {
      var amount = parseFloat(String(this.amountFilter || "").replace(/,/g, "").trim());
      if (isNaN(amount) || amount <= 0) return this.listings;
      return this.listings.filter(function (listing) {
        var min = parseFloat(listing.min_amount || "0") || 0;
        var max = parseFloat(listing.max_amount || "0") || 0;
        var available = parseFloat(listing.available_amount || "0") || 0;
        return amount >= min && amount <= max && amount <= available;
      });
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "lody");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.fetchHallData();
    window.addEventListener("scroll", this.handleListingScroll, { passive: true });
  },
  beforeDestroy: function () {
    window.removeEventListener("scroll", this.handleListingScroll);
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
  },
  methods: {
    fetchHallData: function () {
      this.fetchAccountSnapshot();
      this.fetchListings();
    },
    fetchAccountSnapshot: function () {
      var self = this;
      userApi
        .overview()
        .then(function (data) {
          var map = (data && data.wallet_map) || {};
          var usdt = map.cash_usdt || {};
          self.usdtAvailableBalance = parseFloat(usdt.available_balance || "0") || 0;
        })
        .catch(function () {
          self.usdtAvailableBalance = 0;
        });
      userApi
        .ordersSummary()
        .then(function (data) {
          self.activeOrderCount = parseInt((data && data.active_count) || "0", 10) || 0;
        })
        .catch(function () {
          self.activeOrderCount = 0;
        });
    },
    fetchListings: function () {
      this.listingsPage = 1;
      this.listingsHasMore = true;
      this.loadListingsPage(true);
    },
    loadListingsPage: function (reset) {
      if (this.listingsLoading) return;
      if (!reset && !this.listingsHasMore) return;
      var self = this;
      var page = reset ? 1 : this.listingsPage + 1;
      this.listingsLoading = true;
      this.listingsError = "";
      userApi
        .listings("?page=" + encodeURIComponent(page) + "&page_size=" + encodeURIComponent(this.listingsPageSize))
        .then(function (data) {
          var rows = data && data.items ? data.items : [];
          self.listings = reset ? rows : self.listings.concat(rows);
          self.listingsPage = page;
          self.listingsHasMore = !!(data && data.has_more);
        })
        .catch(function (err) {
          if (reset) self.listings = [];
          self.listingsError = (err && err.message) || "載入掛單失敗";
        })
        .finally(function () {
          self.listingsLoading = false;
        });
    },
    handleListingScroll: function () {
      if (this.listingsLoading || !this.listingsHasMore) return;
      var doc = document.documentElement;
      var scrollTop = window.pageYOffset || doc.scrollTop || 0;
      var viewportHeight = window.innerHeight || doc.clientHeight || 0;
      var fullHeight = Math.max(doc.scrollHeight || 0, document.body ? document.body.scrollHeight : 0);
      if (scrollTop + viewportHeight >= fullHeight - 260) {
        this.loadListingsPage(false);
      }
    },
    /** 單價／限額等：依後端字串去掉尾隨 0，不強制小數位數 */
    formatListingAmount: function (v) {
      if (v == null || v === "") return "—";
      var s = String(v).trim();
      if (s === "" || s.toLowerCase() === "nan") return "—";
      var neg = false;
      if (s.charAt(0) === "-") {
        neg = true;
        s = s.slice(1);
      }
      if (!/^\d+(\.\d+)?$/.test(s)) {
        var n = Number(s);
        if (isNaN(n)) return String(v);
        return this.formatListingAmount(String(n));
      }
      var parts = s.split(".");
      if (parts.length === 1) {
        return (neg ? "-" : "") + parts[0];
      }
      var frac = parts[1].replace(/0+$/, "");
      if (frac === "") {
        return (neg ? "-" : "") + parts[0];
      }
      return (neg ? "-" : "") + parts[0] + "." + frac;
    },
    /** 庫存：最多兩位小數，並去掉尾隨 0 */
    formatInventory: function (v) {
      if (v == null || v === "") return "—";
      var n = parseFloat(String(v).trim());
      if (isNaN(n)) return String(v);
      var rounded = Math.round(n * 100) / 100;
      var s = rounded.toFixed(2);
      return this.formatListingAmount(s);
    },
    formatMoney: function (value, digits) {
      return Number(value || 0).toLocaleString("en-GB", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    },
    avatarLetter: function (name) {
      var s = String(name || "").trim();
      if (!s) return "?";
      return s.charAt(0).toUpperCase();
    },
    /** 成交率字串 → 0–100 數值（後端可能為「100%」） */
    parseCompletionPercent: function (cr) {
      var s = String(cr != null ? cr : "")
        .replace(/%/g, "")
        .replace(/,/g, "")
        .trim();
      var n = parseFloat(s);
      return isNaN(n) ? 0 : n;
    },
    listingBadgeOn: function (listing, key) {
      var v = listing && listing[key];
      return v === true || v === 1 || v === "1";
    },
    /** 僅啟用「認證章／認證星」時顯示星形，且固定一顆；未勾選則不顯示（不以成交率推斷多顆星）。 */
    listingHallStarSlots: function (listing) {
      if (this.listingBadgeOn(listing, "badge_stars")) return [1];
      return [];
    },
    /** VIP／PRO 可同時啟用；未勾選時才依成交率推斷單一標籤。 */
    listingShowVip: function (listing) {
      if (this.listingBadgeOn(listing, "badge_vip")) return true;
      if (this.listingBadgeOn(listing, "badge_pro")) return false;
      var n = this.parseCompletionPercent(listing && listing.completion_rate);
      return n >= 99;
    },
    listingShowPro: function (listing) {
      if (this.listingBadgeOn(listing, "badge_pro")) return true;
      if (this.listingBadgeOn(listing, "badge_vip")) return false;
      var n = this.parseCompletionPercent(listing && listing.completion_rate);
      return n >= 90 && n < 99;
    },
    setMode: function (mode) {
      this.mode = mode;
    }
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");

.hall-tier {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.625rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  line-height: 1;
  padding: 0.2rem 0.4rem;
  border-radius: 0.375rem;
  white-space: nowrap;
}

.hall-tier--vip {
  background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
  color: #fffaf0;
  box-shadow: 0 1px 2px rgba(180, 83, 9, 0.25);
}

.hall-tier--pro {
  background: linear-gradient(135deg, #64748b 0%, #334155 100%);
  color: #f8fafc;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.2);
}

.hall-certified-badge {
  width: 1.35rem;
  height: 1.35rem;
  object-fit: contain;
  display: inline-block;
  flex: 0 0 auto;
}
</style>
