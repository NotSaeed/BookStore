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

// Check if PhpSpreadsheet is available
if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
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
    
    if (!$autoload_found || !class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback to legacy XML Excel format
        generateLegacyExcel($conn, $seller_id, $sellerName, $businessName);
        exit;
    }
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

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
    // Build comprehensive query with all book data
    $sql = "SELECT sb.book_id, sb.title, sb.author, sb.isbn, sb.genre, sb.condition_type,
                   sb.price, sb.cost_price, sb.stock_quantity, sb.description, sb.cover_image,
                   sb.created_at, sb.updated_at,
                   COALESCE(AVG(sr.rating), 0) as avg_rating,
                   COUNT(DISTINCT sr.review_id) as review_count,
                   COALESCE(SUM(so.quantity), 0) as total_sold,
                   ROUND((sb.price - sb.cost_price) / sb.cost_price * 100, 2) as profit_margin
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
    $stats = getComprehensiveStats($conn, $seller_id);
    
    // Log the export action
    $action = "Exported " . count($books) . " books to Excel with advanced analytics";
    $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
    if ($log) {
        $log->bind_param("is", $seller_id, $action);
        $log->execute();
        $log->close();
    }

    // Generate professional Excel file
    generateProfessionalExcel($books, $stats, $sellerName, $businessName);

} catch (Exception $e) {
    $error_action = "Excel export error: " . $e->getMessage();
    $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
    if ($log) {
        $log->bind_param("is", $seller_id, $error_action);
        $log->execute();
        $log->close();
    }
    
    showErrorPage('Excel Export Failed', 'An error occurred while generating your Excel file: ' . $e->getMessage());
}

$conn->close();

