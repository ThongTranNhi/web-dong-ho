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
