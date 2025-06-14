<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$page_title = "Reports";
require_once __DIR__ . '/includes/seller_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="welcome-banner p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">📋 Reports</h2>
                        <p class="mb-0 text-muted">Generate detailed reports for your bookstore operations.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-file-earmark-bar-graph text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Reports Center</h3>
                            <p class="text-muted">Advanced reporting features are coming soon!</p>
                            <p class="text-muted">This will include sales reports, inventory reports, and custom analytics.</p>
                            
                            <div class="row mt-4">
                                <div class="col-md-6 mx-auto">
                                    <div class="list-group">
                                        <a href="seller_export_excel.php" class="list-group-item list-group-item-action">
                                            <i class="bi bi-file-earmark-excel text-success"></i>
                                            Export Books to Excel
                                        </a>
                                        <a href="seller_export_pdf.php" class="list-group-item list-group-item-action">
                                            <i class="bi bi-file-earmark-pdf text-danger"></i>
                                            Export Books to PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="seller_dashboard.php" class="btn btn-success mt-3">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/seller_footer.php'; ?>
