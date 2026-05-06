<template>
  <div>
    <main class="max-w-5xl mx-auto px-4 py-8">
      <section class="glass-panel rounded-[28px] p-5 md:p-6">
        <div class="flex items-center justify-between gap-4">
          <a class="text-sm text-on-surface-variant" href="#/pages/index/serviceCenter" @click="backGo($event, '#/pages/index/serviceCenter')">返回</a>
          <div><p class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">內嵌視窗</p><h1 class="font-headline text-2xl">內嵌內容容器</h1></div>
          <span class="rounded-full bg-primary-container px-3 py-1 text-xs font-semibold text-primary-dim">iframe / H5</span>
        </div>
        <div class="mt-6 rounded-[24px] border border-outline-variant/20 bg-surface-container-low p-4">
          <div class="rounded-[20px] bg-surface-bright border border-outline-variant/15 min-h-[520px] flex items-center justify-center text-center px-6">
            <div>
              <h2 class="font-headline text-xl">外部內容載入區</h2>
              <p class="text-sm text-on-surface-variant mt-2">此頁對應 `webView` 容器，用於活動頁、公告詳情或合作方 H5 內容嵌入。</p>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3 mt-6 text-sm">
          <a class="rounded-2xl bg-surface-bright px-4 py-4 border border-outline-variant/15 text-center" href="#/pages/common/article">公告文章</a>
          <a class="rounded-2xl bg-surface-bright px-4 py-4 border border-outline-variant/15 text-center" href="#/pages/index/serviceCenter">客服中心</a>
          <a class="rounded-2xl bg-surface-bright px-4 py-4 border border-outline-variant/15 text-center" href="#/pages/index/index">返回首頁</a>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
/**
 * EURFOREX - 內嵌視窗 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/webview.html 為唯一基準逐字遷移
 * 原稿無內聯 JS，僅外部 back-nav.js
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--webview",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "WebviewH5",
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "webview");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
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
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
