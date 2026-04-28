<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/wallet" @click="backGo($event, '#/pages/setting/wallet')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="min-w-0 flex-1 px-1 text-center">
        <div class="font-headline text-lg font-bold leading-tight text-on-surface">USDT 提現</div>
        <p class="mt-0.5 text-[11px] font-medium text-on-surface-variant">鏈上轉出 · 請核對網絡與地址</p>
      </div>
      <a class="flex shrink-0 min-w-[2.5rem] items-center justify-center rounded-full p-2 text-xs font-bold text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/withdraw" title="改為法幣提現">EUR</a>
    </header>

    <main class="mx-auto max-w-lg px-4 pt-20">

      <div class="mb-3 flex items-start gap-2 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-3.5 py-2.5 text-xs font-medium leading-relaxed text-amber-950/85">
        <span class="material-symbols-outlined mt-0.5 shrink-0 text-[18px] text-amber-700">shield_lock</span>
        <span><span class="font-bold text-amber-900/95">鏈上 USDT 提領：</span>輸入 USDT 提領金額與目標地址，或帶入已審核通過的綁定地址後提交。請僅向可信任地址提現；錯誤網絡或地址可能導致資產無法找回，新地址建議先小額測試。</span>
      </div>

      <section class="usdt-card p-[18px]">
        <div class="space-y-5 text-sm">

          <div>
            <div class="mb-2 font-semibold text-on-surface">提現網絡</div>
            <div class="flex flex-wrap gap-2.5" role="group" aria-label="鏈上網絡">
              <button type="button" class="usdt-pill" :class="{ 'usdt-pill--active': network === 'TRC20' }" data-network="TRC20" :aria-pressed="network === 'TRC20' ? 'true' : 'false'" @click="network = 'TRC20'">TRC20</button>
              <button type="button" class="usdt-pill" :class="{ 'usdt-pill--active': network === 'ERC20' }" data-network="ERC20" :aria-pressed="network === 'ERC20' ? 'true' : 'false'" @click="network = 'ERC20'">ERC20</button>
              <button type="button" class="usdt-pill" :class="{ 'usdt-pill--active': network === 'BEP20' }" data-network="BEP20" :aria-pressed="network === 'BEP20' ? 'true' : 'false'" @click="network = 'BEP20'">BEP20</button>
            </div>
            <p class="mt-2 text-xs text-on-surface-variant">手續費與到帳速度依所選網絡而定；請與收款方確認同一網絡。</p>
          </div>

          <div>
            <div class="mb-2 flex items-center justify-between gap-2">
              <span class="font-semibold text-on-surface">提現地址</span>
              <div class="flex items-center gap-1.5">
                <button type="button" id="usdt-paste-btn" class="inline-flex h-8 items-center gap-1 rounded-full border border-outline-variant/30 bg-white px-3 text-xs font-bold text-primary-dim transition-colors hover:bg-surface-container-low" @click="pasteAddress">
                  <span class="material-symbols-outlined text-[15px]">content_paste</span>
                  黏貼
                </button>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-full px-1 py-0.5 text-xs font-bold text-primary-dim transition-colors hover:bg-primary/8"
                  aria-haspopup="dialog"
                  :aria-expanded="addrBookOpen ? 'true' : 'false'"
                  @click="openAddressBook"
                >
                  <span class="material-symbols-outlined text-[16px]">bookmark</span>
                  地址簿
                </button>
              </div>
            </div>
            <div class="rounded-[18px] border-2 border-outline-variant/70 bg-gradient-to-b from-white to-surface-container-low/90 p-1 shadow-[inset_0_1px_0_rgba(255,255,255,0.85)]">
              <textarea class="min-h-[56px] w-full resize-none rounded-[14px] border-0 bg-transparent px-3 py-2.5 font-mono text-[13px] leading-relaxed text-on-surface placeholder:text-on-surface-variant/65 focus:outline-none focus:ring-0" id="usdt-address" rows="2" spellcheck="false" autocomplete="off" :placeholder="addressPlaceholder" v-model="address" ref="addrInput"></textarea>
            </div>
          </div>

          <div>
            <label class="mb-2 block font-semibold text-on-surface" for="usdt-wd-amount">提現數量</label>
            <div class="flex min-h-[56px] items-center gap-3 rounded-[18px] border-2 border-outline-variant/70 bg-gradient-to-b from-white to-surface-container-low/90 px-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.85)]">
              <input class="min-w-0 flex-1 border-0 bg-transparent text-base font-bold text-on-surface placeholder:text-on-surface-variant/80 focus:outline-none focus:ring-0" id="usdt-wd-amount" inputmode="decimal" autocomplete="off" placeholder="0" type="text" v-model="amount" />
              <span class="shrink-0 border-l-2 border-outline-variant/50 pl-3 text-[13px] font-extrabold uppercase tracking-wider text-on-surface">USDT</span>
              <button class="shrink-0 rounded-xl bg-primary-container/70 px-3 py-1.5 text-xs font-bold text-primary-dim hover:bg-primary-container" id="usdt-max" type="button" @click="fillMax">全部</button>
            </div>
            <p class="mt-2 text-xs text-on-surface-variant">單筆最低 <span class="font-semibold text-on-surface">10 USDT</span> · 可用 <span class="font-semibold tabular-nums text-on-surface">{{ formatAmount(maxBal, 2) }} USDT</span></p>
          </div>

          <div class="rounded-[18px] border-2 border-outline-variant/70 bg-surface-container-low/90 px-4 py-3">
            <h3 class="text-xs font-bold uppercase tracking-[0.12em] text-on-surface-variant">預估費用</h3>
            <dl class="mt-3 space-y-2 text-sm">
              <div class="flex justify-between gap-4">
                <dt class="text-on-surface-variant">網絡手續費（{{ network }}）</dt>
                <dd class="font-semibold tabular-nums">{{ fee.toFixed(2) }} USDT</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-on-surface-variant">預計到帳數量</dt>
                <dd class="font-headline font-bold tabular-nums text-primary-dim" id="usdt-receive">{{ recvDisplay }}</dd>
              </div>
            </dl>
          </div>

          <button type="button" class="usdt-confirm flex h-[52px] w-full items-center justify-center rounded-full font-headline text-base font-bold tracking-wide" data-href="#/pages/setting/withdrawDetail" :disabled="submitting" @click="handleConfirm">{{ submitting ? '提交中...' : '確認提現' }}</button>

          <p class="px-1 text-center text-xs leading-relaxed text-on-surface-variant">
            提現將從平台熱錢包發出，鏈上確認時間視網絡擁堵而定。大額可能觸發人工審核。
          </p>
        </div>
      </section>

      <section class="mt-4 grid grid-cols-3 gap-2.5 text-center text-xs font-semibold sm:gap-3 sm:text-sm">
        <a class="usdt-card py-3.5 hover:opacity-95" href="#/pages/setting/withdrawAddressBinding">地址管理</a>
        <a class="usdt-card py-3.5 hover:opacity-95" href="#/pages/setting/fundRecord">提現紀錄</a>
        <a class="usdt-card py-3.5 hover:opacity-95" href="#/pages/setting/mixrecharge">USDT 充值</a>
      </section>
    </main>

    <div class="pointer-events-none fixed bottom-0 left-0 z-40 h-24 w-full bg-gradient-to-t from-background to-transparent"></div>

    <!-- 地址簿（與法幣選銀行共用 Nc2cCenterSheet 外殼） -->
    <Nc2cCenterSheet :visible.sync="addrBookOpen" title="選擇提領地址" title-id="usdt-addr-book-title">
      <p class="mt-2 text-sm leading-relaxed text-slate-600">僅展示已保存的 USDT 鏈上地址；審核通過的地址可一鍵帶入。請確認與當前所選網絡一致。</p>
      <a
        href="#/pages/setting/withdrawAddressBinding"
        class="mt-3 flex w-full items-center justify-center rounded-2xl border-2 border-primary/30 bg-primary/5 py-3 text-sm font-bold text-primary-dim shadow-sm transition-colors hover:bg-primary/10"
        @click="closeAddressBook"
      >新增地址</a>
      <LoadingInlineSpinner v-if="addrBookLoading" aria-label="地址簿載入" />
      <div v-else-if="addrBookError" class="mt-4 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950/90">
        {{ addrBookError }}
        <button type="button" class="mt-2 text-xs font-bold text-primary-dim underline" @click="loadPayoutMethodsForBook">重試</button>
      </div>
      <div v-else id="usdt-addr-book-list" class="mt-4 max-h-[min(52vh,360px)] space-y-2 overflow-y-auto">
        <p v-if="!payoutUsdtItems.length" class="py-8 text-center text-sm text-on-surface-variant">尚無 USDT 提領地址，請先新增並完成審核。</p>
        <template v-else>
          <button
            v-for="it in payoutUsdtItems"
            :key="'pm-' + it.id"
            type="button"
            class="bank-pick w-full rounded-2xl p-4 text-left transition-colors"
            :class="addrBookRowClass(it)"
            :disabled="it.status !== 'approved'"
            @click="pickPayoutRow(it)"
          >
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-headline text-base font-bold text-slate-900">{{ payoutRowTitle(it) }}</span>
                <span v-if="it.is_default" class="rounded-md border border-primary/35 bg-white px-2 py-0.5 text-[10px] font-bold text-primary-dim shadow-sm">預設</span>
                <span
                  v-if="it.status !== 'approved'"
                  class="rounded-md border border-amber-300/80 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-900/90"
                >{{ payoutStatusLabel(it.status) }}</span>
              </div>
              <p class="mt-2 font-mono text-[13px] leading-snug tracking-wide text-slate-800 break-all">{{ it.payout_address }}</p>
            </div>
          </button>
        </template>
      </div>
      <div class="mt-5 flex gap-3">
        <button
          type="button"
          class="flex-1 rounded-full border-2 border-slate-300 bg-white py-3 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50"
          @click="closeAddressBook"
        >取消</button>
        <button type="button" class="bank-picker-confirm-btn flex-1 rounded-full py-3 text-sm font-bold text-white shadow-[0_10px_22px_rgba(47,115,205,0.35)]" @click="confirmAddressBook">確定</button>
      </div>
    </Nc2cCenterSheet>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'
