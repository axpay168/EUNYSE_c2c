const { defineConfig, devices } = require('@playwright/test')
const path = require('path')

const appDir = __dirname
const adminDir = path.join(appDir, '..', 'admin-web')

module.exports = defineConfig({
  testDir: path.join(appDir, 'e2e'),
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list']],
  timeout: 90_000,
  expect: { timeout: 15_000 },
  use: {
    baseURL: 'http://127.0.0.1:8094',
    trace: 'on-first-retry',
    viewport: { width: 430, height: 900 },
    ignoreHTTPSErrors: true
  },
  webServer: [
    {
      command: 'npm run serve:dist',
      cwd: appDir,
      url: 'http://127.0.0.1:8094/h5/index.html',
      reuseExistingServer: !process.env.CI,
      timeout: 180_000
    },
    {
      command: 'npx --yes serve@14.2.6 -l 8095 .',
      cwd: adminDir,
      url: 'http://127.0.0.1:8095/index.html',
      reuseExistingServer: !process.env.CI,
      timeout: 120_000
    }
  ],
  projects: [{ name: 'chromium', use: { ...devices['Pixel 5'] } }]
})
