<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/index/index" @click="backGo($event, '#/pages/index/index')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">訂單</div>
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/index/serviceCenter"><span class="material-symbols-outlined">search</span></a>
    </header>
    <main class="pt-20 px-4 max-w-3xl mx-auto space-y-5 pb-32 sm:pb-36">
      <section class="glass-panel rounded-[24px] p-3" aria-label="訂單分類">
        <div class="grid grid-cols-5 gap-2 text-sm" role="tablist">
          <button
            v-for="f in filters"
            :key="f.key"
            type="button"
            role="tab"
            :aria-selected="filter === f.key ? 'true' : 'false'"
            :data-order-filter="f.key"
            class="order-filter-btn rounded-2xl py-2 transition-colors"
            :class="filter === f.key ? 'orders-chip bg-primary-container/70 font-semibold text-primary-dim' : 'orders-subtext bg-surface-bright text-on-surface-variant font-normal'"
            @click="filter = f.key"
          >{{ f.label }}</button>
        </div>
      </section>

      <LoadingInlineSpinner v-if="loading" aria-label="訂單載入" />
      <p v-else-if="errorMessage" class="rounded-2xl bg-error/5 px-4 py-3 text-center text-sm text-error">{{ errorMessage }}</p>
      <p v-else-if="!visibleOrders.length" class="rounded-2xl bg-surface-bright px-4 py-8 text-center text-sm text-on-surface-variant">目前沒有符合條件的訂單。</p>
      <a
        v-for="order in visibleOrders"
        :key="order.realId || order.orderNo"
        class="order-card block space-y-4"
        :data-order-status="order.status"
        :href="'#/pages/setting/orderDetail?id=' + encodeURIComponent(order.realId)"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="order-card__id">訂單編號 {{ order.orderNo }}</div>
            <h2 class="order-card__title font-headline text-xl mt-1">{{ order.title }}</h2>
          </div>
          <span class="order-card__badge shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.14em]" :class="order.badgeClass">{{ order.statusLabel }}</span>
        </div>
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div class="info-box">
            <div class="orders-subtext">{{ order.methodLabel }}</div>
            <div class="order-card__value mt-1 font-semibold">{{ order.method }}</div>
          </div>
          <div class="info-box">
            <div class="orders-subtext">訂單金額</div>
            <div class="order-card__value mt-1 font-semibold">{{ order.fiatAmount }}</div>
          </div>
        </div>
        <div v-if="order.note" class="info-box info-box--note text-sm leading-relaxed">{{ order.note }}</div>
        <div class="order-card__footer flex items-center justify-between text-sm">
          <div class="orders-subtext">{{ order.timeLabel }} {{ order.time }}</div>
          <span class="order-card__link font-semibold inline-flex items-center gap-1">查看詳情 <span class="material-symbols-outlined text-[16px]">arrow_forward</span></span>
        </div>
      </a>
      <section class="glass-panel rounded-[24px] p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-headline text-lg">快捷入口</h2>
          <a class="text-sm text-primary-dim font-semibold" href="#/pages/index/hall">前往交易大廳</a>
        </div>
        <div class="grid grid-cols-3 gap-3 text-sm">
          <a class="rounded-2xl bg-surface-bright px-4 py-4 text-center hover:bg-surface-container-low transition-colors" href="#/pages/setting/fundRecord">資金紀錄</a>
          <a class="rounded-2xl bg-surface-bright px-4 py-4 text-center hover:bg-surface-container-low transition-colors" href="#/pages/index/serviceCenter">客服協助</a>
          <a class="rounded-2xl bg-surface-bright px-4 py-4 text-center hover:bg-surface-container-low transition-colors" href="#/pages/setting/wallet">我的資產</a>
        </div>
      </section>
    </main>
    <nav
      class="eurnyse-home-bottom-nav fixed bottom-0 left-0 w-full z-50 rounded-t-2xl bg-white/78 backdrop-blur-lg shadow-[0px_-8px_24px_rgba(33,79,131,0.08)] font-['Manrope']"
      aria-label="主頁底部導航"
    >
      <div class="flex justify-around items-center h-20 px-4 pb-safe">
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/index/index"><span class="material-symbols-outlined">home</span><span class="text-[11px] font-semibold tracking-wide mt-0.5">主頁</span></a>
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/index/hall"><span class="material-symbols-outlined">swap_horizontal_circle</span><span class="text-[11px] font-semibold tracking-wide mt-0.5">交易大廳</span></a>
        <button class="flex flex-col items-center justify-center text-primary-dim bg-primary-container/60 rounded-xl px-3 py-1 transition-all active:scale-90"><span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">receipt_long</span><span class="text-[11px] font-semibold tracking-wide mt-0.5">訂單</span></button>
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/setting/user"><span class="material-symbols-outlined">person</span><span class="text-[11px] font-semibold tracking-wide mt-0.5">個人中心</span></a>
      </div>
    </nav>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURNYSE - 訂單 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/orders.html 為唯一基準逐字遷移
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--orders",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-32"
];

