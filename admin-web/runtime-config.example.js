/**
 * 範本：當後台「系統配置」同步 api_public_base_url 時，可選寫入此檔（見 backend sync_admin_runtime_config_file）。
 * 一般情況請直接使用倉庫內 runtime-config.js（同源自動偵測 + 本機回退）。
 */
window.__ADMIN_RUNTIME_CONFIG__ = {
  apiBaseUrl: "https://your-api.example.com",
  envName: "production",
  prototypeUi: false
};
