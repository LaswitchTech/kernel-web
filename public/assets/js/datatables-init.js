/**
 * KernelWeb — shared DataTables initializer
 *
 * Provides two entry points:
 *   KernelWeb.dt.init(selector, options)        — full table (search, pagination, length, buttons)
 *   KernelWeb.dt.initCompact(selector, options) — summary table (no pagination/search/length)
 *
 * Both merge the supplied options on top of their respective defaults so
 * per-table overrides (e.g. custom column order, pageLength) are easy.
 *
 * Depends on: jQuery, DataTables 2.3.x, DataTables Buttons 3.2.x
 *
 * Deferred init:
 *   If a view calls KernelWeb.dt.init() before this script loads, the call is
 *   silently queued and replayed after this script finishes executing.
 */
(function (window, $) {
    'use strict';

    // Guard: the layout <head> inline script always defines window.KernelWeb
    // and window.KernelWeb._dtInitQueue. If missing, create an empty queue
    // so this file is safe when loaded standalone (e.g. blank layout).
    window.KernelWeb = window.KernelWeb || {};
    if (!window.KernelWeb._dtInitQueue) {
        window.KernelWeb._dtInitQueue = null;
    }

    // ── Defaults (DataTables 2 layout API) ──
    // Layout:
    //   topStart  : buttons (left)
    //   topEnd    : search   (right)
    //   bottomStart: pageLength (left)
    //   bottom     : info     (center)
    //   bottomEnd  : paging   (right)
    var DEFAULTS = {
        pageLength : 25,
        lengthMenu : [10, 25, 50, 100],
        responsive : true,
        layout     : {
            topStart:   'buttons',
            topEnd:     'search',
            bottomStart: 'pageLength',
            bottom:     'info',
            bottomEnd:  'paging'
        },
        language   : {
            emptyTable        : 'No data available.',
            zeroRecords       : 'No matching records found.',
            info              : 'Showing _START_–_END_ of _TOTAL_',
            infoEmpty         : 'No entries',
            infoFiltered      : '(filtered from _MAX_)',
            lengthMenu        : 'Show _MENU_',
            search            : '',
            searchPlaceholder : 'Search…',
            paginate          : {
                first    : '«',
                previous : '‹',
                next     : '›',
                last     : '»',
            },
        },
    };

    // Compact preset: table only, no controls.
    var COMPACT_DEFAULTS = $.extend(true, {}, DEFAULTS, {
        paging    : false,
        searching : false,
        info      : false,
        layout    : { topStart: null, topEnd: null, bottomStart: null, bottom: null, bottomEnd: null },
        buttons   : null,
        responsive: false,
    });

    // ── Real implementations (DataTables 2.x) ──
    function init(selector, options) {
        var tableEl = $(selector).first();
        if (tableEl.length === 0) return null;

        // Merge view options onto defaults
        var config = $.extend(true, {}, DEFAULTS, options || {});

        // Extract custom buttons and wire into layout.
        // DT2 layout slot value is { featureName: featureConfig }.
        // For the buttons feature, featureConfig = { buttons: [...] }.
        // So: layout.topStart = { buttons: { buttons: [...] } }
        var customButtons = Array.isArray(config.buttons) ? config.buttons : [];
        delete config.buttons;
        delete config.buttonClasses;

        config.layout = {
            topStart: customButtons.length
                ? { buttons: { buttons: customButtons } }
                : null,
            topEnd:     'search',
            bottomStart: 'pageLength',
            bottom:     'info',
            bottomEnd:  'paging'
        };

        return new DataTable(tableEl[0], config);
    }

    function initCompact(selector, options) {
        var tableEl = $(selector).first();
        if (tableEl.length === 0) return null;
        return new DataTable(tableEl[0], $.extend(true, {}, COMPACT_DEFAULTS, options || {}));
    }

    // Install real implementations on KernelWeb.
    window.KernelWeb.dt = { init: init, initCompact: initCompact };

    // ── Drain the head-stub queue ──
    // Views may have called KernelWeb.dt.init() before this script loaded;
    // the head bootstrap creates _dtInitQueue to collect those calls.
    var q = window.KernelWeb._dtInitQueue;
    window.KernelWeb._dtInitQueue = null;
    if (q) {
        for (var i = 0; i < q.length; i++) { q[i](); }
    }

}(window, jQuery));
