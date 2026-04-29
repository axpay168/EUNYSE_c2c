/**
 * 依 hash 載入各後台視圖資料（讀取 API），並處理後台管理員列表/新增等。
 * 依賴：admin-api-fetch.js、admin-console.js、admin-modals.js（users）
 */
(function (window, document) {
  function api() {
    return window.__ADMIN_API_FETCH__;
  }

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function adminUserDisplayCode(user, fallbackId) {
    var direct = String(user && user.display_code ? user.display_code : "").trim();
    if (direct) return direct.toUpperCase();
    var username = String(user && user.username ? user.username : "").trim();
    if (/^EN\d{6}$/i.test(username)) return username.toUpperCase();
    if (/^EU\d{6}$/i.test(username)) return "EN" + username.slice(2);
    var invite = String(user && user.invitation_code ? user.invitation_code : "").trim();
    if (/^EN\d{6}$/i.test(invite)) return invite.toUpperCase();
    if (/^EU\d{6}$/i.test(invite)) return "EN" + invite.slice(2);
    if (/^\d{6}$/.test(invite)) return "EN" + invite;
    var id = Number(user && user.id != null ? user.id : fallbackId);
    if (Number.isFinite(id) && id > 0) return "EN" + String(Math.floor(id)).padStart(6, "0");
    return username || "—";
  }

  function sequenceNo(index) {
    return String((Number(index) || 0) + 1).padStart(2, "0");
  }

  function userOrdinalId(user, fallbackId) {
    var id = Number(user && user.id != null ? user.id : fallbackId);
    if (!Number.isFinite(id) || id <= 0) return "—";
    return String(Math.floor(id)).padStart(2, "0");
  }

  function adminUserAccountText(user, displayCode) {
    var email = String(user && user.email ? user.email : "").trim();
    var mobile = String(user && (user.mobile_e164 || user.mobile) ? user.mobile_e164 || user.mobile : "").trim();
    var username = String(user && user.username ? user.username : "").trim();
    if (email) return email;
    if (mobile) return mobile;
    if (username && username !== displayCode) return username;
    return "—";
  }

  function renderAdminUserSummaryCell(user, fallbackId) {
    var code = adminUserDisplayCode(user, fallbackId);
    return (
      '<td><div class="mono">' +
      esc(code) +
      '</div><div class="admin-cell-sub">' +
      esc(adminUserAccountText(user, code)) +
      "</div></td>"
    );
  }

  function adminUserFromPrefixedFields(row, prefix) {
    prefix = String(prefix || "");
    return {
      id: row && row[prefix + "user_id"],
      username: row && row[prefix + "username"],
      email: row && row[prefix + "email"],
      mobile_e164: row && row[prefix + "mobile_e164"],
      invitation_code: row && row[prefix + "invitation_code"],
      display_code: row && row[prefix + "display_code"]
    };
  }

  function renderAdminUserSummaryHtml(user, fallbackId) {
    return renderAdminUserSummaryCell(user, fallbackId).replace(/^<td>|<\/td>$/g, "");
  }

  function setModalBodyHtml(id, html) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = html;
  }

  function detailRows(rows) {
    return (
      '<dl class="admin-modal__detail-list admin-modal__detail-list--compact">' +
      rows
        .map(function (row) {
          var value = row && row.raw ? row.value : esc(row && row.value != null && row.value !== "" ? row.value : "—");
          return "<div><dt>" + esc(row.label || "") + "</dt><dd" + (row.mono ? ' class="mono"' : "") + ">" + value + "</dd></div>";
        })
        .join("") +
      "</dl>"
    );
  }

  function showModalById(id) {
    var modal = document.getElementById(id);
    if (modal && window.AdminModals && typeof window.AdminModals.open === "function") window.AdminModals.open(modal);
  }

  function renderJsonBlock(value) {
    if (value == null || value === "") return "—";
    var text = typeof value === "string" ? value : JSON.stringify(value, null, 2);
    return '<pre class="admin-modal__json mono">' + esc(text) + "</pre>";
  }

  function adminNotifySuccess(msg) {
    if (typeof window.showAdminToast === "function") window.showAdminToast(msg);
    else if (typeof window.showAdminAlertDialog === "function") {
      window.showAdminAlertDialog({ title: "完成", message: msg });
    } else window.alert(msg);
  }

  function adminNotifyError(msg) {
    if (typeof window.showAdminAlertDialog === "function") {
      window.showAdminAlertDialog({ title: "提示", message: msg });
    } else window.alert(msg);
  }

  function adminConfirm(opts) {
    if (typeof window.showAdminConfirmDialog === "function") {
      return window.showAdminConfirmDialog(opts || {});
    }
    return Promise.resolve(window.confirm(String(opts && opts.message ? opts.message : "")));
  }

  function adminPrompt(opts) {
    if (typeof window.showAdminPromptDialog === "function") {
      return window.showAdminPromptDialog(opts || {});
    }
    var o = opts || {};
    return Promise.resolve(
      window.prompt(String(o.message || ""), o.defaultValue != null ? String(o.defaultValue) : "")
    );
  }

  function adminResetPager(tbodyId) {
    adminPagerGet(tbodyId).page = 1;
  }

  function buildTableSkeletonRow(cols) {
    var cells = [];
    for (var i = 0; i < cols; i++) {
      var tone = i % 3 === 0 ? "lg" : i % 3 === 1 ? "md" : "sm";
      cells.push('<span class="admin-skeleton-line admin-skeleton-line--' + tone + '"></span>');
    }
    return '<div class="admin-skeleton-row" style="--admin-skeleton-cols:' + cols + '">' + cells.join("") + "</div>";
  }

  function setTbodyLoadingSkeleton(tbodyId, colspan, rows) {
    var tb = document.getElementById(tbodyId);
    if (!tb) return null;
    var cols = Math.max(1, Number(colspan) || 12);
    var rowCount = Math.max(2, Number(rows) || 4);
    var html = "";
    for (var i = 0; i < rowCount; i++) html += buildTableSkeletonRow(cols);
    tb.innerHTML = '<tr><td colspan="' + cols + '" class="admin-skeleton-table">' + html + "</td></tr>";
    return tb;
  }

  function setTbodyLoading(tbodyId, colspan, useSkeleton) {
    if (typeof useSkeleton === "undefined") useSkeleton = true;
    if (useSkeleton) return setTbodyLoadingSkeleton(tbodyId, colspan, 4);
    var tb = document.getElementById(tbodyId);
    if (!tb) return null;
    tb.innerHTML =
      '<tr><td colspan="' +
      (colspan || 12) +
      '" class="admin-muted">載入中…</td></tr>';
    return tb;
  }

  var ADMIN_PAGE_SIZE_DEFAULT = 25;
  var ADMIN_PAGE_SIZE_OPTIONS = [10, 25, 50, 100];
  var adminPagerState = {};

  function adminPagerKey(tbodyId) {
    return String(tbodyId || "default");
  }

  function adminPagerGet(tbodyId) {
    var key = adminPagerKey(tbodyId);
    if (!adminPagerState[key]) {
      adminPagerState[key] = { page: 1, pageSize: ADMIN_PAGE_SIZE_DEFAULT, total: 0, hasMore: false };
    }
    return adminPagerState[key];
  }

  function adminPagerBuildPath(path, state) {
    var raw = String(path || "");
    var hash = "";
    var hashPos = raw.indexOf("#");
    if (hashPos >= 0) {
      hash = raw.slice(hashPos);
      raw = raw.slice(0, hashPos);
    }
    var parts = raw.split("?");
    var base = parts[0] || "";
    var q = new URLSearchParams(parts[1] || "");
    q.set("page", String(state.page || 1));
    q.set("page_size", String(state.pageSize || ADMIN_PAGE_SIZE_DEFAULT));
    return base + "?" + q.toString() + hash;
  }

  function adminPagerSignature(path) {
    var raw = String(path || "");
    var hashPos = raw.indexOf("#");
    if (hashPos >= 0) raw = raw.slice(0, hashPos);
    var parts = raw.split("?");
    var q = new URLSearchParams(parts[1] || "");
    q.delete("page");
    q.delete("page_size");
    var pairs = [];
    q.forEach(function (value, key) {
      pairs.push(key + "=" + value);
    });
    pairs.sort();
    return (parts[0] || "") + "?" + pairs.join("&");
  }

  function adminPagerRequestPath(tbodyId, path) {
    var state = adminPagerGet(tbodyId);
    var signature = adminPagerSignature(path);
    if (state.signature && state.signature !== signature) state.page = 1;
    state.signature = signature;
    return adminPagerBuildPath(path, state);
  }

  function adminPagerRender(tbodyId, pagination, reloadFn) {
    var tb = document.getElementById(tbodyId);
    if (!tb) return;
    var wrap = tb.closest ? tb.closest(".admin-table-wrap") : null;
    if (!wrap) return;
    var state = adminPagerGet(tbodyId);
    var pag = pagination || {};
    if (pag.page != null) state.page = Math.max(1, Number(pag.page) || state.page || 1);
    if (pag.page_size != null) state.pageSize = Math.max(1, Number(pag.page_size) || state.pageSize || ADMIN_PAGE_SIZE_DEFAULT);
    state.total = pag.total != null ? Math.max(0, Number(pag.total) || 0) : state.total || 0;
    state.hasMore = pag.has_more != null ? !!pag.has_more : state.total ? state.page * state.pageSize < state.total : false;
    var totalText = state.total ? "共 " + state.total + " 筆" : state.hasMore ? "尚有更多資料" : "目前頁面";
    var id = "admin-pager-" + adminPagerKey(tbodyId).replace(/[^a-z0-9_-]/gi, "-");
    var old = document.getElementById(id);
    var html =
      '<div class="admin-table-pager" id="' +
      esc(id) +
      '" data-admin-pager-for="' +
      esc(tbodyId) +
      '">' +
      '<div class="admin-table-pager__meta">第 <span class="mono">' +
      esc(state.page) +
      '</span> 頁 · 每頁 <span class="mono">' +
      esc(state.pageSize) +
      "</span> 筆 · " +
      esc(totalText) +
      "</div>" +
      '<div class="admin-table-pager__actions">' +
      '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-admin-pager-prev ' +
      (state.page <= 1 ? "disabled" : "") +
      ">上一頁</button>" +
      '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-admin-pager-size>每頁筆數</button>' +
      '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-admin-pager-next ' +
      (!state.hasMore ? "disabled" : "") +
      ">下一頁</button>" +
      "</div></div>";
    if (old) old.outerHTML = html;
    else wrap.insertAdjacentHTML("afterend", html);
    var pager = document.getElementById(id);
    if (!pager || pager.dataset.bound === "1") return;
    pager.dataset.bound = "1";
    var prev = pager.querySelector("[data-admin-pager-prev]");
    var next = pager.querySelector("[data-admin-pager-next]");
    var size = pager.querySelector("[data-admin-pager-size]");
    if (prev) prev.addEventListener("click", function () {
      if (state.page <= 1) return;
      state.page -= 1;
      reloadFn();
    });
    if (next) next.addEventListener("click", function () {
      if (!state.hasMore) return;
      state.page += 1;
      reloadFn();
    });
    if (size) size.addEventListener("click", function () {
      var current = String(state.pageSize || ADMIN_PAGE_SIZE_DEFAULT);
      var msg = "請輸入每頁顯示筆數：" + ADMIN_PAGE_SIZE_OPTIONS.join(" / ");
      adminPrompt({ title: "每頁筆數", message: msg, defaultValue: current }).then(function (value) {
        if (value == null) return;
        var n = Number(String(value).trim());
        if (!Number.isFinite(n) || n <= 0) return adminNotifyError("請輸入有效筆數");
        n = Math.max(1, Math.min(200, Math.floor(n)));
        state.pageSize = n;
        state.page = 1;
        reloadFn();
      });
    });
  }

  function setActionLoading(btn, loading, loadingText) {
    if (!btn) return;
    if (loading) {
      if (!btn.dataset.loadingOriginalHtml) btn.dataset.loadingOriginalHtml = btn.innerHTML;
      btn.disabled = true;
      var text = loadingText || "處理中...";
      btn.innerHTML = '<span class="admin-btn__spinner" aria-hidden="true"></span>' + esc(text);
      return;
    }
    if (btn.dataset.loadingOriginalHtml) {
      btn.innerHTML = btn.dataset.loadingOriginalHtml;
      delete btn.dataset.loadingOriginalHtml;
    }
    btn.disabled = false;
  }

  function ensureBatchProgressNode() {
    var node = document.getElementById("admin-batch-progress");
    if (node) return node;
    node = document.createElement("div");
    node.id = "admin-batch-progress";
    node.className = "admin-batch-progress";
    node.hidden = true;
    node.innerHTML =
      '<div class="admin-batch-progress__label" id="admin-batch-progress-label">處理中 0/0</div>' +
      '<div class="admin-batch-progress__track"><div class="admin-batch-progress__fill" id="admin-batch-progress-fill"></div></div>';
    document.body.appendChild(node);
    return node;
  }

  function setBatchProgress(done, total, labelPrefix) {
    var node = ensureBatchProgressNode();
    var labelEl = document.getElementById("admin-batch-progress-label");
    var fillEl = document.getElementById("admin-batch-progress-fill");
    var safeTotal = Math.max(1, Number(total) || 1);
    var safeDone = Math.max(0, Number(done) || 0);
    var percent = Math.max(0, Math.min(100, Math.round((safeDone / safeTotal) * 100)));
    if (labelEl) labelEl.textContent = (labelPrefix || "批次處理") + " " + safeDone + "/" + safeTotal;
    if (fillEl) fillEl.style.width = percent + "%";
    node.hidden = false;
  }

  function clearBatchProgress(delayMs) {
    var node = document.getElementById("admin-batch-progress");
    if (!node) return;
    var delay = Number(delayMs);
    if (isFinite(delay) && delay > 0) {
      setTimeout(function () {
        if (node) node.hidden = true;
      }, delay);
      return;
    }
    node.hidden = true;
  }

  function setTbodyError(tbodyId, msg, colspan) {
    var tb = document.getElementById(tbodyId);
    if (!tb) return;
    tb.innerHTML =
      '<tr><td colspan="' +
      (colspan || 12) +
      '" class="admin-muted">' +
      esc(msg || "載入失敗") +
      "</td></tr>";
  }

  function tableEmptyRow(colspan, title, subtitle) {
    if (typeof window.renderAdminTableEmptyRow === "function") {
      return window.renderAdminTableEmptyRow(colspan, title, subtitle);
    }
    return (
      '<tr><td colspan="' +
      esc(String(colspan || 12)) +
      '" class="admin-muted">' +
      esc(title || "尚無資料") +
      "</td></tr>"
    );
  }

  function adminBtn(o) {
    if (typeof window.renderAdminBtn === "function") return window.renderAdminBtn(o);
    o = o || {};
    var allowedV = {
      primary: true,
      ghost: true,
      success: true,
      danger: true,
      info: true,
      warning: true,
      accent: true
    };
    var v = allowedV[o.variant] ? o.variant : "ghost";
    return (
      '<button type="button" class="admin-btn admin-btn--' +
      esc(v) +
      (o.sm ? " admin-btn--sm" : "") +
      '"' +
      (o.attrs ? " " + o.attrs : "") +
      (o.disabled ? " disabled" : "") +
      (o.title ? ' title="' + esc(String(o.title)) + '"' : "") +
      ">" +
      esc(o.label) +
      "</button>"
    );
  }

  function adminRowActions(inner, opts) {
    if (typeof window.renderAdminRowActions === "function") {
      return window.renderAdminRowActions(inner, opts);
    }
    var extra = opts && opts.layout === "stack2" ? " admin-row-actions--stack" : "";
    return '<div class="admin-row-actions' + extra + '">' + (inner || "") + "</div>";
  }

  function formatC2cOrderSideLabel(side) {
    var s = String(side || "").toLowerCase();
    if (s === "buy") return "買單";
    if (s === "sell") return "賣單";
    if (!s) return "—";
    return "其他";
  }

  function orderSideBadge(side) {
    var s = String(side || "").toLowerCase();
    var label = formatC2cOrderSideLabel(side);
    var tone = s === "buy" ? "active" : s === "sell" ? "processing" : "neutral";
    return badge(tone, label);
  }

  function sourceLabelZh(source) {
    return String(source || "").toLowerCase() === "agent" ? "代理" : "用戶";
  }

  function sourceBadge(source) {
    var normalized = String(source || "").toLowerCase();
    return badge(normalized === "agent" ? "processing" : "active", sourceLabelZh(normalized));
  }

  function orderMerchantSource(order) {
    if (order && order.merchant_source) return String(order.merchant_source || "").toLowerCase();
    var side = String(order && order.side ? order.side : "").toLowerCase();
    if (side === "buy") return String(order && order.seller_source ? order.seller_source : "user").toLowerCase();
    if (side === "sell") return String(order && order.buyer_source ? order.buyer_source : "user").toLowerCase();
    return "user";
  }

  function orderHasRealUserMerchant(order) {
    return orderMerchantSource(order) !== "agent";
  }

  function badge(status, text) {
    var cls = "admin-badge--neutral";
    var t = String(status || "").toLowerCase();
    if (t === "normal" || t === "active" || t === "approved" || t === "completed" || t === "enabled" || t === "paid") {
      cls = "admin-badge--success";
    } else if (t === "pending" || t === "submitted" || t === "reviewing" || t === "locked" || t === "processing") {
      cls = "admin-badge--warning";
    } else if (t === "disabled" || t === "rejected" || t === "cancelled" || t === "deleted" || t === "failed") {
      cls = "admin-badge--danger";
    }
    return '<span class="admin-badge ' + cls + '">' + esc(text || status || "—") + "</span>";
  }

  function zhEnumLookup(map, key) {
    if (key == null || key === "") return "—";
    var raw = String(key);
    var k = raw.toLowerCase();
    if (Object.prototype.hasOwnProperty.call(map, k)) return map[k];
    if (Object.prototype.hasOwnProperty.call(map, raw)) return map[raw];
    return "其他";
  }

  function assetLabelZh(code) {
    if (code == null || code === "") return "—";
    var k = String(code).toUpperCase();
    var map = { USDT: "USDT", USD: "美元", EUR: "歐元", BTC: "比特幣", ETH: "以太幣" };
    return map[k] || "其他資產";
  }

  function payoutChannelLabelZh(ch) {
    if (ch == null || ch === "") return "—";
    var k = String(ch).toLowerCase();
    var map = { bank: "銀行卡", usdt: "鏈上 USDT", pix: "跨境支付（PIX）" };
    return map[k] || "其他渠道";
  }

  function chainNetworkLabelZh(net) {
    if (net == null || net === "") return "—";
    var k = String(net).toLowerCase();
    var map = {
      trc20: "波場網路",
      erc20: "以太坊網路",
      bep20: "幣安智能鏈",
      trx: "波場",
      eth: "以太坊",
      bsc: "幣安智能鏈"
    };
    return map[k] || "其他網路";
  }

  function walletCodeLabelZh(code) {
    if (code == null || code === "") return "—";
    var k = String(code).toLowerCase();
    var map = {
      cash_usdt: "現金錢包（USDT）",
      eur: "歐元錢包",
      usd: "美元錢包",
      spot_usdt: "現貨 USDT",
      spot_eur: "現貨歐元"
    };
    return map[k] || "其他錢包";
  }

  function withdrawalStatusLabelZh(s) {
    return zhEnumLookup(
      {
        pending: "待審核",
        approved: "已通過",
        rejected: "已駁回",
        cancelled: "已取消"
      },
      s
    );
  }

  function depositStatusLabelZh(s) {
    return zhEnumLookup(
      {
        pending: "待審核",
        approved: "已通過",
        rejected: "已駁回",
        cancelled: "已取消"
      },
      s
    );
  }

  function parseDepositProofUrls(raw) {
    if (raw == null) return [];
    var s = String(raw).trim();
    if (!s) return [];
    if (s.charAt(0) === "[") {
      try {
        var j = JSON.parse(s);
        if (Array.isArray(j)) {
          return j
            .map(function (x) {
              return String(x == null ? "" : x).trim();
            })
            .filter(Boolean);
        }
      } catch (e) {
        /* ignore */
      }
    }
    if (s.indexOf(";") >= 0) {
      return s.split(";").map(function (x) { return x.trim(); }).filter(Boolean);
    }
    if (s.indexOf(",") >= 0) {
      return s.split(",").map(function (x) { return x.trim(); }).filter(Boolean);
    }
    return [s];
  }

  function depositProofLooksLikeImageUrl(url) {
    var u = String(url || "")
      .trim()
      .split("?")[0]
      .toLowerCase();
    return /\.(jpe?g|png|gif|webp|bmp|svg)$/.test(u);
  }

  function renderDepositProofGrid(urls) {
    return urls
      .map(function (u) {
        return String(u == null ? "" : u).trim();
      })
      .filter(Boolean)
      .map(function (raw) {
        if (!depositProofLooksLikeImageUrl(raw)) {
          return (
            '<div class="admin-deposit-proof-tile admin-deposit-proof-tile--link">' +
            '<a class="admin-btn admin-btn--ghost admin-btn--sm" href="' +
            esc(raw) +
            '" target="_blank" rel="noopener noreferrer">開啟連結</a></div>'
          );
        }
        return (
          '<button type="button" class="admin-deposit-proof-thumb" aria-label="展開預覽">' +
          '<img src="' +
          esc(raw) +
          '" alt="憑證縮圖" loading="lazy" decoding="async"/>' +
          "</button>"
        );
      })
      .join("");
  }

  function closeDepositDetailZoom() {
    var z = document.getElementById("view-deposit-detail-img-zoom");
    var img = document.getElementById("view-deposit-detail-zoom-img");
    if (img) img.removeAttribute("src");
    if (z) {
      z.hidden = true;
      z.setAttribute("aria-hidden", "true");
    }
  }

  function openDepositDetailZoom(src) {
    var z = document.getElementById("view-deposit-detail-img-zoom");
    var img = document.getElementById("view-deposit-detail-zoom-img");
    if (!z || !img || !src) return;
    img.src = src;
    z.hidden = false;
    z.setAttribute("aria-hidden", "false");
  }

  window.__adminCloseDepositProofZoom = closeDepositDetailZoom;

  function bindDepositDetailModalOnce() {
    if (document.documentElement._adminDepositDetailZoomBound) return;
    document.documentElement._adminDepositDetailZoomBound = true;
    var modal = document.getElementById("admin-modal-deposit-detail");
    var z = document.getElementById("view-deposit-detail-img-zoom");
    if (z) {
      z.querySelectorAll("[data-deposit-proof-zoom-close]").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          closeDepositDetailZoom();
        });
      });
      z.addEventListener("click", function (e) {
        if (e.target === z) closeDepositDetailZoom();
      });
    }
    if (modal) {
      modal.addEventListener("click", function (e) {
        var th = e.target && e.target.closest ? e.target.closest(".admin-deposit-proof-thumb") : null;
        if (!th || !modal.contains(th)) return;
        e.preventDefault();
        var im = th.querySelector("img");
        var src = im && im.getAttribute("src");
        if (src) openDepositDetailZoom(src);
      });
    }
  }

  function renderDepositDetailDlHtml(req) {
    var st = String(req.status || "").toLowerCase();
    var uid = req.user_id != null ? req.user_id : "—";
    var contact = [req.email, req.mobile_e164]
      .map(function (x) {
        return x != null ? String(x).trim() : "";
      })
      .filter(Boolean)
      .join(" · ");
    var rows = [
      ["申請單號", String(req.id != null ? req.id : "—")],
      ["用戶 ID", String(uid)],
      ["用戶名", req.username != null ? String(req.username) : "—"],
      ["聯絡方式", contact || "—"],
      ["金額", String(req.amount != null ? req.amount : "—") + " " + assetLabelZh(req.asset_code)],
      ["目標錢包", req.target_wallet_code != null ? String(req.target_wallet_code) : "—"],
      ["鏈 / 通道", chainNetworkLabelZh(req.network)],
      ["狀態", "__BADGE__"],
      ["備註 / 參考", req.reference_text != null && String(req.reference_text).trim() !== "" ? String(req.reference_text) : "—"],
      ["審核備註", req.admin_note != null && String(req.admin_note).trim() !== "" ? String(req.admin_note) : "—"],
      ["建立時間", req.created_at != null ? String(req.created_at) : "—"],
      ["審核時間", req.reviewed_at != null ? String(req.reviewed_at) : "—"]
    ];
    return rows
      .map(function (pair) {
        if (pair[0] === "狀態") {
          return (
            "<div><dt>" +
            esc(pair[0]) +
            "</dt><dd>" +
            badge(st, depositStatusLabelZh(req.status)) +
            "</dd></div>"
          );
        }
        return "<div><dt>" + esc(pair[0]) + "</dt><dd>" + esc(pair[1]) + "</dd></div>";
      })
      .join("");
  }

  function openDepositDetailModal(requestId) {
    bindDepositDetailModalOnce();
    closeDepositDetailZoom();
    var modal = document.getElementById("admin-modal-deposit-detail");
    var loading = document.getElementById("view-deposit-detail-loading");
    var err = document.getElementById("view-deposit-detail-error");
    var main = document.getElementById("view-deposit-detail-main");
    var dl = document.getElementById("view-deposit-detail-dl");
    var proofs = document.getElementById("view-deposit-detail-proofs");
    var proofsEmpty = document.getElementById("view-deposit-detail-proofs-empty");
    if (!modal || !api()) return;
    if (loading) {
      loading.style.display = "";
      loading.textContent = "載入中…";
    }
    if (err) {
      err.style.display = "none";
      err.textContent = "";
    }
    if (main) main.style.display = "none";
    if (window.AdminModals && window.AdminModals.open) {
      window.AdminModals.open("admin-modal-deposit-detail");
    }
    api()
      .requestJson("/api/admin/deposit-requests/" + encodeURIComponent(requestId), { fallbackMessage: "載入充值詳情失敗" })
      .then(function (data) {
        var req = data && data.request ? data.request : null;
        if (!req) throw new Error("無資料");
        if (loading) loading.style.display = "none";
        if (err) err.style.display = "none";
        if (main) main.style.display = "";
        if (dl) dl.innerHTML = renderDepositDetailDlHtml(req);
        var urls = parseDepositProofUrls(req.proof_url);
        if (proofs) proofs.innerHTML = renderDepositProofGrid(urls);
        if (proofsEmpty) proofsEmpty.style.display = urls.length ? "none" : "";
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        if (loading) loading.style.display = "none";
        if (main) main.style.display = "none";
        if (err) {
          err.style.display = "";
          err.textContent = e.message || "載入失敗";
        }
      });
  }

  function orderStatusLabelZh(s) {
    return zhEnumLookup(
      {
        pending_payment: "待付款",
        paid_pending_release: "待放幣",
        completed: "已完成",
        cancelled: "已取消",
        disputed: "糾紛中"
      },
      s
    );
  }

  function financialProductStatusLabelZh(s) {
    return zhEnumLookup({ active: "啟用", inactive: "停用" }, s);
  }

  function financialReturnModeLabelZh(m) {
    return zhEnumLookup({ auto: "自動返還", manual: "手動返還" }, m);
  }

  function financialSubscriptionStatusLabelZh(s) {
    return zhEnumLookup(
      {
        active: "持有中",
        settled: "已結清",
        cancelled: "已取消",
        pending: "處理中"
      },
      s
    );
  }

  function listingStatusLabelZh(s) {
    return zhEnumLookup({ active: "上架中", inactive: "已下架" }, s);
  }

  function contentItemStatusLabelZh(s) {
    return zhEnumLookup({ active: "啟用", inactive: "停用", disabled: "停用" }, s);
  }

  function kycStatusLabelZh(s) {
    return zhEnumLookup(
      {
        pending: "待審核",
        approved: "已通過",
        rejected: "已駁回",
        cancelled: "已取消",
        submitted: "已提交"
      },
      s
    );
  }

  function authEventActionLabelZh(action) {
    if (action == null || action === "") return "—";
    var k = String(action).toLowerCase().replace(/[\s-]+/g, "_");
    var map = {
      login: "登入",
      logout: "登出",
      login_failed: "登入失敗",
      admin_login: "後台登入",
      admin_logout: "後台登出",
      admin_login_failed: "後台登入失敗",
      password_reset: "重設密碼",
      token_refresh: "更新權杖"
    };
    return map[k] || "其他操作";
  }

  function authErrorCodeLabelZh(code) {
    if (code == null || code === "") return "—";
    var raw = String(code).trim();
    var k = raw.toLowerCase().replace(/[\s-]+/g, "_");
    var map = {
      ok: "成功",
      success: "成功",
      none: "—",
      auth_account_incorrect: "帳號或密碼錯誤",
      auth_invalid_params: "參數錯誤",
      admin_unauthorized: "未授權",
      admin_token_invalid: "權杖無效",
      admin_token_expired: "權杖過期",
      admin_forbidden: "無權限",
      admin_invalid_params: "參數錯誤"
    };
    if (map[k]) return map[k];
    return "錯誤代碼（請查日誌）";
  }

  function loadItems(path, tbodyId, colspan, rowMapper, emptyText, onAfterRender, options) {
    if (!api()) return Promise.resolve();
    options = options || {};
    var state = adminPagerGet(tbodyId);
    var requestPath = options.paginate === false ? path : adminPagerRequestPath(tbodyId, path);
    setTbodyLoading(tbodyId, colspan, !!options.useSkeleton);
    return api()
      .requestJson(requestPath, { fallbackMessage: "載入失敗" })
      .then(function (data) {
        var rawItems = (data && data.items) || [];
        var pagination = data && data.pagination ? data.pagination : null;
        var items = rawItems;
        if (!pagination && options.paginate !== false && rawItems.length > state.pageSize) {
          var start = (Math.max(1, state.page || 1) - 1) * state.pageSize;
          items = rawItems.slice(start, start + state.pageSize);
          pagination = { page: state.page, page_size: state.pageSize, total: rawItems.length };
        }
        var tb = document.getElementById(tbodyId);
        if (!tb) return;
        if (!items.length) {
          tb.innerHTML = tableEmptyRow(colspan, emptyText || "尚無資料", "");
          if (typeof onAfterRender === "function") onAfterRender([]);
          if (options.paginate !== false) {
            adminPagerRender(tbodyId, pagination || { page: state.page, page_size: state.pageSize, total: rawItems.length || 0 }, function () {
              loadItems(path, tbodyId, colspan, rowMapper, emptyText, onAfterRender, options);
            });
          }
          return;
        }
        tb.innerHTML = items.map(rowMapper).join("");
        if (typeof onAfterRender === "function") onAfterRender(items);
        if (options.paginate !== false) {
          adminPagerRender(tbodyId, pagination || { page: state.page, page_size: state.pageSize, total: rawItems.length || items.length }, function () {
            loadItems(path, tbodyId, colspan, rowMapper, emptyText, onAfterRender, options);
          });
        }
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setTbodyError(tbodyId, e.message, colspan);
      });
  }

  function tradeFeedAssetDisplay(code) {
    if (code == null || code === "") return "";
    var k = String(code).toUpperCase();
    if (k === "USDT") return "USDT";
    return assetLabelZh(code);
  }

  function formatTradeFeedAmount(amount, assetCode) {
    if (amount == null || amount === "") return "—";
    var n = Number(amount);
    var value = isFinite(n)
      ? n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })
      : String(amount);
    return value + (assetCode ? " " + tradeFeedAssetDisplay(assetCode) : "");
  }

  function syncTradeFeedTitleField(prefix) {
    var action = document.getElementById("view-tf-" + prefix + "-action");
    var title = document.getElementById("view-tf-" + prefix + "-title");
    if (!action || !title) return;
    if (action.value === "custom") {
      title.removeAttribute("disabled");
      title.placeholder = "請輸入顯示標題";
      return;
    }
    title.setAttribute("disabled", "disabled");
    title.placeholder = "非自訂：標題由系統帶入預設文案";
    if (prefix === "create") title.value = "";
  }

  function resetTradeFeedCreateForm() {
    var action = document.getElementById("view-tf-create-action");
    if (action) action.value = "completed_buy";
    var title = document.getElementById("view-tf-create-title");
    if (title) title.value = "";
    var actor = document.getElementById("view-tf-create-actor");
    if (actor) actor.value = "";
    var asset = document.getElementById("view-tf-create-asset");
    if (asset) asset.value = "USDT";
    var amount = document.getElementById("view-tf-create-amount");
    if (amount) amount.value = "";
    var sort = document.getElementById("view-tf-create-sort");
    if (sort) sort.value = "0";
    var status = document.getElementById("view-tf-create-status");
    if (status) status.value = "active";
    var msg = document.getElementById("view-tf-create-msg");
    if (msg) msg.textContent = "";
    syncTradeFeedTitleField("create");
  }

  function collectTradeFeedBodyFromForm(prefix) {
    var action = (document.getElementById("view-tf-" + prefix + "-action") || {}).value || "custom";
    var titleEl = document.getElementById("view-tf-" + prefix + "-title");
    var actor = (document.getElementById("view-tf-" + prefix + "-actor") || {}).value;
    var asset = (document.getElementById("view-tf-" + prefix + "-asset") || {}).value;
    var amount = (document.getElementById("view-tf-" + prefix + "-amount") || {}).value;
    var sort = (document.getElementById("view-tf-" + prefix + "-sort") || {}).value;
    var status = (document.getElementById("view-tf-" + prefix + "-status") || {}).value;
    var body = {
      action_type: action,
      actor_name: String(actor || "").trim(),
      asset_code: String(asset || "").trim().toUpperCase() || "USDT",
      amount: String(amount || "").trim(),
      sort_order: parseInt(String(sort || "0"), 10) || 0,
      status: status
    };
    if (action === "custom" && titleEl) {
      body.title = String(titleEl.value || "").trim();
    }
    return body;
  }

  function validateTradeFeedBody(body) {
    if (!body.actor_name) return "請填寫商戶名";
    if (body.amount === "" || !isFinite(Number(body.amount))) return "請填寫有效金額";
    if (body.action_type === "custom" && (!body.title || String(body.title).trim() === "")) {
      return "自訂類型需填寫標題";
    }
    return "";
  }

  function openTradeFeedDetailModal(id) {
    if (!api()) return;
    var msg = document.getElementById("view-tf-detail-msg");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/trade-feed-events/" + encodeURIComponent(id), {
        fallbackMessage: "載入播報失敗"
      })
      .then(function (data) {
        var ev = data && data.event;
        if (!ev || ev.id == null) throw new Error("無播報資料");
        var hid = document.getElementById("view-tf-detail-id");
        if (hid) hid.value = String(ev.id);
        var act = document.getElementById("view-tf-detail-action");
        if (act) act.value = ev.action_type || "custom";
        var title = document.getElementById("view-tf-detail-title");
        if (title) title.value = ev.title != null ? String(ev.title) : "";
        var actor = document.getElementById("view-tf-detail-actor");
        if (actor) actor.value = ev.actor_name != null ? String(ev.actor_name) : "";
        var asset = document.getElementById("view-tf-detail-asset");
        if (asset) asset.value = ev.asset_code != null ? String(ev.asset_code) : "USDT";
        var amount = document.getElementById("view-tf-detail-amount");
        if (amount) amount.value = ev.amount != null ? String(ev.amount) : "";
        var sort = document.getElementById("view-tf-detail-sort");
        if (sort) sort.value = ev.sort_order != null ? String(ev.sort_order) : "0";
        var st = document.getElementById("view-tf-detail-status");
        if (st) st.value = ev.status || "active";
        syncTradeFeedTitleField("detail");
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-trade-feed-detail");
        }
      })
      .catch(function (e) {
        if (msg) msg.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function initTradeFeedForms() {
    if (document.documentElement._adminTfFormsBound) return;
    document.documentElement._adminTfFormsBound = true;

    document.addEventListener("click", function (e) {
      var c = e.target.closest("[data-admin-tf-open-create]");
      if (c) {
        e.preventDefault();
        resetTradeFeedCreateForm();
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-trade-feed");
        }
        return;
      }
      var r = e.target.closest("[data-admin-tf-random-create]");
      if (r) {
        e.preventDefault();
        if (!api()) return;
        var originalText = r.textContent;
        var actionType = String(r.getAttribute("data-admin-tf-random-create") || "").trim();
        r.disabled = true;
        r.textContent = "生成中…";
        api()
          .requestJson("/api/admin/trade-feed-events/random", {
            method: "POST",
            body: actionType ? { action_type: actionType } : undefined,
            fallbackMessage: "隨機建立播報失敗"
          })
          .then(function () {
            reloadCurrentView();
          })
          .catch(function (err) {
            adminNotifyError(err.message || "隨機建立播報失敗");
          })
          .finally(function () {
            r.disabled = false;
            r.textContent = originalText || "随机新增";
          });
        return;
      }
      var d = e.target.closest("[data-admin-tf-open-detail]");
      if (d) {
        e.preventDefault();
        var tid = d.getAttribute("data-admin-tf-open-detail");
        if (tid) openTradeFeedDetailModal(tid);
      }
    });

    ["create", "detail"].forEach(function (pfx) {
      var act = document.getElementById("view-tf-" + pfx + "-action");
      if (act && !act._adminTfSyncBound) {
        act._adminTfSyncBound = true;
        act.addEventListener("change", function () {
          syncTradeFeedTitleField(pfx);
        });
      }
    });

    var createForm = document.getElementById("view-trade-feed-create-form");
    if (createForm && !createForm._adminTfBound) {
      createForm._adminTfBound = true;
      createForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-tf-create-msg");
        if (msg) msg.textContent = "";
        var body = collectTradeFeedBodyFromForm("create");
        var err = validateTradeFeedBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/trade-feed-events", {
            method: "POST",
            body: body,
            fallbackMessage: "建立播報失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已建立。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-create-trade-feed");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "建立失敗";
          });
      });
    }

    var detailForm = document.getElementById("view-trade-feed-detail-form");
    if (detailForm && !detailForm._adminTfBound) {
      detailForm._adminTfBound = true;
      detailForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-tf-detail-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-tf-detail-id");
        var id = hid ? String(hid.value || "").trim() : "";
        if (!id) {
          if (msg) msg.textContent = "缺少播報 ID。";
          return;
        }
        var body = collectTradeFeedBodyFromForm("detail");
        var err = validateTradeFeedBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/trade-feed-events/" + encodeURIComponent(id), {
            method: "PATCH",
            body: body,
            fallbackMessage: "更新播報失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已儲存。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-trade-feed-detail");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "儲存失敗";
          });
      });
    }
  }

  function resetHomeBannerCreateForm() {
    [
      "view-hb-create-reason",
      "view-hb-create-title",
      "view-hb-create-subtitle",
      "view-hb-create-badge",
      "view-hb-create-image-url",
      "view-hb-create-link-url"
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = "";
    });
    var sort = document.getElementById("view-hb-create-sort");
    if (sort) sort.value = "0";
    var status = document.getElementById("view-hb-create-status");
    if (status) status.value = "active";
    var msg = document.getElementById("view-hb-create-msg");
    if (msg) msg.textContent = "";
  }

  function collectHomeBannerBody(isDetail) {
    var pfx = isDetail ? "detail" : "create";
    return {
      reason: String((document.getElementById("view-hb-" + pfx + "-reason") || {}).value || "").trim(),
      title: String((document.getElementById("view-hb-" + pfx + "-title") || {}).value || "").trim(),
      subtitle: String((document.getElementById("view-hb-" + pfx + "-subtitle") || {}).value || "").trim(),
      badge_text: String((document.getElementById("view-hb-" + pfx + "-badge") || {}).value || "").trim(),
      image_url: String((document.getElementById("view-hb-" + pfx + "-image-url") || {}).value || "").trim(),
      link_url: String((document.getElementById("view-hb-" + pfx + "-link-url") || {}).value || "").trim(),
      sort_order: parseInt(String((document.getElementById("view-hb-" + pfx + "-sort") || {}).value || "0"), 10) || 0,
      status: (document.getElementById("view-hb-" + pfx + "-status") || {}).value || "active"
    };
  }

  function validateHomeBannerBody(body) {
    if (!body.reason) return "請填寫異動原因";
    if (!body.title) return "請填寫標題";
    if (!body.image_url) return "請填寫圖片網址";
    return "";
  }

  function openHomeBannerDetailModal(id) {
    if (!api()) return;
    var msg = document.getElementById("view-hb-detail-msg");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/home-content/banners/" + encodeURIComponent(id), {
        fallbackMessage: "載入橫幅失敗"
      })
      .then(function (data) {
        var b = data && data.banner;
        if (!b || b.id == null) throw new Error("無橫幅資料");
        var hid = document.getElementById("view-hb-detail-id");
        if (hid) hid.value = String(b.id);
        var reason = document.getElementById("view-hb-detail-reason");
        if (reason) reason.value = "";
        var title = document.getElementById("view-hb-detail-title");
        if (title) title.value = b.title != null ? String(b.title) : "";
        var sub = document.getElementById("view-hb-detail-subtitle");
        if (sub) sub.value = b.subtitle != null ? String(b.subtitle) : "";
        var badge = document.getElementById("view-hb-detail-badge");
        if (badge) badge.value = b.badge_text != null ? String(b.badge_text) : "";
        var img = document.getElementById("view-hb-detail-image-url");
        if (img) img.value = b.image_url != null ? String(b.image_url) : "";
        var link = document.getElementById("view-hb-detail-link-url");
        if (link) link.value = b.link_url != null ? String(b.link_url) : "";
        var sort = document.getElementById("view-hb-detail-sort");
        if (sort) sort.value = b.sort_order != null ? String(b.sort_order) : "0";
        var st = document.getElementById("view-hb-detail-status");
        if (st) st.value = b.status || "active";
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-home-banner-detail");
        }
      })
      .catch(function (e) {
        if (msg) msg.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function initHomeBannerForms() {
    if (document.documentElement._adminHbFormsBound) return;
    document.documentElement._adminHbFormsBound = true;

    document.addEventListener("click", function (e) {
      var c = e.target.closest("[data-admin-hb-open-create]");
      if (c) {
        e.preventDefault();
        resetHomeBannerCreateForm();
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-home-banner");
        }
        return;
      }
      var d = e.target.closest("[data-admin-hb-open-detail]");
      if (d) {
        e.preventDefault();
        var bid = d.getAttribute("data-admin-hb-open-detail");
        if (bid) openHomeBannerDetailModal(bid);
      }
    });

    var createForm = document.getElementById("view-home-banner-create-form");
    if (createForm && !createForm._adminHbBound) {
      createForm._adminHbBound = true;
      createForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-hb-create-msg");
        if (msg) msg.textContent = "";
        var body = collectHomeBannerBody(false);
        var err = validateHomeBannerBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/home-content/banners", {
            method: "POST",
            body: body,
            fallbackMessage: "建立橫幅失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已建立。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-create-home-banner");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "建立失敗";
          });
      });
    }

    var detailForm = document.getElementById("view-home-banner-detail-form");
    if (detailForm && !detailForm._adminHbBound) {
      detailForm._adminHbBound = true;
      detailForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-hb-detail-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-hb-detail-id");
        var id = hid ? String(hid.value || "").trim() : "";
        if (!id) {
          if (msg) msg.textContent = "缺少橫幅 ID。";
          return;
        }
        var body = collectHomeBannerBody(true);
        var err = validateHomeBannerBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/home-content/banners/" + encodeURIComponent(id), {
            method: "PATCH",
            body: body,
            fallbackMessage: "更新橫幅失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已儲存。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-home-banner-detail");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "儲存失敗";
          });
      });
    }
  }

  function resetFinancialProductCreateForm() {
    var fields = {
      "view-fp-create-code": "",
      "view-fp-create-display": "",
      "view-fp-create-asset": "USDT",
      "view-fp-create-subtitle": "",
      "view-fp-create-detail": "",
      "view-fp-create-apr": "5",
      "view-fp-create-term": "30",
      "view-fp-create-min": "100",
      "view-fp-create-personal": "50000",
      "view-fp-create-total": "1000000",
      "view-fp-create-sort": "0",
      "view-fp-create-return-delay": "0"
    };
    Object.keys(fields).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = fields[id];
    });
    var mode = document.getElementById("view-fp-create-return-mode");
    if (mode) mode.value = "auto";
    var st = document.getElementById("view-fp-create-status");
    if (st) st.value = "active";
    var msg = document.getElementById("view-fp-create-msg");
    if (msg) msg.textContent = "";
  }

  function readFpField(pfx, suffix) {
    var el = document.getElementById("view-fp-" + pfx + "-" + suffix);
    return el ? el.value : "";
  }

  function collectFinancialProductBody(detail) {
    var pfx = detail ? "detail" : "create";
    return {
      product_code: String(readFpField(pfx, "code") || "").trim().toLowerCase(),
      display_name: String(readFpField(pfx, "display") || "").trim(),
      asset_code: String(readFpField(pfx, "asset") || "").trim().toUpperCase(),
      subtitle: String(readFpField(pfx, "subtitle") || "").trim(),
      detail_note: String((document.getElementById("view-fp-" + pfx + "-detail") || {}).value || "").trim(),
      apr_rate: parseFloat(String(readFpField(pfx, "apr") || "0")),
      term_days: parseInt(String(readFpField(pfx, "term") || "0"), 10),
      min_subscribe_amount: parseFloat(String(readFpField(pfx, "min") || "0")),
      personal_limit_amount: parseFloat(String(readFpField(pfx, "personal") || "0")),
      total_quota_amount: parseFloat(String(readFpField(pfx, "total") || "0")),
      sort_order: parseInt(String(readFpField(pfx, "sort") || "0"), 10) || 0,
      default_return_mode: (document.getElementById("view-fp-" + pfx + "-return-mode") || {}).value || "auto",
      default_return_delay_days: parseInt(String(readFpField(pfx, "return-delay") || "0"), 10) || 0,
      status: (document.getElementById("view-fp-" + pfx + "-status") || {}).value || "active"
    };
  }

  function validateFinancialProductBody(body) {
    if (!/^[a-z0-9_-]{3,40}$/.test(body.product_code)) {
      return "產品編碼須為 3–40 位小寫英數、底線或連字號";
    }
    if (!body.asset_code) return "請填寫資產代碼";
    if (body.display_name.length > 80) return "顯示名稱過長";
    if (body.subtitle.length > 160) return "副標題過長";
    if (body.detail_note.length > 500) return "產品說明過長（最多 500 字）";
    if (body.apr_rate < 0 || isNaN(body.apr_rate)) return "APR 無效";
    if (!(body.term_days > 0) || isNaN(body.term_days)) return "期限須為大於 0 的整數天數";
    if (!(body.min_subscribe_amount > 0) || isNaN(body.min_subscribe_amount)) return "最低申購須大於 0";
    if (!(body.personal_limit_amount > 0) || isNaN(body.personal_limit_amount)) return "個人額度須大於 0";
    if (!(body.total_quota_amount > 0) || isNaN(body.total_quota_amount)) return "總募集額度須大於 0";
    if (body.personal_limit_amount > body.total_quota_amount) return "個人額度不可大於總募集額度";
    return "";
  }

  function openFinancialProductDetailModal(id) {
    if (!api()) return;
    var msg = document.getElementById("view-fp-detail-msg");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/financial-products/" + encodeURIComponent(id), {
        fallbackMessage: "載入理財產品失敗"
      })
      .then(function (data) {
        var it = data && data.item;
        if (!it || it.id == null) throw new Error("無產品資料");
        var hid = document.getElementById("view-fp-detail-id");
        if (hid) hid.value = String(it.id);
        var set = function (sid, val) {
          var el = document.getElementById(sid);
          if (el) el.value = val != null ? String(val) : "";
        };
        set("view-fp-detail-code", it.product_code);
        set("view-fp-detail-display", it.display_name || "");
        set("view-fp-detail-asset", it.asset_code);
        set("view-fp-detail-subtitle", it.subtitle || "");
        set("view-fp-detail-detail", it.detail_note || "");
        set("view-fp-detail-apr", it.apr_rate);
        set("view-fp-detail-term", it.term_days);
        set("view-fp-detail-min", it.min_subscribe_amount);
        set("view-fp-detail-personal", it.personal_limit_amount);
        set("view-fp-detail-total", it.total_quota_amount);
        set("view-fp-detail-sort", it.sort_order != null ? it.sort_order : 0);
        var mode = document.getElementById("view-fp-detail-return-mode");
        if (mode) mode.value = it.default_return_mode || "auto";
        set("view-fp-detail-return-delay", it.default_return_delay_days != null ? it.default_return_delay_days : 0);
        var st = document.getElementById("view-fp-detail-status");
        if (st) st.value = it.status || "active";
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-financial-product-detail");
        }
      })
      .catch(function (e) {
        if (msg) msg.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function initFinancialProductForms() {
    if (document.documentElement._adminFpFormsBound) return;
    document.documentElement._adminFpFormsBound = true;

    document.addEventListener("click", function (e) {
      var c = e.target.closest("[data-admin-fp-open-create]");
      if (c) {
        e.preventDefault();
        resetFinancialProductCreateForm();
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-financial-product");
        }
        return;
      }
      var d = e.target.closest("[data-admin-fp-open-detail]");
      if (d) {
        e.preventDefault();
        var pid = d.getAttribute("data-admin-fp-open-detail");
        if (pid) openFinancialProductDetailModal(pid);
      }
    });

    var createForm = document.getElementById("view-financial-product-create-form");
    if (createForm && !createForm._adminFpBound) {
      createForm._adminFpBound = true;
      createForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-fp-create-msg");
        if (msg) msg.textContent = "";
        var body = collectFinancialProductBody(false);
        var err = validateFinancialProductBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/financial-products", {
            method: "POST",
            body: body,
            fallbackMessage: "建立理財產品失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已建立。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-create-financial-product");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "建立失敗";
          });
      });
    }

    var detailForm = document.getElementById("view-financial-product-detail-form");
    if (detailForm && !detailForm._adminFpBound) {
      detailForm._adminFpBound = true;
      detailForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-fp-detail-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-fp-detail-id");
        var id = hid ? String(hid.value || "").trim() : "";
        if (!id) {
          if (msg) msg.textContent = "缺少產品 ID。";
          return;
        }
        var body = collectFinancialProductBody(true);
        var err = validateFinancialProductBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/financial-products/" + encodeURIComponent(id), {
            method: "PATCH",
            body: body,
            fallbackMessage: "更新理財產品失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已儲存。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-financial-product-detail");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "儲存失敗";
          });
      });
    }
  }

  var ADMIN_DEFAULT_DEPOSIT_TRC20 = "TUxyywtEffhzRoACbvHUh3756FPE1CYV56";
  var ADMIN_DEFAULT_DEPOSIT_ERC20 = "0x9208A00F5D4B652a68687335Fae4050B5f006332";
  var ADMIN_DEFAULT_DEPOSIT_BEP20 = "0x9208A00F5D4B652a68687335Fae4050B5f006332";

  function syncAdminDepositPlatformDefaultsPanel() {
    var trc = document.getElementById("view-da-default-trc");
    var eth = document.getElementById("view-da-default-eth");
    var bep = document.getElementById("view-da-default-bep");
    if (trc) trc.textContent = ADMIN_DEFAULT_DEPOSIT_TRC20;
    if (eth) eth.textContent = ADMIN_DEFAULT_DEPOSIT_ERC20;
    if (bep) bep.textContent = ADMIN_DEFAULT_DEPOSIT_BEP20;
  }

  function depositChainEffective(raw, fallback) {
    var t = String(raw || "").trim();
    return t ? t : fallback;
  }

  function depositChainUsesDefault(raw) {
    return !String(raw || "").trim();
  }

  /** 列表欄：若與平台預設充值地址相同，只顯示「預設」不展開長地址 */
  function depositAddressListCellHtml(raw, networkCode) {
    var t = String(raw || "").trim();
    if (!t) return esc("--");
    var k = String(networkCode || "").toUpperCase();
    var def =
      k === "TRC20"
        ? ADMIN_DEFAULT_DEPOSIT_TRC20
        : k === "ERC20"
          ? ADMIN_DEFAULT_DEPOSIT_ERC20
          : k === "BEP20"
            ? ADMIN_DEFAULT_DEPOSIT_BEP20
            : "";
    if (def) {
      var a = k === "TRC20" ? t : t.toLowerCase();
      var b = k === "TRC20" ? String(def).trim() : String(def).trim().toLowerCase();
      if (a === b) return esc("預設");
    }
    return esc(t);
  }

  function buildAdminDepositAddressesPath() {
    var q = new URLSearchParams();
    var kwEl = document.getElementById("view-da-keyword");
    var kw = kwEl && kwEl.value ? String(kwEl.value).trim() : "";
    if (kw) q.set("keyword", kw);
    var qs = q.toString();
    return "/api/admin/deposit-addresses" + (qs ? "?" + qs : "");
  }

  function bindViewDepositAddressesPage() {
    var root = document.getElementById("view-deposit-addresses");
    if (!root || root.dataset.daPageBound === "1") return;
    root.dataset.daPageBound = "1";
    var qBtn = document.getElementById("view-da-query");
    var rBtn = document.getElementById("view-da-refresh");
    var resetBtn = document.getElementById("view-da-reset");
    if (qBtn) qBtn.addEventListener("click", function () { loadViewDepositAddresses(); });
    if (rBtn) rBtn.addEventListener("click", function () { loadViewDepositAddresses(); });
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        var kwEl = document.getElementById("view-da-keyword");
        if (kwEl) kwEl.value = "";
        loadViewDepositAddresses();
      });
    }
    var kwEl = document.getElementById("view-da-keyword");
    if (kwEl) {
      kwEl.addEventListener("keydown", function (e) {
        if (e.key === "Enter") loadViewDepositAddresses();
      });
    }
    syncAdminDepositPlatformDefaultsPanel();
  }

  function resetDepositAddressCreateForm() {
    ["view-da-create-user-id", "view-da-create-trc20", "view-da-create-erc20", "view-da-create-bep20"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = "";
    });
    var msg = document.getElementById("view-da-create-msg");
    if (msg) msg.textContent = "";
  }

  function collectDepositAddressBody(isDetail) {
    var pfx = isDetail ? "detail" : "create";
    var uidEl = document.getElementById(isDetail ? "view-da-detail-user-id" : "view-da-create-user-id");
    var uid = uidEl ? parseInt(String(uidEl.value || "").trim(), 10) : 0;
    return {
      user_id: uid,
      trc20_address: String((document.getElementById("view-da-" + pfx + "-trc20") || {}).value || "").trim(),
      erc20_address: String((document.getElementById("view-da-" + pfx + "-erc20") || {}).value || "").trim(),
      bep20_address: String((document.getElementById("view-da-" + pfx + "-bep20") || {}).value || "").trim()
    };
  }

  function validateDepositAddressBody(body) {
    if (!(body.user_id > 0) || isNaN(body.user_id)) return "請填寫有效的用戶 ID";
    if (!body.trc20_address && !body.erc20_address && !body.bep20_address) {
      return "請至少填寫一條鏈地址";
    }
    return "";
  }

  function openDepositAddressDetailModal(userId) {
    if (!api()) return;
    var msg = document.getElementById("view-da-detail-msg");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/deposit-addresses/" + encodeURIComponent(userId), {
        fallbackMessage: "載入充值地址失敗"
      })
      .then(function (data) {
        var it = data && data.item;
        if (!it || it.user_id == null) throw new Error("無地址資料");
        var hid = document.getElementById("view-da-detail-user-id");
        if (hid) hid.value = String(it.user_id);
        var disp = document.getElementById("view-da-detail-user-id-display");
        if (disp) disp.value = String(it.user_id);
        var set = function (sid, val) {
          var el = document.getElementById(sid);
          if (el) el.value = val != null ? String(val) : "";
        };
        set(
          "view-da-detail-trc20",
          depositChainEffective(it.trc20_address, ADMIN_DEFAULT_DEPOSIT_TRC20)
        );
        set(
          "view-da-detail-erc20",
          depositChainEffective(it.erc20_address, ADMIN_DEFAULT_DEPOSIT_ERC20)
        );
        set(
          "view-da-detail-bep20",
          depositChainEffective(it.bep20_address, ADMIN_DEFAULT_DEPOSIT_BEP20)
        );
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-deposit-address-detail");
        }
      })
      .catch(function (e) {
        if (msg) msg.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function initDepositAddressForms() {
    if (document.documentElement._adminDaFormsBound) return;
    document.documentElement._adminDaFormsBound = true;

    document.addEventListener("click", function (e) {
      var c = e.target.closest("[data-admin-da-open-create]");
      if (c) {
        e.preventDefault();
        resetDepositAddressCreateForm();
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-deposit-address");
        }
        return;
      }
      var d = e.target.closest("[data-admin-da-open-detail]");
      if (d) {
        e.preventDefault();
        var uid = d.getAttribute("data-admin-da-open-detail");
        if (uid) openDepositAddressDetailModal(uid);
      }
    });

    var createForm = document.getElementById("view-da-create-form");
    if (createForm && !createForm._adminDaBound) {
      createForm._adminDaBound = true;
      createForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-da-create-msg");
        if (msg) msg.textContent = "";
        var body = collectDepositAddressBody(false);
        var err = validateDepositAddressBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/deposit-addresses", {
            method: "POST",
            body: body,
            fallbackMessage: "建立充值地址失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已建立。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-create-deposit-address");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "建立失敗";
          });
      });
    }

    var detailForm = document.getElementById("view-da-detail-form");
    if (detailForm && !detailForm._adminDaDetailBound) {
      detailForm._adminDaDetailBound = true;
      detailForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-da-detail-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-da-detail-user-id");
        var uid = hid ? String(hid.value || "").trim() : "";
        if (!uid) {
          if (msg) msg.textContent = "缺少用戶 ID。";
          return;
        }
        var body = collectDepositAddressBody(true);
        var err = validateDepositAddressBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/deposit-addresses/" + encodeURIComponent(uid), {
            method: "PATCH",
            body: body,
            fallbackMessage: "更新充值地址失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已儲存。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-deposit-address-detail");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "儲存失敗";
          });
      });
    }
  }

  function resetListingCreateForm() {
    var owner = document.getElementById("view-listing-create-owner");
    if (owner) owner.value = "";
    var nick = document.getElementById("view-listing-create-nickname");
    if (nick) nick.value = "";
    var side = document.getElementById("view-listing-create-side");
    if (side) side.value = "sell";
    var st = document.getElementById("view-listing-create-status");
    if (st) st.value = "active";
    ["view-listing-create-price", "view-listing-create-min", "view-listing-create-max", "view-listing-create-available", "view-listing-create-payment", "view-listing-create-completion"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = "";
    });
    ["view-listing-create-badge-vip", "view-listing-create-badge-pro", "view-listing-create-badge-stars"].forEach(function (id) {
      var cb = document.getElementById(id);
      if (cb) cb.checked = false;
    });
    var msg = document.getElementById("view-listing-create-msg");
    if (msg) msg.textContent = "";
  }

  function readListingField(pfx, suffix) {
    var el = document.getElementById("view-listing-" + pfx + "-" + suffix);
    return el ? el.value : "";
  }

  function readListingCheckbox(pfx, suffix) {
    var el = document.getElementById("view-listing-" + pfx + "-" + suffix);
    return !!(el && el.checked);
  }

  function collectListingBody(pfx) {
    var ownerRaw = String(readListingField(pfx, "owner") || "").trim();
    var ownerUserId = ownerRaw === "" ? 0 : parseInt(ownerRaw, 10);
    if (isNaN(ownerUserId) || ownerUserId < 0) ownerUserId = 0;
    return {
      owner_user_id: ownerUserId,
      nickname: String(readListingField(pfx, "nickname") || "").trim(),
      side: String(readListingField(pfx, "side") || "").trim(),
      price: String(readListingField(pfx, "price") || "").trim(),
      min_amount: String(readListingField(pfx, "min") || "").trim(),
      max_amount: String(readListingField(pfx, "max") || "").trim(),
      available_amount: String(readListingField(pfx, "available") || "").trim(),
      payment_method_summary: String(readListingField(pfx, "payment") || "").trim(),
      completion_rate: String(readListingField(pfx, "completion") || "").trim(),
      status: String(readListingField(pfx, "status") || "").trim(),
      badge_vip: readListingCheckbox(pfx, "badge-vip"),
      badge_pro: readListingCheckbox(pfx, "badge-pro"),
      badge_stars: readListingCheckbox(pfx, "badge-stars")
    };
  }

  function validateListingBody(body) {
    if (!body.nickname) return "請填寫商戶暱稱";
    if (body.side !== "buy" && body.side !== "sell") return "方向無效";
    if (!body.status || (body.status !== "active" && body.status !== "inactive")) return "狀態無效";
    if (!isFinite(parseFloat(body.price))) return "請填寫有效單價";
    if (!isFinite(parseFloat(body.min_amount))) return "請填寫有效最小金額";
    if (!isFinite(parseFloat(body.max_amount))) return "請填寫有效最大金額";
    if (!isFinite(parseFloat(body.available_amount))) return "請填寫有效庫存";
    var mn = parseFloat(body.min_amount);
    var mx = parseFloat(body.max_amount);
    if (mn > mx) return "最小金額不可大於最大金額";
    return "";
  }

  function openListingDetailModal(id) {
    if (!api()) return;
    var msg = document.getElementById("view-listing-detail-msg");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/listings/" + encodeURIComponent(id), {
        fallbackMessage: "載入掛單失敗"
      })
      .then(function (data) {
        var l = data && data.listing;
        if (!l || l.id == null) throw new Error("無掛單資料");
        var hid = document.getElementById("view-listing-detail-id");
        if (hid) hid.value = String(l.id);
        var set = function (sid, val) {
          var el = document.getElementById(sid);
          if (el) el.value = val != null ? String(val) : "";
        };
        set("view-listing-detail-owner", l.owner_user_id != null && l.owner_user_id > 0 ? String(l.owner_user_id) : "");
        set("view-listing-detail-nickname", l.nickname || "");
        var side = document.getElementById("view-listing-detail-side");
        if (side) side.value = l.side === "buy" ? "buy" : "sell";
        var st = document.getElementById("view-listing-detail-status");
        if (st) st.value = l.status === "inactive" ? "inactive" : "active";
        set("view-listing-detail-price", l.price);
        set("view-listing-detail-min", l.min_amount);
        set("view-listing-detail-max", l.max_amount);
        set("view-listing-detail-available", l.available_amount);
        set("view-listing-detail-payment", l.payment_method_summary || "");
        set("view-listing-detail-completion", l.completion_rate != null ? String(l.completion_rate) : "");
        function truthyBadge(v) {
          return v === true || v === 1 || v === "1";
        }
        [["view-listing-detail-badge-vip", l.badge_vip], ["view-listing-detail-badge-pro", l.badge_pro], ["view-listing-detail-badge-stars", l.badge_stars]].forEach(function (pair) {
          var el = document.getElementById(pair[0]);
          if (el) el.checked = truthyBadge(pair[1]);
        });
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-listing-detail");
        }
      })
      .catch(function (e) {
        if (msg) msg.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function initListingForms() {
    if (document.documentElement._adminListingFormsBound) return;
    document.documentElement._adminListingFormsBound = true;

    document.addEventListener("click", function (e) {
      var delBtn = e.target.closest("[data-admin-listing-delete]");
      if (delBtn) {
        e.preventDefault();
        var delId = delBtn.getAttribute("data-admin-listing-delete");
        if (!delId) return;
        adminConfirm({
          title: "刪除掛單",
          message: "確定刪除掛單 #" + delId + "？此操作無法復原。",
          danger: true,
          confirmLabel: "繼續"
        }).then(function (ok) {
          if (!ok) return;
          adminPrompt({
            title: "刪除原因（選填）",
            message: "可填寫刪除原因供審計；留空亦可。",
            fieldLabel: "刪除原因",
            required: false,
            placeholder: "選填"
          }).then(function (delReason) {
            if (delReason === null) return;
            api()
              .requestJson("/api/admin/listings/" + encodeURIComponent(delId), {
                method: "DELETE",
                body: { reason: String(delReason || "").trim() },
                fallbackMessage: "刪除掛單失敗"
              })
              .then(function () {
                reloadCurrentView();
              })
              .catch(function (err) {
                adminNotifyError(err.message || "刪除失敗");
              });
          });
        });
        return;
      }
      var c = e.target.closest("[data-admin-listing-open-create]");
      if (c) {
        e.preventDefault();
        resetListingCreateForm();
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-listing");
        }
        return;
      }
      var d = e.target.closest("[data-admin-listing-open-detail]");
      if (d) {
        e.preventDefault();
        var lid = d.getAttribute("data-admin-listing-open-detail");
        if (lid) openListingDetailModal(lid);
      }
    });

    var createForm = document.getElementById("view-listing-create-form");
    if (createForm && !createForm._adminListingBound) {
      createForm._adminListingBound = true;
      createForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-listing-create-msg");
        if (msg) msg.textContent = "";
        var body = collectListingBody("create");
        var err = validateListingBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/listings", {
            method: "POST",
            body: body,
            fallbackMessage: "建立掛單失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已建立。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-create-listing");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "建立失敗";
          });
      });
    }

    var detailForm = document.getElementById("view-listing-detail-form");
    if (detailForm && !detailForm._adminListingDetailBound) {
      detailForm._adminListingDetailBound = true;
      detailForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-listing-detail-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-listing-detail-id");
        var id = hid ? String(hid.value || "").trim() : "";
        if (!id) {
          if (msg) msg.textContent = "缺少掛單 ID。";
          return;
        }
        var body = collectListingBody("detail");
        var err = validateListingBody(body);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/listings/" + encodeURIComponent(id), {
            method: "PATCH",
            body: body,
            fallbackMessage: "更新掛單失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已儲存。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-listing-detail");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "儲存失敗";
          });
      });
    }
  }

  function resetTierTemplateCreateForm() {
    [
      "view-tt-create-code",
      "view-tt-create-display",
      "view-tt-create-group",
      "view-tt-create-daily",
      "view-tt-create-description",
      "view-tt-create-reason"
    ].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = "";
    });
    var lv = document.getElementById("view-tt-create-level");
    if (lv) lv.value = "1";
    var sc = document.getElementById("view-tt-create-score");
    if (sc) sc.value = "0";
    var me = document.getElementById("view-tt-create-merchant");
    if (me) me.value = "false";
    var ver = document.getElementById("view-tt-create-verified");
    if (ver) ver.value = "false";
    var ms = document.getElementById("view-tt-create-min-sell");
    if (ms) ms.value = "0";
    var mg = document.getElementById("view-tt-create-margin");
    if (mg) mg.value = "0";
    var mr = document.getElementById("view-tt-create-margin-ratio");
    if (mr) mr.value = "0";
    var rk = document.getElementById("view-tt-create-risk");
    if (rk) rk.value = "normal";
    var st = document.getElementById("view-tt-create-status");
    if (st) st.value = "active";
    var so = document.getElementById("view-tt-create-sort");
    if (so) so.value = "0";
    var gp = document.getElementById("view-tt-create-group");
    if (gp) gp.dataset.manualOverride = "false";
    var msg = document.getElementById("view-tt-create-msg");
    if (msg) msg.textContent = "";
    syncTierTemplateGroupInput(false);
  }

  function defaultTierTemplateGroupCode(level) {
    var lv = parseInt(String(level || "1"), 10);
    if (lv === 2) return "vip";
    if (lv === 3) return "vip_plus";
    return "basic";
  }

  function syncTierTemplateGroupInput(isDetail) {
    var pfx = isDetail ? "detail" : "create";
    var lvEl = document.getElementById("view-tt-" + pfx + "-level");
    var groupEl = document.getElementById("view-tt-" + pfx + "-group");
    if (!groupEl) return;
    var defaultGroup = defaultTierTemplateGroupCode(lvEl ? lvEl.value : "1");
    if (groupEl.dataset.manualOverride !== "true") {
      groupEl.value = defaultGroup;
    }
    groupEl.placeholder = "預設：" + defaultGroup + "（可手動覆蓋）";
  }

  function readTierSelectBool(id) {
    var el = document.getElementById(id);
    if (!el) return false;
    return String(el.value || "") === "true";
  }

  function collectTierTemplateBody(isDetail) {
    var pfx = isDetail ? "detail" : "create";
    var gid = "view-tt-" + pfx + "-";
    var code = String((document.getElementById(gid + "code") || {}).value || "").trim().toLowerCase();
    var display = String((document.getElementById(gid + "display") || {}).value || "").trim();
    var level = parseInt(String((document.getElementById(gid + "level") || {}).value || "1"), 10);
    var groupRaw = String((document.getElementById(gid + "group") || {}).value || "").trim();
    var score = parseInt(String((document.getElementById(gid + "score") || {}).value || "0"), 10);
    var dailyRaw = String((document.getElementById(gid + "daily") || {}).value || "").trim();
    var minSell = parseFloat(String((document.getElementById(gid + "min-sell") || {}).value || "0"));
    var margin = parseFloat(String((document.getElementById(gid + "margin") || {}).value || "0"));
    var marginRatio = parseFloat(String((document.getElementById(gid + "margin-ratio") || {}).value || "0"));
    var risk = (document.getElementById(gid + "risk") || {}).value || "normal";
    var description = String((document.getElementById(gid + "description") || {}).value || "").trim();
    var status = (document.getElementById(gid + "status") || {}).value || "active";
    var sortOrder = parseInt(String((document.getElementById(gid + "sort") || {}).value || "0"), 10) || 0;
    var reason = String((document.getElementById(gid + "reason") || {}).value || "").trim();
    var body = {
      template_code: code,
      display_name: display,
      level: isNaN(level) ? 1 : level,
      score: isNaN(score) ? 0 : score,
      merchant_enabled: readTierSelectBool(gid + "merchant"),
      is_verified: readTierSelectBool(gid + "verified"),
      min_sell_amount: isNaN(minSell) ? 0 : minSell,
      margin_amount: isNaN(margin) ? 0 : margin,
      margin_ratio: isNaN(marginRatio) ? 0 : marginRatio,
      risk_status: risk,
      description: description,
      status: status,
      sort_order: sortOrder
    };
    if (groupRaw !== "") body.group_code = groupRaw;
    if (isDetail) {
      if (dailyRaw !== "") body.daily_trade_limit = parseInt(dailyRaw, 10);
      else body.daily_trade_limit = "";
    } else if (dailyRaw !== "") {
      body.daily_trade_limit = parseInt(dailyRaw, 10);
    }
    if (reason) body.reason = reason;
    return body;
  }

  function validateTierTemplateBody(body, isCreate) {
    if (!body.template_code || !/^[a-z0-9_-]+$/.test(body.template_code)) {
      return "模板代碼須為小寫英數、底線或連字號";
    }
    if (!body.display_name) return "請填寫顯示名稱";
    var lv = parseInt(String(body.level), 10);
    if (isNaN(lv) || lv < 1 || lv > 3) return "等級須為 1–3";
    return "";
  }

  function openTierTemplateDetailModal(id) {
    if (!api()) return;
    var msg = document.getElementById("view-tt-detail-msg");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/tier-templates/" + encodeURIComponent(id), {
        fallbackMessage: "載入等級模板失敗"
      })
      .then(function (data) {
        var it = data && data.item;
        if (!it || it.id == null) throw new Error("無模板資料");
        var hid = document.getElementById("view-tt-detail-id");
        if (hid) hid.value = String(it.id);
        var set = function (sid, val) {
          var el = document.getElementById(sid);
          if (el) el.value = val != null ? String(val) : "";
        };
        set("view-tt-detail-code", it.template_code);
        set("view-tt-detail-display", it.display_name || "");
        set("view-tt-detail-level", it.level != null ? it.level : 1);
        set("view-tt-detail-group", it.group_code || "");
        var grp = document.getElementById("view-tt-detail-group");
        if (grp) {
          var expected = defaultTierTemplateGroupCode(it.level != null ? it.level : 1);
          grp.dataset.manualOverride = it.group_code && String(it.group_code) !== expected ? "true" : "false";
        }
        set("view-tt-detail-score", it.score != null ? it.score : 0);
        set("view-tt-detail-daily", it.daily_trade_limit != null ? it.daily_trade_limit : "");
        var me = document.getElementById("view-tt-detail-merchant");
        if (me) me.value = parseInt(String(it.merchant_enabled), 10) === 1 || it.merchant_enabled === true ? "true" : "false";
        var ver = document.getElementById("view-tt-detail-verified");
        if (ver) ver.value = parseInt(String(it.is_verified), 10) === 1 || it.is_verified === true ? "true" : "false";
        set("view-tt-detail-min-sell", it.min_sell_amount);
        set("view-tt-detail-margin", it.margin_amount);
        set("view-tt-detail-margin-ratio", it.margin_ratio);
        var rk = document.getElementById("view-tt-detail-risk");
        if (rk) rk.value = it.risk_status || "normal";
        var st = document.getElementById("view-tt-detail-status");
        if (st) st.value = it.status === "disabled" ? "disabled" : "active";
        set("view-tt-detail-sort", it.sort_order != null ? it.sort_order : 0);
        set("view-tt-detail-description", it.description || "");
        var rs = document.getElementById("view-tt-detail-reason");
        if (rs) rs.value = "";
        syncTierTemplateGroupInput(true);
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-tier-template-detail");
        }
      })
      .catch(function (e) {
        if (msg) msg.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function initTierTemplateForms() {
    if (document.documentElement._adminTtFormsBound) return;
    document.documentElement._adminTtFormsBound = true;

    document.addEventListener("click", function (e) {
      var c = e.target.closest("[data-admin-tt-open-create]");
      if (c) {
        e.preventDefault();
        resetTierTemplateCreateForm();
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-tier-template");
        }
        return;
      }
      var d = e.target.closest("[data-admin-tt-open-detail]");
      if (d) {
        e.preventDefault();
        var tid = d.getAttribute("data-admin-tt-open-detail");
        if (tid) openTierTemplateDetailModal(tid);
        return;
      }
    });

    var createForm = document.getElementById("view-tt-create-form");
    if (createForm && !createForm._adminTtBound) {
      createForm._adminTtBound = true;
      var createLevel = document.getElementById("view-tt-create-level");
      var createGroup = document.getElementById("view-tt-create-group");
      if (createGroup && !createGroup.dataset.manualOverride) createGroup.dataset.manualOverride = "false";
      if (createLevel) {
        createLevel.addEventListener("change", function () {
          syncTierTemplateGroupInput(false);
        });
      }
      if (createGroup) {
        createGroup.addEventListener("input", function () {
          createGroup.dataset.manualOverride = String(createGroup.value || "").trim() ? "true" : "false";
          syncTierTemplateGroupInput(false);
        });
      }
      syncTierTemplateGroupInput(false);
      createForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-tt-create-msg");
        if (msg) msg.textContent = "";
        var body = collectTierTemplateBody(false);
        var err = validateTierTemplateBody(body, true);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/tier-templates", {
            method: "POST",
            body: body,
            fallbackMessage: "建立等級模板失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已建立。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-create-tier-template");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "建立失敗";
          });
      });
    }

    var detailForm = document.getElementById("view-tt-detail-form");
    if (detailForm && !detailForm._adminTtDetailBound) {
      detailForm._adminTtDetailBound = true;
      var detailLevel = document.getElementById("view-tt-detail-level");
      var detailGroup = document.getElementById("view-tt-detail-group");
      if (detailGroup && !detailGroup.dataset.manualOverride) detailGroup.dataset.manualOverride = "false";
      if (detailLevel) {
        detailLevel.addEventListener("change", function () {
          syncTierTemplateGroupInput(true);
        });
      }
      if (detailGroup) {
        detailGroup.addEventListener("input", function () {
          detailGroup.dataset.manualOverride = String(detailGroup.value || "").trim() ? "true" : "false";
          syncTierTemplateGroupInput(true);
        });
      }
      detailForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-tt-detail-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-tt-detail-id");
        var id = hid ? String(hid.value || "").trim() : "";
        if (!id) {
          if (msg) msg.textContent = "缺少模板 ID。";
          return;
        }
        var body = collectTierTemplateBody(true);
        var err = validateTierTemplateBody(body, false);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/tier-templates/" + encodeURIComponent(id), {
            method: "PATCH",
            body: body,
            fallbackMessage: "更新等級模板失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已儲存。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-tier-template-detail");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "儲存失敗";
          });
      });
    }
  }

  function resetTutorialLinkCreateForm() {
    ["view-tl-create-reason", "view-tl-create-title", "view-tl-create-subtitle", "view-tl-create-link-url"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = "";
    });
    var sort = document.getElementById("view-tl-create-sort");
    if (sort) sort.value = "0";
    var st = document.getElementById("view-tl-create-status");
    if (st) st.value = "active";
    var msg = document.getElementById("view-tl-create-msg");
    if (msg) msg.textContent = "";
  }

  function collectTutorialLinkBody(isDetail) {
    var pfx = isDetail ? "detail" : "create";
    return {
      reason: String((document.getElementById("view-tl-" + pfx + "-reason") || {}).value || "").trim(),
      title: String((document.getElementById("view-tl-" + pfx + "-title") || {}).value || "").trim(),
      subtitle: String((document.getElementById("view-tl-" + pfx + "-subtitle") || {}).value || "").trim(),
      link_url: String((document.getElementById("view-tl-" + pfx + "-link-url") || {}).value || "").trim(),
      sort_order: parseInt(String((document.getElementById("view-tl-" + pfx + "-sort") || {}).value || "0"), 10) || 0,
      status: (document.getElementById("view-tl-" + pfx + "-status") || {}).value || "active"
    };
  }

  function validateTutorialLinkBody(body, requireReason) {
    if (requireReason && !body.reason) return "請填寫異動原因";
    if (!body.title) return "請填寫標題";
    if (!body.link_url) return "請填寫連結網址";
    return "";
  }

  function openTutorialLinkDetailModal(id) {
    if (!api()) return;
    var msg = document.getElementById("view-tl-detail-msg");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/home-content/tutorial-links/" + encodeURIComponent(id), {
        fallbackMessage: "載入教學連結失敗"
      })
      .then(function (data) {
        var it = data && data.item;
        if (!it || it.id == null) throw new Error("無資料");
        var hid = document.getElementById("view-tl-detail-id");
        if (hid) hid.value = String(it.id);
        var reason = document.getElementById("view-tl-detail-reason");
        if (reason) reason.value = "";
        var title = document.getElementById("view-tl-detail-title");
        if (title) title.value = it.title != null ? String(it.title) : "";
        var sub = document.getElementById("view-tl-detail-subtitle");
        if (sub) sub.value = it.subtitle != null ? String(it.subtitle) : "";
        var link = document.getElementById("view-tl-detail-link-url");
        if (link) link.value = it.link_url != null ? String(it.link_url) : "";
        var sort = document.getElementById("view-tl-detail-sort");
        if (sort) sort.value = it.sort_order != null ? String(it.sort_order) : "0";
        var st = document.getElementById("view-tl-detail-status");
        if (st) st.value = it.status === "inactive" ? "inactive" : "active";
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-tutorial-link-detail");
        }
      })
      .catch(function (e) {
        if (msg) msg.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function initTutorialLinkForms() {
    if (document.documentElement._adminTlFormsBound) return;
    document.documentElement._adminTlFormsBound = true;

    document.addEventListener("click", function (e) {
      var c = e.target.closest("[data-admin-tl-open-create]");
      if (c) {
        e.preventDefault();
        resetTutorialLinkCreateForm();
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-tutorial-link");
        }
        return;
      }
      var d = e.target.closest("[data-admin-tl-open-detail]");
      if (d) {
        e.preventDefault();
        var lid = d.getAttribute("data-admin-tl-open-detail");
        if (lid) openTutorialLinkDetailModal(lid);
      }
    });

    var createForm = document.getElementById("view-tl-create-form");
    if (createForm && !createForm._adminTlBound) {
      createForm._adminTlBound = true;
      createForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-tl-create-msg");
        if (msg) msg.textContent = "";
        var body = collectTutorialLinkBody(false);
        var err = validateTutorialLinkBody(body, true);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/home-content/tutorial-links", {
            method: "POST",
            body: body,
            fallbackMessage: "建立教學連結失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已建立。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-create-tutorial-link");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "建立失敗";
          });
      });
    }

    var detailForm = document.getElementById("view-tl-detail-form");
    if (detailForm && !detailForm._adminTlDetailBound) {
      detailForm._adminTlDetailBound = true;
      detailForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-tl-detail-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-tl-detail-id");
        var id = hid ? String(hid.value || "").trim() : "";
        if (!id) {
          if (msg) msg.textContent = "缺少 ID。";
          return;
        }
        var body = collectTutorialLinkBody(true);
        var err = validateTutorialLinkBody(body, true);
        if (err) {
          if (msg) msg.textContent = err;
          return;
        }
        api()
          .requestJson("/api/admin/home-content/tutorial-links/" + encodeURIComponent(id), {
            method: "PATCH",
            body: body,
            fallbackMessage: "更新教學連結失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已儲存。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-tutorial-link-detail");
              if (m) window.AdminModals.close(m);
            }
            reloadCurrentView();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "儲存失敗";
          });
      });
    }
  }

  function dashYmdLocal(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, "0");
    var day = String(d.getDate()).padStart(2, "0");
    return y + "-" + m + "-" + day;
  }

  function dashStartOfWeekMonday(d) {
    var x = new Date(d.getTime());
    var day = x.getDay();
    var diff = day === 0 ? -6 : 1 - day;
    x.setDate(x.getDate() + diff);
    x.setHours(0, 0, 0, 0);
    return x;
  }

  function dashStartOfMonth(d) {
    return new Date(d.getFullYear(), d.getMonth(), 1);
  }

  function setDashSegmentActive(btn) {
    document.querySelectorAll("[data-dash-range]").forEach(function (b) {
      b.classList.toggle("is-active", b === btn);
      b.setAttribute("aria-selected", b === btn ? "true" : "false");
    });
  }

  function bindDashboardFundRangeOnce() {
    var root = document.getElementById("dashboard-fund-totals");
    if (!root || root.dataset.dashFundBound === "1") return;
    root.dataset.dashFundBound = "1";
    var startEl = document.getElementById("dash-date-start");
    var endEl = document.getElementById("dash-date-end");
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    if (startEl && !startEl.value) startEl.value = dashYmdLocal(today);
    if (endEl && !endEl.value) endEl.value = dashYmdLocal(today);
    document.querySelectorAll("[data-dash-range]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        setDashSegmentActive(btn);
        var mode = btn.getAttribute("data-dash-range");
        var end = new Date();
        end.setHours(0, 0, 0, 0);
        var start;
        if (mode === "week") {
          start = dashStartOfWeekMonday(end);
        } else if (mode === "month") {
          start = dashStartOfMonth(end);
        } else {
          start = new Date(end.getTime());
        }
        if (startEl) startEl.value = dashYmdLocal(start);
        if (endEl) endEl.value = dashYmdLocal(end);
        fetchDashboardSummary();
      });
    });
    var applyBtn = document.getElementById("dash-date-apply");
    if (applyBtn) {
      applyBtn.addEventListener("click", function () {
        document.querySelectorAll("[data-dash-range]").forEach(function (b) {
          b.classList.remove("is-active");
          b.setAttribute("aria-selected", "false");
        });
        fetchDashboardSummary();
      });
    }
  }

  function fetchDashboardSummary() {
    if (!api()) return Promise.resolve();
    var sEl = document.getElementById("dash-date-start");
    var eEl = document.getElementById("dash-date-end");
    var start = sEl && sEl.value ? String(sEl.value).trim() : "";
    var end = eEl && eEl.value ? String(eEl.value).trim() : "";
    var qs = new URLSearchParams();
    if (start) qs.set("start", start);
    if (end) qs.set("end", end);
    var q = qs.toString();
    return api()
      .requestJson("/api/admin/dashboard-summary" + (q ? "?" + q : ""), { fallbackMessage: "載入儀表統計失敗" })
      .then(function (data) {
        var kpi = (data && data.kpi) || {};
        var fund = (data && data.fund_totals) || {};
        var range = (data && data.range) || {};
        function setKpiVal(id, v) {
          var el = document.getElementById(id);
          if (!el) return;
          var n = Number(v);
          el.textContent = isFinite(n) ? n.toLocaleString("zh-TW") : String(v != null ? v : "—");
        }
        function deltaLine(todayN, yN, a, b) {
          var t = Number(todayN);
          var y = Number(yN);
          if (!isFinite(t)) t = 0;
          if (!isFinite(y)) y = 0;
          return a + " " + t + " · " + b + " " + y;
        }
        setKpiVal("dash-kpi-pending-deposits", kpi.pending_deposits);
        setKpiVal("dash-kpi-pending-withdrawals", kpi.pending_withdrawals);
        setKpiVal("dash-kpi-trading-orders", kpi.trading_orders != null ? kpi.trading_orders : kpi.disputed_orders);
        setKpiVal("dash-kpi-pending-kyc", kpi.pending_kyc);
        var nd = document.getElementById("dash-kpi-pending-deposits-note");
        if (nd) {
          nd.textContent = deltaLine(
            kpi.deposit_pending_new_today,
            kpi.deposit_pending_new_yesterday,
            "本日新進待審",
            "昨日新進"
          );
        }
        var nw = document.getElementById("dash-kpi-pending-withdrawals-note");
        if (nw) {
          nw.textContent = deltaLine(
            kpi.withdrawal_pending_new_today,
            kpi.withdrawal_pending_new_yesterday,
            "本日新進待審",
            "昨日新進"
          );
        }
        var depStr = String(fund.deposit_usdt_equiv != null ? fund.deposit_usdt_equiv : "0");
        var wdrStr = String(fund.withdraw_usdt_equiv != null ? fund.withdraw_usdt_equiv : "0");
        var td = document.getElementById("dash-total-deposit");
        if (td) td.textContent = depStr;
        var tw = document.getElementById("dash-total-withdraw");
        if (tw) tw.textContent = wdrStr;
        var desc = document.getElementById("dash-range-desc");
        if (desc && range.start && range.end) {
          desc.textContent =
            "統計區間：" +
            range.start +
            " — " +
            range.end;
        }
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        ["dash-kpi-pending-deposits", "dash-kpi-pending-withdrawals", "dash-kpi-trading-orders", "dash-kpi-pending-kyc"].forEach(
          function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = "—";
          }
        );
        ["dash-total-deposit", "dash-total-withdraw"].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.textContent = "—";
        });
        var nd = document.getElementById("dash-kpi-pending-deposits-note");
        if (nd) nd.textContent = (e && e.message) || "載入失敗";
        var nw = document.getElementById("dash-kpi-pending-withdrawals-note");
        if (nw) nw.textContent = "";
      });
  }

  function loadViewDashboard() {
    bindDashboardFundRangeOnce();
    return fetchDashboardSummary();
  }

  function loadRecentAdminOps(tbodyId) {
    return loadItems(
      "/api/admin/auth/events?page=1&page_size=12",
      tbodyId,
      4,
      function (it) {
        var errCell = it.error_code
          ? authErrorCodeLabelZh(it.error_code)
          : it.msg
            ? String(it.msg)
            : "—";
        return (
          "<tr><td class=\"mono\">" +
          esc(it.created_at || "") +
          "</td><td>" +
          esc(it.account || "—") +
          "</td><td>" +
          esc(authEventActionLabelZh(it.action)) +
          "</td><td class=\"mono\">" +
          esc(errCell) +
          "</td></tr>"
        );
      },
      "尚無最近操作紀錄"
    );
  }

  function loadViewUsers() {
    if (window.AdminModals && typeof window.AdminModals.reloadUsersTable === "function") {
      return window.AdminModals.reloadUsersTable();
    }
    return Promise.resolve();
  }

  /** 錢包列彙總：USDT / EUR / 其他（依 currency_code，缺省則由 wallet_code 推斷） */
  function walletBalanceCurrencyGroup(w) {
    var c = String(w.currency_code || "").trim().toUpperCase();
    if (c === "USDT") return "USDT";
    if (c === "EUR") return "EUR";
    var k = String(w.wallet_code || "").toLowerCase();
    if (k.indexOf("usdt") !== -1 || k === "cash_usdt" || k === "spot_usdt") return "USDT";
    if (k === "eur" || k.indexOf("eur") !== -1) return "EUR";
    return "OTHER";
  }

  function aggregateUserWalletBalances(items, assetFilter) {
    var f = String(assetFilter || "").trim().toUpperCase();
    var rows = items || [];
    if (f === "USDT") rows = rows.filter(function (w) { return walletBalanceCurrencyGroup(w) === "USDT"; });
    else if (f === "EUR") rows = rows.filter(function (w) { return walletBalanceCurrencyGroup(w) === "EUR"; });
    var usdtAv = 0;
    var usdtRs = 0;
    var eurAv = 0;
    var eurRs = 0;
    var othAv = 0;
    var othRs = 0;
    var maxUpd = "";
    rows.forEach(function (w) {
      var g = walletBalanceCurrencyGroup(w);
      var av = Number(String(w.available_balance != null ? w.available_balance : "").replace(/,/g, ""));
      var rs = Number(String(w.reserved_balance != null ? w.reserved_balance : "").replace(/,/g, ""));
      if (!isFinite(av)) av = 0;
      if (!isFinite(rs)) rs = 0;
      if (g === "USDT") {
        usdtAv += av;
        usdtRs += rs;
      } else if (g === "EUR") {
        eurAv += av;
        eurRs += rs;
      } else {
        othAv += av;
        othRs += rs;
      }
      var u = w.updated_at || w.created_at || "";
      if (u && (!maxUpd || String(u) > String(maxUpd))) maxUpd = String(u);
    });
    var hasOtherWallet = (items || []).some(function (w) {
      return walletBalanceCurrencyGroup(w) === "OTHER";
    });
    return {
      usdtAv: usdtAv,
      usdtRs: usdtRs,
      eurAv: eurAv,
      eurRs: eurRs,
      othAv: othAv,
      othRs: othRs,
      maxUpd: maxUpd || "—",
      hasOtherWallet: hasOtherWallet,
      assetFilter: f
    };
  }

  function parseWalletBalanceField(v) {
    var n = Number(String(v != null ? v : "").replace(/,/g, ""));
    return isFinite(n) ? n : 0;
  }

  /** 成員列表 API 一列之 USDT/EUR 可用、凍結彙總（無「其他」資產明細） */
  function aggregateWalletBalancesFromUserListItem(it, assetFilter) {
    var f = String(assetFilter || "").trim().toUpperCase();
    return {
      usdtAv: parseWalletBalanceField(it.usdt_available_balance),
      usdtRs: parseWalletBalanceField(it.usdt_reserved_balance),
      eurAv: parseWalletBalanceField(it.eur_available_balance),
      eurRs: parseWalletBalanceField(it.eur_reserved_balance),
      othAv: 0,
      othRs: 0,
      maxUpd: it.wallets_max_updated_at || "—",
      hasOtherWallet: false,
      assetFilter: f
    };
  }

  function renderWalletSummaryTableRow(userIdLabel, user, internalUid, agg) {
    var f = agg.assetFilter || "";
    var dash = "—";
    var userCell = renderAdminUserSummaryCell(user, internalUid);
    var cellPair = function (skip, av, rs) {
      if (f === skip) return { a: dash, b: dash };
      return { a: formatListingPriceDisplay(av), b: formatListingPriceDisplay(rs) };
    };
    var usdtCells = cellPair("EUR", agg.usdtAv, agg.usdtRs);
    var eurCells = cellPair("USDT", agg.eurAv, agg.eurRs);
    var otherCell;
    if (f === "USDT" || f === "EUR") {
      otherCell = dash;
    } else if (!agg.hasOtherWallet && agg.othAv === 0 && agg.othRs === 0) {
      otherCell = dash;
    } else {
      otherCell = formatListingPriceDisplay(agg.othAv) + " / " + formatListingPriceDisplay(agg.othRs);
    }
    return (
      '<tr data-admin-wallet-user-id="' +
      esc(String(internalUid || "")) +
      '"><td class="mono">' +
      esc(String(userIdLabel)) +
      "</td>" +
      userCell +
      '<td class="mono">' +
      esc(usdtCells.a) +
      '</td><td class="mono">' +
      esc(usdtCells.b) +
      '</td><td class="mono">' +
      esc(eurCells.a) +
      '</td><td class="mono">' +
      esc(eurCells.b) +
      '</td><td class="mono">' +
      esc(otherCell) +
      '</td><td class="mono">' +
      esc(agg.maxUpd) +
      "</td><td>" +
      adminRowActions(
        adminBtn({
          variant: "accent",
          sm: true,
          attrs: 'data-admin-modal-open="admin-modal-wallet-adjust" data-admin-wallet-adjust="' + esc(String(internalUid || "")) + '"',
          label: "調帳"
        }) +
        adminBtn({
          variant: "info",
          sm: true,
          attrs: 'data-admin-wallet-ledger="' + esc(String(internalUid || "")) + '"',
          label: "流水"
        })
      ) +
      "</td></tr>"
    );
  }

  function walletLedgerTypeLabel(type) {
    return zhEnumLookup(type, {
      admin_adjustment: "後台調帳",
      deposit_approved: "充值入帳",
      withdrawal_requested: "提現凍結",
      withdrawal_rejected: "提現退回",
      withdrawal_approved: "提現出帳",
      c2c_order: "C2C 訂單",
      financial_subscription: "理財認購",
      financial_return: "理財返還",
      wallet_delta: "錢包變動"
    });
  }

  function openWalletLedgerForUser(userId) {
    var normalizedId = String(userId || "").trim();
    if (!normalizedId) {
      var input = document.getElementById("view-wallets-user-id");
      normalizedId = input && input.value ? String(input.value).trim() : "";
    }
    if (!normalizedId || !/^\d+$/.test(normalizedId)) {
      adminNotifyError("請先填寫有效的用戶 ID，或從資金錢包列表列內點「流水」。");
      return;
    }
    showModalById("admin-modal-wallet-ledger");
    var target = document.getElementById("view-wallet-ledger-target");
    if (target) target.textContent = "用戶 ID：" + userOrdinalId({ id: normalizedId }, normalizedId) + "（後端 ID " + normalizedId + "）";
    setTbodyLoading("view-wallet-ledger-tbody", 8);
    var assetEl = document.getElementById("view-wallets-asset");
    var asset = assetEl && assetEl.value ? String(assetEl.value).trim().toUpperCase() : "";
    var qs = new URLSearchParams();
    qs.set("page", "1");
    qs.set("page_size", "50");
    if (asset === "USDT") qs.set("wallet_code", "cash_usdt");
    if (asset === "EUR") qs.set("wallet_code", "eur");
    api()
      .requestJson("/api/admin/users/" + encodeURIComponent(normalizedId) + "/wallets/ledger?" + qs.toString(), {
        fallbackMessage: "載入調帳紀錄失敗"
      })
      .then(function (data) {
        var items = (data && data.items) || [];
        var tb = document.getElementById("view-wallet-ledger-tbody");
        if (!tb) return;
        if (!items.length) {
          tb.innerHTML = '<tr><td colspan="8" class="admin-muted">尚無調帳或資金流水。</td></tr>';
          return;
        }
        tb.innerHTML = items
          .map(function (it) {
            return (
              '<tr><td class="mono">' +
              esc(it.id) +
              '</td><td class="mono">' +
              esc(assetLabelZh(it.currency_code || it.wallet_code)) +
              '</td><td>' +
              esc(walletLedgerTypeLabel(it.change_type)) +
              '</td><td class="mono">' +
              esc(formatListingPriceDisplay(it.available_delta)) +
              '</td><td class="mono">' +
              esc(formatListingPriceDisplay(it.reserved_delta)) +
              '</td><td class="mono">' +
              esc(formatListingPriceDisplay(it.available_after)) +
              " / " +
              esc(formatListingPriceDisplay(it.reserved_after)) +
              "</td><td>" +
              esc(it.reason || it.source_type || "—") +
              '</td><td class="mono">' +
              esc(it.created_at || "—") +
              "</td></tr>"
            );
          })
          .join("");
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setTbodyError("view-wallet-ledger-tbody", e.message || "載入失敗", 8);
      });
  }

  function walletCodeLabel(walletCode) {
    return walletCode === "eur" ? "EUR" : "USDT";
  }

  function setWalletAdjustAsset(walletCode) {
    var normalized = walletCode === "eur" ? "eur" : "cash_usdt";
    var hidden = document.getElementById("view-wallet-adjust-wallet-code");
    if (hidden) hidden.value = normalized;
    document.querySelectorAll("[data-wallet-adjust-asset]").forEach(function (btn) {
      var active = btn.getAttribute("data-wallet-adjust-asset") === normalized;
      btn.classList.toggle("admin-btn--primary", active);
      btn.classList.toggle("admin-btn--ghost", !active);
      btn.textContent = walletCodeLabel(btn.getAttribute("data-wallet-adjust-asset")) + (active ? "（已選）" : "");
      btn.setAttribute("aria-pressed", active ? "true" : "false");
    });
  }

  function openWalletAdjustForUser(userId) {
    var normalizedId = String(userId || "").trim();
    if (!normalizedId) {
      var input = document.getElementById("view-wallets-user-id");
      normalizedId = input && input.value ? String(input.value).trim() : "";
    }
    var target = document.getElementById("view-wallet-adjust-target");
    var hidden = document.getElementById("view-wallet-adjust-user-id");
    var amount = document.getElementById("view-wallet-adjust-amount");
    var reason = document.getElementById("view-wallet-adjust-reason");
    var msg = document.getElementById("view-wallet-adjust-msg");
    if (hidden) hidden.value = normalizedId;
    if (amount) amount.value = "";
    if (reason) reason.value = "";
    if (msg) msg.textContent = "";
    setWalletAdjustAsset("cash_usdt");
    if (target) {
      target.textContent = normalizedId
        ? "目前調整用戶 ID：" + userOrdinalId({ id: normalizedId }, normalizedId) + "（後端 ID " + normalizedId + "）"
        : "尚未指定用戶。請從列表列內點「調帳」，或先在資金錢包頁填寫用戶 ID。";
    }
  }

  function bindWalletAdjustModal() {
    var form = document.getElementById("view-wallet-adjust-form");
    if (form && !form._adminWalletAdjustBound) {
      form._adminWalletAdjustBound = true;
      form.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-wallet-adjust-msg");
        var userId = String((document.getElementById("view-wallet-adjust-user-id") || {}).value || "").trim();
        var walletCode = String((document.getElementById("view-wallet-adjust-wallet-code") || {}).value || "cash_usdt").trim();
        var operation = String((document.getElementById("view-wallet-adjust-operation") || {}).value || "").trim();
        var amount = String((document.getElementById("view-wallet-adjust-amount") || {}).value || "").trim();
        var reason = String((document.getElementById("view-wallet-adjust-reason") || {}).value || "").trim();
        if (!userId || !/^\d+$/.test(userId)) {
          if (msg) msg.textContent = "請先指定有效的用戶 ID。";
          return;
        }
        if (!amount || Number(amount) <= 0) {
          if (msg) msg.textContent = "請輸入大於 0 的金額。";
          return;
        }
        if (msg) msg.textContent = "更新中…";
        api()
          .requestJson("/api/admin/users/" + encodeURIComponent(userId) + "/wallets/" + encodeURIComponent(walletCode), {
            method: "PATCH",
            body: { operation: operation, amount: amount, reason: reason },
            fallbackMessage: "更新錢包失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已更新錢包。";
            if (window.AdminModals && window.AdminModals.close) {
              var modal = document.getElementById("admin-modal-wallet-adjust");
              if (modal) window.AdminModals.close(modal);
            }
            loadViewWallets();
          })
          .catch(function (e) {
            if (msg) msg.textContent = e && e.message ? e.message : "更新錢包失敗";
          });
      });
    }
    document.querySelectorAll("[data-wallet-adjust-asset]").forEach(function (btn) {
      if (btn._adminWalletAssetBound) return;
      btn._adminWalletAssetBound = true;
      btn.addEventListener("click", function () {
        setWalletAdjustAsset(btn.getAttribute("data-wallet-adjust-asset"));
      });
    });
  }

  function loadViewWallets() {
    var uidEl = document.getElementById("view-wallets-user-id");
    var uid = uidEl && uidEl.value.trim() ? uidEl.value.trim() : "";
    var assetEl = document.getElementById("view-wallets-asset");
    var assetF = assetEl && assetEl.value ? String(assetEl.value).trim() : "";
    var kwEl = document.getElementById("view-wallets-keyword");
    var keyword = kwEl && kwEl.value.trim() ? kwEl.value.trim() : "";
    setTbodyLoading("view-wallets-tbody", 9);

    if (!uid) {
      var qs = new URLSearchParams();
      var pager = adminPagerGet("view-wallets-tbody");
      qs.set("page", String(pager.page || 1));
      qs.set("page_size", String(pager.pageSize || ADMIN_PAGE_SIZE_DEFAULT));
      if (keyword) qs.set("keyword", keyword);
      return api()
        .requestJson("/api/admin/users?" + qs.toString(), { fallbackMessage: "載入成員錢包彙總失敗" })
        .then(function (data) {
          var items = (data && data.items) || [];
          var tb = document.getElementById("view-wallets-tbody");
          if (!tb) return;
          if (!items.length) {
            tb.innerHTML =
              '<tr><td colspan="9" class="admin-muted">目前沒有成員，或關鍵字篩選結果為空。</td></tr>';
            adminPagerRender("view-wallets-tbody", (data && data.pagination) || { page: pager.page, page_size: pager.pageSize, total: 0 }, loadViewWallets);
            return;
          }
          tb.innerHTML = items
            .map(function (it, idx) {
              var agg = aggregateWalletBalancesFromUserListItem(it, assetF);
              var internalUid = it.id != null ? it.id : idx;
              return renderWalletSummaryTableRow(userOrdinalId(it, internalUid), it, internalUid, agg);
            })
            .join("");
          adminPagerRender("view-wallets-tbody", data && data.pagination, loadViewWallets);
        })
        .catch(function (e) {
          if (e && e.adminSessionHandled) return;
          setTbodyError("view-wallets-tbody", e.message, 9);
        });
    }

    var path = "/api/admin/users/" + encodeURIComponent(uid) + "/wallets";
    var userPath = "/api/admin/users/" + encodeURIComponent(uid);
    return api()
      .requestJson(path, { fallbackMessage: "載入錢包失敗" })
      .then(function (data) {
        var wallets = (data && data.items) || [];
        return api()
          .requestJson(userPath, { fallbackMessage: "" })
          .then(function (ud) {
            return { wallets: wallets, user: ud && ud.user ? ud.user : null };
          })
          .catch(function () {
            return { wallets: wallets, user: null };
          });
      })
      .then(function (pack) {
        var wallets = pack.wallets;
        var user = pack.user;
        var tb = document.getElementById("view-wallets-tbody");
        if (!tb) return;
        var agg = aggregateUserWalletBalances(wallets, assetF);
        var internalUid = user && user.id != null ? user.id : uid;
        tb.innerHTML = renderWalletSummaryTableRow(userOrdinalId(user, internalUid), user, internalUid, agg);
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setTbodyError("view-wallets-tbody", e.message, 9);
      });
  }

  function tierLevelLabel(level) {
    var raw = String(level == null || level === "" ? "1" : level).trim();
    return /^lv/i.test(raw) ? raw.toUpperCase() : "LV" + raw;
  }

  function renderTierUserTableHeader() {
    return (
      '<thead><tr>' +
      "<th>ID</th><th>UID / 帳號</th><th>等級</th><th>信用分</th><th>日交易上限</th><th>最低賣出</th><th>保證金</th><th>保證金比例</th><th>風控狀態</th><th>操作</th>" +
      "</tr></thead>"
    );
  }

  function tierRiskStatusZh(raw) {
    var status = String(raw || "").toLowerCase();
    if (status === "warning") return "警示";
    if (status === "blocked") return "封鎖";
    return "正常";
  }

  function renderTierUserTableRow(user, profile) {
    var code = adminUserDisplayCode(user, user && user.id);
    var daily = profile.daily_trade_limit == null ? "—" : profile.daily_trade_limit + " 筆";
    return (
      '<tr data-admin-tier-user-id="' +
      esc(user && user.id) +
      '">' +
      '<td class="mono">' +
      esc(userOrdinalId(user, user && user.id)) +
      "</td>" +
      renderAdminUserSummaryCell(user, user && user.id) +
      '<td class="mono">' +
      esc(tierLevelLabel(profile.level)) +
      '</td><td class="mono">' +
      esc(profile.score != null ? profile.score : "30") +
      '</td><td class="mono">' +
      esc(daily) +
      '</td><td class="mono">' +
      esc(profile.min_sell_amount != null ? profile.min_sell_amount : "—") +
      '</td><td class="mono">' +
      esc(profile.margin_amount != null ? profile.margin_amount : "—") +
      '</td><td class="mono">' +
      esc(profile.margin_ratio != null ? profile.margin_ratio : "—") +
      '</td><td class="mono">' +
      esc(tierRiskStatusZh(profile.risk_status || "normal")) +
      "</td><td>" +
      adminRowActions(
        adminBtn({
          variant: "accent",
          sm: true,
          attrs: 'data-admin-tier-edit="' + esc(String(user && user.id)) + '"',
          label: "編輯"
        })
      ) +
      "</td></tr>"
    );
  }

  function renderTierUserTable(rows) {
    return '<table class="admin-table">' + renderTierUserTableHeader() + "<tbody>" + rows.join("") + "</tbody></table>";
  }

  function tierProfileForUser(user) {
    if (user && user.tier_profile) return user.tier_profile;
    if (user && user.tier) return user.tier;
    return {
      level: user && user.tier_level != null ? user.tier_level : 1,
      group_code: user && user.tier_group_code ? user.tier_group_code : "basic",
      score: user && user.credit_score != null ? user.credit_score : 30,
      daily_trade_limit: user && user.daily_trade_limit != null ? user.daily_trade_limit : null,
      min_sell_amount: user && user.min_sell_amount != null ? user.min_sell_amount : "—",
      margin_amount: user && user.margin_amount != null ? user.margin_amount : "—",
      margin_ratio: user && user.margin_ratio != null ? user.margin_ratio : "—",
      risk_status: user && user.risk_status ? user.risk_status : "normal"
    };
  }

  function loadTierUserProfile() {
    var root = document.getElementById("view-tier-user-result");
    if (!root) return Promise.resolve();
    var input = document.getElementById("view-tier-user-keyword");
    var keyword = input && input.value.trim() ? input.value.trim() : "";
    root.innerHTML = '<table class="admin-table"><tbody><tr><td colspan="10" class="admin-muted">載入中…</td></tr></tbody></table>';

    var q = new URLSearchParams();
    q.set("page", "1");
    q.set("page_size", keyword ? "1" : "100");
    if (keyword) q.set(/^\d+$/.test(keyword) ? "user_id" : "keyword", keyword);

    return api()
      .requestJson("/api/admin/users?" + q.toString(), { fallbackMessage: "查詢用戶失敗" })
      .then(function (data) {
        var users = (data && data.items) || [];
        if (!users.length) {
          root.innerHTML = '<table class="admin-table"><tbody><tr><td colspan="10" class="admin-muted">查無用戶，請確認 ID / UID / Email / 手機是否正確。</td></tr></tbody></table>';
          return null;
        }
        var jobs = users.map(function (user) {
          return api()
            .requestJson("/api/admin/users/" + encodeURIComponent(user.id) + "/tier-profile", {
              fallbackMessage: "載入用戶等級失敗"
            })
            .then(function (profileData) {
              return renderTierUserTableRow(user, (profileData && profileData.tier_profile) || tierProfileForUser(user));
            })
            .catch(function () {
              return renderTierUserTableRow(user, tierProfileForUser(user));
            });
        });
        return Promise.all(jobs).then(function (rows) {
          root.innerHTML = renderTierUserTable(rows);
        });
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        root.innerHTML = '<table class="admin-table"><tbody><tr><td colspan="10" class="admin-muted">' + esc(e.message || "載入用戶等級失敗") + "</td></tr></tbody></table>";
      });
  }

  function setTierUserEditValue(id, value) {
    var el = document.getElementById(id);
    if (el) el.value = value == null ? "" : String(value);
  }

  function openTierUserEditModal(userId) {
    if (!api()) return;
    var msg = document.getElementById("view-tier-user-edit-msg");
    if (msg) msg.textContent = "載入中…";
    Promise.all([
      api().requestJson("/api/admin/users/" + encodeURIComponent(userId), { fallbackMessage: "載入用戶資料失敗" }),
      api().requestJson("/api/admin/users/" + encodeURIComponent(userId) + "/tier-profile", { fallbackMessage: "載入用戶等級失敗" })
    ])
      .then(function (packs) {
        var user = packs[0] && packs[0].user ? packs[0].user : { id: userId };
        var profile = (packs[1] && packs[1].tier_profile) || {};
        var code = adminUserDisplayCode(user, userId);
        setTierUserEditValue("view-tier-user-edit-id", userId);
        setTierUserEditValue("view-tier-user-edit-user", userOrdinalId(user, userId) + " / " + code);
        setTierUserEditValue("view-tier-user-edit-level", profile.level != null ? profile.level : 1);
        setTierUserEditValue("view-tier-user-edit-group", profile.group_code || "basic");
        setTierUserEditValue("view-tier-user-edit-score", profile.score != null ? profile.score : 30);
        setTierUserEditValue("view-tier-user-edit-daily", profile.daily_trade_limit == null ? "—" : profile.daily_trade_limit + " 筆");
        setTierUserEditValue("view-tier-user-edit-min-sell", profile.min_sell_amount != null ? profile.min_sell_amount : "0");
        setTierUserEditValue("view-tier-user-edit-margin", profile.margin_amount != null ? profile.margin_amount : "0");
        setTierUserEditValue("view-tier-user-edit-margin-ratio", profile.margin_ratio != null ? profile.margin_ratio : "0");
        var merchant = document.getElementById("view-tier-user-edit-merchant");
        if (merchant) merchant.value = profile.merchant_enabled ? "true" : "false";
        var verified = document.getElementById("view-tier-user-edit-verified");
        if (verified) verified.value = profile.is_verified ? "true" : "false";
        setTierUserEditValue("view-tier-user-edit-risk", profile.risk_status || "normal");
        setTierUserEditValue("view-tier-user-edit-reason", "");
        setTierUserEditValue("view-tier-user-edit-violation", profile.violation_message || "");
        if (msg) msg.textContent = "";
        if (window.AdminModals && window.AdminModals.open) window.AdminModals.open("admin-modal-tier-adjust");
      })
      .catch(function (e) {
        if (msg) msg.textContent = e && e.message ? e.message : "載入用戶等級失敗";
        if (window.showAdminAlertDialog) {
          window.showAdminAlertDialog({ title: "載入失敗", message: msg ? msg.textContent : "載入用戶等級失敗" });
        }
      });
  }

  function bindTierUserEditForm() {
    var form = document.getElementById("view-tier-user-edit-form");
    if (!form || form._adminTierUserEditBound) return;
    form._adminTierUserEditBound = true;
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var msg = document.getElementById("view-tier-user-edit-msg");
      var id = String((document.getElementById("view-tier-user-edit-id") || {}).value || "").trim();
      if (!id) {
        if (msg) msg.textContent = "缺少用戶 ID。";
        return;
      }
      var body = {
        level: Number((document.getElementById("view-tier-user-edit-level") || {}).value || 1),
        group_code: String((document.getElementById("view-tier-user-edit-group") || {}).value || "").trim(),
        score: Number((document.getElementById("view-tier-user-edit-score") || {}).value || 0),
        min_sell_amount: String((document.getElementById("view-tier-user-edit-min-sell") || {}).value || "0").trim(),
        margin_amount: String((document.getElementById("view-tier-user-edit-margin") || {}).value || "0").trim(),
        margin_ratio: String((document.getElementById("view-tier-user-edit-margin-ratio") || {}).value || "0").trim(),
        merchant_enabled: (document.getElementById("view-tier-user-edit-merchant") || {}).value === "true",
        is_verified: (document.getElementById("view-tier-user-edit-verified") || {}).value === "true",
        risk_status: String((document.getElementById("view-tier-user-edit-risk") || {}).value || "normal").trim(),
        violation_message: String((document.getElementById("view-tier-user-edit-violation") || {}).value || "").trim(),
        reason: String((document.getElementById("view-tier-user-edit-reason") || {}).value || "").trim()
      };
      if (msg) msg.textContent = "儲存中…";
      api()
        .requestJson("/api/admin/users/" + encodeURIComponent(id) + "/tier-profile", {
          method: "PATCH",
          body: body,
          fallbackMessage: "儲存用戶等級失敗"
        })
        .then(function () {
          if (msg) msg.textContent = "已儲存。";
          if (window.AdminModals && window.AdminModals.close) {
            var modal = document.getElementById("admin-modal-tier-adjust");
            if (modal) window.AdminModals.close(modal);
          }
          loadTierUserProfile();
        })
        .catch(function (e) {
          if (msg) msg.textContent = e && e.message ? e.message : "儲存用戶等級失敗";
        });
    });
  }

  function bindTierUserPanel() {
    var root = document.getElementById("view-tiers");
    if (!root || root.dataset.tierUserBound === "1") return;
    root.dataset.tierUserBound = "1";
    var input = document.getElementById("view-tier-user-keyword");
    var loadBtn = document.getElementById("view-tier-user-load");
    var pageRefreshBtn = document.getElementById("view-tiers-refresh");
    var refreshBtn = document.getElementById("view-tier-user-refresh");
    var resetBtn = document.getElementById("view-tier-user-reset");
    if (loadBtn) loadBtn.addEventListener("click", loadTierUserProfile);
    if (pageRefreshBtn) pageRefreshBtn.addEventListener("click", function () { loadViewTiers(); });
    if (refreshBtn) refreshBtn.addEventListener("click", loadTierUserProfile);
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        if (input) input.value = "";
        loadTierUserProfile();
      });
    }
    if (input) {
      input.addEventListener("keydown", function (e) {
        if (e.key === "Enter") loadTierUserProfile();
      });
    }
    bindTierUserEditForm();
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-admin-tier-edit]");
      if (!btn) return;
      e.preventDefault();
      openTierUserEditModal(btn.getAttribute("data-admin-tier-edit"));
    });
  }

  function loadViewTiers() {
    bindTierUserPanel();
    bindSimpleFilterControls("view-tiers", {
      query: "view-tier-template-query",
      refresh: "view-tier-template-refresh",
      reset: "view-tier-template-reset",
      inputs: ["view-tier-template-keyword"]
    }, loadViewTiers);
    var ttKeywordEl = document.getElementById("view-tier-template-keyword");
    var ttKeyword = ttKeywordEl && ttKeywordEl.value ? String(ttKeywordEl.value).trim().toLowerCase() : "";
    return Promise.all([
      loadTierUserProfile(),
      api()
        .requestJson("/api/admin/tier-templates?page_size=50", { fallbackMessage: "載入等級模板失敗" })
        .then(function (data) {
          var items = (data && data.items) || [];
          if (ttKeyword) {
            items = items.filter(function (it) {
              return [it.template_code, it.display_name, it.level != null ? "LV" + it.level : "", it.level]
                .join(" ")
                .toLowerCase()
                .indexOf(ttKeyword) !== -1;
            });
          }
          var tb = document.getElementById("view-tiers-templates-tbody");
          if (!tb) return;
          if (!items.length) {
            tb.innerHTML = '<tr><td colspan="7" class="admin-muted">尚無等級模板或篩選結果為空。</td></tr>';
            return;
          }
          tb.innerHTML = items
            .map(function (it) {
              var lv = it.level != null ? it.level : "—";
              var lim = it.daily_trade_limit != null ? it.daily_trade_limit : "—";
              return (
                "<tr><td>" +
                esc(it.display_name || it.template_code || "—") +
                '</td><td class="mono">' +
                esc(lv) +
                '</td><td class="mono">' +
                esc(lim) +
                '</td><td class="mono">' +
                esc(it.min_sell_amount || "—") +
                '</td><td class="mono">' +
                esc(it.merchant_enabled ? "是" : "否") +
                '</td><td class="mono">' +
                esc(it.margin_amount || "—") +
                "</td><td>" +
                adminRowActions(
                  adminBtn({
                    variant: "ghost",
                    sm: true,
                    attrs: 'data-admin-tt-open-detail="' + esc(String(it.id)) + '"',
                    label: "編輯"
                  })
                ) +
                "</td></tr>"
              );
            })
            .join("");
        })
        .catch(function (e) {
          if (e && e.adminSessionHandled) return;
          setTbodyError("view-tiers-templates-tbody", e.message || "載入失敗", 7);
        })
    ]);
  }

  function buildAdminDepositsListPath() {
    var q = new URLSearchParams();
    var kwEl = document.getElementById("view-dp-keyword");
    var userEl = document.getElementById("view-dp-user");
    var stEl = document.getElementById("view-dp-status");
    var section = document.getElementById("view-deposits");
    var kw = kwEl && kwEl.value ? String(kwEl.value).trim() : "";
    var userLookup = userEl && userEl.value ? String(userEl.value).trim() : "";
    var status = stEl && stEl.value ? String(stEl.value).trim() : "";
    var assetRaw =
      section && section.getAttribute("data-dp-asset") != null
        ? String(section.getAttribute("data-dp-asset")).trim().toUpperCase()
        : "";
    if (kw) q.set("keyword", kw);
    if (userLookup) q.set("user_id", userLookup);
    if (status) q.set("status", status);
    if (assetRaw === "USDT" || assetRaw === "EUR") {
      q.set("asset_code", assetRaw);
    }
    var qs = q.toString();
    return "/api/admin/deposit-requests" + (qs ? "?" + qs : "");
  }

  function syncDepositAssetTabUi() {
    var root = document.getElementById("view-deposits");
    if (!root) return;
    var cur =
      root.getAttribute("data-dp-asset") != null ? String(root.getAttribute("data-dp-asset")).trim().toUpperCase() : "";
    if (cur !== "USDT" && cur !== "EUR") cur = "";
    root.setAttribute("data-dp-asset", cur);
    root.querySelectorAll("[data-dp-asset-tab]").forEach(function (btn) {
      var v = btn.getAttribute("data-dp-asset-tab");
      var tabVal = v != null ? String(v).trim().toUpperCase() : "";
      if (tabVal !== "USDT" && tabVal !== "EUR") tabVal = "";
      var active = tabVal === cur;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-selected", active ? "true" : "false");
    });
  }

  function getSelectedPendingDepositIds() {
    var out = [];
    Array.prototype.forEach.call(document.querySelectorAll(".view-dp-cb:checked"), function (cb) {
      var id = Number(cb.getAttribute("data-dp-id"));
      if (id) out.push(id);
    });
    return out;
  }

  function runDepositReviewSerial(ids, action, reason, onProgress) {
    var i = 0;
    function step() {
      if (i >= ids.length) return Promise.resolve();
      var id = ids[i++];
      return api()
        .requestJson("/api/admin/deposit-requests/" + id, {
          method: "PATCH",
          body: { action: action, reason: reason != null ? String(reason) : "" },
          fallbackMessage: "充值審核失敗"
        })
        .then(function () {
          if (typeof onProgress === "function") onProgress(i, ids.length, id);
        })
        .then(step);
    }
    return step();
  }

  function bindViewDepositsPage() {
    var root = document.getElementById("view-deposits");
    if (!root || root.dataset.depositsPageBound === "1") return;
    root.dataset.depositsPageBound = "1";
    bindDepositDetailModalOnce();
    var qBtn = document.getElementById("view-dp-query");
    var rBtn = document.getElementById("view-dp-refresh");
    var resetBtn = document.getElementById("view-dp-reset");
    if (qBtn) qBtn.addEventListener("click", function () { loadViewDeposits(); });
    if (rBtn) rBtn.addEventListener("click", function () { loadViewDeposits(); });
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        var kwEl = document.getElementById("view-dp-keyword");
        var userEl = document.getElementById("view-dp-user");
        var stEl = document.getElementById("view-dp-status");
        if (kwEl) kwEl.value = "";
        if (userEl) userEl.value = "";
        if (stEl) stEl.value = "";
        root.setAttribute("data-dp-asset", "");
        syncDepositAssetTabUi();
        loadViewDeposits();
      });
    }
    root.querySelectorAll("[data-dp-asset-tab]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var v = btn.getAttribute("data-dp-asset-tab");
        var val = v != null ? String(v).trim().toUpperCase() : "";
        if (val !== "USDT" && val !== "EUR") val = "";
        root.setAttribute("data-dp-asset", val);
        syncDepositAssetTabUi();
        loadViewDeposits();
      });
    });
    syncDepositAssetTabUi();
    root.addEventListener("change", function (e) {
      var t = e.target;
      var tb = document.getElementById("view-deposits-tbody");
      var sa = document.getElementById("view-dp-select-all");
      if (t && t.classList && t.classList.contains("view-dp-cb") && tb && sa) {
        var all = tb.querySelectorAll(".view-dp-cb");
        var n = 0;
        all.forEach(function (cb) {
          if (cb.checked) n++;
        });
        sa.checked = all.length > 0 && n === all.length;
        sa.indeterminate = n > 0 && n < all.length;
        return;
      }
      if (!t || t.id !== "view-dp-select-all") return;
      var on = t.checked;
      if (!tb) return;
      var boxes = tb.querySelectorAll(".view-dp-cb");
      boxes.forEach(function (cb) {
        cb.checked = on;
      });
      if (sa) sa.indeterminate = false;
      if (on && boxes.length === 0) {
        t.checked = false;
        adminNotifyError("本頁沒有「待審核」列可勾選；請將狀態篩選改為待審核或確認列表資料。");
      }
    });
    var batchApprove = document.getElementById("view-dp-batch-approve");
    var batchReject = document.getElementById("view-dp-batch-reject");
    if (batchApprove) {
      batchApprove.addEventListener("click", function () {
        if (!api()) return;
        var ids = getSelectedPendingDepositIds();
        if (!ids.length) {
          adminNotifyError("請先勾選狀態為「待審核」的充值列。");
          return;
        }
        adminConfirm({
          title: "批次通過",
          message: "確定批次通過 " + ids.length + " 筆充值申請？",
          confirmLabel: "確定通過"
        }).then(function (ok) {
          if (!ok) return;
          setActionLoading(batchApprove, true, "處理中...");
          if (batchReject) setActionLoading(batchReject, true, "處理中...");
          setBatchProgress(0, ids.length, "批次通過充值");
          runDepositReviewSerial(ids, "approve", "", function (done, total) {
            setBatchProgress(done, total, "批次通過充值");
          })
            .then(function () {
              adminNotifySuccess("已通過 " + ids.length + " 筆。");
              clearBatchProgress(900);
              return loadViewDeposits();
            })
            .catch(function (e) {
              if (e && e.adminSessionHandled) return;
              adminNotifyError(e.message || "審核失敗");
              clearBatchProgress();
              loadViewDeposits();
            })
            .then(function () {
              setActionLoading(batchApprove, false);
              if (batchReject) setActionLoading(batchReject, false);
            });
        });
      });
    }
    if (batchReject) {
      batchReject.addEventListener("click", function () {
        if (!api()) return;
        var ids = getSelectedPendingDepositIds();
        if (!ids.length) {
          adminNotifyError("請先勾選狀態為「待審核」的充值列。");
          return;
        }
        adminPrompt({
          title: "駁回原因",
          message: "請輸入駁回原因（必填，將寫入審核備註）。",
          fieldLabel: "駁回原因",
          required: true,
          requiredMessage: "駁回必須填寫原因。"
        }).then(function (reason) {
          if (reason == null) return;
          reason = String(reason).trim();
          if (!reason) return;
          return adminConfirm({
            title: "批次駁回",
            message: "確定批次駁回 " + ids.length + " 筆？",
            danger: true,
            confirmLabel: "確定駁回"
          }).then(function (ok) {
            if (!ok) return;
            setActionLoading(batchReject, true, "處理中...");
            if (batchApprove) setActionLoading(batchApprove, true, "處理中...");
            setBatchProgress(0, ids.length, "批次駁回充值");
            runDepositReviewSerial(ids, "reject", reason, function (done, total) {
              setBatchProgress(done, total, "批次駁回充值");
            })
              .then(function () {
                adminNotifySuccess("已駁回 " + ids.length + " 筆。");
                clearBatchProgress(900);
                return loadViewDeposits();
              })
              .catch(function (e) {
                if (e && e.adminSessionHandled) return;
                adminNotifyError(e.message || "審核失敗");
                clearBatchProgress();
                loadViewDeposits();
              })
              .then(function () {
                setActionLoading(batchReject, false);
                if (batchApprove) setActionLoading(batchApprove, false);
              });
          });
        });
      });
    }
    root.addEventListener("click", function (e) {
      var det = e.target && e.target.closest ? e.target.closest("[data-dp-open-detail]") : null;
      if (det && root.contains(det)) {
        e.preventDefault();
        var rid = Number(det.getAttribute("data-dp-open-detail") || "0");
        if (rid) openDepositDetailModal(rid);
        return;
      }
      var btn = e.target && e.target.closest ? e.target.closest("[data-dp-row-approve], [data-dp-row-reject]") : null;
      if (!btn || !root.contains(btn)) return;
      if (!api()) return;
      var approveId = btn.getAttribute("data-dp-row-approve");
      var rejectId = btn.getAttribute("data-dp-row-reject");
      var id = Number(approveId || rejectId || "0");
      if (!id) return;
      if (approveId != null) {
        adminConfirm({
          title: "通過充值",
          message: "確定通過充值申請 #" + id + "（入帳至目標錢包）？",
          confirmLabel: "確定通過"
        }).then(function (ok) {
          if (!ok) return;
          setActionLoading(btn, true, "處理中...");
          api()
            .requestJson("/api/admin/deposit-requests/" + id, {
              method: "PATCH",
              body: { action: "approve", reason: "" },
              fallbackMessage: "審核失敗"
            })
            .then(function () {
              adminNotifySuccess("充值 #" + id + " 已通過。");
              return loadViewDeposits();
            })
            .catch(function (err) {
              if (err && err.adminSessionHandled) return;
              adminNotifyError(err.message || "審核失敗");
              loadViewDeposits();
            })
            .then(function () {
              setActionLoading(btn, false);
            });
        });
        return;
      }
      adminPrompt({
        title: "駁回充值",
        message: "請輸入駁回原因（必填，將寫入審核備註）。",
        fieldLabel: "駁回原因",
        required: true,
        requiredMessage: "駁回須填寫原因。"
      }).then(function (reason) {
        if (reason == null) return;
        reason = String(reason).trim();
        if (!reason) return;
        adminConfirm({
          title: "駁回充值",
          message: "確定駁回充值申請 #" + id + "？",
          danger: true,
          confirmLabel: "確定駁回"
        }).then(function (ok) {
          if (!ok) return;
          setActionLoading(btn, true, "處理中...");
          api()
            .requestJson("/api/admin/deposit-requests/" + id, {
              method: "PATCH",
              body: { action: "reject", reason: reason },
              fallbackMessage: "審核失敗"
            })
            .then(function () {
              adminNotifySuccess("充值 #" + id + " 已駁回。");
              return loadViewDeposits();
            })
            .catch(function (err) {
              if (err && err.adminSessionHandled) return;
              adminNotifyError(err.message || "審核失敗");
              loadViewDeposits();
            })
            .then(function () {
              setActionLoading(btn, false);
            });
        });
      });
    });
  }

  function loadViewDeposits() {
    bindViewDepositsPage();
    return loadItems(
      buildAdminDepositsListPath(),
      "view-deposits-tbody",
      8,
      function (it) {
        var st = String(it.status != null ? it.status : "").trim().toLowerCase();
        var pending = st === "pending";
        var userCell = renderAdminUserSummaryCell(it, it.user_id);
        var cb = pending
          ? '<td><input type="checkbox" class="view-dp-cb" data-dp-id="' +
            esc(it.id) +
            '" aria-label="選取充值 #' +
            esc(it.id) +
            '"/></td>'
          : '<td class="mono admin-muted" title="僅「待審核」可批次勾選">—</td>';
        return (
          "<tr>" +
          cb +
          "<td class=\"mono\">" +
          esc(it.id) +
          "</td>" +
          userCell +
          '<td class="mono">' +
          esc(it.amount) +
          "</td><td>" +
          esc(assetLabelZh(it.asset_code)) +
          "</td><td>" +
          esc(chainNetworkLabelZh(it.network)) +
          "</td><td>" +
          badge(st, depositStatusLabelZh(it.status)) +
          "</td><td>" +
          adminRowActions(
            (pending
              ? adminBtn({
                  variant: "success",
                  sm: true,
                  attrs: 'data-dp-row-approve="' + esc(String(it.id)) + '"',
                  label: "通過"
                }) +
                adminBtn({
                  variant: "danger",
                  sm: true,
                  attrs: 'data-dp-row-reject="' + esc(String(it.id)) + '"',
                  label: "駁回"
                })
              : "") +
              adminBtn({
                variant: "info",
                sm: true,
                attrs: 'data-dp-open-detail="' + esc(String(it.id)) + '"',
                label: "詳情"
              })
          ) +
          "</td></tr>"
        );
      },
      "尚無充值申請",
      function () {
        var sa = document.getElementById("view-dp-select-all");
        if (sa) {
          sa.checked = false;
          sa.indeterminate = false;
        }
      },
      { useSkeleton: true }
    );
  }

  function loadViewDepositAddresses() {
    bindViewDepositAddressesPage();
    initDepositAddressForms();
    if (!api()) return Promise.resolve();
    var pager = adminPagerGet("view-deposit-addresses-tbody");
    var requestPath = adminPagerRequestPath("view-deposit-addresses-tbody", buildAdminDepositAddressesPath());
    setTbodyLoading("view-deposit-addresses-tbody", 6);
    return api()
      .requestJson(requestPath, { fallbackMessage: "載入充值地址失敗" })
      .then(function (data) {
        var items = (data && data.items) || [];
        var page = (data && data.pagination && data.pagination.page) || 1;
        var pageSize = (data && data.pagination && data.pagination.page_size) || pager.pageSize;
        var tb = document.getElementById("view-deposit-addresses-tbody");
        if (!tb) return;
        if (!items.length) {
          tb.innerHTML =
            tableEmptyRow(6, "尚無用戶資料", "請調整搜尋條件或稍後重新查詢。");
          adminPagerRender("view-deposit-addresses-tbody", (data && data.pagination) || { page: pager.page, page_size: pager.pageSize, total: 0 }, loadViewDepositAddresses);
          return;
        }
        tb.innerHTML = items
          .map(function (it, idx) {
            var seqNum = (page - 1) * pageSize + idx + 1;
            var seq = seqNum <= 99 ? ("0" + seqNum).slice(-2) : String(seqNum);
            var uid = it.user_id != null ? it.user_id : "—";
            var un = it.username != null && String(it.username).trim() !== "" ? String(it.username).trim() : "";
            var userTitle = un || "用戶 " + uid;
            var em = String(it.email || "").trim();
            var userCell =
              "<td>" +
              esc(userTitle) +
              (em ? '<div class="admin-cell-sub">' + esc(em) + "</div>" : "") +
              '<div class="admin-cell-sub mono">用戶 ID：' +
              esc(uid) +
              "</div></td>";
            return (
              "<tr><td class=\"mono\">" +
              esc(seq) +
              "</td>" +
              userCell +
              '<td class="mono">' +
              depositAddressListCellHtml(it.trc20_address, "TRC20") +
              "</td><td class=\"mono\">" +
              depositAddressListCellHtml(it.erc20_address, "ERC20") +
              "</td><td class=\"mono\">" +
              depositAddressListCellHtml(it.bep20_address, "BEP20") +
              "</td><td>" +
              adminRowActions(
                adminBtn({
                  variant: "ghost",
                  sm: true,
                  attrs: 'data-admin-da-open-detail="' + esc(String(uid)) + '"',
                  label: "編輯"
                })
              ) +
              "</td></tr>"
            );
          })
          .join("");
        adminPagerRender("view-deposit-addresses-tbody", data && data.pagination, loadViewDepositAddresses);
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setTbodyError("view-deposit-addresses-tbody", e.message, 6);
      });
  }

  function buildAdminWithdrawalsListPath() {
    var q = new URLSearchParams();
    var kwEl = document.getElementById("view-wd-keyword");
    var userEl = document.getElementById("view-wd-user");
    var chEl = document.getElementById("view-wd-channel");
    var assetEl = document.getElementById("view-wd-asset");
    var stEl = document.getElementById("view-wd-status");
    var kw = kwEl && kwEl.value ? String(kwEl.value).trim() : "";
    var userLookup = userEl && userEl.value ? String(userEl.value).trim() : "";
    var ch = chEl && chEl.value ? String(chEl.value).trim() : "";
    var asset = assetEl && assetEl.value ? String(assetEl.value).trim().toUpperCase() : "";
    var status = stEl && stEl.value ? String(stEl.value).trim() : "";
    if (kw) q.set("keyword", kw);
    if (userLookup) q.set("user_id", userLookup);
    if (ch) q.set("channel_type", ch);
    if (asset) q.set("asset_code", asset);
    if (status) q.set("status", status);
    var qs = q.toString();
    return "/api/admin/withdrawal-requests" + (qs ? "?" + qs : "");
  }

  function getSelectedPendingWithdrawalIds() {
    var out = [];
    Array.prototype.forEach.call(document.querySelectorAll(".view-wd-cb:checked"), function (cb) {
      var id = Number(cb.getAttribute("data-wd-id"));
      if (id) out.push(id);
    });
    return out;
  }

  function runWithdrawalReviewSerial(ids, action, reason, onProgress) {
    var i = 0;
    function step() {
      if (i >= ids.length) return Promise.resolve();
      var id = ids[i++];
      return api()
        .requestJson("/api/admin/withdrawal-requests/" + id, {
          method: "PATCH",
          body: { action: action, reason: reason != null ? String(reason) : "" },
          fallbackMessage: "提現審核失敗"
        })
        .then(function () {
          if (typeof onProgress === "function") onProgress(i, ids.length, id);
        })
        .then(step);
    }
    return step();
  }

  function bindViewWithdrawalsPage() {
    var root = document.getElementById("view-withdrawals");
    if (!root || root.dataset.withdrawalsPageBound === "1") return;
    root.dataset.withdrawalsPageBound = "1";
    var qBtn = document.getElementById("view-wd-query");
    var rBtn = document.getElementById("view-wd-refresh");
    var resetBtn = document.getElementById("view-wd-reset");
    if (qBtn) qBtn.addEventListener("click", function () { loadViewWithdrawals(); });
    if (rBtn) rBtn.addEventListener("click", function () { loadViewWithdrawals(); });
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        var kwEl = document.getElementById("view-wd-keyword");
        var userEl = document.getElementById("view-wd-user");
        var chEl = document.getElementById("view-wd-channel");
        var assetEl = document.getElementById("view-wd-asset");
        var stEl = document.getElementById("view-wd-status");
        if (kwEl) kwEl.value = "";
        if (userEl) userEl.value = "";
        if (chEl) chEl.value = "";
        if (assetEl) assetEl.value = "";
        if (stEl) stEl.value = "";
        loadViewWithdrawals();
      });
    }
    root.addEventListener("change", function (e) {
      var t = e.target;
      var tb = document.getElementById("view-withdrawals-tbody");
      var sa = document.getElementById("view-wd-select-all");
      if (t && t.classList && t.classList.contains("view-wd-cb") && tb && sa) {
        var all = tb.querySelectorAll(".view-wd-cb");
        var n = 0;
        all.forEach(function (cb) {
          if (cb.checked) n++;
        });
        sa.checked = all.length > 0 && n === all.length;
        sa.indeterminate = n > 0 && n < all.length;
        return;
      }
      if (!t || t.id !== "view-wd-select-all") return;
      var on = t.checked;
      if (!tb) return;
      var boxes = tb.querySelectorAll(".view-wd-cb");
      boxes.forEach(function (cb) {
        cb.checked = on;
      });
      if (sa) sa.indeterminate = false;
      if (on && boxes.length === 0) {
        t.checked = false;
        adminNotifyError("本頁沒有「待審核」列可勾選；請將狀態篩選改為待審核或確認列表資料。");
      }
    });
    var batchApprove = document.getElementById("view-wd-batch-approve");
    var batchReject = document.getElementById("view-wd-batch-reject");
    if (batchApprove) {
      batchApprove.addEventListener("click", function () {
        if (!api()) return;
        var ids = getSelectedPendingWithdrawalIds();
        if (!ids.length) {
          adminNotifyError("請先勾選狀態為「待審核」的提現列。");
          return;
        }
        adminConfirm({
          title: "批次放行",
          message: "確定批次放行 " + ids.length + " 筆提現（approve）？",
          confirmLabel: "確定放行"
        }).then(function (ok) {
          if (!ok) return;
          setActionLoading(batchApprove, true, "處理中...");
          if (batchReject) setActionLoading(batchReject, true, "處理中...");
          setBatchProgress(0, ids.length, "批次放行提現");
          runWithdrawalReviewSerial(ids, "approve", "", function (done, total) {
            setBatchProgress(done, total, "批次放行提現");
          })
            .then(function () {
              adminNotifySuccess("已放行 " + ids.length + " 筆。");
              clearBatchProgress(900);
              return loadViewWithdrawals();
            })
            .catch(function (e) {
              if (e && e.adminSessionHandled) return;
              adminNotifyError(e.message || "審核失敗");
              clearBatchProgress();
              loadViewWithdrawals();
            })
            .then(function () {
              setActionLoading(batchApprove, false);
              if (batchReject) setActionLoading(batchReject, false);
            });
        });
      });
    }
    if (batchReject) {
      batchReject.addEventListener("click", function () {
        if (!api()) return;
        var ids = getSelectedPendingWithdrawalIds();
        if (!ids.length) {
          adminNotifyError("請先勾選狀態為「待審核」的提現列。");
          return;
        }
        adminPrompt({
          title: "駁回原因",
          message: "請輸入駁回原因（必填，將寫入審核備註）。",
          fieldLabel: "駁回原因",
          required: true,
          requiredMessage: "駁回必須填寫原因。"
        }).then(function (reason) {
          if (reason == null) return;
          reason = String(reason).trim();
          if (!reason) return;
          return adminConfirm({
            title: "批次拒絕並退回",
            message: "確定批次拒絕並退回 " + ids.length + " 筆？",
            danger: true,
            confirmLabel: "確定拒絕"
          }).then(function (ok) {
            if (!ok) return;
            setActionLoading(batchReject, true, "處理中...");
            if (batchApprove) setActionLoading(batchApprove, true, "處理中...");
            setBatchProgress(0, ids.length, "批次駁回提現");
            runWithdrawalReviewSerial(ids, "reject", reason, function (done, total) {
              setBatchProgress(done, total, "批次駁回提現");
            })
              .then(function () {
                adminNotifySuccess("已拒絕 " + ids.length + " 筆。");
                clearBatchProgress(900);
                return loadViewWithdrawals();
              })
              .catch(function (e) {
                if (e && e.adminSessionHandled) return;
                adminNotifyError(e.message || "審核失敗");
                clearBatchProgress();
                loadViewWithdrawals();
              })
              .then(function () {
                setActionLoading(batchReject, false);
                if (batchApprove) setActionLoading(batchApprove, false);
              });
          });
        });
      });
    }
    root.addEventListener("click", function (e) {
      var btn = e.target && e.target.closest ? e.target.closest("[data-wd-row-approve], [data-wd-row-reject]") : null;
      if (!btn || !root.contains(btn)) return;
      if (!api()) return;
      var approveId = btn.getAttribute("data-wd-row-approve");
      var rejectId = btn.getAttribute("data-wd-row-reject");
      var id = Number(approveId || rejectId || "0");
      if (!id) return;
      if (approveId != null) {
        adminConfirm({
          title: "放行提現",
          message: "確定通過提現 #" + id + "（放行放款）？",
          confirmLabel: "確定放行"
        }).then(function (ok) {
          if (!ok) return;
          setActionLoading(btn, true, "處理中...");
          api()
            .requestJson("/api/admin/withdrawal-requests/" + id, {
              method: "PATCH",
              body: { action: "approve", reason: "" },
              fallbackMessage: "審核失敗"
            })
            .then(function () {
              adminNotifySuccess("提現 #" + id + " 已通過。");
              return loadViewWithdrawals();
            })
            .catch(function (err) {
              if (err && err.adminSessionHandled) return;
              adminNotifyError(err.message || "審核失敗");
              loadViewWithdrawals();
            })
            .then(function () {
              setActionLoading(btn, false);
            });
        });
        return;
      }
      adminPrompt({
        title: "駁回提現",
        message: "請輸入駁回原因（必填，將寫入審核備註）。",
        fieldLabel: "駁回原因",
        required: true,
        requiredMessage: "駁回須填寫原因。"
      }).then(function (reason) {
        if (reason == null) return;
        reason = String(reason).trim();
        if (!reason) return;
        adminConfirm({
          title: "駁回提現",
          message: "確定駁回提現 #" + id + "（拒絕並退回）？",
          danger: true,
          confirmLabel: "確定駁回"
        }).then(function (ok) {
          if (!ok) return;
          setActionLoading(btn, true, "處理中...");
          api()
            .requestJson("/api/admin/withdrawal-requests/" + id, {
              method: "PATCH",
              body: { action: "reject", reason: reason },
              fallbackMessage: "審核失敗"
            })
            .then(function () {
              adminNotifySuccess("提現 #" + id + " 已駁回。");
              return loadViewWithdrawals();
            })
            .catch(function (err) {
              if (err && err.adminSessionHandled) return;
              adminNotifyError(err.message || "審核失敗");
              loadViewWithdrawals();
            })
            .then(function () {
              setActionLoading(btn, false);
            });
        });
      });
    });
  }

  function loadViewWithdrawals() {
    return loadItems(
      buildAdminWithdrawalsListPath(),
      "view-withdrawals-tbody",
      9,
      function (it) {
        var st = String(it.status != null ? it.status : "").trim().toLowerCase();
        var pending = st === "pending";
        var userCell = renderAdminUserSummaryCell(it, it.user_id);
        var cb = pending
          ? '<td><input type="checkbox" class="view-wd-cb" data-wd-id="' +
            esc(it.id) +
            '" aria-label="選取提現 #' +
            esc(it.id) +
            '"/></td>'
          : '<td class="mono admin-muted" title="僅「待審核」可批次勾選">—</td>';
        return (
          "<tr>" +
          cb +
          "<td class=\"mono\">" +
          esc(it.id) +
          "</td>" +
          userCell +
          '<td class="mono">' +
          esc(it.amount) +
          "</td><td>" +
          esc(assetLabelZh(it.asset_code)) +
          "</td><td>" +
          esc(payoutChannelLabelZh(it.channel_type)) +
          "</td><td>" +
          esc(it.remark || "—") +
          "</td><td>" +
          badge(it.status, withdrawalStatusLabelZh(it.status)) +
          "</td><td>" +
          adminRowActions(
            (pending
              ? adminBtn({
                  variant: "success",
                  sm: true,
                  attrs: 'data-wd-row-approve="' + esc(String(it.id)) + '"',
                  label: "通過"
                }) +
                adminBtn({
                  variant: "danger",
                  sm: true,
                  attrs: 'data-wd-row-reject="' + esc(String(it.id)) + '"',
                  label: "駁回"
                })
              : "") +
              adminBtn({
                variant: "info",
                sm: true,
                attrs: 'data-admin-withdrawal-detail="' + esc(String(it.id)) + '"',
                label: "詳情"
              })
          ) +
          "</td></tr>"
        );
      },
      "尚無提現申請",
      function () {
        var sa = document.getElementById("view-wd-select-all");
        if (sa) {
          sa.checked = false;
          sa.indeterminate = false;
        }
      },
      { useSkeleton: true }
    );
  }

  function openWithdrawalDetail(id) {
    id = String(id || "").trim();
    if (!id || !api()) return;
    showModalById("admin-modal-withdrawal-detail");
    setModalBodyHtml("view-withdrawal-detail-body", '<p class="admin-modal__empty-note">載入提現申請詳情中…</p>');
    api()
      .requestJson("/api/admin/withdrawal-requests/" + encodeURIComponent(id), { fallbackMessage: "載入提現詳情失敗" })
      .then(function (data) {
        var it = (data && data.request) || {};
        var user = {
          id: it.user_id,
          username: it.username,
          email: it.email,
          mobile_e164: it.mobile_e164
        };
        setModalBodyHtml(
          "view-withdrawal-detail-body",
          detailRows([
            { label: "申請單號", value: it.id, mono: true },
            { label: "用戶", value: renderAdminUserSummaryHtml(user, it.user_id), raw: true },
            { label: "金額", value: formatListingPriceDisplay(it.amount) + " " + assetLabelZh(it.asset_code), mono: true },
            { label: "路徑", value: payoutChannelLabelZh(it.channel_type) },
            { label: "收款資料", value: [it.bank_name, it.account_holder, it.account_no_masked, it.usdt_network, it.pix_key].filter(Boolean).join(" / ") || "—" },
            { label: "狀態", value: badge(it.status, withdrawalStatusLabelZh(it.status)), raw: true },
            { label: "備註", value: it.remark || "—" },
            { label: "審核原因", value: it.review_reason || it.reason || "—" },
            { label: "建立時間", value: it.created_at || "—", mono: true },
            { label: "更新時間", value: it.updated_at || "—", mono: true }
          ])
        );
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setModalBodyHtml("view-withdrawal-detail-body", '<p class="admin-modal__empty-note">' + esc(e.message || "載入失敗") + "</p>");
      });
  }

  function syncViewEurSwapFeeModeUi() {
    var modeEl = document.getElementById("view-eur-swap-fee-mode");
    var m = modeEl && modeEl.value === "fixed_usdt" ? "fixed_usdt" : "percent";
    var rowRate = document.getElementById("view-eur-swap-fee-rate-field");
    var rowFixed = document.getElementById("view-eur-swap-fee-fixed-field");
    if (rowRate) rowRate.style.display = m === "percent" ? "" : "none";
    if (rowFixed) rowFixed.style.display = m === "fixed_usdt" ? "" : "none";
  }

  function bindViewEurSwapPage() {
    var root = document.getElementById("view-eur-swap-withdraw-config");
    if (!root || root.dataset.eurSwapPageBound === "1") return;
    root.dataset.eurSwapPageBound = "1";
    var modeEl = document.getElementById("view-eur-swap-fee-mode");
    if (modeEl) modeEl.addEventListener("change", syncViewEurSwapFeeModeUi);
    var refreshBtn = document.getElementById("view-eur-swap-refresh");
    if (refreshBtn) refreshBtn.addEventListener("click", function () { loadViewEurSwap(); });
    var saveBtn = document.getElementById("view-eur-swap-save");
    if (saveBtn) {
      saveBtn.addEventListener("click", function () {
        if (!api()) return;
        var rateEl = document.getElementById("view-eur-swap-rate");
        var fr = document.getElementById("view-eur-swap-fee-rate");
        var ff = document.getElementById("view-eur-swap-fee-fixed");
        var reasonEl = document.getElementById("view-eur-swap-reason");
        var reason = reasonEl && reasonEl.value ? String(reasonEl.value).trim() : "";
        var rateStr = rateEl && rateEl.value !== "" ? String(rateEl.value).trim() : "";
        var feeRateStr = fr && fr.value !== "" ? String(fr.value).trim() : "";
        var feeFixedStr = ff && ff.value !== "" ? String(ff.value).trim() : "";
        var feeMode = modeEl && modeEl.value === "fixed_usdt" ? "fixed_usdt" : "percent";
        var rateNum = Number(rateStr);
        var feeRateNum = Number(feeRateStr);
        var feeFixedNum = Number(feeFixedStr);
        if (!rateStr || !isFinite(rateNum) || rateNum <= 0) {
          adminNotifyError("請填寫有效的匯率（須大於 0）。");
          return;
        }
        if (feeRateStr === "" || !isFinite(feeRateNum) || feeRateNum < 0) {
          adminNotifyError("請填寫有效的手續費百分比（可為 0）。");
          return;
        }
        if (feeFixedStr === "" || !isFinite(feeFixedNum) || feeFixedNum < 0) {
          adminNotifyError("請填寫有效的固定手續費（可為 0）。");
          return;
        }
        saveBtn.disabled = true;
        api()
          .requestJson("/api/admin/withdrawal-settings/eur-swap", {
            method: "PATCH",
            body: {
              rate: rateStr,
              fee_mode: feeMode,
              fee_rate: feeRateStr,
              fee_fixed_usdt: feeFixedStr,
              reason: reason
            },
            fallbackMessage: "儲存換匯提領設定失敗"
          })
          .then(function () {
            adminNotifySuccess("提領配置已儲存。");
            return loadViewEurSwap();
          })
          .catch(function (e) {
            if (e && e.adminSessionHandled) return;
            adminNotifyError(e.message || "儲存失敗");
          })
          .then(function () {
            saveBtn.disabled = false;
          });
      });
    }
    var focusBtn = document.getElementById("view-eur-swap-rule-focus");
    if (focusBtn) {
      focusBtn.addEventListener("click", function () {
        var rate = document.getElementById("view-eur-swap-rate");
        if (rate && typeof rate.focus === "function") rate.focus();
        adminNotifySuccess("請直接在上方提領參數調整規則並儲存。");
      });
    }
    var copyBtn = document.getElementById("view-eur-swap-rule-copy");
    if (copyBtn) {
      copyBtn.addEventListener("click", function () {
        if (!api()) return;
        adminPrompt({
          title: "複製換匯規則",
          message: "系統會複製目前提領參數到審計紀錄，方便之後追蹤版本。",
          fieldLabel: "備註",
          defaultValue: "複製目前 EUR 換 USDT 提領規則",
          required: false
        }).then(function (reason) {
          if (reason == null) return;
          copyBtn.disabled = true;
          api()
            .requestJson("/api/admin/withdrawal-settings/eur-swap/copy", {
              method: "POST",
              body: { reason: reason || "" },
              fallbackMessage: "複製換匯規則失敗"
            })
            .then(function () {
              adminNotifySuccess("已複製目前規則到版本紀錄。");
              return loadViewEurSwap();
            })
            .catch(function (e) {
              if (e && e.adminSessionHandled) return;
              adminNotifyError(e.message || "複製失敗");
            })
            .then(function () {
              copyBtn.disabled = false;
            });
        });
      });
    }
    var historyBtn = document.getElementById("view-eur-swap-rule-history");
    if (historyBtn) {
      historyBtn.addEventListener("click", function () {
        if (!api()) return;
        historyBtn.disabled = true;
        api()
          .requestJson("/api/admin/withdrawal-settings/eur-swap/history", {
            fallbackMessage: "載入換匯規則版本紀錄失敗"
          })
          .then(function (data) {
            var items = (data && data.items) || [];
            var lines = items.length
              ? items.map(function (it) {
                  var after = it.after || {};
                  return [
                    "#" + it.id,
                    it.created_at || "—",
                    "rate=" + (after.rate || "—"),
                    "fee=" + (after.fee_mode || "—") + "/" + (after.fee_rate || after.fee_fixed_usdt || "0"),
                    it.reason ? "reason=" + it.reason : ""
                  ].filter(Boolean).join(" · ");
                }).join("\n")
              : "尚無版本紀錄。";
            if (window.AdminModals && window.AdminModals.showAlert) {
              window.AdminModals.showAlert({ title: "換匯規則版本紀錄", message: lines });
            } else {
              adminNotifySuccess(lines);
            }
          })
          .catch(function (e) {
            if (e && e.adminSessionHandled) return;
            adminNotifyError(e.message || "載入失敗");
          })
          .then(function () {
            historyBtn.disabled = false;
          });
      });
    }
  }

  function loadViewEurSwap() {
    return api()
      .requestJson("/api/admin/withdrawal-settings/eur-swap", { fallbackMessage: "載入換匯設定失敗" })
      .then(function (data) {
        var item = (data && data.item) || {};
        var rate = document.getElementById("view-eur-swap-rate");
        var mode = document.getElementById("view-eur-swap-fee-mode");
        var fr = document.getElementById("view-eur-swap-fee-rate");
        var ff = document.getElementById("view-eur-swap-fee-fixed");
        if (rate) rate.value = item.rate ? formatListingPriceDisplay(item.rate) : "";
        if (mode) mode.value = item.fee_mode === "fixed_usdt" ? "fixed_usdt" : "percent";
        if (fr) fr.value = item.fee_rate != null ? item.fee_rate : "";
        if (ff) ff.value = item.fee_fixed_usdt != null ? item.fee_fixed_usdt : "";
        var current = document.getElementById("view-eur-swap-current-config");
        if (current) {
          var feeText =
            item.fee_mode === "fixed_usdt"
              ? "固定 USDT 扣費 " + (item.fee_fixed_usdt != null ? item.fee_fixed_usdt : "0")
              : "百分比扣費 " + (item.fee_rate != null ? item.fee_rate : "0") + "%";
          current.textContent = "目前配置：匯率 " + (item.rate || "—") + "，" + feeText;
        }
        var updated = document.getElementById("view-eur-swap-rule-updated");
        if (updated) updated.textContent = item.updated_at || "目前設定";
        syncViewEurSwapFeeModeUi();
      })
      .catch(function () {});
  }

  function buildAdminOrdersListPath() {
    var q = new URLSearchParams();
    var kwEl = document.getElementById("view-orders-keyword");
    var userEl = document.getElementById("view-orders-user");
    var statusEl = document.getElementById("view-orders-status");
    var group = document.getElementById("view-orders-side-group");
    var activeBtn = group ? group.querySelector(".admin-toggle-group__btn.is-active") : null;
    var side = activeBtn ? String(activeBtn.getAttribute("data-order-side") || "") : "";
    var kw = kwEl && kwEl.value ? String(kwEl.value).trim() : "";
    var userLookup = userEl && userEl.value ? String(userEl.value).trim() : "";
    var status = statusEl && statusEl.value ? String(statusEl.value).trim() : "";
    if (kw) q.set("keyword", kw);
    if (userLookup) q.set("user_id", userLookup);
    if (status) q.set("status", status);
    if (side) q.set("side", side);
    var qs = q.toString();
    return "/api/admin/orders" + (qs ? "?" + qs : "");
  }

  function bindViewOrdersFilters() {
    var section = document.getElementById("view-orders");
    if (!section || section.dataset.ordersFiltersBound === "1") return;
    section.dataset.ordersFiltersBound = "1";
    var group = document.getElementById("view-orders-side-group");
    if (group) {
      group.addEventListener("click", function (e) {
        var btn = e.target && e.target.closest ? e.target.closest(".admin-toggle-group__btn[data-order-side]") : null;
        if (!btn || !group.contains(btn)) return;
        Array.prototype.forEach.call(group.querySelectorAll(".admin-toggle-group__btn"), function (b) {
          b.classList.toggle("is-active", b === btn);
        });
        loadViewOrders();
      });
    }
    var qBtn = document.getElementById("view-orders-query");
    var rBtn = document.getElementById("view-orders-refresh");
    var resetBtn = document.getElementById("view-orders-reset");
    if (qBtn) qBtn.addEventListener("click", function () { loadViewOrders(); });
    if (rBtn) rBtn.addEventListener("click", function () { loadViewOrders(); });
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        var kwEl = document.getElementById("view-orders-keyword");
        var userEl = document.getElementById("view-orders-user");
        var statusEl = document.getElementById("view-orders-status");
        if (kwEl) kwEl.value = "";
        if (userEl) userEl.value = "";
        if (statusEl) statusEl.value = "";
        if (group) {
          Array.prototype.forEach.call(group.querySelectorAll(".admin-toggle-group__btn"), function (b, i) {
            b.classList.toggle("is-active", i === 0);
          });
        }
        loadViewOrders();
      });
    }
  }

  function loadViewOrders() {
    return loadItems(
      buildAdminOrdersListPath(),
      "view-orders-tbody",
      8,
      function (it) {
        var buyerCell =
          renderAdminUserSummaryHtml(adminUserFromPrefixedFields(it, "buyer_"), it.buyer_user_id) +
          '<div class="admin-cell-sub">來源：' +
          sourceBadge(it.buyer_source) +
          "</div>";
        var sellerCell =
          renderAdminUserSummaryHtml(adminUserFromPrefixedFields(it, "seller_"), it.seller_user_id) +
          '<div class="admin-cell-sub">來源：' +
          sourceBadge(it.seller_source) +
          "</div>";
        return (
          "<tr><td class=\"mono\">" +
          esc(it.id) +
          "</td><td class=\"mono\">" +
          esc(it.order_no) +
          "</td><td>" +
          orderSideBadge(it.side) +
          "</td><td class=\"mono\">" +
          esc(formatListingPriceDisplay(it.amount)) +
          " " +
          esc(assetLabelZh(it.asset_code)) +
          "</td><td>" +
          buyerCell +
          "</td><td>" +
          sellerCell +
          "</td><td>" +
          badge(it.status, orderStatusLabelZh(it.status)) +
          "</td><td>" +
          adminRowActions(
            adminBtn({
              variant: "success",
              sm: true,
              attrs:
                'data-admin-order-approve="' +
                esc(String(it.id)) +
                '" data-admin-order-merchant-source="' +
                esc(orderMerchantSource(it)) +
                '"',
              label: "通過"
            }) +
            adminBtn({
              variant: "danger",
              sm: true,
              attrs:
                'data-admin-order-reject="' +
                esc(String(it.id)) +
                '" data-admin-order-merchant-source="' +
                esc(orderMerchantSource(it)) +
                '"',
              label: "駁回"
            }) +
            adminBtn({
              variant: "info",
              sm: true,
              attrs: 'data-admin-order-detail="' + esc(String(it.id)) + '"',
              label: "詳情"
            })
          ) +
          "</td></tr>"
        );
      },
      "尚無訂單",
      null,
      { useSkeleton: true }
    );
  }

  function openOrderDetail(id) {
    id = String(id || "").trim();
    if (!id || !api()) return;
    showModalById("admin-modal-order-detail");
    setModalBodyHtml("view-order-detail-body", '<p class="admin-modal__empty-note">載入訂單詳情中…</p>');
    api()
      .requestJson("/api/admin/orders/" + encodeURIComponent(id), { fallbackMessage: "載入訂單詳情失敗" })
      .then(function (data) {
        var it = (data && data.order) || {};
        var buyer = adminUserFromPrefixedFields(it, "buyer_");
        var seller = adminUserFromPrefixedFields(it, "seller_");
        var evidence = it.evidences || it.evidence || it.proofs || [];
        setModalBodyHtml(
          "view-order-detail-body",
          '<div class="admin-modal__subgrid">' +
            '<section class="admin-modal__cardblock"><h3 class="admin-modal__cardblock-title">交易雙方</h3>' +
            detailRows([
              { label: "買家", value: renderAdminUserSummaryHtml(buyer, it.buyer_user_id), raw: true },
              { label: "買家來源", value: sourceBadge(it.buyer_source), raw: true },
              { label: "賣家", value: renderAdminUserSummaryHtml(seller, it.seller_user_id), raw: true },
              { label: "賣家來源", value: sourceBadge(it.seller_source), raw: true }
            ]) +
            "</section>" +
            '<section class="admin-modal__cardblock"><h3 class="admin-modal__cardblock-title">訂單資訊</h3>' +
            detailRows([
              { label: "ID", value: it.id, mono: true },
              { label: "訂單號", value: it.order_no || "—", mono: true },
              { label: "方向", value: orderSideBadge(it.side), raw: true },
              { label: "數量", value: formatListingPriceDisplay(it.amount) + " " + assetLabelZh(it.asset_code), mono: true },
              { label: "單價", value: it.price != null ? formatListingPriceDisplay(it.price) : "—", mono: true },
              { label: "狀態", value: badge(it.status, orderStatusLabelZh(it.status)), raw: true }
            ]) +
            "</section></div>" +
            detailRows([
              { label: "建立時間", value: it.created_at || "—", mono: true },
              { label: "更新時間", value: it.updated_at || "—", mono: true },
              { label: "付款時間", value: it.paid_at || "—", mono: true },
              { label: "完成時間", value: it.completed_at || "—", mono: true },
              { label: "取消/拒絕原因", value: it.cancel_reason || it.reject_reason || it.reason || "—" },
              { label: "證據/補充資料", value: renderJsonBlock(evidence), raw: true }
            ])
        );
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setModalBodyHtml("view-order-detail-body", '<p class="admin-modal__empty-note">' + esc(e.message || "載入失敗") + "</p>");
      });
  }

  function runAdminOrderApprove(id, merchantSource, triggerBtn) {
    id = String(id || "").trim();
    if (!id || !api()) return Promise.resolve();
    var firstMessage = "確認通過此筆交易？將執行實際放幣與資金結算。";
    if (String(merchantSource || "").toLowerCase() !== "agent") {
      firstMessage = "此筆為真實用戶商家訂單，確認通過後將進行真實扣減/放幣，是否繼續？";
    }
    return adminConfirm({
      title: "確認通過",
      message: firstMessage,
      confirmText: "確認通過",
      cancelText: "取消"
    }).then(function (ok) {
      if (!ok) return;
      setActionLoading(triggerBtn, true, "處理中...");
      return api()
        .requestJson("/api/admin/orders/" + encodeURIComponent(id) + "/release", {
          method: "POST",
          fallbackMessage: "訂單通過失敗"
        })
        .then(function () {
          adminNotifySuccess("訂單已通過並完成結算。");
          reloadCurrentView();
        })
        .then(function () {
          setActionLoading(triggerBtn, false);
        })
        .catch(function (e) {
          setActionLoading(triggerBtn, false);
          throw e;
        });
    });
  }

  function runAdminOrderReject(id, merchantSource, triggerBtn) {
    id = String(id || "").trim();
    if (!id || !api()) return Promise.resolve();
    var firstMessage = "確認駁回此筆交易？系統將取消訂單並走退款/退回流程。";
    if (String(merchantSource || "").toLowerCase() !== "agent") {
      firstMessage = "此筆為真實用戶商家訂單，駁回後將進行真實退款/退回，是否繼續？";
    }
    return adminConfirm({
      title: "確認駁回",
      message: firstMessage,
      confirmText: "下一步",
      cancelText: "取消"
    }).then(function (ok) {
      if (!ok) return;
      return adminPrompt({
        title: "駁回原因",
        message: "請輸入駁回原因（可留空）",
        defaultValue: "",
        placeholder: "選填"
      }).then(function (reason) {
        if (reason === null) return;
        setActionLoading(triggerBtn, true, "處理中...");
        return api()
          .requestJson("/api/admin/orders/" + encodeURIComponent(id) + "/status", {
            method: "PATCH",
            body: {
              status: "cancelled",
              reason: String(reason || "").trim()
            },
            fallbackMessage: "訂單駁回失敗"
          })
          .then(function () {
            adminNotifySuccess("訂單已駁回並取消。");
            reloadCurrentView();
          })
          .then(function () {
            setActionLoading(triggerBtn, false);
          })
          .catch(function (e) {
            setActionLoading(triggerBtn, false);
            throw e;
          });
      });
    });
  }

  function bindSimpleFilterControls(sectionId, controlIds, loader, resetFn) {
    var section = document.getElementById(sectionId);
    if (!section || section.dataset.simpleFiltersBound === "1") return;
    section.dataset.simpleFiltersBound = "1";
    function byId(key) {
      return controlIds && controlIds[key] ? document.getElementById(controlIds[key]) : null;
    }
    ["query", "refresh"].forEach(function (key) {
      var btn = byId(key);
      if (btn) btn.addEventListener("click", loader);
    });
    var reset = byId("reset");
    if (reset) {
      reset.addEventListener("click", function () {
        (controlIds.inputs || []).forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = "";
        });
        if (resetFn) resetFn();
        loader();
      });
    }
    (controlIds.inputs || []).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener("keydown", function (e) {
          if (e.key === "Enter") loader();
        });
        if (el.tagName === "SELECT") el.addEventListener("change", loader);
      }
    });
  }

  function queryValue(id) {
    var el = document.getElementById(id);
    return el && el.value ? String(el.value).trim() : "";
  }

  function buildFinancialProductsPath() {
    var q = new URLSearchParams();
    var kw = queryValue("view-fp-keyword");
    var st = queryValue("view-fp-status");
    if (kw) q.set("keyword", kw);
    if (st) q.set("status", st);
    var qs = q.toString();
    return "/api/admin/financial-products" + (qs ? "?" + qs : "");
  }

  function loadViewFinancialProducts() {
    return loadItems(
      buildFinancialProductsPath(),
      "view-financial-products-tbody",
      8,
      function (it) {
        return (
          "<tr><td class=\"mono\">" +
          esc(it.product_code || it.id) +
          "</td><td>" +
          esc(it.display_name || "—") +
          '</td><td class="mono">' +
          esc(it.apr_rate || "—") +
          "</td><td>" +
          esc(it.term_days != null ? it.term_days + " 天" : "—") +
          '</td><td class="mono">' +
          esc(it.min_subscribe_amount || "—") +
          "</td><td>" +
          esc(financialReturnModeLabelZh(it.default_return_mode)) +
          "</td><td>" +
          badge(it.status, financialProductStatusLabelZh(it.status)) +
          "</td><td>" +
          adminRowActions(
            adminBtn({
              variant: "ghost",
              sm: true,
              attrs: 'data-admin-fp-open-detail="' + esc(String(it.id)) + '"',
              label: "編輯"
            })
          ) +
          "</td></tr>"
        );
      },
      "尚無理財產品"
    );
  }

  function buildFinancialOrdersPath() {
    var q = new URLSearchParams();
    var kw = queryValue("view-fo-keyword");
    var user = queryValue("view-fo-user");
    var mode = queryValue("view-fo-return-mode");
    var st = queryValue("view-fo-status");
    if (kw) q.set("keyword", kw);
    if (user) q.set("user_id", user);
    if (mode) q.set("return_mode", mode);
    if (st) q.set("status", st);
    var qs = q.toString();
    return "/api/admin/financial-subscriptions" + (qs ? "?" + qs : "");
  }

  function loadViewFinancialOrders() {
    return loadItems(
      buildFinancialOrdersPath(),
      "view-financial-orders-tbody",
      7,
      function (it) {
        return (
          "<tr><td class=\"mono\">" +
          esc(it.id) +
          "</td><td>" +
          esc(it.username || it.email || it.user_id) +
          "</td><td>" +
          esc(it.product_code || "—") +
          '</td><td class="mono">' +
          esc(it.amount != null ? it.amount : it.principal_amount || "—") +
          "</td><td>" +
          esc(financialReturnModeLabelZh(it.return_mode)) +
          "</td><td>" +
          badge(it.status, financialSubscriptionStatusLabelZh(it.status)) +
          "</td><td>" +
          adminRowActions(
            adminBtn({
              variant: "info",
              sm: true,
              attrs: 'data-admin-financial-order-detail="' + esc(String(it.id)) + '"',
              label: "詳情"
            })
          ) +
          "</td></tr>"
        );
      },
      "尚無理財訂單"
    );
  }

  function openFinancialOrderDetail(id) {
    id = String(id || "").trim();
    if (!id || !api()) return;
    showModalById("admin-modal-financial-order-detail");
    setModalBodyHtml("view-financial-order-detail-body", '<p class="admin-modal__empty-note">載入理財訂單詳情中…</p>');
    api()
      .requestJson("/api/admin/financial-subscriptions/" + encodeURIComponent(id), { fallbackMessage: "載入理財訂單詳情失敗" })
      .then(function (data) {
        var it = (data && data.subscription) || {};
        var user = { id: it.user_id, username: it.username, email: it.email, mobile_e164: it.mobile_e164 };
        setModalBodyHtml(
          "view-financial-order-detail-body",
          detailRows([
            { label: "訂單 ID", value: it.id, mono: true },
            { label: "用戶", value: renderAdminUserSummaryHtml(user, it.user_id), raw: true },
            { label: "產品", value: it.product_code || "—", mono: true },
            { label: "本金", value: formatListingPriceDisplay(it.principal_amount || it.amount) + " USDT", mono: true },
            { label: "年化", value: it.apr_rate != null ? it.apr_rate + "%" : "—", mono: true },
            { label: "期限", value: it.term_days != null ? it.term_days + " 天" : "—" },
            { label: "返還模式", value: financialReturnModeLabelZh(it.return_mode) },
            { label: "狀態", value: badge(it.status, financialSubscriptionStatusLabelZh(it.status)), raw: true },
            { label: "返還管理員", value: it.returned_admin_name || "—" },
            { label: "返還時間", value: it.returned_at || "—", mono: true },
            { label: "建立時間", value: it.created_at || "—", mono: true },
            { label: "備註", value: it.remark || it.return_reason || "—" }
          ])
        );
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setModalBodyHtml("view-financial-order-detail-body", '<p class="admin-modal__empty-note">' + esc(e.message || "載入失敗") + "</p>");
      });
  }

  function formatListingIntegerForDisplay(val) {
    if (val === null || val === undefined || val === "") return "—";
    var n = Number(String(val).replace(/,/g, ""));
    if (!isFinite(n)) return String(val);
    return String(Math.round(n));
  }

  function formatListingRangeCell(minAmt, maxAmt) {
    var a = formatListingIntegerForDisplay(minAmt);
    var b = formatListingIntegerForDisplay(maxAmt);
    if (a === "—" && b === "—") return "—";
    return a + " ~ " + b;
  }

  /** 掛單價格：去掉尾隨 0（0.93 不顯示成 0.93000000） */
  function formatListingPriceDisplay(val) {
    if (val === null || val === undefined || val === "") return "—";
    var raw = String(val).trim().replace(/,/g, "");
    if (raw === "") return "—";
    var n = Number(raw);
    if (!isFinite(n)) return raw;
    var s = n.toFixed(12).replace(/\.?0+$/, "");
    if (s === "" || s === "-0") return "0";
    return s;
  }

  function syncListingSideTabUi() {
    var r = document.getElementById("view-listings");
    if (!r) return;
    var cur = r.getAttribute("data-listing-side") != null ? String(r.getAttribute("data-listing-side")).trim().toLowerCase() : "";
    if (cur !== "buy" && cur !== "sell") cur = "";
    r.setAttribute("data-listing-side", cur);
    r.querySelectorAll("[data-listing-side-tab]").forEach(function (btn) {
      var v = btn.getAttribute("data-listing-side-tab");
      var tabVal = v != null ? String(v).trim().toLowerCase() : "";
      if (tabVal !== "buy" && tabVal !== "sell") tabVal = "";
      var active = tabVal === cur;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-selected", active ? "true" : "false");
    });
  }

  function buildAdminListingsListPath() {
    var q = new URLSearchParams();
    var root = document.getElementById("view-listings");
    var sideRaw =
      root && root.getAttribute("data-listing-side") != null ? String(root.getAttribute("data-listing-side")).trim().toLowerCase() : "";
    if (sideRaw === "buy" || sideRaw === "sell") q.set("side", sideRaw);
    var kwEl = document.getElementById("view-listings-keyword");
    var ownEl = document.getElementById("view-listings-owner");
    var stEl = document.getElementById("view-listings-status");
    var kw = kwEl && kwEl.value ? String(kwEl.value).trim() : "";
    var ow = ownEl && ownEl.value ? String(ownEl.value).trim() : "";
    var st = stEl && stEl.value ? String(stEl.value).trim() : "";
    if (kw) q.set("keyword", kw);
    if (ow) q.set("owner_user_id", ow);
    if (st === "active" || st === "inactive") q.set("status", st);
    var qs = q.toString();
    return "/api/admin/listings" + (qs ? "?" + qs : "");
  }

  function getSelectedListingIds() {
    var out = [];
    Array.prototype.forEach.call(document.querySelectorAll(".view-listing-cb:checked"), function (cb) {
      var id = Number(cb.getAttribute("data-listing-id"));
      if (id) out.push(id);
    });
    return out;
  }

  function closeListingsBatchPanel() {
    var panel = document.getElementById("view-listings-batch-panel");
    var toggle = document.getElementById("view-listings-batch-toggle");
    if (panel) panel.hidden = true;
    if (toggle) toggle.setAttribute("aria-expanded", "false");
  }

  function runListingStatusSerial(ids, status) {
    var i = 0;
    function step() {
      if (i >= ids.length) return Promise.resolve();
      var id = ids[i++];
      return api()
        .requestJson("/api/admin/listings/" + encodeURIComponent(id), {
          method: "PATCH",
          body: { status: status },
          fallbackMessage: "批次更新掛單失敗"
        })
        .then(step);
    }
    return step();
  }

  function runListingDeleteSerial(ids, reason) {
    var i = 0;
    var body = { reason: reason != null ? String(reason) : "" };
    function step() {
      if (i >= ids.length) return Promise.resolve();
      var id = ids[i++];
      return api()
        .requestJson("/api/admin/listings/" + encodeURIComponent(id), {
          method: "DELETE",
          body: body,
          fallbackMessage: "批次刪除掛單失敗"
        })
        .then(step);
    }
    return step();
  }

  function bindViewListingsPage() {
    var root = document.getElementById("view-listings");
    if (!root || root.dataset.listingsPageBound === "1") return;
    root.dataset.listingsPageBound = "1";
    var batchPanel = document.getElementById("view-listings-batch-panel");
    var batchToggle = document.getElementById("view-listings-batch-toggle");

    root.addEventListener("change", function (e) {
      var t = e.target;
      var tb = document.getElementById("view-listings-tbody");
      var sa = document.getElementById("view-listings-select-all");
      if (t && t.classList && t.classList.contains("view-listing-cb") && tb && sa) {
        var all = tb.querySelectorAll(".view-listing-cb");
        var n = 0;
        all.forEach(function (cb) {
          if (cb.checked) n++;
        });
        sa.checked = all.length > 0 && n === all.length;
        sa.indeterminate = n > 0 && n < all.length;
        return;
      }
      if (!t || t.id !== "view-listings-select-all") return;
      var on = t.checked;
      if (!tb) return;
      tb.querySelectorAll(".view-listing-cb").forEach(function (cb) {
        cb.checked = on;
      });
      if (sa) sa.indeterminate = false;
    });

    if (batchToggle && batchPanel) {
      batchToggle.addEventListener("click", function (ev) {
        ev.stopPropagation();
        var open = batchPanel.hidden;
        batchPanel.hidden = !open;
        batchToggle.setAttribute("aria-expanded", open ? "true" : "false");
      });
    }

    root.addEventListener("click", function (e) {
      if (!batchPanel || !batchToggle || batchPanel.hidden) return;
      if (batchToggle.contains(e.target) || batchPanel.contains(e.target)) return;
      closeListingsBatchPanel();
    });

    function ensureIdsOrToast() {
      var ids = getSelectedListingIds();
      if (!ids.length) {
        adminNotifyError("請先勾選至少一筆掛單。");
        return null;
      }
      return ids;
    }

    var bAct = document.getElementById("view-listings-batch-activate");
    var bDeact = document.getElementById("view-listings-batch-deactivate");
    var bDel = document.getElementById("view-listings-batch-delete");

    if (bAct) {
      bAct.addEventListener("click", function () {
        if (!api()) return;
        var ids = ensureIdsOrToast();
        if (!ids) return;
        adminConfirm({
          title: "批次上架",
          message: "將已選取的 " + ids.length + " 筆掛單設為「上架中（啟用）」，確定？",
          confirmLabel: "確定上架"
        }).then(function (ok) {
          if (!ok) return;
          closeListingsBatchPanel();
          runListingStatusSerial(ids, "active")
            .then(function () {
              adminNotifySuccess("已更新 " + ids.length + " 筆為上架中。");
              reloadCurrentView();
            })
            .catch(function (err) {
              if (err && err.adminSessionHandled) return;
              adminNotifyError(err.message || "更新失敗");
              reloadCurrentView();
            });
        });
      });
    }

    if (bDeact) {
      bDeact.addEventListener("click", function () {
        if (!api()) return;
        var ids = ensureIdsOrToast();
        if (!ids) return;
        adminConfirm({
          title: "批次下架",
          message: "將已選取的 " + ids.length + " 筆掛單設為「已下架（停用）」，確定？",
          danger: true,
          confirmLabel: "確定下架"
        }).then(function (ok) {
          if (!ok) return;
          closeListingsBatchPanel();
          runListingStatusSerial(ids, "inactive")
            .then(function () {
              adminNotifySuccess("已更新 " + ids.length + " 筆為已下架。");
              reloadCurrentView();
            })
            .catch(function (err) {
              if (err && err.adminSessionHandled) return;
              adminNotifyError(err.message || "更新失敗");
              reloadCurrentView();
            });
        });
      });
    }

    if (bDel) {
      bDel.addEventListener("click", function () {
        if (!api()) return;
        var ids = ensureIdsOrToast();
        if (!ids) return;
        adminConfirm({
          title: "批次刪除",
          message: "確定刪除已選取的 " + ids.length + " 筆掛單？此操作無法復原。",
          danger: true,
          confirmLabel: "繼續"
        }).then(function (ok) {
          if (!ok) return;
          adminPrompt({
            title: "刪除原因（選填）",
            message: "可填寫同一原因供審計；留空亦可。",
            fieldLabel: "刪除原因",
            required: false,
            placeholder: "選填"
          }).then(function (delReason) {
            if (delReason === null) return;
            closeListingsBatchPanel();
            runListingDeleteSerial(ids, delReason)
              .then(function () {
                adminNotifySuccess("已刪除 " + ids.length + " 筆掛單。");
                reloadCurrentView();
              })
              .catch(function (err) {
                if (err && err.adminSessionHandled) return;
                adminNotifyError(err.message || "刪除失敗");
                reloadCurrentView();
              });
          });
        });
      });
    }

    root.querySelectorAll("[data-listing-side-tab]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var v = btn.getAttribute("data-listing-side-tab");
        var val = v != null ? String(v).trim().toLowerCase() : "";
        if (val !== "buy" && val !== "sell") val = "";
        root.setAttribute("data-listing-side", val);
        syncListingSideTabUi();
        loadViewListings();
      });
    });

    var resetBtn = document.getElementById("view-listings-reset");
    var queryBtn = document.getElementById("view-listings-query");
    var refreshBtn = document.getElementById("view-listings-refresh");
    if (resetBtn && !resetBtn._listingsFilterBound) {
      resetBtn._listingsFilterBound = true;
      resetBtn.addEventListener("click", function () {
        var kwEl = document.getElementById("view-listings-keyword");
        var ownEl = document.getElementById("view-listings-owner");
        var stEl = document.getElementById("view-listings-status");
        if (kwEl) kwEl.value = "";
        if (ownEl) ownEl.value = "";
        if (stEl) stEl.value = "";
        root.setAttribute("data-listing-side", "");
        syncListingSideTabUi();
        loadViewListings();
      });
    }
    if (queryBtn && !queryBtn._listingsFilterBound) {
      queryBtn._listingsFilterBound = true;
      queryBtn.addEventListener("click", function () {
        loadViewListings();
      });
    }
    if (refreshBtn && !refreshBtn._listingsFilterBound) {
      refreshBtn._listingsFilterBound = true;
      refreshBtn.addEventListener("click", function () {
        loadViewListings();
      });
    }

    syncListingSideTabUi();
  }

  function loadViewListings() {
    bindViewListingsPage();
    if (!api()) return Promise.resolve();
    var pager = adminPagerGet("view-listings-tbody");
    var requestPath = adminPagerRequestPath("view-listings-tbody", buildAdminListingsListPath());
    setTbodyLoading("view-listings-tbody", 9);
    return api()
      .requestJson(requestPath, { fallbackMessage: "載入掛單失敗" })
      .then(function (data) {
        var items = (data && data.items) || [];
        var tb = document.getElementById("view-listings-tbody");
        if (!tb) return;
        if (!items.length) {
          tb.innerHTML = tableEmptyRow(9, "尚無掛單", "");
          adminPagerRender("view-listings-tbody", (data && data.pagination) || { page: pager.page, page_size: pager.pageSize, total: 0 }, loadViewListings);
        } else {
          tb.innerHTML = items
            .map(function (it) {
              var cells =
                '<td><input type="checkbox" class="view-listing-cb" data-listing-id="' +
                esc(it.id) +
                '" aria-label="選取掛單 #' +
                esc(it.id) +
                '"/></td>' +
                "<td class=\"mono\">" +
                esc(it.id) +
                "</td><td>" +
                esc(it.nickname || it.owner_username || it.owner_user_id) +
                '<div class="admin-cell-sub">來源：' +
                sourceBadge(it.owner_source) +
                "</div>" +
                "</td><td>" +
                esc(formatC2cOrderSideLabel(it.side)) +
                '</td><td class="mono">' +
                esc(formatListingPriceDisplay(it.price)) +
                '</td><td class="mono">' +
                esc(formatListingRangeCell(it.min_amount, it.max_amount)) +
                '</td><td class="mono">' +
                esc(it.available_amount || "—") +
                "</td><td>" +
                badge(it.status, listingStatusLabelZh(it.status)) +
                "</td><td>" +
                adminRowActions(
                  adminBtn({
                    variant: "ghost",
                    sm: true,
                    attrs: 'data-admin-listing-open-detail="' + esc(String(it.id)) + '"',
                    label: "編輯"
                  }) +
                  adminBtn({
                    variant: "danger",
                    sm: true,
                    attrs: 'data-admin-listing-delete="' + esc(String(it.id)) + '"',
                    label: "刪除"
                  })
                ) +
                "</td>";
              return "<tr>" + cells + "</tr>";
            })
            .join("");
          adminPagerRender("view-listings-tbody", data && data.pagination, loadViewListings);
        }
        var sa = document.getElementById("view-listings-select-all");
        if (sa) {
          sa.checked = false;
          sa.indeterminate = false;
        }
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setTbodyError("view-listings-tbody", e.message, 9);
      });
  }

  function loadViewTradeFeed() {
    return loadItems(
      buildTradeFeedPath(),
      "view-trade-feed-tbody",
      7,
      function (it) {
        return (
          "<tr><td class=\"mono\">" +
          esc(it.id) +
          "</td><td>" +
          esc(it.title_display || it.title || "—") +
          "</td><td>" +
          esc(it.actor_name || "—") +
          '</td><td class="mono">' +
          esc(formatTradeFeedAmount(it.amount, it.asset_code)) +
          '</td><td class="mono">' +
          esc(it.sort_order != null ? it.sort_order : "—") +
          "</td><td>" +
          badge(it.status, contentItemStatusLabelZh(it.status)) +
          "</td><td>" +
          adminRowActions(
            adminBtn({
              variant: "ghost",
              sm: true,
              attrs: 'data-admin-tf-open-detail="' + esc(String(it.id)) + '"',
              label: "編輯"
            })
          ) +
          "</td></tr>"
        );
      },
      "尚無成交播報"
    );
  }

  function buildTradeFeedPath() {
    var q = new URLSearchParams();
    var kw = queryValue("view-tf-keyword");
    var st = queryValue("view-tf-status");
    if (kw) q.set("keyword", kw);
    if (st) q.set("status", st);
    var qs = q.toString();
    return "/api/admin/trade-feed-events" + (qs ? "?" + qs : "");
  }

  function loadViewHomeContent() {
    if (!api()) return Promise.resolve();
    return api()
      .requestJson("/api/admin/home-content", { fallbackMessage: "載入運營配置失敗" })
      .then(function (data) {
        var ta = document.getElementById("view-home-notice-text");
        if (ta) ta.value = data && data.notice != null ? String(data.notice) : "";
        var banners = (data && data.banners) || [];
        var tb = document.getElementById("view-home-banners-tbody");
        if (tb) {
          if (!banners.length) {
            tb.innerHTML =
              tableEmptyRow(6, "尚無橫幅", "可點「新增橫幅」建立素材，或切換至其他子模組。");
          } else {
            tb.innerHTML = banners
              .map(function (b) {
                return (
                  "<tr><td class=\"mono\">" +
                  esc(b.id) +
                  "</td><td>" +
                  esc(b.title) +
                  "</td><td>" +
                  esc(b.badge_text || "—") +
                  '</td><td class="mono">' +
                  esc(b.sort_order != null ? b.sort_order : "—") +
                  "</td><td>" +
                  badge(b.status, contentItemStatusLabelZh(b.status)) +
                  "</td><td>" +
                  adminRowActions(
                    adminBtn({
                      variant: "ghost",
                      sm: true,
                      attrs: 'data-admin-hb-open-detail="' + esc(String(b.id)) + '"',
                      label: "編輯"
                    })
                  ) +
                  "</td></tr>"
                );
              })
              .join("");
          }
        }
        var tutorials = (data && data.tutorial_links) || [];
        var ttb = document.getElementById("view-home-tutorial-tbody");
        if (ttb) {
          if (!tutorials.length) {
            ttb.innerHTML =
              tableEmptyRow(6, "尚無教學連結", "可點「新增教學項目」維護首頁教學區塊。");
          } else {
            ttb.innerHTML = tutorials
              .map(function (t) {
                return (
                  "<tr><td class=\"mono\">" +
                  esc(t.id) +
                  "</td><td>" +
                  esc(t.title || "—") +
                  '</td><td class="mono" style="max-width:220px;overflow:hidden;text-overflow:ellipsis">' +
                  esc(t.link_url || "—") +
                  '</td><td class="mono">' +
                  esc(t.sort_order != null ? t.sort_order : "—") +
                  "</td><td>" +
                  badge(t.status, contentItemStatusLabelZh(t.status)) +
                  "</td><td>" +
                  adminRowActions(
                    adminBtn({
                      variant: "ghost",
                      sm: true,
                      attrs: 'data-admin-tl-open-detail="' + esc(String(t.id)) + '"',
                      label: "編輯"
                    })
                  ) +
                  "</td></tr>"
                );
              })
              .join("");
          }
        }
        return Promise.resolve();
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        var ta = document.getElementById("view-home-notice-text");
        if (ta) ta.placeholder = e.message || "載入失敗";
      });
  }

  function activeKycStatusTabValue() {
    var active = document.querySelector("#view-kyc .admin-kyc-status-tabs__btn.is-active");
    if (!active) return "all";
    var v = String(active.getAttribute("data-kyc-status-tab") || "all").toLowerCase();
    if (v === "approved" || v === "pending") return v;
    return "all";
  }

  function syncKycStatusTabAria() {
    document.querySelectorAll("#view-kyc .admin-kyc-status-tabs__btn").forEach(function (btn) {
      var on = btn.classList.contains("is-active");
      btn.setAttribute("aria-selected", on ? "true" : "false");
    });
  }

  function buildAdminKycListPath() {
    var q = new URLSearchParams();
    var kwEl = document.getElementById("view-kyc-keyword");
    var userEl = document.getElementById("view-kyc-user");
    var kw = kwEl && kwEl.value ? String(kwEl.value).trim() : "";
    var userLookup = userEl && userEl.value ? String(userEl.value).trim() : "";
    if (kw) q.set("keyword", kw);
    if (userLookup) q.set("user_id", userLookup);
    var tab = activeKycStatusTabValue();
    if (tab === "approved") q.set("status", "approved");
    else if (tab === "pending") q.set("status", "pending");
    var qs = q.toString();
    return "/api/admin/kyc-applications" + (qs ? "?" + qs : "");
  }

  function kycImagePreviewBlock(url, emptyNote) {
    var u = url != null && String(url).trim() !== "" ? String(url).trim() : "";
    if (!u) {
      return '<div class="admin-muted">' + esc(emptyNote || "未上傳") + "</div>";
    }
    return (
      '<button type="button" class="admin-kyc-preview-thumb" data-kyc-preview-src="' +
      esc(u) +
      '" aria-label="預覽原圖">' +
      '<img src="' +
      esc(u) +
      '" alt="" loading="lazy" decoding="async"/>' +
      '<span class="admin-kyc-preview-thumb__hint">點擊預覽原圖</span></button>'
    );
  }

  function closeKycDetailZoom() {
    var z = document.getElementById("view-kyc-detail-img-zoom");
    var img = document.getElementById("view-kyc-detail-zoom-img");
    if (img) img.removeAttribute("src");
    if (z) {
      z.hidden = true;
      z.setAttribute("aria-hidden", "true");
    }
  }

  function openKycDetailZoom(src) {
    var z = document.getElementById("view-kyc-detail-img-zoom");
    var img = document.getElementById("view-kyc-detail-zoom-img");
    if (!z || !img || !src) return;
    img.src = src;
    z.hidden = false;
    z.setAttribute("aria-hidden", "false");
  }

  window.__adminCloseKycPreviewZoom = closeKycDetailZoom;

  function renderKycDetailMeta(app) {
    var rows = [
      ["申請 ID", app.id],
      ["用戶 ID", app.user_id],
      ["用戶名", app.username || "—"],
      ["郵箱", app.email || "—"],
      ["手機", app.mobile_e164 || "—"],
      ["姓名", app.legal_name || "—"],
      ["證件號", app.id_number_masked || "—"],
      ["狀態", kycStatusLabelZh(app.status)],
      ["審核備註", app.review_note || "—"],
      ["提交時間", app.submitted_at || "—"],
      ["審核時間", app.reviewed_at || "—"]
    ];
    return rows
      .map(function (pair) {
        return "<div><dt>" + esc(pair[0]) + "</dt><dd>" + esc(pair[1] != null ? String(pair[1]) : "—") + "</dd></div>";
      })
      .join("");
  }

  function openKycDetailModal(applicationId) {
    if (!api()) return;
    var modal = document.getElementById("admin-modal-kyc-detail");
    var msg = document.getElementById("view-kyc-detail-msg");
    var loading = document.getElementById("view-kyc-detail-loading");
    var body = document.getElementById("view-kyc-detail-body");
    var meta = document.getElementById("view-kyc-detail-meta");
    var pf = document.getElementById("view-kyc-preview-front");
    var pb = document.getElementById("view-kyc-preview-back");
    var ps = document.getElementById("view-kyc-preview-selfie");
    var actions = document.getElementById("view-kyc-detail-actions");
    if (msg) msg.textContent = "";
    if (loading) {
      loading.style.display = "";
      loading.textContent = "載入中…";
    }
    if (body) body.style.display = "none";
    if (modal) modal.dataset.kycAppId = String(applicationId);
    api()
      .requestJson("/api/admin/kyc-applications/" + encodeURIComponent(applicationId), {
        fallbackMessage: "載入 KYC 詳情失敗"
      })
      .then(function (data) {
        var app = data && data.application;
        if (!app) throw new Error("無申請資料");
        if (meta) meta.innerHTML = renderKycDetailMeta(app);
        if (pf) pf.innerHTML = kycImagePreviewBlock(app.id_doc_front_url, "未上傳證件正面");
        if (pb) pb.innerHTML = kycImagePreviewBlock(app.id_doc_back_url, "未上傳證件反面");
        if (ps) ps.innerHTML = kycImagePreviewBlock(app.selfie_url, "未上傳自拍／人臉");
        var pending = String(app.status || "").toLowerCase() === "pending";
        if (actions) actions.style.display = pending ? "" : "none";
        if (loading) loading.style.display = "none";
        if (body) body.style.display = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-kyc-detail");
        }
      })
      .catch(function (e) {
        if (loading) loading.textContent = e.message || "載入失敗";
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "載入失敗");
      });
  }

  function syncKycReviewReasonField() {
    var act = document.getElementById("view-kyc-review-action");
    var wrap = document.getElementById("view-kyc-review-reason-wrap");
    var inp = document.getElementById("view-kyc-review-reason");
    var need = act && String(act.value || "") === "reject";
    if (wrap) wrap.style.display = need ? "" : "none";
    if (inp && !need) inp.value = "";
  }

  function initKycAdminUi() {
    if (document.documentElement._adminKycUiBound) return;
    document.documentElement._adminKycUiBound = true;

    var actSel = document.getElementById("view-kyc-review-action");
    if (actSel) actSel.addEventListener("change", syncKycReviewReasonField);

    var detailModal = document.getElementById("admin-modal-kyc-detail");
    var zoom = document.getElementById("view-kyc-detail-img-zoom");
    if (zoom) {
      zoom.querySelectorAll("[data-kyc-preview-zoom-close]").forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          closeKycDetailZoom();
        });
      });
      zoom.addEventListener("click", function (e) {
        if (e.target === zoom) closeKycDetailZoom();
      });
    }
    if (detailModal) {
      detailModal.addEventListener("click", function (e) {
        var btn = e.target && e.target.closest ? e.target.closest(".admin-kyc-preview-thumb") : null;
        if (!btn || !detailModal.contains(btn)) return;
        e.preventDefault();
        openKycDetailZoom(btn.getAttribute("data-kyc-preview-src"));
      });
    }

    var reviewForm = document.getElementById("view-kyc-review-form");
    if (reviewForm && !reviewForm._adminKycReviewBound) {
      reviewForm._adminKycReviewBound = true;
      reviewForm.addEventListener("submit", function (ev) {
        ev.preventDefault();
        var msg = document.getElementById("view-kyc-review-msg");
        if (msg) msg.textContent = "";
        var hid = document.getElementById("view-kyc-review-app-id");
        var id = hid ? Number(String(hid.value || "").trim()) : 0;
        if (!id) {
          if (msg) msg.textContent = "缺少申請 ID。";
          return;
        }
        var action = (document.getElementById("view-kyc-review-action") || {}).value || "approve";
        var reason = String((document.getElementById("view-kyc-review-reason") || {}).value || "").trim();
        if (action === "reject" && !reason) {
          if (msg) msg.textContent = "駁回必須填寫原因。";
          return;
        }
        var submitBtn = document.getElementById("view-kyc-review-submit");
        if (submitBtn) submitBtn.disabled = true;
        api()
          .requestJson("/api/admin/kyc-applications/" + id, {
            method: "PATCH",
            body: { action: action, reason: action === "approve" ? reason || "審核通過" : reason },
            fallbackMessage: "審核失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已提交。";
            if (window.AdminModals && window.AdminModals.close) {
              var m = document.getElementById("admin-modal-kyc-review");
              if (m) window.AdminModals.close(m);
            }
            return loadViewKyc();
          })
          .catch(function (err) {
            if (msg) msg.textContent = err.message || "審核失敗";
          })
          .then(function () {
            if (submitBtn) submitBtn.disabled = false;
          });
      });
    }

    var detailModal = document.getElementById("admin-modal-kyc-detail");
    if (detailModal && !detailModal._adminKycDetailActionsBound) {
      detailModal._adminKycDetailActionsBound = true;
      detailModal.addEventListener("click", function (e) {
        var approveBtn = e.target && e.target.closest ? e.target.closest("#view-kyc-detail-approve") : null;
        var rejectBtn = e.target && e.target.closest ? e.target.closest("#view-kyc-detail-reject") : null;
        if (!approveBtn && !rejectBtn) return;
        if (!detailModal.contains(approveBtn || rejectBtn)) return;
        var id = Number(detailModal.dataset.kycAppId || "0");
        if (!id || !api()) return;
        if (approveBtn) {
          adminConfirm({
            title: "通過實名",
            message: "確定通過實名申請 #" + id + "？",
            confirmLabel: "確定通過"
          }).then(function (ok) {
            if (!ok) return;
            approveBtn.disabled = true;
            api()
              .requestJson("/api/admin/kyc-applications/" + id, {
                method: "PATCH",
                body: { action: "approve", reason: "審核通過" },
                fallbackMessage: "審核失敗"
              })
              .then(function () {
                if (window.AdminModals && window.AdminModals.close) {
                  window.AdminModals.close(detailModal);
                }
                return loadViewKyc();
              })
              .catch(function (err) {
                adminNotifyError(err.message || "審核失敗");
              })
              .then(function () {
                approveBtn.disabled = false;
              });
          });
          return;
        }
        adminPrompt({
          title: "駁回實名",
          message: "請輸入駁回原因（必填）。",
          fieldLabel: "駁回原因",
          required: true,
          requiredMessage: "駁回必須填寫原因。"
        }).then(function (reason) {
          if (reason == null) return;
          reason = String(reason).trim();
          if (!reason) return;
          adminConfirm({
            title: "駁回實名",
            message: "確定駁回實名申請 #" + id + "？",
            danger: true,
            confirmLabel: "確定駁回"
          }).then(function (ok) {
            if (!ok) return;
            rejectBtn.disabled = true;
            api()
              .requestJson("/api/admin/kyc-applications/" + id, {
                method: "PATCH",
                body: { action: "reject", reason: reason },
                fallbackMessage: "審核失敗"
              })
              .then(function () {
                if (window.AdminModals && window.AdminModals.close) {
                  window.AdminModals.close(detailModal);
                }
                return loadViewKyc();
              })
              .catch(function (err) {
                adminNotifyError(err.message || "審核失敗");
              })
              .then(function () {
                rejectBtn.disabled = false;
              });
          });
        });
      });
    }
  }

  function openKycReviewModal(applicationId) {
    var hid = document.getElementById("view-kyc-review-app-id");
    var msg = document.getElementById("view-kyc-review-msg");
    var act = document.getElementById("view-kyc-review-action");
    var reason = document.getElementById("view-kyc-review-reason");
    if (hid) hid.value = String(applicationId);
    if (act) act.value = "approve";
    if (reason) reason.value = "";
    if (msg) msg.textContent = "";
    syncKycReviewReasonField();
    if (window.AdminModals && window.AdminModals.open) {
      window.AdminModals.open("admin-modal-kyc-review");
    }
  }

  function getSelectedPendingKycIds() {
    var out = [];
    Array.prototype.forEach.call(document.querySelectorAll(".view-kyc-cb:checked"), function (cb) {
      var id = Number(cb.getAttribute("data-kyc-id"));
      if (id) out.push(id);
    });
    return out;
  }

  function runKycReviewSerial(ids, action, reason, onProgress) {
    var i = 0;
    function step() {
      if (i >= ids.length) return Promise.resolve();
      var id = ids[i++];
      return api()
        .requestJson("/api/admin/kyc-applications/" + id, {
          method: "PATCH",
          body: { action: action, reason: action === "approve" ? reason || "審核通過" : reason },
          fallbackMessage: "KYC 審核失敗"
        })
        .then(function () {
          if (typeof onProgress === "function") onProgress(i, ids.length, id);
        })
        .then(step);
    }
    return step();
  }

  function bindViewKycPage() {
    var root = document.getElementById("view-kyc");
    if (!root || root.dataset.kycPageBound === "1") return;
    root.dataset.kycPageBound = "1";
    initKycAdminUi();

    var qBtn = document.getElementById("view-kyc-query");
    var rBtn = document.getElementById("view-kyc-refresh");
    var resetBtn = document.getElementById("view-kyc-reset");
    if (qBtn) qBtn.addEventListener("click", function () { loadViewKyc(); });
    if (rBtn) rBtn.addEventListener("click", function () { loadViewKyc(); });
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        var kwEl = document.getElementById("view-kyc-keyword");
        var userEl = document.getElementById("view-kyc-user");
        if (kwEl) kwEl.value = "";
        if (userEl) userEl.value = "";
        document.querySelectorAll("#view-kyc .admin-kyc-status-tabs__btn").forEach(function (btn) {
          var isAll = String(btn.getAttribute("data-kyc-status-tab") || "") === "all";
          btn.classList.toggle("is-active", isAll);
        });
        syncKycStatusTabAria();
        loadViewKyc();
      });
    }
    root.addEventListener("change", function (e) {
      var t = e.target;
      var tb = document.getElementById("view-kyc-tbody");
      var sa = document.getElementById("view-kyc-select-all");
      if (t && t.classList && t.classList.contains("view-kyc-cb") && tb && sa) {
        var all = tb.querySelectorAll(".view-kyc-cb");
        var n = 0;
        all.forEach(function (cb) { if (cb.checked) n++; });
        sa.checked = all.length > 0 && n === all.length;
        sa.indeterminate = n > 0 && n < all.length;
        return;
      }
      if (!t || t.id !== "view-kyc-select-all") return;
      if (!tb) return;
      var boxes = tb.querySelectorAll(".view-kyc-cb");
      boxes.forEach(function (cb) { cb.checked = t.checked; });
      if (sa) sa.indeterminate = false;
      if (t.checked && boxes.length === 0) {
        t.checked = false;
        adminNotifyError("本頁沒有待審 KYC 可勾選。");
      }
    });
    var batchApprove = document.getElementById("view-kyc-batch-approve");
    var batchReject = document.getElementById("view-kyc-batch-reject");
    if (batchApprove) {
      batchApprove.addEventListener("click", function () {
        if (!api()) return;
        var ids = getSelectedPendingKycIds();
        if (!ids.length) {
          adminNotifyError("請先勾選待審 KYC。");
          return;
        }
        adminConfirm({ title: "批量通過 KYC", message: "確定通過 " + ids.length + " 筆 KYC 申請？", confirmLabel: "確定通過" }).then(function (ok) {
          if (!ok) return;
          setActionLoading(batchApprove, true, "處理中...");
          if (batchReject) setActionLoading(batchReject, true, "處理中...");
          setBatchProgress(0, ids.length, "批次通過KYC");
          runKycReviewSerial(ids, "approve", "批量審核通過", function (done, total) {
            setBatchProgress(done, total, "批次通過KYC");
          })
            .then(function () {
              adminNotifySuccess("已通過 " + ids.length + " 筆 KYC。");
              clearBatchProgress(900);
              return loadViewKyc();
            })
            .catch(function (e) {
              if (e && e.adminSessionHandled) return;
              adminNotifyError(e.message || "KYC 批量審核失敗");
              clearBatchProgress();
              loadViewKyc();
            })
            .then(function () {
              setActionLoading(batchApprove, false);
              if (batchReject) setActionLoading(batchReject, false);
            });
        });
      });
    }
    if (batchReject) {
      batchReject.addEventListener("click", function () {
        if (!api()) return;
        var ids = getSelectedPendingKycIds();
        if (!ids.length) {
          adminNotifyError("請先勾選待審 KYC。");
          return;
        }
        adminPrompt({ title: "批量駁回 KYC", message: "請輸入駁回原因（必填）。", fieldLabel: "駁回原因", required: true, requiredMessage: "駁回必須填寫原因。" }).then(function (reason) {
          if (reason == null) return;
          reason = String(reason).trim();
          if (!reason) return;
          setActionLoading(batchReject, true, "處理中...");
          if (batchApprove) setActionLoading(batchApprove, true, "處理中...");
          setBatchProgress(0, ids.length, "批次駁回KYC");
          runKycReviewSerial(ids, "reject", reason, function (done, total) {
            setBatchProgress(done, total, "批次駁回KYC");
          })
            .then(function () {
              adminNotifySuccess("已駁回 " + ids.length + " 筆 KYC。");
              clearBatchProgress(900);
              return loadViewKyc();
            })
            .catch(function (e) {
              if (e && e.adminSessionHandled) return;
              adminNotifyError(e.message || "KYC 批量審核失敗");
              clearBatchProgress();
              loadViewKyc();
            })
            .then(function () {
              setActionLoading(batchReject, false);
              if (batchApprove) setActionLoading(batchApprove, false);
            });
        });
      });
    }

    root.addEventListener("click", function (e) {
      var tabBtn = e.target && e.target.closest ? e.target.closest("[data-kyc-status-tab]") : null;
      if (tabBtn && root.contains(tabBtn)) {
        e.preventDefault();
        document.querySelectorAll("#view-kyc .admin-kyc-status-tabs__btn").forEach(function (btn) {
          btn.classList.toggle("is-active", btn === tabBtn);
        });
        syncKycStatusTabAria();
        loadViewKyc();
        return;
      }
      var detailBtn = e.target && e.target.closest ? e.target.closest("[data-kyc-open-detail]") : null;
      if (detailBtn && root.contains(detailBtn)) {
        e.preventDefault();
        var kid = Number(detailBtn.getAttribute("data-kyc-open-detail"));
        if (kid) openKycDetailModal(kid);
        return;
      }
      var reviewBtn = e.target && e.target.closest ? e.target.closest("[data-kyc-open-review]") : null;
      if (reviewBtn && root.contains(reviewBtn)) {
        e.preventDefault();
        var rid = Number(reviewBtn.getAttribute("data-kyc-open-review"));
        if (rid) openKycReviewModal(rid);
        return;
      }
      var approveBtn = e.target && e.target.closest ? e.target.closest("[data-kyc-row-approve]") : null;
      if (!approveBtn || !root.contains(approveBtn)) return;
      if (!api()) return;
      var id = Number(approveBtn.getAttribute("data-kyc-row-approve"));
      if (!id) return;
      adminConfirm({
        title: "通過實名",
        message: "確定通過實名申請 #" + id + "？",
        confirmLabel: "確定通過"
      }).then(function (ok) {
        if (!ok) return;
        approveBtn.disabled = true;
        api()
          .requestJson("/api/admin/kyc-applications/" + id, {
            method: "PATCH",
            body: { action: "approve", reason: "審核通過" },
            fallbackMessage: "審核失敗"
          })
          .then(function () {
            return loadViewKyc();
          })
          .catch(function (err) {
            if (err && err.adminSessionHandled) return;
            adminNotifyError(err.message || "審核失敗");
            loadViewKyc();
          })
          .then(function () {
            approveBtn.disabled = false;
          });
      });
    });
  }

  function loadViewKyc() {
    bindViewKycPage();
    return loadItems(
      buildAdminKycListPath(),
      "view-kyc-tbody",
      6,
      function (it, index) {
        var st = String(it.status || "").toLowerCase();
        var pending = st === "pending";
        var uid = it.user_id != null ? it.user_id : "";
        var userCell = renderAdminUserSummaryCell(it, uid);
        var cb = pending
          ? '<td><input type="checkbox" class="view-kyc-cb" data-kyc-id="' + esc(String(it.id)) + '" aria-label="選取 KYC #' + esc(String(it.id)) + '"/></td>'
          : '<td class="mono admin-muted" title="僅待審核可批次勾選">—</td>';
        return (
          "<tr>" +
          cb +
          "<td class=\"mono\">" +
          esc(sequenceNo(index)) +
          "</td>" +
          userCell +
          "<td>" +
          esc(it.legal_name || "—") +
          '</td><td class="mono">' +
          esc(it.id_number_masked || "—") +
          "</td><td>" +
          adminRowActions(
            (pending
              ? adminBtn({
                  variant: "success",
                  sm: true,
                  attrs: 'data-kyc-row-approve="' + esc(String(it.id)) + '"',
                  label: "通過"
                }) +
                adminBtn({
                  variant: "warning",
                  sm: true,
                  attrs: 'data-kyc-open-review="' + esc(String(it.id)) + '"',
                  label: "審核"
                })
              : "") +
              adminBtn({
                variant: "info",
                sm: true,
                attrs: 'data-kyc-open-detail="' + esc(String(it.id)) + '"',
                label: "詳情"
              })
          ) +
          "</td></tr>"
        );
      },
      "尚無 KYC 申請",
      function () {},
      { useSkeleton: true }
    );
  }

  function buildAdminPayoutMethodsPath() {
    var q = new URLSearchParams();
    var kwEl = document.getElementById("view-pm-keyword");
    var userEl = document.getElementById("view-pm-user");
    var chEl = document.getElementById("view-pm-channel");
    var kw = kwEl && kwEl.value ? String(kwEl.value).trim() : "";
    var userLookup = userEl && userEl.value ? String(userEl.value).trim() : "";
    var ch = chEl && chEl.value ? String(chEl.value).trim() : "";
    if (kw) q.set("keyword", kw);
    if (userLookup) q.set("user_id", userLookup);
    if (ch) q.set("channel_type", ch);
    var qs = q.toString();
    return "/api/admin/payout-methods" + (qs ? "?" + qs : "");
  }

  function payoutMethodCardTitle(it) {
    var c = String(it.channel_type || "").toLowerCase();
    if (c === "bank") return "銀行卡";
    if (c === "pix") return "PIX";
    if (c === "usdt") return "鏈上地址";
    return "其他方式";
  }

  function aggregatePayoutMethodsByUser(items) {
    var byUser = {};
    items.forEach(function (it) {
      var uid = it.user_id != null ? String(it.user_id) : "";
      if (!uid) return;
      if (!byUser[uid]) {
        byUser[uid] = {
          user_id: it.user_id,
          username: it.username || "",
          invitation_code: it.invitation_code || "",
          display_code: it.display_code || "",
          email: it.email || "",
          mobile_e164: it.mobile_e164 || "",
          items: [],
          bank_count: 0,
          address_count: 0
        };
      }
      byUser[uid].items.push(it);
      var ch = String(it.channel_type || "").toLowerCase();
      if (ch === "bank") byUser[uid].bank_count += 1;
      else byUser[uid].address_count += 1;
    });
    var list = Object.keys(byUser).map(function (k) {
      return byUser[k];
    });
    list.sort(function (a, b) {
      return Number(b.user_id || 0) - Number(a.user_id || 0);
    });
    return list;
  }

  function renderPayoutMethodCards(items) {
    if (!items.length) return '<div class="admin-muted">此用戶暫無收款方式。</div>';
    return items
      .map(function (it) {
        var ch = String(it.channel_type || "").toLowerCase();
        var bankLine = it.bank_name
          ? '<div class="admin-cell-sub">銀行：' + esc(it.bank_name) + "</div>"
          : "";
        var accountLine = it.account_no_masked
          ? '<div class="admin-cell-sub mono">卡號：' + esc(it.account_no_masked) + "</div>"
          : "";
        var addrLine = it.payout_address
          ? '<div class="admin-cell-sub mono">地址：' + esc(it.payout_address) + "</div>"
          : "";
        var netLine = it.usdt_network
          ? '<div class="admin-cell-sub">網路：' + esc(chainNetworkLabelZh(it.usdt_network)) + "</div>"
          : "";
        return (
          '<div class="admin-card" style="margin-bottom:10px">' +
          "<div><div>" +
          esc(payoutMethodCardTitle(it)) +
          "</div>" +
          (ch === "bank" ? bankLine + accountLine : addrLine + netLine) +
          '<div class="admin-cell-sub mono">方式 ID：' +
          esc(it.id) +
          "</div></div></div>"
        );
      })
      .join("");
  }

  function openPayoutUserDetailModal(userId) {
    if (!api()) return;
    var emptyEl = document.getElementById("view-pm-detail-empty");
    var bodyEl = document.getElementById("view-pm-detail-body");
    var sumEl = document.getElementById("view-pm-detail-summary");
    var cardsEl = document.getElementById("view-pm-detail-cards");
    if (emptyEl) emptyEl.style.display = "";
    if (bodyEl) bodyEl.style.display = "none";
    if (emptyEl) emptyEl.textContent = "載入中…";
    api()
      .requestJson("/api/admin/payout-methods?page=1&page_size=200&user_id=" + encodeURIComponent(userId), {
        fallbackMessage: "載入收款方詳情失敗"
      })
      .then(function (data) {
        var items = (data && data.items) || [];
        var bankCount = 0;
        var addressCount = 0;
        items.forEach(function (it) {
          if (String(it.channel_type || "").toLowerCase() === "bank") bankCount += 1;
          else addressCount += 1;
        });
        if (sumEl) {
          sumEl.innerHTML =
            '用戶 <span class="mono">#' +
            esc(userId) +
            "</span>　銀行卡 <strong>" +
            esc(bankCount) +
            "</strong> 張　地址 <strong>" +
            esc(addressCount) +
            "</strong> 個";
        }
        if (cardsEl) cardsEl.innerHTML = renderPayoutMethodCards(items);
        if (emptyEl) emptyEl.style.display = "none";
        if (bodyEl) bodyEl.style.display = "";
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-payout-detail");
        }
      })
      .catch(function (err) {
        if (emptyEl) emptyEl.textContent = err.message || "載入失敗";
      });
  }

  function bindViewPayoutMethodsPage() {
    var root = document.getElementById("view-payout-methods");
    if (!root || root.dataset.payoutPageBound === "1") return;
    root.dataset.payoutPageBound = "1";
    var qBtn = document.getElementById("view-pm-query");
    var rBtn = document.getElementById("view-pm-refresh");
    var resetBtn = document.getElementById("view-pm-reset");
    if (qBtn) qBtn.addEventListener("click", function () { loadViewPayoutMethods(); });
    if (rBtn) rBtn.addEventListener("click", function () { loadViewPayoutMethods(); });
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        var kwEl = document.getElementById("view-pm-keyword");
        var userEl = document.getElementById("view-pm-user");
        var chEl = document.getElementById("view-pm-channel");
        if (kwEl) kwEl.value = "";
        if (userEl) userEl.value = "";
        if (chEl) chEl.value = "";
        loadViewPayoutMethods();
      });
    }
    root.addEventListener("click", function (e) {
      var detailBtn = e.target && e.target.closest ? e.target.closest("[data-pm-user-detail]") : null;
      if (!detailBtn || !root.contains(detailBtn)) return;
      var userId = Number(detailBtn.getAttribute("data-pm-user-detail"));
      if (!userId) return;
      openPayoutUserDetailModal(userId);
    });
  }

  function loadViewPayoutMethods() {
    bindViewPayoutMethodsPage();
    if (!api()) return Promise.resolve();
    var pager = adminPagerGet("view-payout-methods-tbody");
    var requestPath = adminPagerRequestPath("view-payout-methods-tbody", buildAdminPayoutMethodsPath());
    setTbodyLoading("view-payout-methods-tbody", 5);
    return api()
      .requestJson(requestPath, { fallbackMessage: "載入失敗" })
      .then(function (data) {
        var tb = document.getElementById("view-payout-methods-tbody");
        if (!tb) return;
        var users = aggregatePayoutMethodsByUser((data && data.items) || []);
        if (!users.length) {
          tb.innerHTML = tableEmptyRow(5, "尚無收款方式", "請調整關鍵字或用戶條件後重新查詢。");
          adminPagerRender("view-payout-methods-tbody", (data && data.pagination) || { page: pager.page, page_size: pager.pageSize, total: 0 }, loadViewPayoutMethods);
          return;
        }
        tb.innerHTML = users
          .map(function (it, index) {
            return (
              "<tr><td class=\"mono\">" +
              esc(sequenceNo(index)) +
              "</td>" +
              renderAdminUserSummaryCell(it, it.user_id) +
              '<td><span class="mono">' +
              esc(it.address_count) +
              '</span> 個</td><td><span class="mono">' +
              esc(it.bank_count) +
              "</span> 張</td><td>" +
              adminRowActions(
                adminBtn({
                  variant: "info",
                  sm: true,
                  attrs: 'data-pm-user-detail="' + esc(it.user_id) + '"',
                  label: "詳情"
                })
              ) +
              "</td></tr>"
            );
          })
          .join("");
        adminPagerRender("view-payout-methods-tbody", data && data.pagination, loadViewPayoutMethods);
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setTbodyError("view-payout-methods-tbody", e.message, 5);
      });
  }

  function loadViewAuthEvents() {
    var root = document.getElementById("view-auth-events");
    var currentTab = root && root.getAttribute("data-auth-log-tab") ? String(root.getAttribute("data-auth-log-tab")) : "auth";
    if (currentTab === "admin") {
      return loadRecentAdminOps("view-auth-events-admin-tbody");
    }
    return loadItems(
      buildAuthEventsPath(),
      "view-auth-events-tbody",
      7,
      function (it) {
        return (
          "<tr><td class=\"mono\">" +
          esc(it.id) +
          "</td><td>" +
          esc(authEventActionLabelZh(it.action)) +
          "</td><td>" +
          esc(it.account || "—") +
          '</td><td class="mono">' +
          esc(it.error_code ? authErrorCodeLabelZh(it.error_code) : it.msg ? String(it.msg) : "—") +
          "</td><td class=\"mono\">" +
          esc(it.ip || "—") +
          "</td><td class=\"mono\">" +
          esc(it.created_at || "") +
          "</td><td>" +
          adminRowActions(
            adminBtn({
              variant: "info",
              sm: true,
              attrs: 'data-admin-auth-event-detail="' + esc(String(it.id)) + '"',
              label: "詳情"
            })
          ) +
          "</td></tr>"
        );
      },
      "尚無認證事件"
    );
  }

  function syncAuthLogTabUi() {
    var root = document.getElementById("view-auth-events");
    if (!root) return;
    var currentTab = root.getAttribute("data-auth-log-tab") || "auth";
    root.querySelectorAll("[data-auth-log-tab]").forEach(function (btn) {
      var active = String(btn.getAttribute("data-auth-log-tab") || "") === currentTab;
      btn.classList.toggle("is-active", active);
      btn.setAttribute("aria-selected", active ? "true" : "false");
      btn.tabIndex = active ? 0 : -1;
    });
    var authPanel = document.getElementById("view-auth-events-auth-panel");
    var adminPanel = document.getElementById("view-auth-events-admin-panel");
    if (authPanel) authPanel.hidden = currentTab !== "auth";
    if (adminPanel) adminPanel.hidden = currentTab !== "admin";
  }

  function bindAuthLogTabs() {
    var root = document.getElementById("view-auth-events");
    if (!root || root.dataset.authLogTabsBound === "1") return;
    root.dataset.authLogTabsBound = "1";
    root.setAttribute("data-auth-log-tab", root.getAttribute("data-auth-log-tab") || "auth");
    root.querySelectorAll("[data-auth-log-tab]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var tab = String(btn.getAttribute("data-auth-log-tab") || "auth");
        root.setAttribute("data-auth-log-tab", tab);
        syncAuthLogTabUi();
        loadViewAuthEvents();
      });
    });
    syncAuthLogTabUi();
  }

  function buildAuthEventsPath() {
    var q = new URLSearchParams();
    var account = queryValue("view-ae-account");
    var user = queryValue("view-ae-user");
    var errorCode = queryValue("view-ae-error");
    var action = queryValue("view-ae-action");
    if (account) q.set("account", account);
    if (user) q.set("user_id", user);
    if (errorCode) q.set("error_code", errorCode);
    if (action) q.set("action", action);
    var qs = q.toString();
    return "/api/admin/auth/events" + (qs ? "?" + qs : "");
  }

  function openAuthEventDetail(id) {
    id = String(id || "").trim();
    if (!id || !api()) return;
    showModalById("admin-modal-auth-event-detail");
    setModalBodyHtml("view-auth-event-detail-body", '<p class="admin-modal__empty-note">載入認證事件詳情中…</p>');
    api()
      .requestJson("/api/admin/auth/events/" + encodeURIComponent(id), { fallbackMessage: "載入認證事件詳情失敗" })
      .then(function (data) {
        var it = (data && data.event) || {};
        setModalBodyHtml(
          "view-auth-event-detail-body",
          detailRows([
            { label: "事件 ID", value: it.id, mono: true },
            { label: "類型", value: authEventActionLabelZh(it.action) },
            { label: "帳號", value: it.account || "—" },
            { label: "用戶 ID", value: it.user_id != null ? userOrdinalId({ id: it.user_id }, it.user_id) : "—", mono: true },
            { label: "錯誤碼", value: it.error_code ? authErrorCodeLabelZh(it.error_code) : "—", mono: true },
            { label: "訊息", value: it.msg || "—" },
            { label: "IP", value: it.ip || "—", mono: true },
            { label: "時間", value: it.created_at || "—", mono: true },
            { label: "Payload", value: renderJsonBlock(it.payload), raw: true }
          ])
        );
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        setModalBodyHtml("view-auth-event-detail-body", '<p class="admin-modal__empty-note">' + esc(e.message || "載入失敗") + "</p>");
      });
  }

  function loadViewSystemConfig() {
    var q = new URLSearchParams();
    var group = queryValue("view-sc-group");
    if (group) q.set("group", group);
    var path = "/api/admin/system/configs" + (q.toString() ? "?" + q.toString() : "");
    return loadItems(
      path,
      "view-system-config-tbody",
      4,
      function (it) {
        var val =
          typeof it.value === "object"
            ? JSON.stringify(it.value)
            : String(it.value);
        var g = String(it.group || "");
        var k = String(it.key || "");
        var isPlatformDeposit =
          g === "finance" &&
          (k === "usdt_platform_deposit_trc20" ||
            k === "usdt_platform_deposit_erc20" ||
            k === "usdt_platform_deposit_bep20");
        var canEditRow = !isPlatformDeposit || currentAdminIsRoot();
        var actionsCell = canEditRow
          ? adminRowActions(
              adminBtn({
                variant: "ghost",
                sm: true,
                attrs:
                  'data-admin-system-config-edit data-sc-group="' +
                  esc(g) +
                  '" data-sc-key="' +
                  esc(k) +
                  '" data-sc-value="' +
                  esc(typeof it.value === "object" ? JSON.stringify(it.value, null, 2) : String(it.value)) +
                  '"',
                label: "編輯"
              })
            )
          : '<span class="admin-muted" title="全站 USDT 預設充值地址僅限大老板修改（系統配置 API）">僅大老板</span>';
        return (
          "<tr><td>" +
          esc(it.group || "—") +
          '</td><td class="mono">' +
          esc(it.key || "—") +
          '</td><td class="mono">' +
          esc(val.length > 120 ? val.slice(0, 120) + "…" : val) +
          "</td><td>" +
          actionsCell +
          "</td></tr>"
        );
      },
      "尚無系統配置"
    );
  }

  function bindSystemConfigPage() {
    bindSimpleFilterControls("view-system-config", {
      query: "view-sc-query",
      refresh: "view-sc-refresh",
      reset: "view-sc-reset",
      inputs: ["view-sc-group"]
    }, loadViewSystemConfig);
    var root = document.getElementById("view-system-config");
    if (root && !root._adminScEditBound) {
      root._adminScEditBound = true;
      root.addEventListener("click", function (e) {
        var btn = e.target.closest("[data-admin-system-config-edit]");
        if (!btn) return;
        var group = btn.getAttribute("data-sc-group") || "";
        var key = btn.getAttribute("data-sc-key") || "";
        var val = btn.getAttribute("data-sc-value") || "";
        var tg = document.getElementById("view-sc-edit-target");
        var g = document.getElementById("view-sc-edit-group");
        var k = document.getElementById("view-sc-edit-key");
        var v = document.getElementById("view-sc-edit-value");
        var r = document.getElementById("view-sc-edit-reason");
        var msg = document.getElementById("view-sc-edit-msg");
        if (tg) tg.textContent = "正在更新：" + group + "." + key;
        if (g) g.value = group;
        if (k) k.value = key;
        if (v) v.value = val;
        if (r) r.value = "";
        if (msg) msg.textContent = "";
        showModalById("admin-modal-system-config-detail");
      });
    }
    var form = document.getElementById("view-sc-edit-form");
    if (form && !form._adminScFormBound) {
      form._adminScFormBound = true;
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var group = queryValue("view-sc-edit-group");
        var key = queryValue("view-sc-edit-key");
        var value = (document.getElementById("view-sc-edit-value") || {}).value;
        var reason = queryValue("view-sc-edit-reason");
        var msg = document.getElementById("view-sc-edit-msg");
        if (!group || !key) {
          if (msg) msg.textContent = "請先選擇配置項。";
          return;
        }
        if (!reason) {
          if (msg) msg.textContent = "請填寫更新原因。";
          return;
        }
        if (msg) msg.textContent = "更新中…";
        api()
          .requestJson("/api/admin/system/configs/" + encodeURIComponent(group) + "/" + encodeURIComponent(key), {
            method: "PATCH",
            body: { value: value, reason: reason },
            fallbackMessage: "更新配置失敗"
          })
          .then(function () {
            if (msg) msg.textContent = "已更新。";
            if (window.AdminModals && window.AdminModals.close) {
              var modal = document.getElementById("admin-modal-system-config-detail");
              if (modal) window.AdminModals.close(modal);
            }
            return loadViewSystemConfig();
          })
          .catch(function (err) {
            if (err && err.adminSessionHandled) return;
            if (msg) msg.textContent = err.message || "更新失敗";
          });
      });
    }
  }

  var _adminCatalogCache = null;
  /** 自「模組權限」複製模版至「新增管理員」時暫存的 module_access（僅與 role_template=custom 併用） */
  var _adminCreateUserModuleAccessDraft = null;
  var _adminGroupCache = [];
  var _adminUserRowsById = {};
  var _adminUsersPage = 1;
  var _adminUsersPageSize = ADMIN_PAGE_SIZE_DEFAULT;

  /** 模組 key → 繁中（與後台側欄用語一致；無對照時再用 API catalog.label） */
  var ADMIN_MODULE_LABELS_ZH = {
    users: "使用者管理",
    wallets: "資金錢包",
    tiers: "等級與限額",
    deposits: "充值審核",
    "deposit-addresses": "充值地址管理",
    withdrawals: "提現審核",
    orders: "訂單中心",
    "financial-products": "理財配置",
    "financial-orders": "理財訂單",
    listings: "商戶掛單",
    "trade-feed": "成交播報",
    "home-content": "運營配置",
    kyc: "實名 / KYC",
    "payout-methods": "收款方",
    "auth-events": "操作日誌",
    "system-config": "系統配置",
    "admin-users": "後台管理員"
  };

  var ROLE_TEMPLATE_ZH = {
    big_boss: "大老板",
    super_admin: "組超級管理員",
    finance_ops: "財務",
    reviewer: "審核",
    customer_service: "客服",
    custom: "自訂"
  };

  var ROLE_CODE_ZH = {
    super_admin: "組超級管理員",
    finance_ops: "財務",
    reviewer: "審核",
    customer_service: "客服"
  };

  function moduleKeyLabelZh(key) {
    if (ADMIN_MODULE_LABELS_ZH[key]) return ADMIN_MODULE_LABELS_ZH[key];
    var cat = _adminCatalogCache && _adminCatalogCache.catalog && _adminCatalogCache.catalog[key];
    if (cat && cat.label) return String(cat.label);
    return String(key || "—");
  }

  function accessLevelZh(level) {
    if (level === "write") return "修改";
    if (level === "read") return "閱讀";
    if (level === "" || level == null) return "關閉";
    return String(level || "—");
  }

  function roleTemplateLabelZh(code) {
    if (code == null || code === "") return "—";
    var c = String(code);
    return ROLE_TEMPLATE_ZH[c] || c;
  }

  function roleCodesSummaryZh(codes, isSuperAdmin, roleTemplate) {
    if (roleTemplate === "big_boss") return "大老板";
    if (isSuperAdmin) return "組全局";
    if (!Array.isArray(codes) || !codes.length) return "—";
    return codes
      .map(function (x) {
        return ROLE_CODE_ZH[x] || x;
      })
      .join("、");
  }

  function adminCreatorLabelZh(u) {
    if (!u || !u.created_by_admin_id) return "系統 / 根管理員";
    var display = String(u.created_by_admin_display_name || "").trim();
    var account = String(u.created_by_admin_account || "").trim();
    if (display && account) return display + "（" + account + "）";
    return display || account || ("ID " + u.created_by_admin_id);
  }

  function currentAdminProfile() {
    return window.__ADMIN_SESSION__ && typeof window.__ADMIN_SESSION__.getAdminProfile === "function"
      ? window.__ADMIN_SESSION__.getAdminProfile()
      : null;
  }

  function currentAdminIsRoot() {
    var p = currentAdminProfile() || {};
    if (p.is_root_admin === true) return true;
    if (p.role_template === "big_boss") return true;
    return String(p.display_name || "").trim() === "大老板";
  }

  function currentAdminCanGrantGroupGlobal() {
    var p = currentAdminProfile() || {};
    return currentAdminIsRoot() || p.can_view_group_global_data === true;
  }

  function adminGroupLabelZh(u) {
    var name = String((u && u.admin_group_name) || "").trim();
    var code = String((u && u.admin_group_code) || "").trim();
    if (!name && !code) return "—";
    if (name && code && name !== code) return name + "（" + code + "）";
    return name || code;
  }

  function currentAdminModuleGrant(mk) {
    var p = currentAdminProfile() || {};
    var catalog = _adminCatalogCache && _adminCatalogCache.catalog;
    if (p.is_root_admin || (Array.isArray(p.role_codes) && p.role_codes.indexOf("super_admin") >= 0)) return "write";
    if (!catalog || !catalog[mk] || !Array.isArray(catalog[mk].permissions)) return "";
    var permissions = Array.isArray(p.permissions) ? p.permissions : [];
    var lookup = {};
    permissions.forEach(function (perm) {
      lookup[perm] = true;
    });
    var hasRead = false;
    var hasWrite = false;
    catalog[mk].permissions.forEach(function (perm) {
      if (!lookup[perm]) return;
      if (String(perm).slice(-6) === ".write") hasWrite = true;
      else hasRead = true;
    });
    if (hasWrite) return "write";
    if (hasRead) return "read";
    return "";
  }

  function moduleAccessSummaryZh(mod) {
    if (!mod || typeof mod !== "object") return "—";
    var keys = Object.keys(mod);
    if (!keys.length) return "—";
    var parts = keys.slice(0, 8).map(function (k) {
      return moduleKeyLabelZh(k) + "（" + accessLevelZh(mod[k]) + "）";
    });
    return parts.join("、") + (keys.length > 8 ? "…" : "");
  }

  /** 管理員模版分頁：由 GET admin-users 的 role_templates 填表 */
  function isEditableRoleTemplateKey(k) {
    var custom = (_adminCatalogCache && _adminCatalogCache.custom_role_template_keys) || [];
    return custom.indexOf(k) >= 0;
  }

  function renderAdminRoleTemplatesTable(templates) {
    var tb = document.getElementById("view-admin-role-templates-tbody");
    if (!tb) return;
    var keys = templates && typeof templates === "object" ? Object.keys(templates) : [];
    if (!keys.length) {
      tb.innerHTML =
        tableEmptyRow(
          4,
          "尚無模版資料",
          "請先以超管身分載入「後台管理員」列表後再查看此表。"
        );
      return;
    }
    var order = ["finance_ops", "reviewer", "customer_service"];
    keys.sort(function (a, b) {
      var ia = order.indexOf(a);
      var ib = order.indexOf(b);
      if (ia === -1 && ib === -1) return String(a).localeCompare(String(b));
      if (ia === -1) return 1;
      if (ib === -1) return -1;
      return ia - ib;
    });
    tb.innerHTML = keys
      .map(function (k) {
        var t = templates[k] || {};
        var labZh = ROLE_TEMPLATE_ZH[k] || t.label || k;
        var sum = moduleAccessSummaryZh(t.module_access || {});
        var isCustom = isEditableRoleTemplateKey(k);
        var actions = adminRowActions(
          adminBtn({
            variant: "ghost",
            sm: true,
            attrs: 'data-admin-rt-edit data-admin-rt-key="' + esc(k) + '"',
            label: "編輯"
          }) +
            adminBtn({
              variant: "danger",
              sm: true,
              attrs: 'data-admin-rt-delete data-admin-rt-key="' + esc(k) + '"',
              label: "刪除",
              disabled: !isCustom,
              title: isCustom ? "" : "內建模版不可刪除"
            })
        );
        return (
          "<tr><td class=\"mono\">" +
          esc(k) +
          "</td><td>" +
          esc(labZh) +
          "</td><td class=\"mono\" style=\"white-space:normal;word-break:break-word\">" +
          esc(sum) +
          "</td><td>" +
          actions +
          "</td></tr>"
        );
      })
      .join("");
  }

  function moduleAccessRowNameSuffix(mk, rtOrPerm) {
    var safe = String(mk || "").replace(/[^a-zA-Z0-9_-]/g, "_");
    if (rtOrPerm === "perm") return "admin-modaccess-perm-" + safe;
    if (rtOrPerm === "create") return "admin-modaccess-create-" + safe;
    return "admin-modaccess-rt-" + safe;
  }

  /** 單列：關閉 / 閱讀 / 修改（大型單選，語意同 "", read, write） */
  function buildModuleAccessRowHTML(mk, cur, grant, readOnly, rtOrPerm) {
    var rowAttr =
      rtOrPerm === "perm"
        ? "data-admin-perm-module"
        : rtOrPerm === "create"
          ? "data-admin-create-module"
          : "data-admin-rt-module";
    var lab = moduleKeyLabelZh(mk);
    var name = moduleAccessRowNameSuffix(mk, rtOrPerm);
    var selNone = !cur || (cur !== "read" && cur !== "write");
    var selRead = cur === "read";
    var selWrite = cur === "write";
    var noneDisabled = !!readOnly;
    var readDisabled = !!readOnly || grant === "";
    var writeDisabled = !!readOnly || grant !== "write";
    return (
      '<div class="admin-module-access-row" ' +
      rowAttr +
      '="' +
      esc(mk) +
      '">' +
      '<div class="admin-module-access-row__label"><span class="admin-modal__label">' +
      esc(lab) +
      '</span></div><div class="admin-module-access-choices" role="radiogroup" aria-label="' +
      esc(lab + " 模組權限") +
      '">' +
      '<label class="admin-module-access-choice' +
      (noneDisabled ? " is-disabled" : "") +
      '"><input type="radio" class="admin-module-access-choice__input" name="' +
      esc(name) +
      '" value="" ' +
      (selNone ? "checked " : "") +
      (noneDisabled ? "disabled " : "") +
      '/><span class="admin-module-access-choice__text">關閉</span></label>' +
      '<label class="admin-module-access-choice' +
      (readDisabled ? " is-disabled" : "") +
      '"><input type="radio" class="admin-module-access-choice__input" name="' +
      esc(name) +
      '" value="read" ' +
      (selRead ? "checked " : "") +
      (readDisabled ? "disabled " : "") +
      '/><span class="admin-module-access-choice__text">閱讀</span></label>' +
      '<label class="admin-module-access-choice' +
      (writeDisabled ? " is-disabled" : "") +
      '"><input type="radio" class="admin-module-access-choice__input" name="' +
      esc(name) +
      '" value="write" ' +
      (selWrite ? "checked " : "") +
      (writeDisabled ? "disabled " : "") +
      '/><span class="admin-module-access-choice__text">修改</span></label>' +
      "</div></div>"
    );
  }

  function buildRtModuleMatrixHTML(access, readOnly) {
    readOnly = !!readOnly;
    var catalog = _adminCatalogCache && _adminCatalogCache.catalog;
    if (!catalog || typeof catalog !== "object") {
      return '<p class="admin-modal__empty-note">請先於「後台管理員」分頁成功載入列表以取得模組目錄。</p>';
    }
    access = access || {};
    var keys = Object.keys(catalog).sort();
    return keys
      .map(function (mk) {
        var cur = access[mk];
        var grant = currentAdminModuleGrant(mk);
        return buildModuleAccessRowHTML(mk, cur, grant, readOnly, "rt");
      })
      .join("");
  }

  function collectRtModuleAccessFromForm() {
    var root = document.getElementById("view-admin-rt-matrix");
    if (!root) return {};
    var rows = root.querySelectorAll("[data-admin-rt-module]");
    var out = {};
    Array.prototype.forEach.call(rows, function (row) {
      var mk = row.getAttribute("data-admin-rt-module");
      var checked = row.querySelector('.admin-module-access-choices input[type="radio"]:checked');
      if (!mk || !checked) return;
      var v = String(checked.value || "");
      if (v === "read" || v === "write") {
        out[mk] = v;
      }
    });
    return out;
  }

  /** 模組權限彈窗 #view-admin-perm-matrix */
  function collectAdminPermMatrixModuleAccess(matrixEl) {
    if (!matrixEl) return {};
    var rows = matrixEl.querySelectorAll("[data-admin-perm-module]");
    var out = {};
    Array.prototype.forEach.call(rows, function (row) {
      var mk = row.getAttribute("data-admin-perm-module");
      var checked = row.querySelector('.admin-module-access-choices input[type="radio"]:checked');
      if (!mk || !checked) return;
      var v = String(checked.value || "");
      if (v === "read" || v === "write") {
        out[mk] = v;
      }
    });
    return out;
  }

  /** 新增管理員表單 #view-admin-create-module-matrix */
  function buildCreateAdminModuleMatrixHTML(access) {
    var catalog = _adminCatalogCache && _adminCatalogCache.catalog;
    if (!catalog || typeof catalog !== "object") {
      return '<p class="admin-modal__empty-note">請先於「後台管理員」分頁載入列表以取得模組目錄。</p>';
    }
    access = access || {};
    var keys = Object.keys(catalog).sort();
    return keys
      .map(function (mk) {
        var cur = access[mk];
        var grant = currentAdminModuleGrant(mk);
        return buildModuleAccessRowHTML(mk, cur, grant, false, "create");
      })
      .join("");
  }

  function collectCreateAdminModuleMatrix() {
    var root = document.getElementById("view-admin-create-module-matrix");
    if (!root) return {};
    var rows = root.querySelectorAll("[data-admin-create-module]");
    var out = {};
    Array.prototype.forEach.call(rows, function (row) {
      var mk = row.getAttribute("data-admin-create-module");
      var checked = row.querySelector('.admin-module-access-choices input[type="radio"]:checked');
      if (!mk || !checked) return;
      var v = String(checked.value || "");
      if (v === "read" || v === "write") {
        out[mk] = v;
      }
    });
    return out;
  }

  function syncCreateAdminCustomModulePanel() {
    var wrap = document.getElementById("view-admin-create-custom-wrap");
    var matrix = document.getElementById("view-admin-create-module-matrix");
    var roleTpl = document.getElementById("view-admin-create-role-template");
    var superEl = document.getElementById("view-admin-create-super");
    if (!wrap || !matrix || !roleTpl) return;
    var isSuper = currentAdminIsRoot() && superEl && superEl.checked;
    var isCustom = roleTpl.value === "custom";
    if (!isCustom || isSuper) {
      wrap.hidden = true;
      if (isSuper) matrix.innerHTML = "";
      return;
    }
    wrap.hidden = false;
    var seed =
      _adminCreateUserModuleAccessDraft && typeof _adminCreateUserModuleAccessDraft === "object"
        ? _adminCreateUserModuleAccessDraft
        : {};
    matrix.innerHTML = buildCreateAdminModuleMatrixHTML(seed);
  }

  function prepareRoleTemplateModalCreate() {
    var title = document.getElementById("amt-crt-title");
    var mode = document.getElementById("view-admin-rt-mode");
    var keyIn = document.getElementById("view-admin-rt-key");
    var labelIn = document.getElementById("view-admin-rt-label");
    var msg = document.getElementById("view-admin-rt-msg");
    var matrix = document.getElementById("view-admin-rt-matrix");
    var sub = document.getElementById("view-admin-rt-submit");
    if (mode) mode.value = "create";
    if (title) title.textContent = "新增管理員模版";
    if (keyIn) {
      keyIn.value = "";
      keyIn.readOnly = false;
    }
    if (labelIn) {
      labelIn.value = "";
      labelIn.readOnly = false;
    }
    if (msg) msg.textContent = "";
    if (matrix) matrix.innerHTML = buildRtModuleMatrixHTML({}, false);
    if (sub) {
      sub.textContent = "建立";
      sub.disabled = false;
    }
  }

  function openRoleTemplateModalEdit(key) {
    var title = document.getElementById("amt-crt-title");
    var mode = document.getElementById("view-admin-rt-mode");
    var keyIn = document.getElementById("view-admin-rt-key");
    var labelIn = document.getElementById("view-admin-rt-label");
    var msg = document.getElementById("view-admin-rt-msg");
    var matrix = document.getElementById("view-admin-rt-matrix");
    var sub = document.getElementById("view-admin-rt-submit");
    if (msg) msg.textContent = "載入中…";
    api()
      .requestJson("/api/admin/role-templates/" + encodeURIComponent(key), {
        fallbackMessage: "載入模版失敗"
      })
      .then(function (data) {
        var tpl = (data && data.template) || {};
        var builtin = !!(data && data.builtin);
        if (mode) mode.value = builtin ? "view_builtin" : "edit";
        if (title) {
          title.textContent = builtin ? "檢視管理員模版（內建）" : "編輯管理員模版";
        }
        if (keyIn) {
          keyIn.value = (data && data.template_key) || key;
          keyIn.readOnly = true;
        }
        if (labelIn) {
          labelIn.value = tpl.label || "";
          labelIn.readOnly = !!builtin;
        }
        if (msg) {
          msg.textContent = builtin
            ? "此為系統內建模版，資料來自後端 catalog；僅可檢視，無法於此儲存或刪除。"
            : "";
        }
        if (matrix) matrix.innerHTML = buildRtModuleMatrixHTML(tpl.module_access || {}, builtin);
        if (sub) {
          if (builtin) {
            sub.textContent = "僅供檢視";
            sub.disabled = true;
          } else {
            sub.textContent = "儲存";
            sub.disabled = false;
          }
        }
        if (window.AdminModals && window.AdminModals.open) {
          window.AdminModals.open("admin-modal-create-role-template");
        }
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        if (msg) msg.textContent = e.message || "載入失敗";
      });
  }

  function submitRoleTemplateForm() {
    var modeEl = document.getElementById("view-admin-rt-mode");
    var mode = modeEl ? modeEl.value : "create";
    var msg = document.getElementById("view-admin-rt-msg");
    if (mode === "view_builtin") {
      if (msg) msg.textContent = "內建模版無法於此儲存。";
      return;
    }
    var keyIn = document.getElementById("view-admin-rt-key");
    var labelIn = document.getElementById("view-admin-rt-label");
    var key = keyIn ? keyIn.value.trim() : "";
    var label = labelIn ? labelIn.value.trim() : "";
    var access = collectRtModuleAccessFromForm();
    if (Object.keys(access).length === 0) {
      if (msg) msg.textContent = "請至少為一個模組選擇「閱讀」或「修改」。";
      return;
    }
    if (mode === "create") {
      if (!/^[a-z][a-z0-9_]*$/.test(key)) {
        if (msg) msg.textContent = "模版鍵須為小寫開頭，僅含小寫字母、數字與底線。";
        return;
      }
    }
    if (label === "") {
      if (msg) msg.textContent = "請填寫顯示名稱。";
      return;
    }
    if (msg) msg.textContent = mode === "create" ? "建立中…" : "儲存中…";
    var req =
      mode === "create"
        ? api().requestJson("/api/admin/role-templates", {
            method: "POST",
            body: { template_key: key, label: label, module_access: access },
            fallbackMessage: "建立模版失敗"
          })
        : api().requestJson("/api/admin/role-templates/" + encodeURIComponent(key), {
            method: "PATCH",
            body: { label: label, module_access: access },
            fallbackMessage: "更新模版失敗"
          });
    req
      .then(function () {
        if (msg) msg.textContent = mode === "create" ? "已建立。" : "已儲存。";
        loadViewAdminUsers();
        if (window.AdminModals && window.AdminModals.close) {
          var m = document.getElementById("admin-modal-create-role-template");
          if (m) window.AdminModals.close(m);
        }
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        if (msg) msg.textContent = e.message || "失敗";
      });
  }

  function submitDeleteRoleTemplate(key) {
    api()
      .requestJson("/api/admin/role-templates/" + encodeURIComponent(key), {
        method: "DELETE",
        fallbackMessage: "刪除模版失敗"
      })
      .then(function () {
        loadViewAdminUsers();
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        adminNotifyError(e.message || "刪除失敗");
      });
  }

  function initRoleTemplateManagement() {
    if (document._adminRtUiBound) return;
    document._adminRtUiBound = true;
    document.addEventListener(
      "click",
      function (e) {
        if (e.target.closest('[data-admin-modal-open="admin-modal-create-role-template"]')) {
          prepareRoleTemplateModalCreate();
        }
        var ed = e.target.closest("[data-admin-rt-edit]");
        if (ed) {
          e.preventDefault();
          var ek = ed.getAttribute("data-admin-rt-key");
          if (ek) openRoleTemplateModalEdit(ek);
          return;
        }
        var del = e.target.closest("[data-admin-rt-delete]");
        if (del) {
          e.preventDefault();
          if (del.disabled) return;
          var dk = del.getAttribute("data-admin-rt-key");
          if (!dk) return;
          adminConfirm({
            title: "刪除模版",
            message: "確定刪除自訂模版「" + dk + "」？（有管理員帳號使用時將無法刪除）",
            danger: true,
            confirmLabel: "確定刪除"
          }).then(function (ok) {
            if (!ok) return;
            submitDeleteRoleTemplate(dk);
          });
        }
      },
      true
    );
    var form = document.getElementById("view-admin-rt-form");
    if (form && !form._adminRtSubmitBound) {
      form._adminRtSubmitBound = true;
      form.addEventListener("submit", function (ev) {
        ev.preventDefault();
        submitRoleTemplateForm();
      });
    }
  }

  function adminStatusLabelZh(status) {
    var s = String(status || "").toLowerCase();
    if (s === "normal") return "正常";
    if (s === "disabled") return "禁用";
    if (s === "locked") return "鎖定";
    if (s === "pending") return "待審核";
    return "其他";
  }

  function renderAdminUserRow(it) {
    var base = roleCodesSummaryZh(it.role_codes, it.is_super_admin, it.role_template);
    return (
      '<tr data-admin-id="' +
      esc(it.id) +
      '"><td class="mono">' +
      esc(it.id) +
      "</td><td>" +
      esc(it.account || "—") +
      "</td><td>" +
      esc(it.display_name || "—") +
      "</td><td>" +
      esc(adminGroupLabelZh(it)) +
      '</td><td class="mono">' +
      esc(it.staff_invite_code || "—") +
      "</td><td>" +
      esc(roleTemplateLabelZh(it.role_template)) +
      "</td><td>" +
      badge(it.status, adminStatusLabelZh(it.status)) +
      "</td><td class=\"mono\">" +
      esc(base) +
      "</td><td class=\"mono\">" +
      esc(moduleAccessSummaryZh(it.module_access)) +
      "</td><td class=\"mono\">" +
      esc((it.created_at || "").slice(0, 10)) +
      "</td><td>" +
      adminRowActions(
        adminBtn({
          variant: "info",
          sm: true,
          attrs:
            'data-admin-modal-open="admin-modal-admin-user-detail" data-admin-operator-id="' +
            esc(it.id) +
            '"',
          label: "詳情"
        }) +
          adminBtn({
            variant: "ghost",
            sm: true,
            attrs:
              'data-admin-modal-open="admin-modal-admin-user-edit" data-admin-operator-id="' +
              esc(it.id) +
              '"',
            label: "編輯"
          }) +
          (it.is_super_admin
            ? adminBtn({
                variant: "info",
                sm: true,
                attrs:
                  'data-admin-modal-open="admin-modal-admin-user-permissions" data-admin-operator-id="' +
                  esc(it.id) +
                  '"',
                label: "檢視權限",
                title: "組超級管理員：後端對授權範圍內全部模組等同讀寫；此處為唯讀對照表。"
              })
            : adminBtn({
                variant: "accent",
                sm: true,
                attrs:
                  'data-admin-modal-open="admin-modal-admin-user-permissions" data-admin-operator-id="' +
                  esc(it.id) +
                  '"',
                label: "權限",
                title: "編輯模組讀寫權限"
              })) +
          adminBtn({
            variant: "warning",
            sm: true,
            attrs:
              'data-admin-modal-open="admin-modal-admin-password" data-admin-operator-id="' +
              esc(it.id) +
              '"',
            label: "重置密碼"
          })
      ) +
      "</td></tr>"
    );
  }

  function updateAdminUsersPagerUi(data, items) {
    var pag = (data && data.pagination) || {};
    var total = pag.total != null ? Number(pag.total) : (items && items.length) || 0;
    var page = pag.page != null ? Number(pag.page) : _adminUsersPage;
    var ps = pag.page_size != null ? Number(pag.page_size) : _adminUsersPageSize;
    if (page >= 1) _adminUsersPage = page;
    if (ps >= 1) _adminUsersPageSize = ps;
    var totalPages = Math.max(1, Math.ceil(total / (_adminUsersPageSize || ADMIN_PAGE_SIZE_DEFAULT)));
    var countEl = document.getElementById("view-admin-users-count");
    var info = document.getElementById("view-admin-users-page-info");
    var prev = document.getElementById("view-admin-users-prev");
    var next = document.getElementById("view-admin-users-next");
    if (countEl) countEl.textContent = "共 " + total + " 筆";
    if (info) info.textContent = "第 " + _adminUsersPage + " / " + totalPages + " 頁（每頁 " + _adminUsersPageSize + " 筆）";
    if (prev) prev.disabled = _adminUsersPage <= 1;
    if (next) next.disabled = _adminUsersPage >= totalPages || total === 0;
  }

  function promptAdminUsersPageSize() {
    return adminPrompt({
      title: "每頁筆數",
      message: "請輸入每頁顯示筆數：" + ADMIN_PAGE_SIZE_OPTIONS.join(" / "),
      defaultValue: String(_adminUsersPageSize || ADMIN_PAGE_SIZE_DEFAULT)
    }).then(function (value) {
      if (value == null) return;
      var n = Number(String(value).trim());
      if (!Number.isFinite(n) || n <= 0) return adminNotifyError("請輸入有效筆數");
      _adminUsersPageSize = Math.max(1, Math.min(200, Math.floor(n)));
      _adminUsersPage = 1;
      loadViewAdminUsers();
    });
  }

  function loadViewAdminUsers() {
    if (!api()) return Promise.resolve();
    fillAdminRoleTemplateSelects(null);
    var kw = document.getElementById("view-admin-users-keyword");
    var st = document.getElementById("view-admin-users-status");
    var q =
      "/api/admin/admin-users?page=" +
      encodeURIComponent(String(_adminUsersPage)) +
      "&page_size=" +
      encodeURIComponent(String(_adminUsersPageSize));
    if (kw && kw.value.trim()) q += "&keyword=" + encodeURIComponent(kw.value.trim());
    if (st && st.value) q += "&status=" + encodeURIComponent(st.value);
    setTbodyLoading("view-admin-users-tbody", 11);
    var countEl = document.getElementById("view-admin-users-count");
    return api()
      .requestJson(q, { fallbackMessage: "載入後台管理員失敗" })
      .then(function (data) {
        _adminCatalogCache = data || {};
        if (_adminCatalogCache && !Array.isArray(_adminCatalogCache.custom_role_template_keys)) {
          _adminCatalogCache.custom_role_template_keys = [];
        }
        _adminUserRowsById = {};
        var items = (data && data.items) || [];
        items.forEach(function (it) {
          _adminUserRowsById[it.id] = it;
        });
        var tb = document.getElementById("view-admin-users-tbody");
        if (tb) {
          if (!items.length) {
            tb.innerHTML =
              tableEmptyRow(
                11,
                "此頁尚無管理員資料",
                "可能尚無符合篩選的帳號，或需超管權限才能載入列表。"
              );
          } else {
            tb.innerHTML = items.map(renderAdminUserRow).join("");
          }
        }
        updateAdminUsersPagerUi(data, items);
        fillAdminRoleTemplateSelects(data && data.role_templates);
        fillAdminGroupSelects(data && data.admin_groups);
        renderAdminRoleTemplatesTable(data && data.role_templates);
      })
      .catch(function (e) {
        if (e && e.adminSessionHandled) return;
        _adminCatalogCache = null;
        _adminGroupCache = [];
        _adminUserRowsById = {};
        setTbodyError("view-admin-users-tbody", e.message, 11);
        if (countEl) countEl.textContent = "載入失敗";
        var info = document.getElementById("view-admin-users-page-info");
        if (info) info.textContent = "";
        var prev = document.getElementById("view-admin-users-prev");
        var next = document.getElementById("view-admin-users-next");
        if (prev) prev.disabled = true;
        if (next) next.disabled = true;
        fillAdminRoleTemplateSelects(null);
        fillAdminGroupSelects(null);
        renderAdminRoleTemplatesTable(null);
      });
  }

  /** 同步「新增 / 編輯」角色模板下拉；templates 為 null 時使用內建 fallback（與後端預設模板鍵一致）。 */
  function fillAdminRoleTemplateSelects(templates) {
    var keys = templates && typeof templates === "object" ? Object.keys(templates).filter(function (k) { return k !== "big_boss"; }) : [];
    var fallback =
      '<option value="finance_ops">財務</option><option value="reviewer">審核</option><option value="customer_service">客服</option>';
    var core =
      keys.length > 0
        ? keys
            .map(function (k) {
              var lab = ROLE_TEMPLATE_ZH[k] || (templates[k] && templates[k].label) || k;
              return '<option value="' + esc(k) + '">' + esc(lab) + "</option>";
            })
            .join("")
        : fallback;
    var createSel = document.getElementById("view-admin-create-role-template");
    if (createSel) {
      createSel.innerHTML = core;
      if (!createSel.querySelector('option[value="custom"]')) {
        createSel.insertAdjacentHTML("beforeend", '<option value="custom">自訂</option>');
      }
    }
    var editSel = document.getElementById("view-admin-edit-role-template");
    if (editSel) {
      editSel.innerHTML = '<option value="super_admin">組超級管理員</option>' + core;
    }
  }

  function adminGroupOptionLabel(group) {
    var name = String((group && group.group_name) || "").trim();
    var code = String((group && group.group_code) || "").trim();
    if (name && code && name !== code) return name + "（" + code + "）";
    return name || code || "未命名分組";
  }

  function fillAdminGroupSelects(groups) {
    _adminGroupCache = Array.isArray(groups) ? groups.slice() : [];
    var createSel = document.getElementById("view-admin-create-group-select");
    if (!createSel) return;
    var html = '<option value="">不指定分組（沿用建立者目前組別）</option>';
    html += _adminGroupCache
      .map(function (group) {
        var code = String(group.group_code || "").trim();
        if (!code) return "";
        return (
          '<option value="' +
          esc(code) +
          '" data-group-name="' +
          esc(group.group_name || code) +
          '">' +
          esc(adminGroupOptionLabel(group)) +
          "</option>"
        );
      })
      .join("");
    createSel.innerHTML = html;
  }

  function bindAdminUsersPage() {
    var kw = document.getElementById("view-admin-users-keyword");
    var st = document.getElementById("view-admin-users-status");
    var search = document.getElementById("view-admin-users-search");
    var reset = document.getElementById("view-admin-users-reset");
    var refresh = document.getElementById("view-admin-users-refresh");
    if (search && !search._adminBound) {
      search._adminBound = true;
      search.addEventListener("click", function () {
        _adminUsersPage = 1;
        loadViewAdminUsers();
      });
    }
    if (reset && !reset._adminBound) {
      reset._adminBound = true;
      reset.addEventListener("click", function () {
        if (kw) kw.value = "";
        if (st) st.value = "";
        _adminUsersPage = 1;
        loadViewAdminUsers();
      });
    }
    if (refresh && !refresh._adminBound) {
      refresh._adminBound = true;
      refresh.addEventListener("click", function () {
        loadViewAdminUsers();
      });
    }
    var prev = document.getElementById("view-admin-users-prev");
    var next = document.getElementById("view-admin-users-next");
    var size = document.getElementById("view-admin-users-page-size");
    if (prev && !prev._adminBound) {
      prev._adminBound = true;
      prev.addEventListener("click", function () {
        if (_adminUsersPage > 1) {
          _adminUsersPage -= 1;
          loadViewAdminUsers();
        }
      });
    }
    if (next && !next._adminBound) {
      next._adminBound = true;
      next.addEventListener("click", function () {
        _adminUsersPage += 1;
        loadViewAdminUsers();
      });
    }
    if (size && !size._adminBound) {
      size._adminBound = true;
      size.addEventListener("click", promptAdminUsersPageSize);
    }
  }

  function setAdminScopeControls(mode, values) {
    var isCreate = mode === "create";
    var root = currentAdminIsRoot();
    var canGrantGlobal = currentAdminCanGrantGroupGlobal();
    var globalEl = document.getElementById(isCreate ? "view-admin-create-group-global" : "view-admin-edit-group-global");
    values = values || {};
    if (!isCreate) {
      var scope = document.getElementById("view-admin-edit-scope-group");
      var nameEl = document.getElementById("view-admin-edit-group-name");
      var codeEl = document.getElementById("view-admin-edit-group-code");
      if (scope) scope.hidden = false;
      if (nameEl) {
        nameEl.value = values.admin_group_name || "";
        nameEl.readOnly = !root;
      }
      if (codeEl) {
        codeEl.value = values.admin_group_code || "";
        codeEl.readOnly = !root;
      }
    }
    if (globalEl) {
      globalEl.checked = !!values.can_view_group_global_data;
      globalEl.disabled = !canGrantGlobal;
      globalEl.title = canGrantGlobal ? "" : "只有大老板或具備全組資料權限的組超級管理員可勾選";
    }
  }

  function initCreateAdminGroupForm() {
    var form = document.getElementById("view-admin-create-group-form");
    if (!form || form._adminBound) return;
    form._adminBound = true;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var nameEl = document.getElementById("view-admin-group-create-name");
      var codeEl = document.getElementById("view-admin-group-create-code");
      var msg = document.getElementById("view-admin-group-create-msg");
      var body = {
        group_name: nameEl ? nameEl.value.trim() : "",
        group_code: codeEl ? codeEl.value.trim() : ""
      };
      if (!body.group_name) {
        if (msg) msg.textContent = "請填寫組別名稱。";
        return;
      }
      if (msg) msg.textContent = "送出中…";
      api()
        .requestJson("/api/admin/admin-groups", {
          method: "POST",
          body: body,
          fallbackMessage: "新增分組失敗"
        })
        .then(function () {
          if (msg) msg.textContent = "已建立分組。";
          form.reset();
          return loadViewAdminUsers();
        })
        .then(function () {
          if (window.AdminModals && window.AdminModals.close) {
            var modal = document.getElementById("admin-modal-create-admin-group");
            if (modal) window.AdminModals.close(modal);
          }
        })
        .catch(function (err) {
          if (err && err.adminSessionHandled) return;
          if (msg) msg.textContent = err.message || "新增分組失敗";
        });
    });
  }

  function initCreateAdminUserForm() {
    var form = document.getElementById("view-admin-create-user-form");
    if (!form || form._adminBound) return;
    form._adminBound = true;
    if (!document.documentElement._adminCreateModalOpenClear) {
      document.documentElement._adminCreateModalOpenClear = true;
      document.addEventListener(
        "click",
        function (e) {
          var opener = e.target.closest('[data-admin-modal-open="admin-modal-create-admin-user"]');
          if (!opener) return;
          _adminCreateUserModuleAccessDraft = null;
          var p = currentAdminProfile() || {};
          setAdminScopeControls("create", {
            admin_group_code: currentAdminIsRoot() ? "" : p.admin_group_code,
            admin_group_name: currentAdminIsRoot() ? "" : p.admin_group_name,
            can_view_group_global_data: false
          });
        },
        true
      );
    }
    var createSuper = document.getElementById("view-admin-create-super");
    if (createSuper && !currentAdminIsRoot()) {
      createSuper.checked = false;
      createSuper.disabled = true;
      createSuper.title = "只有大老板可新增組超級管理員";
    }
    var profileForCreate = currentAdminProfile() || {};
    setAdminScopeControls("create", {
      admin_group_code: currentAdminIsRoot() ? "" : profileForCreate.admin_group_code,
      admin_group_name: currentAdminIsRoot() ? "" : profileForCreate.admin_group_name,
      can_view_group_global_data: false
    });
    var roleTplEl = document.getElementById("view-admin-create-role-template");
    if (roleTplEl && !roleTplEl._adminCreateCustomBound) {
      roleTplEl._adminCreateCustomBound = true;
      roleTplEl.addEventListener("change", syncCreateAdminCustomModulePanel);
    }
    if (createSuper && !createSuper._adminCreateCustomBound) {
      createSuper._adminCreateCustomBound = true;
      createSuper.addEventListener("change", syncCreateAdminCustomModulePanel);
    }
    syncCreateAdminCustomModulePanel();
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var account = document.getElementById("view-admin-create-account");
      var password = document.getElementById("view-admin-create-password");
      var display = document.getElementById("view-admin-create-display");
      var invite = document.getElementById("view-admin-create-invite");
      var status = document.getElementById("view-admin-create-status");
      var superEl = document.getElementById("view-admin-create-super");
      var roleTpl = document.getElementById("view-admin-create-role-template");
      var groupSel = document.getElementById("view-admin-create-group-select");
      var groupGlobal = document.getElementById("view-admin-create-group-global");
      var msg = document.getElementById("view-admin-create-msg");
      var isSuper = currentAdminIsRoot() && superEl && superEl.checked;
      var roleVal = roleTpl ? roleTpl.value : "finance_ops";
      var selectedGroupOption = groupSel && groupSel.selectedIndex >= 0 ? groupSel.options[groupSel.selectedIndex] : null;
      var selectedGroupCode = groupSel ? String(groupSel.value || "").trim() : "";
      var selectedGroupName =
        selectedGroupOption && selectedGroupOption.getAttribute("data-group-name")
          ? selectedGroupOption.getAttribute("data-group-name")
          : "";
      var draft =
        _adminCreateUserModuleAccessDraft && typeof _adminCreateUserModuleAccessDraft === "object"
          ? _adminCreateUserModuleAccessDraft
          : null;
      var module_access = {};
      if (!isSuper && roleVal === "custom") {
        var fromMatrix = collectCreateAdminModuleMatrix();
        if (Object.keys(fromMatrix).length > 0) {
          module_access = fromMatrix;
        } else if (draft && Object.keys(draft).length > 0) {
          module_access = Object.assign({}, draft);
        }
      }
      if (!isSuper && roleVal === "custom" && Object.keys(module_access).length === 0) {
        if (msg) msg.textContent = "請至少為一個模組選擇「閱讀」或「修改」。";
        return;
      }
      var body = {
        account: account ? account.value.trim() : "",
        password: password ? password.value : "",
        display_name: display ? display.value.trim() : "",
        staff_invite_code: invite && invite.value.trim() ? invite.value.trim() : null,
        status: status ? status.value : "normal",
        is_super_admin: isSuper,
        role_template: roleVal,
        module_access: module_access,
        admin_group_name: selectedGroupName,
        admin_group_code: selectedGroupCode,
        can_view_group_global_data: groupGlobal && !groupGlobal.disabled ? groupGlobal.checked : false
      };
      if (!body.account || !body.password) {
        if (msg) msg.textContent = "請填寫帳號與密碼。";
        return;
      }
      if (msg) msg.textContent = "送出中…";
      api()
        .requestJson("/api/admin/admin-users", {
          method: "POST",
          body: body,
          fallbackMessage: "新增失敗"
        })
        .then(function () {
          if (msg) msg.textContent = "已建立管理員。";
          _adminCreateUserModuleAccessDraft = null;
          form.reset();
          if (superEl) superEl.checked = false;
          if (createSuper && !currentAdminIsRoot()) {
            createSuper.disabled = true;
            createSuper.title = "只有大老板可新增組超級管理員";
          } else if (createSuper) {
            createSuper.disabled = false;
            createSuper.title = "";
          }
          var p = currentAdminProfile() || {};
          setAdminScopeControls("create", {
            admin_group_code: currentAdminIsRoot() ? "" : p.admin_group_code,
            admin_group_name: currentAdminIsRoot() ? "" : p.admin_group_name,
            can_view_group_global_data: false
          });
          syncCreateAdminCustomModulePanel();
          loadViewAdminUsers();
          if (window.AdminModals && window.AdminModals.close) {
            var m = document.getElementById("admin-modal-create-admin-user");
            if (m) window.AdminModals.close(m);
          }
        })
        .catch(function (err) {
          if (err && err.adminSessionHandled) return;
          if (msg) msg.textContent = err.message || "新增失敗";
        });
    });
  }

  function initAdminUserDetailHooks() {
    if (document._adminUserDetailHook) return;
    document._adminUserDetailHook = true;
    document.addEventListener(
      "click",
      function (e) {
        var btn = e.target.closest("[data-admin-operator-id]");
        if (!btn) return;
        var id = btn.getAttribute("data-admin-operator-id");
        if (!id) return;
        var open = btn.getAttribute("data-admin-modal-open");
        if (open === "admin-modal-admin-user-detail") {
          api()
            .requestJson("/api/admin/admin-users/" + encodeURIComponent(id), {
              fallbackMessage: "載入詳情失敗"
            })
            .then(function (data) {
              var u = (data && data.admin_user) || {};
              var box = document.getElementById("admin-modal-admin-user-detail-body");
              if (!box) return;
              box.innerHTML =
                "<dl class=\"admin-modal__detail-list\">" +
                "<div><dt>帳號</dt><dd>" +
                esc(u.account) +
                "</dd></div>" +
                "<div><dt>名稱</dt><dd>" +
                esc(u.display_name || "—") +
                "</dd></div>" +
                "<div><dt>邀請碼</dt><dd>" +
                esc(u.staff_invite_code || "—") +
                "</dd></div>" +
                "<div><dt>建立者</dt><dd>" +
                esc(adminCreatorLabelZh(u)) +
                "</dd></div>" +
                "<div><dt>組別</dt><dd>" +
                esc(adminGroupLabelZh(u)) +
                "</dd></div>" +
                "<div><dt>狀態</dt><dd>" +
                esc(adminStatusLabelZh(u.status)) +
                "</dd></div>" +
                "<div><dt>角色模板</dt><dd>" +
                esc(roleTemplateLabelZh(u.role_template)) +
                "</dd></div>" +
                "<div><dt>底層權限</dt><dd>" +
                esc(roleCodesSummaryZh(u.role_codes, u.is_super_admin, u.role_template)) +
                "</dd></div>" +
                "<div><dt>模組權限</dt><dd>" +
                esc(moduleAccessSummaryZh(u.module_access)) +
                "</dd></div>" +
                "<div><dt>組全局資料</dt><dd>" +
                (u.can_view_group_global_data ? "可查看" : "僅下級鏈路") +
                "</dd></div>" +
                "<div><dt>組超管</dt><dd>" +
                (u.is_super_admin ? "是" : "否") +
                "</dd></div></dl>";
            })
            .catch(function () {});
        }
        if (open === "admin-modal-admin-user-edit") {
          api()
            .requestJson("/api/admin/admin-users/" + encodeURIComponent(id), {
              fallbackMessage: "載入失敗"
            })
            .then(function (data) {
              var u = (data && data.admin_user) || {};
              var f = document.getElementById("view-admin-edit-user-form");
              if (!f) return;
              document.getElementById("view-admin-edit-id").value = id;
              document.getElementById("view-admin-edit-account").value = u.account || "";
              document.getElementById("view-admin-edit-display").value = u.display_name || "";
              document.getElementById("view-admin-edit-invite").value = u.staff_invite_code || "";
              document.getElementById("view-admin-edit-status").value = u.status || "normal";
              var editSuper = document.getElementById("view-admin-edit-super");
              if (editSuper) {
                editSuper.checked = !!u.is_super_admin;
                editSuper.disabled = !currentAdminIsRoot();
                editSuper.title = currentAdminIsRoot() ? "" : "只有大老板可調整組超級管理員";
              }
              setAdminScopeControls("edit", u);
              var rt = document.getElementById("view-admin-edit-role-template");
              if (rt) {
                var tpl = u.role_template || "finance_ops";
                var optCustom = rt.querySelector('option[value="custom"]');
                if (tpl === "custom") {
                  if (!optCustom) {
                    rt.insertAdjacentHTML("beforeend", '<option value="custom">自訂</option>');
                  }
                } else if (optCustom) {
                  optCustom.remove();
                }
                rt.value = tpl;
              }
            })
            .catch(function () {});
        }
        if (open === "admin-modal-admin-password") {
          var pid = document.getElementById("view-admin-password-id");
          if (pid) pid.value = id;
        }
        if (open === "admin-modal-admin-user-permissions") {
          openPermissionsEditor(id);
        }
      },
      true
    );
  }

  function openPermissionsEditor(id) {
    if (window.AdminModals && window.AdminModals.open) {
      window.AdminModals.open("admin-modal-admin-user-permissions");
    }
    var note = document.getElementById("view-admin-perm-super-note");
    var matrix = document.getElementById("view-admin-perm-matrix");
    var saveBtn = document.getElementById("view-admin-perm-save");
    var copyPermBtn = document.getElementById("view-admin-perm-copy-template");
    var hiddenId = document.getElementById("view-admin-perm-target-id");
    var msg = document.getElementById("view-admin-perm-msg");
    if (hiddenId) hiddenId.value = id;
    if (msg) msg.textContent = "";
    if (saveBtn) {
      saveBtn.textContent = "儲存";
    }
    if (copyPermBtn) copyPermBtn.disabled = true;
    var catalog = _adminCatalogCache && _adminCatalogCache.catalog;
    if (!catalog || typeof catalog !== "object") {
      if (matrix) {
        matrix.innerHTML =
          '<p class="admin-modal__empty-note">請先成功載入管理員列表（需超管）以取得模組目錄。</p>';
      }
      if (saveBtn) saveBtn.disabled = true;
      if (note) note.hidden = true;
      return;
    }
    if (matrix) matrix.innerHTML = '<p class="admin-muted">載入中…</p>';
    if (saveBtn) saveBtn.disabled = true;
    if (note) note.hidden = true;
    api()
      .requestJson("/api/admin/admin-users/" + encodeURIComponent(id), {
        fallbackMessage: "載入失敗"
      })
      .then(function (data) {
        var u = (data && data.admin_user) || {};
        if (hiddenId) hiddenId.value = String(u.id || id);
        if (u.is_super_admin) {
          if (note) {
            note.hidden = false;
            note.textContent =
              "此帳號為組超級管理員：後端在其授權範圍內對全部模組等同「修改」。下列為與目錄對照之唯讀預覽（無法於此儲存變更）。";
          }
          var moduleKeysSuper = Object.keys(catalog).sort();
          var rowsSuper = moduleKeysSuper
            .map(function (mk) {
              return buildModuleAccessRowHTML(mk, "write", "write", true, "perm");
            })
            .join("");
          if (matrix) matrix.innerHTML = rowsSuper || '<p class="admin-muted">尚無模組目錄</p>';
          if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = "僅供檢視";
          }
          if (copyPermBtn) copyPermBtn.disabled = true;
          return;
        }
        if (note) note.hidden = true;
        var access = u.module_access || {};
        var moduleKeys = Object.keys(catalog).sort();
        var rows = moduleKeys
          .map(function (mk) {
            var cur = access[mk];
            var grant = currentAdminModuleGrant(mk);
            return buildModuleAccessRowHTML(mk, cur, grant, false, "perm");
          })
          .join("");
        if (matrix) matrix.innerHTML = rows || '<p class="admin-muted">尚無模組目錄</p>';
        if (saveBtn) {
          saveBtn.textContent = "儲存";
          saveBtn.disabled = false;
        }
        if (copyPermBtn) copyPermBtn.disabled = false;
      })
      .catch(function (err) {
        if (err && err.adminSessionHandled) return;
        if (matrix) {
          matrix.innerHTML =
            '<p class="admin-modal__empty-note">' + esc(err.message || "載入失敗") + "</p>";
        }
        if (saveBtn) saveBtn.disabled = true;
        if (copyPermBtn) copyPermBtn.disabled = true;
      });
  }

  function ensureCreateAdminFormCustomOption() {
    var sel = document.getElementById("view-admin-create-role-template");
    if (!sel) return;
    if (!sel.querySelector('option[value="custom"]')) {
      sel.insertAdjacentHTML("beforeend", '<option value="custom">自訂</option>');
    }
  }

  function prepareCreateAdminFromCopiedModuleAccess(access) {
    _adminCreateUserModuleAccessDraft = Object.assign({}, access || {});
    if (window.AdminModals && window.AdminModals.close) {
      var pm = document.getElementById("admin-modal-admin-user-permissions");
      if (pm) window.AdminModals.close(pm);
    }
    ensureCreateAdminFormCustomOption();
    if (window.AdminModals && window.AdminModals.open) {
      window.AdminModals.open("admin-modal-create-admin-user");
    }
    window.setTimeout(function () {
      var form = document.getElementById("view-admin-create-user-form");
      var createSel = document.getElementById("view-admin-create-role-template");
      var cmsg = document.getElementById("view-admin-create-msg");
      var account = document.getElementById("view-admin-create-account");
      var createSuper = document.getElementById("view-admin-create-super");
      if (form) form.reset();
      if (createSuper && !currentAdminIsRoot()) {
        createSuper.checked = false;
        createSuper.disabled = true;
        createSuper.title = "只有大老板可新增組超級管理員";
      } else if (createSuper) {
        createSuper.checked = false;
        createSuper.disabled = false;
        createSuper.title = "";
      }
      ensureCreateAdminFormCustomOption();
      if (createSel) createSel.value = "custom";
      if (cmsg) {
        cmsg.textContent =
          "已帶入「模組權限」中的設定（角色模板為「自訂」）。請填寫登入帳號與初始密碼後建立。";
        cmsg.classList.remove("admin-create-user-message--error");
      }
      if (account) account.focus();
      syncCreateAdminCustomModulePanel();
    }, 50);
  }

  function initAdminPermCopyTemplateBtn() {
    var copyBtn = document.getElementById("view-admin-perm-copy-template");
    if (!copyBtn || copyBtn._adminBound) return;
    copyBtn._adminBound = true;
    copyBtn.addEventListener("click", function () {
      var saveBtn = document.getElementById("view-admin-perm-save");
      if (saveBtn && saveBtn.disabled) {
        if (typeof window.showAdminToast === "function") {
          window.showAdminToast("目前為唯讀預覽，無法複製模版。");
        }
        return;
      }
      var matrix = document.getElementById("view-admin-perm-matrix");
      var access = collectAdminPermMatrixModuleAccess(matrix);
      if (Object.keys(access).length === 0) {
        var pmsg = document.getElementById("view-admin-perm-msg");
        if (pmsg) pmsg.textContent = "請至少為一個模組選擇「閱讀」或「修改」，再複製模版。";
        return;
      }
      prepareCreateAdminFromCopiedModuleAccess(access);
    });
  }

  function initAdminPermissionsSave() {
    var btn = document.getElementById("view-admin-perm-save");
    if (!btn || btn._adminBound) return;
    btn._adminBound = true;
    btn.addEventListener("click", function () {
      var hiddenId = document.getElementById("view-admin-perm-target-id");
      var uid = hiddenId ? hiddenId.value : "";
      var matrix = document.getElementById("view-admin-perm-matrix");
      var module_access = collectAdminPermMatrixModuleAccess(matrix);
      var msg = document.getElementById("view-admin-perm-msg");
      if (Object.keys(module_access).length === 0) {
        if (msg) msg.textContent = "請至少為一個模組選擇「閱讀」或「修改」。";
        return;
      }
      if (msg) msg.textContent = "儲存中…";
      api()
        .requestJson("/api/admin/admin-users/" + encodeURIComponent(uid), {
          method: "PATCH",
          body: { module_access: module_access, role_template: "custom" },
          fallbackMessage: "更新權限失敗"
        })
        .then(function () {
          if (msg) msg.textContent = "已更新模組權限。";
          loadViewAdminUsers();
          if (window.AdminModals && window.AdminModals.close) {
            var m = document.getElementById("admin-modal-admin-user-permissions");
            if (m) window.AdminModals.close(m);
          }
        })
        .catch(function (err) {
          if (err && err.adminSessionHandled) return;
          if (msg) msg.textContent = err.message || "失敗";
        });
    });
  }

  function initEditAdminUserForm() {
    var form = document.getElementById("view-admin-edit-user-form");
    if (!form || form._adminBound) return;
    form._adminBound = true;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var id = document.getElementById("view-admin-edit-id").value;
      var editSuper = document.getElementById("view-admin-edit-super");
      var editGroupName = document.getElementById("view-admin-edit-group-name");
      var editGroupCode = document.getElementById("view-admin-edit-group-code");
      var editGroupGlobal = document.getElementById("view-admin-edit-group-global");
      var body = {
        account: document.getElementById("view-admin-edit-account").value.trim(),
        display_name: document.getElementById("view-admin-edit-display").value.trim(),
        staff_invite_code: document.getElementById("view-admin-edit-invite").value.trim() || null,
        status: document.getElementById("view-admin-edit-status").value,
        is_super_admin: currentAdminIsRoot() && editSuper && editSuper.checked,
        role_template: document.getElementById("view-admin-edit-role-template").value,
        admin_group_name: editGroupName ? editGroupName.value.trim() : "",
        admin_group_code: editGroupCode ? editGroupCode.value.trim() : ""
      };
      if (editGroupGlobal && !editGroupGlobal.disabled) {
        body.can_view_group_global_data = editGroupGlobal.checked;
      }
      var msg = document.getElementById("view-admin-edit-msg");
      if (msg) msg.textContent = "儲存中…";
      api()
        .requestJson("/api/admin/admin-users/" + encodeURIComponent(id), {
          method: "PATCH",
          body: body,
          fallbackMessage: "更新失敗"
        })
        .then(function () {
          if (msg) msg.textContent = "已更新。";
          loadViewAdminUsers();
          if (window.AdminModals && window.AdminModals.close) {
            var m = document.getElementById("admin-modal-admin-user-edit");
            if (m) window.AdminModals.close(m);
          }
        })
        .catch(function (err) {
          if (err && err.adminSessionHandled) return;
          if (msg) msg.textContent = err.message || "更新失敗";
        });
    });
  }

  function initAdminPasswordForm() {
    var form = document.getElementById("view-admin-password-form");
    if (!form || form._adminBound) return;
    form._adminBound = true;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var id = document.getElementById("view-admin-password-id").value;
      var pw = document.getElementById("view-admin-password-new").value;
      var msg = document.getElementById("view-admin-password-msg");
      if (msg) msg.textContent = "更新中…";
      api()
        .requestJson("/api/admin/admin-users/" + encodeURIComponent(id) + "/password", {
          method: "PATCH",
          body: { password: pw },
          fallbackMessage: "重設密碼失敗"
        })
        .then(function () {
          if (msg) msg.textContent = "密碼已更新。";
          document.getElementById("view-admin-password-new").value = "";
          if (window.AdminModals && window.AdminModals.close) {
            var m = document.getElementById("admin-modal-admin-password");
            if (m) window.AdminModals.close(m);
          }
        })
        .catch(function (err) {
          if (err && err.adminSessionHandled) return;
          if (msg) msg.textContent = err.message || "失敗";
        });
    });
  }

  function bindWalletsReload() {
    bindWalletAdjustModal();
    var loadBtn = document.getElementById("view-wallets-load");
    if (loadBtn && !loadBtn._adminBound) {
      loadBtn._adminBound = true;
      loadBtn.addEventListener("click", function () {
        loadViewWallets();
      });
    }
    var refreshBtn = document.getElementById("view-wallets-refresh");
    if (refreshBtn && !refreshBtn._adminBound) {
      refreshBtn._adminBound = true;
      refreshBtn.addEventListener("click", function () {
        loadViewWallets();
      });
    }
    var resetBtn = document.getElementById("view-wallets-reset");
    if (resetBtn && !resetBtn._adminBound) {
      resetBtn._adminBound = true;
      resetBtn.addEventListener("click", function () {
        var kw = document.getElementById("view-wallets-keyword");
        var uid = document.getElementById("view-wallets-user-id");
        var asset = document.getElementById("view-wallets-asset");
        if (kw) kw.value = "";
        if (uid) uid.value = "";
        if (asset) asset.value = "";
        loadViewWallets();
      });
    }
    var root = document.getElementById("view-wallets");
    if (root && !root._adminWalletAdjustClickBound) {
      root._adminWalletAdjustClickBound = true;
      root.addEventListener("click", function (e) {
        var rowBtn = e.target.closest("[data-admin-wallet-adjust]");
        if (rowBtn) {
          openWalletAdjustForUser(rowBtn.getAttribute("data-admin-wallet-adjust"));
          return;
        }
        var ledgerRowBtn = e.target.closest("[data-admin-wallet-ledger]");
        if (ledgerRowBtn) {
          openWalletLedgerForUser(ledgerRowBtn.getAttribute("data-admin-wallet-ledger"));
          return;
        }
        var toolbarBtn = e.target.closest("[data-admin-wallet-adjust-toolbar]");
        if (toolbarBtn) {
          openWalletAdjustForUser("");
        }
      });
    }
    var ledgerBtn = document.getElementById("view-wallets-ledger");
    if (ledgerBtn && !ledgerBtn._adminWalletLedgerBound) {
      ledgerBtn._adminWalletLedgerBound = true;
      ledgerBtn.addEventListener("click", function () {
        openWalletLedgerForUser("");
      });
    }
  }

  function bindHomeNoticeSave() {
    var refreshBtn = document.getElementById("view-home-content-refresh");
    if (refreshBtn && !refreshBtn._adminBound) {
      refreshBtn._adminBound = true;
      refreshBtn.addEventListener("click", function () {
        loadViewHomeContent();
      });
    }
    var btn = document.getElementById("view-home-notice-save");
    if (btn && !btn._adminBound) {
      btn._adminBound = true;
      btn.addEventListener("click", function () {
        var ta = document.getElementById("view-home-notice-text");
        var reason = document.getElementById("view-home-notice-reason");
        var body = {
          notice: ta ? ta.value : "",
          reason: reason ? reason.value.trim() : ""
        };
        if (!body.reason) {
          adminNotifyError("請填寫修改原因");
          return;
        }
        api()
          .requestJson("/api/admin/home-content/notice", {
            method: "PATCH",
            body: body,
            fallbackMessage: "儲存失敗"
          })
          .then(function () {
            adminNotifySuccess("公告已儲存");
          })
          .catch(function (e) {
            adminNotifyError(e.message || "儲存失敗");
          });
      });
    }
  }

  var LOADERS = {
    dashboard: loadViewDashboard,
    users: loadViewUsers,
    wallets: function () {
      bindWalletsReload();
      return loadViewWallets();
    },
    tiers: loadViewTiers,
    deposits: loadViewDeposits,
    "deposit-addresses": loadViewDepositAddresses,
    withdrawals: function () {
      bindViewWithdrawalsPage();
      return loadViewWithdrawals();
    },
    "eur-swap-withdraw-config": function () {
      bindViewEurSwapPage();
      return loadViewEurSwap();
    },
    orders: function () {
      bindViewOrdersFilters();
      return loadViewOrders();
    },
    "financial-products": function () {
      bindSimpleFilterControls("view-financial-products", {
        query: "view-fp-query",
        refresh: "view-fp-refresh",
        reset: "view-fp-reset",
        inputs: ["view-fp-keyword", "view-fp-status"]
      }, loadViewFinancialProducts);
      return loadViewFinancialProducts();
    },
    "financial-orders": function () {
      bindSimpleFilterControls("view-financial-orders", {
        query: "view-fo-query",
        refresh: "view-fo-refresh",
        reset: "view-fo-reset",
        inputs: ["view-fo-keyword", "view-fo-user", "view-fo-return-mode", "view-fo-status"]
      }, loadViewFinancialOrders);
      return loadViewFinancialOrders();
    },
    listings: loadViewListings,
    "trade-feed": function () {
      bindSimpleFilterControls("view-trade-feed", {
        query: "view-tf-query",
        refresh: "view-tf-refresh",
        reset: "view-tf-reset",
        inputs: ["view-tf-keyword", "view-tf-status"]
      }, loadViewTradeFeed);
      return loadViewTradeFeed();
    },
    "home-content": function () {
      bindHomeNoticeSave();
      return loadViewHomeContent();
    },
    kyc: loadViewKyc,
    "payout-methods": loadViewPayoutMethods,
    "auth-events": function () {
      bindAuthLogTabs();
      bindSimpleFilterControls("view-auth-events", {
        query: "view-ae-query",
        refresh: "view-ae-refresh",
        reset: "view-ae-reset",
        inputs: ["view-ae-account", "view-ae-user", "view-ae-error", "view-ae-action"]
      }, loadViewAuthEvents);
      return loadViewAuthEvents();
    },
    "system-config": function () {
      bindSystemConfigPage();
      return loadViewSystemConfig();
    },
    "admin-users": function () {
      bindAdminUsersPage();
      initRoleTemplateManagement();
      initCreateAdminGroupForm();
      initCreateAdminUserForm();
      initAdminUserDetailHooks();
      initEditAdminUserForm();
      initAdminPasswordForm();
      initAdminPermissionsSave();
      initAdminPermCopyTemplateBtn();
      return loadViewAdminUsers();
    }
  };

  function currentViewId() {
    var h = (window.location.hash || "").replace(/^#/, "").trim();
    if (!h) return "dashboard";
    return h;
  }

  function reloadCurrentView() {
    if (!window.__ADMIN_API_FETCH__) return;
    var token =
      window.__ADMIN_SESSION__ && window.__ADMIN_SESSION__.getApiToken
        ? window.__ADMIN_SESSION__.getApiToken()
        : "";
    if (!token) return;
    var id = currentViewId();
    var fn = LOADERS[id];
    if (fn) fn();
  }

  function boot() {
    document.addEventListener("click", function (e) {
      var unavailable = e.target && e.target.closest ? e.target.closest("[data-admin-unavailable]") : null;
      if (unavailable) {
        e.preventDefault();
        adminNotifyError(unavailable.getAttribute("data-admin-unavailable") || "此功能尚未開放。");
        return;
      }
      var target = e.target && e.target.closest ? e.target.closest("[data-admin-withdrawal-detail],[data-admin-order-detail],[data-admin-order-approve],[data-admin-order-reject],[data-admin-financial-order-detail],[data-admin-auth-event-detail]") : null;
      if (!target) return;
      if (target.hasAttribute("data-admin-withdrawal-detail")) openWithdrawalDetail(target.getAttribute("data-admin-withdrawal-detail"));
      else if (target.hasAttribute("data-admin-order-detail")) openOrderDetail(target.getAttribute("data-admin-order-detail"));
      else if (target.hasAttribute("data-admin-order-approve")) {
        runAdminOrderApprove(
          target.getAttribute("data-admin-order-approve"),
          target.getAttribute("data-admin-order-merchant-source"),
          target
        ).catch(function (err) {
          if (err && err.adminSessionHandled) return;
          adminNotifyError((err && err.message) || "訂單通過失敗");
        });
      } else if (target.hasAttribute("data-admin-order-reject")) {
        runAdminOrderReject(
          target.getAttribute("data-admin-order-reject"),
          target.getAttribute("data-admin-order-merchant-source"),
          target
        ).catch(function (err) {
          if (err && err.adminSessionHandled) return;
          adminNotifyError((err && err.message) || "訂單駁回失敗");
        });
      }
      else if (target.hasAttribute("data-admin-financial-order-detail")) openFinancialOrderDetail(target.getAttribute("data-admin-financial-order-detail"));
      else if (target.hasAttribute("data-admin-auth-event-detail")) openAuthEventDetail(target.getAttribute("data-admin-auth-event-detail"));
    });
    window.addEventListener("hashchange", reloadCurrentView);
    window.addEventListener("eurnyse-admin-api-token-ready", reloadCurrentView);
    function start() {
      initTradeFeedForms();
      initHomeBannerForms();
      initFinancialProductForms();
      initDepositAddressForms();
      initListingForms();
      initTierTemplateForms();
      initTutorialLinkForms();
      setTimeout(reloadCurrentView, 0);
    }
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", start, { once: true });
    } else {
      start();
    }
  }

  boot();

  window.__ADMIN_VIEW_LOADERS__ = {
    reloadCurrentView: reloadCurrentView,
    LOADERS: LOADERS
  };
})(window, document);
