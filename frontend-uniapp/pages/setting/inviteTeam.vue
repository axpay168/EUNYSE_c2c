<template>
  <div>
    <header class="fixed top-0 w-full border-b border-primary/10 bg-white/75 backdrop-blur-3xl shadow-[0_12px_32px_rgba(33,79,131,0.08)] flex justify-between items-center px-6 h-14 w-full z-50">
      <a class="flex shrink-0 items-center justify-center rounded-full p-2 text-primary-dim transition-colors hover:bg-primary/10" href="#/pages/setting/user" @click="backGo($event, '#/pages/setting/user')" aria-label="返回"><span class="material-symbols-outlined">arrow_back</span></a>
      <div class="font-headline text-lg font-bold text-on-surface">邀請團隊</div>
      <div class="w-10 shrink-0" aria-hidden="true"></div>
    </header>

    <main class="mx-auto max-w-lg px-4 pt-20">
      <section class="invite-card invite-panel p-5 md:p-6">
        <p class="invite-panel__title font-headline text-xl font-bold leading-snug md:text-2xl">邀請好友，一起成長</p>

        <div class="invite-panel__stats mt-5 grid grid-cols-2 gap-2">
          <div class="invite-stat-tile rounded-xl px-2 py-2 text-center">
            <p class="invite-stat-tile__label text-[22px] font-semibold leading-tight">團隊人數</p>
            <p class="invite-stat-tile__value font-headline mt-1.5 text-2xl font-bold tabular-nums tracking-tight">{{ totalInvites }}</p>
          </div>
          <div class="invite-stat-tile invite-stat-tile--reward rounded-xl px-2 py-2 text-center">
            <p class="invite-stat-tile__label text-[22px] font-semibold leading-tight">{{ $t('phrases.直推 / 二級') }}</p>
            <p class="invite-stat-tile__reward font-headline mt-1.5 text-lg font-bold tabular-nums tracking-tight md:text-xl">{{ directCount }} / {{ levelTwoCount }}</p>
          </div>
        </div>

        <div class="invite-panel__row invite-panel__row--code mt-6 flex flex-col gap-4 sm:flex-row sm:items-stretch sm:gap-3">
          <div class="invite-code-strip flex min-h-[52px] min-w-0 flex-1 overflow-hidden rounded-full">
            <img v-if="inviteQrUrl" class="invite-qr-image" :src="inviteQrUrl" alt="邀請 QR" />
            <div v-else class="invite-qr-placeholder" aria-hidden="true" title="邀請 QR" />
            <span class="invite-code-strip__value flex flex-1 items-center px-4 font-headline text-lg font-bold tracking-wide" id="invite-code-text">{{ invitationCode || '—' }}</span>
          </div>
          <button type="button" class="invite-btn-copy-code invite-copy-btn flex h-12 w-[8.75rem] shrink-0 items-center justify-center rounded-full px-2 text-sm font-bold transition" data-copy-target="invite-code-text" @click="copyFromEl('invite-code-text')">複製邀請碼</button>
        </div>

        <div class="invite-panel__row mt-5 flex flex-col gap-4 sm:flex-row sm:items-stretch sm:gap-3">
          <div class="invite-link-panel flex min-h-[52px] min-w-0 flex-1 items-center gap-2 rounded-[20px] px-3 py-2.5">
            <span class="invite-link-panel__icon material-symbols-outlined shrink-0 text-[22px]">grid_view</span>
            <p class="invite-link-panel__url min-w-0 flex-1 break-all text-[12px] leading-snug" id="invite-link-text">{{ inviteLink }}</p>
          </div>
          <button type="button" class="invite-link-btn invite-copy-btn flex h-12 w-[8.75rem] shrink-0 items-center justify-center rounded-full px-2 text-sm font-bold transition" data-copy-target="invite-link-text" @click="copyFromEl('invite-link-text')">複製連結</button>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import { userApi } from '@/utils/api'
import QRCode from 'qrcode'

