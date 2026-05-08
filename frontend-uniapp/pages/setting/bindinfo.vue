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
          <article v-for="(card, idx) in bankList" :key="card.id || idx" class="bound-bank-card">
            <div class="bound-bank-card__head">
              <div>
                <p class="bound-bank-card__eyebrow">已綁定銀行 {{ idx + 1 }}</p>
                <h3 class="bound-bank-card__title">銀行收款帳戶</h3>
              </div>
              <span class="bound-bank-card__badge">EUR</span>
            </div>
            <div class="bound-bank-card__fields">
              <div class="bound-bank-card__row">
                <span>戶名</span>
                <strong>{{ card.accountName }}</strong>
              </div>
              <div class="bound-bank-card__row">
                <span>銀行名</span>
                <strong>{{ card.bankName }}</strong>
              </div>
              <div class="bound-bank-card__row">
                <span>銀行帳號</span>
                <strong class="font-mono tracking-wide">{{ card.accountNo }}</strong>
              </div>
            </div>
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
      <div class="add-bank-dialog w-full max-w-md rounded-2xl border border-outline-variant/15 bg-surface-bright p-5 shadow-[0_24px_48px_rgba(22,41,63,0.18)]">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 id="add-bank-modal-title" class="font-headline text-lg font-bold text-on-surface">新增銀行</h2>
            <p class="mt-1 text-xs font-medium text-on-surface-variant">請確認戶名、銀行名與銀行帳號正確，提交後會直接綁定。</p>
          </div>
          <button type="button" id="close-add-bank-modal" class="rounded-full p-1.5 text-on-surface-variant hover:bg-surface-container-low" aria-label="關閉" @click="closeModal"><span class="material-symbols-outlined text-[22px] leading-none">close</span></button>
        </div>
        <form id="add-bank-form" class="mt-5 space-y-4" @submit.prevent="handleSubmit">
          <div class="add-bank-field">
            <label for="field-account-name">{{ $t('phrases.戶名') }}</label>
            <input id="field-account-name" ref="firstField" name="accountName" type="text" required autocomplete="name" v-model="form.accountName" placeholder="請輸入收款戶名" />
          </div>
          <div class="add-bank-field">
            <label for="field-bank-name">{{ $t('phrases.銀行名') }}</label>
            <input id="field-bank-name" name="bankName" type="text" required v-model="form.bankName" placeholder="請輸入銀行名稱" />
          </div>
          <div class="add-bank-field">
            <label for="field-bank-account">{{ $t('phrases.銀行帳號') }}</label>
            <input id="field-bank-account" name="bankAccount" type="text" required inputmode="numeric" autocomplete="off" v-model="form.bankAccount" :placeholder="$t('phrases.請輸入帳號')" />
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
    mapPayoutMethod: function (item) {
      return {
        id: item.id,
        accountName: item.account_holder || "—",
        bankName: item.bank_name || this.$t("phrases.銀行帳戶"),
        accountNo: item.account_no_masked || "—"
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

<style scoped>
.bound-bank-card {
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 251, 255, 0.98)),
    radial-gradient(circle at top right, rgba(59, 154, 232, 0.12), transparent 42%);
  border: 1.5px solid rgba(47, 115, 205, 0.22);
  border-radius: 22px;
  box-shadow: 0 14px 30px rgba(29, 80, 135, 0.09), inset 0 1px 0 rgba(255, 255, 255, 0.95);
  padding: 16px;
}
.bound-bank-card__head {
  align-items: flex-start;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}
.bound-bank-card__eyebrow {
  color: #64748b;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
}
.bound-bank-card__title {
  color: #0f2742;
  font-size: 15px;
  font-weight: 900;
  margin-top: 3px;
}
.bound-bank-card__badge {
  background: rgba(47, 115, 205, 0.09);
  border: 1px solid rgba(47, 115, 205, 0.18);
  border-radius: 999px;
  color: #1e5cad;
  flex: 0 0 auto;
  font-size: 11px;
  font-weight: 900;
  padding: 4px 10px;
}
.bound-bank-card__fields {
  display: grid;
  gap: 8px;
  margin-top: 14px;
}
.bound-bank-card__row {
  align-items: flex-start;
  background: rgba(255, 255, 255, 0.76);
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 14px;
  display: flex;
  gap: 12px;
  justify-content: space-between;
  padding: 10px 12px;
}
.bound-bank-card__row span {
  color: #64748b;
  flex: 0 0 auto;
  font-size: 12px;
  font-weight: 800;
}
.bound-bank-card__row strong {
  color: #0f172a;
  flex: 1 1 auto;
  font-size: 13px;
  font-weight: 900;
  text-align: right;
  word-break: break-word;
}
.add-bank-dialog {
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.99), rgba(248, 251, 255, 0.98)),
    radial-gradient(circle at top right, rgba(59, 154, 232, 0.16), transparent 46%) !important;
}
.add-bank-field {
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 16px;
  padding: 12px;
}
.add-bank-field label {
  color: #64748b;
  display: block;
  font-size: 12px;
  font-weight: 900;
  margin-bottom: 8px;
}
.add-bank-field input {
  background: rgba(248, 251, 255, 0.92);
  border: 1.5px solid rgba(148, 163, 184, 0.28);
  border-radius: 14px;
  color: #0f172a;
  font-size: 14px;
  font-weight: 800;
  outline: none;
  padding: 11px 13px;
  transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
  width: 100%;
}
.add-bank-field input:focus {
  background: #ffffff;
  border-color: rgba(47, 115, 205, 0.52);
  box-shadow: 0 0 0 3px rgba(47, 115, 205, 0.12);
}
</style>
