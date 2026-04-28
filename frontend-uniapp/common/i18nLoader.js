import { FALLBACK_LANG, normalizeLang } from './langs'

const loaded = {}

function unwrap(module) {
  const first = module && (module.default || module)
  return first && (first.default || first)
}

export function loadLocale(lang) {
  const locale = normalizeLang(lang)
  if (loaded[locale]) return Promise.resolve(loaded[locale])
  const imports = {
    eng: () => import(/* webpackChunkName: "locale-eng" */ '@/locales/eng.json'),
    'zh-Hant': () => import(/* webpackChunkName: "locale-zh-hant" */ '@/locales/zh-Hant.json'),
    'zh-Hans': () => import(/* webpackChunkName: "locale-zh-hans" */ '@/locales/zh-Hans.json'),
    jp: () => import(/* webpackChunkName: "locale-jp" */ '@/locales/jp.json'),
    kr: () => import(/* webpackChunkName: "locale-kr" */ '@/locales/kr.json'),
    vi: () => import(/* webpackChunkName: "locale-vi" */ '@/locales/vi.json'),
    de: () => import(/* webpackChunkName: "locale-de" */ '@/locales/de.json'),
    fr: () => import(/* webpackChunkName: "locale-fr" */ '@/locales/fr.json'),
    it: () => import(/* webpackChunkName: "locale-it" */ '@/locales/it.json'),
    nl: () => import(/* webpackChunkName: "locale-nl" */ '@/locales/nl.json'),
    es: () => import(/* webpackChunkName: "locale-es" */ '@/locales/es.json'),
    pt: () => import(/* webpackChunkName: "locale-pt" */ '@/locales/pt.json'),
    el: () => import(/* webpackChunkName: "locale-el" */ '@/locales/el.json'),
    da: () => import(/* webpackChunkName: "locale-da" */ '@/locales/da.json'),
    sv: () => import(/* webpackChunkName: "locale-sv" */ '@/locales/sv.json'),
    fi: () => import(/* webpackChunkName: "locale-fi" */ '@/locales/fi.json'),
    pl: () => import(/* webpackChunkName: "locale-pl" */ '@/locales/pl.json'),
    hu: () => import(/* webpackChunkName: "locale-hu" */ '@/locales/hu.json'),
    th: () => import(/* webpackChunkName: "locale-th" */ '@/locales/th.json'),
    hi: () => import(/* webpackChunkName: "locale-hi" */ '@/locales/hi.json'),
    ur: () => import(/* webpackChunkName: "locale-ur" */ '@/locales/ur.json'),
    ne: () => import(/* webpackChunkName: "locale-ne" */ '@/locales/ne.json'),
    fa: () => import(/* webpackChunkName: "locale-fa" */ '@/locales/fa.json')
  }
  const loader = imports[locale] || imports[FALLBACK_LANG]
  return loader().then(module => {
    loaded[locale] = unwrap(module)
    return loaded[locale]
  })
}
