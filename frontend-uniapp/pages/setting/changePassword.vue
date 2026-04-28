<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="javascript:;" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">更換密碼</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>
    <main class="pt-20 pb-16 px-4 max-w-3xl mx-auto">
      <section class="rounded-[24px] border border-outline-variant/10 bg-white/80 p-5 shadow-[0_18px_36px_rgba(40,88,142,0.08)]">
        <form class="space-y-3 text-sm" action="#" method="post" autocomplete="on" @submit.prevent="handleChangePassword">
          <p v-if="errorMessage" class="rounded-2xl border border-error/15 bg-error-container/35 px-4 py-3 text-error">{{ errorMessage }}</p>
          <p v-if="successMessage" class="rounded-2xl border border-primary/15 bg-primary-container/40 px-4 py-3 text-primary-dim">{{ successMessage }}</p>
          <div>
            <label class="mb-1 block text-xs font-medium text-on-surface-variant" for="cp-current">目前密碼</label>
            <input class="w-full min-w-0 rounded-2xl border border-outline-variant/10 bg-surface-container-low px-4 py-3 text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/20" id="cp-current" name="current_password" type="password" autocomplete="current-password" placeholder="請輸入目前密碼" v-model="currentPassword" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-on-surface-variant" for="cp-new">新密碼</label>
            <input class="w-full min-w-0 rounded-2xl border border-outline-variant/10 bg-surface-container-low px-4 py-3 text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/20" id="cp-new" name="new_password" type="password" autocomplete="new-password" placeholder="請輸入新密碼" v-model="newPassword" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-on-surface-variant" for="cp-confirm">確認新密碼</label>
            <input class="w-full min-w-0 rounded-2xl border border-outline-variant/10 bg-surface-container-low px-4 py-3 text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary/40 focus:outline-none focus:ring-2 focus:ring-primary/20" id="cp-confirm" name="confirm_password" type="password" autocomplete="new-password" placeholder="請再次輸入新密碼" v-model="confirmPassword" />
          </div>
          <button class="mt-4 w-full bg-primary text-white font-headline text-[12px] px-5 py-3 rounded-full hover:bg-primary-dim transition-colors uppercase tracking-[0.18em] font-bold flex items-center justify-center" type="submit">更新密碼</button>
        </form>
      </section>
      <section class="mt-4 grid grid-cols-3 gap-3 text-sm">
        <a class="rounded-[20px] border border-outline-variant/10 bg-white/80 p-4 text-center shadow-[0_18px_36px_rgba(40,88,142,0.08)]" href="#/pages/common/resetpwd">忘記密碼</a>
        <a class="rounded-[20px] border border-outline-variant/10 bg-white/80 p-4 text-center shadow-[0_18px_36px_rgba(40,88,142,0.08)]" href="#/pages/index/serviceCenter">客服協助</a>
        <div aria-hidden="true" class="pointer-events-none min-h-0"></div>
      </section>
    </main>
  </div>
</template>

<script>
import { changePassword } from '@/utils/api'

/**
 * EURNYSE - 更換密碼 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/change-password.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 * 原稿的 onsubmit="return false;" → Vue 的 @submit.prevent
 * 原稿的 onclick="return EurnyseBack.go(...)" → @click 調用 backGo 方法（載入後呼叫 window.EurnyseBack.go）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--change-password",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "ChangePasswordH5",
  data: function () {
    return {
      currentPassword: "",
      newPassword: "",
      confirmPassword: "",
      errorMessage: "",
      successMessage: ""
    };
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "change-password");
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
    },
    handleChangePassword: function () {
      var currentPassword = (this.currentPassword || "").trim();
      var newPassword = (this.newPassword || "").trim();
      var confirmPassword = (this.confirmPassword || "").trim();
      this.errorMessage = "";
      this.successMessage = "";
      if (!currentPassword) {
        this.errorMessage = "請輸入目前密碼";
        return;
      }
      if (!newPassword) {
        this.errorMessage = "請輸入新密碼";
        return;
      }
      if (newPassword !== confirmPassword) {
        this.errorMessage = "兩次輸入的新密碼不一致";
        return;
      }
      var self = this;
      changePassword(currentPassword, newPassword).then(function () {
        self.successMessage = "密碼已更新，請使用新密碼登入。";
        self.currentPassword = "";
        self.newPassword = "";
        self.confirmPassword = "";
      }).catch(function (error) {
        self.errorMessage = (error && error.message) || "更新失敗，請稍後再試。";
      });
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
