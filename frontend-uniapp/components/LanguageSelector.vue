<template>
  <div class="eurforex-language-selector" :class="{ 'eurforex-language-selector--row': variant === 'menu-row' }">
    <button
      v-if="variant !== 'menu-row'"
      type="button"
      class="m15-lang-btn"
      :id="buttonId"
      @click="openLanguagePicker"
    >
      <svg viewBox="0 0 24 24" class="m15-lang-btn__icon" aria-hidden="true">
        <circle cx="12" cy="12" r="8"></circle>
        <path d="M4.8 12h14.4"></path>
        <path d="M12 4a12.6 12.6 0 0 1 0 16"></path>
        <path d="M12 4a12.6 12.6 0 0 0 0 16"></path>
      </svg>
      <span>{{ currentMeta.short }}</span>
    </button>
    <button
      v-else
      type="button"
      class="language-menu-row group"
      :id="buttonId"
      @click="openLanguagePicker"
    >
      <div class="flex items-center gap-4 min-w-0 text-left">
        <div class="w-11 h-11 rounded-2xl bg-primary/8 flex items-center justify-center text-primary border border-primary/10 shrink-0">
          <span class="material-symbols-outlined">language</span>
        </div>
        <div class="min-w-0">
          <div class="font-body text-base font-semibold text-on-surface">語言</div>
          <div class="text-sm text-on-surface-variant">切換目前前台顯示語言。</div>
        </div>
      </div>
      <div class="language-menu-row__value">
        <span>{{ currentMeta.name }}</span>
        <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors shrink-0">chevron_right</span>
      </div>
    </button>
    <div
      v-if="open"
      ref="overlay"
      class="eurforex-language-overlay"
      :class="{ 'eurforex-language-overlay--profile': variant === 'menu-row' }"
      @click.self="open = false"
    >
      <div
        class="eurforex-language-sheet"
        :class="{ 'eurforex-language-sheet--profile': variant === 'menu-row' }"
        role="dialog"
        aria-modal="true"
      >
        <div class="eurforex-language-sheet__head">
          <div>
            <p>{{ $t('ui.currentLanguage') }}</p>
            <h3>{{ $t('ui.selectLanguage') }}</h3>
          </div>
          <button type="button" :aria-label="$t('ui.close')" @click="open = false">×</button>
        </div>
        <div class="language-current-card" v-if="variant === 'menu-row'">
          <span class="material-symbols-outlined">translate</span>
          <div>
            <small>{{ $t('ui.currentLanguage') }}</small>
            <strong>{{ currentMeta.name }}</strong>
          </div>
        </div>
        <div class="language-wheel" :class="{ 'language-wheel--profile': variant === 'menu-row' }">
          <div class="language-wheel__focus"></div>
          <div class="language-sheet__list">
            <button
              v-for="item in langs"
              :key="item.value"
              type="button"
              class="language-sheet__item"
              :class="{ 'language-sheet__item--active': item.value === currentLang }"
              :data-lang-option="item.value"
              @click="select(item.value)"
            >
              <span>{{ item.name }}</span>
              <span v-if="variant === 'menu-row' && item.value === currentLang" class="material-symbols-outlined language-sheet__check">check_circle</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { LANGS, langMeta } from '@/common/langs'
import { currentLocale, setLocale } from '@/common/i18n'

