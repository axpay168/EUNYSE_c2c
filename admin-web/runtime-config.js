/**
 * 後台執行時設定（請保留於 index.html 最前載入）
 *
 * 部署防呆：
 * - 未手動指定 apiBaseUrl 時，部署環境預設使用 location.origin（前後端同源不必再改檔）。
 * - 本機開發若後台跑在 8095，API 預設指向 http://127.0.0.1:8000。
 * - 僅在無法取得網頁 origin（例如 file:// 本機開檔）時才回退 http://127.0.0.1:8000。
 * - API 與靜態後台不同網域時，請在「本檔」或更前面的內嵌 script 設定：
 *   window.__ADMIN_RUNTIME_CONFIG__ = { apiBaseUrl: "https://你的-api-網域" };
 *   （同源自動偵測只適用於「同一個 origin 提供後台 HTML 與 /api」的部署。）
 */
(function (window) {
  var preset = window.__ADMIN_RUNTIME_CONFIG__ || {};

  function sameOriginBase() {
    try {
      var o = window.location && window.location.origin;
      if (o && /^https?:\/\//i.test(o)) return o.replace(/\/$/, "");
    } catch (e) {}
    return "";
  }

  function defaultApiBase() {
    var host = String((window.location && window.location.hostname) || "");
    var port = String((window.location && window.location.port) || "");
    if (/^(localhost|127\.0\.0\.1)$/i.test(host) && port !== "8000") {
      return "http://127.0.0.1:8000";
    }
    return sameOriginBase() || "http://127.0.0.1:8000";
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
    var auto = defaultApiBase();
    if (auto) return auto;
    return "http://127.0.0.1:8000";
  };

  var defaultBase = defaultApiBase();
  var host = String((window.location && window.location.hostname) || "");
  var defaultEnv = /localhost|127\.0\.0\.1/i.test(host) ? "local" : "deployment";

  window.__ADMIN_RUNTIME_CONFIG__ = Object.assign(
    {
      apiBaseUrl: defaultBase,
      envName: defaultEnv,
      prototypeUi: false
    },
    preset
  );

  if (preset.apiBaseUrl == null || String(preset.apiBaseUrl).trim() === "") {
    window.__ADMIN_RUNTIME_CONFIG__.apiBaseUrl = defaultBase;
  }
})(window);
