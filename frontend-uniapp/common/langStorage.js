import { DEFAULT_LANG, detectBrowserLang, langMeta, normalizeLang } from './langs'

export const LANG_STORAGE_KEY = 'lang'
export const LANG_MANUAL_STORAGE_KEY = 'lang:manual'
export const LANG_CHANGE_EVENT = 'app:langchange'

function readStoredLangValue() {
  try {
    const uniApi = typeof uni !== 'undefined' ? uni : null
    const value = uniApi && uniApi.getStorageSync ? uniApi.getStorageSync(LANG_STORAGE_KEY) : ''
    if (value) return value
  } catch (e) {}
  try {
    if (typeof window !== 'undefined' && window.localStorage) {
      return window.localStorage.getItem(LANG_STORAGE_KEY) || ''
    }
  } catch (e) {}
  return ''
}

export function hasStoredLang() {
  return Boolean(readStoredLangValue())
}

export function getStoredLang() {
  const stored = readStoredLangValue()
  if (stored) return normalizeLang(stored)
  return detectBrowserLang() || DEFAULT_LANG
}

export function syncDocumentLang(lang) {
  try {
    if (typeof document === 'undefined') return
    document.documentElement.setAttribute('lang', langMeta(lang).htmlLang)
  } catch (e) {}
}

export function setStoredLang(lang, options = {}) {
  const normalized = normalizeLang(lang)
  const manual = options.manual !== false
  try {
    const uniApi = typeof uni !== 'undefined' ? uni : null
    if (uniApi && uniApi.setStorageSync) {
      uniApi.setStorageSync(LANG_STORAGE_KEY, normalized)
      if (manual) uniApi.setStorageSync(LANG_MANUAL_STORAGE_KEY, '1')
    }
  } catch (e) {}
  try {
    if (typeof window !== 'undefined' && window.localStorage) {
      window.localStorage.setItem(LANG_STORAGE_KEY, normalized)
      if (manual) window.localStorage.setItem(LANG_MANUAL_STORAGE_KEY, '1')
    }
  } catch (e) {}
  syncDocumentLang(normalized)
  try {
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent(LANG_CHANGE_EVENT, { detail: normalized }))
    }
  } catch (e) {}
  return normalized
}

syncDocumentLang(getStoredLang())
