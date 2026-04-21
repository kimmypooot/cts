/**
 * users_management.js
 * Manages IMIS portal users (superadmin view).
 */

'use strict';

(function ($) {

    // ── State ────────────────────────────────────────────────────────────────
    let currentUserId   = null;
    let usersTable      = null;
    let editModal       = null;

    // Cropper state
    let cropper         = null;
    let currentCropTarget = null;   // 'add' | 'edit'
    let cropTargetUserId  = null;   // userId captured at crop-start (edit only)
    let croppedAddBlob    = null;   // Blob held in memory until the add-form submits

    // Default avatar path — single source of truth
    const DEFAULT_AVATAR = '../assets/img/default-avatar.png';

    // ── Init ─────────────────────────────────────────────────────────────────
    $(document).ready(function () {
        usersTable = initUsersTable();
        editModal  = new bootstrap.Modal(document.getElementById('editModal'), {
            backdrop: 'static',
            keyboard: false
        });
        bindEvents();
        initCropperEvents();
    });

    // ── Users DataTable ───────────────────────────────────────────────────────
    function initUsersTable() {
        return $('#usersTable').DataTable({
            processing: true,
            ajax: {
                url:     'backend/fetch/fetch_users.php',
                type:    'GET',
                dataSrc: 'data'
            },
            columns: [
                {
                    data:      null,
                    className: 'text-center align-middle',
                    orderable: false,
                    render:    (d, t, r, meta) => meta.row + 1
                },
                {
                    data:   null,
                    render: (d, t, r) =>
                        escHtml([r.fname, r.minitial, r.lname].filter(Boolean).join(' '))
                },
                { data: 'username' },
                { data: 'fo_rsu' },
                { data: 'position' },
                {
                    data:      'status',
                    className: 'text-center',
                    render:    v => v === 'Active'
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>'
                },
                {
                    data:      'role',
                    className: 'text-center',
                    render:    v =>
                        `<span class="badge bg-secondary text-capitalize">${escHtml(v)}</span>`
                },
                {
                    data:      'id',
                    className: 'text-center align-middle',
                    orderable: false,
                    render:    id =>
                        `<button class="btn btn-sm btn-outline-primary edit-btn me-1"
                                 data-id="${id}" title="Edit User">
                            <i class="bi bi-pencil-square"></i>
                         </button>
                         <button class="btn btn-sm btn-outline-danger delete-btn"
                                 data-id="${id}" title="Delete User">
                            <i class="bi bi-trash3"></i>
                         </button>`
                }
            ],
            order:      [[1, 'asc']],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100]
        });
    }

    // ── Event Bindings ────────────────────────────────────────────────────────
    function bindEvents() {

        // ── Add user ──────────────────────────────────────────────────────────
        $('#addUserBtn').on('click', function () {
            resetAddModal();
            $('#addUserModal').modal('show');
        });
        $('#submitAddUserBtn').on('click', insertUser);

        // Clean up whenever the Add modal is fully hidden (covers backdrop click,
        // "Cancel", and post-submit close).
        $('#addUserModal').on('hidden.bs.modal', resetAddModal);

        // ── Edit user ──────────────────────────────────────────────────────────
        $('#usersTable').on('click', '.edit-btn', function () {
            currentUserId = parseInt($(this).data('id'), 10);
            openEditModal(currentUserId);
        });

        // ── Delete user ───────────────────────────────────────────────────────
        $('#usersTable').on('click', '.delete-btn', function () {
            deleteUser(parseInt($(this).data('id'), 10));
        });

        // ── Save buttons ──────────────────────────────────────────────────────
        $('#saveProfileBtn').on('click',    saveProfile);
        $('#saveAccountBtn').on('click',    saveAccount);
        $('#changePasswordBtn').on('click', changePassword);

        // ── Status toggle ─────────────────────────────────────────────────────
        $('#edit-statusToggle').on('change', function () {
            const inactive = $(this).is(':checked');
            $('#edit-statusLabel').text(inactive ? 'Inactive' : 'Active');
            if (inactive) $('#edit-role').val('none');
            $('#edit-role').prop('disabled', inactive);
        });

        // ── Password visibility toggle ────────────────────────────────────────
        $(document).on('click', '.pw-toggle', function () {
            const $input = $(`#${$(this).data('target')}`);
            const show   = $input.attr('type') === 'password';
            $input.attr('type', show ? 'text' : 'password');
            $(this).find('i').toggleClass('bi-eye bi-eye-slash');
        });

        // ── Password match feedback ───────────────────────────────────────────
        $('#edit-newPassword, #edit-confirmPassword').on('input', validatePasswordMatch);

        // ── Swap footer button when tab changes ───────────────────────────────
        // Use 'show.bs.tab' (fires before animation) for snappier UX.
        $('#editTabs').on('show.bs.tab', 'button[data-bs-toggle="tab"]', function () {
            const tab = $(this).data('bs-target').replace('#', '');
            $('.tab-action').addClass('d-none');
            $(`.tab-action[data-tab="${tab}"]`).removeClass('d-none');
        });

        // ── Clean up edit modal on close ──────────────────────────────────────
        $('#editModal').on('hidden.bs.modal', function () {
            $('#edit-profilePicInput').val('');
            $('#edit-newPassword, #edit-confirmPassword').val('');
            $('#pwMatchFeedback').text('').removeClass('text-success text-danger');
            // Ensure first-tab footer button is visible on next open
            $('.tab-action').addClass('d-none');
            $('#saveProfileBtn').removeClass('d-none');
        });
    }

    // ── Cropper Integration ───────────────────────────────────────────────────
    function initCropperEvents() {

        // ── Add modal: clickable avatar triggers hidden file input ────────────
        $('#addProfileUploadTrigger').on('click', () => $('#add-profilePic').trigger('click'));

        // ── Edit modal: clickable avatar triggers hidden file input ───────────
        $('#profileUploadTrigger').on('click', () => $('#edit-profilePicInput').trigger('click'));

        // ── File-selected handlers ────────────────────────────────────────────
        $('#add-profilePic').on('change', function (e) {
            handleFileSelectForCrop(e, 'add');
        });

        $('#edit-profilePicInput').on('change', function (e) {
            // Capture the current userId NOW so that a later close/reopen can't
            // cause a stale value by the time the user clicks "Crop & Save".
            cropTargetUserId = currentUserId;
            handleFileSelectForCrop(e, 'edit');
        });

        // ── Initialise Cropper when crop modal finishes opening ───────────────
        $('#cropModal').on('shown.bs.modal', function () {
            const image = document.getElementById('imageToCrop');
            cropper = new Cropper(image, {
                aspectRatio:  1,
                viewMode:     1,
                autoCropArea: 0.9,
                guides:       true,
                center:       true,
                highlight:    false,
                background:   false
            });
        });

        // ── Destroy Cropper when crop modal closes ────────────────────────────
        $('#cropModal').on('hidden.bs.modal', function () {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            // Clear the image src so the previous photo doesn't flash on next open
            $('#imageToCrop').attr('src', '');
        });

        // ── "Crop & Save" button ──────────────────────────────────────────────
        $('#btnCropAndSave').on('click', function () {
            if (!cropper) return;

            const $btn = $(this).prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-1"></span> Processing…');

            cropper.getCroppedCanvas({ width: 500, height: 500 }).toBlob(function (blob) {

                if (!blob) {
                    Swal.fire({ icon: 'error', title: 'Crop Failed', text: 'Could not process the image.' });
                    $btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Crop & Save');
                    return;
                }

                if (currentCropTarget === 'edit') {
                    // Upload immediately; use the userId captured when file was selected.
                    previewAndUploadPhoto(blob, cropTargetUserId);

                } else if (currentCropTarget === 'add') {
                    // Keep the blob in memory; show a preview inside the Add modal.
                    croppedAddBlob = blob;
                    const objectUrl = URL.createObjectURL(blob);
                    $('#add-profileImgPreview').attr('src', objectUrl);
                    // Show a small badge so the user knows a photo is queued
                    $('#add-photoBadge').removeClass('d-none');
                }

                $('#cropModal').modal('hide');
                $btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Crop & Save');

            }, 'image/jpeg', 0.90);
        });
    }

    /**
     * Reads the selected file and loads it into the crop modal.
     *
     * @param {Event}  e      Change event from the file input.
     * @param {string} target 'add' | 'edit'
     */
    function handleFileSelectForCrop(e, target) {
        const file = e.target.files[0];

        // Reset the input immediately so the same file can be re-selected after a cancel.
        e.target.value = '';

        if (!file) return;

        if (!file.type.startsWith('image/')) {
            return Swal.fire({ icon: 'warning', title: 'Invalid File', text: 'Please select an image file (JPG, PNG, WEBP, GIF).' });
        }
        if (file.size > 5 * 1024 * 1024) {
            return Swal.fire({ icon: 'warning', title: 'File Too Large', text: 'Please select an image under 5 MB.' });
        }

        currentCropTarget = target;

        const reader = new FileReader();
        reader.onload = function (event) {
            $('#imageToCrop').attr('src', event.target.result);
            $('#cropModal').modal('show');
        };
        reader.readAsDataURL(file);
    }

    // ── Helpers: Add Modal reset ──────────────────────────────────────────────

    /**
     * Resets the entire Add User modal to a clean state.
     * Called on modal open (via the Add User button) and on modal close.
     */
    function resetAddModal() {
        const form = document.getElementById('addUserForm');
        if (form) form.reset();

        // Revoke any existing object URL to avoid memory leaks
        const currentSrc = $('#add-profileImgPreview').attr('src');
        if (currentSrc && currentSrc.startsWith('blob:')) {
            URL.revokeObjectURL(currentSrc);
        }

        $('#add-profileImgPreview').attr('src', DEFAULT_AVATAR);
        $('#add-photoBadge').addClass('d-none');
        $('#add-profilePic').val('');
        croppedAddBlob = null;
    }

    // ── Add User ──────────────────────────────────────────────────────────────
    function insertUser() {
        const form = document.getElementById('addUserForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const $btn = $('#submitAddUserBtn').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Adding…');

        // Build FormData from the form (picks up all named inputs).
        const fd = new FormData(form);

        // If the user cropped an image, replace the empty file-input entry with the
        // Blob so PHP receives it exactly like a normal <input type="file"> upload.
        if (croppedAddBlob) {
            fd.set('profilePic', croppedAddBlob, 'profile.jpg');
        } else {
            // No image chosen — remove the key entirely so the backend treats it as absent.
            fd.delete('profilePic');
        }

        $.ajax({
            url:         'backend/insert/insert_user.php',
            type:        'POST',
            data:        fd,
            processData: false,
            contentType: false,
            dataType:    'json',
            success(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success', title: 'User Added!',
                        text: res.message || 'New user has been created.',
                        timer: 2000, showConfirmButton: false
                    }).then(() => {
                        $('#addUserModal').modal('hide');
                        // resetAddModal() fires automatically via hidden.bs.modal
                        usersTable.ajax.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Could not add user.' });
                }
            },
            error() {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Please try again.' });
            },
            complete() {
                $btn.prop('disabled', false).html('<i class="bi bi-person-fill-add me-1"></i> Add User');
            }
        });
    }

    // ── Edit Modal: load data ─────────────────────────────────────────────────
    function openEditModal(userId) {
        // Show skeleton, hide real content
        $('#editSkeleton').removeClass('d-none');
        $('#editFormContent').addClass('d-none');
        $('#editModalName').text('Loading…');

        // Reset footer to Profile tab button
        $('.tab-action').addClass('d-none');
        $('#saveProfileBtn').removeClass('d-none');

        // Force the Profile tab to be active
        const profileTab = document.querySelector('#editTabs button[data-bs-target="#tabProfile"]');
        if (profileTab) bootstrap.Tab.getOrCreateInstance(profileTab).show();

        editModal.show();

        $.ajax({
            url:      'backend/fetch/fetch_user_details.php',
            type:     'GET',
            data:     { id: userId },
            dataType: 'json',
            success(res) {
                if (!res.success) {
                    editModal.hide();
                    return Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to load user.' });
                }
                populateEditForm(res.data);
            },
            error() {
                editModal.hide();
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Could not load user details.' });
            }
        });
    }

    function populateEditForm(data) {
        $('#editModalName').text(`${data.fname} ${data.lname}`);

        $('#edit-profileImg').attr('src', data.profile ? `uploads/${data.profile}` : DEFAULT_AVATAR);
        $('#edit-id').val(data.id);
        $('#edit-fname').val(data.fname    ?? '');
        $('#edit-mname').val(data.mname    ?? '');
        $('#edit-lname').val(data.lname    ?? '');
        $('#edit-email').val(data.email    ?? '');
        $('#edit-position').val(data.position ?? '');
        $('#edit-birthday').val(data.birthday ?? '');
        $('#edit-sex').val(data.sex        ?? '');
        $('#edit-type').val(data.type      ?? '');

        const inactive = data.status === 'Inactive';
        $('#edit-username').val(data.username ?? '');
        $('#edit-role').val(data.role || 'user').prop('disabled', inactive);
        $('#edit-statusToggle').prop('checked', inactive);
        $('#edit-statusLabel').text(inactive ? 'Inactive' : 'Active');

        $('#edit-newPassword, #edit-confirmPassword').val('');
        $('#pwMatchFeedback').text('');

        $('#editSkeleton').addClass('d-none');
        $('#editFormContent').removeClass('d-none');
    }

    // ── Save Profile ──────────────────────────────────────────────────────────
    function saveProfile() {
        const id = $('#edit-id').val();
        if (!id) return;

        const payload = {
            id,
            fname:    $('#edit-fname').val().trim(),
            lname:    $('#edit-lname').val().trim(),
            mname:    $('#edit-mname').val().trim(),
            email:    $('#edit-email').val().trim(),
            sex:      $('#edit-sex').val(),
            birthday: $('#edit-birthday').val(),
            type:     $('#edit-type').val(),
            position: $('#edit-position').val().trim()
        };

        if (!payload.fname || !payload.lname || !payload.email || !payload.birthday || !payload.type || !payload.position) {
            return Swal.fire({ icon: 'warning', title: 'Incomplete', text: 'Please fill in all required fields.' });
        }

        const $btn = $('#saveProfileBtn').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Saving…');

        $.ajax({
            url:      'backend/update/update_user_profile.php',
            type:     'POST',
            data:     payload,
            dataType: 'json',
            success(res) {
                if (res.success) {
                    $('#editModalName').text(`${payload.fname} ${payload.lname}`);
                    Swal.fire({ icon: 'success', title: 'Saved!', timer: 1500, showConfirmButton: false });
                    usersTable.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Update failed.' });
                }
            },
            error() {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Please try again.' });
            },
            complete() {
                $btn.prop('disabled', false).html('<i class="bi bi-floppy-fill me-1"></i> Save Profile');
            }
        });
    }

    // ── Save Account ──────────────────────────────────────────────────────────
    function saveAccount() {
        const id = $('#edit-id').val();
        if (!id) return;

        const inactive = $('#edit-statusToggle').is(':checked');
        const $btn = $('#saveAccountBtn').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Saving…');

        $.ajax({
            url:      'backend/update/update_user_account.php',
            type:     'POST',
            data:     { id, role: inactive ? 'none' : $('#edit-role').val(), status: inactive ? 'Inactive' : 'Active' },
            dataType: 'json',
            success(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Account Updated!', timer: 1500, showConfirmButton: false });
                    usersTable.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Update failed.' });
                }
            },
            error() {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Please try again.' });
            },
            complete() {
                $btn.prop('disabled', false).html('<i class="bi bi-floppy-fill me-1"></i> Save Account Settings');
            }
        });
    }

    // ── Change Password ───────────────────────────────────────────────────────
    function changePassword() {
        const id  = $('#edit-id').val();
        const pw  = $('#edit-newPassword').val();
        const cfm = $('#edit-confirmPassword').val();

        if (!pw)           return Swal.fire({ icon: 'warning', title: 'Required',   text: 'Please enter a new password.' });
        if (pw.length < 8) return Swal.fire({ icon: 'warning', title: 'Too Short',  text: 'Password must be at least 8 characters.' });
        if (pw !== cfm)    return Swal.fire({ icon: 'warning', title: 'Mismatch',   text: 'Passwords do not match.' });

        const $btn = $('#changePasswordBtn').prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Updating…');

        $.ajax({
            url:      'backend/update/update_user_password.php',
            type:     'POST',
            data:     { id, password: pw },
            dataType: 'json',
            success(res) {
                if (res.success) {
                    $('#edit-newPassword, #edit-confirmPassword').val('');
                    $('#pwMatchFeedback').text('');
                    Swal.fire({ icon: 'success', title: 'Password Updated!', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Update failed.' });
                }
            },
            error() {
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Please try again.' });
            },
            complete() {
                $btn.prop('disabled', false).html('<i class="bi bi-key-fill me-1"></i> Update Password');
            }
        });
    }

    // ── Photo Upload (Edit flow) ──────────────────────────────────────────────
    /**
     * Immediately previews the cropped blob in the edit modal and uploads it to
     * the server. Reverts the UI on failure.
     *
     * @param {Blob}   fileBlob  JPEG blob from Cropper.getCroppedCanvas().toBlob()
     * @param {number} userId    The user whose photo is being updated.
     */
    function previewAndUploadPhoto(fileBlob, userId) {
        // Show preview immediately so the UI feels responsive
        const objectUrl = URL.createObjectURL(fileBlob);
        $('#edit-profileImg').attr('src', objectUrl);

        const fd = new FormData();
        fd.append('id', userId);
        fd.append('profilePic', fileBlob, 'profile.jpg');

        $.ajax({
            url:         'backend/update/update_user_photo.php',
            type:        'POST',
            data:        fd,
            processData: false,
            contentType: false,
            dataType:    'json',
            success(res) {
                URL.revokeObjectURL(objectUrl); // Clean up object URL after browser has painted
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Photo Updated!', timer: 1200, showConfirmButton: false });
                    usersTable.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Upload Failed', text: res.message || 'Could not save photo.' });
                    // Revert to the last server-saved photo
                    openEditModal(userId);
                }
            },
            error() {
                URL.revokeObjectURL(objectUrl);
                Swal.fire({ icon: 'error', title: 'Server Error', text: 'Photo upload failed.' });
                openEditModal(userId);
            }
        });
    }

    // ── Delete User ───────────────────────────────────────────────────────────
    function deleteUser(userId) {
        Swal.fire({
            title:              'Delete this user?',
            text:               'This action is permanent and cannot be undone.',
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  'Yes, delete',
            cancelButtonText:   'Cancel'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.ajax({
                url:      'backend/delete/delete_user.php',
                type:     'POST',
                data:     { id: userId },
                dataType: 'json',
                success(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1500, showConfirmButton: false });
                        usersTable.ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Could not delete user.' });
                    }
                },
                error() {
                    Swal.fire({ icon: 'error', title: 'Server Error', text: 'Please try again.' });
                }
            });
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function validatePasswordMatch() {
        const pw  = $('#edit-newPassword').val();
        const cfm = $('#edit-confirmPassword').val();
        const $fb = $('#pwMatchFeedback');
        if (!cfm) { $fb.text('').removeClass('text-success text-danger'); return; }
        $fb.text(pw === cfm ? 'Passwords match.' : 'Passwords do not match.')
           .toggleClass('text-success', pw === cfm)
           .toggleClass('text-danger',  pw !== cfm);
    }

    function escHtml(str) {
        if (str == null) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(str).replace(/[&<>"']/g, m => map[m]);
    }

}(jQuery));