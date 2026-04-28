<template>
  <div>
    <main class="max-w-3xl mx-auto px-4 py-8">
      <section class="glass-panel rounded-[28px] p-5 md:p-6">
        <a class="text-sm text-on-surface-variant" href="#/pages/setting/fundRecord" @click="backGo($event, '#/pages/setting/fundRecord')">返回</a>
        <LoadingInlineSpinner v-if="loading" aria-label="充值詳情載入" />
        <template v-else>
        <div class="mt-4 flex items-start justify-between gap-4">
          <div><p class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">儲值明細</p><h1 class="font-headline text-2xl mt-1">充值詳情</h1></div>
          <span class="rounded-full bg-success-container px-3 py-1 text-xs font-semibold text-success-text">{{ statusLabel }}</span>
        </div>
        <p v-if="errorMessage" class="mt-4 rounded-2xl bg-error/5 px-4 py-3 text-sm text-error">{{ errorMessage }}</p>
        <div class="mt-6 space-y-3">
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between"><span class="text-on-surface-variant">訂單編號</span><span>{{ item.id || '-' }}</span></div>
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between"><span class="text-on-surface-variant">充值金額</span><span class="font-headline text-xl">{{ item.amount || '-' }} {{ item.asset_code || '' }}</span></div>
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between"><span class="text-on-surface-variant">審核時間</span><span>{{ item.reviewed_at || '-' }}</span></div>
          <div class="rounded-2xl bg-surface-bright border border-outline-variant/20 p-4 flex items-center justify-between"><span class="text-on-surface-variant">支付通道</span><span>{{ item.network || '-' }}</span></div>
        </div>
        <div class="grid grid-cols-3 gap-3 mt-6 text-sm">
          <a class="rounded-2xl bg-surface-container-low px-4 py-4 text-center" href="#/pages/setting/wallet">我的資產</a>
          <a class="rounded-2xl bg-surface-container-low px-4 py-4 text-center" href="#/pages/setting/mixrecharge">再次充值</a>
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
 * EURNYSE - 儲值詳情 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/recharge-detail.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--recharge-detail",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "RechargeDetailH5",
  data: function () {
    return {
      item: {},
      errorMessage: "",
      loading: true
    };
  },
  computed: {
    statusLabel: function () {
      var map = { pending: "審核中", approved: "已完成", rejected: "已駁回", cancelled: "已取消" };
      return map[this.item.status] || (this.item.status || "—");
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "recharge-detail");
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
        this.errorMessage = "缺少充值申請編號";
        this.loading = false;
        return;
      }
      var self = this;
      userApi.depositRequestDetail(id).then(function (data) {
        self.item = data.request || {};
      }).catch(function (error) {
        self.errorMessage = (error && error.message) || "載入充值詳情失敗";
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
