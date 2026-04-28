<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="javascript:;" @click="backGo($event, '#/pages/setting/user')" aria-label="返回"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">提領地址綁定</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>

    <main class="mx-auto max-w-3xl px-4 pt-20">
      <section class="glass-panel-high mb-6 rounded-[24px] p-5 md:p-6">
        <h2 class="font-headline text-lg text-on-surface">新增提領地址</h2>
        <p class="mt-1 text-sm text-on-surface-variant">選擇資產與鏈別，貼上錢包地址並設定備註名稱。</p>
        <div class="mt-5 space-y-4">
          <div>
            <div id="field-asset-lbl" class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">資產</div>
            <button
              type="button"
              class="addr-field-trigger mt-1.5 flex w-full items-center justify-between gap-3 rounded-2xl border border-outline-variant/25 bg-white/90 px-4 py-3.5 text-left shadow-[0_1px_0_rgba(255,255,255,0.9)] transition hover:border-primary/28 focus:outline-none focus:ring-2 focus:ring-primary/22"
              aria-haspopup="dialog"
              :aria-expanded="pickerOpen && pickerType === 'asset' ? 'true' : 'false'"
              :aria-controls="'addr-picker-sheet'"
              aria-labelledby="field-asset-lbl"
              @click="openPicker('asset')"
            >
              <span class="min-w-0 flex-1">
                <span class="block font-headline text-base font-bold tracking-tight text-on-surface">{{ currentAssetLabel }}</span>
                <span class="mt-0.5 block text-[12px] leading-snug text-on-surface-variant">{{ currentAssetDesc }}</span>
              </span>
              <span class="material-symbols-outlined shrink-0 text-on-surface-variant/90 text-[24px] leading-none" aria-hidden="true">expand_more</span>
            </button>
          </div>
          <div>
            <div id="field-network-lbl" class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">網路 / 鏈</div>
            <button
              type="button"
              class="addr-field-trigger mt-1.5 flex w-full items-center justify-between gap-3 rounded-2xl border border-outline-variant/25 bg-white/90 px-4 py-3.5 text-left shadow-[0_1px_0_rgba(255,255,255,0.9)] transition hover:border-primary/28 focus:outline-none focus:ring-2 focus:ring-primary/22"
              aria-haspopup="dialog"
              :aria-expanded="pickerOpen && pickerType === 'network' ? 'true' : 'false'"
              :aria-controls="'addr-picker-sheet'"
              aria-labelledby="field-network-lbl"
              @click="openPicker('network')"
            >
              <span class="min-w-0 flex-1">
                <span class="block font-headline text-base font-bold tracking-tight text-on-surface">{{ currentNetworkLabel }}</span>
                <span class="mt-0.5 block text-[12px] leading-snug text-on-surface-variant">{{ currentNetworkDesc }}</span>
              </span>
              <span class="material-symbols-outlined shrink-0 text-on-surface-variant/90 text-[24px] leading-none" aria-hidden="true">expand_more</span>
            </button>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant" for="addr-input">提領地址</label>
            <textarea id="addr-input" rows="3" v-model="addr" placeholder="貼上完整錢包地址" class="mt-1.5 w-full resize-y rounded-2xl border border-outline-variant/25 bg-white/90 px-4 py-3 font-mono text-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant" for="addr-memo">Memo / Tag（若該鏈需要）</label>
            <input id="addr-memo" type="text" v-model="memo" placeholder="無需則留空" class="mt-1.5 w-full rounded-2xl border border-outline-variant/25 bg-white/90 px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant" for="addr-label">地址備註（選填）</label>
            <input id="addr-label" type="text" v-model="addrLabel" placeholder="例如：硬體錢包、交易所子帳戶" class="mt-1.5 w-full rounded-2xl border border-outline-variant/25 bg-white/90 px-4 py-3 text-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
          </div>
        </div>
        <button type="button" class="mt-5 w-full rounded-full bg-gradient-to-br from-primary to-primary-dim py-3.5 text-center text-sm font-bold text-white shadow-[0_12px_28px_rgba(47,115,205,0.35)] hover:opacity-95" id="btn-submit-address" :disabled="submitting" @click="submitAddress">{{ submitting ? '提交中...' : '提交' }}</button>
      </section>

      <section class="glass-panel-high mb-6 rounded-[24px] p-5">
        <div class="flex items-end justify-between gap-3">
          <h2 class="font-headline text-lg text-on-surface">已綁定地址</h2>
          <button
            v-if="!boundLoading"
            type="button"
            class="shrink-0 text-xs font-bold text-primary-dim underline decoration-primary/40 underline-offset-2 hover:opacity-90"
            @click="loadBoundAddresses"
          >重新整理</button>
        </div>
        <LoadingInlineSpinner v-if="boundLoading" aria-label="已綁定地址載入" />
        <div v-else-if="boundError" class="mt-4 rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950/90">
          {{ boundError }}
          <button type="button" class="mt-2 text-xs font-bold text-primary-dim underline" @click="loadBoundAddresses">重試</button>
        </div>
        <div v-else-if="!boundUsdtItems.length" class="mt-4 rounded-2xl border border-outline-variant/15 bg-surface-bright/80 px-4 py-10 text-center text-sm text-on-surface-variant">
          尚無已綁定的 USDT 鏈上地址。提交上方表單並通過審核後會顯示於此。
        </div>
        <div v-else class="mt-4 space-y-3">
          <div
            v-for="it in boundUsdtItems"
            :key="'bound-' + it.id"
            class="rounded-2xl border border-outline-variant/15 bg-surface-bright/80 px-4 py-3.5"
          >
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-semibold text-on-surface">{{ boundRowTitle(it) }}</p>
              <span v-if="it.is_default" class="rounded-md bg-primary-container/80 px-2 py-0.5 text-[10px] font-bold text-primary-dim">預設</span>
              <span
                v-if="it.status && it.status !== 'approved'"
                class="rounded-md border border-amber-300/80 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-900/90"
              >{{ boundStatusLabel(it.status) }}</span>
            </div>
            <p class="mt-2 font-mono text-[13px] leading-relaxed tracking-wide text-on-surface break-all">{{ it.payout_address }}</p>
            <p class="mt-1.5 text-xs font-medium text-on-surface-variant">網路：{{ boundRowNetwork(it) }}</p>
          </div>
        </div>
      </section>
    </main>

    <!-- 底部抽層：資產 / 網路選擇 -->
    <div
      v-show="pickerOpen"
      id="addr-picker-overlay"
      class="fixed inset-0 z-[10060] flex flex-col justify-end"
      role="dialog"
      aria-modal="true"
      aria-labelledby="addr-picker-title"
    >
      <div class="absolute inset-0 bg-on-surface/45 backdrop-blur-[2px]" @click="closePicker" aria-hidden="true"></div>
      <div
        id="addr-picker-sheet"
        class="addr-picker__sheet relative mx-auto w-full max-w-lg rounded-t-[1.35rem] border border-outline-variant/20 bg-gradient-to-b from-white to-surface-container-low/95 shadow-[0_-12px_40px_rgba(22,50,80,0.18)]"
        @click.stop
      >
        <div class="addr-picker__grab mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-on-surface/15" aria-hidden="true"></div>
        <div class="flex items-center justify-between border-b border-outline-variant/12 px-4 pb-2 pt-1">
          <h2 id="addr-picker-title" class="font-headline text-base font-bold text-on-surface">{{ pickerTitle }}</h2>
          <button
            type="button"
            class="rounded-full p-2 text-on-surface-variant transition hover:bg-surface-container-low"
            aria-label="關閉"
            @click="closePicker"
          >
            <span class="material-symbols-outlined text-[22px] leading-none">close</span>
          </button>
        </div>
        <ul class="max-h-[50vh] space-y-1.5 overflow-y-auto px-2.5 py-2 pb-3" role="listbox">
          <li v-for="(opt, idx) in activePickerOptions" :key="pickerType + '-' + opt.value">
            <button
              type="button"
              class="addr-picker__opt group flex w-full items-start gap-3 rounded-2xl border px-3.5 py-3.5 text-left transition active:scale-[0.99]"
              :class="
                isOptionSelected(opt)
                  ? 'border-primary/35 bg-primary-container/50 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)]'
                  : 'border-outline-variant/18 bg-white/80 hover:border-primary/25 hover:bg-primary-container/25'
              "
              role="option"
              :aria-selected="isOptionSelected(opt) ? 'true' : 'false'"
              @click="applyPickerOption(opt)"
            >
              <span class="min-w-0 flex-1">
                <span class="block font-headline text-[15px] font-bold text-on-surface">{{ opt.label }}</span>
                <span v-if="opt.desc" class="mt-0.5 block text-xs leading-relaxed text-on-surface-variant">{{ opt.desc }}</span>
              </span>
              <span
                v-show="isOptionSelected(opt)"
                class="material-symbols-outlined mt-0.5 shrink-0 text-[20px] text-primary-dim"
                aria-hidden="true"
                >check_circle</span>
            </button>
          </li>
        </ul>
        <div class="border-t border-outline-variant/10 px-3 pb-3 pt-1" :style="pickerSafeArea"></div>
      </div>
    </div>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'
