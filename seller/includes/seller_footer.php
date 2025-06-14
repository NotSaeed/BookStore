<?php
// filepath: c:\xampp\htdocs\BookStore\seller\includes\seller_footer.php

// Get current year for copyright
$current_year = date('Y');

// Get seller info if available
$seller_name = $_SESSION['seller_name'] ?? 'Seller';
$seller_id = $_SESSION['seller_id'] ?? null;

// Get some quick stats for footer display
$footer_stats = [];
if ($seller_id) {
    try {
        // Quick stats query
        $stats_query = $conn->prepare("
            SELECT 
                COUNT(*) as total_books,
                SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) as public_books,
                SUM(CASE WHEN stock_quantity > 0 THEN 1 ELSE 0 END) as in_stock,
                MAX(created_at) as last_added
            FROM seller_books 
            WHERE seller_id = ?
        ");
        $stats_query->bind_param("i", $seller_id);
        $stats_query->execute();
        $footer_stats = $stats_query->get_result()->fetch_assoc();
        $stats_query->close();
    } catch (Exception $e) {
        // Silently handle error for footer
        $footer_stats = [
            'total_books' => 0,
            'public_books' => 0,
            'in_stock' => 0,
            'last_added' => null
        ];
    }
}

// Get current page for active links
$current_page = basename($_SERVER['PHP_SELF']);
?>

</div> <!-- Close main content wrapper -->

