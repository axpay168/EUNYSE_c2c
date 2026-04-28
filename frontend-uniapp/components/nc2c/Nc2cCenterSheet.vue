<template>
  <div
    class="fixed inset-0 z-[120] items-end justify-center bg-black/45 p-4 sm:items-center"
    :class="visible ? 'flex' : 'hidden'"
    role="dialog"
    aria-modal="true"
    :aria-labelledby="titleId"
    @click.self="close"
  >
    <div
      class="w-full max-w-md rounded-[24px] border border-outline-variant/20 bg-surface-bright p-5 shadow-[0_24px_48px_rgba(22,41,63,0.2)]"
      @click.stop
    >
      <div class="flex items-center justify-between gap-3">
        <h2 :id="titleId" class="font-headline text-lg font-bold text-on-surface">{{ title }}</h2>
        <button
          type="button"
          class="rounded-full p-1.5 text-on-surface-variant hover:bg-surface-container-low"
          aria-label="關閉"
          @click="close"
        >
          <span class="material-symbols-outlined text-[22px] leading-none">close</span>
        </button>
      </div>
      <slot />
    </div>
  </div>
</template>

<script>
/**
 * nc2c 遷移頁：居中／底部對齊的選擇彈窗外殼（法幣選銀行、USDT 地址簿等）
 * 與 withdraw / withdrawUsdt 原稿視覺一致；統一 body 捲動鎖定。
 */
export default {
  name: "Nc2cCenterSheet",
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      required: true
    },
    titleId: {
      type: String,
      required: true
    }
  },
  watch: {
    visible: function (v) {
      try {
        document.body.style.overflow = v ? "hidden" : "";
      } catch (e) {}
    }
  },
  beforeDestroy: function () {
    try {
      document.body.style.overflow = "";
    } catch (e) {}
  },
  methods: {
    close: function () {
      this.$emit("update:visible", false);
    }
  }
};
</script>
