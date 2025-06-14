<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Import Books";
require_once __DIR__ . '/includes/seller_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="welcome-banner p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">📤 Import Books</h2>
                        <p class="mb-0 text-muted">Import multiple books from CSV or Excel files.</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-file-earmark-arrow-up text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Bulk Import</h3>
                            <p class="text-muted">Bulk import functionality is coming soon!</p>
                            <p class="text-muted">This will allow you to import multiple books from CSV/Excel files.</p>
                            <a href="seller_add_book.php" class="btn btn-success me-2">
                                <i class="bi bi-plus-lg"></i> Add Single Book
                            </a>
                            <a href="seller_manage_books.php" class="btn btn-outline-success">
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
