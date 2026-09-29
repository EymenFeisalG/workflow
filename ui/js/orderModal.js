/**
 * Order Modal & Delegation med smart minne och historik
 */
(function ($) {
    'use strict';

    const DRAFT_STORAGE_KEY = 'workflow_order_draft_v1';
    const LAST_DELEGATED_STORAGE_KEY = 'workflow_last_delegated_worker';

    let currentUserId = (window.CurrentUser && window.CurrentUser.id) ? parseInt(window.CurrentUser.id, 10) : 1;
    let currentUserName = (window.CurrentUser && window.CurrentUser.username) ? window.CurrentUser.username : 'admin';
    let selectedWorkerId = currentUserId;
    let teamWorkers = window.TeamWorkers || [];

    let selectedCustomerId = 0;
    let orderSteps = [];
    let orderImages = [];
    let editingOrderId = null;
    let existingImages = [];
    let removedImageIds = [];
    let searchDebounceTimer = null;
    let draftSaveTimer = null;

    window.openOrderModal = function () {
        if (editingOrderId !== null) {
            editingOrderId = null;
            resetOrderModal();
        }
        setModalMode(false);
        $('#orderModalOverlay').addClass('active');
        $('body').css('overflow', 'hidden');

        // Återställ utkast om det finns och formuläret inte redan är fyllt
        restoreDraft();

        if ($('#orderTitle').val().trim() !== '') {
            $('#orderDesc').focus();
        } else {
            $('#orderTitle').focus();
        }
    };

    window.closeOrderModal = function () {
        if ($('#orderSubmitBtn').hasClass('loading')) return;
        if (editingOrderId === null) saveDraft();

        $('#orderModalOverlay').removeClass('active');
        $('body').css('overflow', '');
        if (editingOrderId !== null) {
            editingOrderId = null;
            resetOrderModal();
            setModalMode(false);
            restoreDraft();
        }
    };

    function setModalMode(editing) {
        $('#orderModalHeading').text(editing ? 'Korrigera uppgift' : 'Ny uppgift');
        $('#orderSubmitBtn > span:last-child').text(editing ? 'Spara ändringar' : 'Skapa uppgift');
        $('#orderFooterStatus > span:last-child').text(editing ? 'Ändringar sparas när du klickar på Spara' : 'Utkast sparas automatiskt');
        $('#orderDraftBadge, #draftAlertBar').toggle(!editing && $('#orderDraftBadge').is(':visible'));
        $('#orderContactHint').text(editing ? 'Ändringar i kontaktuppgifter gäller bara denna uppgift.' : 'Kontaktpersoner med namn sparas automatiskt och kan väljas till fler uppgifter.');
        $('#correctionPasswordClear').prop('hidden', !editing);
        $('#orderClearPassword').prop('checked', false);
        $('#orderAdminPass').attr('placeholder', editing ? 'Lämna tomt för att behålla' : 'Lösenord');
        if (!editing) $('#orderExistingImages').prop('hidden', true).empty();
    }

    window.openOrderCorrection = function (id) {
        const orderId = parseInt(id, 10);
        if (!orderId || !$('#orderModalOverlay').length) return;
        $.ajax({ url: 'php/functions/getOrderForEdit.php', type: 'POST', dataType: 'json', data: { orderId: orderId, csrf: window.WorkflowCsrf } })
            .done(function (res) {
                if (!res || !res.success || !res.order) return;
                const order = res.order;
                if (editingOrderId === null && isFormDirty()) saveDraft();
                resetOrderModal();
                editingOrderId = orderId;
                setModalMode(true);
                $('#orderTitle').val(order.title || '');
                $('#orderCompanyName').val(order.contact_name || '');
                $('#orderCompanyDomain').val(order.domain || '');
                $('#orderOrg').val(order.contact_org || '');
                $('#orderContact').val(order.contact_details || '');
                $('#orderAdminUser').val(order.admin_username || '');
                $('#orderAdminPass').val('');
                $('#correctionPasswordClear').prop('hidden', !order.has_password);
                $('#orderDesc').val(order.description || '');
                $('#orderAsapCheck').prop('checked', order.priority === 'asap');
                $('#asapSwitchWrapper').toggleClass('active', order.priority === 'asap');
                $('#orderContactDetails').prop('open', Boolean(order.contact_name || order.contact_org || order.contact_details || order.domain || order.admin_username || order.has_password));
                $('.techCredentialsDetails').prop('open', Boolean(order.admin_username || order.has_password));
                const worker = teamWorkers.find(w => parseInt(w.id, 10) === order.worker_id);
                selectWorker(worker || { id: order.worker_id, username: 'Användare ' + order.worker_id }, true);
                orderSteps = (order.steps || []).map(s => ({ id: parseInt(s.id, 10), text: s.text, completed: parseInt(s.completed, 10) === 1 }));
                existingImages = order.images || [];
                removedImageIds = [];
                renderSteps();
                renderExistingImages();
                $('#orderModalOverlay').addClass('active');
                $('body').css('overflow', 'hidden');
                $('#orderTitle').trigger('focus');
            })
            .fail(function (xhr) {
                const error = (xhr.responseJSON && xhr.responseJSON.error) || 'Uppgiften kunde inte öppnas för korrigering.';
                alert(error);
            });
    };

    function isFormDirty() {
        return $('#orderTitle').val().trim() !== '' ||
               $('#orderCompanyName').val().trim() !== '' ||
               $('#orderCompanyDomain').val().trim() !== '' ||
               $('#orderOrg').val().trim() !== '' ||
               $('#orderContact').val().trim() !== '' ||
               $('#orderAdminUser').val().trim() !== '' ||
               $('#orderAdminPass').val().trim() !== '' ||
               $('#orderAsapCheck').is(':checked') ||
               $('#orderDesc').val().trim() !== '' ||
               orderSteps.length > 0 ||
               orderImages.length > 0 ||
               selectedCustomerId > 0 ||
               selectedWorkerId !== currentUserId;
    }

    function resetOrderModal() {
        $('#orderModalForm')[0].reset();
        selectedCustomerId = 0;
        orderSteps = [];
        orderImages = [];
        existingImages = [];
        removedImageIds = [];
        $('#orderExistingImages').prop('hidden', true).empty();
        $('#customerSelectedBadge').hide();
        $('#customerDropdown').removeClass('open').empty();
        $('#asapSwitchWrapper').removeClass('active');
        $('#orderContactDetails').prop('open', false);
        $('.techCredentialsDetails').prop('open', false);
        renderSteps();
        renderImagePreviews();
        $('#orderSubmitBtn').removeClass('loading').prop('disabled', false);

        // Återställ till mig själv som standard
        selectWorker({ id: currentUserId, username: currentUserName, is_me: true }, true);
    }

    // ==========================================
    // Säkert Utkastsystem (Drafts via localStorage)
    // ==========================================
    function saveDraft() {
        if (editingOrderId !== null) return;
        if (!isFormDirty()) {
            clearDraft(true);
            return;
        }

        const draft = {
            orderTitle: $('#orderTitle').val().trim(),
            selectedCustomerId: selectedCustomerId,
            selectedCustomerName: $('#selectedCustomerName').text(),
            companyName: $('#orderCompanyName').val().trim(),
            companyDomain: $('#orderCompanyDomain').val().trim(),
            org: $('#orderOrg').val().trim(),
            contact: $('#orderContact').val().trim(),
            adminUser: $('#orderAdminUser').val().trim(),
            adminPass: $('#orderAdminPass').val().trim(),
            worker: selectedWorkerId,
            workerName: $('#delegationPillName').text(),
            asap: $('#orderAsapCheck').is(':checked'),
            desc: $('#orderDesc').val(),
            steps: orderSteps,
            savedAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        };

        try {
            localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
            $('#orderDraftBadge').fadeIn(150);
            updateSidebarIndicator(true);
        } catch (e) {
            console.warn('Kunde inte spara utkast i localStorage:', e);
        }
    }

    function queueSaveDraft() {
        if (editingOrderId !== null) return;
        clearTimeout(draftSaveTimer);
        draftSaveTimer = setTimeout(saveDraft, 300);
    }

    function restoreDraft() {
        try {
            const raw = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (!raw) return false;
            const draft = JSON.parse(raw);
            if (!draft) return false;

            const hasContent = hasDraftContent(draft);

            if (!hasContent) {
                clearDraft(true);
                return false;
            }

            $('#orderTitle').val(draft.orderTitle || '');
            $('#orderCompanyName').val(draft.companyName || '');
            $('#orderCompanyDomain').val(draft.companyDomain || '');
            $('#orderOrg').val(draft.org || '');
            $('#orderContact').val(draft.contact || '');
            $('#orderAdminUser').val(draft.adminUser || '');
            $('#orderAdminPass').val(draft.adminPass || '');

            if (draft.companyName || draft.org || draft.contact || draft.companyDomain ||
                draft.adminUser || draft.adminPass || draft.selectedCustomerId > 0) {
                $('#orderContactDetails').prop('open', true);
            }

            if ((draft.adminUser && draft.adminUser.trim() !== '') || (draft.adminPass && draft.adminPass.trim() !== '')) {
                $('.techCredentialsDetails').prop('open', true);
            }

            if (typeof draft.worker !== 'undefined') {
                const wId = parseInt(draft.worker, 10);
                const wName = draft.workerName || (wId === currentUserId ? 'Mig själv' : 'Kollega');
                selectWorker({ id: wId, username: wName, is_me: (wId === currentUserId) }, true);
            }

            if (draft.asap) {
                $('#orderAsapCheck').prop('checked', true);
                $('#asapSwitchWrapper').addClass('active');
            } else {
                $('#orderAsapCheck').prop('checked', false);
                $('#asapSwitchWrapper').removeClass('active');
            }

            $('#orderDesc').val(draft.desc || '');

            if (draft.selectedCustomerId > 0) {
                selectedCustomerId = draft.selectedCustomerId;
                $('#selectedCustomerName').text(draft.selectedCustomerName || draft.companyName);
                $('#customerSelectedBadge').css('display', 'flex');
            }

            if (draft.steps && Array.isArray(draft.steps)) {
                orderSteps = draft.steps;
                renderSteps();
            }

            $('#orderDraftBadge').show();
            $('#draftAlertText').text('Sparat utkast återställt (' + (draft.savedAt || 'nyligen') + ').');
            $('#draftAlertBar').slideDown(150);
            updateSidebarIndicator(true);

            return true;
        } catch (e) {
            console.warn('Kunde inte läsa utkast:', e);
            return false;
        }
    }

    function clearDraft(silent) {
        try {
            localStorage.removeItem(DRAFT_STORAGE_KEY);
        } catch (e) {}

        $('#orderDraftBadge').hide();
        $('#draftAlertBar').slideUp(150);
        updateSidebarIndicator(false);

        if (!silent) {
            resetOrderModal();
        }
    }

    function updateSidebarIndicator(hasDraft) {
        $('.addOrder').toggleClass('has-draft', hasDraft)
            .attr('aria-label', hasDraft ? 'Öppna sparat utkast' : 'Skapa ny uppgift');
        $('.addOrder .dockBtnLabel').text(hasDraft ? 'Öppna utkast' : 'Ny uppgift');
    }

    function hasDraftContent(draft) {
        return !!draft && ((draft.orderTitle && draft.orderTitle.trim() !== '') ||
            (draft.companyName && draft.companyName.trim() !== '') ||
            (draft.companyDomain && draft.companyDomain.trim() !== '') ||
            (draft.org && draft.org.trim() !== '') ||
            (draft.contact && draft.contact.trim() !== '') ||
            (draft.adminUser && draft.adminUser.trim() !== '') ||
            (draft.adminPass && draft.adminPass.trim() !== '') ||
            (draft.desc && draft.desc.trim() !== '') ||
            (draft.steps && draft.steps.length > 0) ||
            (draft.selectedCustomerId > 0) ||
            (draft.worker && parseInt(draft.worker, 10) !== currentUserId) ||
            draft.asap);
    }

    // ==========================================
    // Tilldela & Delegering (Email-liknande väljare)
    // ==========================================
    function getInitials(name) {
        if (!name) return '??';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.slice(0, 2).toUpperCase();
    }

    function getLastDelegatedWorker() {
        try {
            const raw = localStorage.getItem(LAST_DELEGATED_STORAGE_KEY);
            if (!raw) return null;
            const w = JSON.parse(raw);
            if (w && w.id && parseInt(w.id, 10) !== currentUserId) {
                return w;
            }
        } catch (e) {}
        return null;
    }

    function setLastDelegatedWorker(worker) {
        if (!worker || parseInt(worker.id, 10) === currentUserId) return;
        try {
            localStorage.setItem(LAST_DELEGATED_STORAGE_KEY, JSON.stringify({
                id: parseInt(worker.id, 10),
                username: worker.username,
                email: worker.email || ''
            }));
            updateLastDelegatedChip();
        } catch (e) {}
    }

    function updateLastDelegatedChip() {
        const last = getLastDelegatedWorker();
        if (last && last.id !== selectedWorkerId) {
            $('#lastDelegatedName').text(last.username);
            $('#lastDelegatedChip').css('display', 'inline-flex');
        } else {
            $('#lastDelegatedChip').hide();
        }
    }

    function selectWorker(worker, silent) {
        if (!worker) return;
        const wId = parseInt(worker.id, 10);
        const isSelf = (wId === currentUserId);
        selectedWorkerId = wId;

        $('#orderWorkerSelect').val(wId);
        $('#delegationPillName').text(isSelf ? 'Mig själv' : worker.username);
        $('#delegationPillAvatar').text(isSelf ? 'Du' : getInitials(worker.username));
        $('#delegationPillRole').text(isSelf ? 'Ansvarig' : 'Delegerad');

        if (isSelf) {
            $('#delegationPill').removeClass('is-delegated');
        } else {
            $('#delegationPill').addClass('is-delegated');
        }

        $('#delegationDropdown').removeClass('open');
        updateLastDelegatedChip();

        if (!silent) {
            queueSaveDraft();
        }
    }

    function renderDelegationList(filterQuery) {
        const $list = $('#delegationList');
        $list.empty();
        const q = (filterQuery || '').toLowerCase().trim();
        const lastDelegated = getLastDelegatedWorker();

        // 1. Alltid 'Mig själv'
        if (q === '' || 'mig själv'.indexOf(q) !== -1 || currentUserName.toLowerCase().indexOf(q) !== -1) {
            const isSelected = (selectedWorkerId === currentUserId);
            const $meItem = $(
                '<div class="delegationItem ' + (isSelected ? 'selected' : '') + '" data-worker-id="' + currentUserId + '">' +
                    '<div class="delItemLeft">' +
                        '<span class="delAvatar self">Du</span>' +
                        '<div class="delMeta">' +
                            '<span class="delName">Mig själv (' + escapeHtml(currentUserName) + ')</span>' +
                            '<span class="delSub">Skapa uppgiften till mig själv</span>' +
                        '</div>' +
                    '</div>' +
                    (isSelected ? '<span class="delCheck">✓</span>' : '') +
                '</div>'
            );
            $meItem.on('click', function () {
                selectWorker({ id: currentUserId, username: currentUserName, is_me: true });
            });
            $list.append($meItem);
        }

        // 2. Senast delegerad
        if (lastDelegated && lastDelegated.id !== currentUserId) {
            if (q === '' || lastDelegated.username.toLowerCase().indexOf(q) !== -1) {
                const isSelected = (selectedWorkerId === lastDelegated.id);
                $list.append('<div class="delegationSectionLabel">Senast delegerad</div>');
                const $lastItem = $(
                    '<div class="delegationItem ' + (isSelected ? 'selected' : '') + '" data-worker-id="' + lastDelegated.id + '">' +
                        '<div class="delItemLeft">' +
                            '<span class="delAvatar delegated">' + getInitials(lastDelegated.username) + '</span>' +
                            '<div class="delMeta">' +
                                '<span class="delName">' + escapeHtml(lastDelegated.username) + ' <span class="lastBadge">Senast</span></span>' +
                                '<span class="delSub">' + escapeHtml(lastDelegated.email || 'Kollega') + '</span>' +
                            '</div>' +
                        '</div>' +
                        (isSelected ? '<span class="delCheck">✓</span>' : '') +
                    '</div>'
                );
                $lastItem.on('click', function () {
                    selectWorker({ id: lastDelegated.id, username: lastDelegated.username, email: lastDelegated.email, is_me: false });
                });
                $list.append($lastItem);
            }
        }

        // 3. Övriga teammedlemmar
        let otherWorkers = teamWorkers.filter(w => parseInt(w.id, 10) !== currentUserId);
        if (lastDelegated) {
            otherWorkers = otherWorkers.filter(w => parseInt(w.id, 10) !== parseInt(lastDelegated.id, 10));
        }

        if (otherWorkers.length > 0) {
            $list.append('<div class="delegationSectionLabel">Teammedlemmar</div>');
            otherWorkers.forEach(function (w) {
                if (q !== '' && w.username.toLowerCase().indexOf(q) === -1 && (w.email || '').toLowerCase().indexOf(q) === -1) {
                    return;
                }
                const isSelected = (selectedWorkerId === parseInt(w.id, 10));
                const $item = $(
                    '<div class="delegationItem ' + (isSelected ? 'selected' : '') + '" data-worker-id="' + w.id + '">' +
                        '<div class="delItemLeft">' +
                            '<span class="delAvatar">' + getInitials(w.username) + '</span>' +
                            '<div class="delMeta">' +
                                '<span class="delName">' + escapeHtml(w.username) + '</span>' +
                                '<span class="delSub">' + escapeHtml(w.email || (w.role == 2 ? 'Admin' : 'Medarbetare')) + '</span>' +
                            '</div>' +
                        '</div>' +
                        (isSelected ? '<span class="delCheck">✓</span>' : '') +
                    '</div>'
                );
                $item.on('click', function () {
                    selectWorker(w);
                });
                $list.append($item);
            });
        }
    }

    function initDelegationPicker() {
        if (!teamWorkers || teamWorkers.length === 0) {
            $.getJSON('php/functions/getWorkersList.php', function (res) {
                if (res && res.success && res.workers) {
                    teamWorkers = res.workers;
                    renderDelegationList();
                }
            });
        }

        renderDelegationList();
        updateLastDelegatedChip();

        $('#delegationPill').on('click', function (e) {
            e.stopPropagation();
            const isOpen = $('#delegationDropdown').hasClass('open');
            if (isOpen) {
                $('#delegationDropdown').removeClass('open');
            } else {
                renderDelegationList($('#delegationSearchInput').val());
                $('#delegationDropdown').addClass('open');
                setTimeout(function () {
                    $('#delegationSearchInput').focus();
                }, 50);
            }
        });

        $('#lastDelegatedChip').on('click', function (e) {
            e.stopPropagation();
            const last = getLastDelegatedWorker();
            if (last) {
                selectWorker(last);
            }
        });

        $('#delegationSearchInput').on('input', function () {
            renderDelegationList($(this).val());
        });

        $('#delegationDropdown').on('click', function (e) {
            e.stopPropagation();
        });

        $(document).on('click', function () {
            $('#delegationDropdown').removeClass('open');
        });
    }

    // ==========================================
    // Kundsökning och autocomplete
    // ==========================================
    function initCustomerSearch() {
        const $input = $('#orderCustomerSearch');
        const $dropdown = $('#customerDropdown');

        $('#orderCompanyName').on('input', function () {
            if (selectedCustomerId > 0) {
                selectedCustomerId = 0;
                $('#customerSelectedBadge').hide();
            }
        });

        $input.on('input focus', function () {
            const query = $(this).val().trim();
            clearTimeout(searchDebounceTimer);

            searchDebounceTimer = setTimeout(function () {
                $.getJSON('php/functions/searchCustomers.php', { q: query }, function (customers) {
                    $dropdown.empty();
                    if (!customers || customers.length === 0) {
                        if (query !== '') {
                            $dropdown.html('<div class="customerDropdownItem" style="cursor:default; color:#71717a;">Inga sparade kontaktpersoner matchar "' + escapeHtml(query) + '"</div>');
                            $dropdown.addClass('open');
                        } else {
                            $dropdown.removeClass('open');
                        }
                        return;
                    }

                    customers.forEach(function (c) {
                        const itemHtml = $(
                            '<div class="customerDropdownItem">' +
                                '<div>' +
                                    '<div class="custName">' + escapeHtml(c.name) + '</div>' +
                                    '<div class="custOrg">' + (c.org ? 'Org: ' + escapeHtml(c.org) : (c.url ? escapeHtml(c.url) : '')) + '</div>' +
                                '</div>' +
                                '<span style="font-size:0.75rem; color:#818cf8; font-weight:600;">Välj</span>' +
                            '</div>'
                        );

                        itemHtml.on('click', function () {
                            selectCustomer(c);
                        });

                        $dropdown.append(itemHtml);
                    });

                    $dropdown.addClass('open');
                });
            }, 180);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.customerSearchWrapper').length) {
                $dropdown.removeClass('open');
            }
        });

        $('#customerResetBtn').on('click', function (e) {
            e.preventDefault();
            selectedCustomerId = 0;
            $('#customerSelectedBadge').hide();
            $('#orderCustomerSearch').val('').focus();
            $('#orderCompanyName').val('');
            $('#orderCompanyDomain').val('');
            $('#orderOrg').val('');
            $('#orderContact').val('');
            $('#orderAdminUser').val('');
            $('#orderAdminPass').val('');
            queueSaveDraft();
        });
    }

    function selectCustomer(customer) {
        selectedCustomerId = customer.id;
        $('#customerDropdown').removeClass('open');
        $('#orderCustomerSearch').val('');

        $('#orderCompanyName').val(customer.name || '');
        $('#orderCompanyDomain').val(customer.url || '');
        $('#orderOrg').val(customer.org || '');
        $('#orderContact').val(customer.contact || '');
        $('#orderAdminUser').val(customer.admin_username || '');
        $('#orderAdminPass').val(customer.admin_password || '');

        if ((customer.admin_username && customer.admin_username.trim() !== '') || (customer.admin_password && customer.admin_password.trim() !== '')) {
            $('.techCredentialsDetails').prop('open', true);
        }

        $('#selectedCustomerName').text(customer.name);
        $('#customerSelectedBadge').css('display', 'flex');
        $('#orderContactDetails').prop('open', true);

        queueSaveDraft();
    }

    // ==========================================
    // Delmoment (Checklista)
    // ==========================================
    function initSteps() {
        $('#orderAddStepBtn').on('click', function (e) {
            e.preventDefault();
            addStep();
        });

        $('#orderStepInput').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addStep();
            }
        });

        $(document).on('click', '.stepChipBtn', function (e) {
            e.preventDefault();
            const chipText = $(this).data('step');
            if (chipText) {
                orderSteps.push(editingOrderId === null ? chipText : { id: 0, text: chipText });
                renderSteps();
                queueSaveDraft();
                $('#orderStepInput').focus();
            }
        });

        $(document).on('click', '.stepRemoveBtn', function () {
            const index = $(this).data('index');
            orderSteps.splice(index, 1);
            renderSteps();
            queueSaveDraft();
        });

        $(document).on('input', '.stepTextInput', function () {
            const index = parseInt($(this).data('index'), 10);
            if (orderSteps[index] && typeof orderSteps[index] === 'object') orderSteps[index].text = $(this).val();
        });
    }

    function addStep() {
        const text = $('#orderStepInput').val().trim();
        if (text === '') return;

        orderSteps.push(editingOrderId === null ? text : { id: 0, text: text });
        $('#orderStepInput').val('').focus();
        renderSteps();
        queueSaveDraft();
    }

    function renderSteps() {
        const $list = $('#orderStepsList');
        $list.empty();

        if (orderSteps.length === 0) {
            $list.hide();
            return;
        }

        $list.show();
        orderSteps.forEach(function (step, index) {
            const label = typeof step === 'string' ? step : step.text;
            const row = $(
                '<div class="stepItem">' +
                    '<div class="stepText">' +
                        '<span class="stepIndex">' + (index + 1) + '</span>' +
                        (editingOrderId === null ? '<span>' + escapeHtml(label) + '</span>' :
                            '<input class="stepTextInput" data-index="' + index + '" aria-label="Delmoment ' + (index + 1) + '">' +
                            (step.completed ? '<span class="stepDoneBadge">Klart</span>' : '')) +
                    '</div>' +
                    '<button type="button" class="stepRemoveBtn" data-index="' + index + '" title="Ta bort">✕</button>' +
                '</div>'
            );
            if (editingOrderId !== null) row.find('.stepTextInput').val(label);
            $list.append(row);
        });
    }

    // ==========================================
    // Drag & Drop Bilduppladdare
    // ==========================================
    function initDropZone() {
        const $zone = $('#orderDropZone');
        const $input = $('#orderDropZoneInput');

        $zone.on('click', function () {
            $input.trigger('click');
        });

        $input.on('change', function () {
            const files = Array.from(this.files);
            handleIncomingFiles(files);
            $input.val('');
        });

        $zone.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.addClass('dragover');
        });

        $zone.on('dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $zone.removeClass('dragover');
        });

        $zone.on('drop', function (e) {
            const dt = e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                handleIncomingFiles(Array.from(dt.files));
            }
        });

        $(document).on('click', '.thumbRemove', function (e) {
            e.stopPropagation();
            const index = $(this).data('index');
            orderImages.splice(index, 1);
            renderImagePreviews();
        });
    }

    function handleIncomingFiles(files) {
        files.forEach(function (f) {
            if (['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'].includes(f.type)) {
                const exists = orderImages.some(img => img.name === f.name && img.size === f.size);
                if (!exists) {
                    orderImages.push(f);
                }
            }
        });
        renderImagePreviews();
    }

    function renderImagePreviews() {
        const $grid = $('#orderImagePreviewGrid');
        $grid.empty();

        if (orderImages.length === 0) {
            $grid.hide();
            return;
        }

        $grid.show();
        orderImages.forEach(function (file, index) {
            if (file.type === 'application/pdf') {
                const $pdf = $('<div class="previewThumb correctionPdfPreview"></div>');
                $('<span></span>').text('PDF · ' + file.name).appendTo($pdf);
                $('<button type="button" class="thumbRemove" title="Ta bort">✕</button>').attr('data-index', index).appendTo($pdf);
                $grid.append($pdf);
                return;
            }
            const reader = new FileReader();
            const $thumb = $(
                '<div class="previewThumb">' +
                    '<img src="" alt="preview">' +
                    '<button type="button" class="thumbRemove" data-index="' + index + '" title="Ta bort">✕</button>' +
                '</div>'
            );

            reader.onload = function (e) {
                $thumb.find('img').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);

            $grid.append($thumb);
        });
    }

    function renderExistingImages() {
        const $list = $('#orderExistingImages').empty();
        if (editingOrderId === null || !existingImages.length) {
            $list.prop('hidden', true);
            return;
        }
        $list.prop('hidden', false);
        existingImages.forEach(function (img) {
            if (!/^media\/[A-Za-z0-9_-]+\/[A-Za-z0-9_.-]+$/.test(img.url)) return;
            const $row = $('<div class="correctionImageRow"></div>');
            $('<a target="_blank" rel="noopener noreferrer"></a>').attr('href', img.url).text(img.url.split('/').pop()).appendTo($row);
            $('<button type="button" class="correctionImageRemove">Ta bort</button>').on('click', function () {
                removedImageIds.push(parseInt(img.id, 10));
                existingImages = existingImages.filter(item => parseInt(item.id, 10) !== parseInt(img.id, 10));
                renderExistingImages();
            }).appendTo($row);
            $list.append($row);
        });
    }

    // ==========================================
    // Delegeringshistorik (Kort på sidan)
    // ==========================================
    window.loadCreatedOrdersHistory = function () {
        $.getJSON('php/functions/getMyCreatedOrders.php', function (res) {
            if (!res || !res.success) return;

            const stats = res.stats || {};
            const orders = res.orders || [];

            $('#statOngoing').text(stats.ongoing || 0);
            $('#statDelegated').text(stats.delegated || 0);
            $('#statCompleted').text(stats.completed || 0);
            $('#historyCountBadge').text(stats.total || 0);
            $('#historyFloatBadge').text(stats.total || 0);
            $('#historyDelegatedBadge').text(stats.delegated || 0);
            $('#historyOngoingBadge').text(stats.ongoing || 0);
            $('#historyCompletedBadge').text(stats.completed || 0);

            const $list = $('#historyList');
            $list.empty();

            if (orders.length === 0) {
                $list.html('<div class="historyEmptyState">Du har inte skapat några uppdrag ännu.</div>');
                return;
            }

            orders.forEach(function (o) {
                const statusLabels = {
                    'ongoing': 'Pågående',
                    'pending': 'Granskas',
                    'completed': 'Klar',
                    'rework': 'Kompletteras',
                    'canceled': 'Papperskorg'
                };
                const statusColors = {
                    'ongoing': '#f97316',
                    'pending': '#a855f7',
                    'completed': '#22c55e',
                    'rework': '#06b6d4',
                    'canceled': '#94a3b8'
                };

                const statLbl = statusLabels[o.status] || o.status;
                const statCol = statusColors[o.status] || '#94a3b8';
                const isSelf = o.is_self;

                const $item = $(
                    '<div class="historyCardItem" data-order-id="' + o.id + '">' +
                        '<div class="histItemTop">' +
                            '<span class="histItemName" title="' + escapeHtml(o.name) + '">' + escapeHtml(o.name) + '</span>' +
                            '<span class="histStatusBadge" style="background:' + statCol + '18; color:' + statCol + '; border-color:' + statCol + '30;">' +
                                '<span class="statusDot" style="background:' + statCol + ';"></span>' +
                                statLbl +
                            '</span>' +
                        '</div>' +
                        '<div class="histItemBottom">' +
                            '<span class="histAssignee ' + (isSelf ? 'self' : 'delegated') + '">' +
                                (isSelf ? 'Du' : escapeHtml(o.worker_name)) +
                            '</span>' +
                            '<span class="histDate">' + escapeHtml(o.date || '') + '</span>' +
                        '</div>' +
                    '</div>'
                );

                $item.on('click', function () {
                    const id = $(this).data('order-id');
                    if (['ongoing', 'pending', 'rework'].includes(o.status)) {
                        openOrderCorrection(id);
                    } else {
                        window.location.href = 'home.php?dir=created_by_me&orderId=' + encodeURIComponent(id);
                    }
                });

                $list.append($item);
            });
        });
    };

    function initHistoryCard() {
        function setHistoryOpen(open) {
            $('#createdHistoryCard').toggleClass('is-open', open).attr('aria-hidden', open ? 'false' : 'true');
            $('#historyTabTrigger').attr('aria-expanded', open ? 'true' : 'false');
        }

        $('#historyRefreshBtn').on('click', function () {
            $(this).css('transform', 'rotate(360deg)');
            setTimeout(() => $(this).css('transform', ''), 400);
            loadCreatedOrdersHistory();
        });

        $('#historyToggleCollapseBtn').on('click', function () {
            setHistoryOpen(false);
            $('#historyTabTrigger').trigger('focus');
        });

        $('#historyTabTrigger').on('click', function (event) {
            event.stopPropagation();
            const open = !$('#createdHistoryCard').hasClass('is-open');
            if (open) {
                $('#notificationPopover').prop('hidden', true);
                $('#notificationBell').attr('aria-expanded', 'false');
            }
            setHistoryOpen(open);
        });

        $('#dockMoreTrigger, #dockSpotlightTrigger').on('click', function () {
            setHistoryOpen(false);
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('#createdHistoryCard, #historyTabTrigger').length) setHistoryOpen(false);
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape' && $('#createdHistoryCard').hasClass('is-open')) {
                setHistoryOpen(false);
                $('#historyTabTrigger').trigger('focus');
            }
        });

        $('#historyViewAllBtn').on('click', function () {
            if (typeof window.reloadOrders === 'function') {
                window.reloadOrders('created_by_me');
                $('#parentStats span').removeClass('active');
                $('[data-url="created_by_me"]').addClass('active');
            }
            setHistoryOpen(false);
        });
    }

    // ==========================================
    // Formulärlyssnare & Inskickning
    // ==========================================
    function initFormSubmit() {
        // Auto-save vid all inmatning
        $('#orderModalForm').on('input change', 'input, textarea, select', function () {
            queueSaveDraft();
        });

        // Släng utkast-knapp
        $('#draftDiscardBtn').on('click', function (e) {
            e.preventDefault();
            if (confirm('Vill du slänga det sparade utkastet och tömma formuläret?')) {
                clearDraft(false);
            }
        });

        $('#orderAsapCheck').on('change', function () {
            if ($(this).is(':checked')) {
                $('#asapSwitchWrapper').addClass('active');
            } else {
                $('#asapSwitchWrapper').removeClass('active');
            }
            queueSaveDraft();
        });

        $('#orderModalCancelBtn, #orderModalCloseBtn, .orderModalSheetHandle').on('click', function () {
            closeOrderModal();
        });

        $('#orderModalOverlay').on('click', function (e) {
            if ($(e.target).is('#orderModalOverlay')) {
                closeOrderModal();
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#orderModalOverlay').hasClass('active')) {
                closeOrderModal();
            }
        });

        window.addEventListener('beforeunload', function () {
            if ($('#orderModalOverlay').hasClass('active') && editingOrderId === null) {
                saveDraft();
            }
        });

        $('#orderModalForm').on('submit', function (e) {
            e.preventDefault();

            const orderTitle = $('#orderTitle').val().trim();
            if (orderTitle === '') {
                alert('Vänligen ange uppgiftens namn.');
                $('#orderTitle').focus();
                return;
            }
            const companyName = $('#orderCompanyName').val().trim();

            const $submitBtn = $('#orderSubmitBtn');
            const submittedEditId = editingOrderId;
            $submitBtn.addClass('loading').prop('disabled', true);

            const formData = new FormData();
            formData.append('order_title', orderTitle);
            formData.append('company_name', companyName);
            formData.append('company_domain', $('#orderCompanyDomain').val().trim());
            formData.append('org', $('#orderOrg').val().trim());
            formData.append('contact', $('#orderContact').val().trim());
            formData.append('company_admin_username', $('#orderAdminUser').val().trim());
            formData.append('company_admin_password', $('#orderAdminPass').val().trim());
            formData.append('worker', selectedWorkerId);
            formData.append('order_desc', $('#orderDesc').val().trim());
            formData.append('asap', $('#orderAsapCheck').is(':checked') ? 'asap' : 'normal');
            formData.append('customer_id', selectedCustomerId);
            formData.append('steps', JSON.stringify(editingOrderId === null ? orderSteps : orderSteps.map(step => ({ id: step.id || 0, text: (step.text || '').trim() }))));

            if (submittedEditId !== null) {
                formData.append('orderId', submittedEditId);
                formData.append('csrf', window.WorkflowCsrf);
                formData.append('clear_password', $('#orderClearPassword').is(':checked') ? '1' : '0');
                formData.append('remove_images', JSON.stringify(removedImageIds));
            }

            orderImages.forEach(function (file) {
                formData.append('images[]', file);
            });

            $.ajax({
                url: submittedEditId === null ? 'php/functions/addOrder.php' : 'php/functions/saveOrderCorrection.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (res) {
                    $submitBtn.removeClass('loading').prop('disabled', false);

                    if (res && res.success) {
                        const wasEditing = submittedEditId !== null;
                        // Spara som senast delegerad om den inte var till sig själv
                        if (selectedWorkerId !== currentUserId) {
                            const found = teamWorkers.find(w => parseInt(w.id, 10) === selectedWorkerId);
                            setLastDelegatedWorker(found || {
                                id: selectedWorkerId,
                                username: $('#delegationPillName').text()
                            });
                        }

                        // Rensa utkast och stäng modal
                        if (!wasEditing) {
                            clearTimeout(draftSaveTimer);
                            clearDraft(true);
                        }
                        editingOrderId = null;
                        resetOrderModal();
                        setModalMode(false);
                        if (wasEditing) restoreDraft();
                        $('#orderModalOverlay').removeClass('active');
                        $('body').css('overflow', '');

                        if (typeof message === 'function') {
                            message(wasEditing ? 'Uppgiften har korrigerats!' : 'Uppgiften har skapats!');
                        } else {
                            alert(wasEditing ? 'Uppgiften har korrigerats!' : 'Uppgiften har skapats!');
                        }

                        // Uppdatera dashboard och historikkort
                        if (typeof window.reloadOrders === 'function') {
                            window.reloadOrders();
                        }
                        if (typeof loadCreatedOrdersHistory === 'function') {
                            loadCreatedOrdersHistory();
                        }
                    } else {
                        alert('Kunde inte spara uppgiften: ' + (res.error || 'Okänt fel'));
                    }
                },
                error: function (xhr, status, error) {
                    $submitBtn.removeClass('loading').prop('disabled', false);
                    alert('Kunde inte spara uppgiften: ' + ((xhr.responseJSON && xhr.responseJSON.error) || error));
                }
            });
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    $(document).ready(function () {
        initDelegationPicker();
        initCustomerSearch();
        initSteps();
        initDropZone();
        initFormSubmit();
        initHistoryCard();
        loadCreatedOrdersHistory();

        // Kontrollera om det finns ett utkast vid sidladdning
        try {
            const raw = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (raw) {
                const draft = JSON.parse(raw);
                const hasContent = hasDraftContent(draft);
                if (hasContent) {
                    updateSidebarIndicator(true);
                }
            }
        } catch (e) {}

        // Klicka på "Ny uppgift" i appen öppnar modalen direkt
        $(document).on('click', '.addOrder', function (e) {
            e.preventDefault();
            openOrderModal();
        });

        // Kontrollera om sidan laddades med ?newOrder=1
        if (window.location.search.indexOf('newOrder=1') !== -1) {
            openOrderModal();
            if (window.history && window.history.replaceState) {
                const cleanUrl = window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }
        const editId = new URLSearchParams(window.location.search).get('edit');
        if (editId && /^\d+$/.test(editId)) {
            openOrderCorrection(editId);
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

})(jQuery);
