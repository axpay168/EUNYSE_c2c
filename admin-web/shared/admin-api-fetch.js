/**
 * 後台 API JSON 請求（Bearer + session 自動續期一次）
 * 依賴：admin-console.js 先載入以提供 window.__ADMIN_SESSION__
 */
(function (window, document) {
  function adminApiBaseUrl() {
    if (typeof window.__ADMIN_RESOLVE_API_BASE__ === "function") {
      return window.__ADMIN_RESOLVE_API_BASE__();
    }
    var config = window.__ADMIN_RUNTIME_CONFIG__ || {};
    return String(config.apiBaseUrl || "http://127.0.0.1:8000").replace(/\/$/, "");
  }

  function getToken() {
    if (window.__ADMIN_SESSION__ && window.__ADMIN_SESSION__.getApiToken) {
      return window.__ADMIN_SESSION__.getApiToken() || "";
    }
    try {
      return window.localStorage.getItem("eurforex_admin_token") || "";
    } catch (e) {
      return "";
    }
  }

  function getDeviceTimezone() {
    try {
      if (window.Intl && window.Intl.DateTimeFormat) {
        var timezone = window.Intl.DateTimeFormat().resolvedOptions().timeZone;
        return typeof timezone === "string" ? timezone.trim() : "";
      }
    } catch (e) {}
    return "";
  }

  var ADMIN_ERROR_MESSAGES = {
    ADMIN_INVALID_PARAMS: "請確認輸入資料是否完整。",
    ADMIN_UNAUTHORIZED: "請先登入後台。",
    ADMIN_TOKEN_INVALID: "後台登入狀態已失效，請重新登入。",
    ADMIN_TOKEN_EXPIRED: "後台登入狀態已過期，請重新登入。",
    ADMIN_FORBIDDEN: "目前帳號沒有此操作權限。",
    ADMIN_PASSWORD_CHANGE_REQUIRED: "此後台帳號首次登入需先修改密碼，完成後才能使用此功能。",
    ADMIN_TRADE_FEED_RANDOM_SOURCE_EMPTY:
      "未取得金額 ≥ 100 USDT 的鏈上轉帳（已嘗試近 24 小時內資料），請稍後再試。"
  };

  function adminErrorMessage(body, fallback) {
    var code = body && body.error_code;
    if (code && ADMIN_ERROR_MESSAGES[code]) return ADMIN_ERROR_MESSAGES[code];
    if (body && body.msg) return String(body.msg);
    return fallback || "操作失敗，請稍後再試。";
  }

  function adminSessionHandledError() {
    var e = new Error("SESSION_HANDLED");
    e.adminSessionHandled = true;
    return e;
  }

  function whenRejected(response, body, retryDepth, retryFn, fallbackMessage) {
    var sess = window.__ADMIN_SESSION__;
    if (
      retryDepth < 1 &&
      sess &&
      typeof sess.isRecoverableSessionError === "function" &&
      sess.isRecoverableSessionError(body, response)
    ) {
      return sess.tryRecoverSession().then(function (ok) {
        if (ok) return retryFn(retryDepth + 1);
        sess.forceReloginUi("後台 API 連線身分已失效，請重新輸入下方帳號與密碼。");
        return Promise.reject(adminSessionHandledError());
      });
    }
    return Promise.reject(new Error(adminErrorMessage(body, fallbackMessage)));
  }

  /**
   * @param {string} path 以 / 開頭的 API path（含 query）
   * @param {{method?:string,body?:*,headers?:object,skipAuth?:boolean,fallbackMessage?:string}} options
   * @param {number} [retryDepth]
   * @returns {Promise<*>} 解析後的 data（payload.data）
   */
  function requestJson(path, options, retryDepth) {
    if (retryDepth == null) retryDepth = 0;
    options = options || {};
    var method = options.method || "GET";
    var headers = Object.assign({}, options.headers || {});
    var timezone = getDeviceTimezone();
    if (timezone && !headers["X-Timezone"] && !headers["x-timezone"]) headers["X-Timezone"] = timezone;
    var token = getToken();
    if (!options.skipAuth && token) headers.Authorization = "Bearer " + token;
    if (options.body != null && !headers["Content-Type"]) {
      headers["Content-Type"] = "application/json";
    }
    var body =
      options.body === undefined || options.body === null
        ? undefined
        : typeof options.body === "string"
          ? options.body
          : JSON.stringify(options.body);
    var url = path.indexOf("http") === 0 ? path : adminApiBaseUrl() + path;
    return fetch(url, { method: method, headers: headers, body: body })
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
      return response
        .json()
        .catch(function () {
          return {};
        })
        .then(function (payload) {
          if (!response.ok || Number(payload.code) !== 1) {
            return whenRejected(
              response,
              payload,
              retryDepth,
              function (next) {
                return requestJson(path, options, next);
              },
              options.fallbackMessage || "請求失敗"
            );
          }
          return payload.data;
        });
    });
  }

  window.__ADMIN_API_FETCH__ = {
    requestJson: requestJson,
    baseUrl: adminApiBaseUrl,
    errorMessage: adminErrorMessage
  };
})(window, document);