export default {
  name: 'LanguageSelector',
  props: {
    buttonId: {
      type: String,
      default: 'languageButton'
    },
    variant: {
      type: String,
      default: 'button'
    }
  },
  data() {
    return {
      open: false,
      langs: LANGS,
      currentLang: currentLocale()
    }
  },
  computed: {
    currentMeta() {
      return langMeta(this.currentLang)
    }
  },
  mounted() {
    this.onLangChange = event => {
      this.currentLang = event && event.detail ? event.detail : currentLocale()
    }
    window.addEventListener('app:langchange', this.onLangChange)
  },
  beforeDestroy() {
    window.removeEventListener('app:langchange', this.onLangChange)
    if (this.$refs.overlay && this.$refs.overlay.parentNode === document.body) {
      document.body.removeChild(this.$refs.overlay)
    }
  },
  methods: {
    openLanguagePicker() {
      this.open = true
      this.$nextTick(() => {
        if (this.variant === 'menu-row' && this.$refs.overlay && this.$refs.overlay.parentNode !== document.body) {
          document.body.appendChild(this.$refs.overlay)
        }
        const root = this.$refs.overlay || this.$el
        const activeItem = root && root.querySelector('.language-sheet__item--active')
        if (activeItem && typeof activeItem.scrollIntoView === 'function') {
          activeItem.scrollIntoView({ block: 'center', behavior: 'smooth' })
        }
      })
    },
    select(value) {
      setLocale(value).then(locale => {
        this.currentLang = locale
        this.open = false
      })
    }
  }
}
</script>

<style>
.m15-lang-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 34px;
  padding: 0 13px;
  border: 1px solid rgba(47, 115, 205, 0.18);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.76);
  color: #19324f;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  box-shadow: 0 10px 24px rgba(33, 79, 131, 0.08), 0 0 0 1px rgba(255, 255, 255, 0.44) inset;
}

.m15-lang-btn__icon {
  width: 15px;
  height: 15px;
  display: block;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  fill: none;
}

.language-menu-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  width: 100%;
  padding: 0.875rem 0.75rem;
  border: 0;
  border-radius: 1rem;
  background: transparent;
  cursor: pointer;
  transition: background-color 0.18s ease;
}

.language-menu-row:hover {
  background: rgba(255, 255, 255, 0.45);
}

.language-menu-row__value {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
  color: #19324f;
  font-size: 0.875rem;
  font-weight: 700;
}

.eurforex-language-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(15, 27, 45, 0.36);
  backdrop-filter: blur(8px);
}

.eurforex-language-overlay.eurforex-language-overlay--profile {
  position: fixed !important;
  inset: 0 !important;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background: rgba(15, 27, 45, 0.26);
  backdrop-filter: blur(5px);
}

.eurforex-language-sheet {
  width: min(100%, 520px);
  max-height: min(78vh, 720px);
  overflow: hidden;
  border-radius: 28px 28px 0 0;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 -20px 60px rgba(33, 79, 131, 0.18);
  border: 1px solid rgba(47, 115, 205, 0.12);
}

.eurforex-language-sheet--profile {
  width: min(100%, 430px);
  max-height: min(86vh, 680px);
  border-radius: 30px;
  background:
    radial-gradient(circle at 86% 0%, rgba(208, 238, 255, 0.55), transparent 34%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(247, 252, 255, 0.98));
  box-shadow: 0 24px 80px rgba(25, 50, 79, 0.24);
}

.eurforex-language-sheet__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.25rem 0.75rem;
}

.eurforex-language-sheet__head p {
  margin: 0;
  color: #6c7f96;
  font-size: 0.78rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.eurforex-language-sheet__head h3 {
  margin: 0.2rem 0 0;
  color: #19324f;
  font-size: 1.25rem;
}

.eurforex-language-sheet__head button {
  width: 2.4rem;
  height: 2.4rem;
  border: 0;
  border-radius: 999px;
  background: rgba(47, 115, 205, 0.08);
  color: #214f83;
  font-size: 1.5rem;
}

.language-current-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0.25rem 1.25rem 0.85rem;
  padding: 0.9rem 1rem;
  border: 1px solid rgba(47, 115, 205, 0.12);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.72);
  color: #19324f;
  box-shadow: 0 10px 28px rgba(33, 79, 131, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.language-current-card .material-symbols-outlined {
  width: 2.35rem;
  height: 2.35rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 15px;
  background: linear-gradient(135deg, #34d7c2, #078f96);
  color: #fff;
  font-size: 1.25rem;
  box-shadow: 0 12px 24px rgba(0, 150, 155, 0.18);
}

.language-current-card small {
  display: block;
  color: #6c7f96;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.language-current-card strong {
  display: block;
  margin-top: 0.1rem;
  font-size: 1rem;
}

.language-wheel {
  position: relative;
  border-radius: 24px;
  background:
    linear-gradient(180deg, rgba(47, 115, 205, 0.08), rgba(47, 115, 205, 0.02)),
    rgba(247, 250, 255, 0.96);
  overflow: hidden;
  margin: 0.75rem 1.25rem 1.25rem;
}

.language-wheel--profile {
  margin-top: 0;
  border-radius: 22px;
  background: rgba(232, 244, 255, 0.66);
}

.language-wheel--profile .language-wheel__focus {
  display: none;
}

.language-wheel--profile .language-sheet__list {
  max-height: min(44vh, 390px);
  padding: 0.55rem;
  scroll-snap-type: none;
  -webkit-mask-image: none;
  mask-image: none;
}

.language-wheel--profile .language-sheet__item {
  justify-content: space-between;
  min-height: 48px;
  margin-bottom: 0.35rem;
  padding: 0 0.9rem 0 1rem;
  border: 1px solid transparent;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.56);
  color: #667890;
  font-size: 0.95rem;
  font-weight: 700;
  opacity: 1;
  text-align: left;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.62);
}

