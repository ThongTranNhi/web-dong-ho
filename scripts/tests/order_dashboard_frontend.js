const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.LW_BASE_URL || 'http://localhost:8000';
const username = process.env.LW_ADMIN_USER;
const password = process.env.LW_ADMIN_PASSWORD;
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const outputDir = path.resolve(__dirname, '../../docs/screenshots');

if (!username || !password) {
  console.error('Set LW_ADMIN_USER and LW_ADMIN_PASSWORD before running this test.');
  process.exit(1);
}

const check = (condition, message) => {
  if (!condition) throw new Error(message);
  console.log(`  ok  ${message}`);
};

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await puppeteer.launch({ executablePath: chromePath, headless: 'new', args: ['--no-sandbox'] });
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));

  await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle2' });
  await page.type('#user_login', username);
  await page.type('#user_pass', password);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('#wp-submit')]);

  for (const [name, viewport] of [['desktop', { width: 1440, height: 1000 }], ['mobile', { width: 390, height: 844, isMobile: true, hasTouch: true }]]) {
    await page.setViewport(viewport);
    await page.goto(`${baseUrl}/quan-tri-dau-gia/?view=orders`, { waitUntil: 'networkidle2' });
    check(await page.$eval('body', body => body.textContent.includes('Đơn hàng WooCommerce')), `${name}: order list is visible`);
    check(await page.$eval('html', html => html.scrollWidth <= html.clientWidth + 1), `${name}: page has no horizontal overflow`);
    await page.screenshot({ path: path.join(outputDir, `order-dashboard-list-${name}.png`), fullPage: true });

    const detailUrl = await page.$eval('a[href*="view=order&order_id="]', link => link.href);
    await page.goto(detailUrl, { waitUntil: 'networkidle2' });
    check(await page.$eval('body', body => body.textContent.includes('Xử lý đơn hàng')), `${name}: order detail actions are visible`);
    check(Boolean(await page.$('textarea[name="order[reason]"][required]')), `${name}: reason is required`);
    check(Boolean(await page.$('button[value="mark_paid"]')), `${name}: mark-paid action is present`);
    check(await page.$eval('#order-new-status', select => !select.required), `${name}: mark-paid is not blocked by an unrelated status selection`);
    check(await page.$eval('html', html => html.scrollWidth <= html.clientWidth + 1), `${name}: detail has no horizontal overflow`);
    await page.screenshot({ path: path.join(outputDir, `order-dashboard-detail-${name}.png`), fullPage: true });
  }

  check(errors.length === 0, 'no JavaScript page errors');
  console.log('PASS: order dashboard frontend desktop and mobile.');
  await browser.close();
})().catch(error => {
  console.error(error);
  process.exit(1);
});
