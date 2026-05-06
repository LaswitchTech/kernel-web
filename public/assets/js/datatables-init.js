/**
 * KernelWeb — shared DataTables initializer
 */
(function (window, $) {
    'use strict';

    window.KernelWeb = window.KernelWeb || {};
    if (!window.KernelWeb._dtInitQueue) {
        window.KernelWeb._dtInitQueue = null;
    }

    // ── Default Buttons toolbar ───────────────────────────────
    var DEFAULT_BUTTONS = [
        {
            text: '<i class="bi bi-printer" aria-hidden="true"></i><span class="visually-hidden">Print</span>',
            titleAttr: 'Print',
            extend: 'print'
        },
        {
            extend: 'collection',
            text: '<i class="bi-arrow-bar-down"></i><span class="visually-hidden">Export</span>',
            buttons: [
                {
                    extend: 'copy',
                    text: '<i class="bi-clipboard me-2"></i>Clipboard',
                    exportOptions: { columns: ':visible:not(:last-child)' },
                },
                {
                    extend: 'excel',
                    text: '<i class="bi-filetype-xlsx me-2"></i>Excel',
                    exportOptions: { columns: ':visible:not(:last-child)' },
                },
                {
                    extend: 'csv',
                    text: '<i class="bi-filetype-csv me-2"></i>CSV',
                    exportOptions: { columns: ':visible:not(:last-child)' },
                },
                {
                    extend: 'pdf',
                    text: '<i class="bi-filetype-pdf me-2"></i>PDF',
                    exportOptions: { columns: ':visible:not(:last-child)' },
                },
            ],
        },
        {
            extend: 'collection',
            text: '<i class="bi-check2-square"></i><span class="visually-hidden">Select</span>',
            buttons: [
                {
                    extend: 'selectAll',
                    text: '<i class="bi-check2-all me-2"></i>All',
                },
                {
                    extend: 'selectNone',
                    text: '<i class="bi-x-square me-2"></i>None',
                },
                {
                    name: 'selectFiltered',
                    text: '<i class="bi-eye me-2"></i>Filtered',
                    action: function (e, dt, node, config) {
                        dt.rows({ selected: true }).deselect();
                        dt.rows({ search: 'applied', page: 'all' }).select();
                    },
                },
                {
                    name: 'selectUnfiltered',
                    text: '<i class="bi-eye-slash me-2"></i>Unfiltered',
                    action: function (e, dt, node, config) {
                        dt.rows({ selected: true }).deselect();
                        dt.rows({ search: 'removed', page: 'all' }).select();
                    },
                },
            ]
        },
        {
            text: '<i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i><span class="visually-hidden">Column Visibility</span>',
            titleAttr: 'Column Visibility',
            extend: 'colvis'
        },
        {
            text: '<i class="bi bi-list" aria-hidden="true"></i><span class="visually-hidden">Number of rows</span>',
            titleAttr: 'Number of rows',
            extend: 'pageLength'
        },
        // {
        //     text: '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span class="visually-hidden">Load State</span>',
        //     className: 'btn-primary',
        //     init: function (dt, node){
        //         $(node).removeClass('btn-secondary');
        //     },
        //     titleAttr: 'Load State',
        //     extend: 'savedStates',
        //     config: {
        //         splitSecondaries: [
        //             'updateState',
        //             'renameState',
        //             'removeState',
        //         ]
        //     }
        // },
        // {
        //     text: '<i class="bi bi-save" aria-hidden="true"></i><span class="visually-hidden">Save State</span>',
        //     className: 'btn-success',
        //     init: function (dt, node){
        //         $(node).removeClass('btn-secondary');
        //     },
        //     titleAttr: 'Save State',
        //     extend: 'createState',
        //     config: {
        //         creationModal: true
        //     }
        // }
    ];

    var DEFAULTS = {
        pageLength : 25,
        lengthMenu : [10, 25, 50, 100],
        responsive : true,
        layout     : {
            topStart:   'buttons',
            topEnd:     'search',
            bottomStart: 'info',
            bottomEnd:  'paging'
        },
        columnControl: [
            {
                target: 0,
                content: [
                    'order',
                    [
                        'orderAsc',
                        'orderDesc',
                        'spacer',
                        'orderAddAsc',
                        'orderAddDesc',
                        'spacer',
                        'orderRemove'
                    ]
                ]
            },
            {
                target: 1,
                content: ['search']
            }
        ],
        ordering: {
            handler: false,
            indicators: false
        },
        // scrollCollapse: true,
        // scroller: true,
        // scrollY: 500,
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

        // Merge custom buttons before defaults
        var customButtons = Array.isArray(options && options.buttons) ? options.buttons : [];
        var finalButtons = customButtons.concat(DEFAULT_BUTTONS);

        var opts = $.extend({}, options || {});
        delete opts.buttons;
        var config = $.extend(true, {}, DEFAULTS, opts);

        // Set merged buttons into config (only if buttons exist)
        if (finalButtons.length > 0) {
            config.buttons = finalButtons;
        }

        var dt = new DataTable(tableEl[0], config);

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
