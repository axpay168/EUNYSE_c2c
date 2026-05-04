<template>
  <div class="app">
    <div class="login-language-entry">
      <LanguageSelector button-id="loginLanguageButton" />
    </div>
    <div class="logo-image">
      <img :src="logoSrc" alt="EURNYSE Logo" />
    </div>
    <div class="brand-copy">
      <div class="brand-title">EURNYSE</div>
      <div class="brand-subtitle">C2C</div>
    </div>

    <div class="login-card">
      <div class="shine"></div>

      <div class="status error" :class="{ show: errorShow }" id="errorBox">{{ errorMessage }}</div>
      <div class="status success" :class="{ show: successShow }" id="successBox">{{ successMessage }}</div>

      <div class="mode-switch">
        <button class="mode-btn" :class="{ active: currentMode === 'account' }" id="tabAccount" type="button" @click="switchMode('account')">郵箱 / 用戶名</button>
        <button class="mode-btn" :class="{ active: currentMode === 'phone' }" id="tabPhone" type="button" @click="switchMode('phone')">手機號登入</button>
      </div>

      <form class="form" id="loginForm" @submit.prevent="handleLogin">
        <div class="panel" :class="{ active: currentMode === 'account' }" id="panelAccount">
          <div class="field">
            <span class="icon">
              <svg viewBox="0 0 24 24" fill="none" width="18" height="18" stroke="currentColor" stroke-width="1.8">
                <path d="M4 19c0-3.2 3.6-5 8-5s8 1.8 8 5"/>
                <circle cx="12" cy="8" r="4"/>
              </svg>
            </span>
            <input type="text" id="account" placeholder="請輸入郵箱或用戶名" v-model="accountValue" />
          </div>
        </div>

        <div class="panel" :class="{ active: currentMode === 'phone' }" id="panelPhone">
          <div class="phone-row">
            <div class="country-wrap">
              <button type="button" class="country-code" id="countryCodeLink" aria-haspopup="dialog" aria-label="選擇區碼" @click="openAreaPicker"><span id="countryCodeText">{{ countryCode }}</span></button>
            </div>

            <div class="field">
              <span class="icon">
                <svg viewBox="0 0 24 24" fill="none" width="18" height="18" stroke="currentColor" stroke-width="1.8">
                  <rect x="7" y="2.8" width="10" height="18.4" rx="2.4"/>
                  <path d="M10 18.2h4"/>
                </svg>
              </span>
              <input type="tel" id="phone" placeholder="請輸入手機號" v-model="phoneValue" />
            </div>
          </div>
        </div>

        <div class="field">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" width="18" height="18" stroke="currentColor" stroke-width="1.8">
              <rect x="4" y="11" width="16" height="9" rx="2"/>
              <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
            </svg>
          </span>
          <input class="password-input" :type="passwordHidden ? 'password' : 'text'" id="password" placeholder="請輸入密碼" v-model="passwordValue" />
          <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="顯示或隱藏密碼" @click="togglePassword">
            <svg v-show="passwordHidden" id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg v-show="!passwordHidden" id="eyeClosed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M3 3l18 18"/>
              <path d="M10.6 10.7a2 2 0 0 0 2.7 2.7"/>
              <path d="M9.4 5.3A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-4.1 4.8"/>
              <path d="M6.2 6.3C3.7 8 2 12 2 12a17.6 17.6 0 0 0 10 7 10.7 10.7 0 0 0 4-.8"/>
            </svg>
          </button>
        </div>

        <div class="row">
          <label class="remember">
            <input type="checkbox" id="rememberCheckbox" v-model="rememberChecked" />
            <span>記住密碼</span>
          </label>
          <a href="#/pages/common/resetpwd" class="link">忘記密碼？</a>
        </div>

        <button class="submit" type="submit">
          <span>立即登入</span>
          <svg viewBox="0 0 24 24" fill="none" width="18" height="18" stroke="currentColor" stroke-width="2">
            <path d="M5 12h14"/>
            <path d="M13 6l6 6-6 6"/>
          </svg>
        </button>

        <div class="secondary-actions">
          <a class="ghost-btn" href="#/pages/common/register">註冊帳戶</a>
          <a class="ghost-btn" href="javascript:void(0)" @click="openChatwoot($event)">聯繫客服</a>
        </div>
      </form>

      <div class="safe-bar">
        <div class="safe-item"><span class="safe-dot"></span> 資金安全</div>
        <div class="safe-item"><span class="safe-dot"></span> 實名認證</div>
        <div class="safe-item"><span class="safe-dot"></span> 加密防護</div>
      </div>

      <div class="footer-note">
        登入即代表您同意
        <a href="#/pages/common/article">用戶協議</a>
        與
        <a href="#/pages/common/article">隱私政策</a>
      </div>
    </div>
  </div>
