import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const index = path.join(__dirname, '..', 'dist', 'build', 'h5', 'index.html')

if (!fs.existsSync(index)) {
  console.error('[ensure-h5-dist] Missing dist/build/h5/index.html — run `npm run build:h5` in frontend-uniapp first.')
  process.exit(1)
}
