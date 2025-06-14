# BookStore - Seller Module

A comprehensive book selling platform focused on the seller interface and management system.

## Project Structure

```
BookStore/
├── database/
│   ├── bookstore.sql      # Complete database schema and sample data
│   ├── config.php         # Database configuration
│   └── install.php        # Database installation script
├── seller/
│   ├── includes/          # Common includes and utilities
│   ├── uploads/           # Book cover images and uploads
│   ├── seller_login.php   # Seller authentication
│   ├── seller_dashboard.php # Main dashboard
│   ├── seller_add_book.php  # Add new books
│   ├── seller_manage_books.php # Book management
│   ├── seller_settings.php    # Account settings
│   └── ...               # Other seller functionality
├── index.html            # Main landing page
└── select-role.html      # Role selection page

```

## Installation

1. **Setup XAMPP/WAMP**: Make sure Apache and MySQL are running
2. **Clone/Copy Project**: Place this folder in your `htdocs` directory
3. **Install Database**: 
   - Visit: `http://localhost/BookStore/database/install.php`
   - Or manually import `database/bookstore.sql` into phpMyAdmin
4. **Access the Application**:
   - Main site: `http://localhost/BookStore/`
   - Seller login: `http://localhost/BookStore/seller/seller_login.php`

## Default Login Credentials

**Seller Accounts:**
- Email: `seller1@bookstore.com` | Password: `password123`
- Email: `seller2@bookstore.com` | Password: `password123`

## Features

### Seller Features
- ✅ Secure login and registration
- ✅ Dashboard with sales analytics
- ✅ Book inventory management (Add, Edit, Delete)
- ✅ Book visibility and featured toggles
- ✅ Profile photo upload
- ✅ Account settings and preferences
- ✅ Activity logging
- ✅ Export data to Excel/PDF
- ✅ Search and filter books
- ✅ Responsive design

## Database

The system uses MySQL with the following main tables:
- `seller_users` - Seller account information
- `seller_books` - Book inventory
- `seller_activity_log` - User activity tracking
- `seller_sessions` - Session management
- `book_images` - Book cover images
- Plus supporting tables for customers, orders, reviews, etc.

## Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.3
- **Icons**: Bootstrap Icons, Font Awesome

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Security Features

- Password hashing with bcrypt
- Session management
- SQL injection prevention
- XSS protection
- CSRF protection
- Input validation and sanitization

## Performance Features

- Optimized database queries
- Image optimization
- Caching strategies
- Responsive images
- Minified assets

---

## Troubleshooting

**Database Connection Issues:**
1. Check MySQL is running in XAMPP
2. Verify database credentials in `database/config.php`
3. Ensure `bookstore` database exists

**Permission Issues:**
1. Check folder permissions for `uploads/` directory
2. Ensure PHP has write access to session directory

**Login Issues:**
1. Clear browser cache and cookies
2. Check if database tables are properly installed
3. Verify PHP session configuration

---

**Last Updated**: June 15, 2025
**Version**: 1.0
