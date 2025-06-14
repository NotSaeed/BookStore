<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Bulk Edit";
require_once __DIR__ . '/includes/seller_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="welcome-banner p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">✏️ Bulk Edit</h2>
                        <p class="mb-0 text-muted">Edit multiple books at once with bulk operations.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-pencil-square text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Bulk Edit Operations</h3>
                            <p class="text-muted">Bulk edit functionality is coming soon!</p>
                            <p class="text-muted">This will allow you to edit multiple books simultaneously.</p>
                            <a href="seller_manage_books.php" class="btn btn-success">
                                <i class="bi bi-collection"></i> Manage Books
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/seller_footer.php'; ?>
