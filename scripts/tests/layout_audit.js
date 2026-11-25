const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.LW_BASE_URL || 'http://localhost:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const outputDir = path.resolve(__dirname, '../../docs/layout-audit');

const pages = [
  ['home', '/'],
  ['shop', '/shop/'],
  ['cart', '/cart/'],
  ['checkout', '/checkout/'],
  ['account', '/my-account/'],
  ['auctions', '/dau-gia/'],
];

const viewports = [
  ['desktop', { width: 1440, height: 1000 }],
  ['mobile', { width: 390, height: 844, isMobile: true, hasTouch: true }],
];

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await puppeteer.launch({
    executablePath: chromePath,
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const report = [];

  for (const [viewportName, viewport] of viewports) {
    for (const [pageName, route] of pages) {
      const page = await browser.newPage();
      await page.setViewport(viewport);
      const consoleErrors = [];
      const pageErrors = [];
      page.on('console', (message) => {
        if (message.type() === 'error') consoleErrors.push(message.text());
      });
      page.on('pageerror', (error) => pageErrors.push(error.message));

      const response = await page.goto(`${baseUrl}${route}`, {
        waitUntil: 'networkidle2',
        timeout: 60000,
      });
      const metrics = await page.evaluate(() => ({
        title: document.title,
        url: location.href,
        viewportWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        bodyWidth: document.body ? document.body.scrollWidth : 0,
        h1: document.querySelector('h1')?.textContent?.trim() || '',
      }));
      const screenshot = path.join(outputDir, `${pageName}-${viewportName}.png`);
      await page.screenshot({ path: screenshot, fullPage: true });
      report.push({
        page: pageName,
        viewport: viewportName,
        route,
        status: response ? response.status() : null,
        ...metrics,
        horizontalOverflow: Math.max(metrics.scrollWidth, metrics.bodyWidth) > metrics.viewportWidth + 1,
        consoleErrors,
        pageErrors,
        screenshot,
      });
      await page.close();
    }
  }

  fs.writeFileSync(path.join(outputDir, 'report.json'), JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