export default {
  name: "OrdersH5",
  data: function () {
    return {
      filter: "all",
      filters: [
        { key: "all", label: "全部" },
        { key: "progress", label: "進行中" },
        { key: "completed", label: "已完成" },
        { key: "cancelled", label: "已取消" },
        { key: "appeal", label: "申訴中" }
      ],
      orders: [],
      loading: false,
      errorMessage: ""
    };
  },
  computed: {
    visibleOrders: function () {
      if (this.filter === "all") return this.orders;
      return this.orders.filter(function (order) {
        return order.status === this.filter;
      }, this);
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "orders");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.applyFilterFromHash();
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.fetchOrders();
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
    applyFilterFromHash: function () {
      try {
        var hash = window.location.hash || "";
        var q = hash.indexOf("?");
        if (q === -1) return;
        var params = new URLSearchParams(hash.slice(q + 1));
        var f = params.get("filter");
        var allowed = { all: true, progress: true, completed: true, cancelled: true, appeal: true };
        if (f && allowed[f]) this.filter = f;
      } catch (e) {}
    },
    backGo: function (event, fallback) {
      if (window.EurnyseBack && typeof window.EurnyseBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurnyseBack.go(fallback);
      }
    },
    fetchOrders: function () {
      var self = this;
      this.loading = true;
      this.errorMessage = "";
      userApi
        .orders("?page=1&page_size=100")
        .then(function (data) {
          var rows = (data && data.items) || [];
          self.orders = rows.map(function (item) {
            return self.mapApiOrder(item);
          });
        })
        .catch(function (error) {
          self.orders = [];
          self.errorMessage = (error && error.message) || "載入訂單失敗";
        })
        .finally(function () {
          self.loading = false;
        });
    },
    mapApiOrder: function (item) {
      var status = String(item.status || "");
      var side = String(item.side || "");
      var isBuy = side === "buy";
      var amount = this.formatAmount(item.amount, 2);
      var total = this.formatAmount(item.total_amount, 2);
      var fiat = item.fiat_code || "EUR";
      var asset = item.asset_code || "USDT";
      return {
        realId: item.id || "",
        orderNo: item.order_no || String(item.id || "—"),
        status: this.filterStatus(status),
        statusLabel: this.statusLabel(status),
        badgeClass: status === "completed" ? "bg-surface-container-high text-on-surface-variant" : status === "cancelled" ? "bg-error/10 text-error" : status === "disputed" ? "bg-[#f3f5f8] text-on-surface-variant" : "bg-primary-container text-primary-dim",
        title: (isBuy ? "買入 " : "賣出 ") + amount + " " + asset,
        methodLabel: isBuy ? "付款方式" : "收款方式",
        method: this.paymentMethodLabel(item.payment_method_summary),
        fiatAmount: fiat + " " + total,
        timeLabel: status === "completed" ? "完成時間" : status === "disputed" ? "申訴更新" : "建立時間",
        time: item.completed_at || item.updated_at || item.created_at || "—",
        note: status === "disputed" ? "平台正在檢查付款憑證與聊天紀錄。" : ""
      };
    },
    filterStatus: function (status) {
      if (status === "completed") return "completed";
      if (status === "cancelled") return "cancelled";
      if (status === "disputed") return "appeal";
      return "progress";
    },
    statusLabel: function (status) {
      var map = {
        pending_payment: "待付款",
        paid_pending_release: "待放行",
        completed: "已完成",
        cancelled: "已取消",
        disputed: "申訴中"
      };
      return map[status] || status || "未知";
    },
    paymentMethodLabel: function (summary) {
      var s = String(summary || "").trim();
      if (!s || s === "平台撮合") return this.$phrase("平台撮合", "平台撮合");
      return s;
    },
    formatAmount: function (value, digits) {
      return Number(value || 0).toLocaleString("en-GB", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
      });
    }
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");

/* 訂單列表卡片（對齊設計稿 glass + 頂部高光線） */
.order-card {
  margin: 0 0 22px;
  padding: 22px;
  border-radius: 30px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(247, 252, 255, 0.9));
  border: 1px solid rgba(190, 220, 240, 0.85);
  box-shadow:
    0 18px 36px rgba(34, 82, 124, 0.13),
    inset 0 1px 0 rgba(255, 255, 255, 0.9);
  position: relative;
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  transition:
    box-shadow 0.2s ease,
    transform 0.15s ease,
    border-color 0.2s ease;
}

.order-card:hover {
  border-color: rgba(150, 200, 235, 0.95);
  box-shadow:
    0 22px 44px rgba(34, 82, 124, 0.16),
    inset 0 1px 0 rgba(255, 255, 255, 0.95);
  transform: translateY(-1px);
}

.order-card:active {
  transform: translateY(0);
}

.order-card::before {
  content: "";
  position: absolute;
  left: 22px;
  right: 22px;
  top: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(44, 169, 255, 0.55), transparent);
  pointer-events: none;
}

.order-card__id {
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #647d92;
}

.order-card__title {
  color: #1a4a6e;
  letter-spacing: -0.02em;
}

.order-card__value {
  color: #153a5c;
}

.order-card__footer .orders-subtext {
  color: #6b8299;
}

.order-card__link {
  color: #1f6fad;
}

.order-card__link .material-symbols-outlined {
  font-variation-settings: "FILL" 0, "wght" 500, "GRAD" 0, "opsz" 24;
}

.info-box {
  padding: 16px 18px;
  border-radius: 20px;
  background: linear-gradient(180deg, #f6fbff, #edf6fc);
  border: 1px solid #d9e9f5;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
}

.info-box .orders-subtext {
  color: #5c758a;
  font-size: 12px;
  letter-spacing: 0.02em;
}

.info-box--note {
  color: #4d6578;
  border-color: rgba(217, 233, 245, 0.95);
  background: linear-gradient(180deg, #f9fcff, #f0f6fc);
}
</style>
