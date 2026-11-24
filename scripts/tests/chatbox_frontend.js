/**
 * Browser acceptance test for the chat widget (desktop + mobile).
 *
 *   node scripts/tests/chatbox_frontend.js
 *
 * Needs: the site at http://localhost:8000, Chrome, and puppeteer-core
 * (set PUPPETEER_CORE to its path if it is not resolvable). The AI is replaced
 * by scripts/tests/fixtures/lc-mock-provider.php (installed as a mu-plugin for
 * the run and removed afterwards), so no Gemini calls are made.
 * Screenshots go to docs/screenshots/.
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');

const puppeteer = require(process.env.PUPPETEER_CORE || 'puppeteer-core');
const ROOT = path.resolve(__dirname, '..', '..');
const SITE = process.env.SITE_URL || 'http://localhost:8000';
const PHP = process.env.PHP_BIN || 'D:/xampp/php/php.exe';
const CHROME = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const MU_DIR = path.join(ROOT, 'wordpress', 'wp-content', 'mu-plugins');
const MU_FILE = path.join(MU_DIR, 'lc-mock-provider.php');
const SHOTS = path.join(ROOT, 'docs', 'screenshots');
const API = SITE + '/wp-json/luxury-chat/v1/';

let passed = 0;
const failed = [];
function check(name, cond, detail) {
  if (cond) { passed++; console.log('  ok  ' + name); }
  else { failed.push(name); console.log('  FAIL ' + name + (detail ? ' -> ' + detail : '')); }
}
function wp(code) {
  return execFileSync(PHP, ['-r', `require '${ROOT.replace(/\\/g, '/')}/wordpress/wp-load.php'; ${code}`], { encoding: 'utf8' }).trim();
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function lastAssistant(page) {
  return page.$$eval('.lwc-msg--assistant:not([data-typing])', (n) => n.length ? n[n.length - 1].querySelector('.lwc-bubble').textContent : '');
}
async function assistantCount(page) { return page.$$eval('.lwc-msg--assistant:not([data-typing])', (n) => n.length); }
async function sendText(page, text) {
  const before = await assistantCount(page);
  await page.click('.lwc-input', { clickCount: 3 });
  await page.keyboard.press('Backspace');
  await page.type('.lwc-input', text);
  await page.keyboard.press('Enter');
  return before;
}
async function waitReply(page, before, timeout = 20000) {
  await page.waitForFunction((b) => document.querySelectorAll('.lwc-msg--assistant:not([data-typing])').length > b, { timeout }, before);
}
async function wpLogin(page, login, pass) {
  await page.goto(SITE + '/wp-login.php', { waitUntil: 'networkidle2' });
  await page.type('#user_login', login); await page.type('#user_pass', pass);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.click('#wp-submit')]);
}
async function apiHistory(page) {
  return page.evaluate(async (api) => (await (await fetch(api + 'messages', { credentials: 'same-origin' })).json()).data.messages, API);
}

(async () => {
  const startConv = parseInt(wp("global $wpdb; echo (int) $wpdb->get_var(\"SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}luxury_chat_conversations\");"), 10);
  const originalSettings = wp('echo wp_json_encode(get_option("luxury_chatbox_settings"));');
  fs.mkdirSync(MU_DIR, { recursive: true });
  fs.copyFileSync(path.join(__dirname, 'fixtures', 'lc-mock-provider.php'), MU_FILE);
  fs.mkdirSync(SHOTS, { recursive: true });
  wp('update_option("luxury_chatbox_settings", array_merge(LC_Settings::all(), ["enabled" => 1, "per_minute" => 60, "per_day" => 500]));');
  let memberId = 0;
  // WooCommerce "coming soon" mode hides store pages from visitors; a shop manager can see them.
  const managerLogin = 'lc_mgr_' + Math.random().toString(16).slice(2, 8);
  const managerPass = 'Pw-' + Math.random().toString(36).slice(2) + 'B2!';
  const managerId = parseInt(wp(`echo wp_insert_user(['user_login' => '${managerLogin}', 'user_pass' => '${managerPass}', 'role' => 'shop_manager']);`), 10);
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--no-sandbox', '--lang=vi-VN'] });
  const pageErrors = [];
  try {
    /* ================= Desktop ================= */
    console.log('Desktop 1366x860');
    const ctx = await browser.createBrowserContext();
    const page = await ctx.newPage();
    page.on('pageerror', (e) => pageErrors.push(e.message));
    await page.setViewport({ width: 1366, height: 860 });
    await page.goto(SITE + '/', { waitUntil: 'networkidle2' });
    const launcher = await page.$('.lwc-launcher');
    check('launcher labelled "Tư vấn đồng hồ" is visible', launcher && (await page.$eval('.lwc-launcher', (b) => b.textContent.trim())) === 'Tư vấn đồng hồ' && (await launcher.isIntersectingViewport()));
    await page.screenshot({ path: path.join(SHOTS, 'chatbox-desktop-closed.png') });

    await page.click('.lwc-launcher');
    await page.waitForSelector('.lwc-chip:not([disabled])', { timeout: 10000 });
    check('panel opens with title, close button and focus in the input',
      (await page.$eval('#lwc-title', (n) => n.textContent)) === 'Trợ lý LW Atelier' && !!(await page.$('.lwc-icon-btn[aria-label="Đóng cửa sổ chat"]'))
      && (await page.waitForFunction(() => document.activeElement && document.activeElement.id === 'lwc-input', { timeout: 5000 }).then(() => true, () => false)));
    check('empty state shows greeting and 3 suggestions', (await page.$$('.lwc-chip')).length === 3 && !!(await page.$('.lwc-welcome h3')));
    check('launcher reports expanded state', (await page.$eval('.lwc-launcher', (b) => b.getAttribute('aria-expanded'))) === 'true');
    await page.screenshot({ path: path.join(SHOTS, 'chatbox-desktop-welcome.png') });

    // 1. Product advice via suggestion
    let before = await assistantCount(page);
    await page.click('.lwc-chip');
    await page.waitForSelector('[data-typing]', { timeout: 3000 }).catch(() => null);
    check('typing indicator is shown while waiting', !!(await page.$('[data-typing]')) || (await assistantCount(page)) > before);
    check('send is blocked while a reply is pending', await page.$eval('.lwc-send', (b) => b.disabled));
    await waitReply(page, before);
    const cards = await page.$$eval('.lwc-msg--assistant:last-child .lwc-card:not(.lwc-card--auction)', (ns) => ns.map((n) => ({
      name: n.querySelector('.lwc-card__name').textContent, price: n.querySelector('.lwc-card__price').firstChild.textContent,
      stock: (n.querySelector('.lwc-stock') || {}).textContent, href: (n.querySelector('a') || {}).href, img: (n.querySelector('img') || {}).src })));
    check('product cards show image, name, VND price, stock and link', cards.length > 0 && cards.every((c) => c.name && /₫$/.test(c.price) && c.stock && c.href && c.img));
    const history1 = await apiHistory(page);
    const backendProducts = history1[history1.length - 1].products;
    check('cards match the backend product data (name, price)', backendProducts.length === cards.length && backendProducts.every((p, i) => p.name === cards[i].name && cards[i].price.replace(/\D/g, '') === String(p.price)));
    check('backend products respect the 30M men budget', backendProducts.every((p) => p.price <= 30000000 && p.gender === 'Nam'));

    // 2. Follow-up uses the same conversation and history
    before = await sendText(page, 'Trong các mẫu bạn vừa gợi ý, mẫu nào rẻ nhất?');
    await waitReply(page, before);
    check('follow-up answer uses the previous turn', /rẻ nhất/.test(await lastAssistant(page)));
    const history2 = await apiHistory(page);
    check('follow-up stays in the same conversation', history2.length === 4);

    // 3. Warranty policy with source link
    before = await sendText(page, 'Chính sách bảo hành của shop như thế nào?');
    await waitReply(page, before);
    const sources = await page.$$eval('.lwc-msg--assistant:last-child .lwc-sources a', (a) => a.map((x) => x.href));
    check('policy answer links to the warranty page', sources.some((h) => /chinh-sach-bao-hanh/.test(h)));

    // 4. Auction card
    before = await sendText(page, 'Phiên đấu giá Aurelius Deepsea GMT còn mở không?');
    await waitReply(page, before);
    const auction = await page.$eval('.lwc-msg--assistant:last-child .lwc-card--auction', (n) => ({
      title: n.querySelector('.lwc-card__name').textContent, state: n.querySelector('.lwc-state').textContent,
      facts: n.querySelector('.lwc-facts').textContent, href: n.querySelector('a').href })).catch(() => null);
    const snap = JSON.parse(wp('echo wp_json_encode(Luxury_Watches_Auctions::snapshot(26));'));
    check('auction card shows title, state, price, time and link from the API', auction && auction.title === snap.title && auction.state === snap.state_label
      && auction.facts.includes(new Intl.NumberFormat('vi-VN').format(snap.current_bid)) && /lúc/.test(auction.facts) && auction.href === snap.permalink);
    await page.screenshot({ path: path.join(SHOTS, 'chatbox-desktop-conversation.png') });

    // Suggested links work
    const links = [cards[0].href, sources.find((h) => /chinh-sach-bao-hanh/.test(h)), auction && auction.href];
    const codes = await Promise.all(links.map((u) => page.evaluate(async (x) => (await fetch(x, { credentials: 'same-origin' })).status, u)));
    check('suggested product, policy and auction links open (200)', codes.every((c) => c === 200), codes.join(','));

    // 5. Reload restores history
    const bubblesBefore = await page.$$eval('.lwc-msg', (n) => n.length);
    await page.reload({ waitUntil: 'networkidle2' });
    await page.waitForSelector('.lwc-msg--assistant', { timeout: 10000 });
    check('reload reopens the chat and restores the history from the backend', (await page.$$eval('.lwc-msg', (n) => n.length)) === bubblesBefore);

    // 6. Escape closes and returns focus
    await page.focus('.lwc-input');
    await page.keyboard.press('Escape');
    check('Escape closes and focus returns to the launcher', await page.evaluate(() => document.querySelector('.lwc-panel').hidden && document.activeElement === document.querySelector('.lwc-launcher')));
    await page.keyboard.press('Enter');
    await page.waitForSelector('.lwc-panel:not([hidden])');
    check('launcher opens with the keyboard', await page.$eval('.lwc-panel', (p) => !p.hidden));

    // 7. Gemini overload -> retry_after countdown -> retry with same request_id
    const posts = [];
    page.on('request', (r) => { if (r.method() === 'POST' && r.url().endsWith('/messages')) posts.push(JSON.parse(r.postData())); });
    before = await sendText(page, 'Mẫu này còn hàng không? #qua-tai');
    await page.waitForSelector('.lwc-msg--failed .lwc-error', { timeout: 10000 });
    const retryLabel = await page.$eval('.lwc-msg--failed .lwc-error .lwc-btn', (b) => ({ text: b.textContent, disabled: b.disabled }));
    check('overload shows backend message and a disabled retry with countdown', retryLabel.disabled && /Thử lại sau \d+ giây/.test(retryLabel.text)
      && /tạm gián đoạn/.test(await page.$eval('.lwc-msg--failed .lwc-error p', (p) => p.textContent)));
    check('retry button text is white on navy', await page.$eval('.lwc-msg--failed .lwc-error .lwc-btn', (b) => getComputedStyle(b).color === 'rgb(255, 255, 255)'));
    check('typed text is kept in the input after a failure', (await page.$eval('.lwc-input', (i) => i.value)) === 'Mẫu này còn hàng không? #qua-tai');
    await page.waitForFunction(() => { const b = document.querySelector('.lwc-msg--failed .lwc-error .lwc-btn'); return b && !b.disabled; }, { timeout: 8000 });
    await page.click('.lwc-msg--failed .lwc-error .lwc-btn');
    await waitReply(page, before);
    check('retry reuses the same request_id', posts.length === 2 && posts[0].request_id === posts[1].request_id);
    const h3 = await apiHistory(page);
    check('retry stores one question and one answer only', h3.filter((m) => m.content === 'Mẫu này còn hàng không? #qua-tai').length === 1 && h3.filter((m) => m.request_id === posts[0].request_id).length === 2);

    // 8. Double Enter sends once
    posts.length = 0;
    await page.click('.lwc-input', { clickCount: 3 }); await page.keyboard.press('Backspace');
    await page.type('.lwc-input', 'Shop có giao hàng toàn quốc không?');
    before = await assistantCount(page);
    await page.keyboard.press('Enter'); await page.keyboard.press('Enter'); await page.click('.lwc-send').catch(() => null);
    await waitReply(page, before);
    await sleep(300);
    check('rapid repeated send produces a single request', posts.length === 1, String(posts.length));

    // 9. Offline -> keeps text -> retry same id
    posts.length = 0;
    await page.setOfflineMode(true);
    before = await sendText(page, 'Đồng hồ nữ dưới 25 triệu');
    await page.waitForSelector('.lwc-msg--failed .lwc-error', { timeout: 8000 });
    check('offline shows a connection error', /Mất kết nối mạng/.test(await page.$eval('.lwc-msg--failed .lwc-error p', (p) => p.textContent)));
    await page.setOfflineMode(false);
    // Re-sending the same text from the input also reuses the id
    await page.focus('.lwc-input'); await page.keyboard.press('Enter');
    await waitReply(page, before);
    const h4 = await apiHistory(page);
    check('re-send after reconnecting reuses the request_id and stores one copy', h4.filter((m) => m.content === 'Đồng hồ nữ dưới 25 triệu').length === 1 && (await page.$$('.lwc-msg--failed')).length === 0);

    // 10. Invalid response, rate limit, client timeout, AI timeout (intercepted)
    await page.setRequestInterception(true);
    let mode = 'html';
    const handler = (req) => {
      if (req.method() === 'POST' && req.url().endsWith('/messages') && mode) {
        if (mode === 'html') return req.respond({ status: 502, contentType: 'text/html', body: '<html>Bad gateway</html>' });
        if (mode === 'rate') return req.respond({ status: 429, contentType: 'application/json', headers: { 'Retry-After': '5' }, body: JSON.stringify({ ok: false, data: null, error: { code: 'rate_limited', message: 'Bạn gửi tin nhắn quá nhanh. Vui lòng chờ khoảng một phút rồi thử lại.', retryable: true, retry_after: 5 } }) });
        if (mode === 'aitimeout') return req.respond({ status: 504, contentType: 'application/json', body: JSON.stringify({ ok: false, data: null, error: { code: 'ai_timeout', message: 'Trợ lý phản hồi quá lâu. Vui lòng gửi lại sau ít phút.', retryable: true, retry_after: 5 } }) });
        if (mode === 'hang') return; // never answered -> client timeout (4 s in test config)
      }
      req.continue();
    };
    page.on('request', handler);
    const cases = [
      ['html', /không đọc được/, 'invalid response shows a readable error'],
      ['rate', /quá nhanh/, 'rate limit shows backend message with countdown'],
      ['aitimeout', /quá lâu/, 'AI timeout shows backend message'],
      ['hang', /chưa phản hồi trong thời gian/, 'client timeout aborts and offers retry'],
    ];
    for (const [m, pattern, label] of cases) {
      mode = m;
      const failedBefore = (await page.$$('.lwc-msg--failed .lwc-error')).length;
      await sendText(page, 'Kiểm tra lỗi ' + m);
      await page.waitForFunction((n) => document.querySelectorAll('.lwc-msg--failed .lwc-error').length > n, { timeout: 9000 }, failedBefore);
      const [t, hasRetry] = await page.$$eval('.lwc-msg--failed .lwc-error', (els) => {
        const e = els[els.length - 1];
        return [e.textContent, /Thử lại/.test(e.querySelector('.lwc-btn').textContent)];
      });
      check(label, pattern.test(t) && hasRetry, t);
      if (m === 'rate') check('countdown uses retry_after', /Thử lại sau [45] giây/.test(t));
      await page.$eval('.lwc-input', (i) => { i.value = ''; i.dispatchEvent(new Event('input')); });
    }
    mode = null;
    page.off('request', handler);
    await page.setRequestInterception(false);

    // 11. XSS: the backend already strips markup; check that the widget is safe on its own
    before = await sendText(page, 'Thử nội dung #xss');
    await waitReply(page, before);
    check('backend strips markup before it reaches the widget', !/<script|onerror/.test(await lastAssistant(page)));
    await page.setRequestInterception(true);
    const evil = (req) => {
      if (req.method() === 'POST' && req.url().endsWith('/messages')) {
        const rid = JSON.parse(req.postData()).request_id;
        return req.respond({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, error: null, data: {
          conversation: { id: 1 }, request_id: rid, replayed: false,
          user_message: { id: 900001, role: 'user', content: 'x', status: 'completed', request_id: rid },
          reply: { id: 900002, role: 'assistant', status: 'completed', request_id: rid,
            content: '<img src=x onerror="window.__lwcXss=1"><script>window.__lwcXss=1</script>',
            products: [{ id: 1, name: '<b>Evil</b>', price: 1000, in_stock: true, url: 'javascript:window.__lwcXss=1', image: 'https://evil.example/x.png' }],
            auctions: [{ id: 2, title: 'Phiên lạ', state: 'live" onmouseover="x', state_label: 'Đang diễn ra', url: 'https://evil.example/a', accepting_bids: true, minimum_bid: 1 }],
            sources: [{ title: 'Ngoài', url: 'https://evil.example/' }, { title: 'JS', url: 'javascript:alert(1)' }],
            missing_info: false } } }) });
      }
      req.continue();
    };
    page.on('request', evil);
    before = await sendText(page, 'Kiểm tra hiển thị an toàn');
    await waitReply(page, before);
    page.off('request', evil);
    await page.setRequestInterception(false);
    const safe = await page.evaluate(() => {
      const last = [...document.querySelectorAll('.lwc-msg--assistant')].pop();
      return { executed: window.__lwcXss === 1, injected: !!last.querySelector('.lwc-bubble img, .lwc-bubble script, b'),
        text: last.querySelector('.lwc-bubble').textContent, hrefs: [...last.querySelectorAll('a')].map((a) => a.getAttribute('href')),
        imgs: [...last.querySelectorAll('img')].map((i) => i.src), stateClass: (last.querySelector('.lwc-state') || {}).className };
    });
    check('HTML in answers is shown as text and never executed', !safe.executed && !safe.injected && /<script>/.test(safe.text));
    check('external and javascript: URLs never become links or images', safe.hrefs.length === 0 && safe.imgs.length === 0, JSON.stringify(safe));
    check('unknown auction state cannot inject attributes', safe.stateClass === 'lwc-state lwc-state--other');

    // 12. Missing info
    before = await sendText(page, 'Shop có hỗ trợ trả góp không?');
    await waitReply(page, before);
    check('missing information notice is shown', !!(await page.$('.lwc-msg--assistant:last-child .lwc-missing')));

    // 13. Status: disabled vs not configured
    const statusPage = await ctx.newPage();
    await statusPage.setViewport({ width: 1366, height: 860 });
    await statusPage.setRequestInterception(true);
    let statusBody = { enabled: false, available: false };
    statusPage.on('request', (r) => r.url().endsWith('/status')
      ? r.respond({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: Object.assign({ logged_in: false, limits: { max_message_chars: 1000, per_minute: 6, per_day: 60 } }, statusBody), error: null }) })
      : r.continue());
    await statusPage.evaluateOnNewDocument(() => sessionStorage.removeItem('lwc-open'));
    await statusPage.goto(SITE + '/', { waitUntil: 'networkidle2' });
    await statusPage.click('.lwc-launcher');
    await statusPage.waitForSelector('.lwc-notice:not([hidden])', { timeout: 8000 }).catch(async (e) => {
      console.log('  debug:', JSON.stringify(await statusPage.evaluate(() => ({ panelHidden: document.querySelector('.lwc-panel').hidden, notice: document.querySelector('.lwc-notice').outerHTML, launcher: document.querySelector('.lwc-launcher').className }))));
      throw e;
    });
    check('disabled chatbot shows a "tạm tắt" notice and blocks input', /tạm tắt/.test(await statusPage.$eval('.lwc-notice', (n) => n.textContent)) && await statusPage.$eval('.lwc-input', (i) => i.disabled));
    statusBody = { enabled: true, available: false };
    await statusPage.reload({ waitUntil: 'networkidle2' });
    await statusPage.click('.lwc-launcher');
    await statusPage.waitForSelector('.lwc-notice:not([hidden])', { timeout: 8000 });
    check('unconfigured AI shows a different notice', /chưa thể trả lời/.test(await statusPage.$eval('.lwc-notice', (n) => n.textContent)) && await statusPage.$eval('.lwc-input', (i) => i.disabled));
    await statusPage.close();

    // 14. Clear with confirmation
    await page.click('.lwc-icon-btn[aria-label="Xóa hội thoại"]');
    check('clearing asks for confirmation', await page.$eval('.lwc-confirm', (c) => !c.hidden));
    await page.click('.lwc-confirm .lwc-btn--ghost');
    check('cancel keeps the conversation', (await page.$$('.lwc-msg')).length > 0);
    await page.click('.lwc-icon-btn[aria-label="Xóa hội thoại"]');
    await page.click('.lwc-confirm .lwc-btn--danger');
    await page.waitForSelector('.lwc-welcome');
    check('confirmed clear empties the chat and the backend history', (await page.$$('.lwc-msg')).length === 0 && (await apiHistory(page)).length === 0);

    // 15. New-message indicator when reading older messages
    before = await sendText(page, 'Gợi ý đồng hồ nam dưới 30 triệu');
    await waitReply(page, before);
    await page.type('.lwc-input', 'Còn mẫu nào thể thao hơn không?');
    await page.keyboard.press('Enter');
    await page.$eval('.lwc-log', (l) => { l.style.scrollBehavior = 'auto'; l.scrollTop = 0; });
    await page.waitForFunction(() => document.querySelectorAll('.lwc-msg--assistant:not([data-typing])').length >= 2, { timeout: 15000 });
    await sleep(200);
    const indicator = await page.$eval('.lwc-newmsg', (b) => !b.hidden);
    check('reading older messages shows a "Có tin nhắn mới" indicator', indicator);
    await ctx.close();

    /* ================= Member ================= */
    console.log('Logged-in member');
    const login = 'lc_ui_' + Math.random().toString(16).slice(2, 8);
    const pass = 'Pw-' + Math.random().toString(36).slice(2) + 'A1!';
    memberId = parseInt(wp(`echo wp_insert_user(['user_login' => '${login}', 'user_pass' => '${pass}', 'role' => 'customer']);`), 10);
    const mctx = await browser.createBrowserContext();
    const mpage = await mctx.newPage();
    await mpage.setViewport({ width: 1280, height: 800 });
    await mpage.goto(SITE + '/wp-login.php', { waitUntil: 'networkidle2' });
    await mpage.type('#user_login', login); await mpage.type('#user_pass', pass);
    await Promise.all([mpage.waitForNavigation({ waitUntil: 'networkidle2' }), mpage.click('#wp-submit')]);
    await mpage.goto(SITE + '/', { waitUntil: 'networkidle2' });
    await mpage.click('.lwc-launcher');
    await mpage.waitForSelector('.lwc-chip:not([disabled])');
    before = await assistantCount(mpage);
    await mpage.click('.lwc-chip:nth-of-type(1)');
    await waitReply(mpage, before);
    const owner = wp(`global $wpdb; echo (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}luxury_chat_conversations WHERE user_id = %d", ${memberId}));`);
    check('member chat is stored on the member account (REST nonce works)', owner === '1');
    await mctx.close();

    /* ================= Mobile ================= */
    console.log('Mobile 390x844');
    const mob = await browser.createBrowserContext();
    const m = await mob.newPage();
    m.on('pageerror', (e) => pageErrors.push(e.message));
    await m.emulate({ viewport: { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
      userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' });
    await m.goto(SITE + '/', { waitUntil: 'networkidle2' });
    await sleep(900);
    const geo = await m.evaluate(() => {
      const l = document.querySelector('.lwc-launcher').getBoundingClientRect();
      const bar = document.querySelector('.storefront-handheld-footer-bar');
      const b = bar ? bar.getBoundingClientRect() : null;
      return { launcherBottom: l.bottom, launcherTop: l.top, barTop: b && b.height ? b.top : null, vw: innerWidth, right: l.right };
    });
    check('mobile launcher sits above the bottom navigation bar', geo.barTop === null || geo.launcherBottom <= geo.barTop, JSON.stringify(geo));
    check('mobile launcher stays on screen', geo.right <= geo.vw && geo.launcherTop > 0);
    check('launcher label is light text on the dark button', await m.$eval('.lwc-launcher span', (s) => getComputedStyle(s).color === 'rgb(255, 255, 255)'));
    await m.screenshot({ path: path.join(SHOTS, 'chatbox-mobile-closed.png') });

    // Product page: launcher steps aside when it would cover the add-to-cart button
    await wpLogin(m, managerLogin, managerPass);
    await m.goto(SITE + '/product/ocean-gmt-24/', { waitUntil: 'networkidle2' });
    const dodge = await m.evaluate(async () => {
      const btn = document.querySelector('.single_add_to_cart_button');
      const l = document.querySelector('.lwc-launcher');
      if (!btn) return 'no-button';
      const lr = l.getBoundingClientRect();
      window.scrollBy(0, btn.getBoundingClientRect().top - (lr.top + 4));
      await new Promise((r) => setTimeout(r, 900)); // scroll event + fade-out transition
      const b = btn.getBoundingClientRect(), r2 = l.getBoundingClientRect();
      const overlaps = b.left < r2.right && b.right > r2.left && b.top < r2.bottom && b.bottom > r2.top;
      return { overlaps, dodging: l.classList.contains('is-dodging'), visibility: getComputedStyle(l).visibility };
    });
    check('launcher hides while it would cover the add-to-cart button', dodge !== 'no-button' && (!dodge.overlaps || (dodge.dodging && dodge.visibility === 'hidden')), JSON.stringify(dodge));

    await m.goto(SITE + '/', { waitUntil: 'networkidle2' });
    await m.tap('.lwc-launcher');
    await m.waitForSelector('.lwc-chip:not([disabled])');
    const sheet = await m.evaluate(() => { const r = document.querySelector('.lwc-panel').getBoundingClientRect(); return { w: r.width, h: r.height, top: r.top, vw: innerWidth, vh: innerHeight, vvh: visualViewport.height, locked: document.body.classList.contains('lwc-locked') }; });
    check('mobile chat fills the screen and follows the visual viewport', Math.abs(sheet.w - sheet.vw) < 2 && Math.abs(sheet.h - sheet.vvh) < 2 && sheet.locked, JSON.stringify(sheet));
    await m.screenshot({ path: path.join(SHOTS, 'chatbox-mobile-welcome.png') });
    before = await assistantCount(m);
    await m.tap('.lwc-chip');
    await waitReply(m, before);
    // Simulate the on-screen keyboard shrinking the visual viewport
    await m.evaluate(() => { const r = document.querySelector('[data-lwc-root]'); r.style.setProperty('--lwc-vh', '460px'); });
    const kb = await m.evaluate(() => { const f = document.querySelector('.lwc-form').getBoundingClientRect(); return { formBottom: f.bottom }; });
    check('composer stays visible when the keyboard reduces the viewport', kb.formBottom <= 461, JSON.stringify(kb));
    await m.evaluate(() => { document.querySelector('[data-lwc-root]').style.setProperty('--lwc-vh', visualViewport.height + 'px'); });
    await m.screenshot({ path: path.join(SHOTS, 'chatbox-mobile-conversation.png') });
    await m.tap('.lwc-icon-btn[aria-label="Đóng cửa sổ chat"]');
    check('closing on mobile restores page scroll and the launcher', await m.evaluate(() => !document.body.classList.contains('lwc-locked') && !document.querySelector('.lwc-launcher').hidden));
    await mob.close();

    /* ================= Storefront regression with widget ================= */
    console.log('Storefront flows with the widget');
    const s = await browser.createBrowserContext();
    const sp = await s.newPage();
    sp.on('pageerror', (e) => pageErrors.push(e.message));
    await sp.setViewport({ width: 1366, height: 860 });
    await wpLogin(sp, managerLogin, managerPass);
    await sp.goto(SITE + '/product/ocean-gmt-24/', { waitUntil: 'networkidle2' });
    await Promise.all([sp.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => null), sp.click('.single_add_to_cart_button')]);
    await sp.goto(SITE + '/cart/', { waitUntil: 'networkidle2' });
    check('add to cart still works (item in cart)', (await sp.$$('.woocommerce-cart-form .cart_item, .wc-block-cart-items__row')).length > 0);
    await sp.goto(SITE + '/checkout/', { waitUntil: 'networkidle2' });
    check('checkout page loads with the order form', !!(await sp.$('form.checkout, .wc-block-checkout')));
    const res = await sp.goto(SITE + '/my-account/', { waitUntil: 'networkidle2' });
    check('my-account page loads', res.status() === 200 && !!(await sp.$('.woocommerce')));
    await sp.goto(SITE + '/phien-dau-gia/aurelius-deepsea-gmt/', { waitUntil: 'networkidle2' });
    check('auction page renders its bid panel', !!(await sp.$('.lwc-launcher')) && !!(await sp.$('.lw-auction-bid-panel')));
    await s.close();

    check('no JavaScript errors on any page', pageErrors.length === 0, pageErrors.join(' | '));
  } catch (e) {
    failed.push('exception');
    console.log('  FAIL exception: ' + (e && e.stack || e));
  } finally {
    await browser.close();
    try { fs.unlinkSync(MU_FILE); } catch (e) { /* already gone */ }
    try { fs.rmdirSync(MU_DIR); } catch (e) { /* not empty or gone */ }
    wp(`global $wpdb; foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}luxury_chat_conversations WHERE id > %d", ${startConv})) as $id) LC_Repository::delete_conversation($id); update_option("luxury_chatbox_settings", json_decode('${originalSettings.replace(/'/g, "\\'")}', true));`);
    for (const uid of [memberId, managerId]) if (uid) wp(`require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user(${uid});`);
    console.log(`\n${passed} passed, ${failed.length} failed`);
    process.exit(failed.length ? 1 : 0);
  }
})();
