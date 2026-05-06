const TOKEN_KEY = 'eurforex_user_token'
const USER_KEY = 'eurforex_user_profile'

function getUniStorage() {
  try {
    if (typeof uni !== 'undefined') return uni
  } catch (error) {}
  try {
    if (typeof window !== 'undefined' && window.uni) return window.uni
  } catch (error) {}
  return null
}

function getLocalStorage() {
  try {
    if (typeof window !== 'undefined' && window.localStorage) return window.localStorage
  } catch (error) {}
  try {
    if (typeof localStorage !== 'undefined') return localStorage
  } catch (error) {}
  return null
}

function readUniStorage(key) {
  try {
    const api = getUniStorage()
    if (api && typeof api.getStorageSync === 'function') {
      const value = api.getStorageSync(key)
      return value == null ? '' : String(value)
    }
  } catch (error) {}
  return ''
}

function writeUniStorage(key, value) {
  try {
    const api = getUniStorage()
    if (!api) return
    if (value) {
      if (typeof api.setStorageSync === 'function') api.setStorageSync(key, value)
      return
    }
    if (typeof api.removeStorageSync === 'function') api.removeStorageSync(key)
  } catch (error) {}
}

function readLocalStorage(key) {
  try {
    const storage = getLocalStorage()
    return storage ? (storage.getItem(key) || '') : ''
  } catch (error) {
    return ''
  }
}

function writeLocalStorage(key, value) {
  try {
    const storage = getLocalStorage()
    if (!storage) return
    if (value) {
      storage.setItem(key, value)
      return
    }
    storage.removeItem(key)
  } catch (error) {}
}

function readStorage(key) {
  const uniValue = readUniStorage(key)
  if (uniValue) {
    writeLocalStorage(key, uniValue)
    return uniValue
  }

  const localValue = readLocalStorage(key)
  if (localValue) writeUniStorage(key, localValue)
  return localValue
}

function writeStorage(key, value) {
  writeUniStorage(key, value)
  writeLocalStorage(key, value)
}

export function getToken() {
  return readStorage(TOKEN_KEY)
}

export function setToken(token) {
  writeStorage(TOKEN_KEY, token || '')
}

export function getStoredUser() {
  try {
    return JSON.parse(readStorage(USER_KEY) || 'null')
  } catch (error) {
    return null
  }
}

export function setStoredUser(user) {
  if (user) {
    writeStorage(USER_KEY, JSON.stringify(user))
    return
  }
  writeStorage(USER_KEY, '')
}

export function clearSession() {
  setToken('')
  setStoredUser(null)
}

export function hasSession() {
  return Boolean(getToken())
}
