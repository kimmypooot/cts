<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .swal2-popup {
        font-size: 0.90rem !important;
    }
    label,
    input[type="password"] {
        font-size: 14px;
    }
    .small-swal-modal .swal2-modal {
    max-width: 325px;
  }
</style>
<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
        <a href="index" class="logo d-flex align-items-center">
            <img src="img/csclogo.png" alt="">
            <span class="d-none d-lg-block">&nbsp;CSC RO VIII</span>
        </a>
        <!-- <i class="bi bi-list toggle-sidebar-btn"></i> -->
    </div><!-- End Logo -->
    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">
            <li class="nav-item dropdown pe-3">
                <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                    <img src="img/csclogo-profile.png" alt="Profile" class="rounded-circle">
                    <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $_SESSION['name']; ?></span>
                </a><!-- End Profile Image Icon -->
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <!-- <li class="dropdown-header">
                        <h6><?php echo $_SESSION['name']; ?></h6>
                        <span>Administrator</span>
                    </li> -->
                    <li>
                        <a type="button" class="dropdown-item d-flex align-items-center" id="changepw">
                            <i class="bi bi-shield-lock"></i>
                            <span>Change Password</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" id="logout">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Sign Out</span>
                        </a>
                    </li>
                </ul><!-- End Profile Dropdown Items -->
            </li><!-- End Profile Nav -->
        </ul>
    </nav><!-- End Icons Navigation -->
</header><!-- End Header -->
<script>
    document.getElementById('logout').addEventListener('click', function() {
        Swal.fire({
            title: 'Are you sure you want to logout?',
            text: "You will be redirected to the login page.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="bi bi-box-arrow-right"></i> Confirm',
            cancelButtonText: '<i class="bi bi-x-circle"></i> Cancel',
        }).then((result) => {
            if (result.isConfirmed) {
                let timerInterval
                Swal.fire({
                    title: 'You have been successfully logged out.',
                    icon: 'success',
                    html: 'Redirecting to the login page in <strong></strong> second/s.<br/><br/>',
                    timer: 5500,
                    didOpen: () => {
                        const content = Swal.getHtmlContainer()
                        const $ = content.querySelector.bind(content)
                        Swal.showLoading()
                        timerInterval = setInterval(() => {
                            Swal.getHtmlContainer().querySelector('strong')
                                .textContent = (Swal.getTimerLeft() / 1000)
                                .toFixed(0)
                        }, 100)
                    },
                    willClose: () => {
                        clearInterval(timerInterval)
                        location.href = 'inc/logout.php';
                    }
                })
            }
        });
    });

    document.getElementById('changepw').addEventListener('click', function() {
    Swal.fire({
        title: 'Confirmation',
        text: 'Are you sure you want to change the password?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
        Swal.fire({
            title: 'Change Password',
            html: '<form id="changePasswordForm">' +
            '<div class="mb-3">' +
            '<label for="currentPassword" class="form-label"><b>Current Password:</b></label>' +
            '<div class="input-group">' +
            '<input type="password" class="form-control" id="currentPassword" name="currentPassword" required>' +
            '<button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword" onclick="togglePasswordVisibility(\'currentPassword\')">' +
            '<i class="bi bi-eye"></i>' +
            '</button>' +
            '</div>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="newPassword" class="form-label"><b>New Password:</b></label>' +
            '<div class="input-group">' +
            '<input type="password" class="form-control" id="newPassword" name="newPassword" required>' +
            '<button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" onclick="togglePasswordVisibility(\'newPassword\')">' +
            '<i class="bi bi-eye"></i>' +
            '</button>' +
            '</div>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="confirmPassword" class="form-label"><b>Confirm Password:</b></label>' +
            '<div class="input-group">' +
            '<input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>' +
            '<button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" onclick="togglePasswordVisibility(\'confirmPassword\')">' +
            '<i class="bi bi-eye"></i>' +
            '</button>' +
            '</div>' +
            '</div>' +
            '</form>',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-lock"></i> Update',
            cancelButtonText: '<i class="bi bi-x-circle"></i> Close',
            customClass: {
            container: 'small-swal-modal'
            },
            preConfirm: () => {
            const currentPassword = Swal.getPopup().querySelector('#currentPassword').value;
            const newPassword = Swal.getPopup().querySelector('#newPassword').value;
            const confirmPassword = Swal.getPopup().querySelector('#confirmPassword').value;

            return fetch('change_pw.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    currentPassword: currentPassword,
                    newPassword: newPassword,
                    confirmPassword: confirmPassword
                }),
                })
                .then(response => response.json())
                .then(data => {
                if (data.success) {
                    Swal.fire({
                    title: 'Success',
                    text: data.message,
                    icon: 'success'
                    });
                } else {
                    Swal.fire({
                    title: 'Error',
                    text: data.message,
                    icon: 'error'
                    });
                }
                })
                .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred while changing the password.',
                    icon: 'error'
                });
                });
            }
        });
        }
    });
    });

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const button = document.getElementById('toggle' + inputId.charAt(0).toUpperCase() + inputId.slice(1));
    
    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        input.type = 'password';
        button.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

</script>
