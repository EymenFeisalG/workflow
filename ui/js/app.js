/**
 * Workflow Application Core Engine (Clean & Modernized)
 * - Dynamic AJAX Section & Category Loading with Menu Button Fade Pulse
 * - Workflow Kanban & Priority Drag-and-Drop (SortableJS)
 * - SPA Browser History (PushState & PopState)
 * - Order Actions: Steps, Attest, Rework, Delete, Restore, Gallery, Focus
 * - Cleaned legacy dependencies (TinyMCE removed from modal, obsolete menus purged)
 */
$(document).ready(function () {
    'use strict';

    // Application State Variables
    let currentDir = (typeof Direction !== 'undefined' && Direction) ? Direction : 'ongoing';
    let focusedOrderId = null;
    let temporaryView = false;
    let workflowSortable = null;
    let isSectionLoading = false;
    let sectionVersion = 0;
    let lastOrdersMarkup = null;
    let lastNavMarkup = null;
    let liveRequestPending = false;
    window.reloadOrders = function (section) {
        loadSection(section || currentDir, Boolean(section));
    };

    // Mirror the status counts from the desktop tabs into the mobile submenu.
    ['pending', 'rework', 'completed'].forEach(function (dir) {
        const count = $('#parentStats [data-url="' + dir + '"] .dockBadge').text().trim() || '0';
        $('#dockSubmenuPopover .dockMenuCategory[data-url="' + dir + '"] .dockMobileCategoryCount')
            .text(count)
            .attr('aria-label', count + ' uppdrag');
    });

    // Initialize initial section from URL parameter or default
    initCurrentSection();
    restoreFocus();
    if ($('#notificationBell').length) {
        refreshNotifications();
        window.setInterval(refreshNotifications, 30000);
    }
    window.setInterval(refreshLiveTasks, 3000);

    function refreshLiveTasks() {
        if (document.hidden) return;
        if (typeof window.loadCreatedOrdersHistory === 'function') window.loadCreatedOrdersHistory();
        if (isSectionLoading || liveRequestPending) return;
        const requestedDir = currentDir;
        const requestedVersion = sectionVersion;
        liveRequestPending = true;
        $.ajax({
            url: 'php/functions/getOrders.php',
            data: { dir: requestedDir, live: '1' },
            dataType: 'json',
            cache: false,
            timeout: 10000
        })
            .done(function (result) {
                if (!result || requestedDir !== currentDir || requestedVersion !== sectionVersion || isSectionLoading) return;

                if (result.nav !== lastNavMarkup) {
                    lastNavMarkup = result.nav;
                    $('#parentStats').replaceWith($(result.nav).filter('#parentStats'));
                    $('#parentStats [data-url="' + currentDir + '"]').addClass('active');
                    ['pending', 'rework', 'completed'].forEach(function (dir) {
                        const count = $('#parentStats [data-url="' + dir + '"] .dockBadge').text().trim() || '0';
                        $('#dockSubmenuPopover .dockMenuCategory[data-url="' + dir + '"] .dockMobileCategoryCount')
                            .text(count).attr('aria-label', count + ' uppdrag');
                    });
                }

                const markup = (result.orders || '').trim();
                if (markup === lastOrdersMarkup || $('.searchOrder').val().trim() ||
                    $('#orderModalOverlay').hasClass('active') || !$('#decisionModal').prop('hidden') || $('.generalModal:visible, .orders .dragging, .orders .is-saving').length ||
                    $('.orders .taskThreadPanel').filter(function () { return !this.hidden; }).length ||
                    $(document.activeElement).is('.orders input, .orders textarea, .orders [contenteditable="true"]')) return;

                const $orders = $('.orders');
                const scrollTop = $orders.scrollTop();
                const expandedIds = $orders.find('.order .desc.autoHeight').closest('.order').map(function () {
                    return $(this).attr('data-orderid');
                }).get();
                lastOrdersMarkup = markup;
                $orders.html(markup === 'empty' || markup === '' || markup === '0' ? getEmptyMessageHtml(currentDir) : markup);
                addToOrder();
                expandedIds.forEach(function (id) {
                    const $order = $orders.find('.order[data-orderid="' + id + '"]');
                    $order.find('.desc').addClass('autoHeight').removeClass('masked');
                    $order.find('.readMore').addClass('rotate');
                });
                const selectedId = new URLSearchParams(window.location.search).get('orderId');
                if (selectedId && /^\d+$/.test(selectedId)) {
                    $orders.find('.order[data-orderid="' + selectedId + '"]').addClass('selectedCreatedOrder');
                }
                setupWorkflowSortable(currentDir);
                $orders.scrollTop(scrollTop);
            })
            .always(function () { liveRequestPending = false; });
    }

    /* ==========================================================================
       1. Dynamic AJAX Section Loading Engine
       ========================================================================== */

    /**
     * Determines starting section from URL query or global Direction
     */
    function initCurrentSection() {
        const urlParams = new URLSearchParams(window.location.search);
        const dirParam = urlParams.get('dir');
        if (dirParam) {
            currentDir = dirParam;
        }

        // Initial load without pushing history
        loadSection(currentDir, false);
    }

    /**
     * Dynamic Section Loader
     * - Animates clicked menu button text with fading out and in pulse
     * - Performs asynchronous AJAX content fetch
     * - Smoothly updates .orders container with fade-in and empty states
     * - Manages browser history and Kanban SortableJS integration
     */
    function loadSection(sectionUrl, pushHistory = true, $clickedBtn = null) {
        if (!sectionUrl) return;

        // Prevent redundant simultaneous requests
        if (isSectionLoading && currentDir === sectionUrl) return;
        isSectionLoading = true;
        const requestVersion = ++sectionVersion;

        // Normalize section identifier
        let requestDir = (sectionUrl === 'workflow') ? 'prio' : sectionUrl;

        // 1. Locate the menu button and start text fade pulse animation
        $('.dockTabText').removeClass('dockTextLoading');

        let $targetBtn = $clickedBtn;
        if (!$targetBtn || !$targetBtn.length) {
            if (requestDir === 'prio') {
                $targetBtn = $('#dockWorkflowBtn');
            } else if (requestDir === 'canceled') {
                $targetBtn = $('.dockSubmenuItem.recycle');
            } else {
                const $menuCategory = $('#dockSubmenuPopover .dockMenuCategory[data-url="' + requestDir + '"]');
                const $directCategory = $('#parentStats [data-url="' + requestDir + '"]').closest('.dockTextTab');
                $targetBtn = $menuCategory.is(':visible') ? $menuCategory : $directCategory;
            }
        }

        let $btnText = $targetBtn.find('.dockTabText');
        if (!$btnText.length) {
            $btnText = $targetBtn.children('span').first();
        }
        $btnText.addClass('dockTextLoading');

        // 2. Put orders container in subtle loading state
        const $orders = $('.orders');
        $orders.addClass('is-loading').removeClass('ordersFadeIn');

        // 3. Perform AJAX request
        $.ajax({
            url: 'php/functions/getOrders.php',
            type: 'GET',
            data: { 'dir': requestDir },
            dataType: 'html',
            cache: false,
            success: function (response) {
                if (requestVersion !== sectionVersion) return;
                currentDir = requestDir;

                // Stop blinking animation
                $btnText.removeClass('dockTextLoading');
                $('.dockTabText').removeClass('dockTextLoading');

                // Update active states across dock navigation
                $('#parentStats .dockTextTab, .dockMenuCategory').removeClass('active');
                $('#dockWorkflowBtn').removeClass('active');
                $('.recycle').removeClass('active');
                $('#dockMoreTrigger').removeClass('active');

                if (requestDir === 'prio') {
                    $('#dockWorkflowBtn').addClass('active');
                } else if (requestDir === 'canceled') {
                    $('.recycle').addClass('active');
                    $('#dockMoreTrigger').addClass('active');
                } else {
                    const $menuCategory = $('#dockSubmenuPopover .dockMenuCategory[data-url="' + requestDir + '"]');
                    $('#parentStats [data-url="' + requestDir + '"]').addClass('active');
                    $menuCategory.addClass('active');
                    if ($menuCategory.length) $('#dockMoreTrigger').addClass('active');
                }

                // Close dock submenu if open
                if ($('#dockSubmenuPopover').hasClass('active')) {
                    $('#dockSubmenuPopover').removeClass('active');
                    $('#dockMoreTrigger').attr('aria-expanded', 'false');
                }

                // Render content or empty state
                let trimmed = (response || '').trim();
                lastOrdersMarkup = trimmed;
                if (trimmed === 'empty' || trimmed === '' || trimmed === '0') {
                    $orders.html(getEmptyMessageHtml(requestDir));
                } else {
                    $orders.html(trimmed);
                    addToOrder();
                    if ($('#notificationBell').length) refreshNotifications();
                    const selectedId = new URLSearchParams(window.location.search).get('orderId');
                    if (selectedId && /^\d+$/.test(selectedId)) {
                        const $selected = $orders.find('[data-orderId="' + selectedId + '"]');
                        if ($selected.length) {
                            $selected.addClass('selectedCreatedOrder');
                            $selected[0].scrollIntoView({ block: 'center' });
                        }
                    }
                }

                // Smooth fade-in
                $orders.removeClass('is-loading').addClass('ordersFadeIn');

                // Setup or teardown Sortable for Workflow / prio
                setupWorkflowSortable(requestDir);

                // Update browser URL via pushState
                if (pushHistory) {
                    let newUrl = (requestDir === 'prio') ? '?dir=prio' : '?dir=' + encodeURIComponent(requestDir);
                    window.history.pushState({ dir: requestDir }, '', newUrl);
                }

                // Scroll orders container back to top
                $orders.scrollTop(0);
            },
            error: function () {
                if (requestVersion !== sectionVersion) return;
                $btnText.removeClass('dockTextLoading');
                $('.dockTabText').removeClass('dockTextLoading');
                $orders.removeClass('is-loading');
                message("Kunde inte ladda sektionen. Kontrollera anslutningen.", "Fel");
            },
            complete: function () {
                if (requestVersion === sectionVersion) isSectionLoading = false;
            }
        });
    }

    /**
     * Returns a styled modern empty state message based on category
     */
    function getEmptyMessageHtml(dir) {
        const titles = {
            'all': 'Inga pågående uppdrag',
            'ongoing': 'Inga tilldelade uppdrag',
            'asap': 'Inga akuta uppdrag',
            'pending': 'Inga uppdrag att granska',
            'rework': 'Inga uppdrag för komplettering',
            'created': 'Inga skapade uppdrag',
            'completed': 'Inga godkända uppdrag',
            'canceled': 'Papperskorgen är tom',
            'prio': 'Inga uppdrag i workflow'
        };
        const title = titles[dir] || 'Inga uppdrag hittades';
        return `
            <div class="ordersEmptyState">
                <div class="emptyIcon">📭</div>
                <h3>${title}</h3>
                <p>Det finns inga uppgifter att visa i den här vyn just nu.</p>
            </div>
        `;
    }

    /**
     * Initializes or cleans up SortableJS for Workflow / Priority view
     */
    function setupWorkflowSortable(dir) {
        if (workflowSortable) {
            try {
                workflowSortable.destroy();
            } catch (err) {}
            workflowSortable = null;
        }

        if (dir === 'prio' && typeof Sortable !== 'undefined') {
            const container = document.querySelector('.orders');
            if (container) {
                workflowSortable = new Sortable(container, {
                    animation: 160,
                    ghostClass: 'dragging',
                    handle: '.order',
                    onEnd: function () {
                        updatePriorities();
                    }
                });
            }
        }
    }

    /**
     * Saves reordered priorities back to server
     */
    function updatePriorities() {
        let orders = Array.from(document.querySelectorAll('.order'));
        let orderList = orders.map((order, index) => ({
            orderId: order.dataset.orderid,
            priority: index + 1
        }));

        $.post('php/functions/sendPrio.php', { 'list': orderList });
    }

    // Browser Back / Forward History Navigation
    window.addEventListener('popstate', function (event) {
        let params = new URLSearchParams(window.location.search);
        let dir = params.get('dir') || (typeof Direction !== 'undefined' ? Direction : 'ongoing');
        loadSection(dir, false);
    });

    /* ==========================================================================
       2. Dock Menu Click Bindings
       ========================================================================== */

    // Category Filter Tabs in Dock (#parentStats)
    $(document).on('click', '#parentStats [data-url]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        let $tab = $(this).closest('.dockTextTab');
        let dir = $tab.data('url') || $(this).data('url');
        loadSection(dir, true, $tab);
    });

    // Compact mobile status categories inside the More submenu
    $(document).on('click', '#dockSubmenuPopover .dockMenuCategory', function (e) {
        e.preventDefault();
        e.stopPropagation();
        loadSection($(this).data('url'), true, $(this));
    });

    // Workflow Button
    $(document).on('click', '#dockWorkflowBtn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        loadSection('prio', true, $(this));
    });

    // Papperskorg (Recycle Bin) in Submenu
    $(document).on('click', '.recycle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        loadSection('canceled', true, $(this));
    });

    /* ==========================================================================
       3. Order Steps
       ========================================================================== */

    $(document).on('change', '.checkStep', function () {
        const stepId = Number($(this).closest('[data-stepid]').attr('data-stepid'));
        const completed = this.checked;
        if (!Number.isInteger(stepId) || stepId < 1) return;
        const $rows = $('.step[data-stepid="' + stepId + '"]');
        const $checks = $rows.find('.checkStep');
        const $editableChecks = $checks.filter(':not(:disabled)');
        $editableChecks.prop('disabled', true);
        $rows.addClass('is-saving').find('.stepStatus').removeClass('is-error').text('Sparar…');

        $.ajax({
            url: 'php/functions/checkStep.php',
            method: 'POST',
            dataType: 'json',
            data: { stepid: stepId, completed: completed ? '1' : '0', csrf: WorkflowCsrf }
        }).done(function (result) {
            if (!result || !result.success) {
                showStepError((result && result.error) || 'Steget kunde inte sparas.');
                return;
            }
            $rows.toggleClass('is-complete', Boolean(result.completed));
            $checks.prop('checked', Boolean(result.completed));
            $rows.find('.stepStatus').text('Sparat');
            window.setTimeout(function () {
                $rows.find('.stepStatus').filter(function () { return $(this).text() === 'Sparat'; }).empty();
            }, 1800);
        }).fail(function (xhr) {
            showStepError((xhr.responseJSON && xhr.responseJSON.error) || 'Steget kunde inte sparas. Försök igen.');
        }).always(function () {
            $rows.removeClass('is-saving');
            $editableChecks.prop('disabled', false);
        });

        function showStepError(error) {
            $checks.prop('checked', !completed);
            $rows.toggleClass('is-complete', !completed);
            $rows.find('.stepStatus').addClass('is-error').text(error);
        }
    });

    /* ==========================================================================
       4. Attest / Rework Comment Form (.decisionForm)
       ========================================================================== */

    const $decisionModal = $('#decisionModal');
    let decisionTrigger = null;

    function closeDecision() {
        if ($decisionModal.prop('hidden')) return;
        if ($decisionModal.find('.decisionForm').data('saving')) return;
        $decisionModal.prop('hidden', true);
        const $form = $decisionModal.find('.decisionForm');
        $form[0].reset();
        $form.find('.orderid, .action').val('');
        $form.find('.decisionError').text('').prop('hidden', true);
        if (decisionTrigger && document.contains(decisionTrigger)) decisionTrigger.focus();
        decisionTrigger = null;
    }

    function openDecision(trigger, action) {
        if ($decisionModal.find('.decisionForm').data('saving')) return;
        const orderId = $(trigger).closest('[data-orderId]').attr('data-orderId');
        if (!/^\d+$/.test(String(orderId || ''))) return;
        decisionTrigger = trigger;
        const isRework = action === 'deny';
        const $form = $decisionModal.find('.decisionForm');
        $form[0].reset();
        $form.find('.orderid').val(orderId);
        $form.find('.action').val(isRework ? 'deny' : 'attest');
        $form.find('.modalTitle').text(isRework ? 'Begär komplettering' : 'Attestera uppgift');
        $form.find('.decisionDescription').text(isRework
            ? 'Uppgiften skickas tillbaka för komplettering. Beskriv gärna vad som behöver göras.'
            : 'Uppgiften skickas till granskning. Du kan lämna en kommentar till mottagaren.');
        $form.find('.saveDecision').text(isRework ? 'Skicka för komplettering' : 'Attestera uppgift');
        $form.find('.decisionError').text('').prop('hidden', true);
        $decisionModal.prop('hidden', false);
        $form.find('.content').trigger('focus');
    }

    $decisionModal.on('click', '.closeDecision', closeDecision);
    $decisionModal.on('click', function (event) {
        if (event.target === this) closeDecision();
    });
    $(document).on('keydown', function (event) {
        if ($decisionModal.prop('hidden')) return;
        if (event.key === 'Escape') { event.preventDefault(); closeDecision(); }
        if (event.key !== 'Tab') return;
        const $controls = $decisionModal.find('button:visible, textarea:visible');
        const first = $controls[0];
        const last = $controls[$controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });

    $decisionModal.on('submit', '.decisionForm', function (event) {
        event.preventDefault();
        const $form = $(this);
        if ($form.data('saving')) return;
        $form.data('saving', true);
        const targetOrderId = $form.find('.orderid').val();
        const actionType = $form.find('.action').val();
        const $button = $form.find('.saveDecision');
        const $error = $form.find('.decisionError').text('').prop('hidden', true);
        $button.prop('disabled', true).text('Sparar…');
        $.ajax({ url: 'php/functions/submitOrderDecision.php', method: 'POST', data: $form.serialize(), dataType: 'json' })
            .done(function (result) {
                if (!result || !result.success) { $error.text((result && result.error) || 'Beslutet kunde inte sparas.').prop('hidden', false); return; }
                $form.data('saving', false);
                closeDecision();
                message(actionType === 'deny' ? 'Uppgiften har skickats för komplettering.' : 'Uppgiften har skickats till granskning.', actionType === 'deny' ? 'Kompletteras' : 'Attesterad');
                if (focusedOrderId === String(targetOrderId)) closeFocus();
                loadSection(currentDir, false);
                refreshNotifications();
            })
            .fail(function (xhr) {
                $error.text((xhr.responseJSON && xhr.responseJSON.error) || 'Beslutet kunde inte sparas. Försök igen.').prop('hidden', false);
            })
            .always(function () {
                $form.data('saving', false);
                $button.prop('disabled', false).text(actionType === 'deny' ? 'Skicka för komplettering' : 'Attestera uppgift');
            });
    });

    $(document).on('click', '.done', function () { openDecision(this, 'attest'); });
    $(document).on('click', '.denyOrder', function () { openDecision(this, 'deny'); });

    // Accept order (Approver)
    $(document).on('click', '.acceptOrder', function () {
        let $card = $(this).closest('[data-orderId]');
        let orderId = $card.attr('data-orderId');

        if (focusedOrderId === String(orderId)) closeFocus();

        $card.slideUp(250);
        $.post('php/functions/acceptOrder.php', { orderId: orderId }, function () {
            message("Uppgiften blev godkänd!", "Godkänd");
        });
    });

    /* ==========================================================================
       5. Trash & Restore Actions
       ========================================================================== */

    // Move to Trash
    $(document).on('click', '.delete', function () {
        let $card = $(this).closest('[data-orderId]');
        let orderId = $card.attr('data-orderId');

        if (focusedOrderId === String(orderId)) closeFocus();

        $card.slideUp(250);
        $.post('php/functions/deleteOrder.php', { postid: orderId }, function () {
            message("Uppdraget har flyttats till papperskorgen.", "Papperskorg");
        });
    });

    // Restore from Trash
    $(document).on('click', '.Restore', function () {
        let $card = $(this).closest('[data-orderId]');
        let orderId = $card.attr('data-orderId');

        $.post('php/functions/restoreOrder.php', { id: orderId }, function () {
            message("Uppdraget har återställts.", "Återställd");
            loadSection('canceled', false);
        });
    });

    // Change order redirect
    $(document).on('click', '.change', function () {
        let orderId = $(this).closest('[data-orderId]').attr('data-orderId');
        if (typeof window.openOrderCorrection === 'function') window.openOrderCorrection(orderId);
    });

    /* ==========================================================================
       6. Steps, Images & Expand Details
       ========================================================================== */

    function addToOrder() {
        $('.order').each(function () {
            let id = $(this).data('orderid');
            let $stepsContainer = $(this).find('[data-steps="' + id + '"]');
            if ($stepsContainer.length && !$stepsContainer.children().length) {
                $.get('php/functions/getSteps.php', { orderId: id }, function (success) {
                    $stepsContainer.html(success);
                });
            }
        });
    }

    /* ==========================================================================
       Task discussions
       ========================================================================== */

    function threadMessageElement(item) {
        const $item = $('<article class="taskThreadMessage">').attr('data-message-id', Number(item.id));
        const $meta = $('<div class="taskThreadMessageMeta">');
        const date = new Date(String(item.created_at).replace(' ', 'T'));
        $meta.append($('<strong>').text(item.author_name || 'Okänd användare'));
        if (item.source === 'attest' || item.source === 'rework') {
            $item.addClass('is-stamped');
            $meta.append($('<span class="taskThreadStamp">').text('Stämplad').attr('title', item.source === 'attest' ? 'Kommentar från attestering' : 'Kommentar från komplettering'));
            $meta.append($('<span class="taskThreadStampOrigin">').text(item.source === 'attest' ? 'från attestering' : 'från komplettering'));
        }
        $meta.append($('<time>').text(Number.isNaN(date.getTime()) ? item.created_at : date.toLocaleString('sv-SE', { dateStyle: 'short', timeStyle: 'short' })));
        $item.append($meta, $('<p>').text(item.body));
        return $item;
    }

    function threadIsVisible($thread) {
        return !$thread.find('.taskThreadPanel').prop('hidden') &&
            document.visibilityState === 'visible' &&
            (!$('body').hasClass('focusOpen') || $thread.closest('#focusContent').length > 0);
    }

    function markThreadMessagesViewed($thread, items) {
        const ids = items.map(function (item) { return Number(item.id); });
        if (!ids.length || !threadIsVisible($thread)) return;
        $.post('php/functions/taskThread.php', {
            action: 'read', orderId: Number($thread.attr('data-thread-order-id')),
            messageIds: ids, csrf: WorkflowCsrf
        }, refreshNotifications);
    }

    function loadThread($thread, mode) {
        if (!$thread.length || !threadIsVisible($thread)) return;
        if ($thread.data('threadLoading')) {
            $thread.data('threadPendingMode', mode === 'initial' ? 'initial' : ($thread.data('threadPendingMode') || 'newer'));
            return;
        }
        const $messages = $thread.find('.taskThreadMessages');
        const firstId = Number($messages.children().first().attr('data-message-id')) || 0;
        const lastId = Number($messages.children().last().attr('data-message-id')) || 0;
        if (mode === 'older' && !firstId) return;
        if (mode === 'newer' && !lastId) mode = 'initial';
        const data = { orderId: Number($thread.attr('data-thread-order-id')) };
        if (mode === 'older') data.beforeId = firstId;
        if (mode === 'newer') data.afterId = lastId;
        $thread.data('threadLoading', true);
        if (mode !== 'newer') $thread.find('.taskThreadStatus').text('Hämtar diskussionen…');
        $.getJSON('php/functions/taskThread.php', data).done(function (result) {
            if (!threadIsVisible($thread)) return;
            const items = result.messages || [];
            const nodes = items.map(threadMessageElement);
            if (mode === 'initial') $messages.empty();
            if (mode === 'older') $messages.prepend(nodes);
            else $messages.append(nodes);
            if (mode !== 'newer') $thread.find('.taskThreadOlder').prop('hidden', !result.hasMore);
            $thread.find('.taskThreadForm').prop('hidden', !result.canWrite);
            $thread.find('.taskThreadLocked').prop('hidden', !!result.canWrite);
            $thread.find('.taskThreadStatus').text($messages.children().length ? '' : 'Inga inlägg ännu.');
            if (mode === 'initial' && $messages.length) $messages.scrollTop($messages[0].scrollHeight);
            if ($thread.data('threadScrollAfterLoad')) {
                $messages.scrollTop($messages[0].scrollHeight);
                $thread.removeData('threadScrollAfterLoad');
            }
            markThreadMessagesViewed($thread, items);
            if (mode === 'newer' && result.hasMore) {
                window.setTimeout(function () { loadThread($thread, 'newer'); }, 0);
            }
        }).fail(function (xhr) {
            $thread.find('.taskThreadStatus').text((xhr.responseJSON && xhr.responseJSON.error) || 'Diskussionen kunde inte hämtas.');
        }).always(function () {
            $thread.data('threadLoading', false);
            const pending = $thread.data('threadPendingMode');
            if (pending) {
                $thread.removeData('threadPendingMode');
                loadThread($thread, pending);
            }
        });
    }

    function openThread($thread) {
        if (!$thread.length) return;
        $thread.find('.taskThreadPanel').prop('hidden', false);
        $thread.find('.taskThreadToggle').attr('aria-expanded', 'true');
        loadThread($thread, 'initial');
    }

    $(document).on('click', '.taskThreadToggle', function (event) {
        event.stopPropagation();
        const $thread = $(this).closest('.taskThread');
        if ($thread.find('.taskThreadPanel').prop('hidden')) openThread($thread);
        else {
            $thread.find('.taskThreadPanel').prop('hidden', true);
            $(this).attr('aria-expanded', 'false');
        }
    });
    $(document).on('click', '.taskThreadOlder', function (event) {
        event.stopPropagation();
        loadThread($(this).closest('.taskThread'), 'older');
    });
    $(document).on('submit', '.taskThreadForm', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const $thread = $(this).closest('.taskThread');
        const $input = $(this).find('.taskThreadInput');
        const body = $input.val().trim();
        if (!body) return;
        const $button = $(this).find('.taskThreadSend').prop('disabled', true);
        $thread.find('.taskThreadStatus').text('Skickar…');
        $.post('php/functions/taskThread.php', {
            action: 'send', orderId: Number($thread.attr('data-thread-order-id')),
            body: body, csrf: WorkflowCsrf
        }, null, 'json').done(function () {
            $input.val('');
            $thread.find('.taskThreadStatus').text('');
            $thread.data('threadScrollAfterLoad', true);
            loadThread($thread, 'newer');
            refreshNotifications();
        }).fail(function (xhr) {
            $thread.find('.taskThreadStatus').text((xhr.responseJSON && xhr.responseJSON.error) || 'Inlägget kunde inte skickas.');
        }).always(function () { $button.prop('disabled', false); });
    });
    window.setInterval(function () {
        $('.taskThreadPanel').each(function () {
            const $thread = $(this).closest('.taskThread');
            if (threadIsVisible($thread)) loadThread($thread, 'newer');
        });
    }, 30000);

    // Image Gallery Modal
    $(document).on('click', '.openGallery', function () {
        let id = $(this).data('path');
        $.get('php/functions/getImages.php', { 'orderid': id }, function (success) {
            $('body').prepend(`
                <div class="generalModal galleryModal">
                    <div class="close"><button class="closeGallery">✕</button></div>
                    <div class="imageHolder">${success}</div>
                </div>
            `);
            $('.generalModal').show();
        });
    });

    $(document).on('click', '.closeGallery', function () {
        $('.generalModal.galleryModal').remove();
    });

    // Expand / Collapse Order Description
    $(document).on('click', '.readMore', function () {
        let orderId = $(this).data('orderid');
        let $desc = $('[data-orderid="' + orderId + '"] .desc');

        if (!$desc.hasClass('autoHeight')) {
            $desc.addClass('autoHeight').removeClass('masked');
            $(this).addClass('rotate');
        } else {
            $desc.removeClass('autoHeight').addClass('masked');
            $(this).removeClass('rotate');
        }
    });

    /* ==========================================================================
       7. Focus Mode
       ========================================================================== */

    function showFocus(order, openDiscussion = false) {
        if (!order || !$('#focusOverlay').length) return;
        $.get('php/functions/getOrders.php', { dir: 'focus', orderId: order.id }, function (html) {
            if (!html || html.trim() === 'empty') { closeFocus(); return; }
            focusedOrderId = String(order.id);
            temporaryView = false;
            $('#focusTitle').text('Fokus: ' + order.title);
            $('#focusContent').html(html);
            $('#focusOverlay').prop('hidden', false);
            $('body').addClass('focusOpen');
            addToOrder();
            if (openDiscussion) openThread($('#focusContent .taskThread').first());
        }).fail(function () { message('Kunde inte öppna uppgiften.', 'Fokusläge'); });
    }

    function restoreFocus() {
        if (!$('#focusOverlay').length) return;
        $.getJSON('php/functions/focusOrder.php', function (result) {
            if (result.order) showFocus(result.order);
        });
    }

    function showReadOnly(orderId, openDiscussion = false) {
        $.get('php/functions/getOrders.php', { dir: 'single', orderId: orderId }, function (html) {
            if (!html || html.trim() === 'empty') { message('Uppgiften är inte längre tillgänglig.', 'Notifikationer'); return; }
            temporaryView = true;
            $('#focusContent').html(html);
            $('#focusTitle').text('Uppgift #' + orderId);
            $('#focusOverlay').prop('hidden', false);
            $('body').addClass('focusOpen');
            addToOrder();
            if (openDiscussion) openThread($('#focusContent .taskThread').first());
        });
    }

    function setFocus(orderId, allowReadOnly = false, openDiscussion = false) {
        $.post('php/functions/focusOrder.php', { action: 'set', orderId: orderId, csrf: WorkflowCsrf }, function (result) {
            showFocus(result.order, openDiscussion);
        }, 'json').fail(function () {
            if (allowReadOnly) showReadOnly(orderId, openDiscussion);
            else message('Uppgiften kan inte öppnas i fokusläge.', 'Fokusläge');
        });
    }

    function closeFocus() {
        if (temporaryView) {
            temporaryView = false;
            $('#focusOverlay').prop('hidden', true);
            $('#focusContent').empty();
            $('body').removeClass('focusOpen');
            restoreFocus();
            return;
        }
        focusedOrderId = null;
        $('#focusOverlay').prop('hidden', true);
        $('#focusContent').empty();
        $('body').removeClass('focusOpen');
        $.post('php/functions/focusOrder.php', { action: 'clear', csrf: WorkflowCsrf });
    }

    $(document).on('click', '.focusOnOrder', function () {
        const id = String($(this).closest('[data-orderid]').attr('data-orderid'));
        if (id === focusedOrderId) closeFocus(); else setFocus(id);
    });
    $('#focusClose').on('click', closeFocus);
    $('#focusOverlay').on('click', function (event) { if (event.target === this) closeFocus(); });

    function escapeNotification(value) { return $('<span>').text(value == null ? '' : String(value)).html(); }
    const notificationLabels = {
        assigned: 'tilldelade eller omfördelade uppgiften', unassigned: 'tog bort din tilldelning', updated: 'ändrade uppgiften',
        step_completed: 'slutförde ett steg', step_reopened: 'öppnade ett steg igen', pending: 'skickade uppgiften för granskning',
        rework: 'begärde komplettering', completed: 'godkände uppgiften',
        canceled: 'flyttade uppgiften till papperskorgen', restored: 'återställde uppgiften',
        thread_message: 'skrev i diskussionen'
    };

    function refreshNotifications() {
        $.getJSON('php/functions/notifications.php', function (result) {
            const count = Number(result.unread || 0);
            $('#notificationCount').text(count > 99 ? '99+' : count).prop('hidden', count === 0);
            $('#notificationBell').attr('aria-label', 'Notifikationer, ' + count + ' olästa');
            $('[data-thread-order-id]').each(function () {
                const orderId = $(this).attr('data-thread-order-id');
                const total = Number((result.threadTotal || {})[orderId] || 0);
                const unread = Number((result.threadUnread || {})[orderId] || 0);
                $(this).find('.taskThreadCount').text(total)
                    .attr('aria-label', total + (total === 1 ? ' kommentar' : ' kommentarer'));
                $(this).find('.taskThreadUnread').text(unread === 1 ? '1 ny' : unread > 99 ? '99+ nya' : unread + ' nya')
                    .attr('aria-label', unread + (unread === 1 ? ' oläst kommentar' : ' olästa kommentarer'))
                    .prop('hidden', unread === 0);
            });
            const groups = new Map();
            (result.items || []).forEach(function (item) {
                const isThread = item.event_type === 'thread_message';
                const key = String(item.order_id) + (isThread ? ':thread' : ':event');
                if (!groups.has(key)) groups.set(key, { latest: item, ids: [], unread: false, isThread: isThread });
                const group = groups.get(key);
                group.ids.push(Number(item.id));
                if (!item.read_at) group.unread = true;
            });
            if (!groups.size) { $('#notificationList').html('<p class="notificationEmpty">Inga notifikationer ännu.</p>'); return; }
            const markup = Array.from(groups.values()).map(function (group) {
                const item = group.latest;
                const action = notificationLabels[item.event_type] || 'uppdaterade uppgiften';
                const date = new Date(String(item.created_at).replace(' ', 'T'));
                const time = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('sv-SE', { dateStyle: 'short', timeStyle: 'short' });
                const extra = group.ids.length > 1 ? '<span class="notificationGrouped">' + group.ids.length + ' händelser</span>' : '';
                return '<button type="button" class="notificationItem' + (group.unread ? ' unread' : '') + '" data-order-id="' + Number(item.order_id) + '" data-kind="' + (group.isThread ? 'thread' : 'event') + '" data-notification-ids="' + group.ids.join(',') + '">' +
                    '<span class="notificationDot"></span><span class="notificationBody"><strong>' + escapeNotification(item.order_title) + '</strong>' +
                    '<span>' + escapeNotification(item.actor_name) + ' ' + escapeNotification(action) + '</span>' +
                    (item.detail ? '<small>' + escapeNotification(item.detail) + '</small>' : '') +
                    '<small>' + escapeNotification(time) + extra + '</small></span></button>';
            }).join('');
            $('#notificationList').html(markup);
        });
    }

    $('#notificationBell').on('click', function () {
        const opening = $('#notificationPopover').prop('hidden');
        $('#notificationPopover').prop('hidden', !opening);
        $(this).attr('aria-expanded', opening ? 'true' : 'false');
        if (opening) refreshNotifications();
    });
    $('#notificationReadAll').on('click', function () {
        $.post('php/functions/notifications.php', { all: '1', csrf: WorkflowCsrf }, refreshNotifications);
    });
    $(document).on('click', '.notificationItem', function () {
        const ids = String($(this).attr('data-notification-ids')).split(',');
        const orderId = Number($(this).attr('data-order-id'));
        const isThread = $(this).attr('data-kind') === 'thread';
        if (!isThread) $.post('php/functions/notifications.php', { ids: ids, csrf: WorkflowCsrf }, refreshNotifications);
        $('#notificationPopover').prop('hidden', true);
        $('#notificationBell').attr('aria-expanded', 'false');
        setFocus(orderId, true, isThread);
    });

    /* ==========================================================================
       8. Spotlight Search Integration
       ========================================================================== */

    let searchDebounceTimer = null;
    $(".searchOrder").on('input', function () {
        clearTimeout(searchDebounceTimer);
        let query = $(this).val().trim();

        if (query === '') {
            $('.results').html('');
            loadSection(currentDir, false);
            return;
        }

        searchDebounceTimer = setTimeout(function () {
            $.post('php/functions/searchOrders.php', { 'string': query }, function (success) {
                let trimmed = (success || '').trim();
                if (trimmed === 'empty' || trimmed === '' || trimmed === '0') {
                    $('.results').html('0 resultat');
                    $('.orders').html(`
                        <div class="ordersEmptyState">
                            <div class="emptyIcon">🔍</div>
                            <h3>Inga träffar</h3>
                            <p>Inga uppgifter matchade "${$('<div>').text(query).html()}".</p>
                        </div>
                    `);
                } else {
                    $('.orders').html(trimmed);
                    let count = $('.orders').children('.order').length;
                    $('.results').html(count + ' resultat');
                    addToOrder();
                }
            });
        }, 220);
    });

});
