<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/wallet" @click="backGo($event, '#/pages/setting/wallet')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="min-w-0 flex-1 px-1 text-center">
        <div class="font-headline text-lg font-bold leading-tight text-on-surface">法幣提現</div>
      </div>
      <a class="flex shrink-0 min-w-[2.5rem] items-center justify-center rounded-full p-2 text-sm font-bold text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/withdrawUsdt" title="改為 USDT 鏈上提現">USDT</a>
    </header>

    <main class="mx-auto max-w-lg px-4 pb-10 pb-safe pt-20">

      <p class="mb-3 flex items-start gap-2 rounded-2xl border border-primary/15 bg-primary-container/40 px-3.5 py-2.5 text-xs font-medium text-on-surface leading-relaxed">
        <span class="material-symbols-outlined mt-0.5 text-[18px] text-primary-dim shrink-0">account_balance</span>
        <span><span class="font-bold text-primary-dim">EUR 銀行提領：</span>資金將匯入您已驗證的歐元帳戶，到帳時間受銀行與假日影響；提交後可在提領紀錄查看審核與放款狀態。</span>
      </p>

      <section class="fiat-card p-[18px]">
        <div class="flex items-start justify-between gap-3 border-b border-outline-variant/20 pb-4">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">到帳方式</p>
            <p class="mt-1 font-headline text-base font-bold text-on-surface">SEPA 本地轉帳</p>
            <p class="mt-1 text-xs text-on-surface-variant">通常 1～3 個銀行工作日內到帳</p>
          </div>
          <span class="shrink-0 rounded-full bg-surface-container-high px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">EUR</span>
        </div>

        <div class="mt-4">
          <div class="mb-2">
            <span class="text-sm font-semibold text-on-surface">收款銀行</span>
          </div>

          <div id="fiat-bank-empty" class="eurforex-empty-card eurforex-empty-card--compact" v-show="!selectedBank">
            <div class="eurforex-empty-illustration" aria-hidden="true">
              <span class="eurforex-empty-illustration__cube eurforex-empty-illustration__cube--one"></span>
              <span class="eurforex-empty-illustration__cube eurforex-empty-illustration__cube--two"></span>
              <span class="eurforex-empty-illustration__cube eurforex-empty-illustration__cube--three"></span>
              <span class="eurforex-empty-illustration__box"></span>
            </div>
            <div class="eurforex-empty-actions">
              <button type="button" id="open-bank-picker-empty" class="eurforex-empty-btn" @click="showModal">選擇收款銀行</button>
            </div>
          </div>

          <div id="fiat-bank-selected" :class="selectedBank ? '' : 'hidden'" v-show="selectedBank">
            <div class="selected-bank-card">
              <div class="flex flex-wrap items-center gap-2">
                <span class="bank-card-title">已選擇收款銀行</span>
                <span id="fiat-bank-default-badge" class="rounded-md bg-primary-container/80 px-2 py-0.5 text-[10px] font-bold text-primary-dim" :class="{ hidden: !selectedBank || !selectedBank.default }">預設</span>
              </div>
              <div class="mt-3 space-y-2">
                <div class="bank-info-row">
                  <span>戶名</span>
                  <strong id="fiat-bank-holder">{{ selectedBank ? selectedBank.holder : '—' }}</strong>
                </div>
                <div class="bank-info-row">
                  <span>銀行名</span>
                  <strong id="fiat-bank-name">{{ selectedBank ? selectedBank.name : '—' }}</strong>
                </div>
                <div class="bank-info-row">
                  <span>銀行帳號</span>
                  <strong id="fiat-bank-iban" class="font-mono tracking-wide">{{ selectedBank ? selectedBank.iban : '—' }}</strong>
                </div>
              </div>
            </div>
            <button type="button" id="open-bank-picker-change" class="mt-3 w-full rounded-xl border border-outline-variant/30 bg-white py-2.5 text-sm font-bold text-primary-dim transition-colors hover:bg-surface-container-low" @click="showModal">選擇其他銀行</button>
          </div>
        </div>

        <div class="mt-6">
          <label class="mb-2 block text-sm font-semibold text-on-surface" for="fiat-amount">提現金額</label>
          <div class="flex min-h-[56px] items-center gap-2 rounded-[18px] border border-outline-variant/35 bg-gradient-to-b from-white to-surface-container-low/90 px-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.85)] sm:px-4">
            <input class="min-w-0 flex-1 border-0 bg-transparent text-2xl font-headline font-bold tracking-tight text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-0" id="fiat-amount" inputmode="decimal" autocomplete="off" placeholder="0.00" type="text" v-model="amount" />
            <span class="shrink-0 border-l border-outline-variant/25 pl-3 text-sm font-extrabold text-on-surface">EUR</span>
            <button class="shrink-0 rounded-xl bg-primary-container/70 px-3 py-1.5 text-xs font-bold text-primary-dim hover:bg-primary-container" id="fiat-max" type="button" @click="fillMax">全部</button>
          </div>
          <p class="fiat-soft-note mt-2 text-xs text-on-surface-variant">單筆最低 <span class="font-semibold text-on-surface">EUR 20.00</span> · 需預留手續費</p>
        </div>
      </section>

      <section class="fiat-card mt-4 p-[18px]">
        <h2 class="font-headline text-sm font-bold text-on-surface">費用與預覽</h2>
        <dl class="mt-4 space-y-3 text-sm">
          <div class="flex items-center justify-between gap-4">
            <dt class="text-on-surface-variant">可用餘額</dt>
            <dd class="font-semibold tabular-nums">{{ formatEur(maxBal) }}</dd>
          </div>
          <div class="flex items-center justify-between gap-4">
            <dt class="text-on-surface-variant">平台手續費</dt>
            <dd class="font-semibold tabular-nums text-on-surface">EUR 5.00</dd>
          </div>
          <div class="h-px bg-outline-variant/25"></div>
          <div class="flex items-center justify-between gap-4">
            <dt class="font-semibold text-on-surface">預計到帳（扣除費用後）</dt>
            <dd class="font-headline text-lg font-bold tabular-nums text-primary-dim" id="fiat-net">{{ netDisplay }}</dd>
          </div>
        </dl>
        <div class="mt-4 rounded-2xl bg-surface-container-low px-4 py-3 text-xs leading-relaxed text-on-surface-variant">
          <span class="font-semibold text-on-surface">今日剩餘額度</span> EUR 1,520 / 2,000 · 完成
          <a class="font-bold text-primary-dim" href="#/pages/setting/kycVerification">身份認證</a>
          可申請提高。
        </div>
        <button
          type="button"
          class="usdt-confirm mt-5 flex h-[52px] w-full items-center justify-center rounded-full font-headline text-base font-bold tracking-wide"
          :disabled="submitting"
          @click="goWithdrawDetailFiat"
        >{{ submitting ? '提交中...' : '確認提現' }}</button>
      </section>
    </main>

    <Nc2cCenterSheet :visible.sync="modalOpen" title="選擇收款銀行" title-id="bank-picker-title">
      <div class="bank-picker-shell">
        <p class="bank-picker-desc">請選擇本次提現要使用的銀行帳戶，確認後提現頁只會帶入該筆收款資料。</p>
      <a
        href="#/pages/setting/bindinfo"
        class="bank-picker-add-btn"
        @click="hideModal"
      >
        <span class="material-symbols-outlined text-[18px]">add_card</span>
        <span>新增銀行</span>
      </a>
      <div id="bank-picker-list" class="bank-picker-list">
        <p v-if="!banks.length" class="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-4 py-8 text-center text-sm text-on-surface-variant">尚未綁定銀行帳戶，請先新增銀行。</p>
        <button
          v-for="b in banks"
          :key="b.id"
          type="button"
          class="bank-pick"
          :class="{ 'bank-pick--selected': pendingId === b.id }"
          :data-id="b.id"
          @click="pendingId = b.id"
        >
          <div class="bank-pick-head">
            <div>
              <span class="bank-pick-title">銀行收款帳戶</span>
              <span v-if="b.default" class="bank-pick-badge">預設</span>
            </div>
            <span class="bank-pick-radio" aria-hidden="true"></span>
          </div>
          <div class="bank-pick-fields">
            <div class="bank-pick-field">
              <span>戶名</span>
              <strong>{{ b.holder }}</strong>
            </div>
            <div class="bank-pick-field">
              <span>銀行名</span>
              <strong>{{ b.name }}</strong>
            </div>
            <div class="bank-pick-field">
              <span>銀行帳號</span>
              <strong class="font-mono tracking-wide">{{ b.iban }}</strong>
            </div>
          </div>
        </button>
      </div>
      <div class="mt-5 flex gap-3">
        <button type="button" id="bank-picker-cancel" class="flex-1 rounded-full border-2 border-slate-300 bg-white py-3 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50" @click="hideModal">取消</button>
        <button type="button" id="bank-picker-confirm" class="bank-picker-confirm-btn flex-1 rounded-full py-3 text-sm font-bold text-white shadow-[0_10px_22px_rgba(47,115,205,0.35)]" @click="confirmPicker">確定</button>
      </div>
      </div>
    </Nc2cCenterSheet>

  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURFOREX - 法幣提現 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/withdraw.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 * 原稿內聯 IIFE 遷移：
 *   STORAGE_KEY = "eurforex-fiat-withdraw-bank-id" → sessionStorage
 *   BANKS 資料、選擇 modal、金額輸入 → Vue data/methods/computed
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--withdraw",
  "font-body",
  "min-h-screen",
  "bg-background",
  "pb-8",
  "text-on-surface",
  "antialiased"
];

