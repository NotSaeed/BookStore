<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];
$sellerName = $_SESSION['seller_name'] ?? 'Unknown Seller';
$businessName = $_SESSION['business_name'] ?? '';

// Check if mPDF is available
if (!class_exists('\Mpdf\Mpdf')) {
    $possible_autoload_paths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
    ];
    
    $autoload_found = false;
    foreach ($possible_autoload_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $autoload_found = true;
            break;
        }
    }
    
    if (!$autoload_found || !class_exists('\Mpdf\Mpdf')) {
        $error_action = "Failed to export PDF - mPDF library not found";
        $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
        if ($log) {
            $log->bind_param("is", $seller_id, $error_action);
            $log->execute();
            $log->close();
        }
        
        showErrorPage($conn, 'PDF Export Failed', 'The PDF export could not be completed because the required library (mPDF) is not installed or not properly configured.', true);
        exit;
    }
}

// Get enhanced filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$condition = isset($_GET['condition']) ? trim($_GET['condition']) : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

try {
    // Build enhanced query with comprehensive data
    $sql = "SELECT sb.book_id, sb.title, sb.author, sb.isbn, sb.genre, sb.condition_type,
                   sb.price, sb.cost_price, sb.stock_quantity, sb.description, sb.cover_image,
                   sb.created_at, sb.updated_at,
                   COALESCE(AVG(sr.rating), 0) as avg_rating,
                   COUNT(DISTINCT sr.review_id) as review_count,
                   COALESCE(SUM(so.quantity), 0) as total_sold
            FROM seller_books sb
            LEFT JOIN seller_reviews sr ON sb.book_id = sr.book_id
            LEFT JOIN seller_orders so ON sb.book_id = so.book_id
            WHERE sb.seller_id = ?";

    $params = [$seller_id];
    $types = "i";

    // Apply filters
    if (!empty($search)) {
        $sql .= " AND (sb.title LIKE ? OR sb.author LIKE ? OR sb.isbn LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= "sss";
    }

    if (!empty($genre)) {
        $sql .= " AND sb.genre = ?";
        $params[] = $genre;
        $types .= "s";
    }

    if (!empty($condition)) {
        $sql .= " AND sb.condition_type = ?";
        $params[] = $condition;
        $types .= "s";
    }

    if ($min_price > 0) {
        $sql .= " AND sb.price >= ?";
        $params[] = $min_price;
        $types .= "d";
    }

    if ($max_price > 0) {
        $sql .= " AND sb.price <= ?";
        $params[] = $max_price;
        $types .= "d";
    }

    if (!empty($date_from)) {
        $sql .= " AND DATE(sb.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }

    if (!empty($date_to)) {
        $sql .= " AND DATE(sb.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }

    $sql .= " GROUP BY sb.book_id ORDER BY sb.{$sort_by} {$sort_order}";

    $query = $conn->prepare($sql);
    if (!$query) {
        throw new Exception("SQL Error: " . $conn->error);
    }

    $query->bind_param($types, ...$params);
    $query->execute();
    $result = $query->get_result();
    $books = $result->fetch_all(MYSQLI_ASSOC);
    $query->close();

    // Get comprehensive statistics
    $stats = getSellerStatistics($conn, $seller_id);
    
    // Log the export action
    $action = "Exported " . count($books) . " books to PDF with filters applied";
    $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
    if ($log) {
        $log->bind_param("is", $seller_id, $action);
        $log->execute();
        $log->close();
    }

    // Generate enhanced PDF
    generateEnhancedPDF($books, $stats, $sellerName, $businessName);

} catch (Exception $e) {
    $error_action = "PDF export error: " . $e->getMessage();
    $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
    if ($log) {
        $log->bind_param("is", $seller_id, $error_action);
        $log->execute();
        $log->close();
    }
    
    showErrorPage($conn, 'PDF Export Failed', 'An error occurred while generating your PDF: ' . $e->getMessage());
}

$conn->close();

// Enhanced statistics gathering function
function getSellerStatistics($conn, $seller_id) {
    $stats = [];
    
    // Basic counts and values
    $basicQuery = "SELECT 
                    COUNT(*) as total_books,
                    COALESCE(SUM(price), 0) as total_value,
                    COALESCE(SUM(cost_price * stock_quantity), 0) as total_cost,
                    COALESCE(SUM(stock_quantity), 0) as total_stock,
                    COALESCE(AVG(price), 0) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price
                   FROM seller_books WHERE seller_id = ?";
    
    $stmt = $conn->prepare($basicQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['basic'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Genre breakdown
    $genreQuery = "SELECT genre, COUNT(*) as count, SUM(price) as value 
                   FROM seller_books WHERE seller_id = ? AND genre IS NOT NULL AND genre != '' 
                   GROUP BY genre ORDER BY count DESC";
    $stmt = $conn->prepare($genreQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['genres'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Condition breakdown
    $conditionQuery = "SELECT condition_type, COUNT(*) as count, AVG(price) as avg_price 
                       FROM seller_books WHERE seller_id = ? 
                       GROUP BY condition_type ORDER BY count DESC";
    $stmt = $conn->prepare($conditionQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['conditions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Monthly additions
    $monthlyQuery = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                     FROM seller_books WHERE seller_id = ? 
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                     ORDER BY month DESC LIMIT 12";
    $stmt = $conn->prepare($monthlyQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['monthly'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Top performing books (if sales data exists)
    $topBooksQuery = "SELECT sb.title, sb.author, sb.price, 
                             COALESCE(SUM(so.quantity), 0) as total_sold,
                             COALESCE(AVG(sr.rating), 0) as avg_rating,
                             COUNT(DISTINCT sr.review_id) as review_count
                      FROM seller_books sb
                      LEFT JOIN seller_orders so ON sb.book_id = so.book_id
                      LEFT JOIN seller_reviews sr ON sb.book_id = sr.book_id
                      WHERE sb.seller_id = ?
                      GROUP BY sb.book_id
                      ORDER BY total_sold DESC, avg_rating DESC
                      LIMIT 10";
    $stmt = $conn->prepare($topBooksQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['top_books'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $stats;
}

// Enhanced PDF generation function
function generateEnhancedPDF($books, $stats, $sellerName, $businessName) {
    $mpdfConfig = [
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10,
    ];

    $mpdf = new \Mpdf\Mpdf($mpdfConfig);

    // Set document properties
    $mpdf->SetTitle("BookStore Inventory Report - " . date('Y-m-d'));
    $mpdf->SetAuthor($sellerName);
    $mpdf->SetCreator("BookStore Seller Hub");
    $mpdf->SetSubject("Book Inventory and Analytics Report");

    // Professional header
    $headerHtml = '
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; text-align: center; border-radius: 10px;">
        <div style="font-size: 24pt; font-weight: bold; margin-bottom: 5px;">📚 BookStore</div>
        <div style="font-size: 14pt; margin-bottom: 3px;">Professional Inventory Report</div>
        <div style="font-size: 10pt; opacity: 0.9;">' . (!empty($businessName) ? htmlspecialchars($businessName) . ' - ' : '') . htmlspecialchars($sellerName) . '</div>
    </div>';

    $mpdf->SetHTMLHeader($headerHtml);

    // Professional footer
    $footerHtml = '
    <div style="border-top: 2px solid #667eea; padding-top: 10px; font-size: 9pt;">
        <table width="100%">
            <tr>
                <td style="text-align: left; color: #666;">Generated: ' . date('F j, Y \a\t g:i A') . '</td>
                <td style="text-align: center; color: #667eea; font-weight: bold;">BookStore Seller Hub</td>
                <td style="text-align: right; color: #666;">Page {PAGENO} of {nbpg}</td>
            </tr>
        </table>
    </div>';

    $mpdf->SetHTMLFooter($footerHtml);

    // Enhanced CSS styles
    $css = '
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }
        .title-page {
            text-align: center;
            padding: 50px 0;
            page-break-after: always;
        }
        .main-title {
            font-size: 28pt;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        .subtitle {
            font-size: 16pt;
            color: #666;
            margin-bottom: 30px;
        }
        .report-info {
            background: #f8f9fa;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        h1 {
            color: #667eea;
            font-size: 18pt;
            margin: 30px 0 15px 0;
            border-bottom: 2px solid #667eea;
            padding-bottom: 5px;
        }
        h2 {
            color: #764ba2;
            font-size: 14pt;
            margin: 20px 0 10px 0;
        }
        h3 {
            color: #333;
            font-size: 12pt;
            margin: 15px 0 8px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .summary-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
            padding: 12px 8px;
            text-align: left;
            border: none;
        }
        .summary-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            background: #f9f9fa;
        }
        .data-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
            padding: 8px 6px;
            text-align: left;
            font-size: 9pt;
        }
        .data-table td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 9pt;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        .stats-item {
            display: table-cell;
            width: 25%;
            padding: 15px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .stats-number {
            font-size: 18pt;
            font-weight: bold;
            color: #667eea;
            display: block;
        }
        .stats-label {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
        .condition-new { color: #28a745; font-weight: bold; }
        .condition-like_new { color: #20c997; font-weight: bold; }
        .condition-very_good { color: #17a2b8; font-weight: bold; }
        .condition-good { color: #ffc107; font-weight: bold; }
        .condition-acceptable { color: #fd7e14; font-weight: bold; }
        .price-high { color: #dc3545; font-weight: bold; }
        .price-medium { color: #ffc107; font-weight: bold; }
        .price-low { color: #28a745; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-success { color: #28a745; }
        .text-primary { color: #667eea; }
        .text-muted { color: #6c757d; }
        .highlight-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .chart-placeholder {
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-weight: bold;
            margin: 15px 0;
        }
        .page-break { page-break-before: always; }
        .no-break { page-break-inside: avoid; }
    </style>';

    // Build comprehensive HTML report
    $html = $css;

    // Title Page
    $html .= '
    <div class="title-page">
        <div class="main-title">📚 Book Inventory Report</div>
        <div class="subtitle">Professional Analytics & Inventory Management</div>
        
        <div class="report-info">
            <table class="summary-table">
                <tr>
                    <td><strong>Seller:</strong></td>
                    <td>' . htmlspecialchars($sellerName) . '</td>
                </tr>';
    
    if (!empty($businessName)) {
        $html .= '
                <tr>
                    <td><strong>Business:</strong></td>
                    <td>' . htmlspecialchars($businessName) . '</td>
                </tr>';
    }
    
    $html .= '
                <tr>
                    <td><strong>Report Generated:</strong></td>
                    <td>' . date('F j, Y \a\t g:i A') . '</td>
                </tr>
                <tr>
                    <td><strong>Total Books:</strong></td>
                    <td>' . count($books) . '</td>
                </tr>
                <tr>
                    <td><strong>Total Inventory Value:</strong></td>
                    <td>RM ' . number_format($stats['basic']['total_value'], 2) . '</td>
                </tr>
            </table>
        </div>
    </div>';

    // Executive Summary
    $html .= '<h1>📊 Executive Summary</h1>';
    
    $html .= '
    <div class="stats-grid">
        <div class="stats-item">
            <span class="stats-number">' . number_format($stats['basic']['total_books']) . '</span>
            <div class="stats-label">Total Books</div>
        </div>
        <div class="stats-item">
            <span class="stats-number">RM' . number_format($stats['basic']['total_value'], 0) . '</span>
            <div class="stats-label">Total Value</div>
        </div>
        <div class="stats-item">
            <span class="stats-number">' . number_format($stats['basic']['total_stock']) . '</span>
            <div class="stats-label">Stock Units</div>
        </div>
        <div class="stats-item">
            <span class="stats-number">RM' . number_format($stats['basic']['avg_price'], 0) . '</span>
            <div class="stats-label">Avg. Price</div>
        </div>
    </div>';

    // Key Insights
    $html .= '
    <div class="highlight-box">
        <h3>📈 Key Insights</h3>
        <ul>
            <li><strong>Price Range:</strong> RM ' . number_format($stats['basic']['min_price'], 2) . ' - RM ' . number_format($stats['basic']['max_price'], 2) . '</li>
            <li><strong>Most Popular Genre:</strong> ' . (!empty($stats['genres']) ? htmlspecialchars($stats['genres'][0]['genre']) . ' (' . $stats['genres'][0]['count'] . ' books)' : 'No genres specified') . '</li>
            <li><strong>Average Stock per Book:</strong> ' . ($stats['basic']['total_books'] > 0 ? number_format($stats['basic']['total_stock'] / $stats['basic']['total_books'], 1) : '0') . ' units</li>
            <li><strong>Portfolio Diversity:</strong> ' . count($stats['genres']) . ' different genres</li>
        </ul>
    </div>';

    // Genre Analysis
    if (!empty($stats['genres'])) {
        $html .= '<h1 class="page-break">📚 Genre Analysis</h1>';
        $html .= '<table class="data-table">';
        $html .= '<thead><tr><th>Genre</th><th>Books</th><th>Total Value</th><th>Avg Price</th><th>% of Collection</th></tr></thead><tbody>';
        
        foreach ($stats['genres'] as $genre) {
            $percentage = $stats['basic']['total_books'] > 0 ? round(($genre['count'] / $stats['basic']['total_books']) * 100, 1) : 0;
            $avgPrice = $genre['count'] > 0 ? $genre['value'] / $genre['count'] : 0;
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($genre['genre']) . '</td>';
            $html .= '<td class="text-center">' . $genre['count'] . '</td>';
            $html .= '<td class="text-right">RM ' . number_format($genre['value'], 2) . '</td>';
            $html .= '<td class="text-right">RM ' . number_format($avgPrice, 2) . '</td>';
            $html .= '<td class="text-center">' . $percentage . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    // Condition Analysis
    if (!empty($stats['conditions'])) {
        $html .= '<h2>🔍 Book Condition Analysis</h2>';
        $html .= '<table class="data-table">';
        $html .= '<thead><tr><th>Condition</th><th>Count</th><th>Average Price</th><th>Percentage</th></tr></thead><tbody>';
        
        foreach ($stats['conditions'] as $condition) {
            $percentage = $stats['basic']['total_books'] > 0 ? round(($condition['count'] / $stats['basic']['total_books']) * 100, 1) : 0;
            $conditionClass = 'condition-' . $condition['condition_type'];
            
            $html .= '<tr>';
            $html .= '<td><span class="' . $conditionClass . '">' . ucwords(str_replace('_', ' ', $condition['condition_type'])) . '</span></td>';
            $html .= '<td class="text-center">' . $condition['count'] . '</td>';
            $html .= '<td class="text-right">RM ' . number_format($condition['avg_price'], 2) . '</td>';
            $html .= '<td class="text-center">' . $percentage . '%</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    // Top Performing Books
    if (!empty($stats['top_books'])) {
        $html .= '<h1 class="page-break">⭐ Top Performing Books</h1>';
        $html .= '<table class="data-table">';
        $html .= '<thead><tr><th>Title</th><th>Author</th><th>Price</th><th>Sold</th><th>Rating</th><th>Reviews</th></tr></thead><tbody>';
        
        foreach (array_slice($stats['top_books'], 0, 10) as $book) {
            $stars = $book['avg_rating'] > 0 ? str_repeat('⭐', round($book['avg_rating'])) : 'No ratings';
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($book['title']) . '</td>';
            $html .= '<td>' . htmlspecialchars($book['author']) . '</td>';
            $html .= '<td class="text-right">RM ' . number_format($book['price'], 2) . '</td>';
            $html .= '<td class="text-center">' . $book['total_sold'] . '</td>';
            $html .= '<td class="text-center">' . number_format($book['avg_rating'], 1) . ' ' . $stars . '</td>';
            $html .= '<td class="text-center">' . $book['review_count'] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }

    // Complete Book Inventory
    $html .= '<h1 class="page-break">📋 Complete Book Inventory</h1>';
    $html .= '<table class="data-table">';
    $html .= '<thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Genre</th><th>Condition</th><th>Price</th><th>Stock</th><th>Rating</th><th>Added</th></tr></thead><tbody>';

    foreach ($books as $book) {
        $priceClass = $book['price'] >= 100 ? 'price-high' : ($book['price'] >= 50 ? 'price-medium' : 'price-low');
        $conditionClass = 'condition-' . $book['condition_type'];
        $stars = $book['avg_rating'] > 0 ? number_format($book['avg_rating'], 1) . '⭐' : 'No rating';
        
        $html .= '<tr>';
        $html .= '<td>' . $book['book_id'] . '</td>';
        $html .= '<td>' . htmlspecialchars($book['title']) . '</td>';
        $html .= '<td>' . htmlspecialchars($book['author']) . '</td>';
        $html .= '<td>' . htmlspecialchars($book['genre'] ?? 'Not specified') . '</td>';
        $html .= '<td><span class="' . $conditionClass . '">' . ucwords(str_replace('_', ' ', $book['condition_type'])) . '</span></td>';
        $html .= '<td class="text-right"><span class="' . $priceClass . '">RM ' . number_format($book['price'], 2) . '</span></td>';
        $html .= '<td class="text-center">' . $book['stock_quantity'] . '</td>';
        $html .= '<td class="text-center">' . $stars . '</td>';
        $html .= '<td>' . date('M j, Y', strtotime($book['created_at'])) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    // Detailed Book Information (Limited to prevent excessive length)
    if (count($books) <= 20) {
        $html .= '<h1 class="page-break">📖 Detailed Book Information</h1>';
        
        foreach ($books as $index => $book) {
            if ($index > 0 && $index % 2 == 0) {
                $html .= '<div class="page-break"></div>';
            }
            
            $html .= '<div class="no-break" style="margin-bottom: 30px;">';
            $html .= '<h3>' . htmlspecialchars($book['title']) . '</h3>';
            $html .= '<table class="summary-table" style="margin-bottom: 15px;">';
            $html .= '<tr><td width="20%"><strong>Author:</strong></td><td>' . htmlspecialchars($book['author']) . '</td></tr>';
            $html .= '<tr><td><strong>Genre:</strong></td><td>' . htmlspecialchars($book['genre'] ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>ISBN:</strong></td><td>' . htmlspecialchars($book['isbn'] ?? 'Not specified') . '</td></tr>';
            $html .= '<tr><td><strong>Condition:</strong></td><td>' . ucwords(str_replace('_', ' ', $book['condition_type'])) . '</td></tr>';
            $html .= '<tr><td><strong>Price:</strong></td><td>RM ' . number_format($book['price'], 2) . '</td></tr>';
            $html .= '<tr><td><strong>Stock:</strong></td><td>' . $book['stock_quantity'] . ' units</td></tr>';
            $html .= '<tr><td><strong>Rating:</strong></td><td>' . ($book['avg_rating'] > 0 ? number_format($book['avg_rating'], 1) . '/5 (' . $book['review_count'] . ' reviews)' : 'No ratings yet') . '</td></tr>';
            $html .= '<tr><td><strong>Added:</strong></td><td>' . date('F j, Y', strtotime($book['created_at'])) . '</td></tr>';
            
            if (!empty($book['description'])) {
                $html .= '<tr><td valign="top"><strong>Description:</strong></td><td>' . nl2br(htmlspecialchars(substr($book['description'], 0, 300))) . (strlen($book['description']) > 300 ? '...' : '') . '</td></tr>';
            }
            
            $html .= '</table>';
            $html .= '</div>';
        }
    }

    // Report Footer
    $html .= '
    <div class="page-break">
        <div class="highlight-box" style="text-align: center; margin-top: 50px;">
            <h2>📚 Thank you for using BookStore Seller Hub</h2>
            <p>This report was generated automatically to help you manage your book inventory more effectively.</p>
            <p><strong>Report Generation Time:</strong> ' . date('F j, Y \a\t g:i:s A') . '</p>
            <p><strong>Total Processing Time:</strong> ' . number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . ' milliseconds</p>
        </div>
    </div>';

    $mpdf->WriteHTML($html);

    // Generate filename
    $filename = "BookStore_Inventory_Report_" . date('Y-m-d_H-i-s') . ".pdf";
    
    // Output the PDF
    $mpdf->Output($filename, 'D');
}

// Enhanced error page function
function showErrorPage($conn, $title, $message, $showInstallInstructions = false) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($title) ?> | BookStore Seller Hub</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
            .error-card { border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1); }
            .error-icon { font-size: 5rem; color: #dc3545; margin-bottom: 2rem; }
        </style>
    </head>
    <body class="d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card error-card border-0">
                        <div class="card-header bg-danger text-white text-center py-4">
                            <h3 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($title) ?></h3>
                        </div>
                        <div class="card-body text-center p-5">
                            <i class="bi bi-file-earmark-x error-icon"></i>
                            <h4 class="mb-4">Export Failed</h4>
                            <p class="mb-4 lead"><?= htmlspecialchars($message) ?></p>
                            
                            <?php if ($showInstallInstructions): ?>
                            <div class="alert alert-info text-start">
                                <h6><i class="bi bi-info-circle me-2"></i>For System Administrators:</h6>
                                <p class="mb-2">Install the mPDF library using Composer:</p>
                                <pre class="bg-light p-3 rounded"><code>composer require mpdf/mpdf</code></pre>
                                <p class="mb-0 small">Make sure Composer's autoload.php is accessible in the application path.</p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-center gap-3 mt-4">
                                <a href="seller_manage_books.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i>Return to Books
                                </a>
                                <a href="export_books_excel.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="btn btn-success btn-lg">
                                    <i class="bi bi-file-earmark-excel me-2"></i>Export as Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>