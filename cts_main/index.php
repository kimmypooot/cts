<?php
require_once __DIR__ . '/../../includes/session.php'; 
require_once __DIR__ . '/../../includes/connect.php';
include_once __DIR__ . '/../../imis_include.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>CSC RO VIII Job Portal</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="../../assets/img/favicon.png" rel="icon">


  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="../../assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

  <!-- Template Main CSS File -->
  <link href="../../assets/css/style.css" rel="stylesheet">
  <style>
/* Match Select2 to Bootstrap 5's form-select */
.select2-container--default .select2-selection--single {
  height: calc(2.375rem + 2px);
  padding: 0.375rem 0.75rem;
  font-size: 1rem;
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
  line-height: 1.6;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
  height: 100%;
  top: 0.375rem;
  right: 0.75rem;
}
  </style>
</head>

<body>

  <!-- ======= Header ======= -->
  <?php imis_include ('header_js') ?>
  <!-- ======= Sidebar ======= -->
  <?php include 'inc/sidebar.php' ?>
  <!-- End Sidebar-->

  <main id="main" class="main">
<!-- End Page Title -->

<section class="section">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Bulletin of Vacant Positions Management</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPublicationModal">
                            <i class="bi bi-plus-circle"></i> Add Publication
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="tab-content mt-3">
                            <div>
                                <div class="table-responsive">
                                    <table id="publicationsTable" class="table table-bordered table-hover table-striped" style="font-size: 14px; width: 100%">
                                        <thead>
                                            <tr class="table-primary">
                                                <th class="text-center align-middle">#</th>
                                                <th class="text-center align-middle">Name of Government Agency</th>
                                                <th class="text-center align-middle">Posting<br>Date</th>
                                                <th class="text-center align-middle">Closing<br>Date</th>
                                                <th class="text-center align-middle">Publication<br>File</th>
                                                <th class="text-center align-middle">Action<br>Officer</th>
                                                <th class="text-center align-middle">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
  </main>

  <?php imis_include ('footer') ?>

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>


<!-- Add Publication Modal -->
<div class="modal fade" id="addPublicationModal" tabindex="-1" aria-labelledby="addPublicationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-primary bg-opacity-10">
        <h5 class="modal-title fw-semibold" id="addPublicationModalLabel">
          <i class="bi bi-plus-circle me-2 text-primary"></i> Add Publication
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addPublicationForm">
        <div class="modal-body">
          <!-- Agency -->
            <div class="mb-3">
            <label for="addAgencySelect" class="form-label fw-semibold">
                <i class="bi bi-building me-1 text-secondary"></i> Name of Agency <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="addAgencySelect" name="agency_id" style="width: 100%" required>
                <option value="" selected disabled>Select agency...</option>
                <!-- Options will be loaded dynamically -->
            </select>
            </div>

        <div class="row">
            <!-- Posting Date -->
            <div class="col-md-6 mb-3">
                <label for="addPostingDate" class="form-label fw-semibold">
                <i class="bi bi-calendar-check me-1 text-secondary"></i> Posting Date <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" id="addPostingDate" name="posting_date" required>
            </div>

            <!-- Closing Date -->
            <div class="col-md-6 mb-3">
                <label for="addClosingDate" class="form-label fw-semibold">
                <i class="bi bi-calendar-x me-1 text-secondary"></i> Closing Date <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" id="addClosingDate" name="closing_date" required>
            </div>
        </div>

          <!-- Publication File -->
          <div class="mb-3">
            <label for="addPublicationFile" class="form-label fw-semibold">
              <i class="bi bi-file-earmark-pdf me-1 text-secondary"></i> Publication File <span class="text-danger">*</span>
            </label>
            <input type="file" class="form-control" id="addPublicationFile" name="publication_file" accept=".pdf" required>
            <div class="form-text">Only PDF files are allowed. Maximum file size: 10MB</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save2 me-1"></i> Add Publication
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Publication Modal -->
<div class="modal fade" id="editPublicationModal" tabindex="-1" aria-labelledby="editPublicationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-warning bg-opacity-10">
        <h5 class="modal-title fw-semibold" id="editPublicationModalLabel">
          <i class="bi bi-pencil-square me-2 text-warning"></i> Edit Publication
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editPublicationForm">
        <div class="modal-body">
          <input type="hidden" id="editPublicationId" name="id">

          <!-- Agency -->
        <div class="mb-3">
            <label for="editAgencySelect" class="form-label fw-semibold">
                <i class="bi bi-building me-1 text-secondary"></i> Name of Agency <span class="text-danger">*</span>
            </label>
            <select class="form-select" id="editAgencySelect" name="agency_id" style="width: 100%" required>
                <option value="" selected disabled>Select agency...</option>
                <!-- Options will be loaded dynamically -->
            </select>
        </div>

        <div class="row">
            <!-- Posting Date -->
            <div class="col-md-6 mb-3">
                <label for="editPostingDate" class="form-label fw-semibold">
                <i class="bi bi-calendar-check me-1 text-secondary"></i> Posting Date <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" id="editPostingDate" name="posting_date" required>
            </div>

            <!-- Closing Date -->
            <div class="col-md-6 mb-3">
                <label for="editClosingDate" class="form-label fw-semibold">
                <i class="bi bi-calendar-x me-1 text-secondary"></i> Closing Date <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" id="editClosingDate" name="closing_date" required>
            </div>
        </div>

          <!-- Publication File -->
          <div class="mb-3">
            <label for="editPublicationFile" class="form-label fw-semibold">
              <i class="bi bi-file-earmark-pdf me-1 text-secondary"></i> Publication File
            </label>
            <input type="file" class="form-control" id="editPublicationFile" name="publication_file" accept=".pdf" />
            <div class="form-text mt-1">Only PDF files are allowed. Maximum file size: 10MB. Leave empty to keep current file.</div>

            <div id="editCurrentFile" class="mt-2">
              <small class="text-muted d-block text-break">
                <i class="bi bi-paperclip me-1"></i> Current file: <span id="editCurrentFileName"></span>
              </small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-arrow-repeat me-1"></i> Update Publication
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


  <!-- Vendor JS Files -->
  <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Template Main JS File -->
    <script src="../../assets/js/main.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.32/sweetalert2.all.min.js"></script>
   <script src="assets/js/publications.js"></script>
</body>
</html>