.language-wheel--profile .language-sheet__item:last-child {
  margin-bottom: 0;
}

.language-wheel--profile .language-sheet__item--active {
  border-color: rgba(255, 159, 67, 0.24);
  background:
    linear-gradient(135deg, rgba(255, 245, 232, 0.98), rgba(255, 255, 255, 0.88)),
    rgba(255, 255, 255, 0.9);
  color: #f59a2a;
  font-size: 1rem;
  letter-spacing: 0;
  text-shadow: none;
  transform: none;
  box-shadow: 0 12px 28px rgba(255, 159, 67, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.language-sheet__check {
  font-size: 1.1rem;
  color: #f59a2a;
}

.language-wheel__focus {
  position: absolute;
  left: 12px;
  right: 12px;
  top: 50%;
  height: 52px;
  margin-top: -26px;
  z-index: 0;
  border: 1px solid rgba(255, 159, 67, 0.22);
  border-radius: 16px;
  background:
    radial-gradient(circle at center, rgba(255, 173, 87, 0.16), rgba(255, 173, 87, 0.02) 72%),
    rgba(255, 255, 255, 0.52);
  box-shadow:
    0 0 0 1px rgba(255, 255, 255, 0.24) inset,
    0 0 18px rgba(255, 149, 0, 0.18),
    0 8px 22px rgba(255, 149, 0, 0.08);
  pointer-events: none;
}

.language-sheet__list {
  max-height: 248px;
  overflow-y: auto;
  padding: 98px 0;
  scroll-snap-type: y mandatory;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  -webkit-mask-image: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.3) 14%, #000 34%, #000 66%, rgba(0, 0, 0, 0.3) 86%, transparent 100%);
  mask-image: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.3) 14%, #000 34%, #000 66%, rgba(0, 0, 0, 0.3) 86%, transparent 100%);
}

.language-sheet__list::-webkit-scrollbar {
  display: none;
}

.language-sheet__item {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 1;
  width: 100%;
  min-height: 52px;
  padding: 0 10px;
  border: 0;
  background: transparent;
  color: #7a8599;
  font-size: 15px;
  font-weight: 600;
  text-align: center;
  cursor: pointer;
  scroll-snap-align: center;
  opacity: 0.58;
  transition: color 0.18s ease, transform 0.18s ease, opacity 0.18s ease, text-shadow 0.18s ease, letter-spacing 0.18s ease;
}

.language-sheet__item--active {
  color: #ff9f43;
  font-size: 18px;
  font-weight: 800;
  opacity: 1;
  letter-spacing: 0.01em;
  text-shadow:
    0 0 8px rgba(255, 159, 67, 0.42),
    0 0 16px rgba(255, 127, 17, 0.28),
    0 1px 0 rgba(255, 255, 255, 0.24);
  transform: scale(1.1);
}

@media (min-width: 720px) {
  .eurforex-language-overlay {
    align-items: center;
  }

  .eurforex-language-sheet {
    border-radius: 28px;
  }
}
</style>