import { filterUsdtPayoutItems, usdtPayoutRowTitle, payoutMethodStatusLabel } from '@/utils/payoutUsdt'

/**
 * EURNYSE - 提領地址綁定 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/withdraw-address-binding.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（資產／鏈改為底部抽層選擇，不再使用 eurnyse-select.js）
 * 地址提交：POST /api/user/payout-methods → 後台審核後生效
 * 已綁定地址：GET /api/user/payout-methods → 完整鏈上地址展示（不截斷）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--withdraw-address-binding",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-12"
];

export default {
  name: "WithdrawAddressBindingH5",
  data: function () {
    return {
      asset: "USDT",
      network: "TRC20",
      addr: "",
      memo: "",
      addrLabel: "",
      pickerOpen: false,
      pickerType: null,
      assetOptions: [
        { value: "USDT", label: "USDT", desc: "Tether · 與美元掛鉤的穩定幣" },
        { value: "USDC", label: "USDC", desc: "USD Coin · 常用的美元穩定幣" },
        { value: "BTC", label: "BTC", desc: "Bitcoin · 比特幣主鏈資產" },
        { value: "ETH", label: "ETH", desc: "Ethereum · 以太幣主鏈資產" }
      ],
      networkOptions: [
        { value: "TRC20", label: "TRC20（Tron）", desc: "手續費低、到帳快，適合 USDT 小額" },
        { value: "ERC20", label: "ERC20（Ethereum）", desc: "以太坊生態，相容性最廣" },
        { value: "BEP20", label: "BEP20（BSC）", desc: "幣安智能鏈，速度與費用均衡" },
        { value: "BTC", label: "Bitcoin 主網", desc: "BTC 原生鏈轉帳" }
      ],
      payoutItems: [],
      boundLoading: false,
      boundError: "",
      submitting: false
    };
  },
  computed: {
    boundUsdtItems: function () {
      return filterUsdtPayoutItems(this.payoutItems);
    },
    pickerTitle: function () {
      return this.pickerType === "network" ? "選擇網路 / 鏈" : "選擇資產";
    },
    activePickerOptions: function () {
      return this.pickerType === "network" ? this.networkOptions : this.assetOptions;
    },
    pickerSafeArea: function () {
      return { paddingBottom: "max(0.75rem, env(safe-area-inset-bottom, 0px))" };
    },
    currentAssetLabel: function () {
      var o = this.findOption(this.assetOptions, this.asset);
      return o ? o.label : this.asset;
    },
    currentAssetDesc: function () {
      var o = this.findOption(this.assetOptions, this.asset);
      return o && o.desc ? o.desc : "";
    },
    currentNetworkLabel: function () {
      var o = this.findOption(this.networkOptions, this.network);
      return o ? o.label : this.network;
    },
    currentNetworkDesc: function () {
      var o = this.findOption(this.networkOptions, this.network);
      return o && o.desc ? o.desc : "";
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "withdraw-address-binding");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurnyseBackNav");
    this.loadBoundAddresses();
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
      if (event && event.preventDefault) event.preventDefault();
      if (window.EurnyseBack && typeof window.EurnyseBack.go === "function") {
        window.EurnyseBack.go(fallback);
      } else if (fallback) {
        try {
          if (fallback.charAt(0) === "#") {
            window.location.hash = fallback;
          } else {
            window.location.href = fallback;
          }
        } catch (e) {}
      }
    },
    findOption: function (list, value) {
      for (var i = 0; i < list.length; i++) {
        if (list[i].value === value) return list[i];
      }
      return null;
    },
    openPicker: function (type) {
      this.pickerType = type === "network" ? "network" : "asset";
      this.pickerOpen = true;
      try {
        document.body.style.overflow = "hidden";
      } catch (e) {}
    },
    closePicker: function () {
      this.pickerOpen = false;
      this.pickerType = null;
      try {
        document.body.style.overflow = "";
      } catch (e) {}
    },
    isOptionSelected: function (opt) {
      if (this.pickerType === "network") return this.network === opt.value;
      return this.asset === opt.value;
    },
    applyPickerOption: function (opt) {
      if (this.pickerType === "network") this.network = opt.value;
      else this.asset = opt.value;
      this.closePicker();
    },
    submitAddress: function () {
      if (this.asset !== "USDT") {
        alert("目前後端僅支援 USDT 鏈上提領地址。");
        return;
      }
      var address = String(this.addr || "").trim();
      if (!address) {
        alert("請填寫提領地址。");
        return;
      }
      if (this.submitting) return;
      var self = this;
      this.submitting = true;
      userApi
        .createPayoutMethod({
          channel_type: "usdt",
          usdt_network: this.network,
          payout_address: address,
          pix_key: "",
          is_default: false
        })
        .then(function () {
          self.addr = "";
          self.memo = "";
          self.addrLabel = "";
          alert("已提交，待後台審核通過後可用於提領。");
          return self.loadBoundAddresses();
        })
        .catch(function (error) {
          alert((error && error.message) || "地址提交失敗");
        })
        .finally(function () {
          self.submitting = false;
        });
    },
    boundRowTitle: usdtPayoutRowTitle,
    boundRowNetwork: function (it) {
      return it.usdt_network ? String(it.usdt_network).toUpperCase() : "—";
    },
    boundStatusLabel: function (status) {
      return payoutMethodStatusLabel(status);
    },
    loadBoundAddresses: function () {
      var self = this;
      this.boundLoading = true;
      this.boundError = "";
      return userApi
        .payoutMethods()
        .then(function (data) {
          self.payoutItems = (data && data.items) || [];
        })
        .catch(function (e) {
          self.boundError = (e && e.message) || "載入失敗";
          self.payoutItems = [];
        })
        .finally(function () {
          self.boundLoading = false;
        });
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
