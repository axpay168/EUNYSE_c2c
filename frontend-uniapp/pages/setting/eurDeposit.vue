<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/wallet" @click="backGo($event, '#/pages/setting/wallet')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">EUR 充值</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>

    <main class="mx-auto max-w-lg px-4 pt-20">
      <section class="eur-card p-[18px]">
        <div class="space-y-5 text-sm">

          <div>
            <div class="mb-2 font-semibold text-on-surface">網絡</div>
            <div class="flex flex-wrap gap-2.5" role="group" aria-label="入帳網絡">
              <button type="button" class="eur-pill" :class="{ 'eur-pill--active': network === 'SEPA' }" data-network="SEPA" :aria-pressed="network === 'SEPA' ? 'true' : 'false'" @click="network = 'SEPA'">SEPA</button>
              <button type="button" class="eur-pill eur-pill--disabled" disabled title="暫未開通">SWIFT</button>
              <button type="button" class="eur-pill eur-pill--disabled" disabled title="暫未開通">其他</button>
            </div>
          </div>

          <div>
            <div class="mb-2 font-semibold text-on-surface">收款地址</div>
            <div class="eurforex-empty-card eurforex-empty-card--compact">
              <div class="eurforex-empty-illustration" aria-hidden="true">
                <span class="eurforex-empty-illustration__cube eurforex-empty-illustration__cube--one"></span>
                <span class="eurforex-empty-illustration__cube eurforex-empty-illustration__cube--two"></span>
                <span class="eurforex-empty-illustration__cube eurforex-empty-illustration__cube--three"></span>
                <span class="eurforex-empty-illustration__box"></span>
              </div>
              <h2 class="eurforex-empty-title">暫未設置入金資訊</h2>
              <p class="eurforex-empty-sub">SEPA 入金資訊需由客服確認後提供。</p>
              <div class="eurforex-empty-actions">
                <a class="eurforex-empty-btn" href="#/pages/index/serviceCenter">聯繫客服</a>
                <a class="eurforex-empty-btn-soft" href="#/pages/setting/wallet">返回資產</a>
              </div>
            </div>
          </div>

          <div>
            <label class="mb-2 block font-semibold text-on-surface" for="eur-amount">轉賬金額</label>
            <div class="flex min-h-[56px] items-center gap-3 rounded-[18px] border border-outline-variant/35 bg-gradient-to-b from-white to-surface-container-low/90 px-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.85)]">
              <input class="min-w-0 flex-1 border-0 bg-transparent text-base font-bold text-on-surface placeholder:text-on-surface-variant/80 focus:outline-none focus:ring-0" id="eur-amount" inputmode="decimal" autocomplete="off" placeholder="0" type="text" v-model="amount" />
              <span class="shrink-0 border-l border-outline-variant/25 pl-3 text-[13px] font-extrabold uppercase tracking-wider text-on-surface">EUR</span>
            </div>
          </div>

          <div>
            <div class="mb-2 font-semibold text-on-surface">轉賬憑證</div>
            <label class="flex min-h-[138px] cursor-pointer flex-col items-center justify-center gap-3 rounded-[22px] border border-dashed border-primary/35 bg-gradient-to-b from-primary/6 to-primary/3 px-5 py-5 text-center transition hover:border-primary/50" for="eur-proof">
              <input class="sr-only" id="eur-proof" type="file" accept="image/*" @change="handleProofChange" />
              <span class="flex h-[54px] w-[54px] items-center justify-center rounded-full bg-primary/12 text-[32px] font-light leading-none text-on-surface">+</span>
              <span>
                <span class="block text-sm font-extrabold text-on-surface">上傳轉賬憑證</span>
                <span class="mt-1 block text-xs text-on-surface-variant" data-proof-hint>{{ proofHint }}</span>
              </span>
            </label>
          </div>

          <button type="button" class="eur-confirm mt-1 flex h-[52px] w-full items-center justify-center rounded-full font-headline text-base font-bold tracking-wide" :disabled="submitting" @click="handleConfirm">{{ submitting ? ('◌ ' + submitStageText) : '確認' }}</button>
          <p v-if="submitting" class="px-1 text-center text-xs text-on-surface-variant">{{ submitStageText }}</p>

          <p class="px-1 text-center text-xs leading-relaxed text-on-surface-variant">選擇網絡、複製充值地址、輸入轉賬金額、上傳憑證，最後點擊確認即可提交。</p>
        </div>
      </section>

      <nav class="mixrecharge-quicknav mx-auto mt-5 max-w-lg px-1 pb-8" aria-label="入金相關捷徑">
        <ul class="grid grid-cols-3 gap-2.5 sm:gap-3">
          <li class="min-w-0">
            <a class="mixrecharge-quicknav__link" href="#/pages/setting/wallet">
              <span class="mixrecharge-quicknav__icon" aria-hidden="true"><span class="material-symbols-outlined">account_balance_wallet</span></span>
              <span class="mixrecharge-quicknav__text">返回資產</span>
            </a>
          </li>
          <li class="min-w-0">
            <a class="mixrecharge-quicknav__link" href="#/pages/setting/fundRecord">
              <span class="mixrecharge-quicknav__icon" aria-hidden="true"><span class="material-symbols-outlined">receipt_long</span></span>
              <span class="mixrecharge-quicknav__text">充值紀錄</span>
            </a>
          </li>
          <li class="min-w-0">
            <a class="mixrecharge-quicknav__link" href="#/pages/index/tutorial">
              <span class="mixrecharge-quicknav__icon" aria-hidden="true"><span class="material-symbols-outlined">school</span></span>
              <span class="mixrecharge-quicknav__text">充值教學</span>
            </a>
          </li>
        </ul>
      </nav>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURFOREX - EUR 充值 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/eur-deposit.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js、../shared/eur-deposit-modal.js（動態注入）
 * 原稿內聯 IIFE：
 *   - proof input change → handleProofChange
 *   - .eur-confirm click → handleConfirm（呼叫 window.EurDepositModal.open()，否則跳轉 ./service-center.html）
 *   - eur-pill active toggle → Vue :class + @click
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--eur-deposit",
  "font-body",
  "min-h-screen",
  "bg-background",
  "pb-28",
  "text-on-surface",
  "antialiased"
];

