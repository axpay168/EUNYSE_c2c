<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/user" @click="backGo($event, '#/pages/setting/user')"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="min-w-0 truncate px-1 text-center font-headline text-lg font-bold text-on-surface">安全中心 / 客服</div>
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10 scale-95 active:duration-150" href="javascript:void(0)" :aria-label="$t('phrases.聯絡專員')" @click="openChatwoot($event)"><span class="material-symbols-outlined">support_agent</span></a>
    </header>
    <main class="pt-20 px-4 max-w-3xl mx-auto pb-10">
      <section class="glass-panel rounded-[24px] p-5">
        <h2 class="font-headline text-2xl">{{ $t('phrases.客服中心') }}</h2>
        <div class="service-center-tiles" role="navigation" aria-label="客服快捷入口">
          <a class="service-center-tile" href="#/pages/setting/myTask">
            <div class="service-center-tile__icon service-center-tile__icon--primary"><span class="material-symbols-outlined" aria-hidden="true">receipt_long</span></div>
            <div class="service-center-tile__text">
              <div class="service-center-tile__title">訂單問題</div>
              <p class="service-center-tile__desc">付款、放行與申訴</p>
            </div>
          </a>
          <a class="service-center-tile" href="#/pages/setting/bindinfo">
            <div class="service-center-tile__icon service-center-tile__icon--secondary"><span class="material-symbols-outlined" aria-hidden="true">account_balance</span></div>
            <div class="service-center-tile__text">
              <div class="service-center-tile__title">銀行綁定</div>
              <p class="service-center-tile__desc">收付款帳戶綁定</p>
            </div>
          </a>
          <a class="service-center-tile" href="#/pages/setting/kycVerification">
            <div class="service-center-tile__icon service-center-tile__icon--tertiary"><span class="material-symbols-outlined" aria-hidden="true">verified_user</span></div>
            <div class="service-center-tile__text">
              <div class="service-center-tile__title">實名驗證</div>
              <p class="service-center-tile__desc">KYC 與驗證進度</p>
            </div>
          </a>
          <a class="service-center-tile" href="#/pages/index/tutorial">
            <div class="service-center-tile__icon service-center-tile__icon--primary"><span class="material-symbols-outlined" aria-hidden="true">school</span></div>
            <div class="service-center-tile__text">
              <div class="service-center-tile__title">新手指南</div>
              <p class="service-center-tile__desc">操作流程與新手教學</p>
            </div>
          </a>
          <a class="service-center-tile" href="#/pages/common/article">
            <div class="service-center-tile__icon service-center-tile__icon--secondary"><span class="material-symbols-outlined" aria-hidden="true">article</span></div>
            <div class="service-center-tile__text">
              <div class="service-center-tile__title">公告文章</div>
              <p class="service-center-tile__desc">查看通知與活動內容</p>
            </div>
          </a>
          <a class="service-center-tile" href="#/pages/setting/securityCenter">
            <div class="service-center-tile__icon service-center-tile__icon--tertiary"><span class="material-symbols-outlined" aria-hidden="true">shield_lock</span></div>
            <div class="service-center-tile__text">
              <div class="service-center-tile__title">安全中心</div>
              <p class="service-center-tile__desc">密碼、KYC 與綁定設定</p>
            </div>
          </a>
        </div>
        <a class="service-cta mt-5 w-full rounded-full bg-gradient-to-br from-primary to-primary-dim text-white py-3 font-headline font-bold tracking-wide flex items-center justify-center" href="javascript:void(0)" @click="openChatwoot($event)">{{ $t('phrases.聯絡專員') }}</a>
      </section>
    </main>
  </div>
</template>

<script>
/**
 * EURFOREX - 客服中心 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/service-center.html 為唯一基準逐字遷移
 * 外部腳本：../shared/marble-bg.js、../shared/back-nav.js（動態注入）
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--service-center",
  "font-body",
  "antialiased",
  "min-h-screen",
  "bg-background",
  "text-on-surface",
  "pb-10"
];

export default {
  name: "ServiceCenterH5",
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "service-center");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
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
    openChatwoot: function (event) {
      if (event && event.preventDefault) event.preventDefault();
      if (window.EurforexOpenChatwoot && typeof window.EurforexOpenChatwoot === "function") {
        window.EurforexOpenChatwoot();
      }
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");

/* 快捷入口：外層槽位 + 獨立小卡，區隔清楚 */
.service-center-tiles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 1.25rem;
  padding: 12px;
  border-radius: 18px;
  background: linear-gradient(165deg, rgba(255, 255, 255, 0.92) 0%, rgba(245, 249, 252, 0.98) 100%);
  border: 1px solid rgba(33, 79, 131, 0.12);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 2px 12px rgba(33, 79, 131, 0.05);
}

.service-center-tile {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 11px;
  min-height: 4.25rem;
  padding: 11px 12px;
  border-radius: 14px;
  background: #fff;
  border: 1px solid rgba(33, 79, 131, 0.14);
  box-shadow: 0 2px 8px rgba(33, 79, 131, 0.06);
  text-decoration: none;
  color: inherit;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.service-center-tile:hover {
  border-color: rgba(46, 100, 134, 0.38);
  box-shadow: 0 10px 26px rgba(33, 79, 131, 0.12);
  transform: translateY(-2px);
}

.service-center-tile:active {
  transform: translateY(0) scale(0.98);
}

.service-center-tile:focus {
  outline: none;
}

.service-center-tile:focus-visible {
  border-color: rgba(46, 100, 134, 0.55);
  box-shadow: 0 0 0 3px rgba(46, 100, 134, 0.22);
}

.service-center-tile__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
}

.service-center-tile__icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  align-self: center;
}

.service-center-tile__icon .material-symbols-outlined {
  font-size: 22px;
}

.service-center-tile__icon--primary {
  background: rgba(46, 100, 134, 0.1);
  color: rgb(46, 100, 134);
}

.service-center-tile__icon--secondary {
  background: rgba(59, 130, 152, 0.12);
  color: rgb(59, 130, 152);
}

.service-center-tile__icon--tertiary {
  background: rgba(71, 112, 132, 0.11);
  color: rgb(55, 95, 115);
}

.service-center-tile__title {
  margin: 0;
  font-weight: 600;
  font-size: 0.9375rem;
  line-height: 1.35;
  letter-spacing: 0.02em;
}

.service-center-tile__desc {
  margin: 4px 0 0;
  font-size: 0.6875rem;
  line-height: 1.45;
  color: var(--on-surface-variant, #5d6b73);
  opacity: 0.94;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
