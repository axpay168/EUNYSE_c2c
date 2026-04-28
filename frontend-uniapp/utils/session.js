const TOKEN_KEY = 'eurnyse_user_token'
const USER_KEY = 'eurnyse_user_profile'

export function getToken() {
  if (typeof localStorage === 'undefined') return ''
  return localStorage.getItem(TOKEN_KEY) || ''
}

export function setToken(token) {
  if (typeof localStorage === 'undefined') return
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
    return
  }
  localStorage.removeItem(TOKEN_KEY)
}

export function getStoredUser() {
  if (typeof localStorage === 'undefined') return null
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) || 'null')
  } catch (error) {
    return null
  }
}

export function setStoredUser(user) {
  if (typeof localStorage === 'undefined') return
  if (user) {
    localStorage.setItem(USER_KEY, JSON.stringify(user))
    return
  }
  localStorage.removeItem(USER_KEY)
}

export function clearSession() {
  setToken('')
  setStoredUser(null)
}

export function hasSession() {
  return Boolean(getToken())
}