import { filterUsdtPayoutItems, usdtPayoutRowTitle, payoutMethodStatusLabel } from '@/utils/payoutUsdt'

/**
 * EURNYSE - USDT 提現 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/withdraw-usdt.html 為唯一基準逐字遷移
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--withdraw-usdt",
  "font-body",
  "min-h-screen",
  "bg-background",
  "pb-32",
  "text-on-surface",
  "antialiased"
];

export default {
  name: "WithdrawUsdtH5",
  data: function () {
    return {
      network: "TRC20",
      address: "",
      amount: "",
      maxBal: 0,
      submitting: false,
      addrBookOpen: false,
      addrBookLoading: false,
      addrBookError: "",
      payoutItems: [],
      pendingPayoutId: null
    };
  },
  computed: {
    /** 各鏈參考手續費（介面用；實際以後台為準） */
    fee: function () {
      var m = { TRC20: 1, ERC20: 5, BEP20: 0.8 };
      return m[this.network] != null ? m[this.network] : 1;
    },
    addressPlaceholder: function () {
      if (this.network === "TRC20") return "貼上 TRC20 收款地址（以 T 開頭）";
      if (this.network === "ERC20") return "貼上 ERC20 收款地址（以 0x 開頭，Ethereum 主網）";
      return "貼上 BEP20 收款地址（以 0x 開頭，BSC）";
    },
    recvDisplay: function () {
      var raw = this.amount ? String(this.amount).replace(/,/g, "").trim() : "";
      if (!raw) return "—";
      var n = parseFloat(raw);
      if (isNaN(n)) n = 0;
      var net = n - this.fee;
      return (net > 0 ? net.toFixed(2) : "0.00") + " USDT";
    },
    /** 地址簿：僅 USDT 鏈上、有地址欄位 */
    payoutUsdtItems: function () {
      return filterUsdtPayoutItems(this.payoutItems);
    }
  },
  watch: {
    network: function () {
      this.address = "";
    },
    addrBookOpen: function (open) {
      if (!open) this.pendingPayoutId = null;
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "withdraw-usdt");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.loadOverviewBalance();
  },
  beforeDestroy: function () {
    try {
      document.body.style.overflow = "";
    } catch (e) {}
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
  },
  methods: {
    backGo: function (event, fallback) {
      if (window.EurnyseBack && typeof window.EurnyseBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurnyseBack.go(fallback);
      }
    },
    payoutRowTitle: usdtPayoutRowTitle,
    payoutStatusLabel: function (status) {
      return payoutMethodStatusLabel(status, "不可用");
    },
    formatAmount: function (value, digits) {
      return Number(value || 0).toLocaleString("en-GB", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    },
    loadOverviewBalance: function () {
      var self = this;
      userApi
        .overview()
        .then(function (data) {
          var map = (data && data.wallet_map) || {};
          var usdt = map.cash_usdt || {};
          self.maxBal = parseFloat(usdt.available_balance || "0") || 0;
        })
        .catch(function () {
          self.maxBal = 0;
        });
    },
    addrBookRowClass: function (it) {
      if (it.status !== "approved") {
        return "border border-slate-200/90 bg-slate-50/90 cursor-not-allowed opacity-80";
      }
      var sel = this.pendingPayoutId != null && Number(this.pendingPayoutId) === Number(it.id);
      return sel
        ? "bank-pick--selected border-2 border-primary bg-sky-50 ring-2 ring-primary/25 shadow-sm"
        : "border border-slate-300/90 bg-white hover:bg-slate-50";
    },
    pickPayoutRow: function (it) {
      if (!it || it.status !== "approved") return;
      this.pendingPayoutId = it.id;
    },
    preselectPayoutInBook: function () {
      var net = String(this.network || "").toUpperCase();
      var firstApproved = null;
      var matchNet = null;
      var rows = this.payoutUsdtItems;
      for (var i = 0; i < rows.length; i++) {
        var it = rows[i];
        if (it.status !== "approved") continue;
        if (firstApproved == null) firstApproved = it.id;
        if (String(it.usdt_network || "").toUpperCase() === net) {
          matchNet = it.id;
          break;
        }
      }
      this.pendingPayoutId = matchNet != null ? matchNet : firstApproved;
    },
    loadPayoutMethodsForBook: function () {
      var self = this;
      this.addrBookError = "";
      this.addrBookLoading = true;
      return userApi
        .payoutMethods()
        .then(function (data) {
          self.payoutItems = (data && data.items) || [];
          self.preselectPayoutInBook();
        })
        .catch(function (e) {
          self.addrBookError = (e && e.message) || "載入失敗";
          self.payoutItems = [];
        })
        .finally(function () {
          self.addrBookLoading = false;
        });
    },
    openAddressBook: function () {
      this.addrBookOpen = true;
      this.loadPayoutMethodsForBook();
    },
    closeAddressBook: function () {
      this.addrBookOpen = false;
    },
    confirmAddressBook: function () {
      if (this.addrBookLoading) return;
      var id = this.pendingPayoutId;
      if (id == null) {
        alert("請選擇一筆地址。");
        return;
      }
      var it = null;
      var rows = this.payoutUsdtItems;
      for (var i = 0; i < rows.length; i++) {
        if (Number(rows[i].id) === Number(id)) {
          it = rows[i];
          break;
        }
      }
      if (!it || it.status !== "approved") {
        alert("請選擇已審核通過的地址。");
        return;
      }
      this.address = String(it.payout_address || "").trim();
      var u = String(it.usdt_network || "").toUpperCase();
      if (u === "TRC20" || u === "ERC20" || u === "BEP20") {
        this.network = u;
      }
      this.closeAddressBook();
    },
    fillMax: function () {
      this.amount = this.maxBal.toFixed(2);
    },
    pasteAddress: function () {
      var self = this;
      try {
        if (!navigator.clipboard || !navigator.clipboard.readText) throw new Error("no-clipboard");
        navigator.clipboard.readText().then(function (txt) {
          if (!txt) {
            alert("剪貼簿暫無內容。");
            return;
          }
          self.address = String(txt).trim();
        }).catch(function () {
          if (self.$refs.addrInput && self.$refs.addrInput.focus) self.$refs.addrInput.focus();
          alert("請長按輸入框後貼上地址。");
        });
      } catch (e) {
        if (this.$refs.addrInput && this.$refs.addrInput.focus) this.$refs.addrInput.focus();
        alert("請長按輸入框後貼上地址。");
      }
    },
    handleConfirm: function (e) {
      if (!this.address || !String(this.address).trim()) {
        alert("請填寫提現地址。");
        return;
      }
      var n = parseFloat(String(this.amount || "").replace(/,/g, "").trim());
      if (isNaN(n) || n < 10) {
        alert("提現數量需至少 10 USDT。");
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
          channel_type: "usdt",
          asset_code: "USDT",
          source_wallet_code: "cash_usdt",
          payout_address: String(this.address || "").trim(),
          remark: "network:" + this.network
        })
        .then(function (data) {
          window.location.href = "#/pages/setting/withdrawDetail?id=" + (data.withdrawal_request_id || data.request_id || "");
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
