<template>
  <div>
    <main class="max-w-4xl mx-auto px-4 py-8">
      <div class="mb-4">
        <a class="text-sm font-medium text-primary-dim hover:text-primary hover:underline" href="#/pages/index/financial" @click="backGo($event, '#/pages/index/financial')">返回</a>
      </div>
      <section class="glass-panel rounded-[28px] p-5 md:p-6 mb-6">
        <h1 class="font-headline text-2xl">商戶認證</h1>
        <p class="text-sm text-on-surface-variant mt-2">完成 KYC、收款綁定、保證金配置並聯繫客服開通後，即可申請掛單權限。</p>
      </section>
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="glass-panel rounded-[24px] p-5">
          <div class="text-xs uppercase tracking-[0.16em] text-on-surface-variant">步驟 1</div>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <h2 class="font-headline text-lg">身份驗證</h2>
            <span class="merchant-step-tag" :class="tagClass('kyc')" id="merchant-status-kyc" :data-done="steps.kyc ? 'true' : 'false'">{{ steps.kyc ? '已完成' : '未完成' }}</span>
          </div>
          <p class="text-sm text-on-surface-variant mt-2">完成個人或企業實名資料審核。</p>
          <a class="inline-block mt-5 rounded-full bg-primary-container px-4 py-2 text-sm font-semibold text-primary-dim" href="#/pages/setting/kycVerification">前往 KYC</a>
        </div>
        <div class="glass-panel rounded-[24px] p-5">
          <div class="text-xs uppercase tracking-[0.16em] text-on-surface-variant">步驟 2</div>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <h2 class="font-headline text-lg">收款綁定</h2>
            <span class="merchant-step-tag" :class="tagClass('binding')" id="merchant-status-binding" :data-done="steps.binding ? 'true' : 'false'">{{ steps.binding ? '已完成' : '未完成' }}</span>
          </div>
          <p class="text-sm text-on-surface-variant mt-2">綁定法幣收款帳戶與 USDT 地址。</p>
          <a class="inline-block mt-5 rounded-full bg-primary-container px-4 py-2 text-sm font-semibold text-primary-dim" href="#/pages/setting/bindinfo">管理綁定</a>
        </div>
        <div class="glass-panel rounded-[24px] p-5">
          <div class="text-xs uppercase tracking-[0.16em] text-on-surface-variant">步驟 3</div>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <h2 class="font-headline text-lg">保證金設定</h2>
            <span class="merchant-step-tag" :class="tagClass('margin')" id="merchant-status-margin" :data-done="steps.margin ? 'true' : 'false'">{{ steps.margin ? '已完成' : '未完成' }}</span>
          </div>
          <p class="text-sm text-on-surface-variant mt-2">鎖定商戶保證金，解鎖發布掛單與優先成交。</p>
          <a class="inline-block mt-5 rounded-full bg-gradient-to-br from-primary to-primary-dim px-4 py-2 text-sm font-semibold text-white" href="#/pages/setting/wallet">查看資產</a>
        </div>
        <div class="glass-panel rounded-[24px] p-5"><div class="text-xs uppercase tracking-[0.16em] text-on-surface-variant">步驟 4</div><h2 class="font-headline text-lg mt-2">聯繫客服開通</h2><p class="text-sm text-on-surface-variant mt-2">聯繫客服提交開通申請，完成最後審核與掛單權限開通。</p><a class="inline-block mt-5 rounded-full bg-primary-container px-4 py-2 text-sm font-semibold text-primary-dim" href="#/pages/index/serviceCenter">聯繫客服</a></div>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURFOREX - 商家認證 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/merchant-auth.html 為唯一基準逐字遷移
 * 外部腳本：../shared/back-nav.js（動態注入）
 * 步驟狀態由使用者總覽 API 載入，避免商戶認證流程顯示假狀態。
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--merchant-auth",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "MerchantAuthH5",
  data: function () {
    return {
      steps: { kyc: false, binding: false, margin: false }
    };
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "merchant-auth");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.fetchMerchantSteps();
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
    fetchMerchantSteps: function () {
      var self = this;
      userApi.overview()
        .then(function (data) {
          var latestKyc = (data && data.latest_kyc) || {};
          var tier = (data && data.tier_profile) || {};
          self.steps = {
            kyc: String(latestKyc.status || "") === "approved",
            binding: Number((data && data.approved_payout_method_count) || 0) > 0,
            margin: tier.merchant_enabled === true || tier.merchant_enabled === 1 || tier.merchant_enabled === "1"
          };
        })
        .catch(function () {
          self.steps = { kyc: false, binding: false, margin: false };
        });
    },
    tagClass: function (key) {
      return this.steps[key] ? "merchant-step-tag--done" : "merchant-step-tag--pending";
    }
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