</template>

<script>
import { login } from '@/utils/api'
import { brandLogo } from '@/assets/images'

/**
 * EURNYSE 平台登入 — 純 H5 Vue2 Options API 版
 * 以 docs/previews/nnn/login.html 為唯一基準逐字遷移
 * 樣式直接 @import 原稿 preview-entry.css + page-login.css
 * 區碼選擇直接載入原稿 ../shared/area-picker.js
 */
var STORAGE_KEYS = {
  rememberEnabled: "eurnyse.remember.enabled",
  rememberMode: "eurnyse.remember.mode",
  rememberAccount: "eurnyse.remember.account",
  rememberSecret: "eurnyse.remember.secret",
  lastMode: "eurnyse.last.mode",
  lastAccount: "eurnyse.last.account"
};

function encodeSecret(value) {
  try { return btoa(unescape(encodeURIComponent(value))); } catch (e) { return value; }
}

function decodeSecret(value) {
  try { return decodeURIComponent(escape(atob(value))); } catch (e) { return value || ""; }
}

var LOGIN_ERROR_TEXTS = {
  AUTH_INVALID_PARAMS: "請輸入帳號與密碼",
  AUTH_ACCOUNT_INCORRECT: "帳號不存在或輸入錯誤",
  AUTH_ACCOUNT_LOCKED: "帳號已被鎖定，請聯繫客服",
  AUTH_PASSWORD_INCORRECT: "密碼錯誤",
  AUTH_TOKEN_INVALID: "登入狀態已失效，請重新登入",
  AUTH_TOKEN_EXPIRED: "登入狀態已過期，請重新登入",
  AUTH_UNAUTHORIZED: "請先登入"
};

function localizedLoginError(error) {
  var code = error && error.payload && error.payload.error_code;
  if (code && LOGIN_ERROR_TEXTS[code]) return LOGIN_ERROR_TEXTS[code];
  return "登入失敗，請確認帳號密碼。";
}

