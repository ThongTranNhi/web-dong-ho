(() => {
  'use strict';
  const form = document.querySelector('#auction-form');
  if (!form) return;
  const select = document.querySelector('#auction-product');
  const search = document.querySelector('#product-search');
  const options = [...select.options].map(option => option.cloneNode(true));
  const image = document.querySelector('#preview-image');
  const placeholder = document.querySelector('#preview-placeholder');
  const upload = document.querySelector('#auction-image');
  const title = document.querySelector('#auction-title');
  const price = document.querySelector('#start-price');
  const end = document.querySelector('#auction-end');
  let imageUrl = '';

  const showImage = src => {
    image.hidden = !src;
    placeholder.hidden = Boolean(src);
    if (src) image.src = src;
    else image.removeAttribute('src');
  };
  search.addEventListener('input', () => {
    const term = search.value.toLocaleLowerCase('vi');
    const selected = select.value;
    select.replaceChildren(...options.filter(option => !option.value || option.value === selected || option.textContent.toLocaleLowerCase('vi').includes(term)).map(option => option.cloneNode(true)));
    select.value = selected;
    document.querySelector('#product-search-status').textContent = `${Math.max(0, select.options.length - 1)} sản phẩm phù hợp. Mở danh sách bên trên để chọn.`;
  });
  select.addEventListener('change', () => {
    const option = select.selectedOptions[0];
    if (!title.value.trim()) title.value = option.dataset.title || '';
    document.querySelector('#retail').value = option.dataset.price || '0';
    document.querySelector('#preview-title').textContent = option.dataset.title || 'Phiên đấu giá mới';
    if (!upload.files.length) showImage(option.dataset.image || '');
  });
  title.addEventListener('input', () => { document.querySelector('#preview-title').textContent = title.value.trim() || 'Phiên đấu giá mới'; });
  const updatePrice = () => {
    const amount = Number(price.value);
    document.querySelector('#preview-price').textContent = amount > 0 ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount) : '—';
  };
  price.addEventListener('input', updatePrice);
  updatePrice();
  upload.addEventListener('change', () => {
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    imageUrl = upload.files[0] ? URL.createObjectURL(upload.files[0]) : '';
    showImage(imageUrl || select.selectedOptions[0].dataset.image || '');
  });
  const validateDates = () => {
    const startValue = document.querySelector('#auction-start').value;
    end.setCustomValidity(startValue && end.value && end.value <= startValue ? 'Giờ kết thúc phải sau giờ bắt đầu.' : '');
  };
  document.querySelector('#auction-start').addEventListener('change', validateDates);
  end.addEventListener('change', validateDates);
  let submitting = false;
  form.addEventListener('submit', event => {
    validateDates();
    if (!form.reportValidity() || submitting) { event.preventDefault(); return; }
    submitting = true;
    document.querySelector('.submit-status').textContent = 'Đang lưu phiên, vui lòng chờ…';
    // Preserve the clicked button's status value in the submitted form.
    form.querySelectorAll('button[type="submit"]').forEach(button => button.setAttribute('aria-disabled', 'true'));
  });
})();

// Product form: live preview, price rules and single submission.
(() => {
  'use strict';
  const form = document.querySelector('#product-form');
  if (!form) return;
  const $ = (selector) => form.querySelector(selector);
  const name = $('#p-name');
  const regular = $('#p-regular');
  const sale = $('#p-sale');
  const stock = $('#p-stock');
  const upload = $('#p-image');
  const image = $('#p-preview-image');
  const placeholder = $('#p-preview-placeholder');
  const money = (n) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(n);
  let imageUrl = '';

  const render = () => {
    $('#p-preview-title').textContent = name.value.trim() || 'Sản phẩm mới';
    const r = Number(regular.value), s = Number(sale.value);
    const price = s > 0 && s < r ? s : r;
    $('#p-preview-price').textContent = price > 0 ? money(price) : '—';
    const q = stock.value === '' ? NaN : Number(stock.value);
    $('#p-preview-stock').textContent = Number.isInteger(q) ? (q > 0 ? `Còn hàng (${q} chiếc)` : 'Hết hàng') : '';
    sale.setCustomValidity(sale.value !== '' && r > 0 && s >= r ? 'Giá khuyến mãi phải thấp hơn giá niêm yết.' : '');
  };
  [name, regular, sale, stock].forEach((el) => el.addEventListener('input', render));
  render();

  upload.addEventListener('change', () => {
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    imageUrl = upload.files[0] ? URL.createObjectURL(upload.files[0]) : '';
    image.hidden = !imageUrl;
    placeholder.hidden = Boolean(imageUrl);
    if (imageUrl) image.src = imageUrl; else image.removeAttribute('src');
  });

  let submitting = false;
  form.addEventListener('submit', (event) => {
    render();
    const publishing = event.submitter && event.submitter.value === 'publish';
    upload.setCustomValidity(publishing && !upload.files.length ? 'Cần ảnh sản phẩm trước khi đăng. Bạn có thể lưu nháp nếu chưa có ảnh.' : '');
    if (!form.reportValidity() || submitting) { event.preventDefault(); return; }
    submitting = true;
    $('.submit-status').textContent = 'Đang lưu sản phẩm, vui lòng chờ…';
    form.querySelectorAll('button[type="submit"]').forEach((button) => button.setAttribute('aria-disabled', 'true'));
  });
  upload.addEventListener('change', () => upload.setCustomValidity(''));
})();

// Product list: confirm before moving a product to the trash.
(() => {
  'use strict';
  document.querySelectorAll('.js-trash-product').forEach((form) => {
    let sent = false;
    form.addEventListener('submit', (event) => {
      const name = form.dataset.name || 'sản phẩm này';
      if (sent || !window.confirm(`Xóa “${name}”?\n\nSản phẩm sẽ được chuyển vào thùng rác và biến mất khỏi cửa hàng. Bạn có thể hoàn tác ngay sau đó.`)) { event.preventDefault(); return; }
      sent = true;
    });
  });
})();

// Order detail: require a deliberate confirmation before a financial status change.
(() => {
  'use strict';
  document.querySelectorAll('.js-order-update').forEach((form) => {
    let sent = false;
    form.addEventListener('submit', (event) => {
      if (sent) { event.preventDefault(); return; }
      const submitter = event.submitter;
      const status = form.querySelector('#order-new-status');
      status.setCustomValidity(submitter && submitter.value === 'status' && !status.value ? 'Vui lòng chọn trạng thái mới.' : '');
      const action = submitter && submitter.value === 'mark_paid' ? 'xác nhận đã thanh toán' : 'cập nhật trạng thái';
      const number = form.dataset.orderNumber || '';
      if (!form.reportValidity() || !window.confirm(`Bạn chắc chắn muốn ${action} cho đơn #${number}?\n\nThao tác sẽ được lưu vào lịch sử đơn hàng.`)) {
        event.preventDefault();
        return;
      }
      sent = true;
      form.querySelectorAll('button[type="submit"]').forEach((button) => button.setAttribute('aria-disabled', 'true'));
    });
  });
})();
