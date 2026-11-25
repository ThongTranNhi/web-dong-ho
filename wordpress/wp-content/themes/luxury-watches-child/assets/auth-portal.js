/**
 * Luxury Watches – cổng đăng nhập / đăng ký.
 *
 * Nâng cấp dần (progressive enhancement): không có JS, form vẫn POST bình
 * thường tới WooCommerce. Có JS: validate tại chỗ, hiện/ẩn mật khẩu, gửi form
 * nền để hiển thị trạng thái đang xử lý / thành công / lỗi mạng mà không
 * dùng popup. Server vẫn là nơi validate và xác thực cuối cùng.
 */
(function () {
  'use strict';

  var i18n = window.lwAuthI18n || {};
  var required = i18n.required || {};
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var TIMEOUT_MS = 20000;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Thành phần trường ---------- */

  function setFieldError(input, message) {
    var field = input.closest('.lw-field');
    if (!field) return;
    var box = field.querySelector('[data-lw-error]');
    var text = field.querySelector('[data-lw-error-text]');
    field.classList.toggle('is-invalid', !!message);
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (text) text.textContent = message || '';
    if (box) box.hidden = !message;
  }

  function setAlert(form, message) {
    var alert = form.querySelector('[data-lw-alert]');
    if (!alert) return;
    var text = alert.querySelector('[data-lw-alert-text]');
    if (text) text.textContent = message || '';
    alert.hidden = !message;
  }

  function bindToggle(button) {
    var input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    button.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', show ? 'true' : 'false');
      button.setAttribute('aria-label', show ? i18n.hidePassword : i18n.showPassword);
      input.focus({ preventScroll: true });
      try {
        var end = input.value.length;
        input.setSelectionRange(end, end);
      } catch (e) { /* một số loại input không hỗ trợ */ }
    });
  }

  /* ---------- Validate phía trình duyệt (server vẫn validate lại) ---------- */

  /** Cùng quy tắc với lw_phone_normalize() phía server. */
  function normalizePhone(raw) {
    var digits = String(raw).replace(/[\s.\-()]/g, '');
    var m = digits.match(/^\+?84(\d{9})$/);
    if (m) digits = '0' + m[1];
    return /^0[35789]\d{8}$/.test(digits) ? digits : '';
  }

  function validate(form) {
    var firstInvalid = null;
    form.querySelectorAll('.lw-field__input[required]').forEach(function (input) {
      var value = input.type === 'password' ? input.value : input.value.trim();
      var looksLikeEmail = input.type === 'email' || (input.name === 'username' && value.indexOf('@') !== -1);
      var isNewPassword = input.getAttribute('autocomplete') === 'new-password';
      var message = '';

      if (!value) {
        message = required[input.id] || required.username || '';
      } else if (looksLikeEmail && !EMAIL_RE.test(value)) {
        message = i18n.invalidEmail;
      } else if (input.type === 'tel' && !normalizePhone(value)) {
        message = i18n.invalidPhone;
      } else if (isNewPassword && value.length < 8) {
        message = i18n.shortPassword;
      }

      setFieldError(input, message);
      if (message && !firstInvalid) firstInvalid = input;
    });
    return firstInvalid;
  }

  /* ---------- Trạng thái nút ---------- */

  function setBusy(form, button, busy) {
    form.dataset.lwBusy = busy ? '1' : '';
    if (!button) {
      // Form không có nút (vd. Google): báo trạng thái bằng dòng chữ.
      setStatus(form, busy ? form.dataset.loadingText : '');
      return;
    }
    var label = button.querySelector('[data-lw-submit-label]');
    if (label && !button.dataset.lwLabel) button.dataset.lwLabel = label.textContent;

    button.setAttribute('aria-busy', busy ? 'true' : 'false');
    button.setAttribute('aria-disabled', busy ? 'true' : 'false');
    if (label) label.textContent = busy ? (form.dataset.loadingText || label.textContent) : button.dataset.lwLabel;
  }

  function setStatus(form, message) {
    var status = form.querySelector('[data-lw-status]');
    if (status) status.textContent = message || '';
  }

  function showSuccess(form, button) {
    form.dataset.lwBusy = '1';
    if (!button) {
      setStatus(form, (form.dataset.successText || '') + ' ' + i18n.redirecting);
      return;
    }
    var label = button.querySelector('[data-lw-submit-label]');
    button.setAttribute('aria-busy', 'false');
    button.classList.add('is-success');
    button.disabled = true;
    if (label) label.textContent = form.dataset.successText || label.textContent;
    setStatus(form, i18n.redirecting);
  }

  function focusFirstProblem(scope) {
    var errorBox = scope.querySelector('.lw-field.is-invalid [data-lw-error]:not([hidden])');
    var target = errorBox ? errorBox.closest('.lw-field').querySelector('.lw-field__input') : null;
    if (!target) target = scope.querySelector('.lw-field.is-invalid .lw-field__input');
    if (target) {
      target.focus();
      return;
    }
    var alert = scope.querySelector('[data-lw-alert]:not([hidden])');
    if (alert) {
      alert.setAttribute('tabindex', '-1');
      alert.focus();
    }
  }

  /* ---------- Gửi form nền ---------- */

  function submitInBackground(form, button) {
    var data = new FormData(form);
    // Nút submit không nằm trong FormData; WooCommerce cần khóa "login" / "register".
    if (button && button.name) data.append(button.name, button.value);

    var controller = 'AbortController' in window ? new AbortController() : null;
    var timer = window.setTimeout(function () {
      if (controller) controller.abort();
    }, TIMEOUT_MS);

    fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      redirect: 'follow',
      signal: controller ? controller.signal : undefined
    })
      .then(function (response) {
        window.clearTimeout(timer);
        if (!response.ok) {
          var err = new Error('server');
          err.kind = 'server';
          throw err;
        }
        return response.text().then(function (html) {
          return { url: response.url, html: html };
        });
      })
      .then(function (result) {
        var doc = new DOMParser().parseFromString(result.html, 'text/html');
        var nextCard = doc.querySelector('[data-lw-auth-card]');
        var nextForm = nextCard && nextCard.querySelector('[data-lw-auth-form]');

        if (nextForm) {
          // Máy chủ trả lại form kèm lỗi (sai thông tin, thiếu trường, nonce hết hạn…).
          var card = form.closest('[data-lw-auth-card]');
          card.innerHTML = nextCard.innerHTML;
          init(card);
          focusFirstProblem(card);
          return;
        }

        // Không còn form đăng nhập: WooCommerce đã chuyển hướng sau khi đăng nhập thành công.
        showSuccess(form, button);
        window.setTimeout(function () {
          window.location.assign(result.url || window.location.href);
        }, 650);
      })
      .catch(function (error) {
        window.clearTimeout(timer);
        setBusy(form, button, false);
        var message;
        if (error && error.name === 'AbortError') message = i18n.timeout;
        else if (error && error.kind === 'server') message = i18n.server;
        else message = navigator.onLine === false ? i18n.offline : i18n.network;
        setAlert(form, message);
        focusFirstProblem(form);
      });
  }

  function bindForm(form) {
    var button = form.querySelector('[data-lw-submit]');

    form.addEventListener('input', function (event) {
      var input = event.target;
      if (!input.classList || !input.classList.contains('lw-field__input')) return;
      var field = input.closest('.lw-field');
      if (field && field.classList.contains('is-invalid')) {
        setFieldError(input, '');
        // Lỗi "sai thông tin" đánh dấu cả hai trường: sửa một trường thì gỡ cả hai.
        form.querySelectorAll('.lw-field.is-invalid .lw-field__input').forEach(function (other) {
          var box = other.closest('.lw-field').querySelector('[data-lw-error]');
          if (box && box.hidden) setFieldError(other, '');
        });
      }
    });

    form.addEventListener('submit', function (event) {
      if (form.dataset.lwBusy === '1') {
        event.preventDefault();
        return;
      }

      setAlert(form, '');
      setStatus(form, '');

      var firstInvalid = validate(form);
      if (firstInvalid) {
        event.preventDefault();
        firstInvalid.focus();
        return;
      }

      if (navigator.onLine === false) {
        event.preventDefault();
        setAlert(form, i18n.offline);
        return;
      }

      setBusy(form, button, true);

      var canFetch = typeof window.fetch === 'function' && typeof window.DOMParser === 'function' && typeof window.FormData === 'function';
      if (form.hasAttribute('data-lw-native-submit') || !canFetch) {
        return; // Gửi form truyền thống; nút vẫn ở trạng thái đang xử lý.
      }

      event.preventDefault();
      submitInBackground(form, button);
    });
  }

  /* ---------- Bàn phím điện thoại không che input ---------- */

  function keepFocusedFieldVisible(event) {
    var input = event.target;
    if (!input.classList || !input.classList.contains('lw-field__input')) return;
    if (window.innerWidth > 900) return;
    window.setTimeout(function () {
      if (document.activeElement === input) {
        input.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
      }
    }, 320);
  }

  /* ---------- Tab cách đăng ký (Số điện thoại / Email) ---------- */

  function bindTabs(list) {
    var tabs = Array.prototype.slice.call(list.querySelectorAll('[data-lw-tab]'));

    function select(tab, focus) {
      tabs.forEach(function (t) {
        var active = t === tab;
        t.setAttribute('aria-selected', active ? 'true' : 'false');
        t.tabIndex = active ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !active;
      });
      if (focus) tab.focus();
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('method', tab.dataset.lwTab);
        window.history.replaceState(null, '', url.toString());
      } catch (e) { /* trình duyệt cũ: bỏ qua */ }
    }

    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function (event) {
        event.preventDefault();
        select(tab, false);
      });
      tab.addEventListener('keydown', function (event) {
        var next = null;
        if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
        else if (event.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length];
        else if (event.key === 'Home') next = tabs[0];
        else if (event.key === 'End') next = tabs[tabs.length - 1];
        if (next) {
          event.preventDefault();
          select(next, true);
        }
      });
    });
  }

  /* ---------- Google Identity Services ---------- */

  var googleReady = false;

  function googleAvailable() {
    return !!(i18n.googleClientId && window.google && window.google.accounts && window.google.accounts.id);
  }

  function handleGoogleCredential(response) {
    var form = document.querySelector('[data-lw-google-form]');
    if (!form || !response || !response.credential) return;
    var input = form.querySelector('[data-lw-google-credential]');
    if (input) input.value = response.credential;
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
  }

  function renderGoogle(root) {
    if (!googleAvailable()) return;
    var slots = root.querySelectorAll('[data-lw-google-button]');
    if (!slots.length) return;

    if (!googleReady) {
      var block = root.querySelector('[data-lw-google]');
      window.google.accounts.id.initialize({
        client_id: i18n.googleClientId,
        callback: handleGoogleCredential,
        ux_mode: 'popup',
        auto_select: false,
        cancel_on_tap_outside: true,
        context: block && block.dataset.context === 'signup' ? 'signup' : 'signin'
      });
      googleReady = true;
    }

    slots.forEach(function (slot) {
      var block = slot.closest('[data-lw-google]');
      var signup = block && block.dataset.context === 'signup';
      slot.innerHTML = '';
      window.google.accounts.id.renderButton(slot, {
        type: 'standard',
        theme: 'outline',
        size: 'large',
        shape: 'rectangular',
        text: signup ? 'signup_with' : 'signin_with',
        logo_alignment: 'center',
        locale: 'vi',
        width: Math.max(200, Math.min(400, Math.round(slot.getBoundingClientRect().width || 400)))
      });
    });
  }

  /* ---------- Khởi tạo ---------- */

  function init(root) {
    root.querySelectorAll('[data-lw-toggle-password]').forEach(bindToggle);
    root.querySelectorAll('[data-lw-auth-form]').forEach(bindForm);
    root.querySelectorAll('[data-lw-tabs]').forEach(bindTabs);
    renderGoogle(root);
  }

  // Thư viện Google tải bất đồng bộ: vẽ nút khi sẵn sàng.
  window.onGoogleLibraryLoad = function () {
    renderGoogle(document);
  };

  function boot() {
    init(document);
    document.addEventListener('focusin', keepFocusedFieldVisible);

    window.addEventListener('offline', function () {
      document.querySelectorAll('[data-lw-auth-form]').forEach(function (form) {
        setAlert(form, i18n.offline);
      });
    });
    window.addEventListener('online', function () {
      document.querySelectorAll('[data-lw-auth-form]').forEach(function (form) {
        var text = form.querySelector('[data-lw-alert-text]');
        if (text && text.textContent === i18n.offline) setAlert(form, '');
      });
    });

    // Quay lại bằng nút Back (bfcache): trả nút về trạng thái bình thường.
    window.addEventListener('pageshow', function (event) {
      if (!event.persisted) return;
      document.querySelectorAll('[data-lw-auth-form]').forEach(function (form) {
        var button = form.querySelector('[data-lw-submit]');
        if (button) {
          button.classList.remove('is-success');
          button.disabled = false;
        }
        setBusy(form, button, false);
        setStatus(form, '');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
