<template>
  <div>
    <div class="app">
      <div class="topbar">
        <LanguageSelector button-id="registerLanguageButton" />
      </div>

      <div class="register-card">
        <div class="shine"></div>

        <div class="title">
          <h1 id="pageTitle">{{ texts.pageTitle }}</h1>
          <p id="pageSubtitle">{{ texts.pageSubtitle }}</p>
        </div>

        <div class="status error" :class="{ show: errorShow }" id="errorBox">{{ errorMessage }}</div>
        <div class="status success" :class="{ show: successShow }" id="successBox">{{ successMessage }}</div>

        <div class="mode-switch">
          <button class="mode-btn" :class="{ active: currentMode === 'email' }" id="tabEmail" type="button" @click="switchMode('email')">{{ texts.tabEmail }}</button>
          <button class="mode-btn" :class="{ active: currentMode === 'phone' }" id="tabPhone" type="button" @click="switchMode('phone')">{{ texts.tabPhone }}</button>
        </div>

        <form class="form" id="registerForm" @submit.prevent="handleRegister">
          <div class="panel" :class="{ active: currentMode === 'email' }" id="panelEmail">
            <div class="field">
              <span class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <rect x="3" y="5" width="18" height="14" rx="2"/>
                  <path d="M3 7l9 6 9-6"/>
                </svg>
              </span>
              <input type="email" id="email" :placeholder="texts.emailPlaceholder" v-model="emailValue" />
            </div>
          </div>

          <div class="panel" :class="{ active: currentMode === 'phone' }" id="panelPhone">
            <div class="phone-row">
              <div class="country-wrap">
                <button type="button" class="country-code" id="countryCodeLink" aria-haspopup="dialog" aria-label="選擇區碼" @click="openAreaPicker"><span id="countryCodeText">{{ currentAreaCode }}</span></button>
              </div>

              <div class="field">
                <span class="icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="7" y="2.8" width="10" height="18.4" rx="2.4"/>
                    <path d="M10 18.2h4"/>
                  </svg>
                </span>
                <input type="tel" id="phone" :placeholder="texts.phonePlaceholder" v-model="phoneValue" />
              </div>
            </div>
          </div>

          <div class="field">
            <span class="icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="4" y="11" width="16" height="9" rx="2"/>
                <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
              </svg>
            </span>
            <input class="password-input" :type="passwordHidden1 ? 'password' : 'text'" id="password" :placeholder="texts.passwordPlaceholder" v-model="passwordValue" />
            <button type="button" class="toggle-password" id="togglePasswordBtn" aria-label="顯示或隱藏密碼" @click="passwordHidden1 = !passwordHidden1">
              <svg v-show="passwordHidden1" id="eyeOpen1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg v-show="!passwordHidden1" id="eyeClosed1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M3 3l18 18"/>
                <path d="M10.6 10.7a2 2 0 0 0 2.7 2.7"/>
                <path d="M9.4 5.3A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-4.1 4.8"/>
                <path d="M6.2 6.3C3.7 8 2 12 2 12a17.6 17.6 0 0 0 10 7 10.7 10.7 0 0 0 4-.8"/>
              </svg>
            </button>
          </div>

          <div class="field">
            <span class="icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="4" y="11" width="16" height="9" rx="2"/>
                <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
              </svg>
            </span>
            <input class="password-input" :type="passwordHidden2 ? 'password' : 'text'" id="confirmPassword" :placeholder="texts.confirmPlaceholder" v-model="confirmPasswordValue" />
            <button type="button" class="toggle-password" id="toggleConfirmPasswordBtn" aria-label="顯示或隱藏密碼" @click="passwordHidden2 = !passwordHidden2">
              <svg v-show="passwordHidden2" id="eyeOpen2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg v-show="!passwordHidden2" id="eyeClosed2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M3 3l18 18"/>
                <path d="M10.6 10.7a2 2 0 0 0 2.7 2.7"/>
                <path d="M9.4 5.3A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-4.1 4.8"/>
                <path d="M6.2 6.3C3.7 8 2 12 2 12a17.6 17.6 0 0 0 10 7 10.7 10.7 0 0 0 4-.8"/>
              </svg>
            </button>
          </div>

          <div class="field">
            <span class="icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M5 12h14"/>
                <path d="M12 5v14"/>
              </svg>
            </span>
            <input type="text" id="inviteCode" :placeholder="texts.invitePlaceholder" v-model="inviteCodeValue" />
          </div>

          <label class="agreement">
            <input type="checkbox" id="agreement" v-model="agreementChecked" />
            <span>{{ texts.agreementPrefix }}<a href="#/pages/common/article">{{ texts.agreementUser }}</a> 與 <a href="#/pages/common/article">{{ texts.agreementPrivacy }}</a></span>
          </label>

          <button class="submit" type="submit">
            <span>{{ texts.submit }}</span>
            <svg viewBox="0 0 24 24" fill="none" width="18" height="18" stroke="currentColor" stroke-width="2">
              <path d="M5 12h14"/>
              <path d="M13 6l6 6-6 6"/>
            </svg>
          </button>

        </form>


        <div class="bottom-link">
          {{ texts.bottomLink }}<a href="#/pages/common/login">{{ texts.bottomLogin }}</a>
        </div>
      </div>
    </div>

    <div class="overlay" :class="{ open: showPostRegisterModal }" id="postRegisterModal" @click.self="showPostRegisterModal = false">
      <div class="sheet">
        <div style="font-size:20px;font-weight:700;" id="bindTitle">{{ texts.bindTitle }}</div>
        <p style="font-size:14px;line-height:1.6;color:#5f7594;margin:10px 0 0;" id="bindBody">{{ texts.bindBody }}</p>
        <div class="modal-row">
          <button class="modal-btn secondary" id="bindLaterBtn" type="button" @click="onBindLater">{{ texts.bindLater }}</button>
          <button class="modal-btn primary" id="bindNowBtn" type="button" @click="onBindNow">{{ texts.bindNow }}</button>
        </div>
      </div>
    </div>

    <div class="overlay" :class="{ open: showBiometricModal }" id="registerBiometricModal" @click.self="showBiometricModal = false">
      <div class="sheet">
        <div style="font-size:20px;font-weight:700;" id="biometricTitle">{{ texts.biometricTitle }}</div>
        <p style="font-size:14px;line-height:1.6;color:#5f7594;margin:10px 0 0;" id="biometricPrompt">{{ texts.biometricPrompt }}</p>
        <div class="modal-row">
          <button class="modal-btn secondary" id="bindCancelBtn" type="button" @click="onBindCancel">{{ texts.cancel }}</button>
          <button class="modal-btn primary" id="bindConfirmBtn" type="button" @click="onBindConfirm">{{ texts.verifyNow }}</button>
        </div>
      </div>
    </div>


    <div class="overlay" :class="{ open: showLangOverlay }" id="langOverlay" @click.self="showLangOverlay = false">
      <div class="sheet">
        <div style="text-align:center;font-size:20px;font-weight:700;" id="languageTitle">{{ texts.languageTitle }}</div>
        <div class="lang-list" id="langList">
          <button v-for="item in LANGS" :key="item.value" type="button" class="lang-item" :class="{ active: item.value === currentLang }" :data-value="item.value" @click="selectLang(item.value)">
            <span>{{ item.name }}</span>
            <span>{{ item.short }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { assignRandomAvatarForUser } from '@/common/avatarPool'
import { register as registerAccount } from '@/utils/api'
import { hasSession } from '@/utils/session'
import { getStoredLang } from '@/common/langStorage'
import { setLocale } from '@/common/i18n'

/**
 * EURFOREX 平台註冊 — 純 H5 Vue2 Options API 版
 * 以 docs/previews/nnn/register.html 為唯一基準逐字遷移
 * 樣式直接 @import 原稿 preview-entry.css + page-register.css
 * 區碼選擇直接載入原稿 ../shared/area-picker.js
 * 本檔策略與 login-h5.vue 保持一致（STORAGE / 區碼腳本注入 / body class / beforeDestroy 清理）
 */
var STORAGE_KEYS = {
  lastMode: "eurforex.last.mode",
  lastAccount: "eurforex.last.account",
  biometricEnabled: "eurforex.biometric.enabled",
  biometricMode: "eurforex.biometric.mode",
  biometricAccount: "eurforex.biometric.account"
};

var LANGS = [
  { name: "English", value: "eng", short: "EN" },
  { name: "繁體中文", value: "zh-Hant", short: "ZH" },
  { name: "简体中文", value: "zh-Hans", short: "CN" },
  { name: "日本語", value: "jp", short: "JP" },
  { name: "한국어", value: "kr", short: "KR" },
  { name: "Việt Nam", value: "vi", short: "VI" },
  { name: "Deutsch", value: "de", short: "DE" },
  { name: "Français", value: "fr", short: "FR" },
  { name: "Italiano", value: "it", short: "IT" },
  { name: "Nederlands", value: "nl", short: "NL" },
  { name: "Español", value: "es", short: "ES" },
  { name: "Português", value: "pt", short: "PT" },
  { name: "Ελληνικά", value: "el", short: "EL" },
  { name: "Dansk", value: "da", short: "DA" },
  { name: "Svenska", value: "sv", short: "SV" },
  { name: "Suomi", value: "fi", short: "FI" },
  { name: "Polski", value: "pl", short: "PL" },
  { name: "Magyar", value: "hu", short: "HU" },
  { name: "ไทย", value: "th", short: "TH" },
  { name: "हिन्दी", value: "hi", short: "HI" },
  { name: "اردو", value: "ur", short: "UR" },
  { name: "नेपाली", value: "ne", short: "NE" },
  { name: "فارسی", value: "fa", short: "FA" }
];

var TEXTS = {
  "zh-Hant": {
    pageTitle: "建立帳戶",
    pageSubtitle: "填寫以下資訊，註冊你的 C2C 平台帳戶。",
    tabEmail: "郵箱註冊",
    tabPhone: "電話註冊",
    emailPlaceholder: "請輸入郵箱",
    phonePlaceholder: "請輸入手機號",
    invitePlaceholder: "請輸入邀請碼（必填）",
    passwordPlaceholder: "請輸入密碼",
    confirmPlaceholder: "請再次輸入密碼",
    agreementPrefix: "我已閱讀並同意 ",
    agreementUser: "用戶協議",
    agreementPrivacy: "隱私政策",
    submit: "立即註冊",
    bottomLink: "已有帳戶？",
    bottomLogin: "立即登入",
    emailRequired: "請輸入郵箱",
    phoneRequired: "請輸入手機號",
    inviteRequired: "請輸入邀請碼",
    passwordRequired: "請輸入密碼",
    confirmRequired: "請再次輸入密碼",
    passwordMismatch: "兩次輸入的密碼不一致",
    agreementRequired: "請先勾選用戶協議與隱私政策",
    registerSuccess: "註冊資料驗證完成。",
    registerFailed: "註冊失敗，請稍後再試。",
    languageTitle: "語言",
    bindTitle: "註冊成功！",
    bindBody: "是否啟用生物識別快速登入？下次登入時可使用 Face ID 或指紋一鍵驗證。",
    bindLater: "稍後再說",
    bindNow: "立即啟用",
    biometricTitle: "生物識別驗證",
    biometricPrompt: "請驗證 Face ID / 指紋，完成本裝置密鑰登入綁定。",
    cancel: "取消",
    verifyNow: "立即驗證",
    biometricEnabledToast: "生物識別已啟用",
    unsupported: "目前裝置不支援生物識別"
  },
  "zh-Hans": {
    pageTitle: "创建账户",
    pageSubtitle: "填写以下信息，注册你的 C2C 平台账户。",
    tabEmail: "邮箱注册",
    tabPhone: "电话注册",
    emailPlaceholder: "请输入邮箱",
    phonePlaceholder: "请输入手机号",
    invitePlaceholder: "请输入邀请码（必填）",
    passwordPlaceholder: "请输入密码",
    confirmPlaceholder: "请再次输入密码",
    agreementPrefix: "我已阅读并同意 ",
    agreementUser: "用户协议",
    agreementPrivacy: "隐私政策",
    submit: "立即注册",
    bottomLink: "已有账户？",
    bottomLogin: "立即登录",
    emailRequired: "请输入邮箱",
    phoneRequired: "请输入手机号",
    inviteRequired: "请输入邀请码",
    passwordRequired: "请输入密码",
    confirmRequired: "请再次输入密码",
    passwordMismatch: "两次输入的密码不一致",
    agreementRequired: "请先勾选用户协议与隐私政策",
    registerSuccess: "注册资料验证完成。",
    registerFailed: "注册失败，请稍后再试。",
    languageTitle: "语言",
    bindTitle: "注册成功！",
    bindBody: "是否启用生物识别快速登录？下次登录时可使用 Face ID 或指纹一键验证。",
    bindLater: "稍后再说",
    bindNow: "立即启用",
    biometricTitle: "生物识别验证",
    biometricPrompt: "请验证 Face ID / 指纹，完成本设备密钥登录绑定。",
    cancel: "取消",
    verifyNow: "立即验证",
    biometricEnabledToast: "生物识别已启用",
    unsupported: "当前设备不支持生物识别"
  },
  "eng": {
    pageTitle: "Create Account",
    pageSubtitle: "Fill in the information below to create your C2C account.",
    tabEmail: "Email Register",
    tabPhone: "Phone Register",
    emailPlaceholder: "Enter email",
    phonePlaceholder: "Enter phone number",
    invitePlaceholder: "Enter invitation code",
    passwordPlaceholder: "Enter password",
    confirmPlaceholder: "Confirm password",
    agreementPrefix: "I have read and agree to the ",
    agreementUser: "User Agreement",
    agreementPrivacy: "Privacy Policy",
    submit: "Register Now",
    bottomLink: "Already have an account?",
    bottomLogin: "Login Now",
    emailRequired: "Please enter email",
    phoneRequired: "Please enter phone number",
    inviteRequired: "Please enter invitation code",
    passwordRequired: "Please enter password",
    confirmRequired: "Please confirm password",
    passwordMismatch: "Passwords do not match",
    agreementRequired: "Please agree to the terms first",
    registerSuccess: "Registration validated.",
    registerFailed: "Registration failed. Please try again later.",
    languageTitle: "Language",
    bindTitle: "Registration Success!",
    bindBody: "Enable biometric quick sign-in for next time?",
    bindLater: "Later",
    bindNow: "Enable",
    biometricTitle: "Biometric Verification",
    biometricPrompt: "Verify Face ID / fingerprint to finish binding.",
    cancel: "Cancel",
    verifyNow: "Verify",
    biometricEnabledToast: "Biometric sign-in enabled",
    unsupported: "Biometric is not supported on this device"
  }
};

var REGISTER_ERROR_TEXTS = {
  "zh-Hant": {
    AUTH_INVALID_PARAMS: "請填寫完整註冊資料",
    AUTH_INVITATION_REQUIRED: "請輸入邀請碼",
    AUTH_INVITATION_INVALID: "邀請碼錯誤或不存在",
    AUTH_PASSWORD_TOO_SHORT: "密碼至少需要 6 位字元",
    AUTH_EMAIL_INVALID: "Email 格式不正確",
    AUTH_EMAIL_EXISTS: "此 Email 已被註冊",
    AUTH_MOBILE_INVALID: "手機號碼格式不正確",
    AUTH_MOBILE_EXISTS: "此手機號碼已被註冊"
  },
  "zh-Hans": {
    AUTH_INVALID_PARAMS: "请填写完整注册资料",
    AUTH_INVITATION_REQUIRED: "请输入邀请码",
    AUTH_INVITATION_INVALID: "邀请码错误或不存在",
    AUTH_PASSWORD_TOO_SHORT: "密码至少需要 6 位字符",
    AUTH_EMAIL_INVALID: "Email 格式不正确",
    AUTH_EMAIL_EXISTS: "此 Email 已被注册",
    AUTH_MOBILE_INVALID: "手机号格式不正确",
    AUTH_MOBILE_EXISTS: "此手机号已被注册"
  },
  eng: {
    AUTH_INVALID_PARAMS: "Please complete the registration form.",
    AUTH_INVITATION_REQUIRED: "Please enter invitation code.",
    AUTH_INVITATION_INVALID: "Invitation code is invalid or does not exist.",
    AUTH_PASSWORD_TOO_SHORT: "Password must be at least 6 characters.",
    AUTH_EMAIL_INVALID: "Email format is invalid.",
    AUTH_EMAIL_EXISTS: "This email is already registered.",
    AUTH_MOBILE_INVALID: "Mobile number is invalid.",
    AUTH_MOBILE_EXISTS: "This mobile number is already registered."
  }
};

function localizedRegisterError(error, lang, fallback) {
  var currentLang = TEXTS[lang] ? lang : "zh-Hant";
  var code = error && error.payload && error.payload.error_code;
  var texts = REGISTER_ERROR_TEXTS[currentLang] || REGISTER_ERROR_TEXTS["zh-Hant"];
  if (code && texts[code]) return texts[code];
  return fallback || (TEXTS[currentLang] && TEXTS[currentLang].registerFailed) || "註冊失敗，請稍後再試。";
}


function supportsBiometric() {
  return typeof PublicKeyCredential !== "undefined" || /Android|iPhone|iPad|Mac/i.test(navigator.userAgent);
}

export default {
  name: "RegisterH5",
  data: function () {
    return {
      currentMode: "email",
      currentLang: getStoredLang(),
      currentAreaCode: "+84",
      emailValue: "",
      phoneValue: "",
      passwordValue: "",
      confirmPasswordValue: "",
      inviteCodeValue: "",
      passwordHidden1: true,
      passwordHidden2: true,
      agreementChecked: false,
      errorMessage: "",
      successMessage: "",
      errorShow: false,
      successShow: false,
      showPostRegisterModal: false,
      showBiometricModal: false,
      showLangOverlay: false,
      pendingRegisterAccount: "",
      LANGS: LANGS
    };
  },
  computed: {
    texts: function () {
      return TEXTS[this.currentLang] || TEXTS["zh-Hant"];
    },
    activeLang: function () {
      var self = this;
      var hit = null;
      for (var i = 0; i < LANGS.length; i++) {
        if (LANGS[i].value === self.currentLang) { hit = LANGS[i]; break; }
      }
      return hit || LANGS[0];
    }
  },
  watch: {
    currentLang: function (val) {
      setLocale(val);
    }
  },
  mounted: function () {
    if (hasSession()) {
      window.location.hash = "#/pages/index/index";
      return;
    }

    // body / html 的 class 與 data-* 與 register.html 對齊
    // page-register.css 的 body.nc2c-page.nc2c-page--register::before/::after 必須靠這組 class 命中
    try {
      document.body.classList.add("nc2c-page", "nc2c-page--register");
      document.body.setAttribute("data-nc2c-page", "register");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}

    // 讀取之前儲存的語言與區碼（與原稿：localStorage 直取）
    try {
      this.currentLang = getStoredLang();
      this.currentAreaCode = localStorage.getItem("selected_area_code") || "+84";
    } catch (e) {}

    // 同步 html lang 屬性（不等 watcher，mount 時先設一次）
    try {
      setLocale(this.currentLang);
    } catch (e) {}

    // area-picker.js：與 register.html 完全一致的原稿腳本
    this.loadAreaPickerScript();

    // 區碼選擇回呼：和原稿一致的 window.__eurforexAreaPickerOnSelect
    var self = this;
    this.__prevAreaPickerOnSelect = window.__eurforexAreaPickerOnSelect;
    window.__eurforexAreaPickerOnSelect = function (code) {
      self.currentAreaCode = code;
    };

    // 與原稿結尾順序一致：renderTexts -> renderLangList -> switchMode("email")
    // renderTexts 在 Vue 裡是 computed + v-bind 驅動；renderLangList 是 v-for 驅動。
    this.switchMode("email");
    this.__onLangChange = function (event) {
      self.currentLang = event && event.detail ? event.detail : getStoredLang();
    };
    window.addEventListener("app:langchange", this.__onLangChange);
  },
  beforeDestroy: function () {
    try {
      document.body.classList.remove("nc2c-page", "nc2c-page--register");
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
    if (window.__eurforexAreaPickerOnSelect && this.__prevAreaPickerOnSelect !== undefined) {
      window.__eurforexAreaPickerOnSelect = this.__prevAreaPickerOnSelect;
    }
    if (this.__onLangChange) window.removeEventListener("app:langchange", this.__onLangChange);
  },
  methods: {
    loadAreaPickerScript: function () {
      if (window.EurforexAreaPicker) return;
      if (document.querySelector('script[data-eurforex-area-picker]')) return;
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
        if (index >= candidates.length || window.EurforexAreaPicker) return;
        var s = document.createElement("script");
        s.src = candidates[index];
        s.setAttribute("data-eurforex-area-picker", "1");
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
      if (window.EurforexAreaPicker && typeof window.EurforexAreaPicker.open === "function") {
        window.EurforexAreaPicker.open();
      } else {
        var self = this;
        setTimeout(function () {
          if (window.EurforexAreaPicker && typeof window.EurforexAreaPicker.open === "function") {
            window.EurforexAreaPicker.open();
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
    persistLastAccount: function (mode, account) {
      localStorage.setItem(STORAGE_KEYS.lastMode, mode);
      localStorage.setItem(STORAGE_KEYS.lastAccount, account);
    },
    bindBiometric: function (mode, account) {
      localStorage.setItem(STORAGE_KEYS.biometricEnabled, "1");
      localStorage.setItem(STORAGE_KEYS.biometricMode, mode);
      localStorage.setItem(STORAGE_KEYS.biometricAccount, account);
    },
    switchMode: function (mode) {
      this.currentMode = mode;
      var isEmail = mode === "email";
      if (isEmail) {
        this.phoneValue = "";
      } else {
        this.emailValue = "";
      }
      this.clearStatus();
    },
    selectLang: function (value) {
      this.currentLang = value;
      setLocale(value);
      this.showLangOverlay = false;
    },
    finalizeRegister: function () {
      setTimeout(function () {
        window.location.href = "#/pages/index/index";
      }, 500);
    },
    handleRegister: function () {
      this.clearStatus();

      var texts = this.texts;
      var emailValue = (this.emailValue || (document.getElementById("email") || {}).value || "").trim();
      var phoneValue = (this.phoneValue || (document.getElementById("phone") || {}).value || "").trim();
      var inviteCode = (this.inviteCodeValue || (document.getElementById("inviteCode") || {}).value || "").trim();
      var password = (this.passwordValue || (document.getElementById("password") || {}).value || "").trim();
      var confirmPassword = (this.confirmPasswordValue || (document.getElementById("confirmPassword") || {}).value || "").trim();
      var agreement = !!this.agreementChecked;

      this.emailValue = emailValue;
      this.phoneValue = phoneValue;
      this.inviteCodeValue = inviteCode;
      this.passwordValue = password;
      this.confirmPasswordValue = confirmPassword;

      if (this.currentMode === "email" && !emailValue) { return this.showError(texts.emailRequired); }
      if (this.currentMode === "phone" && !phoneValue) { return this.showError(texts.phoneRequired); }
      if (!password) { return this.showError(texts.passwordRequired); }
      if (!confirmPassword) { return this.showError(texts.confirmRequired); }
      if (password !== confirmPassword) { return this.showError(texts.passwordMismatch); }
      if (!inviteCode) { return this.showError(texts.inviteRequired); }
      if (!agreement) { return this.showError(texts.agreementRequired); }

      this.pendingRegisterAccount = this.currentMode === "email"
        ? emailValue
        : (this.currentAreaCode + phoneValue);

      this.persistLastAccount(this.currentMode, this.pendingRegisterAccount);

      var payload = {
        mode: this.currentMode,
        inviteCode: inviteCode,
        password: password
      };

      if (this.currentMode === "email") {
        payload.email = emailValue;
      } else {
        payload.countryCode = this.currentAreaCode;
        payload.phone = phoneValue;
      }

      var self = this;
      registerAccount(this.pendingRegisterAccount, password, inviteCode, this.currentLang || "zh-Hant")
        .then(function () {
          assignRandomAvatarForUser(self.pendingRegisterAccount);
          try { sessionStorage.setItem("preview_register_payload", JSON.stringify(payload)); } catch (e) {}
          self.showSuccess(texts.registerSuccess);
          self.showPostRegisterModal = true;
        })
        .catch(function (error) {
          self.showError(localizedRegisterError(error, self.currentLang, texts.registerFailed));
        });
    },
    onBindLater: function () {
      this.showPostRegisterModal = false;
      this.finalizeRegister();
    },
    onBindNow: function () {
      this.showPostRegisterModal = false;
      if (!supportsBiometric()) {
        this.showError(this.texts.unsupported);
        return this.finalizeRegister();
      }
      this.showBiometricModal = true;
    },
    onBindCancel: function () {
      this.showBiometricModal = false;
      this.finalizeRegister();
    },
    onBindConfirm: function () {
      this.bindBiometric(this.currentMode, this.pendingRegisterAccount);
      this.showBiometricModal = false;
      this.showSuccess(this.texts.biometricEnabledToast);
      this.finalizeRegister();
    }
  }
};
</script>

<!--
  直接複用原稿 CSS：
  - ../nc2c/css/preview-entry.css
  - ../nc2c/css/page-register.css
  不重寫、不翻譯；body.nc2c-page.nc2c-page--register 選擇器靠 mounted 時加到 document.body 的 class 命中。
-->
<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");
@import url("../../static/previews/nc2c/css/page-register.css");

</style>
