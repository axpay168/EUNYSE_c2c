<template>
  <div>
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
      <section class="glass-panel rounded-[28px] p-6 md:p-8 w-full max-w-md">
        <div class="text-center">
          <div class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">帳號救援</div>
          <h1 class="font-headline text-3xl mt-2">重設密碼</h1>
          <p class="text-sm text-on-surface-variant mt-2">透過驗證碼重設你的登入密碼。</p>
        </div>
        <div class="mt-6 space-y-4">
          <div class="rounded-2xl bg-surface-bright px-4 py-4 border border-outline-variant/20"><label class="block text-xs uppercase tracking-[0.16em] text-on-surface-variant">手機號 / 信箱</label><div class="mt-2 text-on-surface">aaaa@gmail.com</div></div>
          <div class="grid grid-cols-[1fr_auto] gap-3">
            <div class="rounded-2xl bg-surface-bright px-4 py-4 border border-outline-variant/20"><label class="block text-xs uppercase tracking-[0.16em] text-on-surface-variant">驗證碼</label><div class="mt-2 text-on-surface">328219</div></div>
            <div class="rounded-2xl bg-primary-container text-primary-dim px-4 py-4 font-semibold flex items-center justify-center whitespace-nowrap">發送驗證碼</div>
          </div>
          <div class="rounded-2xl bg-surface-bright px-4 py-4 border border-outline-variant/20"><label class="block text-xs uppercase tracking-[0.16em] text-on-surface-variant">新密碼</label><div class="mt-2 text-on-surface">••••••••</div></div>
          <a class="block w-full rounded-full bg-gradient-to-br from-primary to-primary-dim text-white text-center py-3 font-headline font-bold tracking-wide" href="#/pages/common/login">更新密碼</a>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
/**
 * EURNYSE - 重設密碼 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/reset-password.html 為唯一基準逐字遷移
 * 樣式直接 @import preview-entry.css
 * 外部腳本：../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--reset-password",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "ResetPasswordH5",
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "reset-password");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
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
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
