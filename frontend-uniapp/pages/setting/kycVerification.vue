<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="javascript:;" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">身份認證</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>
    <main class="pt-20 pb-16 px-4 md:px-8 max-w-4xl mx-auto">
      <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="glass-panel-high rounded-[24px] px-5 pb-5 pt-2.5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 shrink-0 rounded-2xl bg-primary/10 border border-primary/15 text-primary flex items-center justify-center">
              <span class="material-symbols-outlined">badge</span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-headline text-lg text-on-surface">KYC</h2>
                <span class="material-symbols-outlined shrink-0 text-[20px] text-on-surface-variant" aria-hidden="true">shield_lock</span>
                <span class="shrink-0 rounded-full border border-outline-variant/12 bg-surface-container-high px-3 py-1 text-[11px] font-semibold tracking-[0.14em] text-on-surface-variant">{{ statusLabel }}</span>
              </div>
            </div>
          </div>
          <div class="space-y-3 text-sm">
            <div class="flex items-center justify-between rounded-2xl bg-surface-bright/75 px-4 py-3 border border-outline-variant/10">
              <span class="text-on-surface">身分證件</span>
              <span class="text-on-surface-variant">{{ documentStatusLabel }}</span>
            </div>
            <div class="flex items-center justify-between rounded-2xl bg-surface-bright/75 px-4 py-3 border border-outline-variant/10">
              <span class="text-on-surface">人臉驗證</span>
              <span class="text-on-surface-variant">{{ faceStatusLabel }}</span>
            </div>
            <div class="flex items-center justify-between rounded-2xl bg-surface-bright/75 px-4 py-3 border border-outline-variant/10">
              <span class="text-on-surface">手機綁定</span>
              <span class="font-semibold" :class="mobileBound ? 'text-primary-dim' : 'text-on-surface-variant'">{{ mobileBound ? $t('phrases.已完成') : $t('phrases.未綁定') }}</span>
            </div>
          </div>
          <p v-if="reviewNote" class="mt-4 rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">{{ $t('phrases.駁回原因：') }}{{ reviewNote }}</p>
          <p v-if="statusMessage" class="mt-4 rounded-2xl border px-4 py-3 text-sm" :class="statusError ? 'border-error/20 bg-error/5 text-error' : 'border-primary/20 bg-primary/5 text-primary-dim'">{{ statusMessage }}</p>
        </div>

        <div class="glass-panel-high rounded-[24px] p-5">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-2xl bg-secondary/10 border border-secondary/15 text-secondary flex items-center justify-center">
              <span class="material-symbols-outlined">policy</span>
            </div>
            <div>
              <h2 class="font-headline text-lg text-on-surface">安全提醒</h2>
              <p class="text-sm text-on-surface-variant">請妥善保護帳戶與驗證資料。</p>
            </div>
          </div>
          <div class="space-y-3 text-sm text-on-surface-variant">
            <div class="rounded-2xl bg-surface-bright/75 px-4 py-3 border border-outline-variant/10">通過驗證後可提升單筆與每日結算額度。</div>
            <div class="rounded-2xl bg-surface-bright/75 px-4 py-3 border border-outline-variant/10">請與出金帳戶使用相同法定身分資訊。</div>
            <div class="rounded-2xl bg-surface-bright/75 px-4 py-3 border border-outline-variant/10">審核通常於 1 個工作天內完成。</div>
          </div>
        </div>
      </section>
      <section class="glass-panel-high rounded-[24px] p-5 mt-4">
        <LoadingInlineSpinner v-if="loading" />
        <h2 class="font-headline text-lg text-on-surface">{{ canSubmit ? '提交實名資料' : '已提交資料' }}</h2>
        <form id="kycForm" class="mt-4 space-y-3" @submit.prevent="submitKyc">
          <input id="kycLegalName" class="w-full rounded-2xl border border-outline-variant/20 bg-surface-bright/80 px-4 py-3 text-sm outline-none focus:border-primary/50" :placeholder="$t('phrases.法定姓名')" v-model="form.legalName" :disabled="!canSubmit || submitting" />
          <input id="kycIdNumber" class="w-full rounded-2xl border border-outline-variant/20 bg-surface-bright/80 px-4 py-3 text-sm outline-none focus:border-primary/50" :placeholder="$t('phrases.證件號碼')" v-model="form.idNumber" :disabled="!canSubmit || submitting" />
          <input id="kycFrontUrl" class="w-full rounded-2xl border border-outline-variant/20 bg-surface-bright/80 px-4 py-3 text-sm outline-none focus:border-primary/50" :placeholder="$t('phrases.證件正面圖片 URL')" v-model="form.frontUrl" :disabled="!canSubmit || submitting" />
          <input id="kycBackUrl" class="w-full rounded-2xl border border-outline-variant/20 bg-surface-bright/80 px-4 py-3 text-sm outline-none focus:border-primary/50" :placeholder="$t('phrases.證件反面圖片 URL')" v-model="form.backUrl" :disabled="!canSubmit || submitting" />
          <button id="kycSubmitBtn" type="submit" class="w-full rounded-full bg-primary py-3 font-headline text-sm font-bold tracking-[0.16em] text-on-primary disabled:cursor-not-allowed disabled:opacity-60" :disabled="!canSubmit || submitting">{{ submitting ? $t('phrases.submitInProgress') : submitLabel }}</button>
        </form>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURFOREX - 實名認證 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/kyc-verification.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--kyc-verification",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

