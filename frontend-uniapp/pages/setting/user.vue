<template>
  <div>
    <!-- TopAppBar -->
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <div class="leading_type flex items-center">
        <img alt="EURNYSE" width="40" height="40" decoding="async" class="h-10 w-10 shrink-0 object-contain block" :src="logoSrc" />
      </div>
      <div class="font-headline text-lg font-bold text-on-surface">個人中心</div>
      <div class="trailing_type text-primary-dim hover:bg-primary/10 transition-colors scale-95 active:duration-150 p-2 rounded-full cursor-pointer flex items-center justify-center">
        <span class="material-symbols-outlined">notifications</span>
      </div>
    </header>

    <!-- Main Content Canvas -->
    <main class="pt-20 pb-32 px-4 md:px-8 max-w-5xl mx-auto relative z-10">
      <!-- Background Ambient Lighting -->
      <div class="fixed top-0 left-0 w-full h-full pointer-events-none z-0 overflow-hidden">
        <div class="absolute top-[-10%] right-[-10%] w-[50%] h-[50%] bg-primary/10 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-[60%] h-[60%] bg-secondary/10 rounded-full blur-[150px]"></div>
      </div>

      <!-- Hero Profile -->
      <section class="rounded-[28px] p-5 md:p-6 mb-6 relative z-10 overflow-hidden border border-white/60 bg-gradient-to-br from-primary-fixed via-white/70 to-primary-container shadow-[0_18px_36px_rgba(40,88,142,0.1)]">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.7),transparent_45%)]"></div>
        <div class="grid grid-cols-[118px_minmax(0,1fr)] md:grid-cols-[132px_minmax(0,1fr)] gap-x-4 md:gap-x-5 gap-y-3 items-start min-w-0">
          <div class="relative shrink-0">
            <div class="absolute inset-0 bg-white/55 rounded-[1.75rem] blur-xl"></div>
            <button
              type="button"
              class="relative z-10 group block rounded-[1.5rem] border-0 bg-transparent p-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/35"
              aria-label="更換使用者大頭照"
              @click="openAvatarPicker"
            >
              <img alt="使用者大頭照" class="w-24 h-24 md:w-28 md:h-28 rounded-[1.5rem] object-cover border border-white/70 shadow-[0_18px_36px_rgba(40,88,142,0.14)] transition-transform group-hover:scale-[1.02]" :src="avatarSrc" />
              <span class="absolute inset-x-2 bottom-2 rounded-full bg-on-surface/58 px-2 py-1 text-[11px] font-semibold text-white opacity-0 transition-opacity group-hover:opacity-100">更換頭像</span>
            </button>
            <div class="absolute -bottom-2 -right-2 z-20 bg-white/85 px-3 py-1 rounded-full border border-white/70 shadow-[0_10px_20px_rgba(33,79,131,0.12)]">
              <span class="font-headline text-[11px] text-primary-dim tracking-[0.18em] font-bold">{{ tierLevelLabel }}</span>
            </div>
          </div>
          <div class="min-w-0 pt-1">
            <button
              type="button"
              class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-full bg-white/72 px-3 py-1.5 border border-white/75 shadow-[0_8px_18px_rgba(33,79,131,0.06)] cursor-pointer transition-colors hover:bg-white/88 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/35"
              aria-label="複製 UID"
              @click="copyUserId"
            >
              <span class="text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">ID</span>
              <span class="text-on-surface font-medium text-sm">{{ userId }}</span>
              <span class="material-symbols-outlined text-[16px] text-primary/60 pointer-events-none">content_copy</span>
            </button>
            <div class="mt-1 space-y-1 font-body text-sm md:text-body-md text-on-surface-variant/90">
              <div class="flex items-center gap-2 rounded-full bg-white/52 px-3 py-1 border border-white/65">
                <span class="material-symbols-outlined text-[16px] text-primary/70">call</span>
                <span class="truncate">{{ mobileLabel }}</span>
              </div>
              <div class="flex items-center gap-2 rounded-full bg-white/52 px-3 py-1 border border-white/65">
                <span class="material-symbols-outlined text-[16px] text-primary/70">mail</span>
                <span class="truncate">{{ emailLabel }}</span>
              </div>
            </div>
          </div>
          <div class="col-span-2 flex flex-wrap items-center gap-3 sm:gap-4">
            <h2 class="font-headline text-xl md:text-2xl text-on-surface tracking-tight whitespace-nowrap leading-tight">{{ realName }}</h2>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-white/70 bg-white/60 px-2.5 py-1 text-[12px] font-medium text-primary-dim shrink-0 self-center">
              <span class="material-symbols-outlined text-[15px]">verified_user</span>
              <span>信用分 {{ creditScore }}</span>
            </span>
          </div>
        </div>
      </section>
      <!-- Bento Grid: Account Cards -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-6 mb-8 relative z-10">
        <!-- 可用餘額 -->
        <div class="lg:col-span-12 glass-panel rounded-[28px] p-5 md:p-6 relative overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-surface/0 to-secondary/5 opacity-60"></div>
          <div class="relative z-10">
            <div class="flex items-start justify-between gap-3">
              <h2 class="font-headline text-label-md text-on-surface-variant uppercase tracking-widest flex items-center gap-2 min-w-0">
                <span class="material-symbols-outlined text-[1rem] shrink-0">account_balance_wallet</span>
                <span class="truncate">可用餘額</span>
              </h2>
              <a class="shrink-0 flex h-[39px] w-[39px] items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-primary transition-colors hover:bg-primary/15" href="#/pages/setting/fundRecord" aria-label="前往資金紀錄">
                <span class="material-symbols-outlined text-[24px] leading-none">receipt_long</span>
              </a>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-3">
              <a class="rounded-2xl bg-white/55 border border-white/60 backdrop-blur-md px-4 pt-4 pb-3 shadow-[0_10px_24px_rgba(33,79,131,0.08)] min-h-[96px] block hover:bg-white/65 transition-colors active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-primary/25" href="#/pages/setting/wallet" aria-label="前往我的資產（法幣）">
                <div class="flex items-center justify-between">
                  <span class="font-body text-xs text-primary-dim">法幣</span>
                </div>
                <div class="font-headline text-3xl md:text-4xl text-on-surface mt-0.5 pr-12 leading-none tabular-nums">{{ eurBalanceParts.main }}<span class="text-on-surface-variant/50 text-[0.62em] font-headline align-baseline">.{{ eurBalanceParts.decimal }}</span></div>
                <div class="mt-0.5 flex justify-end">
                  <span class="font-body text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">EUR</span>
                </div>
              </a>
              <a class="rounded-2xl bg-white/45 border border-white/55 backdrop-blur-md px-4 pt-4 pb-3 shadow-[0_10px_24px_rgba(33,79,131,0.06)] min-h-[96px] block hover:bg-white/55 transition-colors active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-primary/25" href="#/pages/setting/wallet" aria-label="前往我的資產（加密資產）">
                <div class="flex items-center justify-between">
                  <span class="font-body text-xs text-primary-dim">加密資產</span>
                </div>
                <div class="font-headline text-3xl md:text-4xl text-on-surface mt-0.5 pr-12 leading-none tabular-nums">{{ usdtBalanceParts.main }}<span class="text-on-surface-variant/50 text-[0.62em] font-headline align-baseline">.{{ usdtBalanceParts.decimal }}</span></div>
                <div class="mt-0.5 flex justify-end">
                  <span class="font-body text-[11px] uppercase tracking-[0.18em] text-on-surface-variant">USDT</span>
                </div>
              </a>
            </div>
          </div>
        </div>
      </section>
      <!-- Settings List -->
      <section class="relative z-10">
        <div class="mb-3 px-1">
          <h4 class="font-headline text-sm md:text-label-md text-on-surface-variant uppercase tracking-widest">帳戶功能</h4>
        </div>
        <div class="glass-panel-high eurnyse-user-account-menu rounded-[24px] p-2 md:p-3">
          <LanguageSelector button-id="userLanguageButton" variant="menu-row" />
          <a class="flex items-center justify-between gap-4 px-3 py-3.5 cursor-pointer hover:bg-surface-bright/45 rounded-2xl transition-colors group" href="#/pages/setting/fundRecord">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/8 flex items-center justify-center text-primary border border-primary/10 shrink-0">
                <span class="material-symbols-outlined">account_balance</span>
              </div>
              <div class="min-w-0">
                <div class="font-body text-base font-semibold text-on-surface">資金紀錄</div>
                <div class="text-sm text-on-surface-variant">檢視充值、提現與結算紀錄。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors shrink-0">chevron_right</span>
          </a>
          <a class="flex items-center justify-between gap-4 px-3 py-3.5 cursor-pointer hover:bg-surface-bright/45 rounded-2xl transition-colors group" href="#/pages/setting/eurSwapWithdraw">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/10 flex items-center justify-center text-primary-dim border border-primary/15 shrink-0">
                <span class="material-symbols-outlined">currency_exchange</span>
              </div>
              <div class="min-w-0">
                <div class="font-body text-base font-semibold text-on-surface">換匯提領</div>
                <div class="text-sm text-on-surface-variant">以 EUR 餘額換算並提領 USDT 至鏈上地址。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary-dim transition-colors shrink-0">chevron_right</span>
          </a>
          <a class="flex items-center justify-between gap-4 px-3 py-3.5 cursor-pointer hover:bg-surface-bright/45 rounded-2xl transition-colors group" href="#/pages/setting/securityCenter">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/8 flex items-center justify-center text-primary border border-primary/10 shrink-0">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1, 'wght' 500;">verified_user</span>
              </div>
              <div class="min-w-0">
                <div class="font-body text-base font-semibold text-on-surface">安全中心</div>
                <div class="text-sm text-on-surface-variant">KYC 狀態、身份驗證與帳戶保護設定。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors shrink-0">chevron_right</span>
          </a>
          <a class="flex items-center justify-between gap-4 px-3 py-3.5 cursor-pointer hover:bg-surface-bright/45 rounded-2xl transition-colors group" href="#/pages/setting/inviteTeam">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-secondary/8 flex items-center justify-center text-secondary border border-secondary/10 shrink-0">
                <span class="material-symbols-outlined">group_add</span>
              </div>
              <div class="min-w-0">
                <div class="font-body text-base font-semibold text-on-surface">邀請團隊</div>
                <div class="text-sm text-on-surface-variant">管理推薦獎勵與團隊交易成長。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-secondary transition-colors shrink-0">chevron_right</span>
          </a>
          <a class="flex items-center justify-between gap-4 px-3 py-3.5 cursor-pointer hover:bg-surface-bright/45 rounded-2xl transition-colors group" href="#/pages/index/serviceCenter">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-tertiary/8 flex items-center justify-center text-tertiary border border-tertiary/10 shrink-0">
                <span class="material-symbols-outlined">support_agent</span>
              </div>
              <div class="min-w-0">
                <div class="font-body text-base font-semibold text-on-surface">客服中心</div>
                <div class="text-sm text-on-surface-variant">付款與訂單問題請聯繫線上客服。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-tertiary transition-colors shrink-0">chevron_right</span>
          </a>
          <a class="flex items-center justify-between gap-4 px-3 py-3.5 cursor-pointer hover:bg-surface-bright/45 rounded-2xl transition-colors group" href="#/pages/index/tutorial">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/8 flex items-center justify-center text-primary-dim border border-primary/10 shrink-0">
                <span class="material-symbols-outlined">school</span>
              </div>
              <div class="min-w-0">
                <div class="font-body text-base font-semibold text-on-surface">新手指南</div>
                <div class="text-sm text-on-surface-variant">充值、訂單與爭議處理指南。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors shrink-0">chevron_right</span>
          </a>
        </div>
        <div class="glass-panel rounded-[24px] p-2 mt-4 border border-error/15">
          <button class="w-full flex items-center justify-between gap-4 px-3 py-3.5 cursor-pointer hover:bg-error/8 rounded-2xl transition-colors group text-left" id="signOutButton" type="button" @click="handleSignOut">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-11 h-11 rounded-2xl bg-error/6 flex items-center justify-center text-error border border-error/12 shrink-0">
                <span class="material-symbols-outlined">logout</span>
              </div>
              <div class="min-w-0">
                <div class="font-body text-base font-semibold text-error">登出</div>
                <div class="text-sm text-on-surface-variant">從本裝置安全登出。</div>
              </div>
            </div>
            <span class="material-symbols-outlined text-error/70 shrink-0">chevron_right</span>
          </button>
        </div>
      </section>
    </main>

    <div v-if="avatarPickerOpen" class="eurnyse-avatar-picker" role="dialog" aria-modal="true" aria-label="選擇頭像" @click.self="closeAvatarPicker">
      <section class="eurnyse-avatar-picker__panel">
        <div class="eurnyse-avatar-picker__header">
          <div>
            <p class="eurnyse-avatar-picker__eyebrow">Avatar</p>
            <h3>選擇你的頭像</h3>
          </div>
          <button type="button" class="eurnyse-avatar-picker__close" aria-label="關閉頭像選擇" @click="closeAvatarPicker">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="eurnyse-avatar-picker__section">
          <div class="eurnyse-avatar-picker__section-title">男生</div>
          <div class="eurnyse-avatar-picker__grid">
            <button
              v-for="item in maleAvatarOptions"
              :key="item.id"
              type="button"
              class="eurnyse-avatar-option"
              :class="{ 'is-active': item.id === selectedAvatarId }"
              @click="selectAvatar(item.id)"
            >
              <img :src="item.src" :alt="item.label" />
              <span>{{ item.label }}</span>
            </button>
          </div>
        </div>
        <div class="eurnyse-avatar-picker__section">
          <div class="eurnyse-avatar-picker__section-title">女生</div>
          <div class="eurnyse-avatar-picker__grid">
            <button
              v-for="item in femaleAvatarOptions"
              :key="item.id"
              type="button"
              class="eurnyse-avatar-option"
              :class="{ 'is-active': item.id === selectedAvatarId }"
              @click="selectAvatar(item.id)"
            >
              <img :src="item.src" :alt="item.label" />
              <span>{{ item.label }}</span>
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- BottomNavBar：須含 eurnyse-home-bottom-nav，H5 fixed 才相對視口（見 eurnyse-style-refresh.css） -->
    <nav
      class="eurnyse-home-bottom-nav fixed bottom-0 left-0 w-full z-50 rounded-t-2xl bg-white/78 backdrop-blur-lg shadow-[0px_-8px_24px_rgba(33,79,131,0.08)] font-['Manrope']"
      aria-label="主頁底部導航"
    >
      <div class="flex justify-around items-center h-20 px-4 pb-safe">
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/index/index">
          <span class="material-symbols-outlined">home</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">主頁</span>
        </a>
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/index/hall">
          <span class="material-symbols-outlined">swap_horizontal_circle</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">交易大廳</span>
        </a>
        <a class="flex flex-col items-center justify-center text-on-surface-variant px-3 py-1 hover:text-primary-dim transition-all" href="#/pages/setting/myTask">
          <span class="material-symbols-outlined">receipt_long</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">訂單</span>
        </a>
        <a class="flex flex-col items-center justify-center text-primary-dim bg-primary-container/60 rounded-xl px-3 py-1 transition-all active:scale-90" href="#/pages/setting/user">
          <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person</span>
          <span class="text-[11px] font-semibold tracking-wide mt-0.5">個人中心</span>
        </a>
      </div>
    </nav>
  </div>
