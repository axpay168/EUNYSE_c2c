import { getApiBaseUrl } from '@/config/runtime'
import { getStoredLang } from '@/common/langStorage'
import { clearSession, getToken, setStoredUser, setToken } from '@/utils/session'

function getDeviceTimezone() {
  try {
    if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
      const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
      return typeof timezone === 'string' ? timezone.trim() : ''
    }
  } catch (error) {
    void error
  }
  return ''
}

function buildUrl(path) {
  if (/^https?:\/\//i.test(path)) return path
  return getApiBaseUrl() + (path.charAt(0) === '/' ? path : '/' + path)
}

const API_ERROR_MESSAGES = {
  'zh-Hant': {
    AUTH_INVALID_PARAMS: '請確認輸入資料是否完整',
    AUTH_ACCOUNT_INCORRECT: '帳號不存在或輸入錯誤',
    AUTH_ACCOUNT_LOCKED: '帳號已被鎖定，請聯繫客服',
    AUTH_PASSWORD_INCORRECT: '密碼錯誤',
    AUTH_PASSWORD_TOO_SHORT: '密碼至少需要 6 位字元',
    AUTH_TOKEN_INVALID: '登入狀態已失效，請重新登入',
    AUTH_TOKEN_EXPIRED: '登入狀態已過期，請重新登入',
    AUTH_UNAUTHORIZED: '請先登入',
    INVITE_CODE_REQUIRED: '請輸入邀請碼',
    INVITATION_CODE_REQUIRED: '請輸入邀請碼',
    INVITATION_CODE_INVALID: '邀請碼錯誤或不存在',
    AUTH_INVITATION_REQUIRED: '請輸入邀請碼',
    AUTH_INVITATION_INVALID: '邀請碼錯誤或不存在',
    AUTH_EMAIL_INVALID: 'Email 格式不正確',
    AUTH_EMAIL_EXISTS: '此 Email 已被註冊',
    AUTH_MOBILE_INVALID: '手機號碼格式不正確',
    AUTH_MOBILE_EXISTS: '此手機號碼已被註冊',
    AUTH_WALLET_BALANCE_INSUFFICIENT: '錢包餘額不足',
    AUTH_NOT_FOUND: '資料不存在',
    AUTH_INVALID_AMOUNT: '金額不正確',
    AUTH_INVALID_PAYOUT_CHANNEL: '收款通道不正確',
    AUTH_KYC_REQUIRED: '請先完成實名認證',
    AUTH_INVALID_FILE: '上傳檔案不正確',
    AUTH_ORDER_CREATE_FAILED: '建立訂單失敗，請稍後再試',
    AUTH_TIER_DAILY_SELL_LIMIT_REACHED: '日賣出訂單次數已達目前等級限制',
    AUTH_FINANCIAL_SUBSCRIBE_FAILED: '理財申購失敗，請稍後再試'
  },
  'zh-Hans': {
    AUTH_INVALID_PARAMS: '请确认输入资料是否完整',
    AUTH_ACCOUNT_INCORRECT: '账号不存在或输入错误',
    AUTH_ACCOUNT_LOCKED: '账号已被锁定，请联系客服',
    AUTH_PASSWORD_INCORRECT: '密码错误',
    AUTH_PASSWORD_TOO_SHORT: '密码至少需要 6 位字符',
    AUTH_TOKEN_INVALID: '登录状态已失效，请重新登录',
    AUTH_TOKEN_EXPIRED: '登录状态已过期，请重新登录',
    AUTH_UNAUTHORIZED: '请先登录',
    INVITE_CODE_REQUIRED: '请输入邀请码',
    INVITATION_CODE_REQUIRED: '请输入邀请码',
    INVITATION_CODE_INVALID: '邀请码错误或不存在',
    AUTH_INVITATION_REQUIRED: '请输入邀请码',
    AUTH_INVITATION_INVALID: '邀请码错误或不存在',
    AUTH_EMAIL_INVALID: 'Email 格式不正确',
    AUTH_EMAIL_EXISTS: '此 Email 已被注册',
    AUTH_MOBILE_INVALID: '手机号格式不正确',
    AUTH_MOBILE_EXISTS: '此手机号已被注册',
    AUTH_WALLET_BALANCE_INSUFFICIENT: '钱包余额不足',
    AUTH_NOT_FOUND: '资料不存在',
    AUTH_INVALID_AMOUNT: '金额不正确',
    AUTH_INVALID_PAYOUT_CHANNEL: '收款通道不正确',
    AUTH_KYC_REQUIRED: '请先完成实名认证',
    AUTH_INVALID_FILE: '上传文件不正确',
    AUTH_ORDER_CREATE_FAILED: '建立订单失败，请稍后再试',
    AUTH_TIER_DAILY_SELL_LIMIT_REACHED: '日卖出订单次数已达当前等级限制',
    AUTH_FINANCIAL_SUBSCRIBE_FAILED: '理财申购失败，请稍后再试'
  },
  eng: {
    AUTH_INVALID_PARAMS: 'Please check that all required information is complete',
    AUTH_ACCOUNT_INCORRECT: 'Account does not exist or is incorrect',
    AUTH_ACCOUNT_LOCKED: 'Account is locked. Please contact support',
    AUTH_PASSWORD_INCORRECT: 'Incorrect password',
    AUTH_PASSWORD_TOO_SHORT: 'Password must be at least 6 characters',
    AUTH_TOKEN_INVALID: 'Login session is invalid. Please sign in again',
    AUTH_TOKEN_EXPIRED: 'Login session expired. Please sign in again',
    AUTH_UNAUTHORIZED: 'Please sign in first',
    INVITE_CODE_REQUIRED: 'Please enter an invitation code',
    INVITATION_CODE_REQUIRED: 'Please enter an invitation code',
    INVITATION_CODE_INVALID: 'Invitation code is invalid or does not exist',
    AUTH_INVITATION_REQUIRED: 'Please enter an invitation code',
    AUTH_INVITATION_INVALID: 'Invitation code is invalid or does not exist',
    AUTH_EMAIL_INVALID: 'Invalid email format',
    AUTH_EMAIL_EXISTS: 'This email is already registered',
    AUTH_MOBILE_INVALID: 'Invalid mobile number format',
    AUTH_MOBILE_EXISTS: 'This mobile number is already registered',
    AUTH_WALLET_BALANCE_INSUFFICIENT: 'Insufficient wallet balance',
    AUTH_NOT_FOUND: 'Record not found',
    AUTH_INVALID_AMOUNT: 'Invalid amount',
    AUTH_INVALID_PAYOUT_CHANNEL: 'Invalid payout channel',
    AUTH_KYC_REQUIRED: 'Please complete KYC verification first',
    AUTH_INVALID_FILE: 'Invalid uploaded file',
    AUTH_ORDER_CREATE_FAILED: 'Failed to create order. Please try again later',
    AUTH_TIER_DAILY_SELL_LIMIT_REACHED: 'Daily sell order count has reached the limit for your current tier',
    AUTH_FINANCIAL_SUBSCRIBE_FAILED: 'Financial subscription failed. Please try again later'
  }
}

function currentLang() {
  const lang = getStoredLang()
  return API_ERROR_MESSAGES[lang] ? lang : 'eng'
}

function messageFromPayload(payload) {
  const code = payload && payload.error_code
  const lang = currentLang()
  const messages = API_ERROR_MESSAGES[lang] || API_ERROR_MESSAGES['zh-Hant']
  if (code && messages[code]) return messages[code]
  if (lang === 'eng') return (payload && payload.msg) || 'Request failed'
  return '操作失敗，請稍後再試'
}

function parseResponse(response) {
  return response.json().catch(() => ({})).then(payload => {
    if (!response.ok || Number(payload.code) !== 1) {
      const error = new Error(messageFromPayload(payload))
      error.payload = payload
      error.status = response.status
      throw error
    }
    return payload.data || {}
  })
}

export function apiRequest(path, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {})
  }
  const timezone = getDeviceTimezone()
  if (timezone && !headers['X-Timezone'] && !headers['x-timezone']) headers['X-Timezone'] = timezone
  const token = getToken()
  if (token) headers.Authorization = 'Bearer ' + token

  return fetch(buildUrl(path), {
    method: options.method || 'GET',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined
  }).then(parseResponse).catch(error => {
    const code = error && error.payload && error.payload.error_code
    if (code === 'AUTH_TOKEN_INVALID' || code === 'AUTH_TOKEN_EXPIRED' || code === 'AUTH_UNAUTHORIZED') {
      clearSession()
    }
    throw error
  })
}

