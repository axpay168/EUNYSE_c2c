<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="javascript:;" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">銀行綁定</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>
    <main class="pt-20 pb-16 px-4 max-w-3xl mx-auto">
      <section class="rounded-[24px] border border-outline-variant/10 bg-white/80 p-5 shadow-[0_18px_36px_rgba(40,88,142,0.08)]">
        <div id="bank-list" class="space-y-3">
          <LoadingInlineSpinner v-if="loading" aria-label="銀行資料載入" />
          <p v-else-if="loadError" class="rounded-2xl bg-error/5 px-4 py-4 text-center text-sm text-error">{{ loadError }}</p>
          <p v-else-if="!bankList.length" class="rounded-2xl bg-surface-container-low px-4 py-4 text-center text-sm text-on-surface-variant">{{ $t('phrases.尚未綁定銀行帳戶。') }}</p>
          <article v-for="(card, idx) in bankList" :key="card.id || idx" class="bank-card rounded-2xl border border-outline-variant/10 bg-surface-container-low px-4 py-3 text-sm">
            <div class="text-[11px] font-medium text-on-surface-variant">已綁定銀行{{ idx + 1 }}</div>
            <div class="mt-0.5 font-medium text-on-surface">{{ card.accountName }}</div>
            <div class="mt-2 font-semibold text-on-surface">{{ card.bankName }}</div>
            <div class="mt-1 text-on-surface-variant">{{ card.ibanLabel }}</div>
            <div class="mt-1 text-[11px] text-on-surface-variant">{{ $t('phrases.狀態：') }}{{ card.statusLabel }}</div>
          </article>
        </div>
        <button type="button" id="open-add-bank-modal" class="font-headline mt-4 flex w-full items-center justify-center rounded-full bg-primary px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-primary-dim" @click="openModal">新增銀行</button>
      </section>
      <section class="mt-4 grid grid-cols-3 gap-3 text-sm">
        <a class="rounded-[20px] bg-white/80 border border-outline-variant/10 p-4 text-center shadow-[0_18px_36px_rgba(40,88,142,0.08)]" href="#/pages/setting/withdraw">前往提現</a>
        <a class="rounded-[20px] bg-white/80 border border-outline-variant/10 p-4 text-center shadow-[0_18px_36px_rgba(40,88,142,0.08)]" href="#/pages/setting/securityCenter">安全中心</a>
        <a class="rounded-[20px] bg-white/80 border border-outline-variant/10 p-4 text-center shadow-[0_18px_36px_rgba(40,88,142,0.08)]" href="#/pages/index/serviceCenter">客服協助</a>
      </section>
    </main>

    <div id="add-bank-modal" class="fixed inset-0 z-[60] items-center justify-center bg-black/45 p-4" :class="modalOpen ? 'flex' : 'hidden'" role="dialog" aria-modal="true" aria-labelledby="add-bank-modal-title" @click.self="closeModal">
      <div class="w-full max-w-md rounded-2xl border border-outline-variant/15 bg-surface-bright p-5 shadow-[0_24px_48px_rgba(22,41,63,0.18)]">
        <div class="flex items-center justify-between gap-3">
          <h2 id="add-bank-modal-title" class="font-headline text-lg font-bold text-on-surface">新增銀行</h2>
          <button type="button" id="close-add-bank-modal" class="rounded-full p-1.5 text-on-surface-variant hover:bg-surface-container-low" aria-label="關閉" @click="closeModal"><span class="material-symbols-outlined text-[22px] leading-none">close</span></button>
        </div>
        <form id="add-bank-form" class="mt-5 space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label class="text-sm font-medium text-on-surface-variant" for="field-account-name">{{ $t('phrases.戶名') }}</label>
            <input id="field-account-name" ref="firstField" name="accountName" type="text" required autocomplete="name" v-model="form.accountName" class="mt-1.5 w-full rounded-xl border border-outline-variant/40 bg-surface-container-low px-4 py-2.5 text-on-surface outline-none ring-0 transition-colors placeholder:text-on-surface-variant/50 focus:border-primary/50 focus:bg-surface-bright" />
          </div>
          <div>
            <label class="text-sm font-medium text-on-surface-variant" for="field-bank-name">{{ $t('phrases.銀行名') }}</label>
            <input id="field-bank-name" name="bankName" type="text" required v-model="form.bankName" class="mt-1.5 w-full rounded-xl border border-outline-variant/40 bg-surface-container-low px-4 py-2.5 text-on-surface outline-none focus:border-primary/50 focus:bg-surface-bright" />
          </div>
          <div>
            <label class="text-sm font-medium text-on-surface-variant" for="field-bank-account">{{ $t('phrases.銀行帳號') }}</label>
            <input id="field-bank-account" name="bankAccount" type="text" required inputmode="numeric" autocomplete="off" v-model="form.bankAccount" class="mt-1.5 w-full rounded-xl border border-outline-variant/40 bg-surface-container-low px-4 py-2.5 text-on-surface outline-none focus:border-primary/50 focus:bg-surface-bright" :placeholder="$t('phrases.請輸入帳號')" />
          </div>
          <div class="flex gap-3 pt-2">
            <button type="button" id="cancel-add-bank" class="flex-1 rounded-full border border-outline-variant/40 py-3 text-sm font-semibold text-on-surface transition-colors hover:bg-surface-container-low" @click="closeModal">取消</button>
            <button type="submit" class="flex-1 rounded-full bg-primary py-3 text-sm font-bold text-white transition-colors hover:bg-primary-dim disabled:opacity-60" :disabled="submitting">{{ submitting ? $t('phrases.submitInProgress') : $t('phrases.確認') }}</button>
          </div>
          <p v-if="submitMessage" class="text-center text-xs" :class="submitError ? 'text-error' : 'text-primary-dim'">{{ submitMessage }}</p>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURFOREX - 銀行綁定 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/bank-binding.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 * 原稿 IIFE（開關彈窗 + ESC 關閉 + 提交表單追加 bank-card）→ 全部遷移為 methods + v-for
 * maskAccount、escHtml 的行為透過 v-for + {{ }} 自動轉義 + computed 實現，邏輯等效
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--bank-binding",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "BankBindingH5",
  data: function () {
    return {
      modalOpen: false,
      loading: false,
      loadError: "",
      submitting: false,
      submitMessage: "",
      submitError: false,
      bankList: [],
      form: { accountName: "", bankName: "", bankAccount: "" }
    };
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "bank-binding");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");

    this.__escHandler = this.handleEscKey.bind(this);
    document.addEventListener("keydown", this.__escHandler);
    this.fetchBankList();
  },
  beforeDestroy: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
      document.body.style.overflow = "";
    } catch (e) {}
    if (this.__escHandler) document.removeEventListener("keydown", this.__escHandler);
  },
  methods: {
    backGo: function (event, fallback) {
      if (window.EurforexBack && typeof window.EurforexBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurforexBack.go(fallback);
      }
    },
    openModal: function () {
      this.modalOpen = true;
      this.submitMessage = "";
      this.submitError = false;
      try { document.body.style.overflow = "hidden"; } catch (e) {}
      var self = this;
      setTimeout(function () {
        if (self.$refs.firstField) self.$refs.firstField.focus();
      }, 50);
    },
    closeModal: function () {
      this.modalOpen = false;
      try { document.body.style.overflow = ""; } catch (e) {}
    },
    handleEscKey: function (e) {
      if (e.key === "Escape" && this.modalOpen) this.closeModal();
    },
    statusLabel: function (status) {
      var map = { approved: "已通過", pending: "審核中", rejected: "已駁回", disabled: "已停用" };
      return this.$t("phrases." + (map[String(status || "")] || "待審核"));
    },
    mapPayoutMethod: function (item) {
      return {
        id: item.id,
        accountName: item.account_holder || "—",
        bankName: (item.bank_name || this.$t("phrases.銀行帳戶")) + " · EUR",
        ibanLabel: this.$t("phrases.帳號") + " " + (item.account_no_masked || "—"),
        statusLabel: this.statusLabel(item.status)
      };
    },
    fetchBankList: function () {
      var self = this;
      this.loading = true;
      this.loadError = "";
      userApi.payoutMethods()
        .then(function (data) {
          var items = (data && data.items) || [];
          self.bankList = items
            .filter(function (item) { return String(item.channel_type || "") === "bank"; })
            .map(function (item) { return self.mapPayoutMethod(item); });
        })
        .catch(function (error) {
          self.bankList = [];
          self.loadError = (error && error.message) || "銀行資料載入失敗";
        })
        .finally(function () {
          self.loading = false;
        });
    },
    handleSubmit: function () {
      var accountName = (this.form.accountName || "").trim();
      var bankName = (this.form.bankName || "").trim();
      var bankAccount = (this.form.bankAccount || "").trim();
      if (!accountName || !bankName || !bankAccount) return;
      var self = this;
      this.submitting = true;
      this.submitMessage = "";
      this.submitError = false;
      userApi.createPayoutMethod({
        channel_type: "bank",
        account_holder: accountName,
        bank_name: bankName,
        account_no: bankAccount
      }).then(function () {
        self.form = { accountName: "", bankName: "", bankAccount: "" };
        self.closeModal();
        return self.fetchBankList();
      }).catch(function (error) {
        self.submitError = true;
        self.submitMessage = (error && error.message) || "提交失敗，請稍後再試";
      }).finally(function () {
        self.submitting = false;
      });
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