<!-- Enhanced Footer -->
<footer class="footer bg-dark text-light py-5 mt-auto">
    <div class="container">
        <div class="row">
            <!-- Quick Stats Column -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="text-success mb-3">
                    <i class="bi bi-graph-up me-2"></i>Your Stats
                </h5>
                <?php if (!empty($footer_stats)): ?>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Books:</span>
                            <span class="text-success fw-bold"><?= number_format($footer_stats['total_books']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Published:</span>
                            <span class="text-info"><?= number_format($footer_stats['public_books']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>In Stock:</span>
                            <span class="text-warning"><?= number_format($footer_stats['in_stock']) ?></span>
                        </div>
                        <?php if ($footer_stats['last_added']): ?>
                            <div class="d-flex justify-content-between">
                                <span>Last Added:</span>
                                <span class="text-muted small"><?= date('M j', strtotime($footer_stats['last_added'])) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">Start adding books to see your stats here.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Links Column -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="text-success mb-3">
                    <i class="bi bi-link-45deg me-2"></i>Quick Links
                </h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="seller_dashboard.php" class="text-decoration-none text-light hover-success">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="seller_add_book.php" class="text-decoration-none text-light hover-success">
                            <i class="bi bi-plus-circle me-2"></i>Add New Book
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="seller_manage_books.php" class="text-decoration-none text-light hover-success">
                            <i class="bi bi-book me-2"></i>Manage Books
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="seller_settings.php" class="text-decoration-none text-light hover-success">
                            <i class="bi bi-gear me-2"></i>Account Settings
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="seller_activity_log.php" class="text-decoration-none text-light hover-success">
                            <i class="bi bi-clock-history me-2"></i>Activity Log
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Tools & Export Column -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="text-success mb-3">
                    <i class="bi bi-tools me-2"></i>Tools & Export
                </h5>
                <div class="d-grid gap-2">
                    <a href="seller_export_excel.php" class="btn btn-outline-success btn-sm" title="Export your inventory to Excel">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Export to Excel
                    </a>
                    <a href="seller_export_pdf.php" class="btn btn-outline-success btn-sm" title="Export your inventory to PDF">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Export to PDF
                    </a>
                    <button class="btn btn-outline-info btn-sm" onclick="printPage()" title="Print current page">
                        <i class="bi bi-printer me-2"></i>Print Page
                    </button>
                    <a href="seller_search.php" class="btn btn-outline-warning btn-sm" title="Advanced search">
                        <i class="bi bi-search me-2"></i>Advanced Search
                    </a>
                </div>
            </div>

            <!-- Support & Info Column -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="text-success mb-3">
                    <i class="bi bi-question-circle me-2"></i>Support & Info
                </h5>
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <a href="#" class="text-decoration-none text-light hover-success" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="bi bi-question-circle me-2"></i>Help & FAQ
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-decoration-none text-light hover-success" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="bi bi-envelope me-2"></i>Contact Support
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-decoration-none text-light hover-success" data-bs-toggle="modal" data-bs-target="#guidelinesModal">
                            <i class="bi bi-book me-2"></i>Seller Guidelines
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-decoration-none text-light hover-success" onclick="showKeyboardShortcuts()">
                            <i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="my-4 border-secondary">

        <!-- Bottom Footer Row -->
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <img src="../assets/images/bookstore-logo-white.png" alt="BookStore" height="30" class="d-none" onerror="this.style.display='none'">
                        <span class="h5 text-success mb-0">📚 BookStore</span>
                    </div>
                    <div class="small text-muted">
                        <div>Seller Hub Dashboard</div>
                        <div>Empowering Book Sellers Since 2024</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="small text-muted mb-2">
                    Welcome back, <span class="text-success fw-bold"><?= htmlspecialchars($seller_name) ?></span>
                </div>
                <div class="small text-muted">
                    <span id="currentDateTime"></span> | Session: <span class="text-info"><?= substr(session_id(), 0, 8) ?>...</span>
                </div>
            </div>
        </div>

        <!-- Copyright and Legal -->
        <div class="row mt-3 pt-3 border-top border-secondary">
            <div class="col-md-8">
                <p class="small text-muted mb-0">
                    &copy; <?= $current_year ?> BookStore Seller Hub. All rights reserved. 
                    <a href="#" class="text-decoration-none text-muted hover-success ms-2">Privacy Policy</a> |
                    <a href="#" class="text-decoration-none text-muted hover-success ms-2">Terms of Service</a> |
                    <a href="#" class="text-decoration-none text-muted hover-success ms-2">Seller Agreement</a>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="small text-muted">
                    Version 2.1.0 | 
                    <span class="text-success">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i> System Online
                    </span>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="helpModalLabel">
                    <i class="bi bi-question-circle me-2"></i>Help & Frequently Asked Questions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="helpAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                How do I add a new book to my inventory?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                <ol>
                                    <li>Navigate to <strong>Add New Book</strong> from the dashboard or sidebar</li>
                                    <li>Fill in the book details including title, author, price, and description</li>
                                    <li>Upload a cover image (recommended for better visibility)</li>
                                    <li>Set your book as public or private</li>
                                    <li>Click <strong>Add Book</strong> to save</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                How do I make my books visible to customers?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                To make your books visible to customers:
                                <ul>
                                    <li>Go to <strong>Manage Books</strong></li>
                                    <li>Find the book you want to publish</li>
                                    <li>Toggle the <strong>Public/Private</strong> switch to make it public</li>
                                    <li>Ensure your book has a good description and cover image</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                How do I export my inventory?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                You can export your inventory in two formats:
                                <ul>
                                    <li><strong>Excel:</strong> Click "Export to Excel" for a comprehensive spreadsheet</li>
                                    <li><strong>PDF:</strong> Click "Export to PDF" for a formatted document</li>
                                </ul>
                                Both exports include all your book details, statistics, and can be filtered by category or search terms.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-success">More Help Resources</a>
            </div>
        </div>
    </div>
</div>

<!-- Contact Support Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="contactModalLabel">
                    <i class="bi bi-envelope me-2"></i>Contact Support
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="supportForm">
                    <div class="mb-3">
                        <label for="supportCategory" class="form-label">Category</label>
                        <select class="form-select" id="supportCategory" required>
                            <option value="">Select a category</option>
                            <option value="technical">Technical Issue</option>
                            <option value="account">Account Problem</option>
                            <option value="billing">Billing Question</option>
                            <option value="feature">Feature Request</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="supportSubject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="supportSubject" required>
                    </div>
                    <div class="mb-3">
                        <label for="supportMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="supportMessage" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="supportPriority" class="form-label">Priority</label>
                        <select class="form-select" id="supportPriority">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitSupportRequest()">Send Message</button>
            </div>
        </div>
    </div>
</div>

<!-- Seller Guidelines Modal -->
<div class="modal fade" id="guidelinesModal" tabindex="-1" aria-labelledby="guidelinesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="guidelinesModalLabel">
                    <i class="bi bi-book me-2"></i>Seller Guidelines
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success">✅ Do's</h6>
                        <ul class="small">
                            <li>Provide accurate book descriptions</li>
                            <li>Use high-quality cover images</li>
                            <li>Set competitive prices</li>
                            <li>Respond to customer inquiries promptly</li>
                            <li>Keep inventory updated</li>
                            <li>Follow copyright laws</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-danger">❌ Don'ts</h6>
                        <ul class="small">
                            <li>Upload copyrighted images without permission</li>
                            <li>List books you don't own</li>
                            <li>Use misleading descriptions</li>
                            <li>Spam or duplicate listings</li>
                            <li>Violate platform policies</li>
                            <li>Engage in fraudulent activities</li>
                        </ul>
                    </div>
                </div>
                <hr>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Remember:</strong> Following these guidelines helps maintain a quality marketplace for all users.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-warning">Full Terms & Conditions</a>
            </div>
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Modal -->
<div class="modal fade" id="shortcutsModal" tabindex="-1" aria-labelledby="shortcutsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="shortcutsModalLabel">
                    <i class="bi bi-keyboard me-2"></i>Keyboard Shortcuts
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-6">
                        <h6>Navigation</h6>
                        <table class="table table-sm">
                            <tr><td><kbd>Alt + D</kbd></td><td>Dashboard</td></tr>
                            <tr><td><kbd>Alt + A</kbd></td><td>Add Book</td></tr>
                            <tr><td><kbd>Alt + M</kbd></td><td>Manage Books</td></tr>
                            <tr><td><kbd>Alt + S</kbd></td><td>Settings</td></tr>
                        </table>
                    </div>
                    <div class="col-6">
                        <h6>Actions</h6>
                        <table class="table table-sm">
                            <tr><td><kbd>Ctrl + S</kbd></td><td>Save Form</td></tr>
                            <tr><td><kbd>Ctrl + /</kbd></td><td>Search</td></tr>
                            <tr><td><kbd>Esc</kbd></td><td>Close Modal</td></tr>
                            <tr><td><kbd>F1</kbd></td><td>Help</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Styles -->
<style>
.hover-success:hover {
    color: #198754 !important;
    transition: color 0.3s ease;
}

.footer {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%) !important;
    border-top: 3px solid #198754;
}

.footer .btn-outline-success:hover {
    transform: translateY(-2px);
    transition: transform 0.3s ease;
}

@media (max-width: 768px) {
    .footer .col-md-6 {
        text-align: center !important;
        margin-bottom: 1rem;
    }
}

.modal-header.bg-success,
.modal-header.bg-primary,
.modal-header.bg-warning,
.modal-header.bg-info {
    border-bottom: none;
}

kbd {
    background-color: #f8f9fa;
    color: #495057;
    padding: 0.2em 0.4em;
    border-radius: 0.25rem;
    border: 1px solid #dee2e6;
    font-size: 0.75em;
}
</style>

<!-- Footer JavaScript -->
<script>
// Update current time
function updateDateTime() {
    const now = new Date();
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    };
    document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
}

