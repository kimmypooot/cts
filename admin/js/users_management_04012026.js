// imis/admin/js/users_management.js
$(document).ready(function () {
    fetchUsersTable();

    $('#addUserBtn').click(function () {
        $('#addUserModal').modal('show');

    });

    $('#submitAddUserBtn').click(function (e) {
        e.preventDefault();
        insertNewUser();
    });

    $(document).on('click', '#menuBtn', function () {
        $('#menuModal').modal('show');
        var id = $(this).data("id");
        $('#changePwBtn').data("id", id);
        $('#updateRoleBtn').data("id", id);
        $('#viewDetailsBtn').data("id", id);
    });

    $('#viewDetailsBtn').click(function () {
        $('#menuModal').modal('hide');
        $('#viewDetailsModal').modal('show');
        $('#editToggle').prop('checked', false);
        $('#viewDetailsModal #birthday2').hide();
        $('#viewDetailsModal #type').hide();
        $('#viewDetailsModal #birthday').show();
        $('#viewDetailsModal #fo_rsu').show();
        $('.profile-container').data('id', $(this).data("id"));
        $('.to-edit')
            .prop('readonly', true)
            .removeClass('border border-primary');
        fetchUserDetails($(this).data("id"));
    });

    $('#viewDetailsModal').on('hidden.bs.modal', function () {
        $('#menuModal').modal('show');
    });

    $('#editToggle').on('change', function () {
        toggleEdit($(this).is(':checked'));
    });

    $(document).on('click', '#deleteBtn', function () {
        let id = $(this).data('id');
        deleteUser(id);
    });

    $('#updateRoleBtn').click(function() {
        $('#updateRoleModal').modal('show');
        $('#menuModal').modal('hide');
        fetchUserDetails($(this).data('id'));        
        $('#saveRoleChangesBtn').data('id', $(this).data('id'));
    });

    $('#userStatusToggle').on('change', function () {
        if ($(this).is(':checked')) {
            $('#userRoleSelect').prop('disabled', true);
            $('#statusLabel').text('Inactive');
            $('#userRoleSelect').val('none');
        }
        else {
            $('#userRoleSelect').prop('disabled', false);
            $('#statusLabel').text('Active');
            $('#userRoleSelect').val('admin');
        }
    });

    $('#saveRoleChangesBtn').click(function() {
        const id = $(this).data('id');
        const status = $('#userStatusToggle').prop('checked') ? 'Inactive' : 'Active';
        const role = $('#userRoleSelect').val();
        updateUserStatus(id, status, role);
    });

    $('#changePwBtn').click(function () {
        $('#menuModal').modal('hide');
        Swal.fire({
            title: 'Change Password',
            input: 'text',
            inputLabel: 'Enter new password',
            inputPlaceholder: 'New password',
            inputAttributes: {
                maxlength: 100,
                autocapitalize: 'off',
                autocorrect: 'off'
            },
            showCancelButton: true,
            confirmButtonText: 'Update Password',
            cancelButtonText: 'Cancel',
            preConfirm: (newPassword) => {
                if (!newPassword) {
                    Swal.showValidationMessage('Password is required');
                }
                return newPassword;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let newPassword = result.value;
                console.log("New Password:", newPassword); // Replace this with your actual update logic (e.g., Ajax call)
                // Example:
                updatePassword($(this).data('id'), newPassword);
            }
        });
    });

    $('.profile-container').on('click', function () {
        $('#profileInput').click();
    });
    
    $('#profileInput').on('change', function (e) {
        const file = e.target.files[0];
        const userId = $('.profile-container').data('id');
    
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (event) {
                $('#profile').attr('src', event.target.result); // Preview
            };
            reader.readAsDataURL(file);
    
            const formData = new FormData();
            formData.append('profileImage', file);
            formData.append('userId', userId);
    
            $.ajax({
                url: 'backend/update/update_user_photo.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    const res = JSON.parse(response);
                    if (res.success) {
                        Swal.fire('Success', res.message, 'success');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'Something went wrong during upload.', 'error');
                }
            });
        } else {
            Swal.fire('Invalid', 'Please select a valid image file.', 'warning');
        }
    });
    
    
    
});

