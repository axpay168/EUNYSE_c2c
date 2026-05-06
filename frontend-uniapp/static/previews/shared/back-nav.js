(function (window) {
  function applyHashOrHref(fallback) {
    if (!fallback) return;
    try {
      if (fallback.charAt(0) === "#") {
        window.location.hash = fallback;
      } else {
        window.location.href = fallback;
      }
    } catch (e) {}
  }

  /** 非 uni 場景：瀏覽器歷史可退則 back，否則走 fallback */
  function goHistoryThenFallback(fallback) {
    try {
      if (window.history && window.history.length > 1) {
        window.history.back();
        return;
      }
    } catch (e) {}
    applyHashOrHref(fallback);
  }

  /**
   * 返回上一頁：優先使用 uni 頁面棧（與從哪裡進入一致），
   * 再嘗試瀏覽器 history，最後才用 fallback（例如無堆疊、直連進入頁面）。
   * 純靜態預覽無 uni 時仍走 goHistoryThenFallback。
   */
  function go(fallback) {
    if (typeof uni !== "undefined" && typeof uni.navigateBack === "function") {
      uni.navigateBack({
        delta: 1,
        fail: function () {
          goHistoryThenFallback(fallback);
        }
      });
      return false;
    }
    goHistoryThenFallback(fallback);
    return false;
  }

  window.EurforexBack = { go: go };

  function isLockedPage() {
    var path = (window.location.pathname || "").toLowerCase();
    return (
      path.indexOf("/login.html") !== -1 ||
      path.indexOf("/register.html") !== -1 ||
      path.indexOf("/user.html") !== -1
    );
  }

  function bootElevatedTheme() {
    if (isLockedPage()) return;
    if (!document.body) return;
    document.body.classList.add("eurforex-elevated");

    var targets = document.querySelectorAll(
      "main > section, main > div, main .glass-panel, main .glass-panel-high, main .usdt-card, main .fiat-card, main .eur-card"
    );
    if (!targets.length) return;

    if (!("IntersectionObserver" in window)) {
      targets.forEach(function (el) {
        el.classList.add("eurforex-reveal", "is-visible");
      });
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        });
      },
      { threshold: 0.08, rootMargin: "0px 0px -8% 0px" }
    );

    targets.forEach(function (el, idx) {
      if (el.classList.contains("eurforex-reveal")) return;
      el.classList.add("eurforex-reveal");
      el.style.transitionDelay = Math.min(idx * 35, 210) + "ms";
      observer.observe(el);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootElevatedTheme, { once: true });
  } else {
    bootElevatedTheme();
  }
})(window);