// Enhanced statistics function
function getComprehensiveStats($conn, $seller_id) {
    $stats = [];
    
    // Basic statistics
    $basicQuery = "SELECT 
                    COUNT(*) as total_books,
                    COALESCE(SUM(price), 0) as total_value,
                    COALESCE(SUM(cost_price * stock_quantity), 0) as total_cost,
                    COALESCE(SUM(stock_quantity), 0) as total_stock,
                    COALESCE(AVG(price), 0) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    COALESCE(SUM((price - cost_price) * stock_quantity), 0) as potential_profit
                   FROM seller_books WHERE seller_id = ?";
    
    $stmt = $conn->prepare($basicQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['basic'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Genre breakdown
    $genreQuery = "SELECT genre, COUNT(*) as count, SUM(price) as value, AVG(price) as avg_price,
                          SUM(stock_quantity) as total_stock
                   FROM seller_books WHERE seller_id = ? AND genre IS NOT NULL AND genre != '' 
                   GROUP BY genre ORDER BY count DESC";
    $stmt = $conn->prepare($genreQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['genres'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Condition breakdown
    $conditionQuery = "SELECT condition_type, COUNT(*) as count, AVG(price) as avg_price,
                              SUM(stock_quantity) as total_stock
                       FROM seller_books WHERE seller_id = ? 
                       GROUP BY condition_type ORDER BY count DESC";
    $stmt = $conn->prepare($conditionQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['conditions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Monthly performance
    $monthlyQuery = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                            COUNT(*) as books_added, 
                            AVG(price) as avg_price,
                            SUM(price) as total_value
                     FROM seller_books WHERE seller_id = ? 
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                     ORDER BY month DESC LIMIT 12";
    $stmt = $conn->prepare($monthlyQuery);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats['monthly'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Price range analysis
    $priceRanges = [
        ['min' => 0, 'max' => 25, 'label' => 'Under RM25'],
        ['min' => 25, 'max' => 50, 'label' => 'RM25-50'],
        ['min' => 50, 'max' => 100, 'label' => 'RM50-100'],
        ['min' => 100, 'max' => 200, 'label' => 'RM100-200'],
        ['min' => 200, 'max' => 999999, 'label' => 'Over RM200']
    ];

    $stats['price_ranges'] = [];
    foreach ($priceRanges as $range) {
        $rangeQuery = "SELECT COUNT(*) as count, AVG(price) as avg_price
                       FROM seller_books 
                       WHERE seller_id = ? AND price >= ? AND price < ?";
        $stmt = $conn->prepare($rangeQuery);
        $stmt->bind_param("idd", $seller_id, $range['min'], $range['max']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['price_ranges'][] = array_merge($range, $result);
        $stmt->close();
    }

    return $stats;
}

// Professional Excel generation function
function generateProfessionalExcel($books, $stats, $sellerName, $businessName) {
    $spreadsheet = new Spreadsheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator($sellerName)
        ->setLastModifiedBy($sellerName)
        ->setTitle('BookStore Inventory Report')
        ->setSubject('Book Inventory and Analytics')
        ->setDescription('Comprehensive book inventory report with analytics')
        ->setKeywords('books inventory analytics report')
        ->setCategory('Business Report');

    // Create worksheets
    createDashboardSheet($spreadsheet, $stats, $sellerName, $businessName);
    createInventorySheet($spreadsheet, $books);
    createAnalyticsSheet($spreadsheet, $stats);
    createGenreAnalysisSheet($spreadsheet, $stats);
    createMonthlyTrendsSheet($spreadsheet, $stats);

    // Set active sheet to dashboard
    $spreadsheet->setActiveSheetIndex(0);

    // Generate filename
    $filename = "BookStore_Inventory_Report_" . date('Y-m-d_H-i-s') . ".xlsx";

    // Set headers and output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

// Dashboard sheet creation
function createDashboardSheet($spreadsheet, $stats, $sellerName, $businessName) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('📊 Dashboard');

    // Header styling
    $headerStyle = [
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '667eea']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    // Title section
    $sheet->mergeCells('A1:H1');
    $sheet->setCellValue('A1', '📚 BookStore Seller Hub - Inventory Dashboard');
    $sheet->getStyle('A1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(40);

    // Business info section
    $sheet->setCellValue('A3', 'Seller Information');
    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A4', 'Seller Name:');
    $sheet->setCellValue('B4', $sellerName);
    if (!empty($businessName)) {
        $sheet->setCellValue('A5', 'Business Name:');
        $sheet->setCellValue('B5', $businessName);
    }
    $sheet->setCellValue('A6', 'Report Generated:');
    $sheet->setCellValue('B6', date('F j, Y \a\t g:i A'));

    // Key metrics section
    $sheet->setCellValue('A8', 'Key Performance Indicators');
    $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(14);

    $kpis = [
        ['label' => 'Total Books', 'value' => number_format($stats['basic']['total_books']), 'icon' => '📚'],
        ['label' => 'Total Inventory Value', 'value' => 'RM ' . number_format($stats['basic']['total_value'], 2), 'icon' => '💰'],
        ['label' => 'Total Stock Units', 'value' => number_format($stats['basic']['total_stock']), 'icon' => '📦'],
        ['label' => 'Average Book Price', 'value' => 'RM ' . number_format($stats['basic']['avg_price'], 2), 'icon' => '📊'],
    ];

    $row = 9;
    foreach ($kpis as $kpi) {
        $sheet->setCellValue("A{$row}", $kpi['icon'] . ' ' . $kpi['label']);
        $sheet->setCellValue("B{$row}", $kpi['value']);
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f8f9fa']]
        ]);
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        $row++;
    }

    // Price analysis
    $sheet->setCellValue('D8', 'Price Analysis');
    $sheet->getStyle('D8')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('D9', 'Lowest Price:');
    $sheet->setCellValue('E9', 'RM ' . number_format($stats['basic']['min_price'], 2));
    $sheet->setCellValue('D10', 'Highest Price:');
    $sheet->setCellValue('E10', 'RM ' . number_format($stats['basic']['max_price'], 2));
    $sheet->setCellValue('D11', 'Price Range:');
    $sheet->setCellValue('E11', 'RM ' . number_format($stats['basic']['max_price'] - $stats['basic']['min_price'], 2));
    $sheet->setCellValue('D12', 'Potential Profit:');
    $sheet->setCellValue('E12', 'RM ' . number_format($stats['basic']['potential_profit'], 2));

    $sheet->getStyle('D9:E12')->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e3f2fd']]
    ]);

    // Top genres section
    if (!empty($stats['genres'])) {
        $sheet->setCellValue('A15', 'Top 5 Genres by Book Count');
        $sheet->getStyle('A15')->getFont()->setBold(true)->setSize(14);
        
        $genreHeaders = ['Genre', 'Books', 'Total Value', 'Avg Price', 'Stock'];
        $col = 'A';
        foreach ($genreHeaders as $header) {
            $sheet->setCellValue($col . '16', $header);
            $sheet->getStyle($col . '16')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '667eea']],
                'font' => ['color' => ['rgb' => 'FFFFFF']]
            ]);
            $col++;
        }

        $row = 17;
        foreach (array_slice($stats['genres'], 0, 5) as $genre) {
            $sheet->setCellValue("A{$row}", $genre['genre']);
            $sheet->setCellValue("B{$row}", $genre['count']);
            $sheet->setCellValue("C{$row}", 'RM ' . number_format($genre['value'], 2));
            $sheet->setCellValue("D{$row}", 'RM ' . number_format($genre['avg_price'], 2));
            $sheet->setCellValue("E{$row}", $genre['total_stock']);
            $row++;
        }

        $sheet->getStyle("A16:E" . ($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
    }

    // Auto-size columns
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Inventory sheet creation
function createInventorySheet($spreadsheet, $books) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('📋 Complete Inventory');

    // Headers
    $headers = [
        'ID', 'Title', 'Author', 'ISBN', 'Genre', 'Condition', 'Price (RM)', 
        'Cost (RM)', 'Stock', 'Profit Margin (%)', 'Rating', 'Reviews', 
        'Total Sold', 'Added Date', 'Last Updated', 'Description'
    ];

    // Header styling
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '667eea']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
        $col++;
    }

    // Data rows
    $row = 2;
    foreach ($books as $book) {
        $sheet->setCellValue('A' . $row, $book['book_id']);
        $sheet->setCellValue('B' . $row, $book['title']);
        $sheet->setCellValue('C' . $row, $book['author']);
        $sheet->setCellValue('D' . $row, $book['isbn'] ?? 'Not specified');
        $sheet->setCellValue('E' . $row, $book['genre'] ?? 'Not categorized');
        $sheet->setCellValue('F' . $row, ucwords(str_replace('_', ' ', $book['condition_type'])));
        $sheet->setCellValue('G' . $row, (float)$book['price']);
        $sheet->setCellValue('H' . $row, (float)$book['cost_price']);
        $sheet->setCellValue('I' . $row, $book['stock_quantity']);
        $sheet->setCellValue('J' . $row, (float)$book['profit_margin']);
        $sheet->setCellValue('K' . $row, number_format($book['avg_rating'], 1));
        $sheet->setCellValue('L' . $row, $book['review_count']);
        $sheet->setCellValue('M' . $row, $book['total_sold']);
        $sheet->setCellValue('N' . $row, date('Y-m-d', strtotime($book['created_at'])));
        $sheet->setCellValue('O' . $row, date('Y-m-d', strtotime($book['updated_at'])));
        $sheet->setCellValue('P' . $row, substr($book['description'] ?? '', 0, 500));

        // Conditional formatting for condition
        $conditionColors = [
            'new' => 'c8e6c9',
            'like_new' => 'dcedc8',
            'very_good' => 'bbdefb',
            'good' => 'fff3e0',
            'acceptable' => 'ffcdd2'
        ];
        
        if (isset($conditionColors[$book['condition_type']])) {
            $sheet->getStyle('F' . $row)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $conditionColors[$book['condition_type']]]]
            ]);
        }

        // Price formatting
        $sheet->getStyle('G' . $row . ':H' . $row)->getNumberFormat()->setFormatCode('_("RM"* #,##0.00_);_("RM"* \(#,##0.00\);_("RM"* "-"??_);_(@_)');
        
        // Percentage formatting
        $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode('0.00%');

        $row++;
    }

    // Apply borders to data
    $sheet->getStyle('A1:P' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // Auto-size columns
    foreach (range('A', 'P') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Freeze first row
    $sheet->freezePane('A2');
}

// Analytics sheet creation
function createAnalyticsSheet($spreadsheet, $stats) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('📈 Analytics');

    // Title
    $sheet->setCellValue('A1', 'Inventory Analytics & Insights');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->mergeCells('A1:E1');

    // Condition analysis
    $sheet->setCellValue('A3', 'Book Condition Analysis');
    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(14);

    $conditionHeaders = ['Condition', 'Count', 'Percentage', 'Avg Price', 'Total Stock'];
    $col = 'A';
    foreach ($conditionHeaders as $header) {
        $sheet->setCellValue($col . '4', $header);
        $sheet->getStyle($col . '4')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '764ba2']],
            'font' => ['color' => ['rgb' => 'FFFFFF']]
        ]);
        $col++;
    }

    $row = 5;
    $totalBooks = $stats['basic']['total_books'];
    foreach ($stats['conditions'] as $condition) {
        $percentage = $totalBooks > 0 ? ($condition['count'] / $totalBooks) * 100 : 0;
        
        $sheet->setCellValue("A{$row}", ucwords(str_replace('_', ' ', $condition['condition_type'])));
        $sheet->setCellValue("B{$row}", $condition['count']);
        $sheet->setCellValue("C{$row}", $percentage / 100);
        $sheet->setCellValue("D{$row}", (float)$condition['avg_price']);
        $sheet->setCellValue("E{$row}", $condition['total_stock']);
        
        // Format percentage
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('_("RM"* #,##0.00_);_("RM"* \(#,##0.00\);_("RM"* "-"??_);_(@_)');
        
        $row++;
    }

    $sheet->getStyle("A4:E" . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // Price range analysis
    $sheet->setCellValue('A' . ($row + 2), 'Price Range Analysis');
    $sheet->getStyle('A' . ($row + 2))->getFont()->setBold(true)->setSize(14);

    $priceHeaders = ['Price Range', 'Book Count', 'Percentage', 'Avg Price'];
    $startRow = $row + 3;
    $col = 'A';
    foreach ($priceHeaders as $header) {
        $sheet->setCellValue($col . $startRow, $header);
        $sheet->getStyle($col . $startRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '667eea']],
            'font' => ['color' => ['rgb' => 'FFFFFF']]
        ]);
        $col++;
    }

    $row = $startRow + 1;
    foreach ($stats['price_ranges'] as $range) {
        $percentage = $totalBooks > 0 ? ($range['count'] / $totalBooks) * 100 : 0;
        
        $sheet->setCellValue("A{$row}", $range['label']);
        $sheet->setCellValue("B{$row}", $range['count']);
        $sheet->setCellValue("C{$row}", $percentage / 100);
        $sheet->setCellValue("D{$row}", (float)$range['avg_price']);
        
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('_("RM"* #,##0.00_);_("RM"* \(#,##0.00\);_("RM"* "-"??_);_(@_)');
        
        $row++;
    }

    $sheet->getStyle("A{$startRow}:D" . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Genre analysis sheet
function createGenreAnalysisSheet($spreadsheet, $stats) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('📚 Genre Analysis');

    if (empty($stats['genres'])) {
        $sheet->setCellValue('A1', 'No genre data available');
        return;
    }

    $sheet->setCellValue('A1', 'Genre Performance Analysis');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->mergeCells('A1:F1');

    $headers = ['Rank', 'Genre', 'Books', 'Total Value', 'Avg Price', 'Stock Units', 'Market Share'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '3', $header);
        $sheet->getStyle($col . '3')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '667eea']],
            'font' => ['color' => ['rgb' => 'FFFFFF']]
        ]);
        $col++;
    }

    $row = 4;
    $rank = 1;
    $totalBooks = $stats['basic']['total_books'];
    
    foreach ($stats['genres'] as $genre) {
        $marketShare = $totalBooks > 0 ? ($genre['count'] / $totalBooks) * 100 : 0;
        
        $sheet->setCellValue("A{$row}", $rank);
        $sheet->setCellValue("B{$row}", $genre['genre']);
        $sheet->setCellValue("C{$row}", $genre['count']);
        $sheet->setCellValue("D{$row}", (float)$genre['value']);
        $sheet->setCellValue("E{$row}", (float)$genre['avg_price']);
        $sheet->setCellValue("F{$row}", $genre['total_stock']);
        $sheet->setCellValue("G{$row}", $marketShare / 100);

        // Format currency and percentage
        $sheet->getStyle("D{$row}:E{$row}")->getNumberFormat()->setFormatCode('_("RM"* #,##0.00_);_("RM"* \(#,##0.00\);_("RM"* "-"??_);_(@_)');
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('0.00%');

        // Color coding for top performers
        if ($rank <= 3) {
            $colors = ['ffd700', 'c0c0c0', 'cd7f32']; // Gold, Silver, Bronze
            $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[$rank - 1]]]
            ]);
        }

        $rank++;
        $row++;
    }

    $sheet->getStyle("A3:G" . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // Auto-size columns
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Monthly trends sheet
function createMonthlyTrendsSheet($spreadsheet, $stats) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('📅 Monthly Trends');

    if (empty($stats['monthly'])) {
        $sheet->setCellValue('A1', 'No monthly data available');
        return;
    }

    $sheet->setCellValue('A1', 'Monthly Performance Trends');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->mergeCells('A1:E1');

    $headers = ['Month', 'Books Added', 'Average Price', 'Total Value', 'Growth Rate'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '3', $header);
        $sheet->getStyle($col . '3')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '764ba2']],
            'font' => ['color' => ['rgb' => 'FFFFFF']]
        ]);
        $col++;
    }

    $row = 4;
    $previousCount = null;
    
    foreach (array_reverse($stats['monthly']) as $month) {
        $growthRate = $previousCount ? (($month['books_added'] - $previousCount) / $previousCount) * 100 : 0;
        
        $sheet->setCellValue("A{$row}", date('F Y', strtotime($month['month'] . '-01')));
        $sheet->setCellValue("B{$row}", $month['books_added']);
        $sheet->setCellValue("C{$row}", (float)$month['avg_price']);
        $sheet->setCellValue("D{$row}", (float)$month['total_value']);
        $sheet->setCellValue("E{$row}", $growthRate / 100);

        // Format currency and percentage
        $sheet->getStyle("C{$row}:D{$row}")->getNumberFormat()->setFormatCode('_("RM"* #,##0.00_);_("RM"* \(#,##0.00\);_("RM"* "-"??_);_(@_)');
        $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('0.00%');

        if ($growthRate > 0) {
            $sheet->getStyle("E{$row}")->getFont()->getColor()->setRGB('28a745');
        } elseif ($growthRate < 0) {
            $sheet->getStyle("E{$row}")->getFont()->getColor()->setRGB('dc3545');
        }

        $previousCount = $month['books_added'];
        $row++;
    }

    $sheet->getStyle("A3:E" . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Legacy Excel generation (fallback)
function generateLegacyExcel($conn, $seller_id, $sellerName, $businessName) {
    // Get current date for filename
    $date = date('Y-m-d');
    $filename = "BookStore_Inventory_Report_{$date}.xls";

    // Set headers for Excel download
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Get enhanced filter parameters (same as above)
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
    $condition = isset($_GET['condition']) ? trim($_GET['condition']) : '';

    // Build query
    $sql = "SELECT book_id, title, author, isbn, genre, condition_type, price, cost_price, 
                   stock_quantity, description, created_at, updated_at
            FROM seller_books WHERE seller_id = ?";

    $params = [$seller_id];
    $types = "i";

    // Apply basic filters
    if (!empty($search)) {
        $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        $types .= "sss";
    }

    if (!empty($genre)) {
        $sql .= " AND genre = ?";
        $params[] = $genre;
        $types .= "s";
    }

    if (!empty($condition)) {
        $sql .= " AND condition_type = ?";
        $params[] = $condition;
        $types .= "s";
    }

    $sql .= " ORDER BY created_at DESC";

    $query = $conn->prepare($sql);
    $query->bind_param($types, ...$params);
    $query->execute();
    $result = $query->get_result();

    // Enhanced XML Excel format
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">\n";
    
    // Styles
    echo "<Styles>\n";
    echo "<Style ss:ID=\"header\">\n";
    echo "  <Font ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/>\n";
    echo "  <Interior ss:Color=\"#667eea\" ss:Pattern=\"Solid\"/>\n";
    echo "  <Alignment ss:Horizontal=\"Center\"/>\n";
    echo "</Style>\n";
    echo "</Styles>\n";

    // Main inventory sheet
    echo "<Worksheet ss:Name=\"Book Inventory\">\n";
    echo "<Table>\n";

    // Enhanced header row
    echo "<Row ss:StyleID=\"header\">\n";
    $headers = ['ID', 'Title', 'Author', 'ISBN', 'Genre', 'Condition', 'Price (RM)', 'Cost (RM)', 'Stock', 'Profit Margin (%)', 'Added Date', 'Description'];
    foreach ($headers as $header) {
        echo "  <Cell><Data ss:Type=\"String\">{$header}</Data></Cell>\n";
    }
    echo "</Row>\n";

    // Data rows with enhanced information
    while ($row = $result->fetch_assoc()) {
        $profitMargin = $row['cost_price'] > 0 ? round((($row['price'] - $row['cost_price']) / $row['cost_price']) * 100, 2) : 0;
        
        echo "<Row>\n";
        echo "  <Cell><Data ss:Type=\"Number\">" . $row['book_id'] . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"String\">" . htmlspecialchars($row['title']) . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"String\">" . htmlspecialchars($row['author']) . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"String\">" . htmlspecialchars($row['isbn'] ?: 'Not specified') . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"String\">" . htmlspecialchars($row['genre'] ?: 'Not categorized') . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"String\">" . htmlspecialchars(ucwords(str_replace('_', ' ', $row['condition_type']))) . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"Number\">" . number_format($row['price'], 2) . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"Number\">" . number_format($row['cost_price'], 2) . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"Number\">" . $row['stock_quantity'] . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"Number\">" . $profitMargin . "</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"String\">" . date('Y-m-d', strtotime($row['created_at'])) . "</Data></Cell>\n";
        
        $description = $row['description'] ?: 'No description';
        if (strlen($description) > 500) {
            $description = substr($description, 0, 500) . '...';
        }
        echo "  <Cell><Data ss:Type=\"String\">" . htmlspecialchars($description) . "</Data></Cell>\n";
        echo "</Row>\n";
    }

    echo "</Table>\n";
    echo "</Worksheet>\n";

    // Summary sheet
    echo "<Worksheet ss:Name=\"Summary\">\n";
    echo "<Table>\n";
    
    echo "<Row ss:StyleID=\"header\">\n";
    echo "  <Cell><Data ss:Type=\"String\">Report Summary</Data></Cell>\n";
    echo "  <Cell><Data ss:Type=\"String\">Value</Data></Cell>\n";
    echo "</Row>\n";

    // Get summary statistics
    $summaryQuery = $conn->prepare("SELECT COUNT(*) as total_books, 
                                           SUM(price) as total_value, 
                                           AVG(price) as avg_price,
                                           SUM(stock_quantity) as total_stock
                                    FROM seller_books WHERE seller_id = ?");
    $summaryQuery->bind_param("i", $seller_id);
    $summaryQuery->execute();
    $summary = $summaryQuery->get_result()->fetch_assoc();

    $summaryData = [
        ['Export Date', date('Y-m-d H:i:s')],
        ['Seller Name', $sellerName],
        ['Business Name', $businessName ?: 'Not specified'],
        ['Total Books', number_format($summary['total_books'])],
        ['Total Inventory Value', 'RM ' . number_format($summary['total_value'], 2)],
        ['Average Book Price', 'RM ' . number_format($summary['avg_price'], 2)],
        ['Total Stock Units', number_format($summary['total_stock'])]
    ];

    foreach ($summaryData as $data) {
        echo "<Row>\n";
        echo "  <Cell><Data ss:Type=\"String\">{$data[0]}</Data></Cell>\n";
        echo "  <Cell><Data ss:Type=\"String\">{$data[1]}</Data></Cell>\n";
        echo "</Row>\n";
    }

    echo "</Table>\n";
    echo "</Worksheet>\n";
    echo "</Workbook>\n";

    $query->close();
    $summaryQuery->close();
}

// Error page function
function showErrorPage($title, $message) {
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
                            <i class="bi bi-file-earmark-excel" style="font-size: 5rem; color: #dc3545; margin-bottom: 2rem;"></i>
                            <h4 class="mb-4">Export Failed</h4>
                            <p class="mb-4 lead"><?= htmlspecialchars($message) ?></p>
                            
                            <div class="alert alert-info text-start">
                                <h6><i class="bi bi-info-circle me-2"></i>For System Administrators:</h6>
                                <p class="mb-2">Install PhpSpreadsheet using Composer for advanced Excel features:</p>
                                <pre class="bg-light p-3 rounded"><code>composer require phpoffice/phpspreadsheet</code></pre>
                                <p class="mb-0 small">The system will fallback to basic XML Excel format if PhpSpreadsheet is unavailable.</p>
                            </div>
                            
                            <div class="d-flex justify-content-center gap-3 mt-4">
                                <a href="seller_manage_books.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i>Return to Books
                                </a>
                                <a href="export_books_pdf.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="btn btn-danger btn-lg">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>Export as PDF
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