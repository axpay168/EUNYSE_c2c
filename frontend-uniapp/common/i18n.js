import Vue from 'vue'
import VueI18n from 'vue-i18n'
import engMessages from '@/locales/eng.json'
import { FALLBACK_LANG, normalizeLang } from './langs'
import { getStoredLang, LANG_CHANGE_EVENT, setStoredLang, syncDocumentLang } from './langStorage'
import { loadLocale } from './i18nLoader'
import { scheduleTranslate, setDomTranslatorFallbackMessages, setDomTranslatorMessages } from './domTranslator'

Vue.use(VueI18n)

const initialLocale = normalizeLang(getStoredLang())
let pendingLocale = ''

const i18n = new VueI18n({
  locale: FALLBACK_LANG,
  fallbackLocale: FALLBACK_LANG,
  silentFallbackWarn: true,
  silentTranslationWarn: true,
  messages: {
    [FALLBACK_LANG]: engMessages
  }
})

function installMessages(locale, messages) {
  i18n.setLocaleMessage(locale, messages || {})
  if (locale === FALLBACK_LANG) setDomTranslatorFallbackMessages(messages || {})
  if (locale === i18n.locale) setDomTranslatorMessages(messages || {}, locale)
}

export function ensureLocale(lang) {
  const locale = normalizeLang(lang)
  if (locale === FALLBACK_LANG) {
    installMessages(FALLBACK_LANG, engMessages)
    return Promise.resolve(engMessages)
  }
  return loadLocale(locale).then(messages => {
    installMessages(locale, messages)
    return messages
  })
}

export function setLocale(lang) {
  const locale = normalizeLang(lang)
  if (locale === i18n.locale) {
    setStoredLang(locale)
    return ensureLocale(locale).then(() => locale)
  }
  if (pendingLocale === locale) return ensureLocale(locale)
  pendingLocale = locale
  setStoredLang(locale)
  return ensureLocale(locale).then(messages => {
    i18n.locale = locale
    setDomTranslatorMessages(messages, locale)
    syncDocumentLang(locale)
    scheduleTranslate()
    pendingLocale = ''
    return locale
  }).catch(error => {
    pendingLocale = ''
    throw error
  })
}

export function currentLocale() {
  return normalizeLang(i18n.locale)
}

export function phrase(key, fallback) {
  const messages = i18n.getLocaleMessage(i18n.locale) || {}
  const fallbackMessages = i18n.getLocaleMessage(FALLBACK_LANG) || {}
  return (messages.phrases && messages.phrases[key]) || (fallbackMessages.phrases && fallbackMessages.phrases[key]) || fallback || key
}

installMessages(FALLBACK_LANG, engMessages)

export const i18nReady = ensureLocale(initialLocale).then(messages => {
  if (pendingLocale || getStoredLang() !== initialLocale) return i18n.locale
  i18n.locale = initialLocale
  setDomTranslatorMessages(messages, initialLocale)
  syncDocumentLang(initialLocale)
  scheduleTranslate()
  return initialLocale
})

if (typeof window !== 'undefined') {
  window.EurforexI18n = {
    setLocale,
    currentLocale,
    translate: scheduleTranslate,
    messages: () => i18n.getLocaleMessage(i18n.locale)
  }
  window.addEventListener(LANG_CHANGE_EVENT, event => {
    const next = event && event.detail
    if (next && next !== i18n.locale && next !== pendingLocale) setLocale(next)
  })
}

export default i18n
