<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/index/financial" @click="backGo($event, '#/pages/index/financial')" aria-label="返回"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">我的持倉</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>

    <main class="holdings-page mx-auto max-w-4xl px-4 pb-10 pt-20">
      <section class="holdings-hero">
        <div class="holdings-hero__copy">
          <p class="holdings-eyebrow">Earn Portfolio</p>
          <h1>我的持倉</h1>
          <p>追蹤申購、計息、到期與贖回狀態。成功申購後，產品會依結算節點自動出現在此頁。</p>
        </div>
        <a class="holdings-hero__action" href="#/pages/index/financial">查看理財產品</a>
      </section>

      <section class="holdings-summary" aria-label="持倉總覽">
        <article class="holdings-summary__card">
          <span>持倉本金</span>
          <strong>{{ principalDisplay }}</strong>
          <em>{{ activeCount ? '進行中持倉本金' : '目前沒有進行中的產品' }}</em>
        </article>
        <article class="holdings-summary__card">
          <span>累計參考收益</span>
          <strong>{{ interestDisplay }}</strong>
          <em>收益將於到期或結算後更新</em>
        </article>
        <article class="holdings-summary__card">
          <span>下一結算日</span>
          <strong>{{ nextSettlementDisplay }}</strong>
          <em>申購後顯示預計時間</em>
        </article>
      </section>

      <section class="holdings-panel">
        <div class="holdings-panel__head">
          <div>
            <p class="holdings-eyebrow">Positions</p>
            <h2>持倉列表</h2>
          </div>
          <div class="holdings-tabs" aria-label="持倉狀態">
            <button type="button" :class="{ 'is-active': filter === 'all' }" @click="filter = 'all'">全部</button>
            <button type="button" :class="{ 'is-active': filter === 'active' }" @click="filter = 'active'">計息中</button>
            <button type="button" :class="{ 'is-active': filter === 'settled' }" @click="filter = 'settled'">已到期</button>
          </div>
        </div>

        <LoadingInlineSpinner v-if="loading" aria-label="持倉載入" />
        <p v-else-if="errorMessage" class="holdings-loading holdings-loading--error">{{ errorMessage }}</p>
        <div v-else-if="!visibleItems.length" class="holdings-empty-card">
          <div class="holdings-empty-card__glow" aria-hidden="true"></div>
          <div class="holdings-empty-visual" aria-hidden="true">
            <span class="holdings-empty-visual__cube holdings-empty-visual__cube--one"></span>
            <span class="holdings-empty-visual__cube holdings-empty-visual__cube--two"></span>
            <span class="holdings-empty-visual__cube holdings-empty-visual__cube--three"></span>
            <span class="holdings-empty-visual__box"></span>
          </div>
          <div class="holdings-empty-card__content">
            <span class="holdings-empty-card__badge">No Active Position</span>
            <h3>目前尚無持倉</h3>
            <p>完成申購後，這裡會顯示產品名稱、申購本金、參考 APR、起息日、到期日與預估收益。</p>
            <div class="holdings-empty-actions">
              <a class="holdings-btn-primary" href="#/pages/index/financial">前往理財中心</a>
              <a class="holdings-btn-soft" href="#/pages/index/index">返回首頁</a>
            </div>
          </div>
        </div>
        <div v-else class="holdings-list">
          <article v-for="item in visibleItems" :key="item.id" class="holdings-position-card">
            <div class="holdings-position-card__head">
              <div>
                <p class="holdings-eyebrow">{{ item.product_code }}</p>
                <h3>{{ item.product_code }} · {{ item.asset_code }}</h3>
              </div>
              <span>{{ statusLabel(item.status) }}</span>
            </div>
            <div class="holdings-position-card__grid">
              <div><small>本金</small><strong>{{ formatAmount(item.amount, 2) }} {{ item.asset_code }}</strong></div>
              <div><small>參考 APR</small><strong>{{ formatPercent(item.apr_rate) }}</strong></div>
              <div><small>預估收益</small><strong>{{ formatAmount(item.estimated_interest, 2) }} {{ item.asset_code }}</strong></div>
              <div><small>到期時間</small><strong>{{ item.maturity_at || '—' }}</strong></div>
            </div>
          </article>
        </div>

        <div class="holdings-tips">
          <article>
            <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
            <div>
              <strong>申購後 T+1 起息</strong>
              <p>產品確認後會顯示起息時間，收益以產品條款與實際結算為準。</p>
            </div>
          </article>
          <article>
            <span class="material-symbols-outlined" aria-hidden="true">verified_user</span>
            <div>
              <strong>留意到期與贖回</strong>
              <p>到期前可在持倉詳情查看結算窗口、贖回規則與風險揭露。</p>
            </div>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURNYSE - 我的持倉 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/financial-holdings.html 為唯一基準逐字遷移
 * 原稿為靜態頁，只有外部 back-nav.js
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--financial-holdings",
  "font-body",
  "min-h-screen",
  "bg-background",
  "pb-10",
  "text-on-surface",
  "antialiased"
];

