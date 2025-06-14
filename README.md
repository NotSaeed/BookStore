# 📚 BookStore Seller Hub

A comprehensive web-based book selling platform built with PHP, MySQL, and Bootstrap framework.

## 🚀 Project Overview

BookStore Seller Hub is a professional book management system that allows sellers to:
- Manage their book inventory
- Track sales and profits
- Upload and organize book information
- Export data to Excel/PDF formats
- Secure authentication system

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5.3
- **Backend**: PHP 8.x
- **Database**: MySQL 8.x
- **Server**: Apache (XAMPP)
- **Charts**: Chart.js
- **Icons**: Bootstrap Icons, Font Awesome

## 📋 Features

### 🔐 Authentication System
- Secure user registration and login
- Password hashing with bcrypt
- Session management
- Password reset functionality
- Activity logging

### 📖 Book Management
- Add new books with cover images
- Edit existing book details
- Delete books with confirmation
- Book visibility toggle (public/private)
- Advanced search and filtering
- Bulk operations

### 📊 Dashboard & Analytics
- Financial overview and statistics
- Interactive charts and graphs
- Book status distribution
- Price range analysis
- Recent activity tracking

### 📤 Export Features
- Export book data to Excel (CSV)
- Generate PDF reports
- Bulk data operations

### 🎨 User Interface
- Modern responsive design
- Glassmorphism effects
- Mobile-friendly interface
- Professional color scheme
- Intuitive navigation

## 🏗️ Project Structure

```
BookStore/
├── seller_module/
│   ├── seller/
│   │   ├── includes/
│   │   │   ├── seller_db.php          # Database connection
│   │   │   ├── seller_header.php      # Header component
│   │   │   └── seller_footer.php      # Footer component
│   │   ├── css/
│   │   │   └── bootstrap-enhanced.css # Custom styles
│   │   ├── uploads/                   # Book cover images
│   │   ├── seller_login.php           # Login page
│   │   ├── seller_register.php        # Registration page
│   │   ├── seller_dashboard.php       # Main dashboard
│   │   ├── seller_add_book.php        # Add new book
│   │   ├── seller_manage_books.php    # Book management
│   │   ├── seller_settings.php        # User settings
│   │   └── ...other seller pages
│   ├── database/
│   │   ├── schema.sql                 # Database structure
│   │   └── install_database.php       # Database installer
│   └── index.html                     # Landing page
└── README.md
```

## 🚀 Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser
- Git (for version control)

### Local Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/bookstore-seller-hub.git
   cd bookstore-seller-hub
   ```

2. **Move to XAMPP directory**
   ```bash
   # Windows
   copy -r . C:\xampp\htdocs\BookStore\
   
   # macOS/Linux
   cp -r . /Applications/XAMPP/htdocs/BookStore/
   ```

3. **Start XAMPP services**
   - Start Apache
   - Start MySQL

4. **Create database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create database named `bookstore`
   - Import `database/schema.sql`

5. **Configure database connection**
   ```php
   // Update seller_module/seller/includes/seller_db.php
   $host = 'localhost';
   $username = 'root';
   $password = '';
   $database = 'bookstore';
   ```

6. **Access the application**
   - Open: `http://localhost/BookStore/seller_module/`

## 🌐 Live Demo

**Live URL**: [Coming Soon - Will be hosted online]

## 👥 Team Members

- **[Team Member 1]** - Lead Developer & Database Design
- **[Team Member 2]** - Frontend Development & UI/UX
- **[Team Member 3]** - Backend Development & Authentication
- **[Team Member 4]** - Testing & Documentation

## 📱 Screenshots

### Dashboard
![Dashboard](screenshots/dashboard.png)

### Book Management
![Book Management](screenshots/manage-books.png)

### Add Book Form
![Add Book](screenshots/add-book.png)

## 🔧 Configuration

### Environment Variables
Create a `.env` file for sensitive configurations:
```env
DB_HOST=localhost
DB_NAME=bookstore
DB_USER=root
DB_PASS=
APP_ENV=development
```

### Security Settings
- All forms use CSRF protection
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- Secure password hashing
- Session security measures

## 📝 Database Schema

### Main Tables
- `seller_users` - User accounts and profiles
- `seller_books` - Book inventory and details
- `seller_activity_log` - Activity tracking

### Key Relationships
- Users → Books (One-to-Many)
- Users → Activity Logs (One-to-Many)

## 🧪 Testing

### Manual Testing Checklist
- [ ] User registration and login
- [ ] Password reset functionality
- [ ] Book CRUD operations
- [ ] File upload and validation
- [ ] Export functionality
- [ ] Responsive design
- [ ] Security measures

## 📊 Assignment Requirements Met

### ✅ Criteria 1: Web Application Development
- [x] User-friendly interface with Bootstrap
- [x] Secure login system with sessions
- [x] Database insert functionality
- [x] Data retrieval and display
- [x] Update existing data
- [x] Delete records
- [x] Search and sorting features
- [x] Bootstrap framework implementation

### ✅ Criteria 2: Data Validation & Hosting
- [x] JavaScript validation
- [x] Web hosting (to be deployed)

## 🚀 Deployment

### Local Development
```bash
# Start XAMPP
# Access: http://localhost/BookStore/seller_module/
```

### Production Deployment
```bash
# Will be deployed to: [hosting platform]
# Live URL: [to be provided]
```

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## 📄 License

This project is created for academic purposes as part of [Course Code] assignment.

## 📞 Support

For questions and support:
- **Email**: [team-email@domain.com]
- **Project Issues**: [GitHub Issues URL]

## 🏆 Acknowledgments

- **Instructor**: [Instructor Name]
- **Course**: [Course Name and Code]
- **Institution**: [University/College Name]
- **Semester**: [Current Semester/Year]

---

**Built with ❤️ by [Team Name]**
