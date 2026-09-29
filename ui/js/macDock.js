/**
 * macOS Compact Text-Based Dock Behaviors
 * - Spotlight quick search bar toggle (Ctrl+K, Cmd+K, Escape)
 * - Click feedback and tab active state syncing
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        initSpotlightSearch();
        initDockSubmenu();
    });

    /**
     * Spotlight Quick Search Bar
     */
    function initSpotlightSearch() {
        const $popover = $('#spotlightPopover');
        const $input = $popover.find('.searchOrder');
        const $trigger = $('#dockSpotlightTrigger');
        const $closeBtn = $('#spotlightCloseBtn');

        function openSpotlight() {
            // Close submenu if open
            if ($('#dockSubmenuPopover').hasClass('active')) {
                $('#dockSubmenuPopover').removeClass('active');
                $('#dockMoreTrigger').removeClass('active').attr('aria-expanded', 'false');
            }
            $popover.addClass('active');
            $trigger.addClass('active');
            setTimeout(function () {
                $input.focus();
            }, 50);
        }

        function closeSpotlight() {
            $popover.removeClass('active');
            $trigger.removeClass('active');
            // If input had text and is closed, reset search
            if ($input.val() !== '') {
                $input.val('').trigger('input');
            }
        }

        // Toggle button click
        $trigger.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if ($popover.hasClass('active')) {
                closeSpotlight();
            } else {
                openSpotlight();
            }
        });

        // Close button click
        $closeBtn.on('click', function (e) {
            e.preventDefault();
            closeSpotlight();
        });

        // Global hotkeys: Ctrl+K or Cmd+K to toggle spotlight, Escape to close
        $(document).on('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if ($popover.hasClass('active')) {
                    closeSpotlight();
                } else {
                    openSpotlight();
                }
            } else if (e.key === 'Escape' && $popover.hasClass('active')) {
                closeSpotlight();
            }
        });

        // Click outside closes spotlight
        $(document).on('click', function (e) {
            if ($popover.hasClass('active')) {
                if (!$(e.target).closest('#spotlightPopover').length &&
                    !$(e.target).closest('#dockSpotlightTrigger').length) {
                    closeSpotlight();
                }
            }
        });
    }

    /**
     * Dock Submenu Popover (Admin, Papperskorg, Logga ut)
     */
    function initDockSubmenu() {
        const $popover = $('#dockSubmenuPopover');
        const $trigger = $('#dockMoreTrigger');
        const $closeBtn = $('#dockSubmenuCloseBtn');

        function openSubmenu() {
            // Close spotlight if open
            if ($('#spotlightPopover').hasClass('active')) {
                $('#spotlightPopover').removeClass('active');
                $('#dockSpotlightTrigger').removeClass('active');
            }
            $popover.addClass('active');
            $trigger.addClass('active').attr('aria-expanded', 'true');
        }

        function closeSubmenu() {
            $popover.removeClass('active');
            $trigger.removeClass('active').attr('aria-expanded', 'false');
        }

        // Toggle button click
        $trigger.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if ($popover.hasClass('active')) {
                closeSubmenu();
            } else {
                openSubmenu();
            }
        });

        // Close button click
        $closeBtn.on('click', function (e) {
            e.preventDefault();
            closeSubmenu();
        });

        // Close on Escape
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $popover.hasClass('active')) {
                closeSubmenu();
            }
        });

        // Click outside closes submenu
        $(document).on('click', function (e) {
            if ($popover.hasClass('active')) {
                if (!$(e.target).closest('#dockSubmenuPopover').length &&
                    !$(e.target).closest('#dockMoreTrigger').length) {
                    closeSubmenu();
                }
            }
        });

        // When Papperskorg (.recycle) is clicked inside the submenu, close submenu
        $popover.on('click', '.recycle', function () {
            closeSubmenu();
        });

        $popover.on('click', '#dockWorkflowBtn', function () {
            closeSubmenu();
        });

    }

})(jQuery);
