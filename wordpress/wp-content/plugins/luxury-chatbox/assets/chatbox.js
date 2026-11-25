/**
 * Luxury Watches chat widget.
 * Talks only to this site's REST API (window.LWChatConfig.restUrl).
 * All message text is rendered with textContent; links are built only from
 * backend URL fields that point to this site.
 */
(function () {
  'use strict';

  var cfg = window.LWChatConfig;
  var root = document.querySelector('[data-lwc-root]');
  if (!cfg || !root || root.getAttribute('data-ready')) return;
  root.setAttribute('data-ready', '1');

  // Backend AI timeout + headroom (set by PHP), so the server normally answers first.
  // wp_localize_script delivers numbers as strings.
  var configuredTimeout = parseInt(cfg.sendTimeoutMs, 10);
  var SEND_TIMEOUT_MS = configuredTimeout >= 1000 ? configuredTimeout : 75000;
  var READ_TIMEOUT_MS = 15000;
  var STATUS_TTL_MS = 60000;
  var MOBILE = window.matchMedia('(max-width: 640px)');
  var CTA_SELECTOR = '.single_add_to_cart_button, .lw-bid-form button[type="submit"], .lw-auction-login, .checkout-button, #place_order, .storefront-sticky-add-to-cart__content-button, .woocommerce-mini-cart__buttons a';
  var NON_RETRYABLE = ['invalid_request_id', 'empty_message', 'message_too_long', 'request_id_conflict', 'ai_refused', 'ai_bad_request', 'ai_quota_exceeded', 'chatbot_disabled', 'ai_not_configured', 'session_expired'];

  var CLIENT_ERRORS = {
    network_error: 'Mất kết nối mạng. Kiểm tra kết nối rồi thử lại.',
    client_timeout: 'Trợ lý chưa phản hồi trong thời gian cho phép. Bạn có thể thử lại.',
    invalid_response: 'Máy chủ trả về dữ liệu không đọc được. Bạn có thể thử lại.',
    session_expired: 'Phiên đăng nhập đã hết hạn. Tải lại trang để tiếp tục trò chuyện.'
  };
  var HISTORY_ERRORS = {
    ai_unavailable: 'Trợ lý AI đang quá tải nên chưa trả lời được tin này.',
    ai_busy: 'Trợ lý AI đang bận nên chưa trả lời được tin này.',
    ai_timeout: 'Trợ lý phản hồi quá lâu nên tin này chưa được trả lời.',
    ai_bad_response: 'Câu trả lời của trợ lý bị lỗi nên chưa hiển thị được.',
    ai_not_configured: 'Trợ lý chưa sẵn sàng khi bạn gửi tin này.',
    ai_refused: 'Trợ lý không thể trả lời tin này.'
  };
  var SUGGESTIONS = [
    ['Gợi ý đồng hồ nam dưới 30 triệu', 'Chọn đồng hồ theo ngân sách và phong cách'],
    ['Chính sách bảo hành của shop như thế nào?', 'Bảo hành, đổi trả và vận chuyển'],
    ['Phiên đấu giá nào đang mở?', 'Trạng thái, bước giá và mức trả tối thiểu']
  ];

  var state = {
    open: false,
    status: null,
    statusAt: 0,
    statusError: false,
    historyLoaded: false,
    busy: false,
    maxChars: 1000,
    lastFailed: null,      // { rid, text } of the latest retryable failure
    renderedIds: {},
    hasMessages: false,
    unread: false,
    retryTimers: []
  };

  /* ---------- DOM helpers ---------- */
  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (key) {
        var value = attrs[key];
        if (value === null || value === undefined || value === false) return;
        if (key === 'text') node.textContent = value;
        else if (key === 'className') node.className = value;
        else if (key.indexOf('on') === 0) node.addEventListener(key.slice(2), value);
        else node.setAttribute(key, value === true ? '' : String(value));
      });
    }
    (children || []).forEach(function (child) { if (child) node.appendChild(child); });
    return node;
  }
  var SVG_NS = 'http://www.w3.org/2000/svg';
  function svg(paths, viewBox, cls) {
    var s = document.createElementNS(SVG_NS, 'svg');
    s.setAttribute('viewBox', viewBox || '0 0 24 24');
    s.setAttribute('aria-hidden', 'true');
    s.setAttribute('focusable', 'false');
    if (cls) s.setAttribute('class', cls);
    paths.forEach(function (p) {
      var n = document.createElementNS(SVG_NS, p[0]);
      Object.keys(p[1]).forEach(function (k) { n.setAttribute(k, p[1][k]); });
      s.appendChild(n);
    });
    return s;
  }
  function dial(cls) {
    return svg([
      ['circle', { cx: '18', cy: '18', r: '15', fill: 'none', stroke: 'currentColor', 'stroke-width': '2' }],
      ['circle', { cx: '18', cy: '18', r: '11', fill: 'none', stroke: 'currentColor', 'stroke-width': '.8', opacity: '.55' }],
      ['path', { d: 'M18 18V10.5M18 18l5 3', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round' }],
      ['circle', { cx: '18', cy: '18', r: '1.6', fill: 'currentColor' }],
      ['rect', { x: '16.5', y: '0.5', width: '3', height: '2.5', rx: '.8', fill: 'currentColor' }]
    ], '0 0 36 36', cls || 'lwc-dial');
  }
  var ICON_BACK = [['path', { d: 'M19 12H5M12 19l-7-7 7-7', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }]];
  var ICON_CLOSE = [['path', { d: 'M6 6l12 12M18 6L6 18', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round' }]];
  var ICON_TRASH = [['path', { d: 'M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.8', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }]];
  var ICON_SEND = [['path', { d: 'M4 12l16-8-6 16-2.5-6.5L4 12z', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.8', 'stroke-linejoin': 'round' }]];

  function safeUrl(value) {
    if (typeof value !== 'string' || !value) return null;
    try {
      var url = new URL(value, window.location.href);
      if ((url.protocol === 'http:' || url.protocol === 'https:') && url.origin === window.location.origin) return url.href;
    } catch (e) { /* invalid */ }
    return null;
  }
  function isNum(v) { return typeof v === 'number' && isFinite(v); }
  var vnd = (window.Intl && Intl.NumberFormat) ? new Intl.NumberFormat('vi-VN') : null;
  function money(n) { return (vnd ? vnd.format(n) : String(n)) + ' ₫'; }
  function newRid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var bytes = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(bytes);
    return 'r' + Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }
  function storage(get, value) {
    try {
      if (get) return window.sessionStorage.getItem('lwc-open');
      if (value) window.sessionStorage.setItem('lwc-open', '1'); else window.sessionStorage.removeItem('lwc-open');
    } catch (e) { /* storage unavailable */ }
    return null;
  }

  /* ---------- Build UI ---------- */
  var unreadDot = el('span', { className: 'lwc-launcher__unread', hidden: true });
  var launcher = el('button', { type: 'button', className: 'lwc-launcher', 'aria-expanded': 'false', 'aria-controls': 'lwc-panel', 'aria-haspopup': 'dialog' },
    [dial(), el('span', { text: 'Tư vấn đồng hồ' }), unreadDot]);

  var backBtn = el('button', { type: 'button', className: 'lwc-icon-btn lwc-back-btn', 'aria-label': 'Quay lại phần trước' }, [svg(ICON_BACK)]);
  var closeBtn = el('button', { type: 'button', className: 'lwc-icon-btn', 'aria-label': 'Đóng cửa sổ chat' }, [svg(ICON_CLOSE)]);
  var clearBtn = el('button', { type: 'button', className: 'lwc-icon-btn', 'aria-label': 'Xóa hội thoại', disabled: true }, [svg(ICON_TRASH)]);
  var head = el('div', { className: 'lwc-head' }, [
    backBtn,
    dial(),
    el('div', { className: 'lwc-head__text' }, [
      el('h2', { className: 'lwc-title', id: 'lwc-title', text: 'Trợ lý LW Atelier' }),
      el('p', { className: 'lwc-subtitle', text: 'Trả lời theo dữ liệu của cửa hàng' })
    ]),
    clearBtn, closeBtn
  ]);

  var notice = el('p', { className: 'lwc-notice', role: 'status', hidden: true });
  var confirmCancel = el('button', { type: 'button', className: 'lwc-btn lwc-btn--ghost', text: 'Hủy' });
  var confirmOk = el('button', { type: 'button', className: 'lwc-btn lwc-btn--danger', text: 'Xóa hội thoại' });
  var confirmText = el('p', { text: 'Xóa toàn bộ hội thoại này? Bạn sẽ không xem lại được các tin nhắn đã gửi.' });
  var confirmBar = el('div', { className: 'lwc-confirm', role: 'alertdialog', 'aria-labelledby': 'lwc-confirm-text', hidden: true }, [confirmText, confirmOk, confirmCancel]);
  confirmText.id = 'lwc-confirm-text';

  var log = el('div', { className: 'lwc-log', role: 'log', 'aria-live': 'polite', 'aria-relevant': 'additions', 'aria-label': 'Lịch sử hội thoại', tabindex: '0' });
  var newMsgBtn = el('button', { type: 'button', className: 'lwc-newmsg', hidden: true, text: 'Có tin nhắn mới' });
  var body = el('div', { className: 'lwc-body' }, [log, newMsgBtn]);

  var input = el('textarea', { className: 'lwc-input', id: 'lwc-input', rows: '1', placeholder: 'Nhập câu hỏi của bạn', 'aria-describedby': 'lwc-hint', enterkeyhint: 'send' });
  var sendBtn = el('button', { type: 'submit', className: 'lwc-send', 'aria-label': 'Gửi tin nhắn', disabled: true }, [svg(ICON_SEND)]);
  var counter = el('span', { className: 'lwc-count', 'aria-live': 'polite' });
  var form = el('form', { className: 'lwc-form', novalidate: true }, [
    el('label', { className: 'lwc-sr', for: 'lwc-input', text: 'Tin nhắn cho trợ lý' }),
    el('div', { className: 'lwc-form__row' }, [input, sendBtn]),
    el('p', { className: 'lwc-form__hint', id: 'lwc-hint' }, [el('span', { text: 'Enter để gửi, Shift + Enter để xuống dòng' }), counter])
  ]);
  var srStatus = el('p', { className: 'lwc-sr', role: 'status', 'aria-live': 'polite' });

  var panel = el('section', { className: 'lwc-panel', id: 'lwc-panel', role: 'dialog', 'aria-labelledby': 'lwc-title', hidden: true },
    [head, notice, confirmBar, body, form, srStatus]);
  root.appendChild(launcher);
  root.appendChild(panel);

  function announce(text) {
    srStatus.textContent = '';
    window.setTimeout(function () { srStatus.textContent = text; }, 60);
  }

  /* ---------- REST ---------- */
  function api(method, path, payload) {
    var controller = window.AbortController ? new AbortController() : null;
    var timeout = method === 'POST' && path === 'messages' ? SEND_TIMEOUT_MS : READ_TIMEOUT_MS;
    var timer = controller ? window.setTimeout(function () { controller.abort(); }, timeout) : null;
    var headers = { Accept: 'application/json' };
    if (payload) headers['Content-Type'] = 'application/json';
    if (cfg.nonce) headers['X-WP-Nonce'] = cfg.nonce;
    return fetch(cfg.restUrl + path, {
      method: method, credentials: 'same-origin', headers: headers,
      body: payload ? JSON.stringify(payload) : undefined,
      signal: controller ? controller.signal : undefined
    }).then(function (res) {
      return res.json().then(function (data) {
        if (data && typeof data.ok === 'boolean') { data.httpStatus = res.status; return data; }
        if (data && data.code === 'rest_cookie_invalid_nonce') return clientError('session_expired');
        return clientError('invalid_response');
      }, function () { return clientError('invalid_response'); });
    }, function (err) {
      return clientError(err && err.name === 'AbortError' ? 'client_timeout' : 'network_error');
    }).then(function (result) {
      if (timer) window.clearTimeout(timer);
      return result;
    });
  }
  function clientError(code) {
    return { ok: false, data: null, error: { code: code, message: CLIENT_ERRORS[code], retryable: NON_RETRYABLE.indexOf(code) === -1, retry_after: 0 } };
  }

  /* ---------- Status ---------- */
  function setNotice(text, off) {
    notice.hidden = !text;
    notice.textContent = text || '';
    notice.className = 'lwc-notice' + (off ? ' lwc-notice--off' : '');
  }
  function canSend() {
    return !!(state.status && state.status.enabled && state.status.available);
  }
  function refreshStatus(force) {
    if (!force && state.status && Date.now() - state.statusAt < STATUS_TTL_MS) { applyStatus(); return Promise.resolve(); }
    return api('GET', 'status').then(function (res) {
      if (res.ok && res.data) {
        state.status = res.data; state.statusAt = Date.now(); state.statusError = false;
        if (res.data.limits && isNum(res.data.limits.max_message_chars)) state.maxChars = res.data.limits.max_message_chars;
      } else {
        state.status = null; state.statusError = true;
      }
      applyStatus();
    });
  }
  function applyStatus() {
    if (state.statusError) {
      setNotice('Chưa kiểm tra được trạng thái trợ lý. Kiểm tra kết nối mạng rồi mở lại cửa sổ chat.', false);
    } else if (state.status && !state.status.enabled) {
      setNotice('Trợ lý tư vấn đang tạm tắt. Bạn vẫn xem được lịch sử, hoặc đọc mục Câu hỏi thường gặp để được hỗ trợ.', true);
    } else if (state.status && !state.status.available) {
      setNotice('Trợ lý đang được cấu hình và chưa thể trả lời. Vui lòng quay lại sau.', true);
    } else {
      setNotice('');
    }
    input.maxLength = state.maxChars;
    input.disabled = !canSend();
    Array.prototype.forEach.call(log.querySelectorAll('.lwc-chip'), function (chip) { chip.disabled = !canSend(); });
    updateComposer();
  }

  /* ---------- Scrolling ---------- */
  function nearBottom() { return log.scrollHeight - log.scrollTop - log.clientHeight < 56; }
  function toBottom() { log.scrollTop = log.scrollHeight; newMsgBtn.hidden = true; }
  function afterAppend(wasNear, force) {
    if (force || wasNear) toBottom(); else newMsgBtn.hidden = false;
  }
  log.addEventListener('scroll', function () { if (nearBottom()) newMsgBtn.hidden = true; });
  newMsgBtn.addEventListener('click', function () { toBottom(); log.focus(); });

  /* ---------- Rendering ---------- */
  function welcome() {
    var chips = el('ul', { className: 'lwc-chips' });
    SUGGESTIONS.forEach(function (s) {
      var chip = el('button', { type: 'button', className: 'lwc-chip', disabled: !canSend() }, [document.createTextNode(s[0]), el('small', { text: s[1] })]);
      chip.addEventListener('click', function () { submitText(s[0]); });
      chips.appendChild(el('li', null, [chip]));
    });
    return el('div', { className: 'lwc-welcome', 'data-lwc-welcome': '1' }, [
      el('h3', { text: 'Xin chào, tôi là trợ lý của LW Atelier' }),
      el('p', { text: 'Tôi có thể gợi ý đồng hồ theo ngân sách, giải đáp chính sách bảo hành, đổi trả, vận chuyển và cho biết tình trạng các phiên đấu giá.' }),
      chips
    ]);
  }
  function setHasMessages(value) {
    state.hasMessages = value;
    clearBtn.disabled = !value || state.busy;
    var w = log.querySelector('[data-lwc-welcome]');
    if (value && w) w.remove();
    if (!value && !w) log.appendChild(welcome());
  }

  function userBubble(text, rid) {
    var node = el('div', { className: 'lwc-msg lwc-msg--user lwc-msg--pending', 'data-rid': rid }, [
      el('span', { className: 'lwc-sr', text: 'Bạn: ' }),
      el('div', { className: 'lwc-bubble', text: text }),
      el('span', { className: 'lwc-meta', text: 'Đang gửi' })
    ]);
    return node;
  }
  function markSent(node) {
    node.classList.remove('lwc-msg--pending', 'lwc-msg--failed');
    var meta = node.querySelector('.lwc-meta'); if (meta) meta.remove();
    var err = node.querySelector('.lwc-error'); if (err) err.remove();
  }
  function clearRetryTimers() { state.retryTimers.forEach(window.clearInterval); state.retryTimers = []; }

  function markFailed(node, text, rid, error) {
    node.classList.remove('lwc-msg--pending');
    node.classList.add('lwc-msg--failed');
    var meta = node.querySelector('.lwc-meta'); if (meta) meta.textContent = 'Chưa gửi được';
    var old = node.querySelector('.lwc-error'); if (old) old.remove();
    var retryable = error.retryable !== false && NON_RETRYABLE.indexOf(error.code) === -1;
    var actions = el('div', { className: 'lwc-error__actions' });
    if (retryable) {
      var retry = el('button', { type: 'button', className: 'lwc-btn', text: 'Thử lại' });
      retry.addEventListener('click', function () { resend(node, text, rid); });
      actions.appendChild(retry);
      var wait = isNum(error.retry_after) ? Math.min(Math.max(0, Math.round(error.retry_after)), 3600) : 0;
      if (wait > 0) {
        retry.disabled = true;
        var tick = function () {
          if (wait <= 0) { retry.disabled = false; retry.textContent = 'Thử lại'; window.clearInterval(timer); return; }
          retry.textContent = 'Thử lại sau ' + wait + ' giây';
          wait -= 1;
        };
        var timer = window.setInterval(tick, 1000);
        state.retryTimers.push(timer);
        tick();
      }
    } else if (error.code !== 'session_expired') {
      var edit = el('button', { type: 'button', className: 'lwc-btn lwc-btn--ghost', text: 'Sửa tin nhắn' });
      edit.addEventListener('click', function () { input.value = text; autosize(); updateComposer(); input.focus(); });
      actions.appendChild(edit);
    }
    var box = el('div', { className: 'lwc-error', role: 'alert' }, [el('p', { text: error.message || 'Chưa gửi được tin nhắn. Bạn có thể thử lại.' }), actions]);
    node.appendChild(box);
  }

  function productCard(p) {
    if (!p || !isNum(p.id) || typeof p.name !== 'string' || !isNum(p.price)) return null;
    var link = safeUrl(p.url);
    var image = safeUrl(p.image);
    var price = el('p', { className: 'lwc-card__price', text: money(p.price) });
    if (p.on_sale && isNum(p.regular_price) && p.regular_price > p.price) price.appendChild(el('del', { text: money(p.regular_price) }));
    var stockText = p.in_stock === true ? (isNum(p.stock_quantity) && p.stock_quantity > 0 ? 'Còn hàng (' + p.stock_quantity + ' chiếc)' : 'Còn hàng') : (p.in_stock === false ? 'Hết hàng' : '');
    var row = el('div', { className: 'lwc-card__row' }, [
      stockText ? el('span', { className: 'lwc-stock ' + (p.in_stock ? 'lwc-stock--in' : 'lwc-stock--out'), text: stockText }) : null,
      link ? el('a', { href: link, text: 'Xem sản phẩm', 'aria-label': 'Xem sản phẩm ' + p.name }) : null
    ]);
    return el('article', { className: 'lwc-card' }, [
      image ? el('img', { src: image, alt: '', loading: 'lazy', width: '64', height: '64' }) : el('span', { className: 'lwc-card__ph', 'aria-hidden': 'true' }),
      el('div', null, [el('h4', { className: 'lwc-card__name', text: p.name }), price, row])
    ]);
  }
  function auctionCard(a) {
    if (!a || !isNum(a.id) || typeof a.title !== 'string') return null;
    var link = safeUrl(a.url);
    var stateKey = ['live', 'upcoming', 'ended'].indexOf(a.state) !== -1 ? a.state : 'other';
    var facts = el('dl', { className: 'lwc-facts' });
    function fact(label, value) { facts.appendChild(el('dt', { text: label })); facts.appendChild(el('dd', { text: value })); }
    if (isNum(a.current_bid)) fact('Giá hiện tại', money(a.current_bid));
    if (a.accepting_bids === true && isNum(a.minimum_bid)) fact('Trả tối thiểu', money(a.minimum_bid));
    if (isNum(a.bid_increment)) fact('Bước giá', money(a.bid_increment));
    if (stateKey === 'upcoming' && typeof a.starts_at === 'string') fact('Mở lúc', a.starts_at);
    if (stateKey === 'live' && typeof a.ends_at === 'string') fact('Kết thúc lúc', a.ends_at);
    if (stateKey === 'ended' && typeof a.ends_at === 'string') fact('Đã kết thúc lúc', a.ends_at);
    var linkText = a.accepting_bids === true ? 'Trả giá trên trang phiên' : 'Xem phiên đấu giá';
    return el('article', { className: 'lwc-card lwc-card--auction' }, [
      el('div', { className: 'lwc-card__row' }, [
        el('h4', { className: 'lwc-card__name', text: a.title }),
        typeof a.state_label === 'string' ? el('span', { className: 'lwc-state lwc-state--' + stateKey, text: a.state_label }) : null
      ]),
      facts,
      link ? el('a', { href: link, text: linkText, 'aria-label': linkText + ': ' + a.title }) : null
    ]);
  }

  function assistantBubble(m) {
    var node = el('div', { className: 'lwc-msg lwc-msg--assistant', 'data-id': String(m.id) }, [
      el('span', { className: 'lwc-sr', text: 'Trợ lý: ' }),
      el('div', { className: 'lwc-bubble', text: typeof m.content === 'string' ? m.content : '' })
    ]);
    var cardUrls = {};
    var cards = el('div', { className: 'lwc-cards' });
    (Array.isArray(m.products) ? m.products : []).forEach(function (p) {
      var c = productCard(p); if (c) { cards.appendChild(c); var u = safeUrl(p.url); if (u) cardUrls[u] = 1; }
    });
    (Array.isArray(m.auctions) ? m.auctions : []).forEach(function (a) {
      var c = auctionCard(a); if (c) { cards.appendChild(c); var u = safeUrl(a.url); if (u) cardUrls[u] = 1; }
    });
    if (cards.childNodes.length) node.appendChild(cards);
    var list = el('ul');
    (Array.isArray(m.sources) ? m.sources : []).forEach(function (s) {
      var u = s && safeUrl(s.url);
      if (!u || cardUrls[u] || typeof s.title !== 'string') return;
      list.appendChild(el('li', null, [el('a', { className: 'lwc-link', href: u, text: s.title })]));
    });
    if (list.childNodes.length) node.appendChild(el('div', { className: 'lwc-sources' }, [el('span', { text: 'Xem chi tiết' }), list]));
    if (m.missing_info === true) {
      node.appendChild(el('p', { className: 'lwc-missing', text: 'Một phần thông tin chưa có trên website. Bạn có thể liên hệ cửa hàng để được xác nhận.' }));
    }
    return node;
  }

  function typingNode() {
    return el('div', { className: 'lwc-msg lwc-msg--assistant', 'data-typing': '1' }, [
      el('div', { className: 'lwc-bubble lwc-typing' }, [el('span', { className: 'lwc-dots', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i')]), el('span', { text: 'Trợ lý đang soạn trả lời' })])
    ]);
  }

  function renderHistory(messages) {
    log.textContent = '';
    state.renderedIds = {};
    state.lastFailed = null;
    clearRetryTimers();
    (messages || []).forEach(function (m) {
      if (!m || typeof m.content !== 'string') return;
      if (m.role === 'user') {
        var node = userBubble(m.content, m.request_id || '');
        if (m.status === 'completed') markSent(node);
        else {
          var code = m.error_code || 'ai_unavailable';
          var retryable = NON_RETRYABLE.indexOf(code) === -1;
          markFailed(node, m.content, m.request_id, { code: code, message: HISTORY_ERRORS[code] || 'Tin nhắn này chưa được trả lời.', retryable: retryable, retry_after: 0 });
          if (retryable) state.lastFailed = { rid: m.request_id, text: m.content };
        }
        log.appendChild(node);
      } else if (m.role === 'assistant') {
        state.renderedIds[m.id] = 1;
        log.appendChild(assistantBubble(m));
      }
    });
    setHasMessages(log.childNodes.length > 0);
    toBottom();
  }

  /* ---------- Composer ---------- */
  function autosize() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight + 2, 132) + 'px';
  }
  function updateComposer() {
    var length = input.value.length;
    var text = input.value.trim();
    sendBtn.disabled = state.busy || !canSend() || !text || length > state.maxChars;
    sendBtn.setAttribute('aria-label', state.busy ? 'Đang gửi tin nhắn' : 'Gửi tin nhắn');
    counter.textContent = length > state.maxChars * 0.8 ? length + '/' + state.maxChars : '';
    counter.className = 'lwc-count' + (length > state.maxChars ? ' is-over' : '');
    clearBtn.disabled = !state.hasMessages || state.busy;
  }
  input.addEventListener('input', function () { autosize(); updateComposer(); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) {
      e.preventDefault();
      if (!sendBtn.disabled) form.requestSubmit ? form.requestSubmit() : submitText(input.value);
    }
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); submitText(input.value); });

  function submitText(raw) {
    var text = (raw || '').trim();
    if (state.busy || !text || !canSend() || text.length > state.maxChars) return;
    var rid = newRid();
    // Re-sending the same failed text keeps its request_id so the server never stores or answers it twice.
    if (state.lastFailed && state.lastFailed.text === text) {
      rid = state.lastFailed.rid;
      var old = log.querySelector('.lwc-msg--failed[data-rid="' + cssEscape(rid) + '"]');
      if (old) old.remove();
    }
    input.value = ''; autosize();
    send(text, rid, null);
  }
  function resend(node, text, rid) {
    if (state.busy || !canSend()) return;
    if (input.value.trim() === text) { input.value = ''; autosize(); }
    send(text, rid, node);
  }
  function cssEscape(value) { return window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/["\\]/g, '\\$&'); }

  function send(text, rid, existingNode) {
    state.busy = true;
    clearRetryTimers();
    updateComposer();
    setHasMessages(true);
    var node = existingNode || userBubble(text, rid);
    if (existingNode) {
      node.classList.remove('lwc-msg--failed'); node.classList.add('lwc-msg--pending');
      var err = node.querySelector('.lwc-error'); if (err) err.remove();
      var meta = node.querySelector('.lwc-meta'); if (meta) meta.textContent = 'Đang gửi';
      log.appendChild(node); // move to the end of the conversation
    } else {
      log.appendChild(node);
    }
    var typing = typingNode();
    log.appendChild(typing);
    afterAppend(true, true);

    api('POST', 'messages', { message: text, request_id: rid }).then(function (res) {
      typing.remove();
      var wasNear = nearBottom();
      if (res.ok && res.data && res.data.reply) {
        markSent(node);
        state.lastFailed = null;
        var reply = res.data.reply;
        if (!state.renderedIds[reply.id]) {
          state.renderedIds[reply.id] = 1;
          log.appendChild(assistantBubble(reply));
        }
        afterAppend(wasNear, false);
        if (!state.open) { state.unread = true; unreadDot.hidden = false; }
        announce('Trợ lý đã trả lời.');
      } else {
        var error = res.error || clientError('invalid_response').error;
        markFailed(node, text, rid, error);
        var retryable = error.retryable !== false && NON_RETRYABLE.indexOf(error.code) === -1;
        state.lastFailed = retryable ? { rid: rid, text: text } : null;
        // Keep what the customer typed so nothing is lost.
        if (!input.value.trim()) { input.value = text; autosize(); }
        afterAppend(wasNear, false);
        if (error.code === 'chatbot_disabled' || error.code === 'ai_not_configured') refreshStatus(true);
      }
    }).then(function () {
      state.busy = false;
      updateComposer();
    });
  }

  /* ---------- Clear conversation ---------- */
  clearBtn.addEventListener('click', function () {
    if (clearBtn.disabled) return;
    confirmBar.hidden = false;
    confirmText.textContent = 'Xóa toàn bộ hội thoại này? Bạn sẽ không xem lại được các tin nhắn đã gửi.';
    confirmCancel.focus();
  });
  function hideConfirm(focusTarget) { confirmBar.hidden = true; (focusTarget || clearBtn).focus(); }
  confirmCancel.addEventListener('click', function () { hideConfirm(); });
  confirmOk.addEventListener('click', function () {
    confirmOk.disabled = true;
    api('DELETE', 'messages').then(function (res) {
      confirmOk.disabled = false;
      if (res.ok) {
        log.textContent = '';
        state.renderedIds = {}; state.lastFailed = null; clearRetryTimers();
        setHasMessages(false);
        applyStatus();
        confirmBar.hidden = true;
        announce('Đã xóa hội thoại.');
        (input.disabled ? closeBtn : input).focus();
      } else {
        confirmText.textContent = (res.error && res.error.message) || 'Chưa xóa được hội thoại. Bạn có thể thử lại.';
      }
    });
  });

  /* ---------- Open / close ---------- */
  function syncViewport() {
    var vv = window.visualViewport;
    if (!vv) return;
    root.style.setProperty('--lwc-vh', vv.height + 'px');
    root.style.setProperty('--lwc-vtop', vv.offsetTop + 'px');
  }
  function openPanel(focus) {
    if (state.open) return;
    state.open = true;
    panel.hidden = false;
    panel.classList.add('is-entering');
    window.setTimeout(function () { panel.classList.remove('is-entering'); }, 260);
    launcher.hidden = true;
    launcher.setAttribute('aria-expanded', 'true');
    state.unread = false; unreadDot.hidden = true;
    if (MOBILE.matches) document.body.classList.add('lwc-locked');
    syncViewport();
    storage(false, true);
    if (!state.historyLoaded) {
      log.textContent = '';
      log.appendChild(el('p', { className: 'lwc-meta', text: 'Đang tải hội thoại' }));
    }
    var jobs = [refreshStatus(false)];
    if (!state.historyLoaded) {
      jobs.push(api('GET', 'messages').then(function (res) {
        if (res.ok && res.data) { state.historyLoaded = true; renderHistory(res.data.messages); }
        else {
          log.textContent = '';
          setHasMessages(false);
          setNotice((res.error && res.error.message) || 'Chưa tải được lịch sử hội thoại.', false);
        }
      }));
    } else {
      toBottom();
    }
    Promise.all(jobs).then(function () {
      if (state.statusError && state.historyLoaded) applyStatus();
      if (focus !== false) (input.disabled ? closeBtn : input).focus();
    });
    if (focus !== false) closeBtn.focus();
  }
  function closePanel() {
    if (!state.open) return;
    state.open = false;
    panel.hidden = true;
    confirmBar.hidden = true;
    launcher.hidden = false;
    launcher.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('lwc-locked');
    storage(false, false);
    launcher.focus();
    positionLauncher();
  }
  launcher.addEventListener('click', function () { openPanel(true); });
  backBtn.addEventListener('click', function () {
    if (state.hasMessages) {
      log.textContent = '';
      log.appendChild(welcome());
      setHasMessages(false);
      input.value = '';
      autosize();
      updateComposer();
      announce('Đã quay lại màn hình gợi ý');
    } else {
      closePanel();
    }
  });
  closeBtn.addEventListener('click', closePanel);
  panel.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      if (!confirmBar.hidden) hideConfirm(); else closePanel();
      return;
    }
    if (e.key === 'Tab' && MOBILE.matches) { // full-screen on phones: keep focus inside
      var focusables = Array.prototype.filter.call(panel.querySelectorAll('button, [href], textarea, [tabindex="0"]'), function (n) { return !n.disabled && n.offsetParent !== null; });
      if (!focusables.length) return;
      var first = focusables[0], last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* ---------- Keep the launcher clear of page controls ---------- */
  var rafPending = false;
  function positionLauncher() {
    if (rafPending) return;
    rafPending = true;
    window.requestAnimationFrame(function () {
      rafPending = false;
      var bottom = MOBILE.matches ? 14 : 24;
      var bar = document.querySelector('.storefront-handheld-footer-bar');
      if (bar) {
        var r = bar.getBoundingClientRect();
        var visible = r.height > 0 && window.getComputedStyle(bar).display !== 'none' && r.top < window.innerHeight;
        if (visible) bottom = Math.max(bottom, Math.round(window.innerHeight - r.top) + 12);
      }
      root.style.setProperty('--lwc-bottom', bottom + 'px');
      if (state.open || launcher.hidden) return;
      var lr = launcher.getBoundingClientRect();
      var overlap = false;
      Array.prototype.forEach.call(document.querySelectorAll(CTA_SELECTOR), function (cta) {
        if (overlap || cta.offsetParent === null) return;
        var c = cta.getBoundingClientRect();
        if (c.width && c.height && c.left < lr.right + 8 && c.right > lr.left - 8 && c.top < lr.bottom + 8 && c.bottom > lr.top - 8) overlap = true;
      });
      launcher.classList.toggle('is-dodging', overlap);
    });
  }
  window.addEventListener('scroll', positionLauncher, { passive: true });
  window.addEventListener('resize', positionLauncher);
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', function () { if (state.open) syncViewport(); });
    window.visualViewport.addEventListener('scroll', function () { if (state.open) syncViewport(); });
  }
  MOBILE.addEventListener && MOBILE.addEventListener('change', function () {
    document.body.classList.toggle('lwc-locked', state.open && MOBILE.matches);
    positionLauncher();
  });
  positionLauncher();
  window.setTimeout(positionLauncher, 800); // after fonts/images settle

  if (storage(true) === '1') openPanel(false);
})();
