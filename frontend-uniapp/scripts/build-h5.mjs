import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const projectRoot = path.resolve(__dirname, '..')
const distBuildDir = path.join(projectRoot, 'dist', 'build')
const distDir = path.join(distBuildDir, 'h5')
const localCli = path.join(projectRoot, 'node_modules', '.bin', 'vue-cli-service')
const suppressedBuildNotices = [
  'uni-app 有新版本发布'
]

function writeFilteredOutput(value, stream) {
  if (!value) return
  const text = String(value)
  const filtered = text
    .split(/\r?\n/)
    .filter(line => !suppressedBuildNotices.some(notice => line.includes(notice)))
    .join('\n')
  if (filtered) stream.write(filtered + (text.endsWith('\n') ? '\n' : ''))
}

if (!fs.existsSync(localCli)) {
  console.error('[build:h5] Missing local vue-cli-service. Run `npm install` in frontend-uniapp first.')
  process.exit(1)
}

fs.rmSync(distBuildDir, { recursive: true, force: true })

const result = spawnSync(localCli, ['build', '--dest', distDir], {
  cwd: projectRoot,
  env: {
    ...process.env,
    NODE_ENV: 'production',
    UNI_PLATFORM: 'h5',
    UNI_CLI_CONTEXT: '.',
    UNI_INPUT_DIR: '.'
  },
  encoding: 'utf8',
  maxBuffer: 1024 * 1024 * 50
})

writeFilteredOutput(result.stderr, process.stderr)
writeFilteredOutput(result.stdout, process.stdout)

if (result.status !== 0) {
  process.exit(result.status || 1)
}

/** uni-app 產出的 index.html 未必合併 manifest 的 template.h5.html；將 Vue bundle script 插入模板後再寫回 */
function mergeH5IndexFromTemplate() {
  const templatePath = path.join(projectRoot, 'template.h5.html')
  const indexPath = path.join(distDir, 'index.html')
  if (!fs.existsSync(templatePath) || !fs.existsSync(indexPath)) return
  let tpl = fs.readFileSync(templatePath, 'utf8')
  tpl = tpl.replace(/<%= BASE_URL %>/g, '/h5/')
  tpl = tpl.replace(/<%= htmlWebpackPlugin\.options\.title %>/g, 'EURNYSE C2C')
  const built = fs.readFileSync(indexPath, 'utf8')
  const seenSrc = new Set()
  const scripts = []
  const re = /<script[^>]*\ssrc="([^"]+)"[^>]*>\s*<\/script>/gi
  let m
  while ((m = re.exec(built)) !== null) {
    const src = m[1]
    if (seenSrc.has(src)) continue
    seenSrc.add(src)
    scripts.push(`    <script src="${src}"></script>`)
  }
  const divMarker = '<div id="app"></div>'
  const divIdx = tpl.indexOf(divMarker)
  const chatIdx = tpl.indexOf('<!-- Chatwoot')
  if (divIdx === -1 || chatIdx === -1 || scripts.length === 0) {
    console.warn('[build:h5] mergeH5IndexFromTemplate: 模板或 bundle script 解析略過')
    return
  }
  const tplHeadThroughChat = tpl.slice(0, chatIdx)
  const scriptsDeduped = scripts.filter(function (line) {
    const srcMatch = line.match(/src="([^"]+)"/)
    if (!srcMatch) return true
    const src = srcMatch[1]
    if (tplHeadThroughChat.includes('src="' + src + '"')) return false
    return true
  })
  const headThroughApp =
    tpl.slice(0, divIdx + divMarker.length) +
    '\n' +
    scriptsDeduped.join('\n') +
    '\n'
  const fromChatwoot = tpl.slice(chatIdx)
  fs.writeFileSync(indexPath, headThroughApp + fromChatwoot, 'utf8')
  console.log('[build:h5] merged template.h5.html + webpack bundles → dist/build/h5/index.html')
}

mergeH5IndexFromTemplate()

fs.mkdirSync(distBuildDir, { recursive: true })
fs.copyFileSync(path.join(projectRoot, 'runtime-config.js'), path.join(distDir, 'runtime-config.js'))
fs.writeFileSync(
  path.join(distBuildDir, 'index.html'),
  '<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=h5/"><title>Redirect</title></head><body><p>Opening H5 app... <a href="h5/">continue</a></p></body></html>\n',
  'utf8'
)
