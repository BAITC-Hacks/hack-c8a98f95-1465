import { defineConfig, devices } from '@playwright/test'

const port = Number(process.env.E2E_PORT || 5174)
const deployedURL = process.env.E2E_BASE_URL?.replace(/\/+$/, '')
const baseURL = deployedURL || 'http://127.0.0.1:' + port
const managedBackend = !deployedURL && !process.env.E2E_API_URL
const apiURL = process.env.E2E_API_URL || (deployedURL ? deployedURL + '/api' : 'http://127.0.0.1:18082/api')
process.env.E2E_API_URL = apiURL
process.env.E2E_PORT = String(port)

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 45000,
  use: {
    baseURL,
    ...devices['Desktop Chrome'],
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: deployedURL ? [] : [
    ...(managedBackend ? [{
      command: 'node scripts/e2e-backend.mjs',
      url: apiURL + '/health',
      timeout: 30000,
      reuseExistingServer: false,
    }] : []),
    {
      command: 'node node_modules/vite/bin/vite.js --host 127.0.0.1 --port ' + port,
      url: baseURL,
      env: { VITE_API_URL: apiURL },
      reuseExistingServer: false,
      timeout: 30000,
    },
  ],
})