export default {
  name: "LoginH5",
  data: function () {
    return {
      logoSrc: brandLogo,
      currentMode: "account",
      countryCode: "+84",
      accountValue: "",
      phoneValue: "",
      passwordValue: "",
      passwordHidden: true,
      rememberChecked: false,
      errorMessage: "",
      successMessage: "",
      errorShow: false,
      successShow: false
    };
  },
  mounted: function () {
    // body / html 的 class 與 data-* 與 login.html 對齊（page-login.css / preview-entry.css 皆以 body 選擇器命中）
    try {
      document.body.classList.add("nc2c-page", "nc2c-page--login");
      document.body.setAttribute("data-nc2c-page", "login");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}

    // area-picker.js：與 login.html 完全一致的原稿腳本
    this.loadAreaPickerScript();

    // 區碼選擇回呼：和原稿一致的 window.__eurnyseAreaPickerOnSelect
    var self = this;
    this.__prevAreaPickerOnSelect = window.__eurnyseAreaPickerOnSelect;
    window.__eurnyseAreaPickerOnSelect = function (code) {
      self.countryCode = code;
    };

    // 初始化：與 login.html 末尾的 switchMode("account") + hydrateRemembered() 行為一致
    this.switchMode("account");
    this.hydrateRemembered();

    // 與 login.html 的 window.EurnyseAuth 暴露對齊
    window.EurnyseAuth = { clearRememberedPassword: this.clearRememberedPassword };
  },
  beforeDestroy: function () {
    try {
      document.body.classList.remove("nc2c-page", "nc2c-page--login");
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
    if (window.__eurnyseAreaPickerOnSelect && this.__prevAreaPickerOnSelect !== undefined) {
      window.__eurnyseAreaPickerOnSelect = this.__prevAreaPickerOnSelect;
    }
    if (window.EurnyseAuth && window.EurnyseAuth.clearRememberedPassword === this.clearRememberedPassword) {
      try { delete window.EurnyseAuth; } catch (e) { window.EurnyseAuth = undefined; }
    }
  },
  methods: {
    loadAreaPickerScript: function () {
      if (window.EurnyseAreaPicker) return;
      if (document.querySelector('script[data-eurnyse-area-picker]')) return;
      var pathname = window.location && window.location.pathname ? window.location.pathname : "";
      var isH5Path = /^\/h5(?:\/|$)/.test(pathname);
      var candidates = isH5Path
        ? ["/h5/static/previews/shared/area-picker.js"]
        : [
          "./static/previews/shared/area-picker.js",
          "/static/previews/shared/area-picker.js",
          "/h5/static/previews/shared/area-picker.js"
        ];
      var loadNext = function (index) {
        if (index >= candidates.length || window.EurnyseAreaPicker) return;
        var s = document.createElement("script");
        s.src = candidates[index];
        s.setAttribute("data-eurnyse-area-picker", "1");
        s.async = false;
        s.onerror = function () {
          if (s.parentNode) s.parentNode.removeChild(s);
          loadNext(index + 1);
        };
        document.head.appendChild(s);
      };
      loadNext(0);
    },
    openAreaPicker: function () {
      if (window.EurnyseAreaPicker && typeof window.EurnyseAreaPicker.open === "function") {
        window.EurnyseAreaPicker.open();
      } else {
        // 腳本可能還沒載入完成，延遲一幀再試一次
        var self = this;
        setTimeout(function () {
          if (window.EurnyseAreaPicker && typeof window.EurnyseAreaPicker.open === "function") {
            window.EurnyseAreaPicker.open();
          } else {
            self.showError("區碼選擇器尚未載入完成，請稍後再試。");
          }
        }, 160);
      }
    },
    showError: function (message) {
      this.successShow = false;
      this.successMessage = "";
      this.errorMessage = message;
      this.errorShow = true;
    },
    showSuccess: function (message) {
      this.errorShow = false;
      this.errorMessage = "";
      this.successMessage = message;
      this.successShow = true;
    },
    clearStatus: function () {
      this.errorShow = false;
      this.successShow = false;
      this.errorMessage = "";
      this.successMessage = "";
    },
    switchMode: function (mode) {
      this.currentMode = mode;
      var isAccount = mode === "account";
      if (isAccount) {
        this.phoneValue = "";
      } else {
        this.accountValue = "";
      }
      this.clearStatus();
    },
    persistLastAccount: function (mode, account) {
      localStorage.setItem(STORAGE_KEYS.lastMode, mode);
      localStorage.setItem(STORAGE_KEYS.lastAccount, account);
    },
    persistRemembered: function (mode, account, password) {
      localStorage.setItem(STORAGE_KEYS.rememberEnabled, "1");
      localStorage.setItem(STORAGE_KEYS.rememberMode, mode);
      localStorage.setItem(STORAGE_KEYS.rememberAccount, account);
      localStorage.setItem(STORAGE_KEYS.rememberSecret, encodeSecret(password));
    },
    clearRememberedPassword: function (keepAccount) {
      if (keepAccount === undefined) keepAccount = true;
      var mode = localStorage.getItem(STORAGE_KEYS.rememberMode) || localStorage.getItem(STORAGE_KEYS.lastMode) || "account";
      var account = localStorage.getItem(STORAGE_KEYS.rememberAccount) || localStorage.getItem(STORAGE_KEYS.lastAccount) || "";
      localStorage.removeItem(STORAGE_KEYS.rememberEnabled);
      localStorage.removeItem(STORAGE_KEYS.rememberSecret);
      localStorage.removeItem(STORAGE_KEYS.rememberMode);
      localStorage.removeItem(STORAGE_KEYS.rememberAccount);
      if (keepAccount && account) {
        localStorage.setItem(STORAGE_KEYS.lastMode, mode);
        localStorage.setItem(STORAGE_KEYS.lastAccount, account);
      }
    },
    hydrateRemembered: function () {
      var currentAreaCode = localStorage.getItem("selected_area_code") || "+84";
      this.countryCode = currentAreaCode;
      var rememberedEnabled = localStorage.getItem(STORAGE_KEYS.rememberEnabled) === "1";
      var rememberedMode = localStorage.getItem(STORAGE_KEYS.rememberMode);
      var rememberedAccount = localStorage.getItem(STORAGE_KEYS.rememberAccount);
      var rememberedSecret = localStorage.getItem(STORAGE_KEYS.rememberSecret);
      var lastMode = localStorage.getItem(STORAGE_KEYS.lastMode);
      var lastAccount = localStorage.getItem(STORAGE_KEYS.lastAccount);

      if (rememberedEnabled && rememberedMode && rememberedAccount && rememberedSecret) {
        this.switchMode(rememberedMode);
        this.rememberChecked = true;
        if (rememberedMode === "phone") {
          this.phoneValue = rememberedAccount.indexOf(currentAreaCode) === 0
            ? rememberedAccount.slice(currentAreaCode.length)
            : rememberedAccount;
        } else {
          this.accountValue = rememberedAccount;
        }
        this.passwordValue = decodeSecret(rememberedSecret);
        this.showSuccess("已自動回填最近儲存的登入資訊。");
        return;
      }

      if (lastMode && lastAccount) {
        this.switchMode(lastMode);
        if (lastMode === "phone") {
          this.phoneValue = lastAccount.indexOf(currentAreaCode) === 0
            ? lastAccount.slice(currentAreaCode.length)
            : lastAccount;
        } else {
          this.accountValue = lastAccount;
        }
      }
    },
    togglePassword: function () {
      this.passwordHidden = !this.passwordHidden;
    },
    openChatwoot: function (event) {
      if (event && event.preventDefault) event.preventDefault();
      if (window.EurnyseOpenChatwoot && typeof window.EurnyseOpenChatwoot === "function") {
        window.EurnyseOpenChatwoot();
      }
    },
    handleLogin: function () {
      this.clearStatus();

      var password = (this.passwordValue || "").trim();
      var selectedAreaCode = localStorage.getItem("selected_area_code") || "+84";

      if (this.currentMode === "account") {
        var account = (this.accountValue || "").trim();

        if (!account) {
          this.showError("請輸入郵箱或用戶名");
          return;
        }
        if (!password) {
          this.showError("請輸入密碼");
          return;
        }

        this.persistLastAccount("account", account);
        if (this.rememberChecked) {
          this.persistRemembered("account", account, password);
        } else {
          this.clearRememberedPassword(true);
        }

        sessionStorage.setItem("preview_login_payload", JSON.stringify({
          mode: "account",
          account: account,
          password: password
        }));
      } else {
        var phone = (this.phoneValue || "").trim();

        if (!phone) {
          this.showError("請輸入手機號");
          return;
        }
        if (!password) {
          this.showError("請輸入密碼");
          return;
        }

        var fullPhone = selectedAreaCode + phone;
        this.persistLastAccount("phone", fullPhone);
        if (this.rememberChecked) {
          this.persistRemembered("phone", fullPhone, password);
        } else {
          this.clearRememberedPassword(true);
        }

        sessionStorage.setItem("preview_login_payload", JSON.stringify({
          mode: "phone",
          countryCode: selectedAreaCode,
          phone: phone,
          password: password
        }));
      }

      var loginAccount = this.currentMode === "account" ? account : fullPhone;
      var self = this;
      this.showSuccess("登入提交中，正在進入首頁...");
      login(loginAccount, password).then(function () {
        window.location.hash = "#/pages/index/index";
      }).catch(function (error) {
        self.showError(localizedLoginError(error));
      });
    }
  }
};
</script>

<!--
  直接複用原稿 CSS：
  - ../nc2c/css/preview-entry.css
  - ../nc2c/css/page-login.css
  preview-entry.css 又級聯引用 shared/local-fonts-local.css、draft-pixel-frozen.css、
  nc2c-shell.css、preview-engineered.css，都以相對路徑解析，不重寫、不翻譯。
-->
<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");
@import url("../../static/previews/nc2c/css/page-login.css");

.login-language-entry {
  position: fixed;
  top: 1rem;
  right: 1rem;
  z-index: 20;
}
</style>