function fetchUsersTable() {
    $(document).ready(function () {
        $('#usersTable').DataTable({
            "processing": true, // Show loading indicator
            "serverSide": false, // Disable server-side processing
            "ajax": {
                "url": "backend/fetch/fetch_users.php",
                "type": "GET", // Use GET since we're only fetching data
                "dataSrc": "data"
            },
            "columns": [
                {
                    "data": null,
                    "render": function (data, type, row, meta) {
                        return meta.row + 1; // Generates a dynamic row number
                    }
                },
                {
                    "data": null,
                    "render": function (data, type, row) {
                        return `${row.fname} ${row.minitial} ${row.lname}`;
                    }
                },
                {
                    "data": null,
                    "render": function (data, type, row) {
                        return `${row.username}`;
                    }
                },
                {
                    "data": null,
                    "render": function (data, type, row) {
                        return `${row.fo_rsu}`;
                    }
                },
                {
                    "data": null,
                    "render": function (data, type, row) {
                        return `${row.position}`;
                    }
                },
                {
                    "data": null,
                    "render": function (data, type, row) {
                        const badgeClass = row.status === "Active" ? "bg-success" : "bg-danger";
                        return `<span class="badge ${badgeClass}">${row.status}</span>`;
                    }
                },
                {
                    "data": "id",
                    "render": function (data, type, row) {
                        return renderActionButton(data);
                    }
                }
            ]
        });

        function renderActionButton(id) {
            return `
                <div class="d-flex justify-content-center align-items-center">
                    <button class="btn btn-outline-warning ms-2 btn-sm" id="menuBtn" data-id="${id}"><i
                            class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-outline-danger ms-2 btn-sm" id="deleteBtn" data-id="${id}"><i
                            class="bi bi-trash"></i></button>
                </div>`
                ;
        }
    });
}

function fetchUserDetails(userId) {
    $.ajax({
        url: 'backend/fetch/fetch_users_details.php',
        type: 'GET',
        data: { id: userId },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                var data = response.data;
                $('#viewDetailsModal #id').val(data.id);
                $('#viewDetailsModal #fname').val(data.fname);
                $('#viewDetailsModal #mname').val(data.mname);
                $('#viewDetailsModal #lname').val(data.lname);
                $('#viewDetailsModal #username').val(data.username);
                $('#viewDetailsModal #email').val(data.email);
                $('#viewDetailsModal #position').val(data.position);
                $('#viewDetailsModal #fo_rsu').val(data.fo_rsu);
                $('#viewDetailsModal #type').val(data.type);
                $('#viewDetailsModal #birthday').val(formatDate(data.birthday));
                $('#viewDetailsModal #birthday2').val(data.birthday);
                $('#viewDetailsModal #profile').attr('src', 'uploads/' + data.profile || 'https://via.placeholder.com/200');
                $('#updateRoleForm #userRoleSelect').val(data.role);
                if (data.status == "Active") {
                    $('#userStatusToggle').prop('checked', false);
                    $('#statusLabel').text('Active');
                    $('#userRoleSelect').prop('disabled', false);
                }
                else {
                    $('#userStatusToggle').prop('checked', true);
                    $('#statusLabel').text('Inactive');
                    $('#userRoleSelect').prop('disabled', true);
                }

                // Set role/status in modal (example)
                $('#viewDetailsModal #user-role-status').text(`${data.role} - ${data.status}`);
                console.log(data);
            } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: response.error });
            }
        },
        error: function () {
            Swal.fire({ icon: 'error', title: 'Error!', text: 'An unexpected error occurred.' });
        }
    });
}

function toggleEdit(edit) {
    if (edit) {
        $('.to-edit')
            .prop('readonly', false)
            .addClass('border border-primary');
        $('#viewDetailsModal #birthday2').fadeIn();
        $('#viewDetailsModal #birthday').hide();
        $('#viewDetailsModal #type').fadeIn();
        $('#viewDetailsModal #fo_rsu').hide();
    } else {
        $('.to-edit')
            .prop('readonly', true)
            .removeClass('border border-primary');
        $('#viewDetailsModal #birthday').fadeIn();
        $('#viewDetailsModal #birthday2').hide();
        $('#viewDetailsModal #fo_rsu').fadeIn();
        $('#viewDetailsModal #type').hide();
        updateUserDetails();
    }
}

function formatDate(dateStr, options = { year: 'numeric', month: 'long', day: 'numeric' }) {
    if (!dateStr) return '';

    const date = new Date(dateStr);
    if (isNaN(date)) return dateStr; // fallback if invalid date

    return date.toLocaleDateString('en-US', options);
}

