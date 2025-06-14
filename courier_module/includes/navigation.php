<?php
// Standard Navigation for Courier Dashboard
function renderCourierNavigation($activePage) {
    $navItems = [
        'courier-dashboard.php' => ['fas fa-home', 'Dashboard'],
        'active-deliveries.php' => ['fas fa-box', 'Active Deliveries'],
        'delivery-history.php' => ['fas fa-history', 'Delivery History'],
        'delivery-status-management.php' => ['fas fa-edit', 'Status & Cancel Management'],
        'customer-feedback.php' => ['fas fa-star', 'Customer Feedback'],
        'advanced-search.php' => ['fas fa-search', 'Advanced Search'],
        'courier-profile.php' => ['fas fa-user', 'Profile'],
        'settings.php' => ['fas fa-cog', 'Settings'],
        'logout.php' => ['fas fa-sign-out-alt', 'Logout']
    ];
    
    echo '<div class="sidebar">';
    echo '<div class="sidebar-header">';
    echo '<i class="fas fa-truck"></i>';
    echo '<h2>Courier Dashboard</h2>';
    echo '</div>';
    echo '<ul class="nav-links">';
    
    foreach ($navItems as $page => $data) {
        $iconClass = $data[0];
        $label = $data[1];
        $activeClass = ($activePage === $page) ? ' class="active"' : '';
        echo "<li><a href=\"{$page}\"{$activeClass}><i class=\"{$iconClass}\"></i> {$label}</a></li>";
    }
    
    echo '</ul>';
    echo '</div>';
}
?>
