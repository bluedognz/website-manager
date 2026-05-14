jQuery(function ($) {

    // =========================================================================
    // TOGGLES
    // All checkboxes are inside <label> elements. The browser handles the
    // native toggle. We listen on 'change' to sync visual state only.
    // =========================================================================

    $(document).on('change', '.wm-toggle-input', function () {
        var $input  = $(this);
        var checked = $input.prop('checked');
        var slug    = $input.data('slug');

        // Sync the card-row and feature-card active classes
        var $row  = $input.closest('.wm-card-row');
        var $card = $row.find('.wm-feature-card');
        $row.toggleClass('is-active', checked);
        $card.toggleClass('is-active', checked);

        // For cards with controls panel, sync that too
        var $controls = $row.find('.wm-card-controls');
        if ($controls.length) {
            // Show/hide gear
            $controls.find('.wm-config-btn').toggleClass('is-hidden', !checked);
            // Show warning if bb_dashboard on but no template chosen
            if (slug === 'bb_dashboard') {
                var hasTpl = !!$('#wm-bb-dashboard-id').val();
                $row.find('.wm-feature-meta--warn').toggle(checked && !hasTpl);
            }
        }

        // Settings page: white label toggle dims dependent fields
        if ($input.attr('id') === 'wm-wl-toggle') {
            syncWlFields();
        }

        applyFilter();
    });

    // =========================================================================
    // SEARCH & FILTER
    // =========================================================================

    var $rows         = $('.wm-card-row[data-slug]');
    var $filterTabs   = $('.wm-filter-tab');
    var $searchInput  = $('.wm-search-input');
    var $noResults    = $('.wm-no-results');
    var $countLabel   = $('.wm-results-count');
    var total         = $rows.length;
    var currentFilter = 'all';
    var searchTerm    = '';

    $filterTabs.on('click', function () {
        $filterTabs.removeClass('is-active');
        $(this).addClass('is-active');
        currentFilter = $(this).data('filter');
        applyFilter();
    });

    $searchInput.on('input', function () {
        searchTerm = $(this).val().toLowerCase().trim();
        applyFilter();
    });

    function applyFilter() {
        var visible = 0;
        $rows.each(function () {
            var $row     = $(this);
            var isActive = $row.find('.wm-toggle-input').prop('checked');
            var label    = $row.find('.wm-feature-label').text().toLowerCase();
            var desc     = $row.find('.wm-feature-desc').text().toLowerCase();
            var filterOk = currentFilter === 'all'
                        || (currentFilter === 'active'   &&  isActive)
                        || (currentFilter === 'inactive' && !isActive);
            var searchOk = !searchTerm || label.includes(searchTerm) || desc.includes(searchTerm);
            var show = filterOk && searchOk;
            $row.toggleClass('is-hidden', !show);
            if (show) visible++;
        });
        if ($countLabel.length) $countLabel.text('Showing ' + visible + ' of ' + total + ' modules');
        if ($noResults.length)  $noResults.toggleClass('is-visible', visible === 0);
    }

    // =========================================================================
    // GEAR BUTTON — opens template picker modal
    // The gear is inside .wm-card-controls which is OUTSIDE the <label>,
    // so clicking it has zero effect on the checkbox.
    // =========================================================================

    var selectedTplId    = String($('#wm-bb-dashboard-id').val() || '');
    var selectedTplTitle = '';

    $(document).on('click', '#wm-gear-bb-dashboard', function () {
        openTemplateModal();
    });

    function openTemplateModal() {
        var $modal = $('#wm-bb-template-modal');
        var $list  = $('#wm-template-list');
        var $btn   = $('#wm-template-confirm');

        $modal.addClass('is-open');
        $btn.prop('disabled', !selectedTplId);
        $list.html('<div class="wm-template-loading">Loading templates…</div>');

        $.ajax({
            url:  wmData.ajaxUrl,
            type: 'POST',
            data: { action: 'wm_get_bb_templates', nonce: wmData.nonce },
            success: function (res) {
                if (!res.success) {
                    $list.html('<div class="wm-template-empty">' + esc(res.data) + '</div>');
                    return;
                }
                var html = '';
                $.each(res.data, function (id, title) {
                    var sel = String(id) === selectedTplId;
                    html += '<button type="button" class="wm-template-item' + (sel ? ' is-selected' : '') + '" '
                          + 'data-id="' + id + '" data-title="' + esc(title) + '">'
                          + '<span class="wm-template-item-icon">📄</span>'
                          + '<span class="wm-template-item-title">' + esc(title) + '</span>'
                          + (sel ? '<span class="wm-template-item-check">✓</span>' : '')
                          + '</button>';
                });
                $list.html(html || '<div class="wm-template-empty">No Beaver Builder templates found.</div>');
            },
            error: function () {
                $list.html('<div class="wm-template-empty">Could not load templates.</div>');
            }
        });
    }

    $(document).on('click', '.wm-template-item', function () {
        $('.wm-template-item').removeClass('is-selected').find('.wm-template-item-check').remove();
        $(this).addClass('is-selected').append('<span class="wm-template-item-check">✓</span>');
        selectedTplId    = String($(this).data('id'));
        selectedTplTitle = $(this).data('title');
        $('#wm-template-confirm').prop('disabled', false);
    });

    $('#wm-template-confirm').on('click', function () {
        $('#wm-bb-dashboard-id').val(selectedTplId);

        // Update meta line on the BB card
        var $row = $('[data-slug="bb_dashboard"]');
        $row.find('.wm-feature-meta').remove();
        $row.find('.wm-feature-info').append(
            '<span class="wm-feature-meta">Template: <strong>' + esc(selectedTplTitle) + '</strong></span>'
        );

        $('#wm-bb-template-modal').removeClass('is-open');
    });

    // =========================================================================
    // EXPORT / IMPORT MODALS
    // =========================================================================

    $('#wm-export-btn').on('click', function () {
        $('#wm-export-modal').addClass('is-open');
        setTimeout(function () { $('#wm-export-string').select(); }, 60);
    });

    $('#wm-export-copy').on('click', function () {
        $('#wm-export-string').select();
        document.execCommand('copy');
        var $btn = $(this);
        $btn.text('Copied!');
        setTimeout(function () { $btn.text('Copy'); }, 2000);
    });

    $('#wm-import-btn').on('click', function () {
        $('#wm-import-modal').addClass('is-open');
        setTimeout(function () { $('#wm-import-string').focus(); }, 60);
    });

    $('#wm-import-submit').on('click', function () {
        var val = $('#wm-import-string').val().trim();
        if (!val) return;
        $('#wm-import-field').val(val);
        $('#wm-form').submit();
    });

    // =========================================================================
    // CLOSE MODALS
    // =========================================================================

    $(document).on('click', '.wm-modal-overlay', function (e) {
        if ($(e.target).hasClass('wm-modal-overlay')) $(this).removeClass('is-open');
    });
    $(document).on('click', '.wm-modal-close', function () {
        $(this).closest('.wm-modal-overlay').removeClass('is-open');
    });

    // =========================================================================
    // WHITE LABEL FIELD DIMMING (settings page)
    // =========================================================================

    function syncWlFields() {
        var on = $('#wm-wl-toggle').prop('checked');
        $('.wm-wl-field').toggleClass('wm-field-disabled', !on);
    }

    if ($('#wm-wl-toggle').length) syncWlFields();

    // =========================================================================
    // AUTO-HIDE SAVED NOTICE
    // =========================================================================

    if ($('.wm-saved-msg.is-visible').length) {
        setTimeout(function () { $('.wm-saved-msg').removeClass('is-visible'); }, 4000);
    }

    // =========================================================================
    // UTIL
    // =========================================================================

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Initial render
    applyFilter();
});