export function uploadFile(file, category = 'general') {
  const token = getToken()
  const headers = {}
  if (token) headers.Authorization = 'Bearer ' + token
  const form = new FormData()
  form.append('file', file)
  form.append('category', category)
  return fetch(buildUrl('/api/user/uploads'), {
    method: 'POST',
    headers,
    body: form
  }).then(parseResponse).catch(error => {
    const code = error && error.payload && error.payload.error_code
    if (code === 'AUTH_TOKEN_INVALID' || code === 'AUTH_TOKEN_EXPIRED' || code === 'AUTH_UNAUTHORIZED') {
      clearSession()
    }
    throw error
  })
}

export function login(account, password, lang = getStoredLang()) {
  return apiRequest('/api/user/login', {
    method: 'POST',
    body: { account, password, lang }
  }).then(persistUserAuthSession)
}

function persistUserAuthSession(data) {
  const userinfo = (data && data.userinfo) || {}
  const token = userinfo.token || (data && data.token)
  if (token) setToken(token)
  return me().catch(() => {
    const user = (data && (data.userInfo || data.user)) || userinfo
    if (user) setStoredUser(user)
    return user
  })
}

export function register(username, password, invitationCode, lang = getStoredLang()) {
  return apiRequest('/api/user/register', {
    method: 'POST',
    body: {
      username,
      password,
      invitation_code: invitationCode,
      lang
    }
  }).then(persistUserAuthSession)
}

