export const LANGS = [
  { name: 'English', value: 'eng', short: 'EN', htmlLang: 'en' },
  { name: '繁體中文', value: 'zh-Hant', short: '繁', htmlLang: 'zh-Hant' },
  { name: '简体中文', value: 'zh-Hans', short: '简', htmlLang: 'zh-Hans' },
  { name: '日本語', value: 'jp', short: 'JP', htmlLang: 'ja' },
  { name: '한국어', value: 'kr', short: 'KR', htmlLang: 'ko' },
  { name: 'Tiếng Việt', value: 'vi', short: 'VI', htmlLang: 'vi' },
  { name: 'Deutsch', value: 'de', short: 'DE', htmlLang: 'de' },
  { name: 'Français', value: 'fr', short: 'FR', htmlLang: 'fr' },
  { name: 'Italiano', value: 'it', short: 'IT', htmlLang: 'it' },
  { name: 'Nederlands', value: 'nl', short: 'NL', htmlLang: 'nl' },
  { name: 'Español', value: 'es', short: 'ES', htmlLang: 'es' },
  { name: 'Português', value: 'pt', short: 'PT', htmlLang: 'pt' },
  { name: 'Ελληνικά', value: 'el', short: 'EL', htmlLang: 'el' },
  { name: 'Dansk', value: 'da', short: 'DA', htmlLang: 'da' },
  { name: 'Svenska', value: 'sv', short: 'SV', htmlLang: 'sv' },
  { name: 'Suomi', value: 'fi', short: 'FI', htmlLang: 'fi' },
  { name: 'Polski', value: 'pl', short: 'PL', htmlLang: 'pl' },
  { name: 'Magyar', value: 'hu', short: 'HU', htmlLang: 'hu' },
  { name: 'ไทย', value: 'th', short: 'TH', htmlLang: 'th' },
  { name: 'हिन्दी', value: 'hi', short: 'HI', htmlLang: 'hi' },
  { name: 'اردو', value: 'ur', short: 'UR', htmlLang: 'ur' },
  { name: 'नेपाली', value: 'ne', short: 'NE', htmlLang: 'ne' },
  { name: 'فارسی', value: 'fa', short: 'FA', htmlLang: 'fa' }
]

export const DEFAULT_LANG = 'eng'
export const FALLBACK_LANG = 'eng'
export const SUPPORTED_LANGS = LANGS.map(item => item.value)

export function normalizeLang(value) {
  const raw = String(value || '').trim()
  if (SUPPORTED_LANGS.indexOf(raw) >= 0) return raw
  if (/^en(?:-|$)/i.test(raw)) return 'eng'
  if (/^ja(?:-|$)/i.test(raw)) return 'jp'
  if (/^ko(?:-|$)/i.test(raw)) return 'kr'
  if (/^vi(?:-|$)/i.test(raw)) return 'vi'
  if (/^de(?:-|$)/i.test(raw)) return 'de'
  if (/^fr(?:-|$)/i.test(raw)) return 'fr'
  if (/^it(?:-|$)/i.test(raw)) return 'it'
  if (/^nl(?:-|$)/i.test(raw)) return 'nl'
  if (/^es(?:-|$)/i.test(raw)) return 'es'
  if (/^pt(?:-|$)/i.test(raw)) return 'pt'
  if (/^el(?:-|$)/i.test(raw)) return 'el'
  if (/^da(?:-|$)/i.test(raw)) return 'da'
  if (/^sv(?:-|$)/i.test(raw)) return 'sv'
  if (/^fi(?:-|$)/i.test(raw)) return 'fi'
  if (/^pl(?:-|$)/i.test(raw)) return 'pl'
  if (/^hu(?:-|$)/i.test(raw)) return 'hu'
  if (/^th(?:-|$)/i.test(raw)) return 'th'
  if (/^hi(?:-|$)/i.test(raw)) return 'hi'
  if (/^ur(?:-|$)/i.test(raw)) return 'ur'
  if (/^ne(?:-|$)/i.test(raw)) return 'ne'
  if (/^fa(?:-|$)/i.test(raw)) return 'fa'
  if (/^zh-(?:CN|SG)$/i.test(raw)) return 'zh-Hans'
  if (/^zh(?:-|$)/i.test(raw)) return 'zh-Hant'
  return DEFAULT_LANG
}

export function detectBrowserLang() {
  try {
    if (typeof navigator === 'undefined') return DEFAULT_LANG
    const values = []
    if (navigator.languages && navigator.languages.length) values.push(...navigator.languages)
    if (navigator.language) values.push(navigator.language)
    for (let i = 0; i < values.length; i += 1) {
      const normalized = normalizeLang(values[i])
      if (SUPPORTED_LANGS.indexOf(normalized) >= 0) return normalized
    }
  } catch (e) {}
  return DEFAULT_LANG
}

export function langMeta(value) {
  const normalized = normalizeLang(value)
  return LANGS.find(item => item.value === normalized) || LANGS[0]
}