export default {
  name: "FinancialHoldingsH5",
  data: function () {
    return {
      items: [],
      filter: "all",
      loading: false,
      errorMessage: ""
    };
  },
  computed: {
    activeItems: function () {
      return this.items.filter(function (it) {
        return ["active", "pending_settlement"].indexOf(String(it.status || "")) !== -1;
      });
    },
    activeCount: function () {
      return this.activeItems.length;
    },
    visibleItems: function () {
      if (this.filter === "active") return this.activeItems;
      if (this.filter === "settled") {
        return this.items.filter(function (it) {
          return ["settled", "returned", "completed"].indexOf(String(it.status || "")) !== -1;
        });
      }
      return this.items;
    },
    principalDisplay: function () {
      var total = this.activeItems.reduce(function (sum, it) {
        return sum + (parseFloat(it.amount || "0") || 0);
      }, 0);
      var asset = (this.activeItems[0] && this.activeItems[0].asset_code) || "USDT";
      return this.formatAmount(total, 2) + " " + asset;
    },
    interestDisplay: function () {
      var total = this.items.reduce(function (sum, it) {
        return sum + (parseFloat(it.estimated_interest || "0") || 0);
      }, 0);
      var asset = (this.items[0] && this.items[0].asset_code) || "USDT";
      return this.formatAmount(total, 2) + " " + asset;
    },
    nextSettlementDisplay: function () {
      var dates = this.activeItems
        .map(function (it) { return it.return_scheduled_at || it.maturity_at; })
        .filter(Boolean)
        .sort();
      return dates[0] || "—";
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "financial-holdings");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.fetchHoldings();
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
      if (window.EurnyseBack && typeof window.EurnyseBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurnyseBack.go(fallback);
      }
    },
    fetchHoldings: function () {
      var self = this;
      this.loading = true;
      this.errorMessage = "";
      userApi
        .financialSubscriptions()
        .then(function (data) {
          self.items = (data && data.items) || [];
        })
        .catch(function (error) {
          self.items = [];
          self.errorMessage = (error && error.message) || "載入持倉失敗";
        })
        .finally(function () {
          self.loading = false;
        });
    },
    formatAmount: function (value, digits) {
      return Number(value || 0).toLocaleString("en-GB", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    },
    formatPercent: function (value) {
      return Number(value || 0).toLocaleString("en-GB", { minimumFractionDigits: 1, maximumFractionDigits: 2 }) + "%";
    },
    statusLabel: function (status) {
      var map = {
        active: "計息中",
        pending_settlement: "待結算",
        settled: "已結算",
        returned: "已返還",
        completed: "已完成"
      };
      return map[status] || status || "—";
    }
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");

.holdings-page {
  color: #1b3453;
}

.holdings-hero,
.holdings-panel {
  position: relative;
  overflow: hidden;
  border-radius: 28px;
  border: 1px solid rgba(255, 255, 255, 0.78);
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0.32));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 20px 48px rgba(48, 105, 180, 0.14);
  backdrop-filter: blur(24px) saturate(155%);
  -webkit-backdrop-filter: blur(24px) saturate(155%);
}

.holdings-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 20px;
}

.holdings-hero::after,
.holdings-panel::after {
  content: "";
  position: absolute;
  inset: 1px;
  pointer-events: none;
  border-radius: inherit;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.86), transparent 38%), radial-gradient(circle at 88% 12%, rgba(255, 255, 255, 0.68), transparent 24%);
  opacity: 0.55;
}

.holdings-hero__copy,
.holdings-hero__action,
.holdings-panel > * {
  position: relative;
  z-index: 1;
}

