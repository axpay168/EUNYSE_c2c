/**
 * EURNYSE 後台：模組切換、hash 同步、側欄狀態
 */
(function (window, document) {
  if (typeof window.__ADMIN_RESOLVE_API_BASE__ !== "function") {
    function adminDefaultApiBase() {
      var host = String((window.location && window.location.hostname) || "");
      var port = String((window.location && window.location.port) || "");
      if (/^(localhost|127\.0\.0\.1)$/i.test(host) && port !== "8000") {
        return "http://127.0.0.1:8000";
      }
      try {
        var o = window.location && window.location.origin;
        if (o && /^https?:\/\//i.test(o)) return o.replace(/\/$/, "");
      } catch (e) {}
      return "http://127.0.0.1:8000";
    }

    window.__ADMIN_RESOLVE_API_BASE__ = function () {
      var cfg = window.__ADMIN_RUNTIME_CONFIG__ || {};
      if (cfg.apiBaseUrl != null && String(cfg.apiBaseUrl).trim() !== "") {
        return String(cfg.apiBaseUrl).trim().replace(/\/$/, "");
      }
      var legacyBase = cfg.apiBase != null ? String(cfg.apiBase).replace(/\/$/, "") : "";
      var legacyPath = cfg.apiBasePath != null ? String(cfg.apiBasePath) : "";
      if (legacyBase !== "") {
        var joined =
          legacyBase +
          (legacyPath === "" || legacyPath === "/" ? "" : (legacyPath.charAt(0) === "/" ? legacyPath : "/" + legacyPath));
        return joined.replace(/\/$/, "");
      }
      return adminDefaultApiBase();
    };
  }

  var ADMIN_AUTH_KEY = "eurnyse_admin_frontend_auth";
  var ADMIN_PROFILE_KEY = "eurnyse_admin_profile";
  var ADMIN_LAST_ACCOUNT_KEY = "eurnyse_admin_last_account";
  var ADMIN_NOTIFY_KEY = "eurnyse_admin_deposit_alert_settings";
  var MODULE_IDS = [
    "dashboard",
    "users",
    "wallets",
    "tiers",
    "deposits",
    "deposit-addresses",
    "withdrawals",
    "eur-swap-withdraw-config",
    "orders",
    "financial-products",
    "financial-orders",
    "listings",
    "trade-feed",
    "home-content",
    "kyc",
    "payout-methods",
    "auth-events",
    "system-config",
    "admin-users"
  ];

  var LABELS = {
    dashboard: "儀表板",
    users: "使用者管理",
    wallets: "資金錢包",
    tiers: "等級與限額",
    deposits: "充值審核",
    "deposit-addresses": "充值地址管理",
    withdrawals: "提現審核",
    "eur-swap-withdraw-config": "換匯提領配置",
    orders: "訂單中心",
    "financial-products": "理財配置",
    "financial-orders": "理財訂單",
    listings: "商戶掛單",
    "trade-feed": "成交播報",
    "home-content": "運營配置",
    kyc: "實名 / KYC",
    "payout-methods": "收款方",
    "auth-events": "認證事件",
    "system-config": "系統配置",
    "admin-users": "後台管理員"
  };

  function normalizeHash() {
    var h = (window.location.hash || "").replace(/^#/, "").trim();
    if (!h || MODULE_IDS.indexOf(h) === -1) return "dashboard";
    return h;
  }

  function setActiveView(viewId) {
    MODULE_IDS.forEach(function (id) {
      var el = document.getElementById("view-" + id);
      if (el) {
        var on = id === viewId;
        el.classList.toggle("is-active", on);
        el.setAttribute("aria-hidden", on ? "false" : "true");
      }
    });

    document.querySelectorAll("[data-admin-view]").forEach(function (btn) {
      var v = btn.getAttribute("data-admin-view");
      btn.setAttribute("aria-current", v === viewId ? "page" : "false");
    });

    var crumb = document.getElementById("admin-crumb-active");
    if (crumb) crumb.textContent = LABELS[viewId] || viewId;

    document.title = "EURNYSE 後台 — " + (LABELS[viewId] || viewId);
  }

  function closeMobileNav() {
    var app = document.querySelector(".admin-app");
    if (app) app.classList.remove("admin-app--nav-open");
    var menu = document.getElementById("admin-menu-toggle");
    if (menu) menu.setAttribute("aria-expanded", "false");
  }

  function openMobileNav() {
    var app = document.querySelector(".admin-app");
    if (app) app.classList.add("admin-app--nav-open");
    var menu = document.getElementById("admin-menu-toggle");
    if (menu) menu.setAttribute("aria-expanded", "true");
  }

  function toggleMobileNav() {
    var app = document.querySelector(".admin-app");
    if (!app) return;
    if (app.classList.contains("admin-app--nav-open")) closeMobileNav();
    else openMobileNav();
  }

  function showActionNotice(message) {
    var el = document.getElementById("admin-action-notice");
    if (!el) {
      el = document.createElement("div");
      el.id = "admin-action-notice";
      el.className = "admin-action-notice";
      el.setAttribute("role", "status");
      el.setAttribute("aria-live", "polite");
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add("is-visible");
    clearTimeout(el._timer);
    el._timer = setTimeout(function () {
      el.classList.remove("is-visible");
    }, 2600);
  }

  function initFallbackButtonFeedback() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest ? e.target.closest("button.admin-btn") : null;
      if (!btn) return;
      if (btn.hasAttribute("data-admin-modal-open")) return;
      if (btn.id || btn.closest(".admin-api-panel") || btn.closest(".admin-segment") || btn.closest(".admin-login-form")) return;
      var label = (btn.textContent || "").replace(/\s+/g, " ").trim() || "此操作";
      if (label === "前往") {
        var row = btn.closest("tr");
        var badge = row && row.querySelector(".admin-badge");
        var type = badge ? badge.textContent.trim() : "待辦";
        var map = { "充值": "deposits", "提現": "withdrawals", "糾紛": "orders" };
        if (map[type]) {
          window.location.hash = "#" + map[type];
          showActionNotice("已切換到「" + (LABELS[map[type]] || map[type]) + "」模組，請在列表中處理該項目。");
          return;
        }
      }
      showActionNotice(label + "：此功能目前為驗收原型，尚未接後端操作。");
    });
  }

  function setAuthState(isAuthed) {
    document.body.classList.remove("admin-auth-pending");
    document.body.classList.toggle("admin-auth-ready", Boolean(isAuthed));
    document.body.classList.toggle("admin-auth-locked", !isAuthed);
  }

  function adminStorageGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function adminStorageSet(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (e) {}
  }

  function adminStorageRemove(key) {
    try {
      window.localStorage.removeItem(key);
    } catch (e) {}
  }

  function readCachedAdminProfile() {
    try {
      var s = adminStorageGet(ADMIN_PROFILE_KEY);
      if (!s) return null;
      var o = JSON.parse(s);
      return typeof o === "object" && o ? o : null;
    } catch (e) {
      return null;
    }
  }

  /**
   * 頂欄主文案：優先顯示後端帳號／名稱（與列表「帳號」一致），不要用資料庫內建信箱蓋掉使用者輸入的登入名。
   * 信箱僅作 chip title 補充（見 buildAdminChipTitle）。
   */
  function displayAdminBrief(admin, typedAccount) {
    admin = admin || {};
    var acct = String(admin.account || admin.name || "").trim();
    typedAccount = String(typedAccount || "").trim();
    var email = String(admin.email || "").trim();

    if (acct) return acct;
    if (typedAccount) return typedAccount;
    if (email) return email;
    return "管理員";
  }

  function buildAdminChipTitle(admin, brief) {
    admin = admin || {};
    var email = String(admin.email || "").trim();
    if (!email || email === brief) return brief;
    return brief + " · " + email;
  }

  function initialsForAdminLabel(label) {
    label = String(label || "").trim();
    if (!label) return "?";
    if (label.indexOf("@") >= 0) {
      var local = (label.split("@")[0] || label).replace(/[^a-zA-Z0-9\u4e00-\u9fff]/g, "");
      if (local.length >= 2) return local.slice(0, 2).toUpperCase();
      if (local.length === 1) return (local + "•").toUpperCase();
    }
    var parts = label.split(/[\s._-]+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
    }
    if (label.length >= 2) return label.slice(0, 2).toUpperCase();
    return (label.charAt(0) || "?").toUpperCase();
  }

  function updateTopbarFromProfile(admin) {
    var labelEl = document.getElementById("admin-user-chip-label");
    var avEl = document.getElementById("admin-user-chip-avatar");
    var inviteEl = document.getElementById("admin-staff-invite-code");
    var inviteChip = document.getElementById("admin-staff-invite-chip");
    var chip = labelEl && labelEl.closest ? labelEl.closest(".admin-user-chip") : null;
    if (!labelEl || !avEl) return;
    if (!admin) {
      labelEl.textContent = "未登入";
      avEl.textContent = "—";
      if (inviteEl) inviteEl.textContent = "------";
      if (inviteChip) inviteChip.setAttribute("title", "管理員邀請碼");
      if (chip) chip.removeAttribute("title");
      return;
    }
    var typed = adminStorageGet(ADMIN_LAST_ACCOUNT_KEY) || "";
    var brief = displayAdminBrief(admin, typed);
    var staffInvite = String(admin.staff_invite_code || "").trim();
    labelEl.textContent = brief;
    avEl.textContent = initialsForAdminLabel(brief);
    if (inviteEl) inviteEl.textContent = staffInvite || "------";
    if (inviteChip) {
      inviteChip.setAttribute(
        "title",
        staffInvite ? "管理員邀請碼：" + staffInvite + "（點擊複製）" : "管理員邀請碼"
      );
      inviteChip.disabled = !staffInvite;
    }
    if (chip) chip.setAttribute("title", buildAdminChipTitle(admin, brief));
  }

  function clearAdminSessionPayload() {
    adminStorageRemove("eurnyse_admin_token");
    adminStorageRemove(ADMIN_AUTH_KEY);
    adminStorageRemove(ADMIN_PROFILE_KEY);
    adminStorageRemove(ADMIN_LAST_ACCOUNT_KEY);
  }

  function persistAdminSession(token, admin, typedAccount) {
    if (token) adminStorageSet("eurnyse_admin_token", token);
    adminStorageSet(ADMIN_AUTH_KEY, "1");
    if (typedAccount) adminStorageSet(ADMIN_LAST_ACCOUNT_KEY, typedAccount);
    if (admin && typeof admin === "object") {
      adminStorageSet(ADMIN_PROFILE_KEY, JSON.stringify(admin));
    }
    updateTopbarFromProfile(admin && typeof admin === "object" ? admin : readCachedAdminProfile());
    window.dispatchEvent(new CustomEvent("eurnyse-admin-api-token-ready"));
  }

  function adminApiBaseUrl() {
    if (typeof window.__ADMIN_RESOLVE_API_BASE__ === "function") {
      return window.__ADMIN_RESOLVE_API_BASE__();
    }
    var config = window.__ADMIN_RUNTIME_CONFIG__ || {};
    return String(config.apiBaseUrl || "http://127.0.0.1:8000").replace(/\/$/, "");
  }

  /**
   * 將 fetch 網路層錯誤（常見為英文 "Failed to fetch"）轉成可讀說明，供列表／登入等共用。
   */
  function normalizeAdminNetworkFetchError(err) {
    var raw = err && err.message != null ? String(err.message) : "";
    var base = adminApiBaseUrl();
    if (
      raw === "" ||
      /failed to fetch|load failed|networkerror|network request failed|terminated|aborted|err_connection|err_timed_out|fetch failed|ecconnrefused|enotfound/i.test(
        raw
      )
    ) {
      return (
        "無法連線後端 API（目前位址：" +
        base +
        "）。請確認服務已啟動；若 API 與此後台不同網域，請編輯 admin-web/runtime-config.js（或於 index.html 前段內嵌 script）設定 apiBaseUrl，再重新載入本頁。"
      );
    }
    return raw || "網路請求失敗";
  }

  window.normalizeAdminNetworkFetchError = normalizeAdminNetworkFetchError;

  var ADMIN_AUTH_ERROR_MESSAGES = {
    ADMIN_INVALID_PARAMS: "請輸入後台帳號與密碼",
    ADMIN_ACCOUNT_INCORRECT: "後台帳號不存在或輸入錯誤",
    ADMIN_ACCOUNT_TEMP_LOCKED: "後台帳號暫時鎖定，請稍後再試",
    ADMIN_ACCOUNT_LOCKED: "後台帳號已被鎖定",
    ADMIN_PASSWORD_INCORRECT: "後台密碼錯誤",
    ADMIN_PASSWORD_TOO_SHORT: "新密碼至少需要 6 碼",
    ADMIN_PASSWORD_UNCHANGED: "新密碼不可與目前密碼相同",
    ADMIN_PASSWORD_CHANGE_REQUIRED: "請先完成密碼變更"
  };

  function adminAuthErrorMessage(payload) {
    var code = payload && payload.error_code;
    if (code && ADMIN_AUTH_ERROR_MESSAGES[code]) return ADMIN_AUTH_ERROR_MESSAGES[code];
    return "後台 API 登入失敗";
  }

  function requestAdminLogin(account, password) {
    return fetch(adminApiBaseUrl() + "/api/admin/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        account: String(account || "").trim(),
        password: String(password || "")
      })
    })
      .catch(function (err) {
        return Promise.reject(new Error(normalizeAdminNetworkFetchError(err)));
      })
      .then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        return { response: response, payload: payload };
      });
    });
  }

  function adminApiFetchJson(path, options) {
    options = options || {};
    var headers = options.headers || {};
    headers["Content-Type"] = "application/json";
    var token = adminStorageGet("eurnyse_admin_token");
    if (token) headers.Authorization = "Bearer " + token;
    return fetch(adminApiBaseUrl() + path, {
      method: options.method || "GET",
      headers: headers,
      body: options.body ? JSON.stringify(options.body) : undefined
    })
      .catch(function (err) {
        return Promise.reject(new Error(normalizeAdminNetworkFetchError(err)));
      })
      .then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok || Number(payload.code) !== 1) {
          throw new Error(adminAuthErrorMessage(payload));
        }
        return payload.data || {};
      });
    });
  }

  function openAdminPasswordModal(force) {
    var modal = document.getElementById("admin-modal-admin-change-password");
    var callout = document.getElementById("admin-change-password-callout");
    var cancel = document.getElementById("admin-change-password-cancel");
    var msg = document.getElementById("admin-change-password-msg");
    if (modal) {
      modal.dataset.adminForceOpen = force ? "true" : "false";
    }
    if (callout) {
      callout.textContent = force
        ? "首次登入或密碼重置後必須先設定新密碼，完成前無法使用其他後台功能。"
        : "請輸入目前密碼與新密碼。";
    }
    if (cancel) cancel.hidden = !!force;
    if (msg) msg.textContent = "";
    ["admin-change-password-current", "admin-change-password-new", "admin-change-password-confirm"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = "";
    });
    if (window.AdminModals && window.AdminModals.open) {
      window.AdminModals.open("admin-modal-admin-change-password");
    }
  }

  /** @returns {Promise<boolean>} */
  function refreshAdminProfileFromServer() {
    var token = adminStorageGet("eurnyse_admin_token");
    if (!token) return Promise.resolve(false);
    return fetch(adminApiBaseUrl() + "/api/admin/auth/me", {
      method: "GET",
      headers: { Authorization: "Bearer " + token }
    })
      .catch(function (err) {
        return Promise.reject(new Error(normalizeAdminNetworkFetchError(err)));
      })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (payload) {
          if (!response.ok || Number(payload.code) !== 1) return false;
          var adm = payload.data && payload.data.admin;
          if (adm && typeof adm === "object") {
            adminStorageSet(ADMIN_PROFILE_KEY, JSON.stringify(adm));
            updateTopbarFromProfile(adm);
            if (adm.password_must_change) openAdminPasswordModal(true);
          }
          return true;
        });
      })
      .catch(function () {
        return false;
      });
  }

  /**
   * 不在此以固定帳密「偷換」token：避免並發 401 時改登成別人。
   * 無法自動恢復時由上層觸發重新登入介面。
   */
  function tryRecoverAdminSession() {
    return Promise.resolve(false);
  }

  function isRecoverableAdminSessionError(body, response) {
    if (response && response.status === 401) return true;
    var c = body && body.error_code;
    return (
      c === "ADMIN_TOKEN_INVALID" ||
      c === "ADMIN_TOKEN_EXPIRED" ||
      c === "ADMIN_UNAUTHORIZED"
    );
  }

  function forceAdminReloginUi(message) {
    stopDepositAlertMonitor();
    clearAdminSessionPayload();
    updateTopbarFromProfile(null);
    setAuthState(false);
    var errEl = document.getElementById("admin-login-error");
    if (errEl) {
      errEl.textContent =
        message || "後台 API 登入已失效，請於下方重新輸入帳號密碼以繼續。";
    }
    try {
      window.history.replaceState(
        null,
        "",
        window.location.pathname + window.location.search + "#dashboard"
      );
    } catch (e) {}
    setActiveView("dashboard");
    var pwd = document.getElementById("admin-login-password");
    if (pwd) pwd.value = "";
    var acc = document.getElementById("admin-login-account");
    if (acc) {
      acc.focus();
      try {
        acc.select();
      } catch (e2) {}
    }
  }

  window.__ADMIN_SESSION__ = {
    isRecoverableSessionError: isRecoverableAdminSessionError,
    tryRecoverSession: tryRecoverAdminSession,
    forceReloginUi: forceAdminReloginUi,
    getApiToken: function () {
      return adminStorageGet("eurnyse_admin_token") || "";
    },
    getAdminProfile: function () {
      return readCachedAdminProfile();
    }
  };

  function performAdminLogout() {
    stopDepositAlertMonitor();
    var token = adminStorageGet("eurnyse_admin_token");
    if (!token) {
      forceAdminReloginUi("已登出後台。");
      return;
    }
    fetch(adminApiBaseUrl() + "/api/admin/auth/logout", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + token
      },
      body: "{}"
    })
      .catch(function () {})
      .then(function () {
        forceAdminReloginUi("已登出後台。");
      });
  }

  function playDepositAlertBeep(mode) {
    var Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return false;
    var ctx = new Ctx();
    var count = mode === "loop" ? 3 : 1;
    for (var i = 0; i < count; i += 1) {
      var start = ctx.currentTime + i * 0.45;
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(880, start);
      gain.gain.setValueAtTime(0.001, start);
      gain.gain.exponentialRampToValueAtTime(0.16, start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, start + 0.28);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(start);
      osc.stop(start + 0.3);
    }
    return true;
  }

  function playDepositAlertSound(mode) {
    var message = "您有新的充值订单";
    var count = mode === "loop" ? 3 : 1;
    if ("speechSynthesis" in window && "SpeechSynthesisUtterance" in window) {
      try {
        window.speechSynthesis.cancel();
        for (var i = 0; i < count; i += 1) {
          var utterance = new SpeechSynthesisUtterance(message);
          utterance.lang = "zh-CN";
          utterance.rate = 1;
          utterance.pitch = 1;
          utterance.volume = 1;
          window.speechSynthesis.speak(utterance);
        }
        return;
      } catch (e) {}
    }
    playDepositAlertBeep(mode);
  }

  function readDepositAlertSettings() {
    try {
      var raw = adminStorageGet(ADMIN_NOTIFY_KEY);
      var parsed = raw ? JSON.parse(raw) : {};
      return {
        enabled: !!parsed.enabled,
        mode: parsed.mode === "loop" ? "loop" : "once",
        interval: Math.min(120, Math.max(5, Number(parsed.interval || 15)))
      };
    } catch (e) {
      return { enabled: false, mode: "once", interval: 15 };
    }
  }

  function writeDepositAlertSettings(settings) {
    adminStorageSet(ADMIN_NOTIFY_KEY, JSON.stringify(settings));
  }

  var depositAlertTimer = null;
  var depositAlertBaselineId = null;

  function stopDepositAlertMonitor() {
    if (depositAlertTimer) window.clearInterval(depositAlertTimer);
    depositAlertTimer = null;
    depositAlertBaselineId = null;
  }

  function checkDepositAlerts() {
    var settings = readDepositAlertSettings();
    if (!settings.enabled || !adminStorageGet("eurnyse_admin_token")) return;
    adminApiFetchJson("/api/admin/deposit-requests?status=pending&page=1&page_size=1")
      .then(function (data) {
        var item = data.items && data.items[0];
        var latestId = item ? Number(item.id || 0) : 0;
        if (depositAlertBaselineId === null) {
          depositAlertBaselineId = latestId;
          return;
        }
        if (latestId > depositAlertBaselineId) {
          depositAlertBaselineId = latestId;
          playDepositAlertSound(settings.mode);
        }
      })
      .catch(function () {});
  }

  function startDepositAlertMonitor() {
    stopDepositAlertMonitor();
    var settings = readDepositAlertSettings();
    if (!settings.enabled) return;
    checkDepositAlerts();
    depositAlertTimer = window.setInterval(checkDepositAlerts, settings.interval * 1000);
  }

  function initAdminSettingsForms() {
    var passwordForm = document.getElementById("admin-change-password-form");
    if (passwordForm) {
      passwordForm.addEventListener("submit", function (e) {
        e.preventDefault();
        var current = document.getElementById("admin-change-password-current");
        var newer = document.getElementById("admin-change-password-new");
        var confirm = document.getElementById("admin-change-password-confirm");
        var msg = document.getElementById("admin-change-password-msg");
        if (msg) msg.textContent = "";
        if (!newer || !confirm || String(newer.value) !== String(confirm.value)) {
          if (msg) msg.textContent = "兩次輸入的新密碼不一致。";
          return;
        }
        adminApiFetchJson("/api/admin/auth/password", {
          method: "PATCH",
          body: {
            current_password: current ? current.value : "",
            new_password: newer.value
          }
        })
          .then(function () {
            if (msg) msg.textContent = "密碼已更新。";
            return refreshAdminProfileFromServer();
          })
          .then(function () {
            if (window.AdminModals && window.AdminModals.close) {
              var modal = document.getElementById("admin-modal-admin-change-password");
              if (modal) modal.dataset.adminForceOpen = "false";
              window.AdminModals.close(document.getElementById("admin-modal-admin-change-password"));
            }
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "密碼更新失敗。";
          });
      });
    }

    var notifyForm = document.getElementById("admin-deposit-alert-form");
    var enabled = document.getElementById("admin-deposit-alert-enabled");
    var mode = document.getElementById("admin-deposit-alert-mode");
    var interval = document.getElementById("admin-deposit-alert-interval");
    var notifyMsg = document.getElementById("admin-deposit-alert-msg");
    var settings = readDepositAlertSettings();
    if (enabled) enabled.checked = settings.enabled;
    if (mode) mode.value = settings.mode;
    if (interval) interval.value = String(settings.interval);
    if (notifyForm) {
      notifyForm.addEventListener("submit", function (e) {
        e.preventDefault();
        var next = {
          enabled: !!(enabled && enabled.checked),
          mode: mode && mode.value === "loop" ? "loop" : "once",
          interval: Math.min(120, Math.max(5, Number(interval && interval.value ? interval.value : 15)))
        };
        writeDepositAlertSettings(next);
        startDepositAlertMonitor();
        if (notifyMsg) notifyMsg.textContent = "通知設定已儲存。";
      });
    }
    var test = document.getElementById("admin-deposit-alert-test");
    if (test) {
      test.addEventListener("click", function () {
        playDepositAlertSound(mode && mode.value === "loop" ? "loop" : "once");
      });
    }
  }

  function initAdminInviteChip() {
    var chip = document.getElementById("admin-staff-invite-chip");
    if (!chip || chip._adminInviteBound) return;
    chip._adminInviteBound = true;
    chip.addEventListener("click", function () {
      var profile = readCachedAdminProfile() || {};
      var code = String(profile.staff_invite_code || "").trim();
      if (!code) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).catch(function () {});
      }
      if (typeof window.showAdminToast === "function") {
        window.showAdminToast("管理員邀請碼已複製：" + code);
      }
    });
  }

  function initLocalAuthGate() {
    var form = document.getElementById("admin-login-form");
    var account = document.getElementById("admin-login-account");
    var password = document.getElementById("admin-login-password");
    var error = document.getElementById("admin-login-error");
    var token = adminStorageGet("eurnyse_admin_token");
    var isAuthed = Boolean(token);

    if (!token && adminStorageGet(ADMIN_AUTH_KEY) === "1") {
      adminStorageRemove(ADMIN_AUTH_KEY);
    }

    setAuthState(isAuthed);
    if (isAuthed) {
      updateTopbarFromProfile(readCachedAdminProfile());
      refreshAdminProfileFromServer();
    } else {
      updateTopbarFromProfile(null);
    }

    if (!form) return;
    if (!isAuthed && account) account.focus();

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var accountValue = account ? account.value.trim() : "";
      var passwordValue = password ? password.value : "";

      if (!accountValue || !passwordValue) {
        if (error) error.textContent = "請輸入後台帳號與密碼。";
        return;
      }

      if (error) error.textContent = "登入中…";

      requestAdminLogin(accountValue, passwordValue)
        .then(function (ref) {
          var payload = ref.payload;
          var response = ref.response;
          if (!response.ok || Number(payload.code) !== 1) {
            if (error) error.textContent = adminAuthErrorMessage(payload);
            if (password) {
              password.value = "";
              password.focus();
            }
            return;
          }
          var data = payload.data || {};
          var token0 = data.token;
          var admin0 = data.admin;
          if (!token0) {
            if (error) error.textContent = "後端未回傳登入權杖，請稍後再試。";
            return;
          }
          persistAdminSession(token0, admin0, accountValue);
          if (error) error.textContent = "";
          setAuthState(true);
          startDepositAlertMonitor();
          if (admin0 && admin0.password_must_change) openAdminPasswordModal(true);
          refreshAdminProfileFromServer();
        })
        .catch(function () {
          if (error) error.textContent = "無法連線後台 API，請確認網路與服務位址。";
        });
    });
  }

  function onHashChange() {
    setActiveView(normalizeHash());
    closeMobileNav();
    var main = document.querySelector(".admin-main");
    if (main) main.scrollTop = 0;
  }

  function boot() {
    initLocalAuthGate();
    initAdminSettingsForms();
    initAdminInviteChip();
    startDepositAlertMonitor();

    var logoutBtn = document.getElementById("admin-logout-btn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function () {
        performAdminLogout();
      });
    }

    document.querySelectorAll("[data-admin-view]").forEach(function (el) {
      el.addEventListener("click", function (e) {
        var v = el.getAttribute("data-admin-view");
        if (!v) return;
        e.preventDefault();
        window.location.hash = "#" + v;
      });
    });

    var menuBtn = document.getElementById("admin-menu-toggle");
    if (menuBtn) {
      menuBtn.addEventListener("click", toggleMobileNav);
    }
    var cfg = window.__ADMIN_RUNTIME_CONFIG__ || {};
    if (cfg.prototypeUi === true) {
      initFallbackButtonFeedback();
    }

    var backdrop = document.getElementById("admin-nav-backdrop");
    if (backdrop) {
      backdrop.addEventListener("click", closeMobileNav);
    }

    window.addEventListener("hashchange", onHashChange);

    if (!(window.location.hash || "").replace(/^#/, "").trim()) {
      window.history.replaceState(
        null,
        "",
        window.location.pathname + window.location.search + "#dashboard"
      );
    }

    setActiveView(normalizeHash());

    window.showAdminToast = showActionNotice;
  }

  /** 與前台 eurnyse-empty-card 結構對齊的表格空狀態（單列 colspan） */
  function escapeAdminHtml(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderAdminTableEmptyRow(colspan, title, subtitle) {
    var c = Number(colspan) > 0 ? Number(colspan) : 12;
    var t = title != null && String(title).trim() !== "" ? String(title) : "尚無資料";
    var subHtml = "";
    if (subtitle !== false) {
      var sub =
        subtitle != null && String(subtitle).trim() !== ""
          ? String(subtitle).trim()
          : "可調整上方篩選條件，或點「刷新／查詢」重新載入。";
      subHtml = '<p class="admin-empty-state__sub">' + escapeAdminHtml(sub) + "</p>";
    }
    return (
      '<tr class="admin-table-empty-row">' +
      '<td colspan="' +
      escapeAdminHtml(String(c)) +
      '" class="admin-table-empty-cell">' +
      '<div class="admin-empty-state" role="status">' +
      '<div class="admin-empty-state__glow" aria-hidden="true"></div>' +
      '<div class="admin-empty-state__illustration" aria-hidden="true">' +
      '<span class="admin-empty-state__cube admin-empty-state__cube--one"></span>' +
      '<span class="admin-empty-state__cube admin-empty-state__cube--two"></span>' +
      '<span class="admin-empty-state__cube admin-empty-state__cube--three"></span>' +
      '<span class="admin-empty-state__box"></span>' +
      "</div>" +
      '<h3 class="admin-empty-state__title">' +
      escapeAdminHtml(t) +
      "</h3>" +
      subHtml +
      "</div></td></tr>"
    );
  }

  window.renderAdminTableEmptyRow = renderAdminTableEmptyRow;

  var ADMIN_BTN_VARIANTS = {
    primary: true,
    ghost: true,
    success: true,
    danger: true,
    info: true,
    warning: true,
    accent: true
  };

  /**
   * 表格列操作鈕（class 白名單）。opts.attrs 由呼叫端組好並對動態值自行 escape。
   * @param {{ variant?: string, sm?: boolean, attrs?: string, label?: string, title?: string, disabled?: boolean, extraClass?: string }} opts
   */
  function renderAdminBtn(opts) {
    opts = opts || {};
    var v = ADMIN_BTN_VARIANTS[opts.variant] ? opts.variant : "ghost";
    var classes = ["admin-btn", "admin-btn--" + v];
    if (opts.sm) classes.push("admin-btn--sm");
    var xc = opts.extraClass != null ? String(opts.extraClass).trim() : "";
    if (xc) classes.push(xc);
    var attrStr = opts.attrs != null ? String(opts.attrs).trim() : "";
    if (attrStr.length && attrStr.charAt(0) !== " ") attrStr = " " + attrStr;
    var title = opts.title ? ' title="' + escapeAdminHtml(String(opts.title)) + '"' : "";
    var disabled = opts.disabled ? " disabled" : "";
    return (
      '<button type="button" class="' +
      classes.join(" ") +
      '"' +
      attrStr +
      title +
      disabled +
      ">" +
      escapeAdminHtml(opts.label != null ? opts.label : "") +
      "</button>"
    );
  }

  function renderAdminRowActions(innerHtml, opts) {
    opts = opts || {};
    var extra = opts.layout === "stack2" ? " admin-row-actions--stack" : "";
    return '<div class="admin-row-actions' + extra + '">' + (innerHtml || "") + "</div>";
  }

  var ADMIN_FILTER_ACTIONS = {
    reset: { label: "重置", variant: "ghost" },
    query: { label: "查詢", variant: "primary" },
    search: { label: "查詢", variant: "primary" },
    load: { label: "載入", variant: "primary" },
    refresh: { label: "刷新", variant: "ghost" }
  };

  function renderAdminFilterActionToken(token) {
    var parts = String(token || "").split("#");
    var kind = String(parts[0] || "").trim();
    var defaults = ADMIN_FILTER_ACTIONS[kind] || ADMIN_FILTER_ACTIONS.refresh;
    var id = String(parts[1] || "").trim();
    var label = String(parts[2] || "").trim() || defaults.label;
    var variant = String(parts[3] || "").trim() || defaults.variant;
    return renderAdminBtn({
      variant: variant,
      attrs: id ? 'id="' + escapeAdminHtml(id) + '"' : "",
      label: label
    });
  }

  function hydrateAdminFilterActions(root) {
    (root || document).querySelectorAll("[data-admin-filter-actions]").forEach(function (el) {
      var spec = String(el.getAttribute("data-admin-filter-actions") || "");
      el.classList.add("admin-filter-actions");
      el.innerHTML = spec
        .split("|")
        .filter(function (token) {
          return token.trim() !== "";
        })
        .map(renderAdminFilterActionToken)
        .join("");
    });
  }

  window.renderAdminBtn = renderAdminBtn;
  window.renderAdminRowActions = renderAdminRowActions;
  window.hydrateAdminFilterActions = hydrateAdminFilterActions;
  hydrateAdminFilterActions(document);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  } else {
    boot();
  }
})(window, document);
