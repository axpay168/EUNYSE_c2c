<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/user" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="min-w-0 truncate px-1 text-center font-headline text-lg font-bold text-on-surface">安全中心 / 客服</div>
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/common/webView" aria-label="聯絡專員"><span class="material-symbols-outlined">support_agent</span></a>
    </header>
    <main class="pt-20 px-4 max-w-3xl mx-auto space-y-5">
      <section class="glass-panel rounded-[24px] p-5">
        <div class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">線上客服</div>
        <h2 class="font-headline text-2xl mt-1">客服中心</h2>
        <p class="service-subcopy text-sm text-on-surface-variant mt-2">選擇常見問題類型，或直接聯繫人工客服。</p>
        <a class="service-cta mt-4 w-full rounded-full bg-gradient-to-br from-primary to-primary-dim text-white py-3 font-headline font-bold tracking-wide flex items-center justify-center" href="#/pages/common/webView">聯絡專員</a>
      </section>
      <section class="grid grid-cols-2 gap-3">
        <a class="glass-panel rounded-[24px] p-5" href="#/pages/setting/myTask"><div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center"><span class="material-symbols-outlined">receipt_long</span></div><div class="font-semibold mt-3">訂單問題</div><div class="service-subcopy text-sm text-on-surface-variant mt-1">付款、放行與申訴</div></a>
        <a class="glass-panel rounded-[24px] p-5" href="#/pages/setting/bindinfo"><div class="w-10 h-10 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center"><span class="material-symbols-outlined">account_balance</span></div><div class="font-semibold mt-3">銀行綁定</div><div class="service-subcopy text-sm text-on-surface-variant mt-1">收付款帳戶綁定</div></a>
        <a class="glass-panel rounded-[24px] p-5" href="#/pages/setting/kycVerification"><div class="w-10 h-10 rounded-2xl bg-tertiary/10 text-tertiary flex items-center justify-center"><span class="material-symbols-outlined">verified_user</span></div><div class="font-semibold mt-3">實名驗證</div><div class="service-subcopy text-sm text-on-surface-variant mt-1">KYC 與驗證進度</div></a>
        <a class="glass-panel rounded-[24px] p-5" href="#/pages/index/tutorial"><div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center"><span class="material-symbols-outlined">school</span></div><div class="font-semibold mt-3">新手指南</div><div class="service-subcopy text-sm text-on-surface-variant mt-1">操作流程與新手教學</div></a>
        <a class="glass-panel rounded-[24px] p-5" href="#/pages/common/article"><div class="w-10 h-10 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center"><span class="material-symbols-outlined">article</span></div><div class="font-semibold mt-3">公告文章</div><div class="service-subcopy text-sm text-on-surface-variant mt-1">查看通知與活動內容</div></a>
        <a class="glass-panel rounded-[24px] p-5" href="#/pages/setting/securityCenter"><div class="w-10 h-10 rounded-2xl bg-tertiary/10 text-tertiary flex items-center justify-center"><span class="material-symbols-outlined">shield_lock</span></div><div class="font-semibold mt-3">安全中心</div><div class="service-subcopy text-sm text-on-surface-variant mt-1">密碼、KYC 與綁定設定</div></a>
      </section>
    </main>
  </div>
</template>

<script>
/**
 * EURNYSE - 客服中心 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/service-center.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--service-center",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-10"
];

export default {
  name: "ServiceCenterH5",
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "service-center");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
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
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
