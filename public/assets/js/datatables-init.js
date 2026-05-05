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

    window.KernelWeb = window.KernelWeb || {};

    // ── Stub (queued init calls until this file loads) ──
    var _queue = [];
    window.KernelWeb.dt = {
        init: function(selector, options) {
            _queue.push(function() {
                return $(selector).DataTable($.extend(true, {}, _DEFAULTS, options || {}));
            });
        },
        initCompact: function(selector, options) {
            _queue.push(function() {
                return $(selector).DataTable($.extend(true, {}, _COMPACT_DEFAULTS, options || {}));
            });
        },
        _q: _queue
    };

    // ── Defaults ──
    // Top row  : [Buttons / actions (left)]  [Search ~25% (right)]
    // Table    : rt
    // Bottom row: [Show N (left)]  [Info (centre)]  [Pagination (right)]
    var DOM_FULL =
        "<'row g-2 align-items-center mb-2'<'col-auto'B><'col-sm-4 ms-auto'f>>" +
        "rt" +
        "<'row g-2 align-items-center mt-2'<'col-sm-4'l><'col-sm-4 text-center'i><'col-sm-4'p>>";

    var _DEFAULTS = {
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
    var _COMPACT_DEFAULTS = $.extend(true, {}, _DEFAULTS, {
        paging    : false,
        searching : false,
        info      : false,
        dom       : 't',
        buttons   : null,
        responsive: false,
    });

    // ── Real implementations ──
    function init(selector, options) {
        return $(selector).DataTable($.extend(true, {}, _DEFAULTS, options || {}));
    }

    function initCompact(selector, options) {
        return $(selector).DataTable($.extend(true, {}, _COMPACT_DEFAULTS, options || {}));
    }

    // Replace stub with real implementations.
    window.KernelWeb.dt = { init: init, initCompact: initCompact };

    // ── Drain queued calls from views that loaded before this file ──
    var q = _queue;
    _queue = null;
    for (var i = 0; i < q.length; i++) { q[i](); }

}(window, jQuery));
