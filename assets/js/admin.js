/* global elfData, ajaxurl, jQuery */
(function ($) {
    'use strict';

    // Use WordPress's built-in ajaxurl (already on the correct origin/port)
    // Fall back to our localised value only if somehow unavailable.
    var AJAX_URL = typeof ajaxurl !== 'undefined' ? ajaxurl : elfData.ajax_url;

    // ── Helpers ────────────────────────────────────────────────────────────

    function setStatus($el, msg, type) {
        $el.text(msg)
           .removeClass('is-error is-success')
           .addClass(type || '')
           .show();
    }

    function clearStatus($el) {
        setTimeout(function () { $el.text('').hide(); }, 4000);
    }

    // ── Copy feed URL ──────────────────────────────────────────────────────

    $(document).on('click', '.elf-copy-btn', function () {
        var $btn = $(this);
        var url  = $btn.data('url');

        if (!navigator.clipboard) {
            window.prompt('Copy this URL:', url);
            return;
        }

        navigator.clipboard.writeText(url).then(function () {
            var orig = $btn.text();
            $btn.text('Copied!').addClass('copied');
            setTimeout(function () { $btn.text(orig).removeClass('copied'); }, 2000);
        });
    });

    // ── Save settings ──────────────────────────────────────────────────────

    $('#elf-settings-form').on('submit', function (e) {
        e.preventDefault();

        var $btn    = $('#elf-save-btn');
        var $status = $('#elf-save-status');
        var data    = $(this).serializeArray();

        $btn.prop('disabled', true);
        setStatus($status, elfData.i18n.saving, '');

        // Normalise checkboxes (serializeArray omits unchecked)
        var hasStock = data.some(function (f) { return f.name === 'include_out_of_stock'; });
        if (!hasStock) {
            data.push({ name: 'include_out_of_stock', value: 'no' });
        }
        var hasLogging = data.some(function (f) { return f.name === 'enable_logging'; });
        if (!hasLogging) {
            data.push({ name: 'enable_logging', value: 'no' });
        }

        data.push({ name: 'action', value: 'elf_save_settings' });
        data.push({ name: 'nonce',  value: elfData.nonce });

        $.post(AJAX_URL, data)
            .done(function (res) {
                if (res.success) {
                    setStatus($status, elfData.i18n.saved, 'is-success');
                } else {
                    setStatus($status, res.data.message || elfData.i18n.error, 'is-error');
                }
            })
            .fail(function () { setStatus($status, elfData.i18n.error, 'is-error'); })
            .always(function () {
                $btn.prop('disabled', false);
                clearStatus($status);
            });
    });

    // ── Regenerate feeds ───────────────────────────────────────────────────

    $('#elf-regenerate-btn').on('click', function () {
        var $btn    = $(this);
        var $status = $('#elf-regen-status');
        var startTime = Date.now();

        $btn.prop('disabled', true);
        setStatus($status, elfData.i18n.generating, '');

        // Update status every second to show progress
        var progressInterval = setInterval(function () {
            var elapsed = Math.round((Date.now() - startTime) / 1000);
            $status.text(elfData.i18n.generating + ' (' + elapsed + 's)');
        }, 1000);

        $.post(AJAX_URL, {
            action: 'elf_regenerate_feeds',
            nonce:  elfData.nonce,
        })
        .done(function (res) {
            clearInterval(progressInterval);
            
            if (res.success) {
                var message = res.data.message || elfData.i18n.generated;
                setStatus($status, message, 'is-success');
                // Reload to show updated last-generated dates
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                setStatus($status, res.data.message || elfData.i18n.error, 'is-error');
                clearStatus($status);
            }
        })
        .fail(function () {
            clearInterval(progressInterval);
            setStatus($status, elfData.i18n.error, 'is-error');
            clearStatus($status);
        })
        .always(function () { 
            $btn.prop('disabled', false); 
        });
    });

    // ── Taxonomy per-row search ────────────────────────────────────────────

    var searchTimers = {};  // keyed by term-id so each row debounces independently

    $(document).on('input', '.elf-tax-search', function () {
        var $input   = $(this);
        var $wrap    = $input.closest('.elf-tax-search-wrap');
        var termId   = $wrap.data('term-id');
        var $drop    = $wrap.find('.elf-tax-dropdown');
        var q        = $input.val().trim();

        clearTimeout(searchTimers[termId]);

        // User cleared the field — reset hidden value and badge
        if (q === '') {
            $wrap.find('.elf-tax-value').val(0);
            $drop.hide().empty();
            $wrap.find('.elf-mapped-badge')
                 .text('Unmapped')
                 .removeClass('elf-badge--ok')
                 .addClass('elf-badge--warn');
            return;
        }

        if (q.length < 2) {
            $drop.hide().empty();
            return;
        }

        searchTimers[termId] = setTimeout(function () {
            $.post(AJAX_URL, {
                action: 'elf_search_taxonomy',
                nonce:  elfData.nonce,
                q:      q,
            })
            .done(function (res) {
                $drop.empty();

                if (!res.success || !res.data.results || !res.data.results.length) {
                    $drop.hide();
                    return;
                }

                $.each(res.data.results, function (_, item) {
                    $('<div class="elf-tax-result">')
                        .text('[' + item.id + '] ' + item.text)
                        .data('id', item.id)
                        .data('label', '[' + item.id + '] ' + item.text)
                        .appendTo($drop);
                });

                $drop.show();
            })
            .fail(function () { $drop.hide().empty(); });
        }, 300);
    });

    // Select a result from the dropdown
    $(document).on('click', '.elf-tax-result', function (e) {
        e.stopPropagation();

        var $result = $(this);
        var $wrap   = $result.closest('.elf-tax-search-wrap');

        $wrap.find('.elf-tax-search').val($result.data('label'));
        $wrap.find('.elf-tax-value').val($result.data('id'));
        $wrap.find('.elf-tax-dropdown').hide().empty();
        $wrap.find('.elf-mapped-badge')
             .text('Mapped')
             .removeClass('elf-badge--warn')
             .addClass('elf-badge--ok');
    });

    // Close any open dropdown when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.elf-tax-search-wrap').length) {
            $('.elf-tax-dropdown').hide().empty();
        }
    });

    // ── Save category map ──────────────────────────────────────────────────

    $('#elf-category-map-form').on('submit', function (e) {
        e.preventDefault();

        var $btn    = $('#elf-save-map-btn');
        var $status = $('#elf-map-save-status');
        var map     = {};

        // Collect from hidden inputs (one per row)
        $('.elf-tax-search-wrap').each(function () {
            var termId   = $(this).data('term-id');
            var googleId = $(this).find('.elf-tax-value').val();
            if (googleId && googleId !== '0') {
                map[termId] = googleId;
            }
        });

        $btn.prop('disabled', true);
        setStatus($status, elfData.i18n.saving, '');

        $.post(AJAX_URL, {
            action:       'elf_save_category_map',
            nonce:        elfData.nonce,
            category_map: map,
        })
        .done(function (res) {
            if (res.success) {
                setStatus($status, elfData.i18n.saved, 'is-success');
            } else {
                setStatus($status, res.data.message || elfData.i18n.error, 'is-error');
            }
        })
        .fail(function () { setStatus($status, elfData.i18n.error, 'is-error'); })
        .always(function () {
            $btn.prop('disabled', false);
            clearStatus($status);
        });
    });

    // ── Export settings ─────────────────────────────────────────────────────

    $('#elf-export-btn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.text(elfData.i18n.exporting);

        $.post(AJAX_URL, {
            action: 'elf_export_settings',
            nonce:  elfData.nonce,
        })
        .done(function (res) {
            if (res.success && res.data.settings) {
                // Create and download JSON file
                var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(res.data.settings, null, 2));
                var downloadAnchorNode = document.createElement('a');
                downloadAnchorNode.setAttribute("href", dataStr);
                downloadAnchorNode.setAttribute("download", "excellink-feeds-settings-" + new Date().toISOString().slice(0,10) + ".json");
                document.body.appendChild(downloadAnchorNode);
                downloadAnchorNode.click();
                downloadAnchorNode.remove();
                
                $btn.text(elfData.i18n.exported);
                setTimeout(function () { $btn.prop('disabled', false).text('Export Settings'); }, 2000);
            } else {
                alert(res.data.message || elfData.i18n.error);
                $btn.prop('disabled', false).text('Export Settings');
            }
        })
        .fail(function () {
            alert(elfData.i18n.error);
            $btn.prop('disabled', false).text('Export Settings');
        });
    });

    // ── Import settings ─────────────────────────────────────────────────────

    $('#elf-import-file').on('change', function () {
        var file = this.files[0];
        if (!file) return;

        var $status = $('#elf-import-status');
        var reader = new FileReader();

        reader.onload = function(e) {
            var settings = e.target.result;
            setStatus($status, elfData.i18n.importing, '');

            $.post(AJAX_URL, {
                action:   'elf_import_settings',
                nonce:    elfData.nonce,
                settings: settings,
            })
            .done(function (res) {
                if (res.success) {
                    setStatus($status, elfData.i18n.imported, 'is-success');
                    // Reload to show imported settings
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    setStatus($status, res.data.message || elfData.i18n.error, 'is-error');
                    clearStatus($status);
                }
            })
            .fail(function () {
                setStatus($status, elfData.i18n.error, 'is-error');
                clearStatus($status);
            });
        };

        reader.readAsText(file);
        
        // Reset file input
        $(this).val('');
    });

})(jQuery);