export default {
  name: "KycVerificationH5",
  data: function () {
    return {
      loading: false,
      submitting: false,
      application: null,
      mobileBound: false,
      statusMessage: "",
      statusError: false,
      form: {
        legalName: "",
        idNumber: "",
        frontUrl: "",
        backUrl: ""
      }
    };
  },
  computed: {
    currentStatus: function () {
      return this.application && this.application.status ? String(this.application.status) : "";
    },
    statusLabel: function () {
      if (this.loading) return "—";
      if (this.currentStatus === "approved") return this.$t("phrases.已認證");
      if (this.currentStatus === "pending") return this.$t("phrases.審核中");
      if (this.currentStatus === "rejected") return this.$t("phrases.需補件");
      return this.$t("phrases.未認證");
    },
    documentStatusLabel: function () {
      if (this.currentStatus === "approved") return this.$t("phrases.已通過");
      if (this.currentStatus === "pending") return this.$t("phrases.審核中");
      if (this.currentStatus === "rejected") return this.$t("phrases.需補傳");
      return this.$t("phrases.待提交");
    },
    faceStatusLabel: function () {
      if (this.currentStatus === "approved") return this.$t("phrases.已完成");
      if (this.currentStatus === "pending") return this.$t("phrases.審核中");
      if (this.currentStatus === "rejected") return this.$t("phrases.需重新確認");
      return this.$t("phrases.待提交");
    },
    reviewNote: function () {
      return this.currentStatus === "rejected" && this.application ? (this.application.review_note || "") : "";
    },
    canSubmit: function () {
      return this.currentStatus !== "pending" && this.currentStatus !== "approved";
    },
    submitLabel: function () {
      return this.currentStatus === "rejected" ? this.$t("phrases.補傳資料") : this.$t("phrases.提交審核");
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "kyc-verification");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.fetchLatestKyc();
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
    fetchLatestKyc: function () {
      var self = this;
      this.loading = true;
      Promise.all([userApi.kycLatest(), userApi.overview()])
        .then(function (results) {
          var data = results[0] || {};
          var overview = results[1] || {};
          self.application = data.application || null;
          var user = overview.user || {};
          self.mobileBound = Boolean(user.mobile || user.mobile_e164);
          if (self.application) {
            self.form.legalName = self.application.legal_name || "";
            self.form.frontUrl = self.application.id_doc_front_url || "";
            self.form.backUrl = self.application.id_doc_back_url || "";
          }
        })
        .catch(function (error) {
          self.statusError = true;
          self.statusMessage = (error && error.message) || self.$t("phrases.載入 KYC 狀態失敗");
        })
        .finally(function () {
          self.loading = false;
        });
    },
    submitKyc: function () {
      var legalName = (this.form.legalName || "").trim();
      var idNumber = (this.form.idNumber || "").trim();
      var frontUrl = (this.form.frontUrl || "").trim();
      var backUrl = (this.form.backUrl || "").trim();
      if (!legalName || !idNumber || !frontUrl || !backUrl) {
        this.statusError = true;
        this.statusMessage = this.$t("phrases.請完整填寫姓名、證件號碼與證件圖片 URL");
        return;
      }

      var self = this;
      this.submitting = true;
      this.statusMessage = "";
      userApi.submitKyc({
        legal_name: legalName,
        id_number: idNumber,
        id_doc_front_url: frontUrl,
        id_doc_back_url: backUrl
      }).then(function () {
        self.statusError = false;
        self.statusMessage = "資料已提交，請等待平台審核。";
        return self.fetchLatestKyc();
      }).catch(function (error) {
        self.statusError = true;
        self.statusMessage = (error && error.message) || self.$t("phrases.提交失敗，請稍後再試");
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
