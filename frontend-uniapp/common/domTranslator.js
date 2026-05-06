import { FALLBACK_LANG } from './langs'

const ORIGINAL_TEXT = new WeakMap()
const LAST_TRANSLATED_TEXT = new WeakMap()
const ORIGINAL_ATTR_PREFIX = 'data-eurforex-i18n-original-'
const ATTRS = ['placeholder', 'aria-label', 'title', 'alt']
const SKIP_TAGS = { SCRIPT: true, STYLE: true, NOSCRIPT: true, SVG: true, PATH: true }

let activeMessages = {}
let fallbackMessages = {}
let activeLocale = ''
let scheduled = false

function normalizeText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim()
}

function hasLetters(value) {
  return /[\u3400-\u9fffA-Za-zÀ-ÿΑ-ωА-яก-๙\u0900-\u097F\u0600-\u06FF]/.test(value)
}

function translateExact(value) {
  const text = normalizeText(value)
  if (!text) return value
  const phrases = activeMessages.phrases || {}
  const fallbackPhrases = activeLocale && activeLocale !== FALLBACK_LANG ? (fallbackMessages.phrases || {}) : {}
  const exact = phrases[text] || fallbackPhrases[text]
  if (exact) return exact
  const merged = { ...fallbackPhrases, ...phrases }
  let translated = text
  Object.keys(merged)
    .filter(key => key && key.length > 1 && translated.indexOf(key) >= 0)
    .sort((a, b) => b.length - a.length)
    .forEach(key => {
      translated = translated.split(key).join(merged[key])
    })
  return translated
}

function preserveOuterWhitespace(original, translated) {
  const leading = String(original).match(/^\s*/)[0]
  const trailing = String(original).match(/\s*$/)[0]
  return leading + translated + trailing
}

function shouldSkipElement(el) {
  if (!el || SKIP_TAGS[el.tagName]) return true
  if (el.closest && el.closest('[data-i18n-skip], .material-symbols-outlined, .status')) return true
  return false
}

function translateTextNode(node) {
  if (!node || !node.parentElement || shouldSkipElement(node.parentElement)) return
  const current = node.nodeValue
  if (!hasLetters(current)) return
  if (!ORIGINAL_TEXT.has(node) || (LAST_TRANSLATED_TEXT.has(node) && current !== LAST_TRANSLATED_TEXT.get(node))) {
    ORIGINAL_TEXT.set(node, current)
  }
  const original = ORIGINAL_TEXT.get(node)
  const translated = translateExact(original)
  const nextValue = preserveOuterWhitespace(original, translated)
  LAST_TRANSLATED_TEXT.set(node, nextValue)
  node.nodeValue = nextValue
}

function translateAttributes(el) {
  ATTRS.forEach(attr => {
    if (!el.hasAttribute || !el.hasAttribute(attr)) return
    const key = ORIGINAL_ATTR_PREFIX + attr.replace(/[^a-z0-9]+/gi, '-')
    if (!el.hasAttribute(key)) el.setAttribute(key, el.getAttribute(attr) || '')
    const original = el.getAttribute(key) || ''
    if (!hasLetters(original)) return
    el.setAttribute(attr, translateExact(original))
  })
}

export function setDomTranslatorMessages(messages, locale) {
  activeMessages = messages || {}
  activeLocale = locale || activeLocale
}

export function setDomTranslatorFallbackMessages(messages) {
  fallbackMessages = messages || {}
}

export function translateDom(root) {
  const target = root || (typeof document !== 'undefined' ? (document.getElementById('app') || document.body) : null)
  if (!target) return
  const walker = document.createTreeWalker(target, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      return normalizeText(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT
    }
  })
  let node = walker.nextNode()
  while (node) {
    translateTextNode(node)
    node = walker.nextNode()
  }
  const elements = target.querySelectorAll ? target.querySelectorAll('[placeholder], [aria-label], [title], img[alt]') : []
  for (let i = 0; i < elements.length; i += 1) translateAttributes(elements[i])
}

export function scheduleTranslate(root) {
  const run = () => {
    scheduled = false
    translateDom(root)
  }
  if (scheduled) {
    setTimeout(run, 0)
    return
  }
  scheduled = true
  if (typeof window !== 'undefined' && window.requestAnimationFrame) {
    window.requestAnimationFrame(run)
  } else {
    setTimeout(run, 0)
  }
}
