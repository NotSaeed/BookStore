<?php
// Simple database setup and test account creation
require_once 'db_connect.php';

try {
    // Drop tables in correct order (child tables first, then parent tables)
    $sql = "
    SET FOREIGN_KEY_CHECKS = 0;
    DROP TABLE IF EXISTS customer_feedback;
    DROP TABLE IF EXISTS delivery_status_log;
    DROP TABLE IF EXISTS delivery_cancellations;
    DROP TABLE IF EXISTS courier_settings;
    DROP TABLE IF EXISTS delivery_updates;
    DROP TABLE IF EXISTS deliveries;
    DROP TABLE IF EXISTS courier_logins;
    DROP TABLE IF EXISTS customers;
    DROP TABLE IF EXISTS couriers;
    SET FOREIGN_KEY_CHECKS = 1;
      CREATE TABLE couriers (
        courier_id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        password VARCHAR(255) NOT NULL,
        vehicle_number VARCHAR(20),
        profile_image VARCHAR(255),
        avg_rating DECIMAL(3,2) DEFAULT 0.00,
        max_deliveries_per_day INT DEFAULT 15,
        preferred_delivery_radius INT DEFAULT 10,
        auto_accept_orders BOOLEAN DEFAULT FALSE,
        express_delivery_enabled BOOLEAN DEFAULT FALSE,
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
    
    -- Customer Feedback System (INSERT)
    CREATE TABLE customer_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_id INT NOT NULL,
        courier_id VARCHAR(20) NOT NULL,
        customer_rating INT CHECK (customer_rating BETWEEN 1 AND 5),
        customer_comment TEXT,
        delivery_experience ENUM('excellent', 'good', 'average', 'poor') DEFAULT 'good',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_delivery_feedback (delivery_id),
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
        FOREIGN KEY (courier_id) REFERENCES couriers(courier_id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    
    -- Delivery Status Change Log (UPDATE tracking)
    CREATE TABLE delivery_status_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_id INT NOT NULL,
        old_status VARCHAR(50),
        new_status VARCHAR(50),
        updated_by VARCHAR(50),
        update_reason TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    
    -- Delivery Cancellations (DELETE tracking)
    CREATE TABLE delivery_cancellations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_id INT NOT NULL,
        cancelled_by VARCHAR(50),
        cancellation_reason VARCHAR(255),
        cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    
    -- Courier Settings for notifications
    CREATE TABLE courier_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        courier_id VARCHAR(20) NOT NULL,
        email_notifications BOOLEAN DEFAULT TRUE,
        sms_notifications BOOLEAN DEFAULT TRUE,
        push_notifications BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (courier_id) REFERENCES couriers(courier_id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ";
    
    $conn->multi_query($sql);
    
    // Wait for all queries to complete
    while ($conn->next_result()) {;}      // Create test courier with hashed password
    $courier_id = 'COR001';
    $name = 'Test Courier';
    $email = 'test.courier@bookstore.com';
    $phone = '123-456-7890';
    $password = 'Password123'; // Updated to meet validation requirements: uppercase, lowercase, number
    $vehicle_number = 'VEH001';
    $avg_rating = 4.75;
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO couriers (courier_id, name, email, phone, password, vehicle_number, avg_rating) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssd", $courier_id, $name, $email, $phone, $hashed_password, $vehicle_number, $avg_rating);
    $stmt->execute();
      // Insert sample customers
    $customers = [
        ['John Doe', 'john@example.com', '123-456-7890', '123 Main St, City', password_hash('Password123', PASSWORD_DEFAULT)],
        ['Jane Smith', 'jane@example.com', '098-765-4321', '456 Oak Ave, City', password_hash('Password123', PASSWORD_DEFAULT)]
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
        ['COR001', 1, 'ORD003', '789 Pine St, City', 'Express delivery - Academic textbooks', 'John Doe - 123-456-7890', 'completed'],
        ['COR001', 2, 'ORD004', '321 Elm St, City', 'Fiction novels and poetry books', 'Jane Smith - 098-765-4321', 'completed'],
        ['COR001', 1, 'ORD005', '654 Maple Dr, City', 'Business and management books', 'John Doe - 123-456-7890', 'pending']
    ];
    
    $stmt = $conn->prepare("INSERT INTO deliveries (courier_id, customer_id, order_id, delivery_address, delivery_details, customer_info, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($deliveries as $delivery) {
        $stmt->bind_param("sisssss", $delivery[0], $delivery[1], $delivery[2], $delivery[3], $delivery[4], $delivery[5], $delivery[6]);
        $stmt->execute();
    }
    
    // Insert sample customer feedback for completed deliveries
    $feedback_data = [
        [3, 'COR001', 5, 'Excellent service! Books arrived in perfect condition and on time.', 'excellent'],
        [4, 'COR001', 4, 'Good delivery, though slightly delayed. Books were well packaged.', 'good']
    ];
    
    $stmt = $conn->prepare("INSERT INTO customer_feedback (delivery_id, courier_id, customer_rating, customer_comment, delivery_experience) VALUES (?, ?, ?, ?, ?)");
    foreach ($feedback_data as $feedback) {
        $stmt->bind_param("isiss", $feedback[0], $feedback[1], $feedback[2], $feedback[3], $feedback[4]);
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
