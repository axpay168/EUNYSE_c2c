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

          <button class="w-full flex items-center justify-between gap-4 px-3 py-4 rounded-[20px] bg-surface-bright/82 border border-outline-variant/10 hover:bg-surface-bright transition-colors group text-left" id="accountBindingRow" type="button" @click="openBindingManager">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/10 border border-primary/15 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">link</span>
              </div>
              <div class="min-w-0">
                <div class="font-headline text-lg text-on-surface">綁定關聯帳戶</div>
                <div class="text-sm text-on-surface-variant">用於綁定手機號與郵箱，提升帳戶安全性</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors shrink-0">chevron_right</span>
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

    <div class="fixed inset-0 bg-[#0f2036]/34 items-end justify-center p-4 z-[70]" :class="bindingManagerOpen ? 'flex' : 'hidden'" id="accountBindingModal" @click.self="closeBindingManager">
      <div class="w-full max-w-md rounded-[28px] bg-white/95 border border-outline-variant/14 shadow-[0_24px_48px_rgba(28,70,116,0.18)] p-5 backdrop-blur-2xl">
        <div class="flex items-center justify-between gap-3">
          <button v-if="bindingFormOpen" class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-low" type="button" @click="backToBindingList">
            <span class="material-symbols-outlined">arrow_back</span>
          </button>
          <div v-else class="h-9 w-9" aria-hidden="true"></div>
          <h2 class="font-headline text-xl text-on-surface text-center">{{ bindingFormOpen ? activeBindingLabel + '綁定' : '關聯帳戶管理' }}</h2>
          <button class="flex h-9 w-9 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container-low" type="button" @click="closeBindingManager">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div v-if="!bindingFormOpen" class="mt-5 space-y-3">
          <div v-for="item in bindingItems" :key="item.type" class="flex items-center justify-between gap-3 rounded-[20px] bg-surface-bright/82 border border-outline-variant/10 px-3 py-4">
            <div class="flex min-w-0 items-center gap-3">
              <div class="w-10 h-10 rounded-2xl bg-primary/10 border border-primary/15 text-primary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">{{ item.icon }}</span>
              </div>
              <div class="min-w-0">
                <div class="font-headline text-base text-on-surface">{{ item.label }}</div>
                <div class="text-xs text-on-surface-variant truncate">{{ item.bound ? item.value : '未綁定' }}</div>
              </div>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-2">
              <span class="text-xs font-semibold" :class="item.bound ? 'text-on-surface-variant' : 'text-primary-dim'">{{ item.bound ? '已綁定' : '未綁定' }}</span>
              <button class="rounded-2xl px-3 py-2 text-xs font-semibold" :class="item.bound ? 'bg-surface-container-low text-on-surface-variant cursor-not-allowed' : 'bg-gradient-to-br from-primary to-primary-dim text-white shadow-[0_10px_22px_rgba(47,127,119,0.20)]'" type="button" :disabled="item.bound" @click="openBindingForm(item.type)">
                {{ item.bound ? '已綁定' : '去綁定' }}
              </button>
            </div>
          </div>
        </div>

        <form v-else class="mt-5 space-y-4" @submit.prevent="submitBindingForm">
          <div v-if="activeBindingType === 'phone'">
            <label class="text-sm font-semibold text-on-surface" for="bindingPhoneInput">手機號</label>
            <input id="bindingPhoneInput" v-model="bindingForm.phone" class="mt-2 w-full rounded-2xl border border-outline-variant/20 bg-surface-bright/90 px-4 py-3 text-on-surface outline-none focus:border-primary" type="tel" placeholder="請輸入手機號" />
          </div>

          <div v-if="activeBindingType === 'email'">
            <label class="text-sm font-semibold text-on-surface" for="bindingEmailInput">郵箱</label>
            <input id="bindingEmailInput" v-model="bindingForm.email" class="mt-2 w-full rounded-2xl border border-outline-variant/20 bg-surface-bright/90 px-4 py-3 text-on-surface outline-none focus:border-primary" type="email" placeholder="請輸入郵箱" />
          </div>

          <div v-if="bindingNeedsCode">
            <label class="text-sm font-semibold text-on-surface" for="bindingCodeInput">驗證碼</label>
            <div class="mt-2 flex gap-2">
              <input id="bindingCodeInput" v-model="bindingForm.code" class="min-w-0 flex-1 rounded-2xl border border-outline-variant/20 bg-surface-bright/90 px-4 py-3 text-on-surface outline-none focus:border-primary" type="text" inputmode="numeric" placeholder="請輸入驗證碼" />
              <button class="shrink-0 rounded-2xl px-4 py-3 text-sm font-semibold" :class="bindingCodeCountdown > 0 ? 'bg-surface-container-low text-on-surface-variant' : 'bg-primary/10 text-primary-dim'" type="button" :disabled="bindingCodeCountdown > 0" @click="sendBindingCode">
                {{ bindingCodeCountdown > 0 ? bindingCodeCountdown + 's' : '獲取驗證碼' }}
              </button>
            </div>
          </div>


          <p v-if="bindingStatusMessage" class="text-sm" :class="bindingStatusError ? 'text-red-500' : 'text-primary-dim'">{{ bindingStatusMessage }}</p>

          <div class="grid grid-cols-2 gap-3 pt-1">
            <button class="rounded-2xl bg-surface-container-low px-4 py-3 text-on-surface-variant font-semibold" type="button" @click="backToBindingList">取消</button>
            <button class="rounded-2xl bg-gradient-to-br from-primary to-primary-dim px-4 py-3 text-white font-semibold disabled:opacity-60" type="submit" :disabled="!canSubmitBinding">提交綁定</button>
          </div>
        </form>
        <div v-if="!bindingFormOpen" class="mt-4 text-xs leading-5 text-on-surface-variant">目前綁定狀態會優先讀取帳戶資料，表單提交後會同步保存於本機使用者資料。</div>
      </div>
    </div>
  </div>
