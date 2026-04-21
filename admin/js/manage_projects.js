/**
 * js/manage_projects.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Handles the full lifecycle of IMIS project management:
 *   • Projects DataTable with edit / toggle-status actions
 *   • Add / edit modal with two tabs (Project Details + Roles & URLs)
 *   • Dynamic role rows (add / remove inline)
 *   • All saves go through backend/save/save_project.php
 *   • Status toggle goes through backend/toggle/toggle_project.php
 *   • CSRF token read from <meta name="csrf-token">
 * ─────────────────────────────────────────────────────────────────────────────
 */

'use strict';

(function ($) {

    // ── CSRF token ────────────────────────────────────────────────────────────
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── State ─────────────────────────────────────────────────────────────────
    let projectsTable = null;

    // ── Init ──────────────────────────────────────────────────────────────────
    $(document).ready(function () {
        initTooltips();
        initProjectsTable();
        bindEvents();
    });

    // ── Bootstrap tooltips ────────────────────────────────────────────────────
    function initTooltips() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el, { trigger: 'hover' });
        });
    }

    // ── Projects DataTable ────────────────────────────────────────────────────
    function initProjectsTable() {
        projectsTable = $('#projectsTable').DataTable({
            processing: true,
            ajax: {
                url:      'backend/fetch/fetch_projects.php',
                type:     'GET',
                dataSrc:  'data',
                error: function () {
                    showToast('error', 'Failed to load projects. Please refresh.');
                }
            },
            columns: [
                {
                    data:      null,
                    className: 'text-center',
                    orderable: false,
                    render:    (d, t, r, meta) => meta.row + 1
                },
                {
                    data:   'project_name',
                    render: (val, t, row) => {
                        const guest = row.guest_url
                            ? `<br><small class="text-muted">Guest → <code>${escHtml(row.guest_url)}</code></small>`
                            : '';
                        return `<span class="fw-semibold">${escHtml(val)}</span>${guest}`;
                    }
                },
                {
                    data:      'description',
                    className: 'text-muted small',
                    render:    v => escHtml(v)
                },
                {
                    data:      'code_name',
                    className: 'text-center',
                    render:    v => `<code>${escHtml(v)}</code>`
                },
                {
                    data:      'requires_setup',
                    className: 'text-center',
                    render:    v => v
                        ? '<span class="badge bg-warning text-dark">Yes</span>'
                        : '<span class="badge bg-light text-secondary border">No</span>'
                },
                {
                    data:      'is_active',
                    className: 'text-center',
                    render:    v => v
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>'
                },
                {
                    data:      null,
                    className: 'text-center',
                    orderable: false,
                    render:    (d, t, row) => `
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                            <button class="btn btn-sm btn-outline-primary edit-btn"
                                    data-id="${row.id}"
                                    title="Edit project">
                                <i class="bi bi-pencil-fill"></i>
                            </button>
                            <button class="btn btn-sm ${row.is_active ? 'btn-outline-warning' : 'btn-outline-success'} toggle-btn"
                                    data-id="${row.id}"
                                    data-active="${row.is_active}"
                                    title="${row.is_active ? 'Deactivate' : 'Activate'}">
                                <i class="bi ${row.is_active ? 'bi-pause-circle' : 'bi-play-circle'}"></i>
                            </button>
                        </div>`
                }
            ],
            order:      [[1, 'asc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100]
        });
    }

    // ── Event bindings ────────────────────────────────────────────────────────
    function bindEvents() {

        // ── Open blank modal for new project ──────────────────────────────────
        $('#addProjectBtn').on('click', function () {
            resetModal();
            $('#modalTitleText').text('Add Project');
            $('#projectModal').modal('show');
        });

        // ── Edit: load existing project data ──────────────────────────────────
        $('#projectsTable').on('click', '.edit-btn', function () {
            const id = $(this).data('id');
    
            // Pass 'this' as the second argument so the modal function can target it
            openEditModal(id, this);
        });

        // ── Toggle active / inactive ──────────────────────────────────────────
        $('#projectsTable').on('click', '.toggle-btn', function () {
            const id       = $(this).data('id');
            const isActive = parseInt($(this).data('active'));
            confirmToggle(id, isActive);
        });

        // ── Add a blank role row ───────────────────────────────────────────────
        $('#addRoleRowBtn').on('click', function () {
            appendRoleRow();
            syncRolesUI();
        });

        // ── Remove a role row ─────────────────────────────────────────────────
        $('#rolesBody').on('click', '.remove-role-btn', function () {
            $(this).closest('tr').remove();
            syncRolesUI();
        });

        // ── Save (create or update) ───────────────────────────────────────────
        $('#saveProjectBtn').on('click', function () {
            saveProject();
        });

        // ── Reset tabs when modal closes ──────────────────────────────────────
        $('#projectModal').on('hidden.bs.modal', function () {
            resetModal();
        });

        // ── Update roles badge count on tab switch ────────────────────────────
        $('#rolesTab').on('shown.bs.tab', syncRolesUI);
    }

    // ── Open edit modal ───────────────────────────────────────────────────────
    function openEditModal(projectId, btnElement) {
        resetModal();
        $('#modalTitleText').text('Edit Project');
        $('#saveProjectBtn').prop('disabled', true);

        // 1. Target the specific button that was clicked
        const $btn = $(btnElement);
        
        // 2. Disable the button and swap the pencil icon for a spinner
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

        $.ajax({
            url:      'backend/fetch/fetch_projects.php',
            type:     'GET',
            data:     { id: projectId },
            dataType: 'json',
            success: function (res) {
                if (!res.success) {
                    showToast('error', res.message || 'Could not load project.');
                    return;
                }

                const p = res.project;
                $('#projectId').val(p.id);

                // Details tab
                $('#fieldCodeName').val(p.code_name).prop('readonly', true);
                $('#fieldProjectName').val(p.project_name);
                $('#fieldDescription').val(p.description);
                $('#fieldGuestUrl').val(p.guest_url ?? '');
                $('#fieldRequiresSetup').prop('checked', !!parseInt(p.requires_setup));
                $('#fieldIsActive').prop('checked', !!parseInt(p.is_active));

                // Roles tab
                (res.roles || []).forEach(role => {
                    appendRoleRow(role.id, role.name, role.project_url, !!parseInt(role.is_external));
                });
                
                syncRolesUI();
                $('#saveProjectBtn').prop('disabled', false);
                $('#projectModal').modal('show');
            },
            error: function () {
                showToast('error', 'Server error loading project data.');
            },
            complete: function () {
                // 3. Always revert the button back to its original state when done
                $btn.prop('disabled', false)
                    .html('<i class="bi bi-pencil-fill"></i>');
            }
        });
    }

    // ── Role row helpers ──────────────────────────────────────────────────────

    /**
     * Appends a single role row to #rolesBody.
     * roleId is only set when editing an existing role row.
     */
    function appendRoleRow(roleId = '', name = '', url = '', isExternal = false) {
        const $row = $(`
            <tr>
                <td>
                    <input type="hidden" class="role-id" value="${escHtml(String(roleId))}">
                    <input type="text" class="form-control form-control-sm role-name"
                           placeholder="e.g. Admin" maxlength="64"
                           value="${escHtml(name)}" autocomplete="off">
                    <div class="invalid-feedback role-name-err"></div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm role-url"
                           placeholder="/system/admin/dashboard or https://…" maxlength="255"
                           value="${escHtml(url)}" autocomplete="off">
                    <div class="invalid-feedback role-url-err"></div>
                </td>
                <td class="text-center">
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input role-external" type="checkbox" ${isExternal ? 'checked' : ''}>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-role-btn"
                            title="Remove this role">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </td>
            </tr>
        `);
        $('#rolesBody').append($row);
    }

    /**
     * Keeps the roles badge count and the empty-state message in sync.
     */
    function syncRolesUI() {
        const count = $('#rolesBody tr').length;
        $('#rolesCount').text(count);
        $('#rolesEmptyMsg').toggle(count === 0);
    }

    // ── Validation ────────────────────────────────────────────────────────────
    function validateForm() {
        let valid = true;

        // Clear previous errors
        clearFieldErrors();

        const codeName    = $('#fieldCodeName').val().trim();
        const projectName = $('#fieldProjectName').val().trim();
        const description = $('#fieldDescription').val().trim();

        if (!codeName) {
            setFieldError('#fieldCodeName', '#codeNameError', 'System code is required.');
            valid = false;
        } else if (!/^[A-Z0-9_-]{1,100}$/.test(codeName)) {
            setFieldError('#fieldCodeName', '#codeNameError', 'Only uppercase letters, numbers, hyphens, and underscores are allowed.');
            valid = false;
        }

        if (!projectName) {
            setFieldError('#fieldProjectName', '#projectNameError', 'Project name is required.');
            valid = false;
        }

        if (!description) {
            setFieldError('#fieldDescription', '#descriptionError', 'Description is required.');
            valid = false;
        }

        // Validate role rows
        let roleValid = true;
        $('#rolesBody tr').each(function () {
            const $row    = $(this);
            const name    = $row.find('.role-name').val().trim();
            const url     = $row.find('.role-url').val().trim();
            let rowOk = true;

            if (!name) {
                $row.find('.role-name').addClass('is-invalid');
                $row.find('.role-name-err').text('Role name is required.');
                rowOk = false;
            }
            if (!url) {
                $row.find('.role-url').addClass('is-invalid');
                $row.find('.role-url-err').text('Redirect URL is required.');
                rowOk = false;
            }

            if (!rowOk) roleValid = false;
        });

        // If role errors exist, switch to that tab
        if (!roleValid) {
            $('#rolesTab').tab('show');
            valid = false;
        }

        return valid;
    }

    function setFieldError(inputSel, errorSel, message) {
        $(inputSel).addClass('is-invalid');
        $(errorSel).text(message);
    }

    function clearFieldErrors() {
        $('#fieldCodeName, #fieldProjectName, #fieldDescription').removeClass('is-invalid');
        $('#codeNameError, #projectNameError, #descriptionError').text('');
        $('#rolesBody .role-name, #rolesBody .role-url').removeClass('is-invalid');
        $('#rolesBody .role-name-err, #rolesBody .role-url-err').text('');
    }

    // ── Save project (create or update) ───────────────────────────────────────
    function saveProject() {
        if (!validateForm()) return;

        const projectId = $('#projectId').val();
        const isEdit    = !!projectId;

        // Gather role rows
        const roles = [];
        $('#rolesBody tr').each(function () {
            const $row = $(this);
            roles.push({
                id:          $row.find('.role-id').val() || null,
                name:        $row.find('.role-name').val().trim(),
                project_url: $row.find('.role-url').val().trim(),
                is_external: $row.find('.role-external').is(':checked') ? 1 : 0,
            });
        });

        const payload = {
            id:             projectId || null,
            code_name:      $('#fieldCodeName').val().trim().toUpperCase(),
            project_name:   $('#fieldProjectName').val().trim(),
            description:    $('#fieldDescription').val().trim(),
            guest_url:      $('#fieldGuestUrl').val().trim() || null,
            requires_setup: $('#fieldRequiresSetup').is(':checked') ? 1 : 0,
            is_active:      $('#fieldIsActive').is(':checked') ? 1 : 0,
            roles,
        };

        const $btn = $('#saveProjectBtn');
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Saving…');

        $.ajax({
            url:         'backend/save/save_project.php',
            type:        'POST',
            contentType: 'application/json',
            data:        JSON.stringify(payload),
            dataType:    'json',
            headers:     { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
            success: function (res) {
                if (res.success) {
                    $('#projectModal').modal('hide');
                    projectsTable.ajax.reload(null, false);
                    Swal.fire({
                        icon:              'success',
                        title:             isEdit ? 'Updated!' : 'Created!',
                        text:              res.message || 'Project saved successfully.',
                        timer:             2000,
                        showConfirmButton: false,
                    });
                } else {
                    // Field-level server errors (e.g. duplicate code_name)
                    if (res.field) {
                        handleServerFieldError(res.field, res.message);
                    } else {
                        showToast('error', res.message || 'Failed to save project.');
                    }
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Server error. Please try again.';
                showToast('error', msg);
            },
            complete: function () {
                $btn.prop('disabled', false)
                    .html('<i class="bi bi-floppy-fill me-1"></i> Save');
            }
        });
    }

    /**
     * Maps a server-reported field name to the correct input and error element.
     */
    function handleServerFieldError(field, message) {
        const map = {
            code_name:    ['#fieldCodeName',    '#codeNameError'],
            project_name: ['#fieldProjectName', '#projectNameError'],
            description:  ['#fieldDescription', '#descriptionError'],
        };
        if (map[field]) {
            setFieldError(...map[field], message);
            // Make sure the details tab is visible
            $('#detailsTab').tab('show');
        } else {
            showToast('error', message);
        }
    }

    // ── Toggle active / inactive ──────────────────────────────────────────────
    function confirmToggle(projectId, isActive) {
        const action  = isActive ? 'deactivate' : 'activate';
        const iconCls = isActive ? 'warning' : 'success';

        Swal.fire({
            title:              `${isActive ? 'Deactivate' : 'Activate'} project?`,
            text:               isActive
                ? 'Deactivating will hide this system from routing. Existing access records are preserved.'
                : 'Activating will make this system accessible again.',
            icon:               iconCls,
            showCancelButton:   true,
            confirmButtonText:  `Yes, ${action}`,
            confirmButtonColor: isActive ? '#f0ad4e' : '#28a745',
            cancelButtonText:   'Cancel',
        }).then(result => {
            if (!result.isConfirmed) return;

            $.ajax({
                url:         'backend/toggle/toggle_project.php',
                type:        'POST',
                contentType: 'application/json',
                data:        JSON.stringify({ id: projectId }),
                dataType:    'json',
                headers:     { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF },
                success: function (res) {
                    if (res.success) {
                        projectsTable.ajax.reload(null, false);
                        showToast('success', res.message || 'Status updated.');
                    } else {
                        showToast('error', res.message || 'Failed to update status.');
                    }
                },
                error: function () {
                    showToast('error', 'Server error. Please try again.');
                }
            });
        });
    }

    // ── Modal reset ───────────────────────────────────────────────────────────
    function resetModal() {
        $('#projectId').val('');
        $('#fieldCodeName').val('').prop('readonly', false);
        $('#fieldProjectName').val('');
        $('#fieldDescription').val('');
        $('#fieldGuestUrl').val('');
        $('#fieldRequiresSetup').prop('checked', false);
        $('#fieldIsActive').prop('checked', true);
        $('#rolesBody').empty();
        clearFieldErrors();
        syncRolesUI();

        // Reset to details tab
        $('#detailsTab').tab('show');
        $('#saveProjectBtn').prop('disabled', false)
            .html('<i class="bi bi-floppy-fill me-1"></i> Save');
    }

    // ── Toast helper ─────────────────────────────────────────────────────────
    function showToast(type, message) {
        // Uses SweetAlert2 toast mode; falls back to console if not available
        if (typeof Swal === 'undefined') {
            console[type === 'error' ? 'error' : 'log'](message);
            return;
        }
        Swal.fire({
            toast:             true,
            position:          'top-end',
            icon:              type,
            title:             message,
            showConfirmButton: false,
            timer:             3500,
            timerProgressBar:  true,
        });
    }

    // ── XSS escape ───────────────────────────────────────────────────────────
    function escHtml(text) {
        if (text == null) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

}(jQuery));