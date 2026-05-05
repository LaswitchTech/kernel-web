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
 * Depends on: jQuery, DataTables 1.13.x, DataTables Buttons 2.4.x
 *
 * Deferred init:
 *   If a view calls KernelWeb.dt.init() before this file loads, the call is
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

    // ── Defaults ──
    // Top row  : [Buttons / actions (left)]  [Search ~25% (right)]
    // Table    : rt
    // Bottom row: [Show N (left)]  [Info (centre)]  [Pagination (right)]
    var DOM_FULL =
        "<'row g-2 align-items-center mb-2'<'col-auto'B><'col-sm-4 ms-auto'f>>" +
        "rt" +
        "<'row g-2 align-items-center mt-2'<'col-sm-4'l><'col-sm-4 text-center'i><'col-sm-4'p>>";

    var DEFAULTS = {
        pageLength : 25,
        lengthMenu : [10, 25, 50, 100],
        responsive : true,
        dom        : DOM_FULL,
        buttons    : [],
        language   : {
            emptyTable        : 'No records to display.',
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

    // Compact preset: styled table only, no controls.
    var COMPACT_DEFAULTS = $.extend(true, {}, DEFAULTS, {
        paging    : false,
        searching : false,
        info      : false,
        dom       : 't',
        buttons   : null,
        responsive: false,
    });

    // ── Real implementations ──
    function init(selector, options) {
        return $(selector).DataTable($.extend(true, {}, DEFAULTS, options || {}));
    }

    function initCompact(selector, options) {
        return $(selector).DataTable($.extend(true, {}, COMPACT_DEFAULTS, options || {}));
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
