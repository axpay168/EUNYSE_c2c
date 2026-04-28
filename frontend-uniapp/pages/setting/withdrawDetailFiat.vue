<template>
  <div>
    <main class="max-w-3xl mx-auto px-4 py-8">
      <section class="glass-panel rounded-[28px] p-5 md:p-6">
        <a class="text-sm text-on-surface-variant" href="#/pages/setting/fundRecord" @click="backGo($event, '#/pages/setting/fundRecord')">返回</a>
        <LoadingInlineSpinner v-if="loading" aria-label="法幣提現詳情載入" />
        <template v-else>
        <div class="mt-4 flex items-start justify-between gap-4">
          <div><p class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">法幣提現</p><h1 class="font-headline text-2xl mt-1">提現詳情</h1></div>
          <span class="rounded-full bg-warning-container px-3 py-1 text-xs font-semibold text-warning-text">{{ statusLabel }}</span>
        </div>
        <p v-if="errorMessage" class="mt-4 rounded-2xl bg-error/5 px-4 py-3 text-sm text-error">{{ errorMessage }}</p>
        <div class="mt-6 space-y-3">
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between gap-2"><span class="text-on-surface-variant shrink-0">訂單編號</span><span class="text-right font-mono text-sm">{{ item.id || '-' }}</span></div>
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between"><span class="text-on-surface-variant">提領金額</span><span class="font-headline text-xl">{{ item.asset_code || '' }} {{ item.amount || '-' }}</span></div>
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between"><span class="text-on-surface-variant">來源錢包</span><span>{{ item.source_wallet_code || '-' }}</span></div>
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between"><span class="text-on-surface-variant">審核時間</span><span class="font-semibold">{{ item.reviewed_at || '-' }}</span></div>
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><span class="text-on-surface-variant">收款帳戶</span><span class="text-right text-sm">{{ payoutSummary }}</span></div>
        </div>
        <div class="grid grid-cols-3 gap-3 mt-6 text-sm">
          <a class="rounded-2xl bg-surface-container-low px-4 py-4 text-center" href="#/pages/setting/wallet">我的資產</a>
          <a class="rounded-2xl bg-surface-container-low px-4 py-4 text-center" href="#/pages/setting/withdraw">再次提現</a>
          <a class="rounded-2xl bg-surface-container-low px-4 py-4 text-center" href="#/pages/index/serviceCenter">客服協助</a>
        </div>
        </template>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURNYSE - 法幣提現詳情 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/withdraw-detail-fiat.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--withdraw-detail-fiat",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "WithdrawDetailFiatH5",
  data: function () {
    return {
      item: {},
      errorMessage: "",
      loading: true
    };
  },
  computed: {
    statusLabel: function () {
      var map = { pending: "處理中", approved: "已完成", rejected: "已駁回", cancelled: "已取消" };
      return map[this.item.status] || (this.item.status || "—");
    },
    payoutSummary: function () {
      var parts = [this.item.bank_name, this.item.account_holder, this.item.account_no_masked].filter(Boolean);
      return parts.length ? parts.join(" · ") : (this.item.payout_address || "-");
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "withdraw-detail-fiat");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.fetchDetail();
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
    currentId: function () {
      var query = (window.location.hash || "").split("?")[1] || "";
      var params = new URLSearchParams(query);
      return params.get("request_id") || params.get("id") || "";
    },
    fetchDetail: function () {
      var id = this.currentId();
      if (!id) {
        this.errorMessage = "缺少提現申請編號";
        this.loading = false;
        return;
      }
      var self = this;
      userApi.withdrawalRequestDetail(id).then(function (data) {
        self.item = data.request || {};
      }).catch(function (error) {
        self.errorMessage = (error && error.message) || "載入提現詳情失敗";
      }).finally(function () {
        self.loading = false;
      });
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