.holdings-eyebrow {
  margin: 0 0 4px;
  color: #2388ff;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.holdings-hero h1,
.holdings-panel h2,
.holdings-empty-card h3 {
  margin: 0;
  font-family: "Space Grotesk", "Manrope", sans-serif;
  color: #18314f;
  letter-spacing: -0.02em;
}

.holdings-hero h1 {
  font-size: 24px;
}

.holdings-hero p:last-child {
  margin: 8px 0 0;
  max-width: 34rem;
  color: #6f88a7;
  font-size: 13px;
  font-weight: 650;
  line-height: 1.65;
}

.holdings-hero__action,
.holdings-btn-primary,
.holdings-btn-soft {
  display: inline-flex;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0 18px;
  font-size: 13px;
  font-weight: 900;
  text-decoration: none;
  white-space: nowrap;
}

.holdings-hero__action,
.holdings-btn-primary {
  color: #fff;
  background: linear-gradient(180deg, #66b7ff 0%, #248dff 45%, #0d67e8 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7), inset 0 -8px 16px rgba(0, 68, 190, 0.25), 0 14px 26px rgba(25, 126, 255, 0.28);
}

.holdings-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin: 14px 0;
}

.holdings-summary__card {
  border-radius: 22px;
  border: 1px solid rgba(255, 255, 255, 0.76);
  background: rgba(255, 255, 255, 0.58);
  padding: 15px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.86), 0 12px 24px rgba(65, 124, 190, 0.1);
}

.holdings-summary__card span,
.holdings-summary__card em {
  display: block;
  color: #7890ad;
  font-size: 12px;
  font-style: normal;
  font-weight: 750;
}

.holdings-summary__card strong {
  display: block;
  margin: 8px 0 4px;
  color: #18314f;
  font-family: "Space Grotesk", "Manrope", sans-serif;
  font-size: 20px;
}

.holdings-panel {
  padding: 18px;
}

.holdings-panel__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.holdings-tabs {
  display: inline-flex;
  gap: 6px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.76);
  background: rgba(255, 255, 255, 0.42);
  padding: 4px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.86);
}

.holdings-tabs button {
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: #6884a5;
  padding: 7px 11px;
  font-size: 12px;
  font-weight: 850;
}

.holdings-tabs button.is-active {
  color: #1676f4;
  background: rgba(35, 136, 255, 0.12);
}

.holdings-empty-card {
  position: relative;
  display: grid;
  grid-template-columns: 210px minmax(0, 1fr);
  gap: 18px;
  align-items: center;
  min-height: 280px;
  overflow: hidden;
  border-radius: 26px;
  border: 1px solid rgba(255, 255, 255, 0.82);
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.66), rgba(255, 255, 255, 0.28));
  padding: 24px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 18px 42px rgba(65, 124, 190, 0.13);
}

.holdings-loading {
  margin: 0;
  padding: 28px 16px;
  text-align: center;
  color: #7890ad;
  font-size: 14px;
  font-weight: 750;
}

.holdings-loading--error {
  color: #b42318;
}

.holdings-list {
  display: grid;
  gap: 12px;
}

.holdings-position-card {
  border-radius: 22px;
  border: 1px solid rgba(180, 210, 236, 0.7);
  background: rgba(255, 255, 255, 0.78);
  padding: 16px;
  box-shadow: 0 12px 24px rgba(65, 124, 190, 0.1);
}

.holdings-position-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.holdings-position-card__head h3 {
  margin: 0;
  color: #18314f;
  font-size: 18px;
}

.holdings-position-card__head span {
  border-radius: 999px;
  background: rgba(36, 141, 255, 0.12);
  color: #0d67e8;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 900;
}

.holdings-position-card__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.holdings-position-card__grid div {
  border-radius: 16px;
  background: rgba(239, 246, 255, 0.78);
  padding: 10px;
}

.holdings-position-card__grid small {
  display: block;
  color: #7890ad;
  font-size: 11px;
  font-weight: 800;
}

.holdings-position-card__grid strong {
  display: block;
  margin-top: 3px;
  color: #18314f;
  font-size: 13px;
}

.holdings-empty-card__glow {
  position: absolute;
  right: -40px;
  top: -50px;
  width: 260px;
  height: 260px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(69, 158, 255, 0.2), transparent 66%);
  filter: blur(5px);
}

.holdings-empty-visual {
  position: relative;
  z-index: 1;
  width: 210px;
  height: 170px;
}

.holdings-empty-visual__box {
  position: absolute;
  left: 34px;
  bottom: 18px;
  width: 144px;
  height: 80px;
  border-radius: 22px 22px 28px 28px;
  background: linear-gradient(180deg, rgba(202, 232, 255, 0.92), rgba(141, 199, 255, 0.72));
  box-shadow: inset 0 2px 0 rgba(255, 255, 255, 0.88), 0 22px 36px rgba(48, 123, 205, 0.18);
  transform: perspective(300px) rotateX(4deg);
}

