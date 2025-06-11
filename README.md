# BookStore Courier System

## Setup Instructions

### 1. Database Setup
Run the following script to set up the database:
```
http://localhost/LProject/setup_test.php
```

This will create:
- `couriers` table with test account
- `customers` table with sample data
- `deliveries` table with sample deliveries
- `courier_logins` table (for login tracking)
- All related tables for full functionality

### 2. Test Accounts
**Courier Account:**
- **Courier ID**: COR001
- **Email**: test.courier@bookstore.com
- **Password**: password123

**Admin Account:**
- **Username**: admin
- **Password**: admin123

### 3. Login Process
1. Go to: `http://localhost/LProject/courier-login.html`
2. Enter the test credentials above
3. You'll be redirected to the full-featured courier dashboard

## Current Files Structure

### Essential Core Files
- `index.html` - Main landing page
- `select-role.html` - Role selection page
- `courier-login.html` - Courier login page
- `customer-login.html` - Customer login page
- `seller-login.html` - Seller login page
- `admin-login.html` - Admin login page
- `simple_courier_login.php` - Simplified login handler
- `admin_login_handler.php` - Admin login handler
- `db_connect.php` - Database connection
- `logout.php` - Session cleanup

### Courier Dashboard System
- `courier-dashboard.php` - Full-featured courier dashboard
- `courier-profile.php` - Courier profile management
- `active-deliveries.php` - Active deliveries view
- `delivery-history.php` - Delivery history
- `delivery_details.php` - Individual delivery details
- `route-planning.php` - Route planning interface
- `settings.php` - User settings
- `update_delivery_status.php` - Delivery status updates
- `update_route.php` - Route updates

### Admin Dashboard System
- `admin-dashboard.php` - Admin dashboard with system statistics

### Database & Setup
- `database_setup.sql` - Complete database schema
- `setup_test.php` - Quick setup script for testing

### Styling
- `css/sidebar.css` - Shared sidebar styling

## Notes
- ✅ **Complete**: Admin login system now implemented
- All files are interconnected and functional
- System includes full courier workflow with delivery tracking
- Admin dashboard provides system overview and statistics
## How to Use

1. Start XAMPP (Apache + MySQL)
2. Run setup_test.php once to initialize database with full sample data
3. Use courier-login.html to test login
4. Navigate through the full courier dashboard system

## Login Flow
```
courier-login.html → simple_courier_login.php → courier-dashboard.php
```

## Current Status
- ✅ All files are essential and interconnected
- ✅ Full courier workflow implemented
- ✅ Database setup with sample data
- ✅ Admin login system implemented
- ✅ Seller and customer login pages exist but backend not implemented

The system is clean and fully functional for courier operations.
