/**
 * access_management.js
 * Project-based role assignment for the IMIS Super Administrator.
 *
 * API contracts:
 *
 * GET  backend/fetch/fetch_projects.php
 *      → { data: [{ id, project_name, code_name, description, is_active }] }
 *
 * GET  backend/fetch/fetch_project_access.php?project_id=X
 *      → { success: true, users: [{ user_id, name, fo_rsu, position, role_id }],
 *                          roles: [{ id, name }] }
 *
 * POST backend/update/update_system_access.php
 *      body (JSON): { project_id, assignments: [{ user_id, role_id }] }
 *                   role_id = 0  → remove access
 *      → { success: true|false, message: string }
 */

'use strict';

(function ($) {

    /* ══════════════════════════════════════════════════════════════════════
       STATE
       ══════════════════════════════════════════════════════════════════════ */
    let currentProjectId    = null;   // active project id
    let originalAssignment  = {};     // { user_id(str) → role_id(str) } snapshot
    let allRoles            = [];     // [{ id, name }] for current project
    let allUsersData        = [];     // raw user array from server


    /* ══════════════════════════════════════════════════════════════════════
       BOOTSTRAP
       ══════════════════════════════════════════════════════════════════════ */
    $(document).ready(function () {
        initProjectsTable();
        bindEvents();
    });


    /* ══════════════════════════════════════════════════════════════════════
       PROJECTS DATATABLE
       ══════════════════════════════════════════════════════════════════════ */
    function initProjectsTable() {
        const table = $('#projectsTable').DataTable({
            processing : true,
            ajax: {
                url     : 'backend/fetch/fetch_projects.php',
                type    : 'GET',
                dataSrc : 'data',
                error   : function () {
                    showToast('Failed to load projects.', 'danger');
                }
            },
            columns: [
                {
                    data      : null,
                    className : 'text-center',
                    orderable : false,
                    render    : (d, t, r, meta) => meta.row + 1
                },
                { data: 'project_name', className: 'fw-semibold' },
                { data: 'description',  className: 'text-muted small' },
                {
                    data      : 'code_name',
                    className : 'text-center',
                    render    : v => `<code class="text-primary">${escHtml(v)}</code>`
                },
                {
                    data      : 'is_active',
                    className : 'text-center',
                    render    : v => v
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>'
                },
                {
                    data      : null,
                    className : 'text-center',
                    orderable : false,
                    render    : (d, t, row) =>
                        `<button class="btn btn-sm btn-outline-primary manage-btn"
                            data-project-id="${row.id}"
                            data-project-name="${escHtml(row.project_name)}"
                            data-project-code="${escHtml(row.code_name)}">
                            <i class="bi bi-gear-fill me-1"></i>Manage
                        </button>`
                }
            ],
            order     : [[1, 'asc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            language  : { processing: '<div class="spinner-border spinner-border-sm text-primary"></div>' },
            drawCallback: function () {
                const info = this.api().page.info();
                const el   = document.getElementById('projectSummary');
                if (el) el.textContent = `${info.recordsTotal} system(s) registered`;
            }
        });
    }


    /* ══════════════════════════════════════════════════════════════════════
       EVENT BINDINGS
       ══════════════════════════════════════════════════════════════════════ */
    function bindEvents() {

        /* Open modal */
        $(document).on('click', '.manage-btn', function () {
            openModal(
                parseInt($(this).data('project-id')),
                $(this).data('project-name'),
                $(this).data('project-code')
            );
        });

        /* Retry on error */
        $('#retryAccessBtn').on('click', function () {
            if (currentProjectId) {
                showState('loading');
                fetchProjectAccess(currentProjectId);
            }
        });

        /* Role select change → mark dirty & update stats */
        $(document).on('change', '.role-select', function () {
            const $row = $(this).closest('tr');
            const uid  = $(this).data('user-id').toString();
            const isDirty = $(this).val() !== (originalAssignment[uid] ?? '0');
            $row.toggleClass('row-dirty', isDirty);
            syncStats();
            markDirty();
        });

        /* Save */
        $('#saveAccessBtn').on('click', saveAccessChanges);

        /* Reset on modal close */
        $('#accessModal').on('hidden.bs.modal', resetModal);

        /* ── In-modal search ── */
        $('#userSearchInput').on('input', function () {
            applyFilters();
        });
        $('#clearSearchBtn').on('click', function () {
            $('#userSearchInput').val('');
            applyFilters();
        });

        /* Role filter */
        $('#roleFilterSelect').on('change', applyFilters);

        /* Bulk role select enable/disable apply button */
        $('#bulkRoleSelect').on('change', function () {
            $('#bulkApplyBtn').prop('disabled', $(this).val() === '');
        });

        /* Bulk apply */
        $('#bulkApplyBtn').on('click', function () {
            const roleId = $('#bulkRoleSelect').val();
            if (!roleId) return;
            applyRoleToVisible(roleId);
        });

        /* Revoke all visible */
        $('#revokeAllBtn').on('click', function () {
            applyRoleToVisible('0');
        });
    }


    /* ══════════════════════════════════════════════════════════════════════
       MODAL OPEN
       ══════════════════════════════════════════════════════════════════════ */
    function openModal(projectId, projectName, projectCode) {
        currentProjectId   = projectId;
        originalAssignment = {};
        allRoles           = [];
        allUsersData       = [];

        $('#modalProjectName').text(projectName);
        $('#modalProjectCode').text(projectCode);
        $('#saveAccessBtn').prop('disabled', true);
        $('#userSearchInput').val('');
        $('#roleFilterSelect').val('');
        $('#bulkRoleSelect').val('').trigger('change');

        showState('loading');
        $('#accessModal').modal('show');
        fetchProjectAccess(currentProjectId);
    }


    /* ══════════════════════════════════════════════════════════════════════
       FETCH PROJECT ACCESS
       ══════════════════════════════════════════════════════════════════════ */
    function fetchProjectAccess(projectId) {
        $.ajax({
            url      : 'backend/fetch/fetch_project_access.php',
            type     : 'GET',
            data     : { project_id: projectId },
            dataType : 'json',
            success  : function (res) {
                if (!res.success) {
                    showState('error', res.message || 'Failed to load access data.');
                    return;
                }
                allUsersData = res.users  || [];
                allRoles     = res.roles  || [];
                renderAccessTable(allUsersData, allRoles);
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Server error. Please try again.';
                showState('error', msg);
            }
        });
    }


    /* ══════════════════════════════════════════════════════════════════════
       RENDER ACCESS TABLE
       ══════════════════════════════════════════════════════════════════════ */
    function renderAccessTable(users, roles) {
        if (!users.length) {
            showState('empty');
            return;
        }

        /* Populate role dropdowns in toolbar */
        const roleOpts = roles.map(r =>
            `<option value="${r.id}">${escHtml(r.name)}</option>`
        ).join('');

        $('#roleFilterSelect')
            .find('option:not(:first)').remove().end()
            .append(roleOpts);

        $('#bulkRoleSelect')
            .find('option:not(:first)').remove().end()
            .append(roleOpts);

        /* Build role <option> string for each user row */
        const roleOptions = roles.map(r =>
            `<option value="${r.id}">${escHtml(r.name)}</option>`
        ).join('');

        /* Group users by fo_rsu */
        const groups = groupBy(users, u => u.fo_rsu || 'Unassigned');

        let html = '';
        Object.keys(groups).sort().forEach(groupName => {
            html += `<tr class="group-row" data-group="${escHtml(groupName)}">
                        <td colspan="4" class="ps-3">
                            <i class="bi bi-building me-1"></i>${escHtml(groupName)}
                        </td>
                     </tr>`;

            groups[groupName].forEach(user => {
                const val = user.role_id ? String(user.role_id) : '0';
                html += `<tr data-group="${escHtml(groupName)}"
                             data-name="${escHtml(user.name.toLowerCase())}"
                             data-position="${escHtml((user.position || '').toLowerCase())}">
                    <td class="ps-4">${escHtml(user.name)}</td>
                    <td class="text-muted" style="font-size:12px">${escHtml(user.position || '—')}</td>
                    <td class="text-center pe-2">
                        <select class="form-select form-select-sm role-select"
                                data-user-id="${user.user_id}">
                            <option value="0">— No Access —</option>
                            ${roleOptions}
                        </select>
                    </td>
                    <td class="text-center">
                        <span class="access-badge badge ${val === '0' ? 'bg-secondary' : 'bg-success'}">
                            ${val === '0' ? 'No Access' : escHtml(roleName(val, roles))}
                        </span>
                    </td>
                </tr>`;
            });
        });

        $('#accessTableBody').html(html);

        /* Apply saved selections & build snapshot */
        users.forEach(user => {
            const uid = user.user_id.toString();
            const val = user.role_id ? String(user.role_id) : '0';
            $(`.role-select[data-user-id="${user.user_id}"]`).val(val);
            originalAssignment[uid] = val;
        });

        showState('table');
        syncStats();
        $('#saveAccessBtn').prop('disabled', true);
        $('#noSearchResults').addClass('d-none');
    }


    /* ══════════════════════════════════════════════════════════════════════
       IN-MODAL SEARCH & FILTER
       ══════════════════════════════════════════════════════════════════════ */
    function applyFilters() {
        const search     = $('#userSearchInput').val().toLowerCase().trim();
        const roleFilter = $('#roleFilterSelect').val();

        let visibleUserRows = 0;
        let prevGroupShown  = false;
        let $prevGroupRow   = null;

        $('#accessTableBody tr').each(function () {
            const $tr = $(this);

            if ($tr.hasClass('group-row')) {
                /* Defer group visibility; handled when we know if any child is visible */
                if ($prevGroupRow) {
                    $prevGroupRow.toggle(prevGroupShown);
                }
                $prevGroupRow  = $tr;
                prevGroupShown = false;
                return;
            }

            const name     = ($tr.data('name')     || '').toLowerCase();
            const position = ($tr.data('position') || '').toLowerCase();
            const group    = ($tr.data('group')    || '').toLowerCase();
            const curRole  = $tr.find('.role-select').val();

            const matchSearch = !search ||
                name.includes(search) ||
                position.includes(search) ||
                group.includes(search);

            const matchRole = !roleFilter || curRole === roleFilter;

            const visible = matchSearch && matchRole;
            $tr.toggle(visible);
            if (visible) prevGroupShown = true;
        });

        /* Handle last group */
        if ($prevGroupRow) $prevGroupRow.toggle(prevGroupShown);

        /* Visible user-row count */
        visibleUserRows = $('#accessTableBody tr:not(.group-row):visible').length;
        $('#noSearchResults').toggleClass('d-none', visibleUserRows > 0);
    }


    /* ══════════════════════════════════════════════════════════════════════
       BULK ROLE APPLICATION
       ══════════════════════════════════════════════════════════════════════ */
    function applyRoleToVisible(roleId) {
        const label = roleId === '0' ? 'No Access' : roleName(roleId, allRoles);

        Swal.fire({
            icon              : 'question',
            title             : roleId === '0' ? 'Revoke all access?' : `Apply "${label}" to all?`,
            text              : 'This will update every visible user\'s role. You can still cancel before saving.',
            showCancelButton  : true,
            confirmButtonColor: roleId === '0' ? '#dc3545' : '#0d6efd',
            confirmButtonText : roleId === '0' ? 'Revoke All' : 'Apply',
            cancelButtonText  : 'Cancel'
        }).then(result => {
            if (!result.isConfirmed) return;

            $('#accessTableBody tr:not(.group-row):visible .role-select').each(function () {
                $(this).val(roleId).trigger('change');
            });

            markDirty();
            syncStats();
        });
    }


    /* ══════════════════════════════════════════════════════════════════════
       SAVE ACCESS CHANGES
       ══════════════════════════════════════════════════════════════════════ */
    function saveAccessChanges() {
        const assignments = [];
        $('.role-select').each(function () {
            assignments.push({
                user_id : parseInt($(this).data('user-id')),
                role_id : parseInt($(this).val()) || 0
            });
        });

        if (!assignments.length) return;

        /* Count actual changes for confirmation */
        const changedCount = assignments.filter(a => {
            const orig = originalAssignment[a.user_id.toString()] ?? '0';
            return String(a.role_id) !== orig;
        }).length;

        Swal.fire({
            icon              : 'question',
            title             : 'Save Changes?',
            text              : `${changedCount} role assignment(s) will be updated.`,
            showCancelButton  : true,
            confirmButtonColor: '#198754',
            confirmButtonText : 'Yes, Save',
            cancelButtonText  : 'Review First'
        }).then(result => {
            if (!result.isConfirmed) return;
            doSave(assignments);
        });
    }

    function doSave(assignments) {
        const $btn = $('#saveAccessBtn');
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span>Saving…');

        $.ajax({
            url         : 'backend/update/update_system_access.php',
            type        : 'POST',
            contentType : 'application/json',
            data        : JSON.stringify({ project_id: currentProjectId, assignments }),
            dataType    : 'json',
            success: function (res) {
                if (res.success) {
                    /* Update snapshot & clear dirty state */
                    $('.role-select').each(function () {
                        const uid = $(this).data('user-id').toString();
                        originalAssignment[uid] = $(this).val();
                        $(this).closest('tr').removeClass('row-dirty');

                        /* Refresh the status badge */
                        const val   = $(this).val();
                        const label = val === '0' ? 'No Access' : roleName(val, allRoles);
                        const cls   = val === '0' ? 'bg-secondary' : 'bg-success';
                        $(this).closest('tr')
                               .find('.access-badge')
                               .attr('class', `access-badge badge ${cls}`)
                               .text(label);
                    });

                    syncStats();
                    markDirty(); /* re-evaluates; should disable save */

                    Swal.fire({
                        icon              : 'success',
                        title             : 'Saved!',
                        text              : 'System access roles have been updated.',
                        timer             : 2200,
                        showConfirmButton : false
                    });
                } else {
                    Swal.fire({
                        icon  : 'error',
                        title : 'Error',
                        text  : res.message || 'Failed to save. Please try again.'
                    });
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Server error. Please try again.';
                Swal.fire({ icon: 'error', title: 'Server Error', text: msg });
            },
            complete: function () {
                $btn.html('<i class="bi bi-floppy-fill me-1"></i>Save Changes');
                markDirty();
            }
        });
    }


    /* ══════════════════════════════════════════════════════════════════════
       STATS BAR
       ══════════════════════════════════════════════════════════════════════ */
    function syncStats() {
        let total = 0, withAccess = 0, pending = 0;

        $('.role-select').each(function () {
            total++;
            if ($(this).val() !== '0') withAccess++;
            const uid = $(this).data('user-id').toString();
            if ($(this).val() !== (originalAssignment[uid] ?? '0')) pending++;
        });

        $('#statTotal').text(total);
        $('#statWithAccess').text(withAccess);
        $('#statNoAccess').text(total - withAccess);
        $('#statPending').text(pending);
        $('#pendingCount').toggle(pending > 0);
    }


    /* ══════════════════════════════════════════════════════════════════════
       DIRTY DETECTION
       ══════════════════════════════════════════════════════════════════════ */
    function markDirty() {
        let dirty = false;
        $('.role-select').each(function () {
            const uid = $(this).data('user-id').toString();
            if ($(this).val() !== (originalAssignment[uid] ?? '0')) {
                dirty = true;
                return false; // break
            }
        });
        $('#saveAccessBtn').prop('disabled', !dirty);
    }


    /* ══════════════════════════════════════════════════════════════════════
       UI STATES
       ══════════════════════════════════════════════════════════════════════ */
    function showState(state, errorMsg) {
        const isTable = state === 'table';

        $('#accessLoading').toggle(state === 'loading');
        $('#accessEmpty').toggle(state === 'empty');
        $('#accessError').toggle(state === 'error');
        $('#accessTableWrapper').toggle(isTable);

        /* Toolbar and stats bar are only shown in table state */
        $('#accessToolbar, #accessStatsBar').css('display', isTable ? '' : 'none !important');

        if (state === 'error' && errorMsg) {
            $('#accessErrorMsg').text(errorMsg);
        }
    }


    /* ══════════════════════════════════════════════════════════════════════
       MODAL RESET
       ══════════════════════════════════════════════════════════════════════ */
    function resetModal() {
        currentProjectId   = null;
        originalAssignment = {};
        allRoles           = [];
        allUsersData       = [];
        $('#accessTableBody').empty();
        $('#userSearchInput').val('');
        $('#roleFilterSelect').find('option:not(:first)').remove().val('');
        $('#bulkRoleSelect').find('option:not(:first)').remove().val('').trigger('change');
        $('#statTotal, #statWithAccess, #statNoAccess, #statPending').text('0');
        $('#pendingCount').hide();
        $('#noSearchResults').addClass('d-none');
    }


    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /** Group array by key function */
    function groupBy(arr, keyFn) {
        return arr.reduce((acc, item) => {
            const k = keyFn(item);
            (acc[k] = acc[k] || []).push(item);
            return acc;
        }, {});
    }

    /** Look up role name by id string */
    function roleName(idStr, roles) {
        const found = roles.find(r => String(r.id) === String(idStr));
        return found ? found.name : idStr;
    }

    /** Minimal HTML escaping */
    function escHtml(text) {
        if (text == null) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    /** Non-blocking toast (optional helper) */
    function showToast(msg, type = 'primary') {
        const id   = `toast_${Date.now()}`;
        const html = `<div id="${id}" class="toast align-items-center text-bg-${type} border-0 position-fixed bottom-0 end-0 m-3"
                           role="alert" style="z-index:9999">
                        <div class="d-flex">
                            <div class="toast-body">${escHtml(msg)}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                      </div>`;
        $('body').append(html);
        const el = document.getElementById(id);
        bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

}(jQuery));