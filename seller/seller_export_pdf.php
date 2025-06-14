<?php

require_once 'fpdf.php';
require_once __DIR__ . '/includes/seller_db.php';
session_start();

if (!isset($_SESSION['seller_id'])) {
    die("Unauthorized access");
}

$seller_id = $_SESSION['seller_id'];
$seller_name = $_SESSION['seller_name'] ?? 'Unknown Seller';

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

// Get books data with more comprehensive information
$books_query = "SELECT b.title, b.author, b.genre, b.price, b.cost_price, b.stock_quantity, 
                       b.condition_type, b.isbn, b.created_at, b.updated_at,
                       COALESCE(SUM(o.quantity), 0) as total_sold,
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

// Get summary statistics
$stats_query = "SELECT 
                    COUNT(*) as total_books,
                    SUM(stock_quantity) as total_stock,
                    AVG(price) as avg_price,
                    SUM(COALESCE(cost_price, 0)) as total_investment,
                    SUM(price * stock_quantity) as inventory_value
                FROM seller_books 
                WHERE seller_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get sales statistics
$sales_query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(quantity) as total_books_sold,
                    SUM(total_amount) as total_revenue
                FROM seller_orders 
                WHERE seller_id = ?";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$sales_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Enhanced PDF Class with additional methods
class BookStorePDF extends FPDF
{
    private $seller_name;
    private $report_date;
    
    function __construct($seller_name = '')
    {
        parent::__construct();
        $this->seller_name = $seller_name;
        $this->report_date = date('F j, Y');
    }
    
    // Header
    function Header()
    {
        // BookStore Logo/Title
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(102, 126, 234); // Brand color
        $this->Cell(0, 15, 'BookStore Seller Report', 0, 1, 'C');
        
        // Subtitle
        $this->SetFont('Arial', '', 12);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 8, 'Professional Book Inventory & Sales Analysis', 0, 1, 'C');
        
        // Line break
        $this->Ln(5);
        
        // Horizontal line
        $this->SetDrawColor(102, 126, 234);
        $this->SetLineWidth(0.5);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(10);
    }
    
    // Footer
    function Footer()
    {
        $this->SetY(-20);
        
        // Horizontal line
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        
        $this->Ln(3);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(120, 120, 120);
        
        // Left: Generation info
        $this->Cell(0, 5, 'Generated on ' . date('F j, Y \a\t g:i A') . ' | BookStore Seller Hub', 0, 0, 'L');
        
        // Right: Page number
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 1, 'R');
    }
    
    // Section header
    function SectionHeader($title, $icon = '')
    {
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(102, 126, 234);
        $this->SetFillColor(240, 243, 255);
        $this->Cell(0, 12, $icon . ' ' . $title, 0, 1, 'L', true);
        $this->Ln(3);
    }
    
    // Info box for key statistics
    function InfoBox($label, $value, $x, $y, $width = 45, $height = 25)
    {
        $this->SetXY($x, $y);
        
        // Box background
        $this->SetFillColor(248, 250, 255);
        $this->SetDrawColor(102, 126, 234);
        $this->SetLineWidth(0.3);
        $this->Rect($x, $y, $width, $height, 'DF');
        
        // Label
        $this->SetXY($x + 2, $y + 3);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell($width - 4, 5, $label, 0, 1, 'C');
        
        // Value
        $this->SetX($x + 2);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(102, 126, 234);
        $this->Cell($width - 4, 8, $value, 0, 1, 'C');
    }
    
    // Multi-cell with word wrap for long text
    function MultiCellText($w, $h, $txt, $border = 0, $align = 'L', $fill = false, $max_lines = 2)
    {
        $text = $this->truncateText($txt, 30); // Limit text length
        $this->MultiCell($w, $h, $text, $border, $align, $fill);
    }
    
    // Truncate text to fit in cell
    function truncateText($text, $max_length)
    {
        if (strlen($text) > $max_length) {
            return substr($text, 0, $max_length - 3) . '...';
        }
        return $text;
    }
    
    // Add colored cell
    function ColoredCell($w, $h, $txt, $border = 0, $ln = 0, $align = 'L', $fill_color = null)
    {
        if ($fill_color) {
            $this->SetFillColor($fill_color[0], $fill_color[1], $fill_color[2]);
            $this->Cell($w, $h, $txt, $border, $ln, $align, true);
        } else {
            $this->Cell($w, $h, $txt, $border, $ln, $align);
        }
    }
}