/**
 * EURFOREX - 邀請團隊 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/invite-team.html 為唯一基準逐字遷移
 * 原稿內聯 IIFE：clipboard 複製 + fallback execCommand，完整遷移至 methods
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--invite-team",
  "font-body",
  "min-h-screen",
  "bg-background",
  "pb-12",
  "text-on-surface",
  "antialiased"
];

export default {
  name: "InviteTeamH5",
  data: function () {
    return {
      invitationCode: "",
      directCount: 0,
      levelTwoCount: 0,
      totalInvites: 0,
      inviteQrDataUrl: ""
    };
  },
  computed: {
    inviteLink: function () {
      if (!this.invitationCode) return "—";
      var base = "";
      try {
        base = window.location.origin + window.location.pathname;
      } catch (e) {
        base = "/h5/";
      }
      return base.replace(/\/?$/, "/") + "#/pages/common/register?invite=" + encodeURIComponent(this.invitationCode);
    },
    inviteQrUrl: function () {
      return this.inviteQrDataUrl;
    }
  },
  watch: {
    inviteLink: function () {
      this.refreshInviteQr();
    }
  },
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "invite-team");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    this.loadSharedScript("/static/previews/shared/marble-bg.js", "eurforexMarbleBgJs");
    this.loadSharedScript("/static/previews/shared/back-nav.js", "eurforexBackNav");
    this.loadInviteTeam();
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
    loadInviteTeam: function () {
      var self = this;
      userApi
        .inviteTeam()
        .then(function (data) {
          self.invitationCode = String((data && data.invitation_code) || "");
          self.directCount = Number((data && data.direct_count) || 0);
          self.levelTwoCount = Number((data && data.level_two_count) || 0);
          self.totalInvites = Number((data && data.total_invites) || (self.directCount + self.levelTwoCount) || 0);
          self.refreshInviteQr();
        })
        .catch(function () {
          self.invitationCode = "";
          self.inviteQrDataUrl = "";
          self.directCount = 0;
          self.levelTwoCount = 0;
          self.totalInvites = 0;
        });
    },
    refreshInviteQr: function () {
      var self = this;
      if (!this.invitationCode || this.inviteLink === "—") {
        this.inviteQrDataUrl = "";
        return Promise.resolve();
      }
      return QRCode.toDataURL(this.inviteLink, { margin: 1, width: 96 }).then(function (url) {
        self.inviteQrDataUrl = url;
      }).catch(function () {
        self.inviteQrDataUrl = "";
      });
    },
    backGo: function (event, fallback) {
      if (window.EurforexBack && typeof window.EurforexBack.go === "function") {
        if (event && event.preventDefault) event.preventDefault();
        window.EurforexBack.go(fallback);
      }
    },
    copyFromEl: function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      var text = el.textContent || "";
      var self = this;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text.trim()).then(function () {
          self.notify("已複製");
        }).catch(function () {
          self.fallbackCopy(text);
        });
      } else {
        this.fallbackCopy(text);
      }
    },
    fallbackCopy: function (text) {
      var ta = document.createElement("textarea");
      ta.value = text;
      ta.style.position = "fixed";
      ta.style.left = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand("copy");
        this.notify("已複製");
      } catch (e) {
        this.notify("請手動複製");
      }
      document.body.removeChild(ta);
    },
    notify: function (message) {
      try {
        if (typeof uni !== "undefined" && typeof uni.showToast === "function") {
          uni.showToast({ title: message, icon: "none", duration: 1800 });
          return;
        }
      } catch (e) {}
    }
  }
};
</script>

<style>
@import url("../../static/previews/shared/marble-bg.css");
@import url("../../static/previews/nc2c/css/preview-entry.css");

/*
 * 邀請主卡片配色：參考主流交易所邀請／推薦頁常見的淺色模式
 *（高對比白底、冷灰分區、深藍主 CTA、獎勵金額偏「收益」綠強調；見各所 Referral / Invite 活動頁）
 */
