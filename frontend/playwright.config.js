import { defineConfig, devices } from '@playwright/test'

const port = Number(process.env.E2E_PORT || 5173)
const baseURL = 'http://127.0.0.1:' + port

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
  webServer: {
    command: 'npm run dev -- --port ' + port,
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 30000,
  },
})
