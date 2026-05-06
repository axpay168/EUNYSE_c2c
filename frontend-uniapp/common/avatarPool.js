const AVATAR_STORAGE_PREFIX = 'eurforex.user.avatar.'

function svgData(svg) {
  return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg)
}

function makeAvatar(config) {
  const hairPath = config.gender === 'female'
    ? '<path d="M36 48c0-18 11-31 28-31s28 13 28 31c0 17-9 31-28 31S36 65 36 48Z" fill="' + config.hair + '"/><path d="M31 77c5-9 15-15 33-15s28 6 33 15c-6 18-20 30-33 30S37 95 31 77Z" fill="' + config.hair + '"/>'
    : '<path d="M37 46c2-18 13-29 28-29 14 0 25 9 28 25-10-3-20-8-27-17-5 8-16 15-29 21Z" fill="' + config.hair + '"/>'
  const accessory = config.gender === 'female'
    ? '<circle cx="46" cy="58" r="3" fill="' + config.accent + '"/><circle cx="82" cy="58" r="3" fill="' + config.accent + '"/>'
    : '<path d="M47 66c8 6 25 6 34 0" fill="none" stroke="' + config.hair + '" stroke-width="4" stroke-linecap="round"/>'
  return {
    id: config.id,
    gender: config.gender,
    label: config.label,
    src: svgData(
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128">' +
        '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + config.bg1 + '"/><stop offset="1" stop-color="' + config.bg2 + '"/></linearGradient></defs>' +
        '<rect width="128" height="128" rx="28" fill="url(#bg)"/>' +
        '<circle cx="64" cy="55" r="42" fill="#fff" opacity=".35"/>' +
        hairPath +
        '<circle cx="64" cy="55" r="25" fill="' + config.skin + '"/>' +
        '<circle cx="54" cy="55" r="2.8" fill="#172033"/><circle cx="74" cy="55" r="2.8" fill="#172033"/>' +
        '<path d="M56 68c6 5 12 5 18 0" fill="none" stroke="#172033" stroke-width="3" stroke-linecap="round"/>' +
        accessory +
        '<path d="M29 112c5-23 19-35 35-35s30 12 35 35H29Z" fill="' + config.shirt + '"/>' +
        '<path d="M44 84c8 10 32 10 40 0" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="8" stroke-linecap="round"/>' +
      '</svg>'
    )
  }
}

const AVATAR_OPTIONS = [
  makeAvatar({ id: 'male-aurora', gender: 'male', label: '男生 · Aurora', bg1: '#e9f8ff', bg2: '#b8e6ff', skin: '#f2c6a6', hair: '#1f2937', shirt: '#2563eb', accent: '#60a5fa' }),
  makeAvatar({ id: 'male-slate', gender: 'male', label: '男生 · Slate', bg1: '#f4f8fb', bg2: '#d7e5f2', skin: '#d9a77f', hair: '#111827', shirt: '#334155', accent: '#38bdf8' }),
  makeAvatar({ id: 'male-mint', gender: 'male', label: '男生 · Mint', bg1: '#edfff8', bg2: '#b9f3dc', skin: '#f0b98f', hair: '#2f241f', shirt: '#0f766e', accent: '#5eead4' }),
  makeAvatar({ id: 'female-rose', gender: 'female', label: '女生 · Rose', bg1: '#fff1f8', bg2: '#ffd1e6', skin: '#f3c5a7', hair: '#3b1f2b', shirt: '#db2777', accent: '#f472b6' }),
  makeAvatar({ id: 'female-sky', gender: 'female', label: '女生 · Sky', bg1: '#eef8ff', bg2: '#c6e7ff', skin: '#e9b68e', hair: '#1e293b', shirt: '#0284c7', accent: '#38bdf8' }),
  makeAvatar({ id: 'female-violet', gender: 'female', label: '女生 · Violet', bg1: '#f6f0ff', bg2: '#ded0ff', skin: '#c98d68', hair: '#24112f', shirt: '#7c3aed', accent: '#a78bfa' })
]

function storageAvailable() {
  return typeof localStorage !== 'undefined'
}

function normalizeKey(value) {
  return String(value || '').trim().toLowerCase()
}

function userKeys(userOrKey) {
  if (typeof userOrKey === 'string') {
    const key = normalizeKey(userOrKey)
    return key ? ['account:' + key] : []
  }
  const user = userOrKey || {}
  const values = [
    user.id != null ? 'id:' + user.id : '',
    user.display_code,
    user.username,
    user.account,
    user.email,
    user.mobile_e164,
    user.mobile
  ]
  const seen = {}
  return values
    .map(normalizeKey)
    .filter(Boolean)
    .map(value => value.indexOf(':') === -1 ? 'account:' + value : value)
    .filter(value => {
      if (seen[value]) return false
      seen[value] = true
      return true
    })
}

export function avatarById(id) {
  return AVATAR_OPTIONS.find(item => item.id === id) || AVATAR_OPTIONS[0]
}

function readAvatarId(keys) {
  if (!storageAvailable()) return ''
  for (let i = 0; i < keys.length; i++) {
    const id = localStorage.getItem(AVATAR_STORAGE_PREFIX + keys[i])
    if (id && avatarById(id)) return id
  }
  return ''
}

function writeAvatarId(keys, avatarId) {
  if (!storageAvailable()) return
  keys.forEach(key => localStorage.setItem(AVATAR_STORAGE_PREFIX + key, avatarId))
}

function randomAvatarId() {
  return AVATAR_OPTIONS[Math.floor(Math.random() * AVATAR_OPTIONS.length)].id
}

export function getAvatarOptions() {
  return AVATAR_OPTIONS.slice()
}

export function getOrCreateUserAvatar(userOrKey) {
  const keys = userKeys(userOrKey)
  const serverAvatarId = userOrKey && typeof userOrKey === 'object' ? userOrKey.avatar_id : ''
  const id = serverAvatarId && avatarById(serverAvatarId).id === serverAvatarId
    ? serverAvatarId
    : (readAvatarId(keys) || randomAvatarId())
  writeAvatarId(keys, id)
  return avatarById(id)
}

export function setUserAvatar(userOrKey, avatarId) {
  const keys = userKeys(userOrKey)
  const avatar = avatarById(avatarId)
  writeAvatarId(keys, avatar.id)
  return avatar
}

export function assignRandomAvatarForUser(userOrKey) {
  const id = randomAvatarId()
  return setUserAvatar(userOrKey, id)
}
