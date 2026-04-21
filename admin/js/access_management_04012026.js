// imis/admin/js/access_management.js
$(document).ready(function () {
    checkUsers();

    $('.manage-btn').click(function () {
        var sysName = $(this).data('sysname');
        var sysAcro = $(this).data('id');
        //console.log(sysAcro.toLowerCase());
        $('#accessModal').modal('show');
        $('#systemName').text(sysName);
        $('#updateBtn').data('id', sysAcro.toLowerCase());
        fetchUsers(sysAcro.toLowerCase());
    });

    $('#updateBtn').on('click', function () {
        updateSystemAccess($(this).data('id'));
    });

});

function fetchUsers(system) {
    // Helper to render radio buttons for roles
    function renderRoleOption(role, row, currentValue) {
        return `<div class="text-center">
                    <input class="form-check-input" type="radio" name="role_${row.id}" value="${role}" ${currentValue === role ? 'checked' : ''}>
                </div>`;
    }

    // Destroy existing table instance
    $('#usersTable').DataTable().destroy();

    // Initialize new DataTable
    $('#usersTable').DataTable({
        responsive: true,
        processing: true,
        serverSide: false,
        autoWidth: false,
        ajax: {
            url: "backend/fetch/fetch_users_management.php",
            type: "GET",
            dataSrc: "data"
        },
        columns: [
            {
                data: null,
                title: "Name",
                className: "w-auto",
                render: function (data, type, row) {
                    return `&nbsp;&nbsp;&nbsp;&nbsp;${row.name} <span hidden>${row.id}</span>`;
                }
            },
            {
                data: "position",
                title: "Position",
                className: "w-auto"
            },
            {
                data: system,
                title: "Superadmin",
                className: "text-center w-10",
                render: function (data, type, row) {
                    return renderRoleOption("Superadmin", row, data);
                }
            },
            {
                data: system,
                title: "Admin",
                className: "text-center w-auto",
                render: function (data, type, row) {
                    return renderRoleOption("Admin", row, data);
                }
            },
            {
                data: system,
                title: "User",
                className: "text-center w-auto",
                render: function (data, type, row) {
                    return renderRoleOption("User", row, data);
                }
            },
            {
                data: system,
                title: "None",
                className: "text-center w-auto",
                render: function (data, type, row) {
                    return renderRoleOption("None", row, data);
                }
            },
            {
                data: "fo_rsu",
                className: "w-auto",
                visible: false // used for grouping only
            }
        ],
        paging: false,
        lengthChange: false,
        searching: false,
        info: false,
        columnDefs: [{ visible: false, targets: 6 }],
        order: [[6, 'asc']],
        drawCallback: function (settings) {
            var api = this.api();
            var rows = api.rows({ page: 'current' }).nodes();
            var last = null;
            var colspan = api.columns().nodes().length;

            api.column(6, { page: 'current' }).data().each(function (group, i) {
                if (last !== group) {
                    $(rows).eq(i).before(
                        `<tr class="group bg-light fw-bold">
                            <td colspan="${colspan}">${group}</td>
                        </tr>`
                    );
                    last = group;
                }
            });
        }
    });
}

function checkUsers() {
    Luma.FetchData({
        query: `SELECT * FROM users_cscro8`,
        params: {}
    }).then(data => {
        data.forEach(element => {
            Luma.FetchData({
                query: `SELECT id FROM system_access WHERE user = :id`,
                params: {
                    id: element.id
                }
            }).then(dataT => {
                if (dataT.length === 0) {
                    Luma.Insert([{
                        query: `INSERT INTO system_access (
                            user,
                            otrs,
                            eris,
                            ors,
                            cdl,
                            iis,
                            rfcs,
                            dvs,
                            cts,
                            psed,
                            lms
                        )
                        VALUES (
                            :id,
                            'None',
                            'None',
                            'None',
                            'None',
                            'None',
                            'None',
                            'None',
                            'None',
                            'None',
                            'None'
                        );`,                        
                        params: {
                            id: element.id
                        }
                    }]);
                }
            });
        });
    })
}

function updateSystemAccess(currentSystem) {
    let updates = [];

    $('#usersTable tbody tr').each(function () {
        const row = $(this);
        const employeeId = row.find('span').text().trim(); // gets hidden ID
        const selectedRole = row.find('input[type="radio"]:checked').val();

        if (employeeId && selectedRole) {
            updates.push({ employee: employeeId, role: selectedRole });
        }
    });

    console.log(updates);

    if (updates.length > 0) {
        $.ajax({
            url: 'backend/update/update_users_access.php',
            type: 'POST',
            data: { updates: JSON.stringify(updates), system: currentSystem },
            beforeSend: function () {
                Swal.fire({
                    title: 'Updating...',
                    text: 'Please wait while roles are being updated.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function (response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Roles Updated',
                    text: 'User roles were successfully updated!',
                    timer: 2000,
                    showConfirmButton: false
                });
                fetchUsers(currentSystem);
            },
            error: function (xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Update Failed',
                    text: 'There was an error updating user roles. Please try again.'
                });
                console.error('Error updating roles:', error);
            }
        });
    }
}
