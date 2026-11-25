(() => {
  'use strict';

  const formatRemaining = (milliseconds) => {
    const total = Math.max(0, Math.floor(milliseconds / 1000));
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    if (days > 0) return `${days} ngày ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  };

  const clocks = Array.from(document.querySelectorAll('.lw-countdown[data-end]'));
  const updateClocks = () => {
    const now = Date.now();
    clocks.forEach((clock) => {
      const output = clock.querySelector('[data-countdown-output]');
      const end = Date.parse(clock.dataset.end || '');
      if (!output || Number.isNaN(end) || clock.dataset.state === 'ended') return;
      const remaining = end - now;
      output.textContent = remaining > 0 ? formatRemaining(remaining) : 'Phiên vừa chuyển trạng thái';
      if (remaining <= 0 && clock.dataset.expired !== 'true') {
        clock.dataset.expired = 'true';
        window.setTimeout(() => window.location.reload(), 1800);
      }
    });
  };
  if (clocks.length) {
    updateClocks();
    window.setInterval(updateClocks, 1000);
  }

  // Enterprise Filter, Search & Sort
  document.querySelectorAll('.lw-auction-market').forEach((market) => {
    const grid = market.querySelector('.lw-auction-grid');
    if (!grid) return;

    let cards = Array.from(grid.querySelectorAll('.lw-auction-card'));
    const initialOrder = [...cards];
    const empty = market.querySelector('.lw-auction-filter-empty');
    const searchInput = market.querySelector('#lwAuctionSearch');
    const sortSelect = market.querySelector('#lwAuctionSort');
    const filterButtons = market.querySelectorAll('[data-auction-filter]');

    let currentFilter = 'all';
    let currentSearch = '';

    // Update tab counts dynamically
    const updateCounts = () => {
      const counts = { all: cards.length, live: 0, upcoming: 0, ended: 0 };
      cards.forEach(card => {
        const state = card.dataset.auctionState;
        if (counts[state] !== undefined) counts[state]++;
      });
      filterButtons.forEach(btn => {
        const filter = btn.dataset.auctionFilter;
        const countEl = btn.querySelector('.lw-tab-count');
        if (countEl && counts[filter] !== undefined) {
          countEl.textContent = counts[filter];
        }
      });
    };
    updateCounts();

    const applyFilters = () => {
      let visible = 0;
      cards.forEach((card) => {
        const state = card.dataset.auctionState || '';
        const title = (card.dataset.auctionTitle || '').toLowerCase();
        const lot = (card.dataset.auctionLot || '').toLowerCase();

        const matchesState = currentFilter === 'all' || state === currentFilter;
        const matchesQuery = !currentSearch || title.includes(currentSearch) || lot.includes(currentSearch);

        const show = matchesState && matchesQuery;
        card.hidden = !show;
        if (show) visible++;
      });
      if (empty) empty.hidden = visible !== 0;
    };

    filterButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        currentFilter = btn.dataset.auctionFilter || 'all';
        filterButtons.forEach(item => {
          const active = item === btn;
          item.classList.toggle('is-active', active);
          item.setAttribute('aria-pressed', String(active));
        });
        applyFilters();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        currentSearch = searchInput.value.toLowerCase().trim();
        applyFilters();
      });
    }

    if (sortSelect) {
      sortSelect.addEventListener('change', () => {
        const sortVal = sortSelect.value;
        if (sortVal === 'default') {
          cards = [...initialOrder];
        } else if (sortVal === 'end-soon') {
          cards.sort((a, b) => {
            const endA = parseInt(a.dataset.auctionEnd || '0', 10);
            const endB = parseInt(b.dataset.auctionEnd || '0', 10);
            return endA - endB;
          });
        } else if (sortVal === 'price-asc') {
          cards.sort((a, b) => {
            const priceA = parseFloat(a.dataset.auctionPrice || '0');
            const priceB = parseFloat(b.dataset.auctionPrice || '0');
            return priceA - priceB;
          });
        } else if (sortVal === 'price-desc') {
          cards.sort((a, b) => {
            const priceA = parseFloat(a.dataset.auctionPrice || '0');
            const priceB = parseFloat(b.dataset.auctionPrice || '0');
            return priceB - priceA;
          });
        } else if (sortVal === 'bids-desc') {
          cards.sort((a, b) => {
            const bidsA = parseInt(a.dataset.auctionBids || '0', 10);
            const bidsB = parseInt(b.dataset.auctionBids || '0', 10);
            return bidsB - bidsA;
          });
        }
        cards.forEach(card => grid.appendChild(card));
        applyFilters();
      });
    }
  });

  document.querySelectorAll('[data-bid-form]').forEach((form) => {
    const input = form.querySelector('input[name="amount"]');
    form.querySelectorAll('[data-bid-value]').forEach((button) => {
      button.addEventListener('click', () => {
        if (!input) return;
        input.value = button.dataset.bidValue || '';
        input.focus();
      });
    });
    form.addEventListener('submit', () => {
      const submit = form.querySelector('button[type="submit"]');
      if (!submit || submit.disabled) return;
      submit.disabled = true;
      submit.textContent = 'Đang kiểm tra giá…';
    });
  });
})();