// Create PDF instance
$pdf = new BookStorePDF($seller_name);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 25);

// ===== SELLER INFORMATION SECTION =====
$pdf->SectionHeader('Seller Information', '👤');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(60, 60, 60);

// Seller details in two columns
$pdf->Cell(25, 8, 'Name:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(70, 8, $seller_info['seller_name'] ?? 'N/A', 0, 0, 'L');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(25, 8, 'Email:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(70, 8, $seller_info['seller_email'] ?? 'N/A', 0, 1, 'L');

if (!empty($seller_info['business_name'])) {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(25, 8, 'Business:', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 8, $seller_info['business_name'], 0, 1, 'L');
}

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(25, 8, 'Member Since:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 10);
$member_since = !empty($seller_info['registration_date']) ? 
    date('F j, Y', strtotime($seller_info['registration_date'])) : 'N/A';
$pdf->Cell(70, 8, $member_since, 0, 1, 'L');

// ===== SUMMARY STATISTICS SECTION =====
$pdf->SectionHeader('Summary Statistics', '📊');

$y_position = $pdf->GetY();

// Create info boxes for key statistics
$pdf->InfoBox('Total Books', number_format($stats['total_books'] ?? 0), 20, $y_position);
$pdf->InfoBox('Total Stock', number_format($stats['total_stock'] ?? 0), 70, $y_position);
$pdf->InfoBox('Avg Price', 'RM ' . number_format($stats['avg_price'] ?? 0, 2), 120, $y_position);

$y_position += 30;

$pdf->InfoBox('Investment', 'RM ' . number_format($stats['total_investment'] ?? 0, 2), 20, $y_position);
$pdf->InfoBox('Inventory Value', 'RM ' . number_format($stats['inventory_value'] ?? 0, 2), 70, $y_position);
$pdf->InfoBox('Orders', number_format($sales_stats['total_orders'] ?? 0), 120, $y_position);

$pdf->SetY($y_position + 35);

// ===== SALES PERFORMANCE SECTION =====
$pdf->SectionHeader('Sales Performance', '💰');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(60, 60, 60);

// Sales metrics in a structured format
$sales_data = [
    ['Metric', 'Value'],
    ['Total Orders Processed', number_format($sales_stats['total_orders'] ?? 0)],
    ['Total Books Sold', number_format($sales_stats['total_books_sold'] ?? 0)],
    ['Total Revenue Generated', 'RM ' . number_format($sales_stats['total_revenue'] ?? 0, 2)],
    ['Average Order Value', $sales_stats['total_orders'] > 0 ? 
        'RM ' . number_format(($sales_stats['total_revenue'] ?? 0) / $sales_stats['total_orders'], 2) : 'RM 0.00']
];

// Table styling
$pdf->SetFillColor(240, 243, 255);
$pdf->SetDrawColor(102, 126, 234);
$pdf->SetLineWidth(0.3);

foreach ($sales_data as $index => $row) {
    if ($index === 0) {
        // Header row
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(102, 126, 234);
        $fill = true;
    } else {
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        $fill = ($index % 2 == 0);
    }
    
    $pdf->Cell(90, 8, $row[0], 1, 0, 'L', $fill);
    $pdf->Cell(90, 8, $row[1], 1, 1, 'R', $fill);
}

// ===== BOOK INVENTORY SECTION =====
$pdf->Ln(10);
$pdf->SectionHeader('Book Inventory Details', '📚');

// Check if we need a new page for the table
if ($pdf->GetY() > 200) {
    $pdf->AddPage();
}

// Table headers
$headers = ['Title', 'Author', 'Genre', 'Price', 'Stock', 'Condition', 'Added'];
$widths = [35, 30, 25, 20, 15, 25, 25];

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFillColor(102, 126, 234);
$pdf->SetDrawColor(102, 126, 234);

// Header row
foreach ($headers as $index => $header) {
    $pdf->Cell($widths[$index], 10, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Data rows
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(60, 60, 60);
$row_count = 0;

while ($book = $books_result->fetch_assoc()) {
    // Alternate row colors
    $fill = ($row_count % 2 == 0);
    $fill_color = $fill ? [248, 250, 255] : [255, 255, 255];
    
    // Check if we need a new page
    if ($pdf->GetY() > 270) {
        $pdf->AddPage();
        
        // Repeat headers on new page
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(102, 126, 234);
        
        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 10, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(60, 60, 60);
    }
    
    // Set fill color for this row
    if ($fill) {
        $pdf->SetFillColor($fill_color[0], $fill_color[1], $fill_color[2]);
    }
    
    // Data cells
    $pdf->Cell($widths[0], 8, $pdf->truncateText($book['title'], 20), 1, 0, 'L', $fill);
    $pdf->Cell($widths[1], 8, $pdf->truncateText($book['author'], 18), 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 8, $pdf->truncateText($book['genre'] ?? 'N/A', 15), 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 8, 'RM ' . number_format($book['price'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($widths[4], 8, $book['stock_quantity'], 1, 0, 'C', $fill);
    $pdf->Cell($widths[5], 8, ucfirst($book['condition_type'] ?? 'N/A'), 1, 0, 'C', $fill);
    $pdf->Cell($widths[6], 8, date('M j, Y', strtotime($book['created_at'])), 1, 1, 'C', $fill);
    
    $row_count++;
}

// If no books found
if ($row_count === 0) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 20, 'No books found in your inventory.', 0, 1, 'C');
}

// ===== ADDITIONAL INSIGHTS SECTION =====
$pdf->Ln(10);
$pdf->SectionHeader('Business Insights', '🔍');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(60, 60, 60);

// Calculate and display insights
$insights = [];

if ($stats['total_books'] > 0) {
    $avg_stock_per_book = $stats['total_stock'] / $stats['total_books'];
    $insights[] = '• Average stock per book: ' . number_format($avg_stock_per_book, 1) . ' copies';
}

if ($stats['total_investment'] > 0 && $stats['inventory_value'] > 0) {
    $potential_profit_margin = (($stats['inventory_value'] - $stats['total_investment']) / $stats['total_investment']) * 100;
    $insights[] = '• Potential profit margin: ' . number_format($potential_profit_margin, 1) . '%';
}

if ($sales_stats['total_books_sold'] > 0 && $stats['total_books'] > 0) {
    $sell_through_rate = ($sales_stats['total_books_sold'] / ($stats['total_stock'] + $sales_stats['total_books_sold'])) * 100;
    $insights[] = '• Historical sell-through rate: ' . number_format($sell_through_rate, 1) . '%';
}

$insights[] = '• Report generated: ' . date('F j, Y \a\t g:i A');
$insights[] = '• Active seller since: ' . $member_since;

foreach ($insights as $insight) {
    $pdf->Cell(0, 6, $insight, 0, 1, 'L');
}

// ===== FOOTER NOTE =====
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(120, 120, 120);
$pdf->MultiCell(0, 5, 'This report contains confidential business information. Please handle with care and do not share with unauthorized parties. For support or questions about your seller account, please contact BookStore Support.', 0, 'C');

// Output the PDF
$filename = 'BookStore_Seller_Report_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output('D', $filename); // 'D' forces download
exit;
?>