</template>

<script>
import { me } from '@/utils/api'
import { getStoredUser, setStoredUser } from '@/utils/session'

/**
 * EURFOREX - 安全中心 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/security-center.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var ACCOUNT_BINDINGS_KEY = "eurforex.account.bindings";

var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--security-center",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

function emptyAccountBindings() {
  return {
    phone: { bound: false, value: "" },
    email: { bound: false, value: "" }
  };
}

function safeTrim(value) {
  return String(value == null ? "" : value).trim();
}

function readLocalBindings() {
  try {
    var raw = localStorage.getItem(ACCOUNT_BINDINGS_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (e) {
    return null;
  }
}

function writeLocalBindings(bindings) {
  try {
    localStorage.setItem(ACCOUNT_BINDINGS_KEY, JSON.stringify(bindings || emptyAccountBindings()));
  } catch (e) {}
}

function maskPhone(value) {
  var text = safeTrim(value);
  var digits = text.replace(/\D/g, "");
  if (digits.length >= 7) return digits.slice(0, 3) + "****" + digits.slice(-4);
  return text;
}

function maskEmail(value) {
  var text = safeTrim(value);
  var parts = text.split("@");
  if (parts.length !== 2) return text;
  var name = parts[0] || "";
  var prefix = name.length <= 2 ? name : name.slice(0, 2);
  return prefix + "****@" + parts[1];
}


export default {
  name: "SecurityCenterH5",
  data: function () {
    return {
      accountBindings: emptyAccountBindings(),
      bindingManagerOpen: false,
      bindingFormOpen: false,
      activeBindingType: "",
      bindingForm: {
        phone: "",
        email: "",
        code: ""
      },
      bindingCodeCountdown: 0,
      bindingCodeTimer: null,
      bindingStatusMessage: "",
      bindingStatusError: false
    };
  },
  computed: {
    bindingItems: function () {
      return [
        {
          type: "phone",
          icon: "smartphone",
          label: "手機號",
          bound: this.accountBindings.phone.bound,
          value: this.accountBindings.phone.value
        },
        {
          type: "email",
          icon: "mail",
          label: "郵箱",
          bound: this.accountBindings.email.bound,
          value: this.accountBindings.email.value
        }
      ];
    },
    activeBindingLabel: function () {
      if (this.activeBindingType === "phone") return "手機號";
      if (this.activeBindingType === "email") return "郵箱";
      return "";
    },
    bindingNeedsCode: function () {
      return this.activeBindingType === "phone" || this.activeBindingType === "email";
    },
    canSubmitBinding: function () {
      if (this.activeBindingType === "phone") {
        return safeTrim(this.bindingForm.phone) && safeTrim(this.bindingForm.code);
      }
      if (this.activeBindingType === "email") {
        return safeTrim(this.bindingForm.email) && safeTrim(this.bindingForm.code);
      }
      return false;
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
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.initAccountBindings();
    this.loadUserBindings();
  },
  beforeDestroy: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
    this.clearBindingCodeTimer();
  },
  methods: {
    backGo: function (event, fallback) {
      if (window.EurforexBack && typeof window.EurforexBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurforexBack.go(fallback);
      }
    },
    initAccountBindings: function () {
      var saved = readLocalBindings();
      var next = emptyAccountBindings();
      if (saved) {
        next.phone = Object.assign(next.phone, saved.phone || {});
        next.email = Object.assign(next.email, saved.email || {});
      }
      this.accountBindings = next;
      this.applyUserBindings(getStoredUser());
    },
    loadUserBindings: function () {
      var self = this;
      me()
        .then(function (user) {
          self.applyUserBindings(user);
        })
        .catch(function () {
          self.applyUserBindings(getStoredUser());
        });
    },
    applyUserBindings: function (user) {
      var next = {
        phone: Object.assign({}, this.accountBindings.phone),
        email: Object.assign({}, this.accountBindings.email)
      };
      if (user) {
        var phone = safeTrim(user.mobile_e164 || user.mobile || user.phone);
        var email = safeTrim(user.email);
        if (phone) next.phone = { bound: true, value: maskPhone(phone) };
        if (email) next.email = { bound: true, value: maskEmail(email) };
      }
      this.accountBindings = next;
      writeLocalBindings(next);
    },
    openBindingManager: function () {
      this.bindingManagerOpen = true;
      this.backToBindingList();
    },
    closeBindingManager: function () {
      this.bindingManagerOpen = false;
      this.backToBindingList();
    },
    backToBindingList: function () {
      this.bindingFormOpen = false;
      this.activeBindingType = "";
      this.bindingStatusMessage = "";
      this.bindingStatusError = false;
      this.bindingForm = { phone: "", email: "", code: "" };
      this.clearBindingCodeTimer();
    },
    openBindingForm: function (type) {
      this.activeBindingType = type;
      this.bindingFormOpen = true;
      this.bindingStatusMessage = "";
      this.bindingStatusError = false;
      this.bindingForm = { phone: "", email: "", code: "" };
      this.clearBindingCodeTimer();
    },
    clearBindingCodeTimer: function () {
      if (this.bindingCodeTimer) {
        clearInterval(this.bindingCodeTimer);
        this.bindingCodeTimer = null;
      }
      this.bindingCodeCountdown = 0;
    },
    sendBindingCode: function () {
      if (this.activeBindingType === "phone" && !safeTrim(this.bindingForm.phone)) {
        this.showBindingStatus("請先輸入手機號", true);
        return;
      }
      if (this.activeBindingType === "email" && !safeTrim(this.bindingForm.email)) {
        this.showBindingStatus("請先輸入郵箱", true);
        return;
      }
      this.showBindingStatus("驗證碼已發送，請查收", false);
      this.bindingCodeCountdown = 60;
      var self = this;
      this.bindingCodeTimer = setInterval(function () {
        self.bindingCodeCountdown -= 1;
        if (self.bindingCodeCountdown <= 0) self.clearBindingCodeTimer();
      }, 1000);
    },
    submitBindingForm: function () {
      var type = this.activeBindingType;
      if (!this.canSubmitBinding) {
        this.showBindingStatus("請確認綁定資料已填寫完整", true);
        return;
      }

      var displayValue = "";
      if (type === "phone") displayValue = maskPhone(this.bindingForm.phone);
      if (type === "email") displayValue = maskEmail(this.bindingForm.email);
      if (!displayValue) {
        this.showBindingStatus("請確認綁定資料是否正確", true);
        return;
      }

      var next = {
        phone: Object.assign({}, this.accountBindings.phone),
        email: Object.assign({}, this.accountBindings.email)
      };
      next[type] = { bound: true, value: displayValue };
      this.accountBindings = next;
      writeLocalBindings(next);
      this.persistBindingToStoredUser(type);
      this.showBindingStatus(this.activeBindingLabel + "已綁定", false);
      var self = this;
      setTimeout(function () {
        self.backToBindingList();
      }, 600);
    },
    persistBindingToStoredUser: function (type) {
      var user = getStoredUser() || {};
      if (type === "phone") user.mobile = safeTrim(this.bindingForm.phone);
      if (type === "email") user.email = safeTrim(this.bindingForm.email);
      setStoredUser(user);
    },
    showBindingStatus: function (message, isError) {
      this.bindingStatusMessage = message;
      this.bindingStatusError = Boolean(isError);
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
