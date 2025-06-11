<?php
// Simple database setup and test account creation
require_once 'db_connect.php';

try {
    // Create tables
    $sql = "
    DROP TABLE IF EXISTS delivery_updates;
    DROP TABLE IF EXISTS deliveries;
    DROP TABLE IF EXISTS courier_logins;
    DROP TABLE IF EXISTS customers;
    DROP TABLE IF EXISTS couriers;
    
    CREATE TABLE couriers (
        courier_id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        vehicle_number VARCHAR(20),
        profile_image VARCHAR(255),
        avg_rating DECIMAL(3,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    
    CREATE TABLE courier_logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        courier_id VARCHAR(20),
        email VARCHAR(100),
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('success', 'failed') NOT NULL
    ) ENGINE=InnoDB;
    
    CREATE TABLE customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        address TEXT,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    
    CREATE TABLE deliveries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        courier_id VARCHAR(20),
        customer_id INT,
        order_id VARCHAR(50) NOT NULL,
        delivery_address TEXT NOT NULL,
        delivery_details TEXT,
        customer_info TEXT,
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        route_order INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (courier_id) REFERENCES couriers(courier_id) ON DELETE SET NULL,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        INDEX idx_route_order (route_order)
    ) ENGINE=InnoDB;
    
    CREATE TABLE delivery_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_id INT NOT NULL,
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL,
        notes TEXT,
        update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ";
    
    $conn->multi_query($sql);
    
    // Wait for all queries to complete
    while ($conn->next_result()) {;}    
    // Create test courier with hashed password
    $courier_id = 'COR001';
    $name = 'Test Courier';
    $email = 'test.courier@bookstore.com';
    $phone = '123-456-7890';
    $password = 'password123';
    $vehicle_number = 'VEH001';
    $avg_rating = 4.75;
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO couriers (courier_id, name, email, phone, password, vehicle_number, avg_rating) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssd", $courier_id, $name, $email, $phone, $hashed_password, $vehicle_number, $avg_rating);
    $stmt->execute();
    
    // Insert sample customers
    $customers = [
        ['John Doe', 'john@example.com', '123-456-7890', '123 Main St, City', password_hash('password123', PASSWORD_DEFAULT)],
        ['Jane Smith', 'jane@example.com', '098-765-4321', '456 Oak Ave, City', password_hash('password123', PASSWORD_DEFAULT)]
    ];
    
    $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address, password) VALUES (?, ?, ?, ?, ?)");
    foreach ($customers as $customer) {
        $stmt->bind_param("sssss", $customer[0], $customer[1], $customer[2], $customer[3], $customer[4]);
        $stmt->execute();
    }
    
    // Insert sample deliveries
    $deliveries = [
        ['COR001', 1, 'ORD001', '123 Main St, City', 'Package contains 3 books: Programming Guide, Web Development, Database Design', 'John Doe - 123-456-7890', 'pending'],
        ['COR001', 2, 'ORD002', '456 Oak Ave, City', 'Package contains 2 magazines and 1 book', 'Jane Smith - 098-765-4321', 'in_progress'],
        ['COR001', 1, 'ORD003', '789 Pine St, City', 'Express delivery - Academic textbooks', 'John Doe - 123-456-7890', 'completed']
    ];
    
    $stmt = $conn->prepare("INSERT INTO deliveries (courier_id, customer_id, order_id, delivery_address, delivery_details, customer_info, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($deliveries as $delivery) {
        $stmt->bind_param("sisssss", $delivery[0], $delivery[1], $delivery[2], $delivery[3], $delivery[4], $delivery[5], $delivery[6]);
        $stmt->execute();
    }
    
    echo "Database setup complete!<br>";
    echo "Test courier created:<br>";
    echo "Courier ID: $courier_id<br>";
    echo "Email: $email<br>";
    echo "Password: $password<br>";
    echo "<br>Sample deliveries created for testing dashboard.<br>";
    echo "<a href='courier-login.html'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>
