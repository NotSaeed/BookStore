<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Sales Management";
require_once __DIR__ . '/includes/seller_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="welcome-banner p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">💰 Sales Management</h2>
                        <p class="mb-0 text-muted">Track and manage your book sales and orders.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-cart-check text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Sales Dashboard</h3>
                            <p class="text-muted">Sales management features are coming soon!</p>
                            <p class="text-muted">This will include order management, sales tracking, and customer interactions.</p>
                            <a href="seller_dashboard.php" class="btn btn-success">
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