export default {
  name: "EurDepositH5",
  data: function () {
    return {
      network: "SEPA",
      amount: "",
      proofHint: "上傳憑證",
      proofFile: null,
      submitting: false,
      submitStage: ""
    };
  },
  computed: {
    submitStageText: function () {
      if (this.submitStage === "uploading") return "上傳憑證中...";
      if (this.submitStage === "submitting") return "送出充值申請中...";
      return "提交中...";
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "eur-deposit");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.loadSharedScript("/static/previews/shared/eur-deposit-modal.js", "eurforexEurDepositModal");
  },
  beforeDestroy: function () {
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
      if (window.EurforexBack && typeof window.EurforexBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurforexBack.go(fallback);
      }
    },
    handleProofChange: function (e) {
      var input = e && e.target;
      var f = input && input.files && input.files[0];
      this.proofFile = f || null;
      this.proofHint = f ? f.name : "上傳憑證";
    },
    handleConfirm: function () {
      var n = parseFloat(String(this.amount || "").replace(/,/g, "").trim());
      if (isNaN(n) || n <= 0) {
        alert("請輸入有效的充值金額。");
        return;
      }
      if (!this.proofFile) {
        alert("請先上傳轉賬憑證。");
        return;
      }
      if (this.submitting) return;
      var self = this;
      this.submitting = true;
      this.submitStage = "uploading";
      userApi
        .uploadFile(this.proofFile, "deposit-proof")
        .then(function (upload) {
          self.submitStage = "submitting";
          return userApi.createDepositRequest({
            amount: n.toFixed(8),
            asset_code: "EUR",
            network: self.network,
            proof_url: upload.url || upload.public_url || "",
            reference: "network:" + self.network
          });
        })
        .then(function (data) {
          window.location.href = "#/pages/setting/rechargeDetail?request_id=" + (data.deposit_request_id || data.request_id || "");
        })
        .catch(function (error) {
          alert((error && error.message) || "充值申請提交失敗");
        })
        .finally(function () {
          self.submitting = false;
          self.submitStage = "";
        });
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");

.mixrecharge-quicknav__link {
  display: flex;
  min-height: 5.25rem;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 0.65rem 0.35rem;
  border-radius: 1rem;
  border: 1px solid rgba(46, 100, 134, 0.14);
  background: linear-gradient(165deg, rgba(255, 255, 255, 0.98) 0%, rgba(236, 248, 255, 0.55) 48%, rgba(255, 255, 255, 0.92) 100%);
  box-shadow:
    0 1px 0 rgba(255, 255, 255, 0.9) inset,
    0 8px 22px rgba(33, 79, 131, 0.07);
  text-decoration: none;
  color: inherit;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    transform 0.15s ease;
}

.mixrecharge-quicknav__link:hover {
  border-color: rgba(46, 100, 134, 0.28);
  box-shadow:
    0 1px 0 rgba(255, 255, 255, 0.95) inset,
    0 12px 28px rgba(33, 79, 131, 0.12);
  transform: translateY(-1px);
}

.mixrecharge-quicknav__link:active {
  transform: translateY(0);
}

.mixrecharge-quicknav__icon {
  display: flex;
  width: 2.35rem;
  height: 2.35rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  background: linear-gradient(145deg, rgba(86, 175, 232, 0.2) 0%, rgba(46, 100, 134, 0.12) 100%);
  color: rgb(37, 99, 150);
}

.mixrecharge-quicknav__icon .material-symbols-outlined {
  font-size: 1.25rem;
  font-variation-settings: "FILL" 0, "wght" 500, "GRAD" 0, "opsz" 24;
}

.mixrecharge-quicknav__text {
  font-family: "Space Grotesk", "Manrope", "PingFang TC", "Microsoft JhengHei", sans-serif;
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: 0.04em;
  text-align: center;
  text-transform: none;
  color: rgb(30, 41, 59);
  max-width: 100%;
  word-break: keep-all;
}
</style>
