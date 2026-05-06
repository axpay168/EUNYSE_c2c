var DATE_TIME_FORMAT_OPTIONS = {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hour12: false
}

function normalizeDateInput(value) {
  if (value === null || value === undefined || value === '') return null
  if (value instanceof Date) return value

  if (typeof value === 'number') {
    var millis = value > 100000000000 ? value : value * 1000
    return new Date(millis)
  }

  var raw = String(value).trim()
  if (!raw) return null
  if (/^\d+$/.test(raw)) return normalizeDateInput(Number(raw))

  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    return new Date(raw + 'T00:00:00Z')
  }

  var normalized = raw.replace(' ', 'T')
  var hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalized)
  return new Date(hasTimezone ? normalized : normalized + 'Z')
}

export function parseLocalTime(value) {
  var date = normalizeDateInput(value)
  if (!date || isNaN(date.getTime())) return null
  return date
}

export function localTimeMs(value) {
  var date = parseLocalTime(value)
  return date ? date.getTime() : 0
}

export function formatLocalTime(value, fallback, locale) {
  var date = parseLocalTime(value)
  if (!date) return fallback === undefined ? '—' : fallback

  return new Intl.DateTimeFormat(locale || undefined, DATE_TIME_FORMAT_OPTIONS).format(date)
}