function insertNewUser() {
    var fname = $('#addUserForm #fname').val().trim();
    var lname = $('#addUserForm #lname').val().trim();
    var mname = $('#addUserForm #mname').val().trim();
    var email = $('#addUserForm #email').val().trim();
    var division = $('#addUserForm #type option:selected').text().trim();
    var type = $('#addUserForm #type').val();
    var position = $('#addUserForm #position').val().trim();
    var role = $('#addUserForm #role').val().trim();
    var birthdate = $('#addUserForm #birthdate').val();
    var profilePic = $('#addUserForm #profilePic')[0].files[0]; // get file

    // Check if any field is missing
    if (!fname || !lname || !mname || !email || !type || !position || !role || !birthdate || !profilePic) {
        Swal.fire({
            icon: 'error',
            title: 'Missing Field',
            text: 'Please fill in all required fields.',
        });
        return;
    }

    // Create FormData manually
    let formData = new FormData();
    formData.append('fname', fname);
    formData.append('lname', lname);
    formData.append('mname', mname);
    formData.append('email', email);
    formData.append('division', division); // dropdown text
    formData.append('type', type);         // dropdown value
    formData.append('position', position);
    formData.append('role', role);
    formData.append('birthdate', birthdate);
    formData.append('profilePic', profilePic);

    $.ajax({
        url: 'backend/insert/imis_insert_user.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            try {
                let res = JSON.parse(response);
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'User Added!',
                        text: res.message || 'New user has been added successfully.',
                    }).then(() => {
                        $('#addUserModal').modal('hide');
                        $('#addUserForm')[0].reset();
                        $('#usersTable').DataTable().ajax.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: res.message || 'An error occurred while adding the user.',
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Response',
                    text: 'The server did not return a valid JSON response.',
                });
                console.log(response);
            }
        },
        error: function () {
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                text: 'Failed to send request to the server.',
            });
        }
    });
}

function updateUserDetails() {
    // Get values from modal inputs
    const id = $('#viewDetailsModal #id').val();
    const fname = $('#viewDetailsModal #fname').val();
    const mname = $('#viewDetailsModal #mname').val();
    const lname = $('#viewDetailsModal #lname').val();
    const username = $('#viewDetailsModal #username').val();
    const email = $('#viewDetailsModal #email').val();
    const position = $('#viewDetailsModal #position').val();
    const fo_rsu = $('#viewDetailsModal #type option:selected').text();
    const type = $('#viewDetailsModal #type').val();
    const birthday = $('#viewDetailsModal #birthday2').val(); // assuming this is a separate input

    // Send via AJAX POST
    $.ajax({
        url: 'backend/update/update_users.php', // 🔁 update this URL to your actual endpoint
        method: 'POST',
        data: {
            id,
            fname,
            mname,
            lname,
            username,
            email,
            position,
            fo_rsu,
            type,
            birthday
        },
        success: function (response) {
            console.log('User updated:', response);

            // Hide modal
            fetchUserDetails(id);

            // Show success alert
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: response.message || 'User information has been successfully updated.',
                timer: 2000,
                showConfirmButton: false
            });

            // Refresh DataTable
            $('#usersTable').DataTable().ajax.reload(null, false);
        },
        error: function (xhr, status, error) {
            console.error('Update failed:', error);

            // Show error alert
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: 'Something went wrong while updating user.',
                confirmButtonColor: '#d33'
            });
        }
    });
}

function deleteUser(id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This action cannot be undone!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {
            // Send AJAX request to delete record
            $.ajax({
                url: "backend/delete/delete_user.php", // Your backend script
                type: "POST",
                data: { id: id },
                dataType: "json",
                success: function (response) {
                    if (response.success) {
                        Swal.fire("Deleted!", "The record has been deleted.", "success");
                        $('#usersTable').DataTable().ajax.reload(); // Refresh table
                    } else {
                        Swal.fire("Error!", "Failed to delete record.", "error");
                    }
                },
                error: function () {
                    Swal.fire("Error!", "An unexpected error occurred.", "error");
                }
            });
        }
    });
}

function updateUserStatus(id, status, role) {
    // Send via AJAX POST
    $.ajax({
        url: 'backend/update/update_user_role.php', // 🔁 update this URL to your actual endpoint
        method: 'POST',
        data: {
            id,
            status,
            role
        },
        success: function (response) {
            console.log('User updated:', response);
            // Show success alert
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: response.message || 'User information has been successfully updated.',
                timer: 2000,
                showConfirmButton: false
            });
            // Refresh DataTable
            $('#usersTable').DataTable().ajax.reload(null, false);
            $('#updateRoleModal').modal('hide');
            $('#menuModal').modal('show');
        },
        error: function (xhr, status, error) {
            console.error('Update failed:', error);
            // Show error alert
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: 'Something went wrong while updating user.',
                confirmButtonColor: '#d33'
            });
        }
    });
}

function updatePassword(id, password) {
    // Send via AJAX POST
    $.ajax({
        url: 'backend/update/update_user_password.php', // 🔁 update this URL to your actual endpoint
        method: 'POST',
        data: {
            id,
            password
        },
        success: function (response) {
            console.log('User updated:', response);
            // Show success alert
            Swal.fire({
                icon: 'success',
                title: 'Updated!',
                text: response.message || 'User information has been successfully updated.',
                timer: 2000,
                showConfirmButton: false
            });
            // Refresh DataTable
            $('#usersTable').DataTable().ajax.reload(null, false);
            $('#menuModal').modal('show');
        },
        error: function (xhr, status, error) {
            console.error('Update failed:', error);
            // Show error alert
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: 'Something went wrong while updating user.',
                confirmButtonColor: '#d33'
            });
        }
    });
}