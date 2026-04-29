<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/myTask" @click="backGo($event, '#/pages/setting/myTask')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">訂單詳情</div>
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="#/pages/index/serviceCenter"><span class="material-symbols-outlined">support_agent</span></a>
    </header>
    <main class="pt-20 px-4 max-w-3xl mx-auto space-y-5 pb-10 pb-safe">
      <LoadingInlineSpinner v-if="loading && !order" aria-label="訂單詳情載入" />
      <section v-else-if="!order" class="glass-panel rounded-[24px] p-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-container text-primary-dim">
          <span class="material-symbols-outlined">receipt_long</span>
        </div>
        <h2 class="font-headline text-xl font-bold">找不到此訂單</h2>
        <p class="mt-2 text-sm leading-relaxed text-on-surface-variant">{{ errorMessage || '此訂單可能已不存在，或連結缺少訂單編號。請返回訂單列表重新選擇。' }}</p>
        <a class="mt-5 inline-flex h-11 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-dim px-6 text-sm font-bold text-white" href="#/pages/setting/myTask">返回訂單列表</a>
      </section>
      <section v-if="order" class="order-card">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="order-card__id">訂單編號 {{ order.orderNo }}</div>
            <h2 class="order-card__title font-headline text-2xl mt-1">{{ order.title }}</h2>
          </div>
          <span class="order-card__badge shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.14em]" :class="order.badgeClass">{{ order.statusLabel }}</span>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
          <div class="info-box"><div class="orders-subtext">{{ order.totalLabel }}</div><div class="order-card__value mt-1 font-semibold">{{ order.fiatAmount }}</div></div>
          <div class="info-box"><div class="orders-subtext">單價</div><div class="order-card__value mt-1 font-semibold">{{ order.price }}</div></div>
          <div class="info-box"><div class="orders-subtext">對手方</div><div class="order-card__value mt-1 font-semibold">{{ order.counterparty }}</div></div>
          <div class="info-box"><div class="orders-subtext">{{ order.methodLabel }}</div><div class="order-card__value mt-1 font-semibold">{{ order.method }}</div></div>
        </div>
      </section>

      <section v-if="order" class="glass-panel rounded-[24px] p-5">
        <h3 class="font-headline text-lg">進度</h3>
        <div class="mt-4 space-y-4 text-sm">
          <div v-for="(step, idx) in order.steps" :key="step.title" class="flex gap-3" :class="{ 'opacity-50': !step.done }">
            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="step.done ? 'bg-primary text-white' : 'bg-surface-container-high text-on-surface-variant'">{{ idx + 1 }}</div>
            <div><div class="font-semibold">{{ step.title }}</div><div class="text-on-surface-variant">{{ step.desc }}</div></div>
          </div>
        </div>
      </section>

      <section v-if="order" class="order-tip-card">
        <h3 class="font-headline text-lg">備註與安全提示</h3>
        <p class="order-tip-card__text">{{ order.tip }}</p>
        <div class="mt-6 grid gap-3" :class="order.canCancel ? 'grid-cols-3' : 'grid-cols-2'">
          <a class="order-action-btn order-action-btn--support" href="#/pages/index/serviceCenter">聯繫客服</a>
          <button v-if="order.canCancel" type="button" class="order-action-btn order-action-btn--cancel" :disabled="loading" @click="cancelOrder">{{ loading ? '處理中...' : '取消訂單' }}</button>
          <a class="order-action-btn order-action-btn--primary" :href="order.primaryHref">{{ order.primaryAction }}</a>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURNYSE - 訂單詳情 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/order-detail.html 為唯一基準逐字遷移
 * 原稿無內聯 JS，僅外部 back-nav.js
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--order-detail",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-8"
];

