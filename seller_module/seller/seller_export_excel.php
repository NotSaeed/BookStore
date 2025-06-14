<?php

require_once __DIR__ . '/vendor/autoload.php'; // For PhpSpreadsheet
require_once __DIR__ . '/includes/seller_db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

session_start();

if (!isset($_SESSION['seller_id'])) {
    die("Unauthorized access");
}

$seller_id = $_SESSION['seller_id'];
$seller_name = $_SESSION['seller_name'] ?? 'Unknown Seller';

try {
    // Get seller information
    $seller_info = [];
    $stmt = $conn->prepare("SELECT seller_name, seller_email, business_name, business_address, registration_date FROM seller_users WHERE seller_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $seller_info = $result->fetch_assoc();
        $stmt->close();
    }

    // Get comprehensive book data
    $books_query = "SELECT 
                        b.book_id,
                        b.title, 
                        b.author, 
                        b.isbn,
                        b.genre, 
                        b.condition_type,
                        b.price, 
                        b.cost_price,
                        b.stock_quantity, 
                        b.description,
                        b.created_at, 
                        b.updated_at,
                        COALESCE(SUM(o.quantity), 0) as total_sold,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        (b.price - COALESCE(b.cost_price, 0)) as profit_per_unit,
                        (b.price - COALESCE(b.cost_price, 0)) * COALESCE(SUM(o.quantity), 0) as total_profit
                    FROM seller_books b 
                    LEFT JOIN seller_orders o ON b.book_id = o.book_id 
                    WHERE b.seller_id = ? 
                    GROUP BY b.book_id 
                    ORDER BY b.created_at DESC";

    $stmt = $conn->prepare($books_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $books_result = $stmt->get_result();
    $books_data = $books_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get summary statistics
    $stats_query = "SELECT 
                        COUNT(*) as total_books,
                        SUM(stock_quantity) as total_stock,
                        AVG(price) as avg_price,
                        MIN(price) as min_price,
                        MAX(price) as max_price,
                        SUM(COALESCE(cost_price, 0)) as total_investment,
                        SUM(price * stock_quantity) as inventory_value
                    FROM seller_books 
                    WHERE seller_id = ?";

    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get sales data
    $sales_query = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(quantity) as total_books_sold,
                        SUM(total_amount) as total_revenue,
                        AVG(total_amount) as avg_order_value,
                        MIN(order_date) as first_sale,
                        MAX(order_date) as last_sale
                    FROM seller_orders 
                    WHERE seller_id = ?";

    $stmt = $conn->prepare($sales_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $sales_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get genre breakdown
    $genre_query = "SELECT 
                        COALESCE(genre, 'Unspecified') as genre,
                        COUNT(*) as book_count,
                        SUM(stock_quantity) as total_stock,
                        AVG(price) as avg_price,
                        SUM(price * stock_quantity) as genre_value
                    FROM seller_books 
                    WHERE seller_id = ? 
                    GROUP BY COALESCE(genre, 'Unspecified')
                    ORDER BY book_count DESC";

    $stmt = $conn->prepare($genre_query);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $genre_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // ===== SHEET 1: OVERVIEW & SUMMARY =====
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Summary Dashboard');
    
    // Header styling
    $headerStyle = [
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667EEA']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];
    
    // Title
    $sheet1->setCellValue('A1', 'BookStore Seller Dashboard - ' . ($seller_info['seller_name'] ?? 'Unknown Seller'));
    $sheet1->mergeCells('A1:H1');
    $sheet1->getStyle('A1')->applyFromArray($headerStyle);
    $sheet1->getRowDimension(1)->setRowHeight(25);
    
    // Report info
    $sheet1->setCellValue('A3', 'Report Generated: ' . date('F j, Y \a\t g:i A'));
    $sheet1->setCellValue('A4', 'Seller Email: ' . ($seller_info['seller_email'] ?? 'N/A'));
    $sheet1->setCellValue('A5', 'Business Name: ' . ($seller_info['business_name'] ?? 'N/A'));
    $sheet1->setCellValue('A6', 'Member Since: ' . (isset($seller_info['registration_date']) ? date('F j, Y', strtotime($seller_info['registration_date'])) : 'N/A'));
    
    // Key Metrics Section
    $metricsStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F0F3FF']
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    
    $sheet1->setCellValue('A8', 'KEY BUSINESS METRICS');
    $sheet1->mergeCells('A8:H8');
    $sheet1->getStyle('A8')->applyFromArray($metricsStyle);
    
    // Metrics data
    $metrics = [
        ['Metric', 'Value', '', 'Metric', 'Value'],
        ['Total Books Listed', $stats['total_books'] ?? 0, '', 'Total Stock Quantity', $stats['total_stock'] ?? 0],
        ['Total Investment', 'RM ' . number_format($stats['total_investment'] ?? 0, 2), '', 'Inventory Value', 'RM ' . number_format($stats['inventory_value'] ?? 0, 2)],
        ['Average Book Price', 'RM ' . number_format($stats['avg_price'] ?? 0, 2), '', 'Price Range', 'RM ' . number_format($stats['min_price'] ?? 0, 2) . ' - RM ' . number_format($stats['max_price'] ?? 0, 2)],
        ['Total Orders', $sales_stats['total_orders'] ?? 0, '', 'Books Sold', $sales_stats['total_books_sold'] ?? 0],
        ['Total Revenue', 'RM ' . number_format($sales_stats['total_revenue'] ?? 0, 2), '', 'Avg Order Value', 'RM ' . number_format($sales_stats['avg_order_value'] ?? 0, 2)]
    ];
    
    $row = 10;
    foreach ($metrics as $metric) {
        $sheet1->fromArray($metric, null, 'A' . $row);
        if ($row == 10) { // Header row
            $sheet1->getStyle('A' . $row . ':E' . $row)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8EFFF']
                ]
            ]);
        }
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'H') as $col) {
        $sheet1->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ===== SHEET 2: COMPLETE BOOK INVENTORY =====
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Book Inventory');
    
    // Headers for book inventory
    $bookHeaders = [
        'Book ID', 'Title', 'Author', 'ISBN', 'Genre', 'Condition', 'Price (RM)', 
        'Cost Price (RM)', 'Stock Qty', 'Total Sold', 'Revenue (RM)', 
        'Profit/Unit (RM)', 'Total Profit (RM)', 'Added Date', 'Last Updated'
    ];
    
    $sheet2->fromArray($bookHeaders, null, 'A1');
    
    // Style headers
    $sheet2->getStyle('A1:O1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '667EEA']
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Add book data
    $row = 2;
    foreach ($books_data as $book) {
        $bookRow = [
            $book['book_id'],
            $book['title'],
            $book['author'],
            $book['isbn'] ?? 'N/A',
            $book['genre'] ?? 'Unspecified',
            ucfirst($book['condition_type'] ?? 'N/A'),
            $book['price'],
            $book['cost_price'] ?? 0,
            $book['stock_quantity'],
            $book['total_sold'],
            $book['total_revenue'],
            $book['profit_per_unit'],
            $book['total_profit'],
            date('Y-m-d', strtotime($book['created_at'])),
            $book['updated_at'] ? date('Y-m-d', strtotime($book['updated_at'])) : 'N/A'
        ];
        
        $sheet2->fromArray($bookRow, null, 'A' . $row);
        
        // Alternate row colors
        if ($row % 2 == 0) {
            $sheet2->getStyle('A' . $row . ':O' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFF']
                ]
            ]);
        }
        
        // Format currency columns
        $sheet2->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet2->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet2->getStyle('K' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet2->getStyle('L' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet2->getStyle('M' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'O') as $col) {
        $sheet2->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add borders to the table
    $sheet2->getStyle('A1:O' . ($row - 1))->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);
    
    // ===== SHEET 3: GENRE ANALYSIS =====
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('Genre Analysis');
    
    // Genre analysis header
    $sheet3->setCellValue('A1', 'Genre Performance Analysis');
    $sheet3->mergeCells('A1:E1');
    $sheet3->getStyle('A1')->applyFromArray($headerStyle);
    
    // Genre headers
    $genreHeaders = ['Genre', 'Books Count', 'Total Stock', 'Avg Price (RM)', 'Total Value (RM)'];
    $sheet3->fromArray($genreHeaders, null, 'A3');
    $sheet3->getStyle('A3:E3')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8EFFF']
        ]
    ]);
    
    // Add genre data
    $row = 4;
    foreach ($genre_data as $genre) {
        $genreRow = [
            $genre['genre'],
            $genre['book_count'],
            $genre['total_stock'],
            $genre['avg_price'],
            $genre['genre_value']
        ];
        
        $sheet3->fromArray($genreRow, null, 'A' . $row);
        
        // Format currency columns
        $sheet3->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet3->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet3->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Add borders
    $sheet3->getStyle('A3:E' . ($row - 1))->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);
    
    // ===== SHEET 4: SALES ANALYSIS =====
    if ($sales_stats['total_orders'] > 0) {
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Sales Analysis');
        
        // Sales analysis header
        $sheet4->setCellValue('A1', 'Sales Performance Analysis');
        $sheet4->mergeCells('A1:D1');
        $sheet4->getStyle('A1')->applyFromArray($headerStyle);
        
        // Sales summary
        $salesSummary = [
            ['Sales Metric', 'Value'],
            ['Total Orders Processed', $sales_stats['total_orders']],
            ['Total Books Sold', $sales_stats['total_books_sold']],
            ['Total Revenue Generated', 'RM ' . number_format($sales_stats['total_revenue'], 2)],
            ['Average Order Value', 'RM ' . number_format($sales_stats['avg_order_value'], 2)],
            ['First Sale Date', $sales_stats['first_sale'] ? date('F j, Y', strtotime($sales_stats['first_sale'])) : 'N/A'],
            ['Latest Sale Date', $sales_stats['last_sale'] ? date('F j, Y', strtotime($sales_stats['last_sale'])) : 'N/A']
        ];
        
        $row = 3;
        foreach ($salesSummary as $index => $sales) {
            $sheet4->fromArray($sales, null, 'A' . $row);
            if ($index == 0) { // Header row
                $sheet4->getStyle('A' . $row . ':B' . $row)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E8EFFF']
                    ]
                ]);
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $sheet4->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    // Set active sheet back to first sheet
    $spreadsheet->setActiveSheetIndex(0);
    
    // Create Excel writer
    $writer = new Xlsx($spreadsheet);
    
    // Set headers for download
    $filename = 'BookStore_Seller_Data_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1'); // IE9
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // Always modified
    header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
    header('Pragma: public'); // HTTP/1.0
    
    // Save to output
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Error handling
    die('Error generating Excel file: ' . $e->getMessage());
}
?>