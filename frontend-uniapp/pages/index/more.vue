<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/index/index" @click="backGo($event, '#/pages/index/index')" aria-label="返回首頁"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">更多功能</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>

    <main class="container mx-auto max-w-4xl flex-grow space-y-5 px-4 pb-6 pt-20 sm:px-6 lg:px-8">

      <!-- 交易 -->
      <section class="rounded-[20px] border border-outline-variant/25 bg-white/82 px-3 py-4 shadow-[0_10px_28px_rgba(40,88,142,0.06)] backdrop-blur-md" aria-labelledby="more-cat-trade">
        <div class="mb-3 flex items-center gap-2 px-1">
          <span class="h-4 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
          <h2 id="more-cat-trade" class="font-headline text-[15px] font-bold text-on-surface">交易</h2>
        </div>
        <div class="grid grid-cols-4 gap-x-2 gap-y-5 pb-1" role="list">
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/index/hall" role="listitem"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">swap_horizontal_circle</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">交易大廳</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/index/buy" role="listitem"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">shopping_cart</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">買入</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/index/sell" role="listitem"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">sell</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">賣出</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/myTask" role="listitem"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">receipt_long</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">訂單</span></a>
        </div>
      </section>

      <!-- 資產 -->
      <section class="rounded-[20px] border border-outline-variant/25 bg-white/82 px-3 py-4 shadow-[0_10px_28px_rgba(40,88,142,0.06)] backdrop-blur-md" aria-labelledby="more-cat-wallet">
        <div class="mb-3 flex items-center gap-2 px-1">
          <span class="h-4 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
          <h2 id="more-cat-wallet" class="font-headline text-[15px] font-bold text-on-surface">資產</h2>
        </div>
        <div class="grid grid-cols-4 gap-x-2 gap-y-5 pb-1">
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/wallet"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">account_balance_wallet</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">我的資產</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/mixrecharge"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">south_west</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">USDT<br />充值</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/eurDeposit"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">payments</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">EUR<br />法幣入金</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/wallet"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">north_east</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">提現</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/withdrawDetail"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">contract</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">提現詳情</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/fundRecord"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">history</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">資金紀錄</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/rechargeDetail"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">savings</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">儲值詳情</span></a>
        </div>
      </section>

      <!-- 理財 -->
      <section class="rounded-[20px] border border-outline-variant/25 bg-white/82 px-3 py-4 shadow-[0_10px_28px_rgba(40,88,142,0.06)] backdrop-blur-md" aria-labelledby="more-cat-earn">
        <div class="mb-3 flex items-center gap-2 px-1">
          <span class="h-4 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
          <h2 id="more-cat-earn" class="font-headline text-[15px] font-bold text-on-surface">理財</h2>
        </div>
        <div class="grid grid-cols-4 gap-x-2 gap-y-5 pb-1">
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/index/financial"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">trending_up</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">理財中心</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/financialHoldings"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">pie_chart</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">我的持倉</span></a>
        </div>
      </section>

      <!-- 帳戶與安全 -->
      <section class="rounded-[20px] border border-outline-variant/25 bg-white/82 px-3 py-4 shadow-[0_10px_28px_rgba(40,88,142,0.06)] backdrop-blur-md" aria-labelledby="more-cat-account">
        <div class="mb-3 flex items-center gap-2 px-1">
          <span class="h-4 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
          <h2 id="more-cat-account" class="font-headline text-[15px] font-bold text-on-surface">帳戶與安全</h2>
        </div>
        <div class="grid grid-cols-4 gap-x-2 gap-y-5 pb-1">
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/securityCenter"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">shield_lock</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">安全中心</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/kycVerification"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">badge</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">實名認證</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/verificationCenter"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">verified_user</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">認證中心</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/merchantAuth"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">storefront</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">商戶認證</span></a>
          <button v-if="merchantEnabled" type="button" class="group flex cursor-pointer flex-col items-center gap-2 border-0 bg-transparent p-0 font-body" @click="openCreateMerchantModal"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">add_business</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">創建商戶</span></button>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/bindinfo"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">account_balance</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">綁定銀行</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/withdrawAddressBinding"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">add_link</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">提現地址</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/changePassword"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">key</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">修改密碼</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/common/resetpwd"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">lock_reset</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">重置密碼</span></a>
        </div>
      </section>

      <!-- 服務與支援 -->
      <section class="rounded-[20px] border border-outline-variant/25 bg-white/82 px-3 py-4 shadow-[0_10px_28px_rgba(40,88,142,0.06)] backdrop-blur-md" aria-labelledby="more-cat-service">
        <div class="mb-3 flex items-center gap-2 px-1">
          <span class="h-4 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
          <h2 id="more-cat-service" class="font-headline text-[15px] font-bold text-on-surface">服務與支援</h2>
        </div>
        <div class="grid grid-cols-4 gap-x-2 gap-y-5 pb-1">
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/index/serviceCenter"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">headset_mic</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">客服中心</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/index/serviceCenter"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">support_agent</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">線上客服</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/index/tutorial"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">school</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">新手指南</span></a>
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/common/article"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">campaign</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">公告詳情</span></a>
        </div>
      </section>

      <!-- 推廣 -->
      <section class="rounded-[20px] border border-outline-variant/25 bg-white/82 px-3 py-4 shadow-[0_10px_28px_rgba(40,88,142,0.06)] backdrop-blur-md" aria-labelledby="more-cat-referral">
        <div class="mb-3 flex items-center gap-2 px-1">
          <span class="h-4 w-1 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
          <h2 id="more-cat-referral" class="font-headline text-[15px] font-bold text-on-surface">推廣</h2>
        </div>
        <div class="grid grid-cols-4 gap-x-2 gap-y-5 pb-1">
          <a class="group flex cursor-pointer flex-col items-center gap-2" href="#/pages/setting/inviteTeam"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-surface-container-low transition-colors group-hover:bg-primary-container"><span class="material-symbols-outlined text-primary transition-colors group-hover:text-on-primary-container">group_add</span></div><span class="max-w-[4.5rem] text-center text-[11px] font-medium leading-tight text-on-surface-variant group-hover:text-on-surface">邀請團隊</span></a>
        </div>
      </section>
      <div class="fixed inset-0 z-[120] items-center justify-center bg-black/45 p-4" :class="createMerchantModalOpen ? 'flex' : 'hidden'" role="dialog" aria-modal="true" aria-labelledby="create-merchant-service-title" @click.self="closeCreateMerchantModal">
        <div class="relative w-full max-w-sm rounded-2xl border border-primary/10 bg-white p-5 pt-6 shadow-[0_12px_40px_rgba(33,79,131,0.12)]" @click.stop>
          <button type="button" class="absolute right-2 top-2 rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-container-low" aria-label="關閉" @click="closeCreateMerchantModal">
            <span class="material-symbols-outlined text-[22px] leading-none">close</span>
          </button>
          <h2 id="create-merchant-service-title" class="font-headline px-8 text-center text-lg font-bold text-on-surface">創建商戶</h2>
          <p class="mt-4 text-center text-sm leading-relaxed text-on-surface">請聯繫客服</p>
          <button type="button" class="mt-6 w-full rounded-full border border-primary/25 bg-primary-container/60 py-3 text-sm font-bold text-primary-dim transition-colors hover:bg-primary-container" @click="closeCreateMerchantModal">知道了</button>
        </div>
      </div>
    </main>

    <nav
      class="eurnyse-home-bottom-nav fixed bottom-0 left-0 z-50 w-full rounded-t-2xl bg-white/78 font-body shadow-[0px_-8px_24px_rgba(33,79,131,0.08)] backdrop-blur-lg"
      aria-label="主頁底部導航"
    >
      <div class="flex h-20 items-center justify-around px-4 pb-safe">
        <a class="flex flex-col items-center justify-center px-3 py-1 text-on-surface-variant transition-all hover:text-primary-dim" href="#/pages/index/index">
          <span class="material-symbols-outlined">home</span>
          <span class="mt-0.5 text-[11px] font-semibold tracking-wide">主頁</span>
        </a>
        <a class="flex flex-col items-center justify-center px-3 py-1 text-on-surface-variant transition-all hover:text-primary-dim" href="#/pages/index/hall">
          <span class="material-symbols-outlined">swap_horizontal_circle</span>
          <span class="mt-0.5 text-[11px] font-semibold tracking-wide">交易大廳</span>
        </a>
        <a class="flex flex-col items-center justify-center px-3 py-1 text-on-surface-variant transition-all hover:text-primary-dim" href="#/pages/setting/myTask">
          <span class="material-symbols-outlined">receipt_long</span>
          <span class="mt-0.5 text-[11px] font-semibold tracking-wide">訂單</span>
        </a>
        <a class="flex flex-col items-center justify-center px-3 py-1 text-on-surface-variant transition-all hover:text-primary-dim" href="#/pages/setting/user">
          <span class="material-symbols-outlined">person</span>
          <span class="mt-0.5 text-[11px] font-semibold tracking-wide">個人中心</span>
        </a>
      </div>
    </nav>
  </div>
</template>

<script>
import { userApi } from "@/utils/api";

/**
 * EURNYSE - 更多功能 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/more.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--more",
  "bg-background",
  "flex",
  "min-h-screen",
  "flex-col",
  "pb-24"
];

export default {
  name: "MoreH5",
  data: function () {
    return {
      merchantEnabled: false,
      createMerchantModalOpen: false
    };
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "more");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.loadOverview();
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
      if (window.EurnyseBack && typeof window.EurnyseBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurnyseBack.go(fallback);
      }
    },
    loadOverview: function () {
      var self = this;
      userApi
        .overview()
        .then(function (data) {
          var profile = (data && data.tier_profile) || {};
          self.merchantEnabled = Boolean(profile.merchant_enabled);
        })
        .catch(function () {
          self.merchantEnabled = false;
        });
    },
    openCreateMerchantModal: function () {
      this.createMerchantModalOpen = true;
    },
    closeCreateMerchantModal: function () {
      this.createMerchantModalOpen = false;
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