export default {
  name: "OrderDetailH5",
  data: function () {
    return {
      orderId: "",
      loading: false,
      errorMessage: "",
      remoteOrder: null
    };
  },
  computed: {
    order: function () {
      return this.remoteOrder || null;
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "order-detail");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.orderId = this.getOrderIdFromHash();
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.fetchOrderDetail();
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
    getOrderIdFromHash: function () {
      try {
        var hash = window.location.hash || "";
        var query = hash.indexOf("?") >= 0 ? hash.slice(hash.indexOf("?") + 1) : "";
        var params = new URLSearchParams(query);
        var id = params.get("id");
        return id ? String(id).trim() : "";
      } catch (e) {
        return "";
      }
    },
    backGo: function (event, fallback) {
      if (window.EurnyseBack && typeof window.EurnyseBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurnyseBack.go(fallback);
      }
    },
    fetchOrderDetail: function () {
      if (!this.orderId || !/^\d+$/.test(this.orderId)) return;
      var self = this;
      this.loading = true;
      this.errorMessage = "";
      userApi.orderDetail(this.orderId)
        .then(function (data) {
          self.remoteOrder = self.mapApiOrder(data.order || {});
        })
        .catch(function (error) {
          self.errorMessage = (error && error.message) || "載入訂單失敗";
        })
        .finally(function () {
          self.loading = false;
        });
    },
    mapApiOrder: function (item) {
      var side = String(item.side || "");
      var status = String(item.status || "");
      var amount = this.formatAmount(item.amount, 2);
      var total = this.formatAmount(item.total_amount, 2);
      var fiat = item.fiat_code || "EUR";
      var asset = item.asset_code || "USDT";
      var isBuy = side === "buy";
      return {
        realId: item.id,
        orderNo: item.order_no || String(item.id || "—"),
        title: (isBuy ? "買入 " : "賣出 ") + amount + " " + asset,
        statusLabel: this.statusLabel(status),
        badgeClass: status === "completed" ? "bg-surface-container-high text-on-surface-variant" : status === "cancelled" ? "bg-error/10 text-error" : "bg-primary-container text-primary-dim",
        totalLabel: isBuy ? "付款總額" : "收款總額",
        fiatAmount: fiat + " " + total,
        price: this.formatAmount(item.price, 4) + " " + fiat,
        counterparty: isBuy ? ("賣家 #" + (item.seller_user_id || "-")) : ("買家 #" + (item.buyer_user_id || "-")),
        methodLabel: isBuy ? "付款方式" : "收款方式",
        method: this.paymentMethodLabel(item.payment_method_summary),
        primaryAction: "查看資金紀錄",
        primaryHref: "#/pages/setting/fundRecord",
        canCancel: ["pending_payment", "paid_pending_release", "disputed"].indexOf(status) !== -1,
        tip: status === "cancelled" ? "訂單已取消，已觸發對應資產退回。" : "請依平台流程完成付款或等待審核放行。",
        steps: [
          { title: "建立訂單", desc: item.created_at || "-", done: true },
          { title: isBuy ? "資金凍結" : "USDT 凍結", desc: status === "cancelled" ? "已退回" : "等待處理", done: status !== "pending_payment" || isBuy },
          { title: "訂單完成", desc: item.completed_at || "尚未完成", done: status === "completed" }
        ]
      };
    },
    cancelOrder: function () {
      if (!this.remoteOrder || !this.remoteOrder.realId || !this.remoteOrder.canCancel) return;
      if (!window.confirm("確定取消此訂單？取消後會退回已凍結資產並恢復掛單庫存。")) return;
      var self = this;
      this.loading = true;
      userApi.cancelOrder(this.remoteOrder.realId, "使用者取消訂單")
        .then(function () {
          return self.fetchOrderDetail();
        })
        .catch(function (error) {
          alert((error && error.message) || "取消訂單失敗");
        })
        .finally(function () {
          self.loading = false;
        });
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

.order-tip-card {
  border-radius: 24px;
  padding: 20px;
  background: linear-gradient(180deg, #f6fbff, #edf5ff);
  border: 1px solid #d2e4f4;
}

.order-tip-card__text {
  margin-top: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  font-size: 14px;
  line-height: 1.7;
  color: #35516b;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(145, 179, 207, 0.45);
}

.order-action-btn {
  height: 56px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

.order-action-btn:active {
  transform: scale(0.98);
}

.order-action-btn--support {
  color: #194d74;
  background: #ffffff;
  border: 1px solid #b9d6ee;
  box-shadow: 0 8px 20px rgba(40, 97, 145, 0.12);
}

.order-action-btn--cancel {
  color: #b42336;
  background: #fff3f5;
  border: 1px solid #f3c2ca;
  box-shadow: 0 8px 18px rgba(180, 35, 54, 0.12);
}

.order-action-btn--cancel:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.order-action-btn--primary {
  color: #ffffff;
  background: linear-gradient(135deg, #2e78c8, #1d5fa6);
  box-shadow: 0 14px 30px rgba(47, 115, 205, 0.24);
}
</style>