var STORAGE_KEY = "eurforex-fiat-withdraw-bank-id";
export default {
  name: "WithdrawH5",
  data: function () {
    return {
      banks: [],
      selectedId: "",
      pendingId: null,
      modalOpen: false,
      amount: "",
      fee: 5,
      maxBal: 0,
      submitting: false
    };
  },
  computed: {
    selectedBank: function () {
      if (!this.selectedId) return null;
      for (var i = 0; i < this.banks.length; i++) {
        if (this.banks[i].id === this.selectedId) return this.banks[i];
      }
      return null;
    },
    netDisplay: function () {
      var raw = this.amount ? String(this.amount).replace(/,/g, "").trim() : "";
      if (!raw) return "—";
      var n = parseFloat(raw);
      if (isNaN(n)) n = 0;
      var net = n - this.fee;
      if (net <= 0) return this.formatEur(0);
      return this.formatEur(net);
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "withdraw");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    try {
      this.selectedId = sessionStorage.getItem(STORAGE_KEY) || "";
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.loadPageData();
  },
  beforeDestroy: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
      document.body.style.overflow = "";
    } catch (e) {}
  },
  methods: {
    backGo: function (event, fallback) {
      if (window.EurforexBack && typeof window.EurforexBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurforexBack.go(fallback);
      }
    },
    formatEur: function (n) {
      return "EUR " + n.toLocaleString("en-GB", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    loadPageData: function () {
      var self = this;
      userApi
        .overview()
        .then(function (data) {
          var map = (data && data.wallet_map) || {};
          var eur = map.eur || {};
          self.maxBal = parseFloat(eur.available_balance || "0") || 0;
        })
        .catch(function () {
          self.maxBal = 0;
        });
      userApi
        .payoutMethods()
        .then(function (data) {
          var items = (data && data.items) || [];
          self.banks = items
            .filter(function (it) {
              return String(it.channel_type || "") === "bank" && String(it.status || "") === "approved";
            })
            .map(function (it) {
              return {
                id: String(it.id),
                name: it.bank_name || "銀行帳戶",
                default: !!it.is_default,
                holder: it.account_holder || "—",
                iban: it.account_no_masked || "—"
              };
            });
          if (!self.selectedId && self.banks.length) {
            var picked = self.banks.find(function (b) { return b.default; }) || self.banks[0];
            self.selectedId = picked.id;
          }
        })
        .catch(function () {
          self.banks = [];
        });
    },
    showModal: function () {
      var cur = "";
      try { cur = sessionStorage.getItem(STORAGE_KEY) || ""; } catch (e) {}
      this.pendingId = cur || (this.banks[0] && this.banks[0].id) || null;
      this.modalOpen = true;
    },
    hideModal: function () {
      this.modalOpen = false;
    },
    confirmPicker: function () {
      if (this.pendingId) {
        try { sessionStorage.setItem(STORAGE_KEY, this.pendingId); } catch (e) {}
        this.selectedId = this.pendingId;
      }
      this.hideModal();
    },
    fillMax: function () {
      this.amount = this.maxBal.toFixed(2);
    },
    goWithdrawDetailFiat: function () {
      var n = parseFloat(String(this.amount || "").replace(/,/g, "").trim());
      if (!this.selectedBank) {
        alert("請選擇已審核的收款銀行。");
        return;
      }
      if (isNaN(n) || n < 20) {
        alert("提現金額需至少 EUR 20.00。");
        return;
      }
      if (n > this.maxBal) {
        alert("可用餘額不足。");
        return;
      }
      if (this.submitting) return;
      var self = this;
      this.submitting = true;
      userApi
        .createWithdrawalRequest({
          amount: n.toFixed(8),
          channel_type: "bank",
          asset_code: "EUR",
          source_wallet_code: "eur",
          payout_method_id: this.selectedBank.id
        })
        .then(function (data) {
          window.location.href = "#/pages/setting/withdrawDetailFiat?id=" + (data.withdrawal_request_id || data.request_id || "");
        })
        .catch(function (error) {
          alert((error && error.message) || "提現申請提交失敗");
        })
        .finally(function () {
          self.submitting = false;
        });
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>

<style scoped>
/* 銀行選擇彈窗：與全域 preview 按鈕覆寫脫鉤，確保主按鈕與選中列對比足夠 */
.selected-bank-card {
  border: 1.5px solid rgba(47, 115, 205, 0.34);
  border-radius: 20px;
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(240, 248, 255, 0.96)),
    radial-gradient(circle at top right, rgba(59, 154, 232, 0.15), transparent 40%);
  box-shadow: 0 14px 30px rgba(29, 80, 135, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.95);
  padding: 16px;
}
.bank-card-title {
  color: #0f2742;
  font-family: inherit;
  font-size: 15px;
  font-weight: 800;
}
.bank-info-row {
  align-items: flex-start;
  background: rgba(255, 255, 255, 0.74);
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 14px;
  display: flex;
  gap: 12px;
  justify-content: space-between;
  padding: 10px 12px;
}
.bank-info-row span {
  color: #64748b;
  flex: 0 0 auto;
  font-size: 12px;
  font-weight: 700;
}
.bank-info-row strong {
  color: #0f172a;
  flex: 1 1 auto;
  font-size: 13px;
  font-weight: 800;
  text-align: right;
  word-break: break-word;
}
.bank-picker-shell {
  margin-top: 8px;
}
.bank-picker-desc {
  background: linear-gradient(135deg, rgba(239, 248, 255, 0.95), rgba(255, 255, 255, 0.96));
  border: 1px solid rgba(47, 115, 205, 0.14);
  border-radius: 16px;
  color: #475569;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.65;
  padding: 12px 14px;
}
.bank-picker-add-btn {
  align-items: center;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(238, 247, 255, 0.96));
  border: 1.5px solid rgba(47, 115, 205, 0.24);
  border-radius: 18px;
  box-shadow: 0 10px 24px rgba(29, 80, 135, 0.08);
  color: #1e5cad;
  display: flex;
  font-size: 14px;
  font-weight: 800;
  gap: 8px;
  justify-content: center;
  margin-top: 12px;
  padding: 12px 14px;
  transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
  width: 100%;
}
.bank-picker-add-btn:hover {
  background: #f8fbff;
  box-shadow: 0 12px 28px rgba(29, 80, 135, 0.12);
  transform: translateY(-1px);
}
.bank-picker-list {
  margin-top: 14px;
  max-height: min(52vh, 380px);
  overflow-y: auto;
  padding: 2px 2px 4px;
}
.bank-pick {
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(248, 251, 255, 0.98)),
    radial-gradient(circle at top right, rgba(59, 154, 232, 0.1), transparent 38%);
  border: 1.5px solid rgba(148, 163, 184, 0.34);
  border-radius: 20px;
  box-shadow: 0 12px 28px rgba(15, 39, 66, 0.08);
  display: block;
  margin-bottom: 10px;
  padding: 14px;
  text-align: left;
  transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
  width: 100%;
}
.bank-pick:hover {
  border-color: rgba(47, 115, 205, 0.38);
  box-shadow: 0 16px 32px rgba(29, 80, 135, 0.12);
  transform: translateY(-1px);
}
.bank-pick--selected {
  background:
    linear-gradient(135deg, rgba(239, 248, 255, 0.98), rgba(255, 255, 255, 0.98)),
    radial-gradient(circle at top right, rgba(59, 154, 232, 0.18), transparent 44%);
  border-color: #2f73cd;
  box-shadow: 0 16px 36px rgba(47, 115, 205, 0.18), 0 0 0 3px rgba(47, 115, 205, 0.12);
}
.bank-pick-head {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}
.bank-pick-title {
  color: #0f2742;
  font-size: 14px;
  font-weight: 900;
}
.bank-pick-badge {
  background: #ffffff;
  border: 1px solid rgba(47, 115, 205, 0.28);
  border-radius: 999px;
  color: #1e5cad;
  display: inline-flex;
  font-size: 10px;
  font-weight: 900;
  margin-left: 8px;
  padding: 2px 8px;
}
.bank-pick-radio {
  border: 2px solid rgba(100, 116, 139, 0.36);
  border-radius: 999px;
  flex: 0 0 18px;
  height: 18px;
  position: relative;
  width: 18px;
}
.bank-pick--selected .bank-pick-radio {
  border-color: #2f73cd;
}
.bank-pick--selected .bank-pick-radio::after {
  background: #2f73cd;
  border-radius: 999px;
  content: "";
  height: 8px;
  left: 3px;
  position: absolute;
  top: 3px;
  width: 8px;
}
.bank-pick-fields {
  display: grid;
  gap: 8px;
  margin-top: 12px;
}
.bank-pick-field {
  align-items: flex-start;
  background: rgba(255, 255, 255, 0.76);
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 14px;
  display: flex;
  gap: 12px;
  justify-content: space-between;
  padding: 9px 11px;
}
.bank-pick-field span {
  color: #64748b;
  flex: 0 0 auto;
  font-size: 12px;
  font-weight: 800;
}
.bank-pick-field strong {
  color: #0f172a;
  flex: 1 1 auto;
  font-size: 13px;
  font-weight: 900;
  text-align: right;
  word-break: break-word;
}
.bank-picker-confirm-btn {
  background: linear-gradient(135deg, #3b9ae8, #1e5cad) !important;
  color: #ffffff !important;
  border: 1px solid rgba(255, 255, 255, 0.55) !important;
  text-shadow: 0 1px 2px rgba(0, 36, 76, 0.25);
}
.bank-picker-confirm-btn:hover {
  filter: brightness(1.05);
}
</style>
