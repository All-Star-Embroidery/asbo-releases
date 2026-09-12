(() => {
  'use strict';

  const initAll = () => {
  const roots = document.querySelectorAll('[data-asbo-root]');
  if (!roots.length) return;

  roots.forEach((root) => {
    if (root.dataset.asboReady === '1') return;

    const configEl = root.querySelector('.asbo__config');
    let ASBO_CONFIG = {};
    try {
      ASBO_CONFIG = JSON.parse(configEl?.textContent || '{}');
    } catch (error) {
      console.error('Invalid ASBO configuration', error);
      return;
    }
    if (!ASBO_CONFIG.ajaxUrl || !ASBO_CONFIG.nonceUrl) return;
    root.dataset.asboReady = '1';

    const money = new Intl.NumberFormat(ASBO_CONFIG.locale || 'en-US', {
      style: 'currency',
      currency: ASBO_CONFIG.currency || 'USD',
    });

    let currentStep = 1;
    let submitting = false;

    const topNext = root.querySelector('[data-next-step]');
    const stickyNext = root.querySelector('[data-sticky-next]');
    const backButton = root.querySelector('[data-back-step]');
    const sticky = root.querySelector('[data-sticky-summary]');
    const totalItemsEl = root.querySelector('[data-total-items]');
    const grandTotalEl = root.querySelector('[data-grand-total]');
    const totalSavedEl = root.querySelector('[data-total-saved]');
    const reviewTotalEl = root.querySelector('[data-review-total]');
    const reviewEl = root.querySelector('[data-order-review]');
    const notice = root.querySelector('[data-notice]');
    const artworkNotes = root.querySelector('[data-artwork-notes]');
    const artworkRights = root.querySelector('[data-artwork-rights]');
    const artworkPlanInputs = [...root.querySelectorAll('[data-artwork-plan]')];
    const previousOrderSelect = root.querySelector('[data-previous-order-select]');
    const previousOrderManual = root.querySelector('[data-previous-order-manual]');
    const idleCue = root.querySelector('[data-idle-cue]');
    const previousOrderField = root.querySelector('[data-previous-order-field]');
    const welcomeToast = root.querySelector('[data-welcome-toast]');
    const welcomeCloseBtn = root.querySelector('[data-welcome-close]');

    const productData = new Map();
    const summaryCache = new Map();

    root.querySelectorAll('[data-product]').forEach((productEl) => {
      const jsonEl = productEl.querySelector('.asbo__pricing-data');
      let pricing = {};
      try {
        pricing = JSON.parse(jsonEl?.textContent || '{}');
      } catch (error) {
        console.error('Invalid ASBO pricing data', error);
      }

      productData.set(productEl, {
        productId: Number(productEl.dataset.productId || 0),
        pricing,
      });
    });

    const showNotice = (message, type = 'error') => {
      if (!notice) return;
      notice.textContent = message;
      notice.dataset.type = type;
      notice.hidden = false;
      notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const clearNotice = () => {
      if (!notice) return;
      notice.hidden = true;
      notice.textContent = '';
    };

    const selectedDecoration = (productEl) =>
      productEl.querySelector('[data-decoration]:checked')?.value || '';

    const getTier = (tiers, quantity) => {
      const thresholds = Object.keys(tiers || {})
        .map(Number)
        .filter((n) => Number.isFinite(n) && n > 1)
        .sort((a, b) => a - b);

      let chosen = null;
      thresholds.forEach((threshold) => {
        if (quantity >= threshold) {
          chosen = { threshold, price: Number(tiers[threshold]) };
        }
      });
      return chosen;
    };

    const clampQuantity = (value) => {
      const number = Number.parseInt(value, 10);
      if (!Number.isFinite(number) || number < 0) return 0;
      return Math.min(9999, number);
    };

    const selectedArtworkPlan = () =>
      root.querySelector('[data-artwork-plan]:checked')?.value || 'upload_after_checkout';

    const previousOrderValue = () => {
      const selected = previousOrderSelect?.value?.trim() || '';
      if (selected) return selected;
      return previousOrderManual?.value?.trim() || '';
    };

    const updateArtworkPlanUI = () => {
      const needsReference = selectedArtworkPlan() === 'previous_order';
      if (previousOrderField) previousOrderField.hidden = !needsReference;

      root.querySelectorAll('.asbo__artwork-option').forEach((option) => {
        const input = option.querySelector('[data-artwork-plan]');
        option.classList.toggle('is-selected', Boolean(input?.checked));
      });
    };

    const setStickyVisible = (visible) => {
      if (!sticky) return;
      sticky.classList.toggle('is-visible', visible);
      sticky.setAttribute('aria-hidden', visible ? 'false' : 'true');
    };

    const animatePanel = (panel, open) => {
      if (!panel) return;

      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        panel.hidden = !open;
        panel.style.maxHeight = open ? 'none' : '0px';
        panel.style.opacity = open ? '1' : '0';
        return;
      }

      panel.dataset.animating = 'true';

      if (open) {
        panel.hidden = false;
        panel.style.maxHeight = '0px';
        panel.style.opacity = '0';
        void panel.offsetHeight;
        panel.style.maxHeight = panel.scrollHeight + 'px';
        panel.style.opacity = '1';
      } else {
        if (panel.hidden) return;
        panel.style.maxHeight = panel.scrollHeight + 'px';
        panel.style.opacity = '1';
        void panel.offsetHeight;
        panel.style.maxHeight = '0px';
        panel.style.opacity = '0';
      }

      const finish = (event) => {
        if (event && event.propertyName !== 'max-height') return;
        panel.removeEventListener('transitionend', finish);
        delete panel.dataset.animating;
        if (open) {
          panel.style.maxHeight = 'none';
        } else {
          panel.hidden = true;
        }
      };

      panel.addEventListener('transitionend', finish);
    };

    const getProductSummary = (productEl) => {
      const info = productData.get(productEl);
      const decoration = selectedDecoration(productEl);
      const inputs = [...productEl.querySelectorAll('[data-qty-input]')];
      const lines = inputs
        .map((input) => ({
          productId: Number(input.dataset.productId),
          variationId: Number(input.dataset.variationId),
          variationLabel: input.dataset.variationLabel || 'Standard',
          basePrice: Number.isFinite(Number.parseFloat(input.dataset.basePrice)) ? Number.parseFloat(input.dataset.basePrice) : null,
          quantity: clampQuantity(input.value),
          decoration,
        }))
        .filter((line) => line.quantity > 0);

      const quantity = lines.reduce((sum, line) => sum + line.quantity, 0);
      const tier = getTier(info?.pricing?.[decoration] || {}, quantity);
      const normalSubtotal = lines.reduce((sum, line) => sum + ((line.basePrice ?? 0) * line.quantity), 0);
      const subtotal = tier ? tier.price * quantity : normalSubtotal;
      const bulkSavings = Math.max(0, normalSubtotal - subtotal);

      return {
        productId: info?.productId || 0,
        productName: productEl.dataset.productName || productEl.querySelector('.asbo__product-title-wrap strong')?.textContent?.trim() || 'Unnamed product',
        decoration,
        quantity,
        tier,
        subtotal,
        normalSubtotal,
        bulkSavings,
        usingBasePrice: quantity > 0 && !tier,
        lines,
      };
    };

    const refreshProductSummary = (productEl) => {
      if (!productEl) return;
      summaryCache.set(productEl, getProductSummary(productEl));
    };

    const allSummaries = () => {
      if (!summaryCache.size) {
        root.querySelectorAll('[data-product]').forEach(refreshProductSummary);
      }
      return [...summaryCache.values()];
    };

    const orderTotals = () => {
      const summaries = allSummaries();
      const items = summaries.reduce((sum, item) => sum + item.quantity, 0);
      const total = summaries.reduce((sum, item) => sum + item.subtotal, 0);
      const bulkSavings = summaries.reduce((sum, item) => sum + item.bulkSavings, 0);
      const shippingSavings = items >= Number(ASBO_CONFIG.freeShipQty) ? Number(ASBO_CONFIG.shippingSavingsAmount || 0) : 0;
      return {
        summaries,
        items,
        total,
        savings: Math.max(0, bulkSavings + shippingSavings),
      };
    };

    const saveState = () => {
      const quantities = {};
      root.querySelectorAll('[data-qty-input]').forEach((input) => {
        quantities[`${input.dataset.productId}:${input.dataset.variationId}`] = clampQuantity(input.value);
      });

      const decorations = {};
      root.querySelectorAll('[data-product]').forEach((productEl) => {
        decorations[productEl.dataset.productId] = selectedDecoration(productEl);
      });

      try {
        localStorage.setItem(
          ASBO_CONFIG.storageKey,
          JSON.stringify({
            quantities,
            decorations,
            notes: artworkNotes?.value || '',
            artworkPlan: selectedArtworkPlan(),
            previousOrder: previousOrderValue(),
          })
        );
      } catch (error) {
        // Storage may be disabled. The form still works without persistence.
      }
    };

    const restoreState = () => {
      try {
        const saved = JSON.parse(localStorage.getItem(ASBO_CONFIG.storageKey) || '{}');
        Object.entries(saved.quantities || {}).forEach(([key, quantity]) => {
          const [productId, variationId] = key.split(':');
          const input = root.querySelector(
            `[data-qty-input][data-product-id="${CSS.escape(productId)}"][data-variation-id="${CSS.escape(variationId)}"]`
          );
          if (input) input.value = clampQuantity(quantity);
        });

        Object.entries(saved.decorations || {}).forEach(([productId, decoration]) => {
          const productEl = root.querySelector(`[data-product-id="${CSS.escape(productId)}"]`);
          const radio = [...(productEl?.querySelectorAll('[data-decoration]') || [])].find(
            (input) => input.value === decoration
          );
          if (radio) radio.checked = true;
        });

        if (artworkNotes && saved.notes) artworkNotes.value = saved.notes;
        if (saved.previousOrder) {
          const value = String(saved.previousOrder);
          if (previousOrderSelect && [...previousOrderSelect.options].some((option) => option.value === value)) {
            previousOrderSelect.value = value;
          } else if (previousOrderManual) {
            previousOrderManual.value = value.startsWith('order:') ? '' : value;
          }
        }
        if (saved.artworkPlan) {
          const planInput = artworkPlanInputs.find((input) => input.value === saved.artworkPlan);
          if (planInput) planInput.checked = true;
        }
        updateArtworkPlanUI();
      } catch (error) {
        // Ignore malformed saved state.
      }
    };

    const renderReview = (summaries) => {
      if (!reviewEl) return;
      const selected = summaries.filter((summary) => summary.quantity > 0);

      if (!selected.length) {
        reviewEl.innerHTML = '<p class="asbo__empty-review">No garments or headwear selected yet.</p>';
        return;
      }

      reviewEl.innerHTML = selected
        .map(
          (summary) => `
            <div class="asbo__review-product">
              <div>
                <strong>${escapeHtml(summary.productName)}</strong>
                <span>${escapeHtml(summary.decoration)} · ${summary.quantity} items</span>
              </div>
              <strong>${money.format(summary.subtotal)}</strong>
              <ul>
                ${summary.lines
                  .map((line) => `<li>${escapeHtml(line.variationLabel)}: ${line.quantity}</li>`)
                  .join('')}
              </ul>
            </div>
          `
        )
        .join('');
    };

    const updateUI = (changedProductEl = null) => {
      if (changedProductEl) refreshProductSummary(changedProductEl);
      const { summaries, items, total, savings } = orderTotals();

      summaries.forEach((summary) => {
        const productEl = root.querySelector(`[data-product-id="${CSS.escape(String(summary.productId))}"]`);
        if (!productEl) return;

        const subtotalEl = productEl.querySelector('[data-product-subtotal]');
        const quantityLabel = productEl.querySelector('[data-product-quantity-label]');
        const activeTier = productEl.querySelector('[data-active-tier]');

        if (subtotalEl) subtotalEl.textContent = money.format(summary.subtotal);
        if (quantityLabel) {
          quantityLabel.textContent = summary.quantity
            ? `${summary.quantity} piece${summary.quantity === 1 ? '' : 's'} selected`
            : 'No pieces selected';
        }

        productEl.querySelectorAll('[data-pricing-row]').forEach((row) => row.classList.remove('is-active'));
        productEl.querySelectorAll('[data-threshold]').forEach((cell) => cell.classList.remove('is-active'));

        if (summary.tier) {
          if (activeTier) {
            activeTier.textContent = `${summary.quantity} pieces · ${money.format(summary.tier.price)} each`;
          }
          const row = [...productEl.querySelectorAll('[data-pricing-row]')].find(
            (candidate) => candidate.dataset.pricingRow === summary.decoration
          );
          row?.classList.add('is-active');
          row?.querySelector(`[data-threshold="${summary.tier.threshold}"]`)?.classList.add('is-active');
        } else if (summary.usingBasePrice) {
          if (activeTier) {
            activeTier.textContent = `${summary.quantity} piece${summary.quantity === 1 ? '' : 's'} · normal price`;
          }
          const row = [...productEl.querySelectorAll('[data-pricing-row]')].find(
            (candidate) => candidate.dataset.pricingRow === summary.decoration
          );
          row?.classList.add('is-active');
          row?.querySelector('[data-threshold="1"]')?.classList.add('is-active');
        } else if (activeTier) {
          activeTier.textContent = 'Enter quantities to calculate your pricing tier';
        }
      });

      if (totalItemsEl) totalItemsEl.textContent = String(items);
      if (grandTotalEl) grandTotalEl.textContent = money.format(total);
      if (totalSavedEl) totalSavedEl.textContent = money.format(savings);
      if (reviewTotalEl) reviewTotalEl.textContent = money.format(total);

      const artIncentive = root.querySelector('[data-art-incentive]');
      const shipIncentive = root.querySelector('[data-ship-incentive]');
      const reusingApprovedArtwork = selectedArtworkPlan() === 'previous_order';
      const digitizingIncluded = reusingApprovedArtwork || items >= Number(ASBO_CONFIG.freeArtQty);
      artIncentive?.classList.toggle('is-unlocked', digitizingIncluded);
      if (artIncentive) {
        if (!artIncentive.dataset.defaultText) {
          artIncentive.dataset.defaultText = artIncentive.textContent.replace(/^\s*\d+\+\s*/, '').trim();
        }
        artIncentive.textContent = reusingApprovedArtwork
          ? 'Previous artwork · Digitizing + setup waived'
          : `${Number(ASBO_CONFIG.freeArtQty)}+ ${artIncentive.dataset.defaultText}`;
      }
      shipIncentive?.classList.toggle('is-unlocked', items >= Number(ASBO_CONFIG.freeShipQty));

      renderReview(summaries);
      saveState();
    };

    const setStep = (step, shouldScroll = true) => {
      currentStep = Math.max(1, Math.min(2, step));
      clearNotice();

      root.querySelectorAll('[data-step-panel]').forEach((panel) => {
        const active = Number(panel.dataset.stepPanel) === currentStep;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
      });

      root.querySelectorAll('[data-progress-step]').forEach((indicator) => {
        const indicatorStep = Number(indicator.dataset.progressStep);
        indicator.classList.toggle('is-active', indicatorStep === currentStep);
        indicator.classList.toggle('is-complete', indicatorStep < currentStep);
      });

      setStickyVisible(true);
      if (backButton) backButton.hidden = currentStep < 2;

      if (currentStep === 1) {
        if (topNext) topNext.textContent = ASBO_CONFIG.buttonTexts.nextArtwork;
        if (stickyNext) stickyNext.textContent = ASBO_CONFIG.buttonTexts.nextArtwork;
      } else {
        if (topNext) topNext.textContent = ASBO_CONFIG.buttonTexts.continueCheckout;
        if (stickyNext) stickyNext.textContent = ASBO_CONFIG.buttonTexts.continueCheckout;
      }

      updateUI();
      if (shouldScroll) root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const setCheckoutProgress = () => {
      root.querySelectorAll('[data-progress-step]').forEach((indicator) => {
        const indicatorStep = Number(indicator.dataset.progressStep);
        indicator.classList.toggle('is-active', indicatorStep === 3);
        indicator.classList.toggle('is-complete', indicatorStep < 3);
      });
    };

    const submitOrder = async () => {
      if (submitting) return;

      if (!artworkRights?.checked) {
        showNotice(ASBO_CONFIG.messages.confirmArtworkRights);
        artworkRights?.focus();
        return;
      }

      if (selectedArtworkPlan() === 'previous_order' && !previousOrderValue()) {
        showNotice(ASBO_CONFIG.messages.choosePreviousOrder);
        if (previousOrderSelect) previousOrderSelect.focus();
        else previousOrderManual?.focus();
        return;
      }

      const { summaries, items } = orderTotals();
      if (!items) {
        setStep(1);
        showNotice(ASBO_CONFIG.messages.chooseItem);
        return;
      }

      setCheckoutProgress();

      const selections = summaries.flatMap((summary) => summary.lines);

      submitting = true;
      [topNext, stickyNext].forEach((button) => {
        if (button) {
          button.disabled = true;
          button.textContent = ASBO_CONFIG.messages.working;
        }
      });

      try {
        const nonceResponse = await fetch(ASBO_CONFIG.nonceUrl, {
          method: 'GET',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'Accept': 'application/json' },
        });
        const noncePayload = await nonceResponse.json();
        if (!nonceResponse.ok || !noncePayload?.success || !noncePayload?.data?.nonce) {
          throw new Error(ASBO_CONFIG.messages.sessionExpired);
        }

        const form = new FormData();
        form.append('action', 'asbo_add_bulk_to_cart');
        form.append('nonce', noncePayload.data.nonce);
        form.append('selections', JSON.stringify(selections));
        form.append('artworkNotes', artworkNotes?.value || '');
        form.append('artworkPlan', selectedArtworkPlan());
        form.append('previousOrder', previousOrderValue());
        form.append('rightsConfirmed', artworkRights?.checked ? '1' : '0');

        const response = await fetch(ASBO_CONFIG.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          body: form,
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
          const message = response.status === 403
            ? ASBO_CONFIG.messages.sessionExpired
            : (payload?.data?.message || ASBO_CONFIG.messages.error);
          throw new Error(message);
        }

        try {
          localStorage.removeItem(ASBO_CONFIG.storageKey);
        } catch (error) {
          // Ignore storage errors.
        }

        window.location.assign(payload.data.checkoutUrl || ASBO_CONFIG.checkoutUrl);
      } catch (error) {
        showNotice(error.message || ASBO_CONFIG.messages.error);
        submitting = false;
        [topNext, stickyNext].forEach((button) => {
          if (button) button.disabled = false;
        });
        setStep(2, false);
      }
    };

    const goNext = () => {
      if (currentStep === 1) {
        const { items } = orderTotals();
        if (!items) {
          showNotice(ASBO_CONFIG.messages.chooseItem);
          return;
        }
        setStep(2);
        return;
      }

      submitOrder();
    };

    // One-time inactivity cue: after 30 seconds with no user activity, point
    // toward the current sticky action. The first subsequent activity hides it and
    // permanently consumes the cue for this page view.
    let idleCueTimer = null;
    let idleCueShown = false;
    let idleCueConsumed = false;

    const hideIdleCue = () => {
      if (!idleCue) return;
      idleCue.classList.remove('is-visible');
      idleCue.setAttribute('aria-hidden', 'true');
    };

    const showIdleCue = () => {
      if (!idleCue || idleCueShown || idleCueConsumed || submitting || !stickyNext || stickyNext.disabled) return;
      idleCueShown = true;
      idleCue.classList.add('is-visible');
      idleCue.setAttribute('aria-hidden', 'false');
    };

    const scheduleIdleCue = () => {
      if (idleCueConsumed || idleCueShown) return;
      window.clearTimeout(idleCueTimer);
      idleCueTimer = window.setTimeout(showIdleCue, 30000);
    };

    const registerActivity = () => {
      if (idleCueShown) {
        hideIdleCue();
        idleCueConsumed = true;
        window.clearTimeout(idleCueTimer);
        return;
      }
      scheduleIdleCue();
    };

    ['pointerdown', 'keydown', 'input', 'change', 'wheel', 'touchstart'].forEach((eventName) => {
      window.addEventListener(eventName, registerActivity, { passive: true });
    });
    window.addEventListener('scroll', registerActivity, { passive: true });
    scheduleIdleCue();

    root.addEventListener('click', (event) => {
      const filterButton = event.target.closest('[data-subcategory-filter-value]');
      if (filterButton) {
        const value = filterButton.dataset.subcategoryFilterValue || 'all';
        const filter = filterButton.closest('[data-subcategory-filter]');

        filter?.querySelectorAll('[data-subcategory-filter-value]').forEach((button) => {
          const active = button === filterButton;
          button.classList.toggle('is-active', active);
          button.setAttribute('aria-pressed', String(active));
        });

        root.querySelectorAll('[data-product]').forEach((productEl) => {
          const slugs = (productEl.dataset.subcategories || '').split(/\s+/).filter(Boolean);
          const visible = value === 'all' || slugs.includes(value);

          if (!visible) {
            const openTrigger = productEl.querySelector('.asbo__product-trigger[aria-expanded="true"]');
            if (openTrigger) {
              openTrigger.setAttribute('aria-expanded', 'false');
              const icon = productEl.querySelector('.asbo__product-icon');
              if (icon) icon.textContent = '+';
              productEl.classList.remove('is-open');
              animatePanel(productEl.querySelector('.asbo__product-panel'), false);
            }
          }

          productEl.hidden = !visible;
        });
        return;
      }

      const trigger = event.target.closest('.asbo__product-trigger');
      if (trigger) {
        const productEl = trigger.closest('[data-product]');
        const panel = productEl?.querySelector('.asbo__product-panel');
        if (!productEl || !panel) return;

        const expanded = trigger.getAttribute('aria-expanded') === 'true';
        const opening = !expanded;

        // Keep a single product expanded at a time on every viewport. The customer
        // keeps the current pricing task in context without accumulating long open
        // sections above and below the product they are actively configuring.
        if (opening) {
          root.querySelectorAll('.asbo__product-trigger[aria-expanded="true"]').forEach((otherTrigger) => {
            if (otherTrigger === trigger) return;
            const otherProduct = otherTrigger.closest('[data-product]');
            const otherPanel = otherProduct?.querySelector('.asbo__product-panel');
            otherTrigger.setAttribute('aria-expanded', 'false');
            const otherIcon = otherProduct?.querySelector('.asbo__product-icon');
            if (otherIcon) otherIcon.textContent = '+';
            otherProduct?.classList.remove('is-open');
            animatePanel(otherPanel, false);
          });
        }

        trigger.setAttribute('aria-expanded', String(opening));
        const icon = productEl.querySelector('.asbo__product-icon');
        if (icon) icon.textContent = opening ? '−' : '+';
        productEl.classList.toggle('is-open', opening);
        animatePanel(panel, opening);
        return;
      }

      const detailsOpen = event.target.closest('[data-product-details-open]');
      if (detailsOpen) {
        const productEl = detailsOpen.closest('[data-product]');
        const modal = productEl?.querySelector('[data-product-modal]');
        if (modal) {
          modal.hidden = false;
          modal.dataset.returnFocus = 'true';
          detailsOpen.dataset.modalReturnFocus = 'true';
          document.body.classList.add('asbo-modal-open');
          requestAnimationFrame(() => modal.querySelector('.asbo__modal-close')?.focus());
        }
        return;
      }

      const detailsClose = event.target.closest('[data-product-details-close]');
      if (detailsClose) {
        const modal = detailsClose.closest('[data-product-modal]');
        if (modal) {
          modal.hidden = true;
          document.body.classList.remove('asbo-modal-open');
          const productEl = modal.closest('[data-product]');
          const opener = productEl?.querySelector('[data-modal-return-focus="true"]');
          if (opener) {
            delete opener.dataset.modalReturnFocus;
            opener.focus();
          }
        }
        return;
      }

      const plus = event.target.closest('[data-qty-plus]');
      const minus = event.target.closest('[data-qty-minus]');
      if (plus || minus) {
        const input = event.target.closest('.asbo__quantity')?.querySelector('[data-qty-input]');
        if (!input) return;
        const next = clampQuantity(input.value) + (plus ? 1 : -1);
        input.value = Math.max(0, next);
        updateUI(input.closest('[data-product]'));
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const modal = root.querySelector('[data-product-modal]:not([hidden])');
      if (!modal) return;
      modal.hidden = true;
      document.body.classList.remove('asbo-modal-open');
      const productEl = modal.closest('[data-product]');
      const opener = productEl?.querySelector('[data-modal-return-focus="true"]');
      if (opener) {
        delete opener.dataset.modalReturnFocus;
        opener.focus();
      }
    });

    root.addEventListener('input', (event) => {
      if (event.target.matches('[data-qty-input]')) {
        event.target.value = clampQuantity(event.target.value);
        updateUI(event.target.closest('[data-product]'));
      }
      if (event.target.matches('[data-artwork-notes]')) saveState();
      if (event.target.matches('[data-previous-order-manual]')) {
        if (previousOrderSelect) previousOrderSelect.value = '';
        saveState();
      }
    });

    root.addEventListener('change', (event) => {
      if (event.target.matches('[data-decoration]')) updateUI(event.target.closest('[data-product]'));
      if (event.target.matches('[data-artwork-plan]')) {
        updateArtworkPlanUI();
        updateUI();
      }
      if (event.target.matches('[data-previous-order-select]')) {
        if (event.target.value && previousOrderManual) previousOrderManual.value = '';
        saveState();
      }
    });

    topNext?.addEventListener('click', goNext);
    stickyNext?.addEventListener('click', goNext);
    backButton?.addEventListener('click', () => setStep(currentStep - 1));


    // First-visit welcome toast: concise onboarding in place of the old Intro page.
    // The versioned key lets a future materially changed onboarding message be shown once again.
    const WELCOME_SEEN_KEY = ASBO_CONFIG.storageKey + '_welcome_seen_1';
    let welcomeTimer = null;

    const dismissWelcome = () => {
      if (!welcomeToast || welcomeToast.hidden) return;
      welcomeToast.classList.remove('is-visible');
      window.clearTimeout(welcomeTimer);
      window.setTimeout(() => { welcomeToast.hidden = true; }, 250);
      try { localStorage.setItem(WELCOME_SEEN_KEY, '1'); } catch (error) {}
    };

    if (welcomeToast) {
      let alreadySeen = false;
      try { alreadySeen = localStorage.getItem(WELCOME_SEEN_KEY) === '1'; } catch (error) {}

      const showWelcome = () => {
        welcomeToast.hidden = false;
        requestAnimationFrame(() => welcomeToast.classList.add('is-visible'));
        welcomeTimer = window.setTimeout(dismissWelcome, 10000);
      };

      if (!alreadySeen) {
        if (document.visibilityState === 'visible') {
          showWelcome();
        } else {
          const onVisible = () => {
            if (document.visibilityState !== 'visible') return;
            document.removeEventListener('visibilitychange', onVisible);
            showWelcome();
          };
          document.addEventListener('visibilitychange', onVisible);
        }
      }

      welcomeCloseBtn?.addEventListener('click', dismissWelcome);
    }

    restoreState();
    updateArtworkPlanUI();
    updateUI();
    setStep(1, false);
  });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll, { once: true });
  } else {
    initAll();
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
})();
