/**
 * EURFOREX 後台原型：獨立彈窗開關（對齊 New_C2C modal 行為：遮罩、Esc、單層聚焦）
 */
(function (window, document) {
  var OPEN_CLASS = "admin-modal--open";
  var ADMIN_USERS_PAGE_SIZE_DEFAULT = 25;
  var adminUsersPage = 1;
  var adminUsersPageSize = ADMIN_USERS_PAGE_SIZE_DEFAULT;
  var adminUsersTotal = 0;

  function getModalById(id) {
    return id ? document.getElementById(id) : null;
  }

  function anyModalVisible() {
    return document.querySelector(".admin-modal:not([hidden])") !== null;
  }

  function syncBodyScroll() {
    if (anyModalVisible()) {
      document.body.classList.add(OPEN_CLASS);
    } else {
      document.body.classList.remove(OPEN_CLASS);
    }
  }

  function openModal(id) {
    var el = getModalById(id);
    if (!el || !el.classList.contains("admin-modal")) return;
    document.querySelectorAll(".admin-modal:not([hidden])").forEach(function (m) {
      if (m !== el) closeModal(m);
    });
    el.hidden = false;
    syncBodyScroll();
    var dialog = el.querySelector(".admin-modal__dialog");
    if (dialog) {
      var prev = document.activeElement;
      el._adminModalPrevFocus = prev;
      var focusable = dialog.querySelector(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
      );
      if (focusable) focusable.focus();
    }
  }

  function closeModal(el) {
    if (!el || !el.classList.contains("admin-modal")) return;
    if (el.dataset.adminForceOpen === "true") return;

    if (el.id === "admin-modal-generic-prompt") {
      if (!el._adminGenericHandled) {
        var pr = document.documentElement._adminGenericPromptResolve;
        document.documentElement._adminGenericPromptResolve = null;
        if (pr) pr(null);
      }
      el._adminGenericHandled = false;
    } else if (el.id === "admin-modal-generic-confirm") {
      if (!el._adminGenericHandled) {
        var cr = document.documentElement._adminGenericConfirmResolve;
        document.documentElement._adminGenericConfirmResolve = null;
        if (cr) cr(false);
      }
      el._adminGenericHandled = false;
    } else if (el.id === "admin-modal-generic-alert") {
      if (!el._adminGenericHandled) {
        var ar = document.documentElement._adminGenericAlertResolve;
        document.documentElement._adminGenericAlertResolve = null;
        if (ar) ar();
      }
      el._adminGenericHandled = false;
    }

    if (el.id === "admin-modal-user-detail") {
      resetUserDeleteOverlay();
    }
    if (el.id === "admin-modal-deposit-detail" && typeof window.__adminCloseDepositProofZoom === "function") {
      window.__adminCloseDepositProofZoom();
    }
    if (el.id === "admin-modal-kyc-detail" && typeof window.__adminCloseKycPreviewZoom === "function") {
      window.__adminCloseKycPreviewZoom();
    }
    el.hidden = true;
    if (el._adminModalPrevFocus && typeof el._adminModalPrevFocus.focus === "function") {
      try {
        el._adminModalPrevFocus.focus();
      } catch (e) {}
    }
    syncBodyScroll();
  }

  function closeAllModals() {
    document.querySelectorAll(".admin-modal:not([hidden])").forEach(function (m) {
      closeModal(m);
    });
    syncBodyScroll();
  }

  function adminApiBaseUrl() {
    if (typeof window.__ADMIN_RESOLVE_API_BASE__ === "function") {
      return window.__ADMIN_RESOLVE_API_BASE__();
    }
    var config = window.__ADMIN_RUNTIME_CONFIG__ || {};
    return String(config.apiBaseUrl || "http://127.0.0.1:8000").replace(/\/$/, "");
  }

  var ADMIN_ERROR_MESSAGES = {
    ADMIN_INVALID_PARAMS: "請確認輸入資料是否完整。",
    ADMIN_USER_NOT_FOUND: "找不到使用者。",
    ADMIN_INVALID_ACCOUNT_TYPE: "帳號類型不正確。",
    ADMIN_PASSWORD_TOO_SHORT: "密碼至少需要 6 位字元。",
    ADMIN_INVALID_STATUS: "狀態不正確。",
    ADMIN_EMAIL_INVALID: "Email 格式不正確。",
    ADMIN_EMAIL_EXISTS: "此 Email 已被使用。",
    ADMIN_MOBILE_INVALID: "手機號碼格式不正確。",
    ADMIN_MOBILE_EXISTS: "此手機號碼已被使用。",
    ADMIN_INVITATION_CODE_REQUIRED: "請填寫邀請碼。",
    ADMIN_INVITATION_CODE_EXISTS: "此邀請碼已被使用。",
    ADMIN_INVITED_BY_INVALID: "邀請人用戶 ID 不正確。",
    ADMIN_INVITED_BY_NOT_FOUND: "找不到邀請人用戶。",
    ADMIN_UNAUTHORIZED: "請先登入後台。",
    ADMIN_TOKEN_INVALID: "後台登入狀態已失效，請重新登入。",
    ADMIN_TOKEN_EXPIRED: "後台登入狀態已過期，請重新登入。",
    ADMIN_PASSWORD_CHANGE_REQUIRED: "此後台帳號首次登入需先修改密碼，完成後才能載入使用者資料。",
    ADMIN_FORBIDDEN: "目前帳號沒有此操作權限。"
  };

  function adminErrorMessage(body, fallback) {
    var code = body && body.error_code;
    if (code && ADMIN_ERROR_MESSAGES[code]) return ADMIN_ERROR_MESSAGES[code];
    return fallback || "操作失敗，請稍後再試。";
  }

  function adminSessionHandledError() {
    var e = new Error("SESSION_HANDLED");
    e.adminSessionHandled = true;
    return e;
  }

  function getAdminApiToken() {
    if (window.__ADMIN_SESSION__ && window.__ADMIN_SESSION__.getApiToken) {
      return window.__ADMIN_SESSION__.getApiToken() || "";
    }
    return window.localStorage.getItem("eurforex_admin_token") || "";
  }

  function whenAdminApiJsonRejected(response, body, retryDepth, retryFn, fallbackMessage) {
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

  function adminFetchNetworkCatch(err) {
    var fn = window.normalizeAdminNetworkFetchError;
    var msg =
      typeof fn === "function" ? fn(err) : err && err.message ? String(err.message) : "網路請求失敗";
    return Promise.reject(new Error(msg));
  }

  function statusLabel(status) {
    if (status === "normal") return "正常";
    if (status === "locked") return "鎖定";
    if (status === "disabled") return "禁用";
    if (status === "pending") return "待審核";
    return "其他";
  }

  function invitationStatusLabel(status) {
    var s = String(status || "").toLowerCase();
    if (s === "active") return "邀請碼啟用";
    if (s === "inactive" || s === "disabled") return "邀請碼停用";
    if (s === "expired") return "已過期";
    if (s === "revoked") return "已作廢";
    return "其他";
  }

  function statusBadgeClass(status) {
    if (status === "normal") return "admin-badge--success";
    if (status === "locked") return "admin-badge--warning";
    if (status === "disabled") return "admin-badge--danger";
    return "admin-badge--neutral";
  }

  function money(value) {
    var n = Number(value || 0);
    return Number.isFinite(n) ? n.toLocaleString("zh-TW", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "0.00";
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function sequenceNo(index) {
    return String(index + 1).padStart(2, "0");
  }

  function userOrdinalId(user) {
    var id = Number(user && user.id != null ? user.id : 0);
    if (!Number.isFinite(id) || id <= 0) return "—";
    return String(Math.floor(id)).padStart(2, "0");
  }

  function displayUserCode(user) {
    var username = String(user && user.username ? user.username : "").trim();
    if (/^EN\d{6}$/i.test(username)) return username.toUpperCase();
    if (/^EU\d{6}$/i.test(username)) return "EN" + username.slice(2);
    var id = Number(user && user.id ? user.id : 0);
    if (Number.isFinite(id) && id > 0) return "EN" + String(id).padStart(6, "0");
    return username || "-";
  }


  function renderUplineLabel(label) {
    var raw = String(label || "").trim();
    if (!raw || raw === "-") return '<span class="admin-muted">-</span>';
    var parts = raw.split(/[﹡*]/);
    var role = (parts[0] || "").trim();
    var account = parts.slice(1).join("*").trim();
    if (!role || !account) return escapeHtml(raw);
    return (
      '<div class="admin-upline-cell">' +
      '<div class="admin-upline-cell__role">' +
      escapeHtml(role) +
      '</div><div class="admin-upline-cell__account">' +
      escapeHtml(account) +
      "</div></div>"
    );
  }

  function userLevelLabel(item) {
    var raw =
      item && item.tier && item.tier.level != null
        ? item.tier.level
        : item && item.tier_level != null
          ? item.tier_level
          : item && item.level != null
            ? item.level
            : 1;
    var text = String(raw == null || raw === "" ? "1" : raw).trim();
    return /^lv/i.test(text) ? text.toUpperCase() : "LV" + text;
  }

  function adminGroupLabel(item) {
    var group = item && item.admin_group ? item.admin_group : null;
    var name = String((group && group.name) || (item && item.admin_group_name) || "").trim();
    var code = String((group && group.code) || (item && item.admin_group_code) || "").trim();
    return name || code || "—";
  }

  function renderUsersTable(items, total) {
    var tbody = document.getElementById("admin-users-tbody");
    var count = document.getElementById("admin-users-count");
    if (!tbody) return;
    adminUsersTotal = total == null ? items.length : Number(total) || 0;
    if (count) count.textContent = "真實資料 " + adminUsersTotal + " 筆";
    renderAdminUsersPager();
    if (!items.length) {
      tbody.innerHTML =
        typeof window.renderAdminTableEmptyRow === "function"
          ? window.renderAdminTableEmptyRow(13, "目前沒有符合條件的使用者。", false)
          : '<tr><td colspan="13" class="admin-muted">目前沒有符合條件的使用者。</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(function (item, index) {
      var account = item.email || item.mobile || item.mobile_e164 || "-";
      var userCode = displayUserCode(item);
      var accountTypeLabel = item.account_type === "mobile" ? "手機" : "郵箱";
      return [
        '<tr data-admin-user-id="' + escapeHtml(item.id) + '">',
        '<td><input type="checkbox" class="admin-users-cb" data-admin-user-select="' + escapeHtml(item.id) + '" aria-label="選取使用者 ' + escapeHtml(userOrdinalId(item)) + '"/></td>',
        '<td class="mono">' + escapeHtml(userOrdinalId(item)) + '</td>',
        '<td><div class="mono">' + escapeHtml(userCode) + '</div><div class="admin-cell-sub">' + escapeHtml(account) + '</div></td>',
        '<td>' + escapeHtml(adminGroupLabel(item)) + '</td>',
        '<td>' + renderUplineLabel(item.upline_label) + '</td>',
        '<td>' + accountTypeLabel + '</td>',
        '<td class="mono">' + escapeHtml(userLevelLabel(item)) + '</td>',
        '<td><span class="admin-badge ' + statusBadgeClass(item.status) + '">' + statusLabel(item.status) + '</span></td>',
        '<td><div class="mono">' + escapeHtml(item.invitation_code || "-") + '</div><div class="admin-cell-sub">' + invitationStatusLabel(item.invitation_status) + '</div></td>',
        '<td class="mono">' + escapeHtml(item.invite_count || 0) + '</td>',
        '<td class="mono">' + money(item.usdt_balance) + '</td>',
        '<td class="mono">' + money(item.eur_balance) + '</td>',
        '<td>' +
          (typeof window.renderAdminRowActions === "function" && typeof window.renderAdminBtn === "function"
            ? window.renderAdminRowActions(
                '<div class="admin-row-actions__row">' +
                  window.renderAdminBtn({
                    variant: "info",
                    sm: true,
                    attrs: 'data-admin-modal-open="admin-modal-user-detail"',
                    label: "詳情"
                  }) +
                  window.renderAdminBtn({
                    variant: "ghost",
                    sm: true,
                    attrs: 'data-admin-modal-open="admin-modal-user-detail"',
                    label: "編輯"
                  }) +
                  "</div>" +
                  '<div class="admin-row-actions__row">' +
                  window.renderAdminBtn({
                    variant: "accent",
                    sm: true,
                    attrs:
                      'data-admin-modal-open="admin-modal-wallet-adjust" data-admin-wallet-adjust="' +
                      escapeHtml(item.id) +
                      '"',
                    label: "餘額"
                  }) +
                  window.renderAdminBtn({
                    variant: "accent",
                    sm: true,
                    attrs: 'data-admin-tier-edit="' + escapeHtml(item.id) + '"',
                    label: "等級"
                  }) +
                  "</div>",
                { layout: "stack2" }
              )
            : '<div class="admin-row-actions admin-row-actions--stack"><div class="admin-row-actions__row"><button type="button" class="admin-btn admin-btn--info admin-btn--sm" data-admin-modal-open="admin-modal-user-detail">詳情</button><button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-admin-modal-open="admin-modal-user-detail">編輯</button></div><div class="admin-row-actions__row"><button type="button" class="admin-btn admin-btn--accent admin-btn--sm" data-admin-modal-open="admin-modal-wallet-adjust" data-admin-wallet-adjust="' + escapeHtml(item.id) + '">餘額</button><button type="button" class="admin-btn admin-btn--accent admin-btn--sm" data-admin-tier-edit="' + escapeHtml(item.id) + '">等級</button></div></div>') +
          "</td>",
        '</tr>'
      ].join("");
    }).join("");
    renderAdminUsersPager();
  }

  function renderAdminUsersPager() {
    var info = document.getElementById("admin-users-page-info");
    var display = document.getElementById("admin-users-page-display");
    var prev = document.getElementById("admin-users-prev");
    var next = document.getElementById("admin-users-next");
    var totalPages = Math.max(1, Math.ceil((adminUsersTotal || 0) / (adminUsersPageSize || ADMIN_USERS_PAGE_SIZE_DEFAULT)));
    if (info) {
      info.textContent = "第 " + adminUsersPage + " / " + totalPages + " 頁 · 每頁 " + adminUsersPageSize + " 筆";
    }
    if (display) {
      display.textContent = "第 " + adminUsersPage + " / " + totalPages + " 頁";
    }
    if (prev) prev.disabled = adminUsersPage <= 1;
    if (next) next.disabled = adminUsersPage >= totalPages || adminUsersTotal === 0;
  }

  function loadAdminUsers(retryDepth) {
    if (retryDepth == null) retryDepth = 0;
    var tbody = document.getElementById("admin-users-tbody");
    if (!tbody) return Promise.resolve();
    var token = getAdminApiToken();
    if (!token) {
      var count = document.getElementById("admin-users-count");
      if (count) count.textContent = "等待後台 API 登入...";
      return Promise.resolve();
    }
    var keyword = document.getElementById("admin-users-keyword");
    var status = document.getElementById("admin-users-status");
    var registeredFrom = document.getElementById("admin-users-registered-from");
    var query = new URLSearchParams({
      page: String(adminUsersPage),
      page_size: String(adminUsersPageSize)
    });
    if (keyword && keyword.value.trim()) query.set("keyword", keyword.value.trim());
    if (status && status.value) query.set("status", status.value);
    if (registeredFrom && registeredFrom.value) query.set("registered_from", registeredFrom.value);
    tbody.innerHTML = '<tr><td colspan="13" class="admin-muted">正在載入真實使用者資料...</td></tr>';
    token = getAdminApiToken();
    return fetch(adminApiBaseUrl() + "/api/admin/users?" + query.toString(), {
      headers: { Authorization: "Bearer " + token }
    })
      .catch(adminFetchNetworkCatch)
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (body) {
          if (!response.ok || Number(body.code) !== 1) {
            return whenAdminApiJsonRejected(
              response,
              body,
              retryDepth,
              loadAdminUsers,
              "載入使用者列表失敗。"
            );
          }
          return body.data || {};
        });
      })
      .then(function (data) {
        var pagination = data.pagination || {};
        if (pagination.page != null) adminUsersPage = Math.max(1, Number(pagination.page) || adminUsersPage);
        if (pagination.page_size != null) adminUsersPageSize = Math.max(1, Number(pagination.page_size) || adminUsersPageSize);
        renderUsersTable(data.items || [], pagination.total);
      })
      .catch(function (error) {
        if (error && error.adminSessionHandled) return;
        tbody.innerHTML =
          '<tr><td colspan="13" class="admin-muted">' +
          escapeHtml(error.message || "載入使用者列表失敗。") +
          "</td></tr>";
      });
  }

  function selectedAdminUserIds() {
    var ids = [];
    document.querySelectorAll(".admin-users-cb:checked").forEach(function (cb) {
      var id = parseInt(cb.getAttribute("data-admin-user-select") || "0", 10);
      if (Number.isFinite(id) && id > 0) ids.push(id);
    });
    return ids;
  }

  function runAdminUsersBatch(action, payload) {
    var token = getAdminApiToken();
    if (!token) {
      showAdminToastIfAvailable("未取得後台 API token，請重新登入。", "error");
      return Promise.reject(new Error("No token"));
    }
    return fetch(adminApiBaseUrl() + "/api/admin/users/batch", {
      method: "PATCH",
      headers: { Authorization: "Bearer " + token, "Content-Type": "application/json" },
      body: JSON.stringify(Object.assign({ action: action }, payload || {}))
    })
      .catch(adminFetchNetworkCatch)
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (body) {
          if (!response.ok || Number(body.code) !== 1) {
            throw new Error(body.msg || body.error_code || "批量操作失敗");
          }
          return body.data || {};
        });
      });
  }

  function syncAdminUsersSelectAll() {
    var all = document.getElementById("admin-users-select-all");
    var boxes = Array.prototype.slice.call(document.querySelectorAll(".admin-users-cb"));
    if (!all) return;
    var selected = boxes.filter(function (cb) { return cb.checked; }).length;
    all.checked = boxes.length > 0 && selected === boxes.length;
    all.indeterminate = selected > 0 && selected < boxes.length;
  }

  function handleAdminUserBatchStatus(nextStatus, label) {
    var ids = selectedAdminUserIds();
    if (!ids.length) {
      showAdminToastIfAvailable("請先勾選要操作的使用者。", "error");
      return;
    }
    showAdminConfirmDialog({
      title: label,
      message: "確定對 " + ids.length + " 位使用者執行「" + label + "」？",
      confirmLabel: "確定"
    }).then(function (ok) {
      if (!ok) return;
      return runAdminUsersBatch("status", {
        user_ids: ids,
        status: nextStatus,
        reason: label
      })
        .then(function () {
          showAdminToastIfAvailable(label + "完成。");
          return loadAdminUsers(0);
        })
        .catch(function (error) {
          showAdminToastIfAvailable(error.message || "批量操作失敗", "error");
        });
    });
  }

  function handleAdminUserBatchRisk() {
    var ids = selectedAdminUserIds();
    if (!ids.length) {
      showAdminToastIfAvailable("請先勾選要標記風控的使用者。", "error");
      return;
    }
    showAdminPromptDialog({
      title: "批次標記風控",
      message: "請輸入風控狀態：warning（預警）或 blocked（阻擋）。輸入 normal 可解除風控。",
      fieldLabel: "風控狀態",
      defaultValue: "warning",
      required: true,
      requiredMessage: "請輸入風控狀態。"
    }).then(function (riskStatus) {
      if (riskStatus == null) return;
      riskStatus = String(riskStatus).trim().toLowerCase();
      if (["normal", "warning", "blocked"].indexOf(riskStatus) === -1) {
        showAdminToastIfAvailable("風控狀態只能是 normal / warning / blocked。", "error");
        return;
      }
      return showAdminPromptDialog({
        title: "風控備註",
        message: "請輸入風控備註（會寫入等級風控訊息，可留空）。",
        fieldLabel: "備註",
        defaultValue: riskStatus === "normal" ? "解除批次風控" : "批次風控標記",
        required: false
      }).then(function (note) {
        return runAdminUsersBatch("risk", {
          user_ids: ids,
          risk_status: riskStatus,
          violation_message: note || "",
          reason: note || "批次風控標記"
        })
          .then(function () {
            showAdminToastIfAvailable("批次風控已更新。");
            return loadAdminUsers(0);
          })
          .catch(function (error) {
            showAdminToastIfAvailable(error.message || "批量操作失敗", "error");
          });
      });
    });
  }

  function initAdminUsersTable() {
    var search = document.getElementById("admin-users-search");
    var refresh = document.getElementById("admin-users-refresh");
    var reset = document.getElementById("admin-users-reset");
    if (search) search.addEventListener("click", function () {
      adminUsersPage = 1;
      loadAdminUsers();
    });
    if (refresh) refresh.addEventListener("click", loadAdminUsers);
    if (reset) {
      reset.addEventListener("click", function () {
        var keyword = document.getElementById("admin-users-keyword");
        var status = document.getElementById("admin-users-status");
        var registeredFrom = document.getElementById("admin-users-registered-from");
        if (keyword) keyword.value = "";
        if (status) status.value = "";
        if (registeredFrom) registeredFrom.value = "";
        adminUsersPage = 1;
        loadAdminUsers();
      });
    }
    var selectAll = document.getElementById("admin-users-select-all");
    if (selectAll) {
      selectAll.addEventListener("change", function () {
        document.querySelectorAll(".admin-users-cb").forEach(function (cb) {
          cb.checked = selectAll.checked;
        });
        syncAdminUsersSelectAll();
      });
    }
    var tbody = document.getElementById("admin-users-tbody");
    if (tbody) {
      tbody.addEventListener("change", function (e) {
        if (e.target && e.target.classList && e.target.classList.contains("admin-users-cb")) {
          syncAdminUsersSelectAll();
        }
      });
    }
    var batchLock = document.getElementById("admin-users-batch-lock");
    var batchUnlock = document.getElementById("admin-users-batch-unlock");
    var batchRisk = document.getElementById("admin-users-batch-risk");
    if (batchLock) batchLock.addEventListener("click", function () { handleAdminUserBatchStatus("locked", "批量鎖定"); });
    if (batchUnlock) batchUnlock.addEventListener("click", function () { handleAdminUserBatchStatus("normal", "批量解禁"); });
    if (batchRisk) batchRisk.addEventListener("click", handleAdminUserBatchRisk);
    var prev = document.getElementById("admin-users-prev");
    var next = document.getElementById("admin-users-next");
    var size = document.getElementById("admin-users-page-size");
    if (prev) {
      prev.addEventListener("click", function () {
        if (adminUsersPage <= 1) return;
        adminUsersPage -= 1;
        loadAdminUsers();
      });
    }
    if (next) {
      next.addEventListener("click", function () {
        var totalPages = Math.max(1, Math.ceil((adminUsersTotal || 0) / (adminUsersPageSize || ADMIN_USERS_PAGE_SIZE_DEFAULT)));
        if (adminUsersPage >= totalPages) return;
        adminUsersPage += 1;
        loadAdminUsers();
      });
    }
    if (size) {
      size.addEventListener("click", function () {
        showAdminPromptDialog({
          title: "每頁筆數",
          message: "請輸入每頁顯示筆數：10 / 25 / 50 / 100",
          defaultValue: String(adminUsersPageSize || ADMIN_USERS_PAGE_SIZE_DEFAULT)
        }).then(function (value) {
          if (value == null) return;
          var n = Number(String(value).trim());
          if (!Number.isFinite(n) || n <= 0) {
            showAdminToastIfAvailable("請輸入有效筆數", "error");
            return;
          }
          adminUsersPageSize = Math.max(1, Math.min(200, Math.floor(n)));
          adminUsersPage = 1;
          loadAdminUsers();
        });
      });
    }
    window.addEventListener("eurforex-admin-api-token-ready", loadAdminUsers);
    window.addEventListener("hashchange", function () {
      if ((window.location.hash || "") === "#users") loadAdminUsers();
    });
    if ((window.location.hash || "") === "#users") loadAdminUsers();
  }

  function writeCreateUserMessage(message, type) {
    var el = document.getElementById("admin-create-user-message");
    if (!el) return;
    el.classList.toggle("admin-create-user-message--error", type === "error");
    el.classList.toggle("admin-create-user-message--success", type === "success");
    el.textContent = message || "";
  }

  function normalizeCreateUserPayload(form) {
    var data = new FormData(form);
    var email = String(data.get("email") || "").trim();
    var mobile = String(data.get("mobile") || "").trim();
    var password = String(data.get("password") || "").trim();
    var selectedAccountType = String(data.get("account_type") || "email").trim();
    var accountType = selectedAccountType === "mobile" && mobile ? "mobile" : "email";
    if (!email && mobile) accountType = "mobile";

    if (!email && !mobile) {
      throw new Error("請至少填寫郵箱或手機其中一項。");
    }
    if (password.length < 6) {
      throw new Error("登入密碼至少需要 6 位字元。");
    }

    return {
      account_type: accountType,
      email: email,
      country_code: String(data.get("country_code") || "").trim(),
      mobile: mobile,
      password: password,
      lang: String(data.get("lang") || "zh-Hant").trim(),
      status: String(data.get("status") || "normal").trim(),
      invited_by_user_id: String(data.get("invited_by_user_id") || "").trim(),
      reason: String(data.get("reason") || "").trim()
    };
  }

  function initCreateUserForm() {
    var form = document.getElementById("admin-create-user-form");
    if (!form) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var submit = form.querySelector('button[type="submit"]');
      var payload;

      try {
        payload = normalizeCreateUserPayload(form);
      } catch (error) {
        writeCreateUserMessage(error.message, "error");
        return;
      }

      var token = getAdminApiToken();
      if (!token) {
        writeCreateUserMessage("已通過前端驗證。若要寫入資料庫，請先取得後台 API token。", "success");
        return;
      }

      if (submit) submit.disabled = true;
      writeCreateUserMessage("正在新增使用者...", "");

      function postCreateUser(retryDepth) {
        if (retryDepth == null) retryDepth = 0;
        var t = getAdminApiToken();
        return fetch(adminApiBaseUrl() + "/api/admin/users", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + t
          },
          body: JSON.stringify(payload)
        })
          .catch(adminFetchNetworkCatch)
          .then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (body) {
            if (!response.ok || Number(body.code) !== 1) {
              return whenAdminApiJsonRejected(
                response,
                body,
                retryDepth,
                postCreateUser,
                "新增使用者失敗。"
              );
            }
            return body;
          });
        });
      }

      postCreateUser(0)
        .then(function (body) {
          var user = body.data && body.data.user ? body.data.user : {};
          writeCreateUserMessage("新增成功：" + (user.username || ("ID " + user.id)), "success");
          form.reset();
        })
        .catch(function (error) {
          if (error && error.adminSessionHandled) return;
          writeCreateUserMessage(error.message || "新增使用者失敗。", "error");
        })
        .finally(function () {
          if (submit) submit.disabled = false;
        });
    });
  }

  function setUserProfileEditable(form, editable) {
    form.querySelectorAll("input, select, textarea").forEach(function (field) {
      if (field.name === "id") return;
      field.disabled = !editable;
    });
    var save = form.querySelector('button[type="submit"]');
    if (save) save.disabled = !editable;
  }

  function setFieldValue(form, name, value) {
    var field = form.querySelector('[name="' + name + '"]');
    if (!field) return;
    var normalized = value == null ? "" : String(value);
    if (field.tagName === "SELECT" && normalized && !Array.from(field.options).some(function (opt) { return opt.value === normalized; })) {
      var option = document.createElement("option");
      option.value = normalized;
      option.textContent = normalized;
      field.appendChild(option);
    }
    field.value = normalized;
  }

  function rowUserIdFromButton(btn) {
    var row = btn && btn.closest("tr");
    if (!row) return "";
    var raw = row.getAttribute("data-admin-user-id") || "";
    var parsed = parseInt(raw, 10);
    return Number.isFinite(parsed) && parsed > 0 ? String(parsed) : "";
  }

  function populateUserProfileForm(form, user) {
    form.setAttribute("data-user-id", String(user.id || 1));
    setFieldValue(form, "id", user.id || "");
    setFieldValue(form, "username", displayUserCode(user));
    setFieldValue(form, "account_type", user.account_type || "email");
    setFieldValue(form, "email", user.email || "");
    setFieldValue(form, "country_code", user.country_code || "");
    setFieldValue(form, "mobile", user.mobile || "");
    setFieldValue(form, "lang", user.lang || "hkg");
    setFieldValue(form, "invitation_code", user.invitation_code || "");
    var cs =
      user.credit_score != null && user.credit_score !== ""
        ? user.credit_score
        : user.tier && user.tier.score != null
          ? user.tier.score
          : 30;
    setFieldValue(form, "credit_score", cs);
    setFieldValue(form, "status", user.status || "normal");
    setFieldValue(form, "password", "");
    setFieldValue(form, "reason", "");
    setUserProfileEditable(form, false);
  }

  function loadUserProfileForButton(btn) {
    var form = document.getElementById("admin-user-profile-form");
    if (!form) return;
    var userId = rowUserIdFromButton(btn);
    if (!userId) {
      writeUserProfileMessage("無法判定目標用戶，請從使用者列表的操作按鈕開啟詳情。", "error");
      return;
    }
    form.setAttribute("data-user-id", userId);
    setFieldValue(form, "id", userId);
    writeUserProfileMessage("正在載入用戶資料...", "");

    var token = getAdminApiToken();
    if (!token) {
      writeUserProfileMessage("未取得後台 API token，暫以目前畫面資料顯示。", "error");
      setUserProfileEditable(form, false);
      return;
    }

    function fetchProfile(retryDepth) {
      if (retryDepth == null) retryDepth = 0;
      var t = getAdminApiToken();
      return fetch(adminApiBaseUrl() + "/api/admin/users/" + encodeURIComponent(userId), {
        headers: { Authorization: "Bearer " + t }
      })
        .catch(adminFetchNetworkCatch)
        .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (body) {
          if (!response.ok || Number(body.code) !== 1) {
            return whenAdminApiJsonRejected(
              response,
              body,
              retryDepth,
              fetchProfile,
              "載入用戶資料失敗。"
            );
          }
          return body.data && body.data.user ? body.data.user : null;
        });
      });
    }

    fetchProfile(0)
      .then(function (user) {
        if (!user) throw new Error("載入用戶資料失敗。");
        populateUserProfileForm(form, user);
        writeUserProfileMessage("用戶資料已載入，點擊「編輯」後可修改。", "success");
      })
      .catch(function (error) {
        if (error && error.adminSessionHandled) return;
        writeUserProfileMessage(error.message || "載入用戶資料失敗。", "error");
        setUserProfileEditable(form, false);
      });
  }

  function writeUserProfileMessage(message, type) {
    var el = document.getElementById("admin-user-profile-message");
    if (!el) return;
    el.classList.toggle("admin-create-user-message--error", type === "error");
    el.classList.toggle("admin-create-user-message--success", type === "success");
    el.textContent = message || "";
  }

  function showAdminToastIfAvailable(message) {
    if (typeof window.showAdminToast === "function") {
      window.showAdminToast(message);
    }
  }

  function resetUserDeleteOverlay() {
    var ov = document.getElementById("admin-user-delete-overlay");
    var s1 = document.getElementById("admin-user-delete-step1");
    var s2 = document.getElementById("admin-user-delete-step2");
    var inp = document.getElementById("admin-user-delete-confirm-input");
    var reason = document.getElementById("admin-user-delete-reason");
    var err = document.getElementById("admin-user-delete-err");
    var fin = document.querySelector("[data-admin-user-delete-final]");
    if (s1) s1.hidden = false;
    if (s2) s2.hidden = true;
    if (inp) inp.value = "";
    if (reason) reason.value = "";
    if (err) err.textContent = "";
    if (fin) fin.disabled = true;
    if (ov) {
      ov.hidden = true;
      ov.setAttribute("aria-hidden", "true");
    }
  }

  function openUserDeleteOverlay(meta) {
    var ov = document.getElementById("admin-user-delete-overlay");
    var lead = document.getElementById("admin-user-delete-lead");
    var tgt = document.getElementById("admin-user-delete-username-target");
    if (!ov) return;
    var uid = String(meta.id != null ? meta.id : "").trim();
    var un = String(meta.username || "").trim();
    ov.dataset.userId = uid;
    ov.dataset.expectedUsername = un;
    if (lead) {
      lead.innerHTML =
        "即將刪除 <strong class=\"mono\">" +
        escapeHtml(un) +
        "</strong>（資料序號 <span class=\"mono\">" +
        escapeHtml(uid) +
        "</span>）。請完成兩步確認。";
    }
    if (tgt) tgt.textContent = un || "—";
    var s1 = document.getElementById("admin-user-delete-step1");
    var s2 = document.getElementById("admin-user-delete-step2");
    if (s1) s1.hidden = false;
    if (s2) s2.hidden = true;
    var inp = document.getElementById("admin-user-delete-confirm-input");
    var reason = document.getElementById("admin-user-delete-reason");
    var err = document.getElementById("admin-user-delete-err");
    var fin = document.querySelector("[data-admin-user-delete-final]");
    if (inp) inp.value = "";
    if (reason) reason.value = "";
    if (err) err.textContent = "";
    if (fin) fin.disabled = true;
    ov.hidden = false;
    ov.setAttribute("aria-hidden", "false");
    var nextBtn = document.querySelector("[data-admin-user-delete-next]");
    if (nextBtn && typeof nextBtn.focus === "function") nextBtn.focus();
  }

  function deleteUserById(userId, reason, retryDepth) {
    if (retryDepth == null) retryDepth = 0;
    var t = getAdminApiToken();
    return fetch(adminApiBaseUrl() + "/api/admin/users/" + encodeURIComponent(userId), {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + t
      },
      body: JSON.stringify({ reason: reason != null ? String(reason) : "" })
    })
      .catch(adminFetchNetworkCatch)
      .then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok || Number(body.code) !== 1) {
          return whenAdminApiJsonRejected(
            response,
            body,
            retryDepth,
            function (d) {
              return deleteUserById(userId, reason, d);
            },
            "刪除用戶失敗。"
          );
        }
        return body;
      });
    });
  }

  function initUserDeleteFlow() {
    var inp = document.getElementById("admin-user-delete-confirm-input");
    if (inp && !inp._adminDeleteInputBound) {
      inp._adminDeleteInputBound = true;
      inp.addEventListener("input", function () {
        var ov = document.getElementById("admin-user-delete-overlay");
        var fin = document.querySelector("[data-admin-user-delete-final]");
        var exp = ov ? String(ov.dataset.expectedUsername || "").trim() : "";
        if (fin) fin.disabled = String(inp.value || "").trim() !== exp;
      });
    }

    document.querySelectorAll("[data-admin-user-delete-cancel]").forEach(function (btn) {
      if (btn._adminDeleteCancelBound) return;
      btn._adminDeleteCancelBound = true;
      btn.addEventListener("click", function () {
        resetUserDeleteOverlay();
      });
    });

    var nextBtn = document.querySelector("[data-admin-user-delete-next]");
    if (nextBtn && !nextBtn._adminDeleteNextBound) {
      nextBtn._adminDeleteNextBound = true;
      nextBtn.addEventListener("click", function () {
        var s1 = document.getElementById("admin-user-delete-step1");
        var s2 = document.getElementById("admin-user-delete-step2");
        var err = document.getElementById("admin-user-delete-err");
        if (err) err.textContent = "";
        if (s1) s1.hidden = true;
        if (s2) s2.hidden = false;
        var inp2 = document.getElementById("admin-user-delete-confirm-input");
        if (inp2) {
          inp2.value = "";
          inp2.focus();
        }
        var fin = document.querySelector("[data-admin-user-delete-final]");
        if (fin) fin.disabled = true;
      });
    }

    var backBtn = document.querySelector("[data-admin-user-delete-back]");
    if (backBtn && !backBtn._adminDeleteBackBound) {
      backBtn._adminDeleteBackBound = true;
      backBtn.addEventListener("click", function () {
        var s1 = document.getElementById("admin-user-delete-step1");
        var s2 = document.getElementById("admin-user-delete-step2");
        var err = document.getElementById("admin-user-delete-err");
        if (err) err.textContent = "";
        if (s2) s2.hidden = true;
        if (s1) s1.hidden = false;
      });
    }

    var finalBtn = document.querySelector("[data-admin-user-delete-final]");
    if (finalBtn && !finalBtn._adminDeleteFinalBound) {
      finalBtn._adminDeleteFinalBound = true;
      finalBtn.addEventListener("click", function () {
        var ov = document.getElementById("admin-user-delete-overlay");
        var err = document.getElementById("admin-user-delete-err");
        var uid = ov ? String(ov.dataset.userId || "").trim() : "";
        var exp = ov ? String(ov.dataset.expectedUsername || "").trim() : "";
        var inp3 = document.getElementById("admin-user-delete-confirm-input");
        var reasonEl = document.getElementById("admin-user-delete-reason");
        if (!uid) {
          if (err) err.textContent = "缺少用戶 ID。";
          return;
        }
        if (String(inp3 && inp3.value ? inp3.value : "").trim() !== exp) {
          if (err) err.textContent = "用戶名不一致，請重新輸入。";
          return;
        }
        var token = getAdminApiToken();
        if (!token) {
          if (err) err.textContent = "請先登入後台 API。";
          return;
        }
        if (err) err.textContent = "";
        finalBtn.disabled = true;
        var reason = reasonEl && reasonEl.value ? String(reasonEl.value).trim() : "";
        deleteUserById(uid, reason, 0)
          .then(function () {
            resetUserDeleteOverlay();
            var modal = document.getElementById("admin-modal-user-detail");
            if (modal && window.AdminModals && typeof window.AdminModals.close === "function") {
              window.AdminModals.close(modal);
            }
            showAdminToastIfAvailable("用戶已永久刪除。");
            loadAdminUsers(0);
          })
          .catch(function (error) {
            if (error && error.adminSessionHandled) return;
            if (err) err.textContent = error.message || "刪除失敗。";
          })
          .finally(function () {
            finalBtn.disabled = false;
          });
      });
    }
  }

  function userProfilePayload(form) {
    var data = new FormData(form);
    var payload = {
      account_type: String(data.get("account_type") || "email").trim(),
      username: String(data.get("username") || "").trim(),
      email: String(data.get("email") || "").trim(),
      country_code: String(data.get("country_code") || "").trim(),
      mobile: String(data.get("mobile") || "").trim(),
      lang: String(data.get("lang") || "zh-Hant").trim(),
      invitation_code: String(data.get("invitation_code") || "").trim(),
      reason: String(data.get("reason") || "").trim()
    };
    var creditRaw = String(data.get("credit_score") != null ? data.get("credit_score") : "").trim();
    if (creditRaw === "" || !/^-?\d+$/.test(creditRaw)) {
      throw new Error("請填寫有效的信用分（整數）。");
    }
    var creditNum = parseInt(creditRaw, 10);
    if (creditNum < 0 || creditNum > 9999999) {
      throw new Error("信用分須介於 0 與 9,999,999 之間。");
    }
    payload.credit_score = creditNum;
    var password = String(data.get("password") || "").trim();
    if (password) payload.password = password;

    if (!payload.username) throw new Error("請填寫用戶名。");
    if (!payload.invitation_code) throw new Error("請填寫邀請碼。");
    if (payload.account_type === "email" && !payload.email) throw new Error("郵箱帳號需要填寫 Email。");
    if (payload.account_type === "mobile" && !payload.mobile) throw new Error("手機帳號需要填寫手機。");
    if (password && password.length < 6) throw new Error("新密碼至少需要 6 位字元。");
    return payload;
  }

  function initUserProfileForm() {
    var form = document.getElementById("admin-user-profile-form");
    if (!form) return;

    setUserProfileEditable(form, false);

    document.querySelectorAll("[data-admin-user-edit]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        setUserProfileEditable(form, true);
        writeUserProfileMessage("正在編輯模式，修改後請點擊「儲存用戶」。", "");
        showAdminToastIfAvailable("已進入編輯模式，完成後請點「儲存用戶」。");
        var first = form.querySelector('input[name="username"]');
        if (first) first.focus();
      });
    });

    document.querySelectorAll("[data-admin-user-log]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        writeUserProfileMessage("請切換至「認證事件」或系統審計列表，依用戶 ID / 帳號查詢實際操作紀錄。", "");
      });
    });

    document.querySelectorAll("[data-admin-user-delete]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var fd = new FormData(form);
        var uid = String(form.getAttribute("data-user-id") || fd.get("id") || "").trim();
        var un = String(fd.get("username") || "").trim();
        if (!uid) {
          writeUserProfileMessage("無法刪除：缺少用戶 ID。", "error");
          return;
        }
        if (!un) {
          writeUserProfileMessage("無法刪除：請先載入完整用戶資料。", "error");
          return;
        }
        openUserDeleteOverlay({ id: uid, username: un });
      });
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var submit = form.querySelector('button[type="submit"]');
      var payload;
      try {
        payload = userProfilePayload(form);
      } catch (error) {
        writeUserProfileMessage(error.message, "error");
        return;
      }

      var token = getAdminApiToken();
      var userId = form.getAttribute("data-user-id") || String(new FormData(form).get("id") || "").trim();
      if (!token) {
        writeUserProfileMessage("已通過前端驗證。若要寫入資料庫，請先取得後台 API token。", "success");
        return;
      }

      if (submit) submit.disabled = true;
      writeUserProfileMessage("正在儲存用戶資料...", "");

      function patchProfile(retryDepth) {
        if (retryDepth == null) retryDepth = 0;
        var t = getAdminApiToken();
        return fetch(adminApiBaseUrl() + "/api/admin/users/" + encodeURIComponent(userId), {
          method: "PATCH",
          headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + t
          },
          body: JSON.stringify(payload)
        })
          .catch(adminFetchNetworkCatch)
          .then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (body) {
            if (!response.ok || Number(body.code) !== 1) {
              return whenAdminApiJsonRejected(
                response,
                body,
                retryDepth,
                patchProfile,
                "儲存用戶失敗。"
              );
            }
            return body;
          });
        });
      }

      patchProfile(0)
        .then(function () {
          writeUserProfileMessage("用戶資料已儲存。", "success");
          showAdminToastIfAvailable("用戶資料已儲存。");
          setUserProfileEditable(form, false);
        })
        .catch(function (error) {
          if (error && error.adminSessionHandled) return;
          writeUserProfileMessage(error.message || "儲存用戶失敗。", "error");
        })
        .finally(function () {
          if (submit) submit.disabled = false;
        });
    });
  }

  function initGenericAdminDialogs() {
    if (document.documentElement._adminGenericDialogsInit) return;
    document.documentElement._adminGenericDialogsInit = true;

    var pModal = document.getElementById("admin-modal-generic-prompt");
    var ta = document.getElementById("admin-generic-prompt-textarea");
    var pErr = document.getElementById("admin-generic-prompt-error");
    var pOk = document.getElementById("admin-generic-prompt-ok");
    var pCancel = document.getElementById("admin-generic-prompt-cancel");
    var pDismiss = pModal ? pModal.querySelector("[data-admin-generic-prompt-dismiss]") : null;
    [pCancel, pDismiss].forEach(function (btn) {
      if (!btn || !pModal) return;
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        closeModal(pModal);
      });
    });
    if (pOk && pModal) {
      pOk.addEventListener("click", function (e) {
        e.preventDefault();
        var opts = pModal._adminGenericPromptOpts || {};
        var t = ta ? String(ta.value || "").trim() : "";
        if (opts.required && !t) {
          if (pErr) {
            pErr.style.display = "";
            pErr.textContent = opts.requiredMessage || "請填寫內容。";
          }
          return;
        }
        if (pErr) {
          pErr.style.display = "none";
          pErr.textContent = "";
        }
        pModal._adminGenericHandled = true;
        var fn = document.documentElement._adminGenericPromptResolve;
        document.documentElement._adminGenericPromptResolve = null;
        if (fn) fn(t);
        closeModal(pModal);
      });
    }

    var cModal = document.getElementById("admin-modal-generic-confirm");
    var cOk = document.getElementById("admin-generic-confirm-ok");
    var cCancel = document.getElementById("admin-generic-confirm-cancel");
    var cDismiss = cModal ? cModal.querySelector("[data-admin-generic-confirm-dismiss]") : null;
    [cCancel, cDismiss].forEach(function (btn) {
      if (!btn || !cModal) return;
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        closeModal(cModal);
      });
    });
    if (cOk && cModal) {
      cOk.addEventListener("click", function (e) {
        e.preventDefault();
        cModal._adminGenericHandled = true;
        var fn = document.documentElement._adminGenericConfirmResolve;
        document.documentElement._adminGenericConfirmResolve = null;
        if (fn) fn(true);
        closeModal(cModal);
      });
    }

    var aModal = document.getElementById("admin-modal-generic-alert");
    var aOk = document.getElementById("admin-generic-alert-ok");
    var aDismiss = aModal ? aModal.querySelector("[data-admin-generic-alert-dismiss]") : null;
    if (aOk && aModal) {
      aOk.addEventListener("click", function (e) {
        e.preventDefault();
        aModal._adminGenericHandled = true;
        var fn = document.documentElement._adminGenericAlertResolve;
        document.documentElement._adminGenericAlertResolve = null;
        if (fn) fn();
        closeModal(aModal);
      });
    }
    if (aDismiss && aModal) {
      aDismiss.addEventListener("click", function (e) {
        e.preventDefault();
        closeModal(aModal);
      });
    }
  }

  function showAdminPromptDialog(opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var modal = document.getElementById("admin-modal-generic-prompt");
      if (!modal) {
        resolve(null);
        return;
      }
      initGenericAdminDialogs();
      modal._adminGenericPromptOpts = opts;
      modal._adminGenericHandled = false;
      document.documentElement._adminGenericPromptResolve = resolve;
      var titleEl = document.getElementById("admin-generic-prompt-title");
      var msgEl = document.getElementById("admin-generic-prompt-message");
      var labEl = document.getElementById("admin-generic-prompt-label");
      var ta = document.getElementById("admin-generic-prompt-textarea");
      var errEl = document.getElementById("admin-generic-prompt-error");
      if (titleEl) titleEl.textContent = opts.title || "請輸入";
      if (msgEl) msgEl.textContent = opts.message || "";
      if (labEl) labEl.textContent = opts.fieldLabel || "內容";
      if (ta) {
        ta.value = opts.defaultValue != null ? String(opts.defaultValue) : "";
        ta.placeholder = opts.placeholder || "";
      }
      if (errEl) {
        errEl.style.display = "none";
        errEl.textContent = "";
      }
      openModal("admin-modal-generic-prompt");
      window.setTimeout(function () {
        if (ta) ta.focus();
      }, 80);
    });
  }

  function showAdminConfirmDialog(opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var modal = document.getElementById("admin-modal-generic-confirm");
      if (!modal) {
        resolve(false);
        return;
      }
      initGenericAdminDialogs();
      modal._adminGenericHandled = false;
      document.documentElement._adminGenericConfirmResolve = resolve;
      var titleEl = document.getElementById("admin-generic-confirm-title");
      var msgEl = document.getElementById("admin-generic-confirm-message");
      var okBtn = document.getElementById("admin-generic-confirm-ok");
      if (titleEl) titleEl.textContent = opts.title || "確認";
      if (msgEl) msgEl.textContent = opts.message || "";
      if (okBtn) {
        okBtn.textContent = opts.confirmLabel || "確定";
        okBtn.className =
          "admin-btn " + (opts.danger ? "admin-btn--danger" : "admin-btn--primary");
      }
      openModal("admin-modal-generic-confirm");
    });
  }

  function showAdminAlertDialog(opts) {
    opts = typeof opts === "string" ? { message: opts } : opts || {};
    return new Promise(function (resolve) {
      var modal = document.getElementById("admin-modal-generic-alert");
      if (!modal) {
        resolve();
        return;
      }
      initGenericAdminDialogs();
      modal._adminGenericHandled = false;
      document.documentElement._adminGenericAlertResolve = resolve;
      var titleEl = document.getElementById("admin-generic-alert-title");
      var msgEl = document.getElementById("admin-generic-alert-message");
      var okBtn = document.getElementById("admin-generic-alert-ok");
      if (titleEl) titleEl.textContent = opts.title || "提示";
      if (msgEl) msgEl.textContent = opts.message || "";
      if (okBtn) okBtn.textContent = opts.confirmLabel || "知道了";
      openModal("admin-modal-generic-alert");
    });
  }

  window.showAdminPromptDialog = showAdminPromptDialog;
  window.showAdminConfirmDialog = showAdminConfirmDialog;
  window.showAdminAlertDialog = showAdminAlertDialog;

  document.addEventListener(
    "click",
    function (e) {
      var openBtn = e.target.closest("[data-admin-modal-open]");
      if (openBtn) {
        var oid = openBtn.getAttribute("data-admin-modal-open");
        if (oid) {
          e.preventDefault();
          openModal(oid);
          if (oid === "admin-modal-user-detail") {
            loadUserProfileForButton(openBtn);
          }
        }
        return;
      }
      var closeBtn = e.target.closest("[data-admin-modal-close]");
      if (closeBtn) {
        var modal = closeBtn.closest(".admin-modal");
        if (modal) {
          e.preventDefault();
          closeModal(modal);
        }
      }
    },
    true
  );

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    var zoom = document.getElementById("view-deposit-detail-img-zoom");
    if (zoom && !zoom.hidden && typeof window.__adminCloseDepositProofZoom === "function") {
      e.preventDefault();
      window.__adminCloseDepositProofZoom();
      return;
    }
    var vis = document.querySelector(".admin-modal:not([hidden])");
    if (vis) {
      e.preventDefault();
      closeModal(vis);
    }
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initGenericAdminDialogs();
      initCreateUserForm();
      initUserDeleteFlow();
      initUserProfileForm();
      initAdminUsersTable();
    }, { once: true });
  } else {
    initGenericAdminDialogs();
    initCreateUserForm();
    initUserDeleteFlow();
    initUserProfileForm();
    initAdminUsersTable();
  }

  window.AdminModals = {
    open: openModal,
    close: closeModal,
    closeAll: closeAllModals,
    showPrompt: showAdminPromptDialog,
    showConfirm: showAdminConfirmDialog,
    showAlert: showAdminAlertDialog,
    reloadUsersTable: function () {
      return loadAdminUsers(0);
    }
  };
})(window, document);
