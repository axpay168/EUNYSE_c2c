<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/user" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">資金紀錄</div>
      <button type="button" class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" aria-label="查看全部資金紀錄" @click="active = 'all'"><span class="material-symbols-outlined">filter_alt</span></button>
    </header>
    <main class="pt-20 px-4 max-w-3xl mx-auto space-y-5 pb-10">
      <section class="glass-panel rounded-[24px] p-3">
        <div id="fund-record-tabs" class="grid grid-cols-4 gap-2 text-sm" role="tablist" aria-label="資金類型">
          <button
            v-for="t in tabs"
            :key="t.key"
            type="button"
            :class="'fund-tab ' + (active === t.key ? TAB_ON : TAB_OFF)"
            :data-tab="t.key"
            role="tab"
            :aria-selected="active === t.key ? 'true' : 'false'"
            @click="active = t.key"
          >{{ t.label }}</button>
        </div>
      </section>
      <section class="glass-panel rounded-[24px] p-5">
        <div id="fund-record-list" class="divide-y divide-outline-variant/20">
          <LoadingInlineSpinner v-if="loading" aria-label="資金紀錄載入" />
          <p v-else-if="errorMessage" class="py-6 text-center text-sm text-error">{{ errorMessage }}</p>
          <template v-else>
            <a
              v-for="record in visibleItems"
              :key="record.id"
              class="fund-record-item flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0 transition-colors hover:bg-surface-bright/70 rounded-xl -mx-1 px-1"
              :href="record.href"
              :data-type="record.type"
            >
              <div>
                <div class="font-semibold">{{ record.title }}</div>
                <div class="text-sm text-on-surface-variant mt-1">{{ record.time }}</div>
              </div>
              <div class="text-right">
                <div class="font-headline text-lg" :class="record.valueClass">{{ record.value }}</div>
                <div class="text-xs text-on-surface-variant">{{ record.statusLabel }}</div>
              </div>
            </a>
          </template>
        </div>
        <div id="fund-record-empty" class="eurnyse-empty-card eurnyse-empty-card--compact" :class="{ hidden: hasAny }">
          <div class="eurnyse-empty-illustration" aria-hidden="true">
            <span class="eurnyse-empty-illustration__cube eurnyse-empty-illustration__cube--one"></span>
            <span class="eurnyse-empty-illustration__cube eurnyse-empty-illustration__cube--two"></span>
            <span class="eurnyse-empty-illustration__cube eurnyse-empty-illustration__cube--three"></span>
            <span class="eurnyse-empty-illustration__box"></span>
          </div>
          <h2 class="eurnyse-empty-title">{{ $t('phrases.此分類暫無紀錄') }}</h2>
          <p class="eurnyse-empty-sub">{{ $t('phrases.目前沒有符合條件的流水資料，切換分類或返回資產頁後再試。') }}</p>
          <div class="eurnyse-empty-actions">
            <button class="eurnyse-empty-btn" type="button" @click="active = 'all'">{{ $t('phrases.查看全部') }}</button>
            <a class="eurnyse-empty-btn-soft" href="#/pages/setting/wallet">{{ $t('phrases.返回資產') }}</a>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'

/**
 * EURNYSE - 資金紀錄 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/fund-record.html 為唯一基準逐字遷移
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--fund-record",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface"
];

var TAB_ON_CLASS = "rounded-2xl py-2 font-semibold text-primary-dim bg-primary-container/70 ring-1 ring-primary/15";
var TAB_OFF_CLASS = "rounded-2xl py-2 text-on-surface-variant bg-surface-bright";

export default {
  name: "FundRecordH5",
  data: function () {
    return {
      active: "all",
      tabs: [
        { key: "all", label: "全部" },
        { key: "deposit", label: "充值" },
        { key: "withdraw", label: "提現" },
        { key: "settlement", label: "結算" }
      ],
      items: [],
      loading: false,
      errorMessage: "",
      TAB_ON: TAB_ON_CLASS,
      TAB_OFF: TAB_OFF_CLASS
    };
  },
  computed: {
    hasAny: function () {
      return this.visibleItems.length > 0;
    },
    visibleItems: function () {
      var a = this.active;
      if (a === "all") return this.items;
      return this.items.filter(function (item) {
        return item.type === a;
      });
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "fund-record");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.fetchRecords();
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
    shouldShow: function (type) {
      return this.active === "all" || this.active === type;
    },
    fetchRecords: function () {
      var self = this;
      this.loading = true;
      this.errorMessage = "";
      userApi
        .fundRecords("")
        .then(function (data) {
          var rows = (data && data.items) || [];
          self.items = rows.map(function (item) {
            return self.mapRecord(item);
          });
        })
        .catch(function (error) {
          self.items = [];
          self.errorMessage = (error && error.message) || "載入資金紀錄失敗";
        })
        .finally(function () {
          self.loading = false;
        });
    },
    mapRecord: function (item) {
      var t = String(item.type || "");
      var tabType = t === "recharge" ? "deposit" : t === "financial" ? "settlement" : t;
      var asset = item.asset_code || "";
      var amount = this.formatAmount(item.amount, 2);
      var sign = String(item.value || "").charAt(0) === "-" ? "-" : "+";
      return {
        id: item.id || String(tabType) + "-" + String(item.record_id || ""),
        type: tabType,
        title: this.recordTitle(t, item),
        time: item.created_at || "—",
        value: sign + " " + asset + " " + amount,
        valueClass: sign === "-" ? "text-error" : "text-primary-dim",
        statusLabel: this.statusLabel(item.status),
        href: "#/" + String(item.route || "/pages/setting/wallet").replace(/^\//, "")
      };
    },
    recordTitle: function (type, item) {
      if (type === "recharge") return this.$t("phrases.充值申請");
      if (type === "withdraw") return String(item.channel_type || "") === "bank" ? this.$t("phrases.銀行提現") : this.$t("phrases.USDT 提現");
      if (type === "financial") return this.$t("phrases.理財申購");
      return item.title || this.$t("phrases.資金紀錄");
    },
    statusLabel: function (status) {
      var map = {
        pending: "審核中",
        approved: "已通過",
        rejected: "已拒絕",
        completed: "已完成",
        active: "進行中",
        settled: "已結算"
      };
      return map[status] ? this.$t("phrases." + map[status]) : (status || "—");
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
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