// Print current page
function printPage() {
    window.print();
}

// Show keyboard shortcuts
function showKeyboardShortcuts() {
    const modal = new bootstrap.Modal(document.getElementById('shortcutsModal'));
    modal.show();
}

// Submit support request
function submitSupportRequest() {
    const form = document.getElementById('supportForm');
    const formData = new FormData(form);
    
    // Basic validation
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    // Here you would typically send the data to your backend
    // For now, we'll just show a success message
    alert('Support request submitted successfully! We\'ll get back to you within 24 hours.');
    
    // Close modal and reset form
    bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
    form.reset();
    form.classList.remove('was-validated');
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.altKey) {
        switch(e.key) {
            case 'd':
                e.preventDefault();
                window.location.href = 'seller_dashboard.php';
                break;
            case 'a':
                e.preventDefault();
                window.location.href = 'seller_add_book.php';
                break;
            case 'm':
                e.preventDefault();
                window.location.href = 'seller_manage_books.php';
                break;
            case 's':
                e.preventDefault();
                window.location.href = 'seller_settings.php';
                break;
        }
    }
    
    if (e.ctrlKey && e.key === '/') {
        e.preventDefault();
        const searchInput = document.querySelector('input[type="search"], input[name="search"]');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    if (e.key === 'F1') {
        e.preventDefault();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('helpModal')).show();
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 60000); // Update every minute
    
    // Add smooth scrolling to footer links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

</body>
</html>