export function me() {
  return apiRequest('/api/user/me').then(data => {
    setStoredUser(data.user)
    return data.user
  })
}

export function updateAvatar(avatarId) {
  return apiRequest('/api/user/avatar', {
    method: 'PATCH',
    body: { avatar_id: avatarId }
  }).then(data => {
    if (data.user) setStoredUser(data.user)
    return data.user
  })
}

export function updateAvatarUrl(avatarUrl) {
  return apiRequest('/api/user/avatar', {
    method: 'PATCH',
    body: { avatar_url: avatarUrl }
  }).then(data => {
    if (data.user) setStoredUser(data.user)
    return data.user
  })
}

export function logout() {
  return apiRequest('/api/user/logout', { method: 'POST' }).finally(() => clearSession())
}

export function changePassword(currentPassword, newPassword) {
  return apiRequest('/api/user/password', {
    method: 'PATCH',
    body: {
      current_password: currentPassword,
      new_password: newPassword
    }
  })
}

export const userApi = {
  /**
   * @param {{ deposit_network?: string }} [params] 可選；有值時查詢對應鏈的 USDT 充值地址（與後端 deposit_network 一致）
   */
  appConfig: (params) => {
    let path = '/api/user/app-config'
    if (params && typeof params === 'object') {
      const net = String(params.deposit_network || '').trim()
      if (net && /^[A-Z0-9_-]{2,24}$/i.test(net)) {
        path += '?deposit_network=' + encodeURIComponent(net.toUpperCase())
      }
    }
    return apiRequest(path)
  },
  uploadFile,
  updateAvatar,
  updateAvatarUrl,
  overview: () => apiRequest('/api/user/overview'),
  homeSnapshot: () => apiRequest('/api/user/home-snapshot'),
  listings: query => apiRequest('/api/user/listings' + (query || '')),
  listingDetail: id => apiRequest('/api/user/listings/' + id),
  createOrder: body => apiRequest('/api/user/orders', { method: 'POST', body }),
  cancelOrder: (id, reason) => apiRequest('/api/user/orders/' + id + '/cancel', { method: 'POST', body: { reason: reason || 'user_cancelled' } }),
  orders: query => apiRequest('/api/user/orders' + (query || '')),
  ordersSummary: () => apiRequest('/api/user/orders/summary'),
  orderDetail: id => apiRequest('/api/user/orders/' + id),
  fundRecords: query => apiRequest('/api/user/fund-records' + (query || '')),
  depositRequests: query => apiRequest('/api/user/deposit-requests' + (query || '')),
  createDepositRequest: body => apiRequest('/api/user/deposit-requests', { method: 'POST', body }),
  depositRequestDetail: id => apiRequest('/api/user/deposit-requests/' + id),
  withdrawalRequests: query => apiRequest('/api/user/withdrawal-requests' + (query || '')),
  createWithdrawalRequest: body => apiRequest('/api/user/withdrawal-requests', { method: 'POST', body }),
  withdrawalRequestDetail: id => apiRequest('/api/user/withdrawal-requests/' + id),
  financialProducts: () => apiRequest('/api/user/financial-products'),
  financialSubscriptions: () => apiRequest('/api/user/financial-subscriptions'),
  createFinancialSubscription: body => apiRequest('/api/user/financial-subscriptions', { method: 'POST', body }),
  kycLatest: () => apiRequest('/api/user/kyc-applications/latest'),
  submitKyc: body => apiRequest('/api/user/kyc-applications', { method: 'POST', body }),
  payoutMethods: () => apiRequest('/api/user/payout-methods'),
  createPayoutMethod: body => apiRequest('/api/user/payout-methods', { method: 'POST', body }),
  inviteTeam: () => apiRequest('/api/user/invite-team'),
  tradeFeed: query => apiRequest('/api/user/trade-feed-events' + (query || '')),
  /** CoinDesk 最新加密新聞，依目前語言取對應站點（後端快取約 6 小時，無需登入） */
  coindeskNews: lang => apiRequest('/api/public/coindesk-news?lang=' + encodeURIComponent(lang || getStoredLang())),
  coindeskZhNews: () => apiRequest('/api/public/coindesk-news?lang=zh-Hant'),
  /** 首頁行情快照（後端代理/快取，避免瀏覽器直接打第三方 API 造成 CORS） */
  marketSnapshot: () => apiRequest('/api/public/market-snapshot')
}
