(function (window, document) {
  function adminClientApiBaseUrl() {
    if (typeof window.__ADMIN_RESOLVE_API_BASE__ === "function") {
      return window.__ADMIN_RESOLVE_API_BASE__();
    }
    var config = window.__ADMIN_RUNTIME_CONFIG__ || {};
    return String(config.apiBaseUrl || "http://127.0.0.1:8000").replace(/\/$/, "");
  }
  var tokenKey = "eurforex_admin_token";
  var token = window.localStorage.getItem(tokenKey) || "";
  var debugEnabled = /(?:\?|&)debugApi=1(?:&|$)/.test(window.location.search);
  var ADMIN_ERROR_MESSAGES = {
    ADMIN_PASSWORD_CHANGE_REQUIRED: "此後台帳號首次登入需先修改密碼，完成後才能使用此功能。"
  };

  function getDeviceTimezone() {
    try {
      if (window.Intl && window.Intl.DateTimeFormat) {
        var timezone = window.Intl.DateTimeFormat().resolvedOptions().timeZone;
        return typeof timezone === "string" ? timezone.trim() : "";
      }
    } catch (e) {}
    return "";
  }

  var endpointByView = {
    dashboard: "/api/admin/auth/events?page=1&page_size=5",
    users: "/api/admin/users?page=1&page_size=10",
    wallets: "/api/admin/users/1/wallets/ledger?page=1&page_size=10",
    tiers: "/api/admin/tier-templates",
    deposits: "/api/admin/deposit-requests?page=1&page_size=10",
    "deposit-addresses": "/api/admin/deposit-addresses?page=1&page_size=10",
    withdrawals: "/api/admin/withdrawal-requests?page=1&page_size=10",
    "eur-swap-withdraw-config": "/api/admin/withdrawal-settings/eur-swap",
    orders: "/api/admin/orders?page=1&page_size=10",
    "financial-products": "/api/admin/financial-products?page=1&page_size=10",
    "financial-orders": "/api/admin/financial-subscriptions?page=1&page_size=10",
    listings: "/api/admin/listings?page=1&page_size=10",
    "trade-feed": "/api/admin/trade-feed-events?page=1&page_size=10",
    "home-content": "/api/admin/home-content",
    kyc: "/api/admin/kyc-applications?page=1&page_size=10",
    "payout-methods": "/api/admin/payout-methods?page=1&page_size=10",
    "auth-events": "/api/admin/auth/events?page=1&page_size=10",
    "system-config": "/api/admin/system/configs",
    "admin-users": "/api/admin/admin-users?page=1&page_size=10"
  };

  function request(path, options, retryDepth) {
    if (retryDepth == null) retryDepth = 0;
    options = options || {};
    token = window.localStorage.getItem(tokenKey) || "";
    var headers = { "Content-Type": "application/json" };
    var timezone = getDeviceTimezone();
    if (timezone) headers["X-Timezone"] = timezone;
    if (token && !options.skipAuth) headers.Authorization = "Bearer " + token;
    return fetch(adminClientApiBaseUrl() + path, {
      method: options.method || "GET",
      headers: headers,
      body: options.body ? JSON.stringify(options.body) : undefined
    })
      .catch(function (err) {
        var fn = window.normalizeAdminNetworkFetchError;
        var msg =
          typeof fn === "function"
            ? fn(err)
            : err && err.message
              ? String(err.message)
              : "網路請求失敗";
        return Promise.reject(new Error(msg));
      })
      .then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok || Number(payload.code) !== 1) {
          var sess = window.__ADMIN_SESSION__;
          if (
            !options.skipAuth &&
            retryDepth < 1 &&
            sess &&
            typeof sess.isRecoverableSessionError === "function" &&
            sess.isRecoverableSessionError(payload, response)
          ) {
            return sess.tryRecoverSession().then(function (ok) {
              if (ok) return request(path, options, retryDepth + 1);
              sess.forceReloginUi("後台 API 身分已失效，請重新登入除錯面板的帳號。");
              throw new Error("SESSION_EXPIRED");
            });
          }
          throw new Error(ADMIN_ERROR_MESSAGES[payload.error_code] || payload.msg || "Request failed");
        }
        return payload.data || {};
      });
    });
  }

  function activeView() {
    var hash = (window.location.hash || "#dashboard").replace(/^#/, "");
    return endpointByView[hash] ? hash : "dashboard";
  }

  function renderShell() {
    var panel = document.createElement("section");
    panel.className = "admin-api-panel";
    panel.innerHTML = [
      '<div class="admin-api-panel__head">',
      '<strong>API 連線</strong>',
      '<span id="admin-api-env"></span>',
      "</div>",
      '<div class="admin-api-login">',
      '<input id="admin-api-account" class="admin-input" placeholder="admin account">',
      '<input id="admin-api-password" class="admin-input" type="password" placeholder="password">',
      '<button id="admin-api-login" class="admin-btn admin-btn--primary" type="button">登入 API</button>',
      '<button id="admin-api-refresh" class="admin-btn admin-btn--ghost" type="button">刷新目前模組</button>',
      "</div>",
      '<pre id="admin-api-output" class="admin-api-output">尚未載入 API。</pre>'
    ].join("");
    document.body.appendChild(panel);
    var cfg = window.__ADMIN_RUNTIME_CONFIG__ || {};
    document.getElementById("admin-api-env").textContent = cfg.envName || "local";
    document.getElementById("admin-api-login").addEventListener("click", login);
    document.getElementById("admin-api-refresh").addEventListener("click", loadActiveView);
  }

  function write(value, isError) {
    var output = document.getElementById("admin-api-output");
    if (!output) return;
    output.classList.toggle("admin-api-output--error", Boolean(isError));
    output.textContent = typeof value === "string" ? value : JSON.stringify(value, null, 2);
  }

  function login() {
    var account = document.getElementById("admin-api-account").value.trim();
    var password = document.getElementById("admin-api-password").value;
    write("登入中...");
    request(
      "/api/admin/auth/login",
      {
        method: "POST",
        body: { account: account, password: password },
        skipAuth: true
      }
    ).then(function (data) {
      token = data.token || "";
      window.localStorage.setItem(tokenKey, token);
      write({ login: "ok", admin: data.admin || null });
      loadActiveView();
    }).catch(function (error) {
      write(error.message, true);
    });
  }

  function loadActiveView() {
    var view = activeView();
    var endpoint = endpointByView[view];
    if (!token) {
      write("請先登入 API。");
      return;
    }
    write("載入 " + view + " -> " + endpoint + " ...");
    request(endpoint).then(function (data) {
      write({ view: view, endpoint: endpoint, data: data });
    }).catch(function (error) {
      write({ view: view, endpoint: endpoint, error: error.message }, true);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (!debugEnabled) return;
    renderShell();
    if (token) loadActiveView();
  });
  window.addEventListener("hashchange", function () {
    if (!debugEnabled) return;
    if (token) loadActiveView();
  });
})(window, document);
