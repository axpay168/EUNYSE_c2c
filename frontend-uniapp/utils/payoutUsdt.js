/**
 * USDT 鏈上提領／綁定地址：後端 user_payout_methods 共用欄位整理
 */

export function filterUsdtPayoutItems(items) {
  var list = items || [];
  return list.filter(function (it) {
    var ch = String(it.channel_type || "").toLowerCase();
    var addr = it.payout_address && String(it.payout_address).trim();
    return ch === "usdt" && addr;
  });
}

/** 卡片標題：備註持有人 > bank_name > 預設 USDT · 網路 */
export function usdtPayoutRowTitle(it) {
  var holder = it.account_holder && String(it.account_holder).trim();
  var bn = it.bank_name && String(it.bank_name).trim();
  var net = it.usdt_network ? String(it.usdt_network).toUpperCase() : "USDT";
  if (holder) return holder + " · USDT";
  if (bn) return bn + " · " + net;
  return "USDT · " + net;
}

/**
 * @param {string} [status]
 * @param {string} [emptyFallback] 非 pending/rejected 時的後備文案（例如「不可用」或「—」）
 */
export function payoutMethodStatusLabel(status, emptyFallback) {
  var fb = emptyFallback != null ? emptyFallback : "—";
  var s = String(status || "").toLowerCase();
  if (s === "pending") return "審核中";
  if (s === "rejected") return "已駁回";
  if (!s) return fb;
  return s;
}
