'use strict';

(() => {
    const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(value);

    const bindToast = (toast, duration = 6000) => {
        let timeoutId;
        const dismiss = () => {
            if (timeoutId) window.clearTimeout(timeoutId);
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 220);
        };
        const closeButton = toast.querySelector('[data-toast-close]');
        if (closeButton) closeButton.addEventListener('click', dismiss);
        if (duration > 0) timeoutId = window.setTimeout(dismiss, duration);
    };

    const showToast = (message, type = 'info', duration = 7000) => {
        const toast = document.createElement('div');
        const icon = document.createElement('span');
        const text = document.createElement('p');
        const closeButton = document.createElement('button');
        const validType = ['success', 'error', 'info'].includes(type) ? type : 'info';

        toast.className = `toast-notification toast-${validType}`;
        toast.setAttribute('role', validType === 'error' ? 'alert' : 'status');
        toast.dataset.toast = '';
        icon.className = 'toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = validType === 'success' ? '✓' : (validType === 'error' ? '!' : 'i');
        text.textContent = message;
        closeButton.className = 'toast-close';
        closeButton.type = 'button';
        closeButton.dataset.toastClose = '';
        closeButton.setAttribute('aria-label', 'Tutup notifikasi');
        closeButton.textContent = '×';
        toast.append(icon, text, closeButton);
        document.body.append(toast);
        bindToast(toast, duration);
    };

    const confirmDelete = (message) => new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'delete-confirm-dialog';
        dialog.setAttribute('aria-labelledby', 'delete-confirm-title');
        dialog.setAttribute('aria-describedby', 'delete-confirm-message');
        dialog.innerHTML = `
            <div class="delete-confirm-content">
                <span class="delete-confirm-icon" aria-hidden="true">!</span>
                <div>
                    <h2 id="delete-confirm-title">Hapus data ini?</h2>
                    <p id="delete-confirm-message"></p>
                </div>
            </div>
            <div class="delete-confirm-actions">
                <button class="button button-outline" type="button" data-delete-cancel>Batal</button>
                <button class="button button-danger" type="button" data-delete-confirm>Ya, hapus</button>
            </div>`;
        dialog.querySelector('#delete-confirm-message').textContent = message;
        document.body.append(dialog);

        let settled = false;
        const finish = (confirmed) => {
            if (settled) return;
            settled = true;
            if (dialog.open) dialog.close();
            dialog.remove();
            resolve(confirmed);
        };
        dialog.querySelector('[data-delete-cancel]').addEventListener('click', () => finish(false));
        dialog.querySelector('[data-delete-confirm]').addEventListener('click', () => finish(true));
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            finish(false);
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) finish(false);
        });
        dialog.showModal();
        dialog.querySelector('[data-delete-cancel]').focus();
    });

    document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }
            event.preventDefault();
            if (form.dataset.confirmPending === 'true') return;
            form.dataset.confirmPending = 'true';
            const submitter = event.submitter;
            const confirmed = await confirmDelete(form.dataset.confirmMessage || 'Data yang dihapus tidak dapat dikembalikan.');
            delete form.dataset.confirmPending;
            if (!confirmed) return;
            form.dataset.confirmed = 'true';
            if (typeof form.requestSubmit === 'function') {
                if (submitter && submitter.form === form) form.requestSubmit(submitter);
                else form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    const createOrderDraftState = (form) => {
        const state = {
            key: `seblak-order-draft:${window.location.pathname}${window.location.search}`,
            restoring: false,
            disabled: false
        };
        const isFormControl = (control) => control.name
            && !['csrf_token', 'action'].includes(control.name)
            && !['button', 'submit', 'reset', 'file'].includes(control.type);

        state.save = () => {
            if (state.restoring || state.disabled) return;
            const draft = {
                bowlCount: form.querySelectorAll('[data-seblak-bowl]').length,
                fields: Array.from(form.elements)
                    .filter(isFormControl)
                    .map((control) => ({
                        name: control.name,
                        type: control.type,
                        value: control.value,
                        checked: ['checkbox', 'radio'].includes(control.type) ? control.checked : undefined
                    }))
            };
            try {
                window.sessionStorage.setItem(state.key, JSON.stringify(draft));
            } catch (error) {
                state.disabled = true;
                console.error('Draft pesanan tidak bisa disimpan.', error);
            }
        };
        state.load = () => {
            if (state.disabled) return null;
            try {
                const serialized = window.sessionStorage.getItem(state.key);
                if (!serialized) return null;
                const draft = JSON.parse(serialized);
                if (!draft || !Array.isArray(draft.fields)) return null;
                return draft;
            } catch (error) {
                state.disabled = true;
                console.error('Draft pesanan tidak bisa dibaca.', error);
                return null;
            }
        };
        state.clear = () => {
            try {
                window.sessionStorage.removeItem(state.key);
            } catch (error) {
                console.error('Draft pesanan tidak bisa dihapus.', error);
            }
        };
        form.addEventListener('input', state.save);
        form.addEventListener('change', state.save);
        form.addEventListener('reset', () => window.setTimeout(state.clear, 0));
        return state;
    };

    const restoreOrderDraftFields = (form, draft) => {
        const controls = Array.from(form.elements);
        draft.fields.forEach((saved) => {
            if (!saved || typeof saved.name !== 'string' || typeof saved.value !== 'string') return;
            const matchingControls = controls.filter((control) => control.name === saved.name);
            if (saved.type === 'checkbox' || saved.type === 'radio') {
                const control = matchingControls.find((item) => item.value === saved.value);
                if (!control) return;
                control.checked = Boolean(saved.checked);
                control.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
            const control = matchingControls[0];
            if (!control) return;
            control.value = saved.value;
            control.dispatchEvent(new Event(control.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
        });
        form.dispatchEvent(new Event('input', { bubbles: true }));
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const isValidOrderDraft = (draft) => draft
        && Number.isInteger(draft.bowlCount)
        && draft.bowlCount >= 0
        && draft.bowlCount <= 20
        && Array.isArray(draft.fields);

    const orderDraftStates = new WeakMap();
    document.querySelectorAll('[data-order-form]').forEach((form) => {
        const draftState = createOrderDraftState(form);
        orderDraftStates.set(form, draftState);
        const calculate = () => {
            if (!draftState.restoring) {
                form.querySelectorAll('[data-seblak-bowl]').forEach((bowl) => {
                    bowl.querySelectorAll('[data-requires-topping]').forEach((group) => {
                        const requiredTopping = group.dataset.requiresTopping.toLocaleLowerCase();
                        const hasRequiredTopping = Array.from(bowl.querySelectorAll('[data-prasmanan-topping-card]')).some((card) => {
                            const toppingName = card.querySelector('h3')?.textContent.trim().toLocaleLowerCase() || '';
                            const quantity = Number(card.querySelector('input[data-topping-price]')?.value || 0);
                            return toppingName.includes(requiredTopping) && quantity > 0;
                        });
                        const disabled = !hasRequiredTopping;
                        group.disabled = disabled;
                        group.classList.toggle('is-dependent-disabled', disabled);
                        const hint = group.querySelector('[data-variant-dependency-hint]');
                        if (hint) hint.hidden = hasRequiredTopping;
                        group.querySelectorAll('select').forEach((select) => {
                            const disabledChanged = select.disabled !== disabled;
                            const selectionChanged = disabled && select.selectedIndex !== 0;
                            select.disabled = disabled;
                            if (disabled) select.selectedIndex = 0;
                            if (disabledChanged || selectionChanged) {
                                select.dispatchEvent(new Event('change'));
                            }
                        });
                        group.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                            input.disabled = disabled;
                            if (disabled) input.checked = false;
                        });
                    });
                });
            }

            let total = 0;
            const summaryItems = [];
            form.querySelectorAll('[data-seblak-bowl]').forEach((bowl) => {
                const quantityInput = bowl.querySelector('[data-prasmanan-quantity]');
                const selectedToppings = [];
                let bowlTotal = 0;
                bowl.querySelectorAll('[data-prasmanan-topping-card]').forEach((card) => {
                    const input = card.querySelector('input[data-topping-price]');
                    const quantity = Math.max(0, Number(input?.value || 0));
                    const subtotal = Number(input?.dataset.toppingPrice || 0) * quantity;
                    if (quantity < 1) return;
                    const pieces = quantity * Number(input?.dataset.pcsPerUnit || 0);
                    bowlTotal += subtotal;
                    selectedToppings.push({
                        name: card.querySelector('h3')?.textContent.trim() || 'Topping',
                        detail: `${quantity}x, ${pieces} pcs`,
                        amount: formatRupiah(subtotal)
                    });
                });

                const selectedVariants = [];
                if (selectedToppings.length) {
                    bowl.querySelectorAll('[data-prasmanan-variants] select[name*="[variants]"]').forEach((select) => {
                        const option = select.selectedOptions[0];
                        if (!option?.value) return;
                        bowlTotal += Number(option.dataset.extra || 0);
                        selectedVariants.push(
                            `${select.closest('fieldset')?.querySelector('legend')?.textContent.replace(/\s*\*$/, '').trim() || 'Varian'}: ${option.textContent.trim()}`
                        );
                    });
                    bowl.querySelectorAll('[data-prasmanan-variants] input[type="checkbox"][name*="[variants]"]:checked').forEach((input) => {
                        bowlTotal += Number(input.dataset.extra || 0);
                        selectedVariants.push(
                            `${input.closest('fieldset')?.querySelector('legend')?.textContent.replace(/\s*\*$/, '').trim() || 'Varian'}: ${input.closest('label')?.textContent.trim() || input.value}`
                        );
                    });
                    total += bowlTotal;
                    const bowlName = bowl.querySelector('input[name*="[name]"]')?.value.trim();
                    summaryItems.push({
                        name: bowlName ? `Seblak - ${bowlName}` : 'Seblak',
                        detail: selectedVariants.join(' · '),
                        amount: formatRupiah(bowlTotal)
                    });
                    selectedToppings.forEach((topping) => {
                        summaryItems.push({
                            name: `${topping.name}`,
                            detail: `${topping.detail} · ${topping.amount}`
                        });
                    });
                }
                if (quantityInput) {
                    quantityInput.value = selectedToppings.length ? '1' : '0';
                }
            });

            form.querySelectorAll('.product-card:not([data-prasmanan-topping-card])').forEach((card) => {
                const quantityInput = card.querySelector('input[name*="[quantity]"]');
                const quantity = Math.max(0, Number(quantityInput?.value || 0));
                let unitPrice = Number(card.dataset.productPrice || 0);

                card.querySelectorAll('select[name*="[variants]"]').forEach((select) => {
                    unitPrice += Number(select.selectedOptions[0]?.dataset.extra || 0);
                });
                card.querySelectorAll('input[type="checkbox"][name*="[variants]"]:checked').forEach((input) => {
                    unitPrice += Number(input.dataset.extra || 0);
                });
                card.querySelectorAll('input[data-topping-price]').forEach((input) => {
                    const selectedQuantity = Number(input.value || 0);
                    if (selectedQuantity < 1 || input.disabled) return;
                    unitPrice += Number(input.dataset.toppingPrice) * selectedQuantity;
                });

                const subtotal = unitPrice * quantity;
                total += subtotal;
                if (quantity > 0) {
                    summaryItems.push({
                        name: card.querySelector('h3')?.textContent.trim() || 'Menu',
                        detail: `${quantity}x`,
                        amount: formatRupiah(subtotal)
                    });
                }
            });

            const summary = form.querySelector('[data-order-summary]');
            const summaryList = form.querySelector('[data-order-summary-items]');
            if (summary && summaryList) {
                summaryList.replaceChildren();
                summaryItems.forEach((entry) => {
                    const item = document.createElement('li');
                    item.className = 'order-summary-item';

                    const details = document.createElement('div');
                    details.className = 'order-summary-item-details';
                    const name = document.createElement('strong');
                    name.textContent = entry.name;
                    details.append(name);
                    if (entry.detail) {
                        const description = document.createElement('span');
                        description.textContent = entry.detail;
                        details.append(description);
                    }

                    item.append(details);
                    if (entry.amount) {
                        const amount = document.createElement('span');
                        amount.className = 'order-summary-amount';
                        amount.textContent = entry.amount;
                        item.append(amount);
                    }
                    summaryList.append(item);
                });
                summary.hidden = summaryItems.length === 0;
            }

            const totalTarget = form.querySelector('[data-order-total]');
            if (totalTarget) totalTarget.textContent = formatRupiah(total);
        };

        form.addEventListener('input', calculate);
        form.addEventListener('change', calculate);
        form.addEventListener('submit', (event) => {
            calculate();
            for (const bowl of form.querySelectorAll('[data-seblak-bowl]')) {
                if (Number(bowl.querySelector('[data-prasmanan-quantity]')?.value || 0) < 1) continue;
                for (const group of bowl.querySelectorAll('[data-prasmanan-variants] [data-required-variant="1"]')) {
                    if (group.disabled) continue;
                    const selected = group.querySelector('select')?.value
                        || group.querySelector('input[type="checkbox"]:checked')?.value;
                    if (!selected) {
                        event.preventDefault();
                        showToast(`Pilih opsi wajib untuk ${group.querySelector('legend')?.textContent || 'Seblak'}.`, 'error', 0);
                        group.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                }
            }

            for (const card of form.querySelectorAll('.product-card:not([data-prasmanan-topping-card])')) {
                const quantity = Number(card.querySelector('input[name*="[quantity]"]')?.value || 0);
                if (quantity < 1) continue;

                for (const group of card.querySelectorAll('[data-required-variant="1"]')) {
                    const selected = group.querySelector('select')?.value
                        || group.querySelector('input[type="checkbox"]:checked')?.value;
                    if (!selected) {
                        event.preventDefault();
                        showToast(`Pilih opsi wajib untuk ${card.querySelector('h3')?.textContent || 'produk ini'}.`, 'error', 0);
                        group.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                }

            }
        });
        form.addEventListener('submit', (event) => {
            if (!event.defaultPrevented) draftState.clear();
        });
        calculate();
    });

    document.querySelectorAll('[data-variant-choice-editor]').forEach((editor) => {
        const rows = editor.querySelector('[data-variant-choice-rows]');
        const template = editor.querySelector('template[data-variant-choice-template]');
        if (!rows || !template) return;

        const syncRemoveButtons = () => {
            const disableRemove = rows.querySelectorAll('[data-variant-choice-row]').length <= 1;
            rows.querySelectorAll('[data-remove-variant-choice]').forEach((button) => {
                button.disabled = disableRemove;
            });
        };

        editor.addEventListener('click', (event) => {
            const addButton = event.target.closest('[data-add-variant-choice]');
            if (addButton) {
                const row = template.content.firstElementChild.cloneNode(true);
                rows.append(row);
                row.querySelector('input[name="pilihan_nama[]"]').focus();
                syncRemoveButtons();
                return;
            }

            const removeButton = event.target.closest('[data-remove-variant-choice]');
            if (removeButton && rows.querySelectorAll('[data-variant-choice-row]').length > 1) {
                removeButton.closest('[data-variant-choice-row]').remove();
                syncRemoveButtons();
            }
        });

        syncRemoveButtons();
    });

    const customSelects = [];
    let customSelectId = 0;

    const enhanceSelect = (select) => {
        if (select.multiple || select.size > 1 || select.classList.contains('custom-select-native')) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'custom-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        const value = document.createElement('span');
        value.className = 'custom-select-value';
        const arrow = document.createElement('span');
        arrow.className = 'custom-select-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        trigger.append(value, arrow);

        const listbox = document.createElement('div');
        listbox.className = 'custom-select-options';
        listbox.setAttribute('role', 'listbox');
        listbox.id = `custom-select-options-${++customSelectId}`;
        trigger.id = `custom-select-trigger-${customSelectId}`;
        trigger.setAttribute('aria-controls', listbox.id);
        listbox.setAttribute('aria-labelledby', trigger.id);

        const optionButtons = Array.from(select.options, (option, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'custom-select-option';
            button.setAttribute('role', 'option');
            button.setAttribute('tabindex', '-1');
            button.dataset.optionIndex = String(index);
            button.textContent = option.textContent.trim();
            button.disabled = option.disabled;
            listbox.append(button);
            return button;
        });

        const label = select.getAttribute('aria-label');
        const labelledBy = select.getAttribute('aria-labelledby');
        const describedBy = select.getAttribute('aria-describedby');
        const groupLabel = select.closest('fieldset')?.querySelector('legend')?.textContent.trim();
        if (label) trigger.setAttribute('aria-label', label);
        else if (groupLabel) trigger.setAttribute('aria-label', groupLabel);
        if (labelledBy) trigger.setAttribute('aria-labelledby', labelledBy);
        if (describedBy) trigger.setAttribute('aria-describedby', describedBy);
        if (select.required) trigger.setAttribute('aria-required', 'true');

        const parent = select.parentNode;
        parent.insertBefore(wrapper, select);
        wrapper.append(trigger, select, listbox);
        select.classList.add('custom-select-native');
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;
        listbox.hidden = true;

        const syncValue = () => {
            const selected = select.selectedOptions[0];
            value.textContent = selected ? selected.textContent.trim() : 'Pilih...';
            trigger.disabled = select.disabled || optionButtons.every((button) => button.disabled);
            optionButtons.forEach((button, index) => {
                const isSelected = select.options[index].selected;
                button.setAttribute('aria-selected', String(isSelected));
                button.classList.toggle('is-selected', isSelected);
            });
        };

        const close = (restoreFocus = false) => {
            wrapper.classList.remove('is-open', 'opens-up');
            listbox.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (listbox.parentNode === document.body) wrapper.append(listbox);
            listbox.style.removeProperty('left');
            listbox.style.removeProperty('top');
            listbox.style.removeProperty('width');
            listbox.style.removeProperty('max-height');
            if (restoreFocus) trigger.focus();
        };

        const positionListbox = () => {
            if (listbox.hidden) return;
            const bounds = trigger.getBoundingClientRect();
            const width = Math.min(bounds.width, window.innerWidth - 16);
            const maxHeight = Math.min(260, window.innerHeight * .45);
            const height = Math.min(listbox.scrollHeight, maxHeight);
            const openUp = window.innerHeight - bounds.bottom < height + 12 && bounds.top > height + 12;
            listbox.style.width = `${width}px`;
            listbox.style.maxHeight = `${maxHeight}px`;
            listbox.style.left = `${Math.max(8, Math.min(bounds.left, window.innerWidth - width - 8))}px`;
            listbox.style.top = `${openUp
                ? Math.max(8, bounds.top - height - 6)
                : Math.min(window.innerHeight - height - 8, bounds.bottom + 6)}px`;
            wrapper.classList.toggle('opens-up', openUp);
        };

        const enabledOptionIndexes = () => optionButtons
            .map((button, index) => button.disabled ? -1 : index)
            .filter((index) => index >= 0);

        const focusOption = (index) => {
            const enabled = enabledOptionIndexes();
            if (!enabled.length) return;
            const preferred = enabled.find((enabledIndex) => enabledIndex >= index);
            optionButtons[preferred ?? enabled[0]].focus();
        };

        const moveFocus = (currentIndex, direction) => {
            const enabled = enabledOptionIndexes();
            if (!enabled.length) return;
            const currentPosition = enabled.indexOf(currentIndex);
            const nextPosition = currentPosition < 0
                ? (direction > 0 ? 0 : enabled.length - 1)
                : (currentPosition + direction + enabled.length) % enabled.length;
            optionButtons[enabled[nextPosition]].focus();
        };

        const open = (index = select.selectedIndex) => {
            if (trigger.disabled) return;
            customSelects.forEach((item) => {
                if (item.wrapper !== wrapper) item.close();
            });
            document.body.append(listbox);
            listbox.hidden = false;
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            positionListbox();
            focusOption(index);
        };

        const chooseOption = (button) => {
            if (button.disabled) return;
            select.selectedIndex = Number(button.dataset.optionIndex);
            syncValue();
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            close(true);
        };

        trigger.addEventListener('click', () => {
            if (listbox.hidden) open();
            else close();
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                const enabled = enabledOptionIndexes();
                open(event.key === 'ArrowUp' ? enabled[enabled.length - 1] ?? 0 : select.selectedIndex);
            } else if (event.key === 'Escape') {
                close();
            }
        });
        listbox.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('.custom-select-option') : null;
            if (target) chooseOption(target);
        });
        listbox.addEventListener('keydown', (event) => {
            const target = event.target instanceof Element ? event.target.closest('.custom-select-option') : null;
            if (!target) return;
            const currentIndex = Number(target.dataset.optionIndex);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Home' || event.key === 'End') {
                event.preventDefault();
                if (event.key === 'Home') focusOption(0);
                else if (event.key === 'End') {
                    const enabled = enabledOptionIndexes();
                    focusOption(enabled[enabled.length - 1] ?? 0);
                } else {
                    moveFocus(currentIndex, event.key === 'ArrowDown' ? 1 : -1);
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                close(true);
            } else if (event.key === 'Tab') {
                const tabStops = Array.from(document.querySelectorAll(
                    'a[href]:not([tabindex="-1"]), button:not(:disabled):not([tabindex="-1"]), input:not(:disabled):not([type="hidden"]):not([tabindex="-1"]), select:not(:disabled):not(.custom-select-native):not([tabindex="-1"]), textarea:not(:disabled):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"]):not(:disabled)'
                )).filter((element) => element.getClientRects().length);
                const triggerIndex = tabStops.indexOf(trigger);
                const next = tabStops[triggerIndex + (event.shiftKey ? -1 : 1)];
                close();
                if (next) {
                    event.preventDefault();
                    next.focus();
                }
            }
        });
        select.addEventListener('change', syncValue);
        let resetHandler = null;
        if (select.form) {
            resetHandler = () => window.setTimeout(syncValue, 0);
            select.form.addEventListener('reset', resetHandler);
        }

        customSelects.push({ select, wrapper, listbox, close, position: positionListbox, resetHandler, syncValue });
        syncValue();
    };

    document.querySelectorAll('[data-payment-method-form]').forEach((form) => {
        const typeSelect = form.querySelector('[data-payment-type]');
        if (!typeSelect) return;
        const updatePaymentFields = () => {
            form.querySelectorAll('[data-payment-type-fields]').forEach((fields) => {
                const active = fields.dataset.paymentTypeFields === typeSelect.value;
                fields.hidden = !active;
                fields.querySelectorAll('[data-payment-required]').forEach((input) => {
                    input.required = active;
                });
            });
            const imageInput = form.querySelector('[data-payment-image]');
            if (imageInput) {
                imageInput.required = typeSelect.value === 'gambar' && imageInput.dataset.hasImage !== '1';
            }
        };
        typeSelect.addEventListener('change', updatePaymentFields);
        updatePaymentFields();
    });

    document.querySelectorAll('input[type="file"][name="gambar"]').forEach((input) => {
        const container = input.closest('.catalog-image-field, .payment-type-fields');
        if (!container) return;

        let preview = container.querySelector('img.catalog-form-image, img.payment-method-image');
        const originalSource = preview?.getAttribute('src') ?? '';
        const previewClass = input.closest('[data-payment-type-fields]')
            ? 'payment-method-image'
            : 'catalog-form-image';
        let previewUrl = '';

        input.addEventListener('change', () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = '';
            }

            const file = input.files[0];
            if (!file) {
                if (preview && originalSource) preview.src = originalSource;
                else if (preview) {
                    preview.remove();
                    preview = null;
                }
                return;
            }

            previewUrl = URL.createObjectURL(file);
            if (!preview) {
                preview = document.createElement('img');
                preview.className = previewClass;
                preview.alt = 'Pratinjau foto baru';
                container.append(preview);
            }
            preview.src = previewUrl;
        });
    });

    document.querySelectorAll('[data-payment-proof-form]').forEach((form) => {
        const proofInput = form.querySelector('[data-payment-proof-input]');
        const submitButton = form.querySelector('[data-payment-proof-submit]');
        if (!proofInput || !submitButton) return;
        const updateSubmitButton = () => {
            submitButton.disabled = proofInput.files.length === 0;
        };
        proofInput.addEventListener('change', updateSubmitButton);
        updateSubmitButton();
    });

    document.querySelectorAll('[data-order-live]').forEach((livePanel) => {
        const status = livePanel.querySelector('[data-order-live-status]');
        const soundButton = livePanel.querySelector('[data-enable-order-sound]');
        const endpoint = livePanel.dataset.endpoint;
        let latestId = Number(livePanel.dataset.latestId || 0);
        let soundEnabled = false;
        let audioContext = null;
        let alertAudio = livePanel.dataset.audioUrl ? new Audio(livePanel.dataset.audioUrl) : null;
        let checking = false;
        let refreshing = false;

        if (!status || !soundButton || !endpoint) return;

        if (alertAudio) {
            alertAudio.preload = 'auto';
            alertAudio.volume = 1;
        }

        try {
            soundEnabled = window.localStorage.getItem('seblak-order-sound') === 'enabled';
        } catch (error) {
            console.error('Preferensi suara notifikasi tidak bisa dibaca.', error);
        }
        soundButton.setAttribute('aria-pressed', String(soundEnabled));
        soundButton.textContent = soundEnabled ? 'Suara aktif' : 'Aktifkan suara';

        const orderNoticeKey = 'seblak-new-order-notice';
        try {
            const savedNotice = window.sessionStorage.getItem(orderNoticeKey);
            if (savedNotice) {
                window.sessionStorage.removeItem(orderNoticeKey);
                const notice = JSON.parse(savedNotice);
                if (notice && Array.isArray(notice.codes) && notice.codes.length > 0) {
                    const codes = notice.codes.filter((code) => typeof code === 'string').slice(0, 3);
                    if (codes.length === 1) {
                        showToast(`Pesanan ${codes[0]} baru masuk.`, 'success', 9000);
                    } else if (codes.length > 1) {
                        const extraCount = notice.codes.length - codes.length;
                        const extraText = extraCount > 0 ? ` dan ${extraCount} pesanan lainnya` : '';
                        showToast(`${codes.length} pesanan baru masuk: ${codes.join(', ')}${extraText}.`, 'success', 9000);
                    }
                }
            }
        } catch (error) {
            console.error('Notifikasi pesanan baru tidak bisa dibaca.', error);
        }

        soundButton.addEventListener('click', async () => {
            try {
                if (window.AudioContext && !audioContext) {
                    audioContext = new window.AudioContext();
                }
                if (audioContext && audioContext.state !== 'running') {
                    await audioContext.resume();
                }
                soundEnabled = true;
                window.localStorage.setItem('seblak-order-sound', 'enabled');
                soundButton.setAttribute('aria-pressed', 'true');
                soundButton.textContent = 'Suara aktif';
                status.textContent = 'Suara aktif; pesanan baru dicek otomatis setiap 5 detik.';
            } catch (error) {
                console.error('Suara notifikasi tidak bisa diaktifkan.', error);
                status.textContent = 'Browser belum mengizinkan suara. Periksa pengaturan suara browser.';
            }
        });

        const playFallbackTone = async () => {
            const AudioContextConstructor = window.AudioContext;
            if (!AudioContextConstructor) {
                throw new Error('Browser tidak mendukung suara notifikasi.');
            }
            if (!audioContext) {
                audioContext = new AudioContextConstructor();
            }
            if (audioContext.state !== 'running') {
                await audioContext.resume();
            }
            if (audioContext.state !== 'running') {
                throw new Error('Aktifkan suara notifikasi melalui tombol terlebih dahulu.');
            }
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            const startAt = audioContext.currentTime;
            oscillator.frequency.setValueAtTime(880, startAt);
            gain.gain.setValueAtTime(0.7, startAt);
            gain.gain.exponentialRampToValueAtTime(0.001, startAt + 0.45);
            oscillator.connect(gain);
            gain.connect(audioContext.destination);
            oscillator.start(startAt);
            oscillator.stop(startAt + 0.45);
            await new Promise((resolve) => oscillator.addEventListener('ended', resolve, { once: true }));
        };

        const playNotification = async () => {
            if (!soundEnabled) return;
            if (!alertAudio) {
                await playFallbackTone();
                return;
            }
            alertAudio.currentTime = 0;
            alertAudio.volume = 1;
            await alertAudio.play();
            await new Promise((resolve) => {
                alertAudio.addEventListener('ended', resolve, { once: true });
                window.setTimeout(resolve, 15000);
            });
        };

        const checkForOrders = async () => {
            if (checking || refreshing) return;
            checking = true;
            try {
                const response = await fetch(`${endpoint}?after_id=${encodeURIComponent(String(latestId))}`, {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                if (!response.ok) {
                    throw new Error(`Pengecekan pesanan gagal (HTTP ${response.status}).`);
                }
                const result = await response.json();
                if (!Array.isArray(result.orders)) {
                    throw new Error('Respons status pesanan tidak valid.');
                }
                if (!result.orders.length) return;
                latestId = Math.max(latestId, ...result.orders.map((order) => Number(order.id) || 0));
                refreshing = true;
                status.textContent = `${result.orders.length} pesanan baru masuk. Memperbarui daftar...`;
                let noticeStored = false;
                try {
                    const codes = result.orders
                        .map((order) => order.kode_pesanan)
                        .filter((code) => typeof code === 'string' && code !== '');
                    if (codes.length > 0) {
                        window.sessionStorage.setItem(orderNoticeKey, JSON.stringify({ codes }));
                        noticeStored = true;
                    }
                } catch (error) {
                    console.error('Notifikasi pesanan baru tidak bisa disimpan.', error);
                    showToast(`${result.orders.length} pesanan baru masuk.`, 'success', 9000);
                }
                try {
                    await playNotification();
                } catch (error) {
                    console.error('Suara pesanan baru tidak bisa diputar.', error);
                    status.textContent = 'Pesanan baru masuk. Aktifkan suara notifikasi jika belum diizinkan browser.';
                }
                if (!noticeStored) await new Promise((resolve) => window.setTimeout(resolve, 7000));
                window.location.reload();
            } catch (error) {
                console.error('Pengecekan pesanan realtime gagal.', error);
                status.textContent = 'Pengecekan otomatis terganggu. Akan mencoba lagi.';
            } finally {
                checking = false;
            }
        };

        window.setInterval(checkForOrders, 5000);
        checkForOrders();
    });

    document.querySelectorAll('select').forEach(enhanceSelect);

    document.addEventListener('click', (event) => {
        customSelects.forEach((item) => {
            if (!item.wrapper.contains(event.target) && !item.listbox.contains(event.target)) item.close();
        });
    });
    const repositionCustomSelects = () => customSelects.forEach((item) => item.position());
    window.addEventListener('scroll', repositionCustomSelects, true);
    window.addEventListener('resize', repositionCustomSelects);

    const addListSearch = (target, labelText, placeholderText, emptyText, filterItems) => {
        const label = document.createElement('label');
        label.className = 'list-search';

        const caption = document.createElement('span');
        caption.textContent = labelText;

        const input = document.createElement('input');
        input.className = 'list-search-input';
        input.type = 'search';
        input.placeholder = placeholderText;
        input.setAttribute('aria-label', labelText);
        label.append(caption, input);
        target.parentNode.insertBefore(label, target);

        const empty = document.createElement('p');
        empty.className = 'list-search-empty';
        empty.setAttribute('role', 'status');
        empty.hidden = true;
        empty.textContent = emptyText;
        target.insertAdjacentElement('afterend', empty);

        input.addEventListener('input', () => {
            const query = input.value.trim().toLocaleLowerCase();
            const visibleCount = filterItems(query);
            empty.hidden = !query || visibleCount > 0;
        });
    };

    document.querySelectorAll('[data-order-menu-toolbar]').forEach((toolbar) => {
        const search = toolbar.querySelector('[data-order-menu-search]');
        const category = toolbar.querySelector('[data-order-menu-category]');
        const grid = toolbar.parentElement.querySelector('.product-grid');
        const orderForm = toolbar.closest('[data-order-form]');
        if (!search || !category || !grid) return;

        const getMenuItems = () => Array.from(grid.children)
            .filter((item) => item.matches('[data-order-menu-item]'));
        const empty = document.createElement('p');
        empty.className = 'list-search-empty';
        empty.setAttribute('role', 'status');
        empty.textContent = 'Pilih kategori untuk melihat menu.';
        empty.hidden = false;
        grid.insertAdjacentElement('afterend', empty);

        const filterMenu = () => {
            const query = search.value.trim().toLocaleLowerCase();
            const addBowlButton = orderForm?.querySelector('[data-add-seblak-bowl]');
            let visibleCount = 0;
            let visibleSeblakBowl = false;
            getMenuItems().forEach((item) => {
                const categoryMatches = category.value !== ''
                    && item.dataset.menuCategory === category.value;
                const isBowl = item.matches('.seblak-bowl');
                const itemName = isBowl
                    ? item.querySelector('.seblak-bowl-heading h3')?.textContent
                    : [
                        item.querySelector('.product-card-heading h3')?.textContent,
                        item.querySelector('.category-caption')?.textContent
                    ].filter(Boolean).join(' ');
                const nameMatches = !query || (itemName || '').toLocaleLowerCase().includes(query);
                let toppingMatches = false;
                if (isBowl) {
                    item.querySelectorAll('[data-prasmanan-topping-card]').forEach((card) => {
                        const toppingName = card.querySelector('h3')?.textContent.trim().toLocaleLowerCase() || '';
                        const matches = !query || toppingName.includes(query);
                        card.hidden = Boolean(query && !nameMatches && !matches);
                        toppingMatches = toppingMatches || matches;
                    });
                }
                const searchMatches = nameMatches || toppingMatches;
                item.hidden = !categoryMatches || !searchMatches;
                if (!item.hidden) {
                    visibleCount += 1;
                    visibleSeblakBowl = visibleSeblakBowl || isBowl;
                }
            });
            if (addBowlButton) {
                addBowlButton.hidden = !visibleSeblakBowl;
            }
            empty.textContent = category.value
                ? 'Tidak ada menu yang cocok.'
                : 'Pilih kategori untuk melihat menu.';
            empty.hidden = Boolean(category.value) && visibleCount > 0;
        };

        search.addEventListener('input', filterMenu);
        category.addEventListener('change', filterMenu);
        orderForm?.addEventListener('order-menu-updated', filterMenu);
        filterMenu();
    });

    const quantitySteppers = [];
    const enhanceQuantityStepper = (stepper) => {
        const input = stepper.querySelector('.quantity-step-input');
        const value = stepper.querySelector('.quantity-step-value');
        if (!input || !value) return;

        const buttons = Array.from(stepper.querySelectorAll('[data-quantity-adjust]'));
        const minimum = Number(input.dataset.quantityMin || 0);
        const maximum = Number(input.dataset.quantityMax || 0);
        const syncValue = () => {
            const current = Number(input.value || minimum);
            value.textContent = String(current);
            buttons.forEach((button) => {
                const next = current + Number(button.dataset.quantityAdjust);
                button.disabled = next < minimum || next > maximum;
            });
        };

        stepper.addEventListener('click', (event) => {
            const button = event.target instanceof Element
                ? event.target.closest('[data-quantity-adjust]')
                : null;
            if (!button || button.disabled) return;

            const next = Math.min(maximum, Math.max(minimum, Number(input.value || minimum) + Number(button.dataset.quantityAdjust)));
            if (next === Number(input.value || minimum)) return;
            input.value = String(next);
            syncValue();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
        input.addEventListener('input', syncValue);

        let resetHandler = null;
        if (input.form) {
            resetHandler = () => window.setTimeout(syncValue, 0);
            input.form.addEventListener('reset', resetHandler);
        }
        quantitySteppers.push({ stepper, resetHandler });
        syncValue();
    };

    document.querySelectorAll('.quantity-stepper').forEach(enhanceQuantityStepper);

    document.querySelectorAll('[data-order-form]').forEach((form) => {
        const template = form.querySelector('template[data-seblak-bowl-template]');
        const addButton = form.querySelector('[data-add-seblak-bowl]');
        const grid = template?.closest('.product-grid');
        const draftState = orderDraftStates.get(form);
        const savedDraft = draftState?.load();
        const shouldRestoreDraft = isValidOrderDraft(savedDraft);
        if (!template || !addButton || !grid) {
            if (shouldRestoreDraft) {
                draftState.restoring = true;
                restoreOrderDraftFields(form, savedDraft);
                draftState.restoring = false;
                form.dispatchEvent(new Event('input', { bubbles: true }));
            }
            return;
        }

        let nextIndex = 0;
        const maxBowls = 20;
        const updateBowls = () => {
            const bowls = grid.querySelectorAll('[data-seblak-bowl]');
            addButton.disabled = bowls.length >= maxBowls;
        };
        const addBowl = () => {
            if (grid.querySelectorAll('[data-seblak-bowl]').length >= maxBowls) return;

            const bowl = template.content.firstElementChild.cloneNode(true);
            const index = String(nextIndex++);
            bowl.dataset.bowlIndex = index;
            bowl.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace(/__INDEX__/g, index);
            });
            grid.append(bowl);
            bowl.querySelectorAll('select').forEach(enhanceSelect);
            bowl.querySelectorAll('.quantity-stepper').forEach(enhanceQuantityStepper);
            updateBowls();
            form.dispatchEvent(new Event('input', { bubbles: true }));
            form.dispatchEvent(new Event('order-menu-updated'));
        };

        addButton.addEventListener('click', addBowl);
        grid.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-remove-seblak-bowl]');
            if (!removeButton) return;
            const bowl = removeButton.closest('[data-seblak-bowl]');
            const removedSelects = new Set(bowl?.querySelectorAll('select') || []);
            const removedSteppers = new Set(bowl?.querySelectorAll('.quantity-stepper') || []);
            for (let index = customSelects.length - 1; index >= 0; index -= 1) {
                const item = customSelects[index];
                if (!removedSelects.has(item.select)) continue;
                item.close();
                item.select.removeEventListener('change', item.syncValue);
                if (item.resetHandler) form.removeEventListener('reset', item.resetHandler);
                customSelects.splice(index, 1);
            }
            for (let index = quantitySteppers.length - 1; index >= 0; index -= 1) {
                const item = quantitySteppers[index];
                if (!removedSteppers.has(item.stepper)) continue;
                if (item.resetHandler) form.removeEventListener('reset', item.resetHandler);
                quantitySteppers.splice(index, 1);
            }
            bowl?.remove();
            const remainingBowls = Array.from(grid.querySelectorAll('[data-seblak-bowl]'));
            remainingBowls.forEach((remainingBowl, index) => {
                const previousIndex = remainingBowl.dataset.bowlIndex;
                remainingBowl.querySelectorAll('[name]').forEach((input) => {
                    input.name = input.name.replace(`[bowls][${previousIndex}]`, `[bowls][${index}]`);
                });
                remainingBowl.dataset.bowlIndex = String(index);
            });
            nextIndex = remainingBowls.length;
            updateBowls();
            form.dispatchEvent(new Event('input', { bubbles: true }));
            form.dispatchEvent(new Event('order-menu-updated'));
        });
        if (shouldRestoreDraft) draftState.restoring = true;
        addBowl();
        if (shouldRestoreDraft) {
            while (grid.querySelectorAll('[data-seblak-bowl]').length < savedDraft.bowlCount) {
                addBowl();
            }
            while (grid.querySelectorAll('[data-seblak-bowl]').length > savedDraft.bowlCount) {
                grid.querySelector('[data-seblak-bowl] [data-remove-seblak-bowl]')?.click();
            }
            restoreOrderDraftFields(form, savedDraft);
            draftState.restoring = false;
            form.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    document.querySelectorAll('.table-wrap').forEach((tableWrap) => {
        const table = tableWrap.querySelector('table');
        if (!table) return;

        const rows = Array.from(table.querySelectorAll('tbody tr'))
            .filter((row) => !row.querySelector('.empty-cell'));
        if (!rows.length) return;

        const groups = [];
        rows.forEach((row) => {
            if (row.classList.contains('order-detail-row') && groups.length) {
                groups[groups.length - 1].push(row);
            } else {
                groups.push([row]);
            }
        });

        addListSearch(
            tableWrap,
            'Cari di daftar',
            'Cari data di daftar ini',
            'Tidak ada data yang cocok.',
            (query) => {
                let visibleCount = 0;
                groups.forEach((group) => {
                    const matches = !query || group
                        .map((row) => row.textContent)
                        .join(' ')
                        .toLocaleLowerCase()
                        .includes(query);
                    group.forEach((row) => {
                        row.hidden = !matches;
                    });
                    if (matches) visibleCount += 1;
                });
                return visibleCount;
            }
        );
    });

    const appPage = document.querySelector('.app-page');
    const navToggle = document.querySelector('[data-nav-toggle]');
    const navClose = document.querySelector('[data-nav-close]');
    const navBackdrop = document.querySelector('[data-nav-backdrop]');
    const sidebar = document.getElementById('admin-navigation');

    if (appPage && navToggle && navClose && navBackdrop && sidebar) {
        const setMenuOpen = (isOpen) => {
            appPage.classList.toggle('nav-open', isOpen);
            navToggle.setAttribute('aria-expanded', String(isOpen));
            navToggle.setAttribute('aria-label', isOpen ? 'Tutup menu' : 'Buka menu');
            sidebar.setAttribute('aria-hidden', String(!isOpen));
            document.body.style.overflow = isOpen ? 'hidden' : '';
            (isOpen ? navClose : navToggle).focus();
        };

        navToggle.addEventListener('click', () => {
            setMenuOpen(navToggle.getAttribute('aria-expanded') !== 'true');
        });
        navClose.addEventListener('click', () => setMenuOpen(false));
        navBackdrop.addEventListener('click', () => setMenuOpen(false));
        sidebar.querySelectorAll('.side-nav a').forEach((link) => {
            link.addEventListener('click', () => setMenuOpen(false));
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && navToggle.getAttribute('aria-expanded') === 'true') {
                setMenuOpen(false);
            }
        });

        const desktopNavigation = window.matchMedia('(min-width: 1100px)');
        const syncNavigationAccessibility = () => {
            if (desktopNavigation.matches) {
                appPage.classList.remove('nav-open');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.setAttribute('aria-label', 'Buka menu');
                sidebar.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = '';
                return;
            }

            sidebar.setAttribute('aria-hidden', String(navToggle.getAttribute('aria-expanded') !== 'true'));
        };
        syncNavigationAccessibility();
        desktopNavigation.addEventListener('change', syncNavigationAccessibility);
    }

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        bindToast(toast, toast.classList.contains('toast-error') ? 0 : 6000);
    });
})();
