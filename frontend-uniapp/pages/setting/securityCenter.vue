<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/user" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">安全中心</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>
    <main class="pt-20 pb-16 px-4 md:px-8 max-w-4xl mx-auto">
      <section class="glass-panel-high rounded-[24px] p-3 md:p-4">
        <div class="space-y-2">
          <a class="flex items-center justify-between gap-4 px-3 py-4 rounded-[20px] bg-surface-bright/82 border border-outline-variant/10 hover:bg-surface-bright transition-colors group" href="#/pages/setting/bindinfo">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/10 border border-primary/15 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">account_balance</span>
              </div>
              <div class="min-w-0">
                <div class="font-headline text-lg text-on-surface">銀行綁定</div>
                <div class="text-sm text-on-surface-variant">管理收付款銀行帳戶與結算資料。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors shrink-0">chevron_right</span>
          </a>

          <a class="flex items-center justify-between gap-4 px-3 py-4 rounded-[20px] bg-surface-bright/82 border border-outline-variant/10 hover:bg-surface-bright transition-colors group" href="#/pages/setting/withdrawAddressBinding">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/10 border border-primary/15 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">account_balance_wallet</span>
              </div>
              <div class="min-w-0">
                <div class="font-headline text-lg text-on-surface">提領地址綁定</div>
                <div class="text-sm text-on-surface-variant">管理加密資產提領地址、白名單與到帳驗證。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors shrink-0">chevron_right</span>
          </a>

          <a class="flex items-center justify-between gap-4 px-3 py-4 rounded-[20px] bg-surface-bright/82 border border-outline-variant/10 hover:bg-surface-bright transition-colors group" href="#/pages/setting/kycVerification">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-secondary/10 border border-secondary/15 text-secondary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">verified_user</span>
              </div>
              <div class="min-w-0">
                <div class="font-headline text-lg text-on-surface">身份認證</div>
                <div class="text-sm text-on-surface-variant">提交證件、完成 KYC 並提升交易限額。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-secondary transition-colors shrink-0">chevron_right</span>
          </a>

          <a class="flex items-center justify-between gap-4 px-3 py-4 rounded-[20px] bg-surface-bright/82 border border-outline-variant/10 hover:bg-surface-bright transition-colors group" href="#/pages/setting/changePassword">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-tertiary/10 border border-tertiary/15 text-tertiary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">lock_reset</span>
              </div>
              <div class="min-w-0">
                <div class="font-headline text-lg text-on-surface">更換密碼</div>
                <div class="text-sm text-on-surface-variant">更新登入密碼並強化帳戶安全。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-tertiary transition-colors shrink-0">chevron_right</span>
          </a>

          <button class="w-full flex items-center justify-between gap-4 px-3 py-4 rounded-[20px] bg-surface-bright/82 border border-outline-variant/10 hover:bg-surface-bright transition-colors group text-left" :class="{ 'opacity-60 cursor-not-allowed': !biometricDeviceSupported }" id="biometricToggleRow" type="button" :disabled="!biometricDeviceSupported" @click="onBiometricRowClick">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/10 border border-primary/15 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">fingerprint</span>
              </div>
              <div class="min-w-0">
                <div class="font-headline text-lg text-on-surface">生物識別／密鑰登入</div>
                <div class="text-sm text-on-surface-variant" id="biometricStatusText">{{ biometricStatusText }}</div>
              </div>
            </div>
            <span class="inline-flex items-center rounded-full p-1 bg-surface-container-high border border-outline-variant/10" id="biometricSwitch">
              <span class="w-10 h-6 rounded-full relative transition-colors" :class="biometricTrackOn ? 'bg-primary' : 'bg-outline-variant/40'" id="biometricTrack">
                <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-all" :style="{ left: biometricTrackOn ? '18px' : '2px' }" id="biometricThumb"></span>
              </span>
            </span>
          </button>
        </div>
      </section>
      <section class="glass-panel-high rounded-[24px] p-4 mt-6">
        <div class="flex items-center justify-between gap-4 mb-4">
          <h2 class="font-headline text-lg text-on-surface">延伸入口</h2>
          <a class="text-sm text-primary-dim font-semibold" href="#/pages/index/serviceCenter">需要協助？</a>
        </div>
        <div class="grid grid-cols-3 gap-3 text-sm">
          <a class="rounded-2xl bg-surface-bright/82 border border-outline-variant/10 px-4 py-4 text-center hover:bg-surface-bright transition-colors" href="#/pages/setting/wallet">我的資產</a>
          <a class="rounded-2xl bg-surface-bright/82 border border-outline-variant/10 px-4 py-4 text-center hover:bg-surface-bright transition-colors" href="#/pages/setting/withdraw">提現設定</a>
          <a class="rounded-2xl bg-surface-bright/82 border border-outline-variant/10 px-4 py-4 text-center hover:bg-surface-bright transition-colors" href="#/pages/setting/merchantAuth">商戶認證</a>
        </div>
      </section>
    </main>

    <div class="fixed inset-0 bg-[#0f2036]/34 items-end justify-center p-4 z-[70]" :class="biometricModalOpen ? 'flex' : 'hidden'" id="biometricModal" @click.self="closeBiometricModal">
      <div class="w-full max-w-sm rounded-[28px] bg-white/95 border border-outline-variant/14 shadow-[0_24px_48px_rgba(28,70,116,0.18)] p-5 backdrop-blur-2xl">
        <h2 class="font-headline text-xl text-on-surface" id="biometricModalTitle">{{ biometricModalTitle }}</h2>
        <p class="text-sm text-on-surface-variant mt-2" id="biometricModalBody">{{ biometricModalBody }}</p>
        <div class="grid grid-cols-2 gap-3 mt-5">
          <button class="rounded-2xl bg-surface-container-low px-4 py-3 text-on-surface-variant font-semibold" id="biometricCancelBtn" type="button" @click="closeBiometricModal">取消</button>
          <button class="rounded-2xl bg-gradient-to-br from-primary to-primary-dim px-4 py-3 text-white font-semibold" id="biometricConfirmBtn" type="button" @click="confirmBiometric">{{ biometricConfirmLabel }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * EURNYSE - 安全中心 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/security-center.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 * 原稿內聯 <script> 的生物識別邏輯，完整遷移到 methods / data，
 * localStorage key、toast/狀態文案、switch 動畫全部逐字保留。
 */
var BIO_KEYS = {
  enabled: "eurnyse.biometric.enabled",
  mode: "eurnyse.biometric.mode",
  account: "eurnyse.biometric.account",
  lastMode: "eurnyse.last.mode",
  lastAccount: "eurnyse.last.account"
};

var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--security-center",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

function biometricSupported() {
  return typeof PublicKeyCredential !== "undefined" || /Android|iPhone|iPad|Mac/i.test(navigator.userAgent);
}

export default {
  name: "SecurityCenterH5",
  data: function () {
    return {
      biometricDeviceSupported: true,
      biometricIsEnabled: false,
      biometricStatusText: "綁定後可在登入頁以 Face ID／指紋一鍵驗證（本機密鑰）。",
      biometricModalOpen: false,
      biometricModalTitle: "啟用生物識別登入",
      biometricModalBody: "使用 Face ID 或指紋快速登入此帳號。",
      biometricConfirmLabel: "確認",
      biometricPendingAction: "enable"
    };
  },
  computed: {
    biometricTrackOn: function () {
      return this.biometricIsEnabled && this.biometricDeviceSupported;
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "security-center");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.syncBiometricUI();
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
    getCurrentAccount: function () {
      try {
        return localStorage.getItem(BIO_KEYS.account) || localStorage.getItem(BIO_KEYS.lastAccount) || "";
      } catch (e) { return ""; }
    },
    getCurrentMode: function () {
      try {
        return localStorage.getItem(BIO_KEYS.mode) || localStorage.getItem(BIO_KEYS.lastMode) || "phone";
      } catch (e) { return "phone"; }
    },
    biometricStoredEnabled: function () {
      try { return localStorage.getItem(BIO_KEYS.enabled) === "1"; } catch (e) { return false; }
    },
    syncBiometricUI: function () {
      var supported = biometricSupported();
      var enabled = this.biometricStoredEnabled();
      var account = this.getCurrentAccount();
      this.biometricDeviceSupported = supported;
      this.biometricIsEnabled = enabled;
      if (!supported) {
        this.biometricStatusText = "您的設備不支援生物識別／密鑰登入。";
      } else if (enabled) {
        this.biometricStatusText = "已綁定：" + (account || "目前帳號") + "，登入頁可使用 Face ID／指紋（密鑰）驗證。";
      } else {
        this.biometricStatusText = "綁定後可在登入頁以 Face ID／指紋一鍵驗證（本機密鑰）。";
      }
    },
    openBiometricModal: function (action) {
      this.biometricPendingAction = action;
      if (action === "enable") {
        var account = this.getCurrentAccount();
        this.biometricModalTitle = "啟用生物識別登入";
        this.biometricModalBody = account ? ("將為 " + account + " 啟用 Face ID / 指紋登入。") : "請先完成一次登入或註冊後再綁定。";
        this.biometricConfirmLabel = "立即啟用";
      } else {
        this.biometricModalTitle = "關閉生物識別登入";
        this.biometricModalBody = "關閉後將無法使用生物識別登入，確定嗎？";
        this.biometricConfirmLabel = "確認關閉";
      }
      this.biometricModalOpen = true;
    },
    closeBiometricModal: function () {
      this.biometricModalOpen = false;
    },
    onBiometricRowClick: function () {
      if (!biometricSupported()) return;
      this.openBiometricModal(this.biometricStoredEnabled() ? "disable" : "enable");
    },
    confirmBiometric: function () {
      if (this.biometricPendingAction === "enable") {
        var account = this.getCurrentAccount();
        if (!account) {
          this.closeBiometricModal();
          this.biometricStatusText = "請先完成一次登入或註冊後再綁定。";
          return;
        }
        try {
          localStorage.setItem(BIO_KEYS.enabled, "1");
          localStorage.setItem(BIO_KEYS.account, account);
          localStorage.setItem(BIO_KEYS.mode, this.getCurrentMode());
        } catch (e) {}
      } else {
        try {
          localStorage.removeItem(BIO_KEYS.enabled);
          localStorage.removeItem(BIO_KEYS.mode);
          localStorage.removeItem(BIO_KEYS.account);
        } catch (e) {}
      }
      this.closeBiometricModal();
      this.syncBiometricUI();
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