</template>

<script>
import { avatar as avatarImage, brandLogo } from '@/assets/images'
import { getAvatarOptions, getOrCreateUserAvatar, setUserAvatar } from '@/common/avatarPool'
import { me, userApi } from '@/utils/api'
import { getStoredUser } from '@/utils/session'

/**
 * EURNYSE - 個人中心 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/user.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js（動態注入）
 * 原稿內聯 <script>（登出按鈕寫入「last.mode / last.account」並清掉 remember.*）
 *   → 完整遷移到 handleSignOut method，所有 localStorage key 逐字保留
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--user",
  "font-body",
  "antialiased",
  "min-h-screen",
  "relative",
  "overflow-x-hidden",
  "selection:bg-primary/30",
  "selection:text-primary"
];

export default {
  name: "UserH5",
  data: function () {
    return {
      logoSrc: brandLogo,
      avatarSrc: avatarImage,
      userId: "",
      realName: "",
      mobileLabel: "尚未綁定手機",
      emailLabel: "尚未綁定 Email",
      creditScore: 0,
      tierLevel: 1,
      fiatAvailableBalance: 0,
      usdtAvailableBalance: 0,
      avatarPickerOpen: false,
      selectedAvatarId: "",
      avatarOptions: getAvatarOptions(),
      currentUserProfile: null
    };
  },
  computed: {
    tierLevelLabel: function () {
      return "LV" + (Number(this.tierLevel) > 0 ? Number(this.tierLevel) : 1);
    },
    eurBalanceParts: function () {
      return this.splitMoney(this.fiatAvailableBalance);
    },
    usdtBalanceParts: function () {
      return this.splitMoney(this.usdtAvailableBalance);
    },
    maleAvatarOptions: function () {
      return this.avatarOptions.filter(function (item) { return item.gender === "male"; });
    },
    femaleAvatarOptions: function () {
      return this.avatarOptions.filter(function (item) { return item.gender === "female"; });
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "user");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurnyseMarbleBgJs");
    this.applyUserProfile(getStoredUser());
    this.loadUserProfile();
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
    applyUserProfile: function (user) {
      if (!user) return;
      this.currentUserProfile = user;
      var userCode = String(user.display_code || user.username || user.account || "").trim();
      var legalName = String(user.real_name || user.legal_name || (user.kyc && user.kyc.legal_name) || "").trim();
      this.userId = userCode || "未取得 ID";
      this.realName = legalName || "--";
      this.mobileLabel = String(user.mobile_e164 || user.mobile || "").trim() || "尚未綁定手機";
      this.emailLabel = String(user.email || "").trim() || "尚未綁定 Email";
      this.creditScore = user.tier && typeof user.tier.score !== "undefined" ? Number(user.tier.score) || 0 : 0;
      this.tierLevel = user.tier && typeof user.tier.level !== "undefined" ? Number(user.tier.level) || 1 : this.tierLevel;
      var avatar = getOrCreateUserAvatar(user);
      this.avatarSrc = avatar.src || avatarImage;
      this.selectedAvatarId = avatar.id || "";
    },
    splitMoney: function (value) {
      var n = Number(value || 0);
      if (!isFinite(n) || n < 0) n = 0;
      var parts = n.toLocaleString("en-GB", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).split(".");
      return { main: parts[0] || "0", decimal: parts[1] || "00" };
    },
    applyOverview: function (overview) {
      var map = (overview && overview.wallet_map) || {};
      var eur = map.eur || {};
      var usdt = map.cash_usdt || {};
      var tier = (overview && overview.tier_profile) || {};
      this.fiatAvailableBalance = Number(eur.available_balance || 0) || 0;
      this.usdtAvailableBalance = Number(usdt.available_balance || 0) || 0;
      if (tier.level != null) this.tierLevel = Number(tier.level) || 1;
      if (tier.score != null) this.creditScore = Number(tier.score) || 0;
    },
    loadUserProfile: function () {
      var self = this;
      me()
        .then(function (user) {
          self.applyUserProfile(user);
          return userApi.overview();
        })
        .then(function (overview) {
          self.applyOverview(overview);
        })
        .catch(function () {
          self.applyUserProfile(getStoredUser());
        });
    },
    openAvatarPicker: function () {
      if (!this.currentUserProfile) this.applyUserProfile(getStoredUser());
      this.avatarPickerOpen = true;
    },
    closeAvatarPicker: function () {
      this.avatarPickerOpen = false;
    },
    selectAvatar: function (avatarId) {
      var self = this;
      var target = this.currentUserProfile || getStoredUser() || this.userId;
      var avatar = setUserAvatar(target, avatarId);
      this.avatarSrc = avatar.src || avatarImage;
      this.selectedAvatarId = avatar.id || avatarId;
      this.avatarPickerOpen = false;
      if (typeof uni !== "undefined" && uni.showToast) {
        uni.showToast({ title: "頭像已更新", icon: "success", duration: 1400 });
      }
      if (this.currentUserProfile) {
        userApi.updateAvatar(this.selectedAvatarId)
          .then(function (user) {
            if (user) self.applyUserProfile(user);
          })
          .catch(function () {});
      }
    },
    copyUserId: function () {
      var text = String(this.userId || "").trim();
      if (!text || text === "未取得 ID") {
        if (typeof uni !== "undefined" && uni.showToast) {
          uni.showToast({ title: "無可複製的 ID", icon: "none" });
        }
        return;
      }
      var toast = function (title, icon) {
        if (typeof uni !== "undefined" && uni.showToast) {
          uni.showToast({ title: title, icon: icon || "none", duration: 1800 });
        }
      };
      var okCopy = function () {
        toast("已複製到剪貼簿", "success");
      };
      var failCopy = function () {
        toast("複製失敗，請長按 ID 手動複製", "none");
      };
      if (typeof navigator !== "undefined" && navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(okCopy).catch(function () {
          fallbackExec();
        });
        return;
      }
      function fallbackExec() {
        try {
          var ta = document.createElement("textarea");
          ta.value = text;
          ta.setAttribute("readonly", "readonly");
          ta.setAttribute("aria-hidden", "true");
          ta.style.position = "fixed";
          ta.style.left = "-9999px";
          ta.style.top = "0";
          document.body.appendChild(ta);
          ta.select();
          ta.setSelectionRange(0, text.length);
          var ok = document.execCommand("copy");
          document.body.removeChild(ta);
          if (ok) okCopy();
          else failCopy();
        } catch (e) {
          failCopy();
        }
      }
      fallbackExec();
    },
    handleSignOut: function () {
      try {
        var rememberMode = localStorage.getItem("eurnyse.remember.mode") || localStorage.getItem("eurnyse.last.mode") || "phone";
        var rememberAccount = localStorage.getItem("eurnyse.remember.account") || localStorage.getItem("eurnyse.last.account") || "";
        if (rememberAccount) {
          localStorage.setItem("eurnyse.last.mode", rememberMode);
          localStorage.setItem("eurnyse.last.account", rememberAccount);
        }
        localStorage.removeItem("eurnyse.remember.enabled");
        localStorage.removeItem("eurnyse.remember.mode");
        localStorage.removeItem("eurnyse.remember.account");
        localStorage.removeItem("eurnyse.remember.secret");
      } catch (e) {}
      window.location.href = "#/pages/common/login";
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");

.eurnyse-avatar-picker {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 22px;
  background: rgba(18, 31, 48, 0.38);
  backdrop-filter: blur(16px);
}

.eurnyse-avatar-picker__panel {
  width: min(92vw, 430px);
  max-height: min(78vh, 620px);
  overflow: auto;
  border: 1px solid rgba(255, 255, 255, 0.72);
  border-radius: 28px;
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.94), rgba(235, 249, 255, 0.88));
  box-shadow: 0 24px 60px rgba(29, 70, 116, 0.22);
  padding: 20px;
}

.eurnyse-avatar-picker__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.eurnyse-avatar-picker__eyebrow {
  margin: 0 0 4px;
  color: #3b82d6;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

.eurnyse-avatar-picker__header h3 {
  margin: 0;
  color: #172033;
  font-size: 20px;
  font-weight: 800;
}

.eurnyse-avatar-picker__close {
  display: inline-flex;
  width: 38px;
  height: 38px;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(94, 151, 207, 0.22);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.72);
  color: #355779;
}

.eurnyse-avatar-picker__section + .eurnyse-avatar-picker__section {
  margin-top: 18px;
}

.eurnyse-avatar-picker__section-title {
  margin-bottom: 10px;
  color: #5c728b;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.eurnyse-avatar-picker__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.eurnyse-avatar-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  border: 1px solid rgba(94, 151, 207, 0.18);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.62);
  padding: 10px 8px;
  color: #37536f;
  font-size: 11px;
  font-weight: 700;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.eurnyse-avatar-option img {
  width: 66px;
  height: 66px;
  border-radius: 18px;
  object-fit: cover;
  box-shadow: 0 10px 20px rgba(33, 79, 131, 0.12);
}

.eurnyse-avatar-option.is-active {
  border-color: rgba(43, 126, 214, 0.72);
  background: rgba(235, 247, 255, 0.92);
  box-shadow: 0 12px 26px rgba(43, 126, 214, 0.16);
  color: #1f65b7;
}

.eurnyse-avatar-option:hover {
  transform: translateY(-1px);
  border-color: rgba(43, 126, 214, 0.48);
}
</style>
