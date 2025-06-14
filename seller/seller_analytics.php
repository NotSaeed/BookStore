<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$page_title = "Analytics Dashboard";
require_once __DIR__ . '/includes/seller_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="welcome-banner p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">📊 Analytics Dashboard</h2>
                        <p class="mb-0 text-muted">Detailed insights and analytics for your bookstore performance.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-graph-up text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Analytics Dashboard</h3>
                            <p class="text-muted">Advanced analytics and reporting features are coming soon!</p>
                            <p class="text-muted">This will include detailed sales analytics, performance metrics, and data visualization.</p>
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
