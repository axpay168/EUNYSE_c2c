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

fs.mkdirSync(distBuildDir, { recursive: true })
fs.copyFileSync(path.join(projectRoot, 'runtime-config.js'), path.join(distDir, 'runtime-config.js'))
fs.writeFileSync(
  path.join(distBuildDir, 'index.html'),
  '<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=h5/"><title>Redirect</title></head><body><p>Opening H5 app... <a href="h5/">continue</a></p></body></html>\n',
  'utf8'
)
