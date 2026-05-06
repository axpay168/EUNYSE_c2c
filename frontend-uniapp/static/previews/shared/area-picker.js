/**
 * EURFOREX 區號選擇彈窗（結構對齊 New_C2C pages/common/area.vue：頂欄標題、搜尋欄、列表列）
 * 依賴：localStorage.lang、localStorage.selected_area_code
 */
(function (window) {
  const AREA_OPTIONS = [
    { key: "greece", code: "+30", nameZh: "希臘", nameEn: "Greece" },
    { key: "netherlands", code: "+31", nameZh: "荷蘭", nameEn: "Netherlands" },
    { key: "belgium", code: "+32", nameZh: "比利時", nameEn: "Belgium" },
    { key: "france", code: "+33", nameZh: "法國", nameEn: "France" },
    { key: "spain", code: "+34", nameZh: "西班牙", nameEn: "Spain" },
    { key: "hungary", code: "+36", nameZh: "匈牙利", nameEn: "Hungary" },
    { key: "italy", code: "+39", nameZh: "義大利", nameEn: "Italy" },
    { key: "austria", code: "+43", nameZh: "奧地利", nameEn: "Austria" },
    { key: "uk", code: "+44", nameZh: "英國", nameEn: "United Kingdom" },
    { key: "denmark", code: "+45", nameZh: "丹麥", nameEn: "Denmark" },
    { key: "sweden", code: "+46", nameZh: "瑞典", nameEn: "Sweden" },
    { key: "poland", code: "+48", nameZh: "波蘭", nameEn: "Poland" },
    { key: "germany", code: "+49", nameZh: "德國", nameEn: "Germany" },
    { key: "australia", code: "+61", nameZh: "澳洲", nameEn: "Australia" },
    { key: "philippines", code: "+63", nameZh: "菲律賓", nameEn: "Philippines" },
    { key: "singapore", code: "+65", nameZh: "新加坡", nameEn: "Singapore" },
    { key: "thailand", code: "+66", nameZh: "泰國", nameEn: "Thailand" },
    { key: "japan", code: "+81", nameZh: "日本", nameEn: "Japan" },
    { key: "southKorea", code: "+82", nameZh: "韓國", nameEn: "South Korea" },
    { key: "vietnam", code: "+84", nameZh: "越南", nameEn: "Vietnam" },
    { key: "china", code: "+86", nameZh: "中國", nameEn: "China" },
    { key: "india", code: "+91", nameZh: "印度", nameEn: "India" },
    { key: "pakistan", code: "+92", nameZh: "巴基斯坦", nameEn: "Pakistan" },
    { key: "afghanistan", code: "+93", nameZh: "阿富汗", nameEn: "Afghanistan" },
    { key: "portugal", code: "+351", nameZh: "葡萄牙", nameEn: "Portugal" },
    { key: "luxembourg", code: "+352", nameZh: "盧森堡", nameEn: "Luxembourg" },
    { key: "ireland", code: "+353", nameZh: "愛爾蘭", nameEn: "Ireland" },
    { key: "finland", code: "+358", nameZh: "芬蘭", nameEn: "Finland" },
    { key: "hongKong", code: "+852", nameZh: "香港", nameEn: "Hong Kong" },
    { key: "macau", code: "+853", nameZh: "澳門", nameEn: "Macau" },
    { key: "nepal", code: "+977", nameZh: "尼泊爾", nameEn: "Nepal" }
  ];

  const PAGE_TEXTS = {
    "zh-Hant": {
      title: "區碼選擇",
      searchLabel: "搜尋國家 / 區碼",
      searchPlaceholder: "輸入國家名稱或 + 區碼",
      back: "返回"
    },
    "zh-Hans": {
      title: "区号选择",
      searchLabel: "搜索国家 / 区号",
      searchPlaceholder: "输入国家名称或 + 区号",
      back: "返回"
    },
    eng: {
      title: "Area Code",
      searchLabel: "Search Country / Code",
      searchPlaceholder: "Type country or + code",
      back: "Back"
    }
  };

  function getLang() {
    return localStorage.getItem("lang") || "zh-Hant";
  }

  function getTexts() {
    const lang = getLang();
    return PAGE_TEXTS[lang] || PAGE_TEXTS.eng;
  }

  function itemName(item) {
    const lang = getLang();
    return lang === "zh-Hant" || lang === "zh-Hans" ? item.nameZh : item.nameEn;
  }

  let rootEl = null;

  function injectStyles() {
    if (document.getElementById("eurforex-area-picker-styles")) return;
    const style = document.createElement("style");
    style.id = "eurforex-area-picker-styles";
    style.textContent = `
      .eurforex-area-picker {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: none;
        align-items: flex-end;
        justify-content: center;
        padding: 0;
        box-sizing: border-box;
      }
      .eurforex-area-picker.is-open {
        display: flex;
      }
      .eurforex-area-picker__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(19, 35, 51, 0.42);
        backdrop-filter: blur(4px);
      }
      .eurforex-area-picker__sheet {
        position: relative;
        width: 100%;
        max-width: 430px;
        max-height: min(92vh, 760px);
        background: linear-gradient(180deg, rgba(255,255,255,0.99), rgba(240,246,252,0.99));
        border: 1px solid rgba(82, 116, 164, 0.18);
        border-radius: 22px 22px 0 0;
        box-shadow: 0 -8px 40px rgba(37, 67, 102, 0.18);
        display: flex;
        flex-direction: column;
        color: #21384c;
        font-family: "Manrope", "PingFang TC", "Microsoft JhengHei", sans-serif;
        animation: eurforex-area-picker-in 0.28s ease-out;
      }
      @keyframes eurforex-area-picker-in {
        from { transform: translateY(12px); opacity: 0.92; }
        to { transform: translateY(0); opacity: 1; }
      }
      .eurforex-area-picker__top {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px 8px;
        border-bottom: 1px solid rgba(82, 116, 164, 0.12);
        flex-shrink: 0;
      }
      .eurforex-area-picker__back {
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 12px;
        background: rgba(82, 116, 164, 0.08);
        color: #35557f;
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
        padding: 0;
      }
      .eurforex-area-picker__back:hover {
        background: rgba(82, 116, 164, 0.14);
      }
      .eurforex-area-picker__title {
        flex: 1;
        text-align: center;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #102538;
        padding-right: 40px;
        box-sizing: border-box;
      }
      .eurforex-area-picker__card {
        margin: 12px 14px 0;
        padding: 12px 14px 14px;
        border-radius: 18px;
        border: 1px solid rgba(82, 116, 164, 0.16);
        background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(242,247,253,0.96));
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.72);
        flex-shrink: 0;
      }
      .eurforex-area-picker__label {
        font-size: 12px;
        font-weight: 700;
        color: #52687d;
        margin-bottom: 8px;
      }
      .eurforex-area-picker__input {
        width: 100%;
        height: 46px;
        border-radius: 14px;
        border: 1px solid rgba(82, 116, 164, 0.22);
        background: rgba(248, 251, 255, 0.98);
        padding: 0 14px;
        font-size: 15px;
        color: #12283b;
        outline: none;
        box-sizing: border-box;
      }
      .eurforex-area-picker__input:focus {
        border-color: rgba(82, 116, 164, 0.5);
        box-shadow: 0 0 0 2px rgba(82, 116, 164, 0.12);
      }
      .eurforex-area-picker__list {
        flex: 1;
        overflow: auto;
        padding: 8px 14px 20px;
        -webkit-overflow-scrolling: touch;
      }
      .eurforex-area-picker__row {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 4px;
        border: none;
        border-bottom: 1px solid rgba(82, 116, 164, 0.12);
        background: transparent;
        text-align: left;
        cursor: pointer;
        color: inherit;
        font: inherit;
      }
      .eurforex-area-picker__row:last-child {
        border-bottom: 0;
      }
      .eurforex-area-picker__row:active {
        opacity: 0.88;
      }
      .eurforex-area-picker__name {
        min-width: 0;
        flex: 1;
        font-size: 14px;
        font-weight: 700;
        color: #12283b;
        line-height: 1.4;
      }
      .eurforex-area-picker__code {
        flex: 0 0 auto;
        font-size: 14px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        color: #35557f;
      }
      @media (min-width: 480px) {
        .eurforex-area-picker {
          align-items: center;
          padding: 16px;
        }
        .eurforex-area-picker__sheet {
          border-radius: 22px;
          max-height: min(88vh, 720px);
        }
      }
    `;
    document.head.appendChild(style);
  }

  function ensureMounted() {
    injectStyles();
    if (rootEl) return;
    rootEl = document.createElement("div");
    rootEl.id = "eurforex-area-picker";
    rootEl.className = "eurforex-area-picker";
    rootEl.setAttribute("aria-hidden", "true");
    rootEl.innerHTML = `
      <div class="eurforex-area-picker__backdrop" data-eap-close="1"></div>
      <div class="eurforex-area-picker__sheet" role="dialog" aria-modal="true" aria-labelledby="eap-title-el">
        <div class="eurforex-area-picker__top">
          <button type="button" class="eurforex-area-picker__back" id="eap-back" aria-label="返回">‹</button>
          <div class="eurforex-area-picker__title" id="eap-title-el"></div>
        </div>
        <div class="eurforex-area-picker__card">
          <div class="eurforex-area-picker__label" id="eap-search-label"></div>
          <input type="text" class="eurforex-area-picker__input" id="eap-search" autocomplete="off" />
        </div>
        <div class="eurforex-area-picker__list" id="eap-list"></div>
      </div>
    `;
    document.body.appendChild(rootEl);

    rootEl.querySelector("[data-eap-close]").addEventListener("click", close);
    document.getElementById("eap-back").addEventListener("click", close);
    document.getElementById("eap-search").addEventListener("input", function () {
      renderList(this.value);
    });
  }

  function renderChrome() {
    const texts = getTexts();
    document.getElementById("eap-title-el").textContent = texts.title;
    document.getElementById("eap-search-label").textContent = texts.searchLabel;
    const input = document.getElementById("eap-search");
    input.placeholder = texts.searchPlaceholder;
    document.getElementById("eap-back").setAttribute("aria-label", texts.back);
  }

  function renderList(keyword) {
    const list = document.getElementById("eap-list");
    const normalized = (keyword || "").trim().toLowerCase();
    const filtered = !normalized
      ? AREA_OPTIONS
      : AREA_OPTIONS.filter(function (item) {
          return (
            item.code.toLowerCase().includes(normalized) ||
            item.nameEn.toLowerCase().includes(normalized) ||
            item.nameZh.toLowerCase().includes(normalized)
          );
        });
    list.innerHTML = filtered
      .map(function (item) {
        const name = itemName(item);
        return (
          '<button type="button" class="eurforex-area-picker__row" data-code="' +
          item.code.replace(/"/g, "&quot;") +
          '">' +
          '<span class="eurforex-area-picker__name">' +
          name +
          "</span>" +
          '<span class="eurforex-area-picker__code">' +
          item.code +
          "</span>" +
          "</button>"
        );
      })
      .join("");

    list.querySelectorAll(".eurforex-area-picker__row").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const code = btn.getAttribute("data-code");
        localStorage.setItem("selected_area_code", code);
        if (typeof window.__eurforexAreaPickerOnSelect === "function") {
          window.__eurforexAreaPickerOnSelect(code);
        }
        close();
      });
    });
  }

  function open() {
    ensureMounted();
    renderChrome();
    document.getElementById("eap-search").value = "";
    renderList("");
    rootEl.classList.add("is-open");
    rootEl.setAttribute("aria-hidden", "false");
    setTimeout(function () {
      document.getElementById("eap-search").focus();
    }, 200);
  }

  function close() {
    if (!rootEl) return;
    rootEl.classList.remove("is-open");
    rootEl.setAttribute("aria-hidden", "true");
  }

  window.EurforexAreaPicker = { open: open, close: close };
})(window);