body[data-nc2c-page="invite-team"] .invite-panel {
  border-color: rgba(15, 45, 88, 0.1) !important;
  background:
    radial-gradient(120% 90% at 100% 0%, rgba(30, 102, 190, 0.07), transparent 52%),
    linear-gradient(180deg, #ffffff 0%, #f2f5fa 100%) !important;
  box-shadow:
    0 14px 44px rgba(18, 48, 86, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.98) !important;
}

body[data-nc2c-page="invite-team"] .invite-panel__title {
  color: #0d1f35 !important;
  letter-spacing: 0.01em;
}

body[data-nc2c-page="invite-team"] .invite-stat-tile {
  border: 1px solid rgba(14, 48, 82, 0.09);
  background: linear-gradient(180deg, #fbfdff 0%, #eef3f9 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
}

body[data-nc2c-page="invite-team"] .invite-stat-tile__label {
  color: #5a6c80 !important;
  font-size: 13px !important;
  font-weight: 700 !important;
}

body[data-nc2c-page="invite-team"] .invite-stat-tile__value {
  color: #0d2238 !important;
}

body[data-nc2c-page="invite-team"] .invite-stat-tile__reward {
  color: #0b7a58 !important;
}

/* 邀請碼列：左 QR 區 + 右碼欄，與下方「複製連結」藍系區隔 */
body[data-nc2c-page="invite-team"] .invite-code-strip {
  border: 1px solid rgba(30, 90, 150, 0.16);
  background: linear-gradient(180deg, #f8fafc 0%, #eef3f9 100%);
  box-shadow:
    0 4px 14px rgba(20, 55, 95, 0.06),
    inset 0 1px 0 rgba(255, 255, 255, 0.98);
}

body[data-nc2c-page="invite-team"] .invite-qr-placeholder {
  width: 52px;
  min-width: 52px;
  align-self: stretch;
  min-height: 52px;
  box-sizing: border-box;
  border-right: 1px solid rgba(30, 90, 150, 0.12);
  background-color: #f1f5fb;
  background-image: radial-gradient(circle at center, #8aa3bf 1.05px, transparent 1.15px);
  background-size: 4px 4px;
  background-position: 2px 2px;
}

body[data-nc2c-page="invite-team"] .invite-qr-image {
  width: 52px;
  min-width: 52px;
  align-self: stretch;
  min-height: 52px;
  box-sizing: border-box;
  border-right: 1px solid rgba(30, 90, 150, 0.12);
  background: #fff;
  object-fit: cover;
}

body[data-nc2c-page="invite-team"] .invite-code-strip__value {
  color: #0a2540 !important;
  background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
}

body[data-nc2c-page="invite-team"] .invite-btn-copy-code {
  color: #f8fbff !important;
  background: linear-gradient(180deg, #14b8a6 0%, #0d9488 48%, #0f766e 100%) !important;
  border: 1px solid rgba(255, 255, 255, 0.28) !important;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.22),
    0 10px 24px rgba(13, 116, 110, 0.22) !important;
}

body[data-nc2c-page="invite-team"] .invite-btn-copy-code:hover {
  filter: brightness(1.06);
}

body[data-nc2c-page="invite-team"] .invite-link-panel {
  border: 1px solid rgba(14, 48, 82, 0.1);
  background: linear-gradient(180deg, #ffffff, #f4f7fb);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

body[data-nc2c-page="invite-team"] .invite-link-panel__icon {
  color: rgba(30, 102, 190, 0.55) !important;
}

body[data-nc2c-page="invite-team"] .invite-link-panel__url {
  color: #4a5d73 !important;
}

body[data-nc2c-page="invite-team"] .invite-link-btn {
  color: #ffffff !important;
  background: linear-gradient(180deg, #2a7bdc 0%, #1a5aa8 52%, #124a8c 100%) !important;
  border: 1px solid rgba(255, 255, 255, 0.22) !important;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.22),
    0 12px 28px rgba(18, 72, 140, 0.28) !important;
}

body[data-nc2c-page="invite-team"] .invite-link-btn:hover {
  filter: brightness(1.05);
}
</style>
