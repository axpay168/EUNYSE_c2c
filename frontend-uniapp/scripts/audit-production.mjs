import { spawnSync } from 'node:child_process'

const allowedAdvisories = new Set([
  // Vue 2 parseHTML ReDoS. The H5 app compiles SFC templates at build time and
  // does not use v-html or runtime template compilation with untrusted input.
  'vue:1100238'
])

function advisoryKeys(vulnerability) {
  const via = Array.isArray(vulnerability.via) ? vulnerability.via : []
  return via
    .filter(item => item && typeof item === 'object' && item.source)
    .map(item => `${vulnerability.name}:${item.source}`)
}

function isAllowed(vulnerability) {
  return advisoryKeys(vulnerability).some(key => allowedAdvisories.has(key))
}

const result = spawnSync('npm', ['audit', '--omit=dev', '--omit=optional', '--json'], {
  encoding: 'utf8',
  stdio: ['ignore', 'pipe', 'pipe']
})

let report
try {
  report = JSON.parse(result.stdout || '{}')
} catch (error) {
  console.error('Unable to parse npm audit output.')
  if (result.stderr) console.error(result.stderr)
  process.exit(1)
}

const vulnerabilities = Object.values(report.vulnerabilities || {})
const unapproved = vulnerabilities.filter(vulnerability => !isAllowed(vulnerability))

if (unapproved.length > 0) {
  console.error('Production npm audit failed. Unapproved vulnerabilities:')
  for (const vulnerability of unapproved) {
    console.error(`- ${vulnerability.name} (${vulnerability.severity}) via ${advisoryKeys(vulnerability).join(', ') || vulnerability.via.join(', ')}`)
  }
  process.exit(1)
}

if (vulnerabilities.length > 0) {
  console.log('Production npm audit passed with approved exceptions:')
  for (const vulnerability of vulnerabilities) {
    console.log(`- ${vulnerability.name} (${vulnerability.severity}) ${advisoryKeys(vulnerability).join(', ')}`)
  }
} else {
  console.log('Production npm audit passed with no vulnerabilities.')
}
