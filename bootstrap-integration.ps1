# Bootstrap Integration Script
# This script adds Bootstrap 5.3.0 to all remaining PHP files

# Files to update with Bootstrap
$files = @(
    "advanced-search.php",
    "customer-feedback.php", 
    "delivery-history.php",
    "courier-profile.php",
    "settings.php"
)

foreach ($file in $files) {
    $filePath = "c:\xamppp\htdocs\LProject\$file"
    if (Test-Path $filePath) {
        Write-Host "Processing $file..."
        
        # Read file content
        $content = Get-Content $filePath -Raw
        
        # Add Bootstrap CSS after Font Awesome
        $content = $content -replace 
            'href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">',
            'href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap 5.3.0 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        
        # Replace old CSS with new CSS
        $content = $content -replace 'css/sidebar.css', 'css/bootstrap-sidebar.css'
        
        # Add Bootstrap JavaScript before closing body tag
        $content = $content -replace 
            '</body>',
            '    <!-- Bootstrap 5.3.0 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>'
        
        # Write updated content back
        Set-Content $filePath $content -NoNewline
        Write-Host "Updated $file successfully"
    } else {
        Write-Host "File $file not found"
    }
}

Write-Host "Bootstrap integration completed!"
