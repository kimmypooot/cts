$(document).ready(function () {
    // SweetAlert2 Toast Configuration
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // Loading modal references
    let addLoadingModal = null;
    let editLoadingModal = null;

    // Initialize DataTable
    const table = $('#publicationsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'get_publications.php',
            type: 'GET',
            dataSrc: ''
        },
        columns: [
            {
                data: null,
                render: function (data, type, row, meta) {
                    return meta.row + 1;
                }
            },
            { data: 'agency_name' },
            {
                data: 'posting_date',
                className: 'text-center',
                render: function (data) {
                    const dateObj = new Date(data);
                    return !isNaN(dateObj)
                        ? dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
                        : data;
                }
            },
            {
                data: 'closing_date',
                className: 'text-center',
                render: function (data) {
                    const dateObj = new Date(data);
                    return !isNaN(dateObj)
                        ? dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
                        : data;
                }
            },
            {
                data: 'publication_path',
                className: 'text-center',
                render: function (data) {
                    return data
                        ? `<a href="${data}" target="_blank" class="btn btn-sm btn-danger" title="View PDF">
                            <i class="bi bi-file-earmark-pdf"></i> View
                        </a>`
                        : `<span class="text-muted">No file</span>`;
                }
            },
            {
                data: 'action_officer'
            },
            {
                data: null,
                className: 'text-center',
                render: function (data, type, row) {
                    return `
                        <button type="button" class="btn btn-sm btn-primary edit-btn" data-id="${row.id}">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="${row.id}">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    `;
                }
            }
        ],
        responsive: true,
        order: [[0, 'asc']]
    });

    function initLazyLoadAgenciesForAdd() {
        const $select = $('#addAgencySelect');

        if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2('destroy');
        }

        $select.select2({
            dropdownParent: $select.closest('.modal'),
            placeholder: "Select agency...",
            allowClear: true,
            width: '100%',
            ajax: {
                url: 'get_agencies.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term || '' // user input
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.map(agency => ({
                            id: agency.id,
                            text: agency.agency_name
                        }))
                    };
                },
                cache: true
            }
        });
    }

    function loadAgenciesForEdit(selectedId) {
        const $select = $('#editAgencySelect');

        if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2('destroy');
        }

        return fetch('get_agencies.php')
            .then(res => res.json())
            .then(agencies => {
                $select.empty().append('<option value="" disabled>Select agency...</option>');
                agencies.forEach(agency => {
                    const isSelected = agency.id == selectedId;
                    $select.append(new Option(agency.agency_name, agency.id, isSelected, isSelected));
                });

                $select.select2({
                    dropdownParent: $select.closest('.modal'),
                    placeholder: "Select agency...",
                    allowClear: true,
                    width: '100%'
                });
            });
    }

    $('#addPublicationModal').on('shown.bs.modal', function () {
        initLazyLoadAgenciesForAdd();
    });

    // On show of Edit Modal (with pre-selection)
    $('#editPublicationModal').on('shown.bs.modal', function () {
        const currentAgencyId = $('#editSelectedAgencyId').val(); // assume this hidden input is filled
        loadAgenciesForSelect('editAgencySelect', currentAgencyId);
    });

    // File validation functions
    function validateAddFile(fileInput) {
        const file = fileInput.files[0];
        if (file) {
            if (file.type !== 'application/pdf') {
                Toast.fire({
                    icon: 'warning',
                    title: 'Invalid file type - PDF only'
                });
                fileInput.value = '';
                return false;
            }

            if (file.size > 5 * 1024 * 1024) {
                Toast.fire({
                    icon: 'warning',
                    title: 'File too large - Max 5MB'
                });
                fileInput.value = '';
                return false;
            }
        }
        return true;
    }

    function validateEditFile(fileInput) {
        const file = fileInput.files[0];
        if (file) {
            if (file.type !== 'application/pdf') {
                Toast.fire({
                    icon: 'warning',
                    title: 'Invalid file type - PDF only'
                });
                fileInput.value = '';
                return false;
            }

            if (file.size > 5 * 1024 * 1024) {
                Toast.fire({
                    icon: 'warning',
                    title: 'File too large - Max 5MB'
                });
                fileInput.value = '';
                return false;
            }
        }
        return true;
    }

    // File input change event handlers
    $('#addPublicationFile').on('change', function() {
        validateAddFile(this);
    });

    $('#editPublicationFile').on('change', function() {
        validateEditFile(this);
    });

    // Form validation functions
    function validateAddForm() {
        const agencyId = $('#addAgencySelect').val();

        if (!agencyId) {
            Toast.fire({
                icon: 'warning',
                title: 'Please select an agency'
            });
            return false;
        }

        if (!$('#addPublicationFile')[0].files.length) {
            Toast.fire({
                icon: 'warning',
                title: 'PDF file is required'
            });
            return false;
        }

        return true;
    }

    function validateEditForm() {
        const agencyId = $('#editAgencySelect').val();

        if (!agencyId) {
            Toast.fire({
                icon: 'warning',
                title: 'Please select an agency'
            });
            return false;
        }

        return true;
    }

    // ADD PUBLICATION FORM SUBMISSION
    $('#addPublicationForm').on('submit', function (e) {
        e.preventDefault();

        if (!validateAddForm()) return;

        const agencyName = $('#addAgencySelect option:selected').text();
        const postingDateRaw = $('#addPostingDate').val();
        const closingDateRaw = $('#addClosingDate').val();

        // Format dates to "Month Day, Year"
        const formatDate = (dateStr) => {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        const postingDate = formatDate(postingDateRaw);
        const closingDate = formatDate(closingDateRaw);

        Swal.fire({
            title: 'Confirm Submission',
            html: `
                <div class="text-center">
                    <p><strong>Agency:</strong> ${agencyName}</p>
                    <p><strong>Posting Date:</strong> ${postingDate}</p>
                    <p><strong>Closing Date:</strong> ${closingDate}</p>
                    <p class="text-danger fw-semibold mb-0">Do you want to proceed?</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: `<i class="bi bi-check-circle me-1"></i> Yes, submit it`,
            cancelButtonText: `<i class="bi bi-x-circle me-1"></i> Cancel`,
            customClass: {
                confirmButton: 'btn btn-primary',
                cancelButton: 'btn btn-secondary ms-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData($('#addPublicationForm')[0]);

                const addLoadingModal = Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait while we upload your publication.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: 'add_publication.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (response) {
                        addLoadingModal.close();

                        if (response.success) {
                            Toast.fire({
                                icon: 'success',
                                title: response.message || 'Publication added successfully!'
                            });
                            $('#addPublicationModal').modal('hide');
                            table.ajax.reload();
                            resetAddForm();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message || 'Failed to add publication'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        addLoadingModal.close();
                        Toast.fire({
                            icon: 'error',
                            title: 'An error occurred while processing your request'
                        });
                        console.error('AJAX Error:', error);
                    }
                });
            }
        });
    });

    // EDIT PUBLICATION FORM SUBMISSION
    $('#editPublicationForm').on('submit', function (e) {
        e.preventDefault();

        if (!validateEditForm()) {
            return;
        }

        const formData = new FormData(this);

        // Show loading modal for edit
        editLoadingModal = Swal.fire({
            title: 'Updating...',
            text: 'Please wait while we update your publication.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: 'update_publication.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (editLoadingModal) {
                    editLoadingModal.close();
                    editLoadingModal = null;
                }

                if (response.success) {
                    Toast.fire({
                        icon: 'success',
                        title: response.message || 'Publication updated successfully!'
                    });
                    $('#editPublicationModal').modal('hide');
                    table.ajax.reload();
                    resetEditForm();
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: response.message || 'Failed to update publication'
                    });
                }
            },
            error: function (xhr, status, error) {
                console.log("Response Error:", xhr.responseText);
                
                if (editLoadingModal) {
                    editLoadingModal.close();
                    editLoadingModal = null;
                }
                
                Toast.fire({
                    icon: 'error',
                    title: 'An error occurred while processing your request'
                });
                console.error('AJAX Error:', error);
            }
        });
    });

    // EDIT BUTTON HANDLER
    $(document).on('click', '.edit-btn', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Loading...',
            html: 'Fetching publication details, please wait...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(`get_publication.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data) throw new Error('Publication not found');

                $('#editPublicationId').val(data.id);
                $('#editPostingDate').val(data.posting_date);
                $('#editClosingDate').val(data.closing_date);

                if (data.publication_file) {
                    $('#editCurrentFileName').text(data.publication_file);
                    $('#editCurrentFile').show();
                } else {
                    $('#editCurrentFile').hide();
                }

                return loadAgenciesForEdit(data.agency_id);
            })
            .then(() => {
                Swal.close();
                $('#editPublicationModal').modal('show');
            })
            .catch(error => {
                Swal.close();
                Toast.fire({
                    icon: 'error',
                    title: error.message || 'Failed to load publication'
                });
            });
    });

    // DELETE BUTTON HANDLER
    $(document).on('click', '.delete-btn', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<i class="bi bi-trash"></i> Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading alert
                Swal.fire({
                    title: 'Deleting...',
                    html: 'Please wait while the publication is being deleted.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                $.ajax({
                    url: 'delete_publication.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function (response) {
                        Swal.close(); // Close loading indicator

                        if (response.success) {
                            Toast.fire({
                                icon: 'success',
                                title: response.message || 'Publication deleted successfully!'
                            });
                            table.ajax.reload();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message || 'Failed to delete publication'
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        Swal.close(); // Close loading indicator
                        Toast.fire({
                            icon: 'error',
                            title: 'An error occurred while deleting'
                        });
                        console.error('AJAX Error:', error);
                    }
                });
            }
        });
    });

    // Reset form functions (separated)
    function resetAddForm() {
        $('#addPublicationForm')[0].reset();
        $('#addSelectedAgencyId').val('');
        $('#addAgencySearch').removeClass('selected-agency');
        $('#addAgencyDropdown').hide();
    }

    function resetEditForm() {
        $('#editPublicationForm')[0].reset();
        $('#editPublicationId').val('');
        $('#editSelectedAgencyId').val('');
        $('#editAgencySearch').removeClass('selected-agency');
        $('#editCurrentFile').hide();
        $('#editCurrentFileName').text('');
        $('#editAgencyDropdown').hide();
    }

    // Modal event handlers
    $('#addPublicationModal').on('hidden.bs.modal', function () {
        // Clean up loading modal if still open
        if (addLoadingModal) {
            addLoadingModal.close();
            addLoadingModal = null;
        }
        resetAddForm();
    });

    $('#editPublicationModal').on('hidden.bs.modal', function () {
        // Clean up loading modal if still open
        if (editLoadingModal) {
            editLoadingModal.close();
            editLoadingModal = null;
        }
        resetEditForm();
    });
});