.holdings-empty-visual__box::before {
  content: "";
  position: absolute;
  left: 16px;
  right: 16px;
  top: -26px;
  height: 44px;
  border-radius: 18px 18px 8px 8px;
  background: linear-gradient(180deg, rgba(247, 252, 255, 0.98), rgba(181, 223, 255, 0.85));
  clip-path: polygon(0 35%, 50% 0, 100% 35%, 86% 100%, 14% 100%);
}

.holdings-empty-visual__cube {
  position: absolute;
  border-radius: 11px;
  background: linear-gradient(145deg, rgba(248, 253, 255, 0.96), rgba(106, 179, 255, 0.65));
  border: 1px solid rgba(255, 255, 255, 0.72);
  box-shadow: 0 12px 22px rgba(58, 130, 210, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.88);
  transform: rotate(45deg);
  animation: holdings-empty-float 4.8s ease-in-out infinite;
}

.holdings-empty-visual__cube--one { width: 32px; height: 32px; left: 76px; top: 18px; }
.holdings-empty-visual__cube--two { width: 22px; height: 22px; left: 128px; top: 42px; animation-delay: .6s; }
.holdings-empty-visual__cube--three { width: 16px; height: 16px; left: 54px; top: 52px; animation-delay: .3s; }

@keyframes holdings-empty-float {
  0%, 100% { transform: translateY(0) rotate(45deg); }
  50% { transform: translateY(-10px) rotate(45deg); }
}

.holdings-empty-card__content {
  position: relative;
  z-index: 1;
}

.holdings-empty-card__badge {
  display: inline-flex;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.78);
  background: rgba(255, 255, 255, 0.52);
  color: #5f7fa6;
  padding: 7px 12px;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.holdings-empty-card h3 {
  margin-top: 12px;
  font-size: 24px;
}

.holdings-empty-card p {
  margin: 8px 0 18px;
  color: #718bad;
  font-size: 14px;
  font-weight: 650;
  line-height: 1.7;
}

.holdings-empty-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.holdings-btn-soft {
  border: 1px solid rgba(255, 255, 255, 0.8);
  color: #4270a0;
  background: rgba(255, 255, 255, 0.46);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.86), 0 10px 22px rgba(65, 124, 190, 0.1);
}

.holdings-tips {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 14px;
}

.holdings-tips article {
  display: flex;
  gap: 10px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.74);
  background: rgba(255, 255, 255, 0.45);
  padding: 13px;
}

.holdings-tips .material-symbols-outlined {
  color: #2388ff;
  font-size: 20px;
}

.holdings-tips strong {
  color: #24426d;
  font-size: 13px;
}

.holdings-tips p {
  margin: 4px 0 0;
  color: #718bad;
  font-size: 12px;
  font-weight: 650;
  line-height: 1.55;
}

@media (max-width: 700px) {
  .holdings-hero,
  .holdings-panel__head {
    flex-direction: column;
    align-items: stretch;
  }

  .holdings-empty-card,
  .holdings-tips {
    grid-template-columns: 1fr;
  }

  .holdings-summary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }

  .holdings-summary__card {
    border-radius: 18px;
    padding: 11px 9px;
  }

  .holdings-summary__card span,
  .holdings-summary__card em {
    font-size: 10px;
    line-height: 1.35;
  }

  .holdings-summary__card strong {
    margin: 6px 0 3px;
    font-size: 15px;
    line-height: 1.1;
  }

  .holdings-empty-card {
    text-align: center;
    min-height: 0;
    gap: 8px;
    padding: 18px;
  }

  .holdings-empty-visual {
    width: 180px;
    height: 132px;
    margin: 0 auto;
  }

  .holdings-empty-visual__box {
    left: 25px;
    bottom: 12px;
    width: 128px;
    height: 68px;
  }

  .holdings-empty-visual__cube--one { left: 68px; top: 8px; }
  .holdings-empty-visual__cube--two { left: 112px; top: 30px; }
  .holdings-empty-visual__cube--three { left: 42px; top: 40px; }

  .holdings-empty-actions {
    justify-content: center;
  }
}

@media (max-width: 380px) {
  .holdings-summary {
    grid-template-columns: 1fr;
  }
}
</style>
