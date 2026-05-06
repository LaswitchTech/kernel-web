/**
 * KernelWeb — shared DataTables initializer
 */
(function (window, $) {
    'use strict';

    window.KernelWeb = window.KernelWeb || {};
    if (!window.KernelWeb._dtInitQueue) {
        window.KernelWeb._dtInitQueue = null;
    }

    var DEFAULTS = {
        pageLength : 25,
        lengthMenu : [10, 25, 50, 100],
        responsive : true,
        layout     : {
            topStart:   null,
            topEnd:     'search',
            bottomStart: 'info',
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

    var COMPACT_DEFAULTS = $.extend(true, {}, DEFAULTS, {
        paging    : false,
        searching : false,
        info      : false,
        layout    : { topStart: null, topEnd: null, bottomStart: null, bottom: null, bottomEnd: null },
        buttons   : null,
        responsive: false,
    });

    function init(selector, options) {
        var tableEl = $(selector).first();
        if (tableEl.length === 0) {
            $(function() { init(selector, options); });
            return null;
        }

        var customButtons = Array.isArray(options && options.buttons) ? options.buttons : [];
        var opts = $.extend({}, options || {});
        delete opts.buttons;
        var config = $.extend(true, {}, DEFAULTS, opts);

        var dt = new DataTable(tableEl[0], config);

        // Manually create buttons if provided (DT2 layout doesn't render "buttons" feature type)
        if (customButtons.length > 0) {
            try {
                // Try the buttons API first
                var api = new $.fn.dataTable.Api(tableEl[0]);
                var btns = new $.fn.dataTable.Buttons(api, customButtons);
                var container = btns.container();

                // Find the top-left slot (first .dt-layout-start in the first .dt-row)
                var containerEl = tableEl.closest('.dt-container');
                var topStart = containerEl.find('.dt-row').first().find('.dt-layout-start').first();

                if (topStart.length === 0) {
                    // Fallback: create the slot ourselves
                    var row = $('<div>').addClass('row mt-2 justify-content-between');
                    topStart = $('<div>').addClass('d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto');
                    row.append(topStart);
                    containerEl.prepend(row);
                }

                container.appendTo(topStart);
            } catch(e) {
                // Buttons API failed — create buttons manually
                var btnGroup = $('<div>').addClass('dt-buttons btn-group flex-wrap');
                customButtons.forEach(function(btnConf) {
                    var text = typeof btnConf.text === 'function' ? btnConf.text() : btnConf.text;
                    var btnEl = $('<button>')
                        .addClass(btnConf.className || 'btn btn-secondary')
                        .attr('type', 'button')
                        .attr('title', btnConf.titleAttr || '')
                        .html(text);
                    if (btnConf.action) {
                        btnEl.on('click', function(e) { btnConf.action(e, null, null, btnConf); });
                    }
                    btnGroup.append(btnEl);
                });
                var container = tableEl.closest('.dt-container');
                var topRow = container.find('.dt-row').first();
                var slot = topRow.find('.dt-layout-start').first();
                if (slot.length === 0) {
                    topRow.prepend(btnGroup);
                } else {
                    btnGroup.appendTo(slot);
                }
            }
        }

        return dt;
    }

    function initCompact(selector, options) {
        var tableEl = $(selector).first();
        if (tableEl.length === 0) return null;
        return new DataTable(tableEl[0], $.extend(true, {}, COMPACT_DEFAULTS, options || {}));
    }

    window.KernelWeb.dt = { init: init, initCompact: initCompact };

    var q = window.KernelWeb._dtInitQueue;
    window.KernelWeb._dtInitQueue = null;
    if (q) {
        for (var i = 0; i < q.length; i++) { q[i](); }
    }

}(window, jQuery));
