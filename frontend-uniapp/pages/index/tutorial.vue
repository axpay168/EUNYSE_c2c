<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/user" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">新手指南</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>
    <main class="pt-20 px-4 max-w-3xl mx-auto space-y-5">
      <section class="glass-panel rounded-[24px] p-5">
        <div class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">學習中心</div>
        <h2 class="font-headline text-2xl mt-1">操作教學</h2>
        <p class="text-sm text-on-surface-variant mt-2">從入金、買賣到訂單放行，按步驟完成操作。</p>
      </section>
      <section class="space-y-4">
        <a class="glass-panel rounded-[24px] p-5 block" href="#/pages/setting/mixrecharge"><div class="flex items-start gap-4"><div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">1</div><div><h3 class="font-headline text-xl">如何充值</h3><p class="text-sm text-on-surface-variant mt-2">前往資產頁，選擇充值方式並確認到帳。</p></div></div></a>
        <a class="glass-panel rounded-[24px] p-5 block" href="#/pages/index/buy"><div class="flex items-start gap-4"><div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">2</div><div><h3 class="font-headline text-xl">如何買入 USDT</h3><p class="text-sm text-on-surface-variant mt-2">在交易大廳選擇商戶，輸入金額並完成付款。</p></div></div></a>
        <a class="glass-panel rounded-[24px] p-5 block" href="#/pages/setting/myTask"><div class="flex items-start gap-4"><div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">3</div><div><h3 class="font-headline text-xl">如何安全放行</h3><p class="text-sm text-on-surface-variant mt-2">從真實訂單列表進入詳情，確認款項入帳後再放行，若異常請先聯絡客服。</p></div></div></a>
      </section>
      <section class="grid grid-cols-2 gap-3">
        <a class="glass-panel rounded-[24px] p-4 text-center" href="#/pages/index/serviceCenter">聯絡客服</a>
        <a class="glass-panel rounded-[24px] p-4 text-center" href="#/pages/common/article">查看公告</a>
      </section>
    </main>
  </div>
</template>

<script>
/**
 * EURFOREX - 新手指南 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/tutorial.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--tutorial",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-10"
];

export default {
  name: "TutorialH5",
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "tutorial");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
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
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
