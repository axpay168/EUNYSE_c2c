/**
 * EUR 法幣充值入口：先顯示「聯繫客服」提示，再可進入充值頁（?skipEurModal=1 略過）。
 * 使用 inline style，避免 Tailwind CDN 未掃描外部 JS 造成樣式缺失。
 */
(function () {
  var MODAL_ID = "eurnyse-eur-deposit-modal";

  function ensureModal() {
    var existing = document.getElementById(MODAL_ID);
    if (existing) return existing;

    var root = document.createElement("div");
    root.id = MODAL_ID;
    root.setAttribute("role", "dialog");
    root.setAttribute("aria-modal", "true");
    root.setAttribute("aria-labelledby", "eur-deposit-modal-title");
    root.style.cssText =
      "display:none;position:fixed;inset:0;z-index:200;align-items:center;justify-content:center;padding:16px;background:rgba(22,41,63,0.45);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);";

    var panel = document.createElement("div");
    panel.style.cssText =
      "width:100%;max-width:384px;border-radius:24px;border:1px solid rgba(199,213,226,0.25);background:#ffffff;padding:24px;box-shadow:0 24px 48px rgba(22,41,63,0.18);font-family:Manrope,system-ui,sans-serif;";

    panel.innerHTML =
      '<h2 id="eur-deposit-modal-title" style="margin:0;font-family:\'Space Grotesk\',sans-serif;font-size:1.125rem;font-weight:700;color:#16293f;">EUR 充值</h2>' +
      '<p style="margin:12px 0 0;font-size:0.875rem;line-height:1.65;color:#617588;">法幣（EUR）入金須由客服提供收款帳戶與入金說明，請先聯繫客服確認後再行操作。</p>' +
      '<div style="margin-top:24px;display:flex;flex-direction:column;gap:12px;">' +
      '<a style="display:flex;height:48px;width:100%;align-items:center;justify-content:center;border-radius:999px;background:#56afe8;font-family:\'Space Grotesk\',sans-serif;font-size:0.875rem;font-weight:700;letter-spacing:0.04em;color:#fff;text-decoration:none;box-shadow:0 12px 28px rgba(47,115,205,0.28);" href="./service-center.html">聯繫客服</a>' +
      '<button type="button" class="eur-deposit-modal-continue" style="display:flex;height:48px;width:100%;align-items:center;justify-content:center;border-radius:999px;border:1px solid rgba(199,213,226,0.45);background:#f0f6fc;font-family:\'Space Grotesk\',sans-serif;font-size:0.875rem;font-weight:600;color:#16293f;cursor:pointer;">繼續查看充值頁面</button>' +
      '<button type="button" class="eur-deposit-modal-close" style="margin:0;padding:8px;border:none;background:transparent;font-size:0.875rem;font-weight:500;color:#617588;text-decoration:underline;text-underline-offset:2px;cursor:pointer;">關閉</button>' +
      "</div>";

    root.appendChild(panel);
    document.body.appendChild(root);

    root.addEventListener("click", function (e) {
      if (e.target === root) closeModal();
    });

    panel.querySelector(".eur-deposit-modal-continue").addEventListener("click", function () {
      closeModal();
      window.location.href = "./eur-deposit.html?skipEurModal=1";
    });

    panel.querySelector(".eur-deposit-modal-close").addEventListener("click", closeModal);

    return root;
  }

  function openModal() {
    var el = ensureModal();
    el.style.display = "flex";
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    var el = document.getElementById(MODAL_ID);
    if (!el) return;
    el.style.display = "none";
    document.body.style.overflow = "";
  }

  function isEurDepositPage() {
    var path = (window.location.pathname || "").toLowerCase();
    return path.indexOf("eur-deposit.html") !== -1;
  }

  function shouldAutoOpenOnEurDeposit() {
    if (!isEurDepositPage()) return false;
    var q = window.location.search || "";
    if (q.indexOf("skipEurModal=1") !== -1) return false;
    return true;
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("a.eur-deposit-gate[href*='eur-deposit.html']").forEach(function (a) {
      a.addEventListener("click", function (e) {
        e.preventDefault();
        openModal();
      });
    });

    if (shouldAutoOpenOnEurDeposit()) {
      openModal();
    }
  });

  window.EurDepositModal = { open: openModal, close: closeModal };
})();
