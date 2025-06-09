<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Check if the required fields are set
        if (!isset($_POST['courier_id']) || !isset($_POST['email']) || !isset($_POST['password'])) {
            throw new Exception("All fields are required");
        }

        $courier_id = $conn->real_escape_string($_POST['courier_id']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password']; // Don't escape password before verification
        
        // First verify if the courier exists and get their stored details
        $sql = "SELECT * FROM couriers WHERE courier_id = ? AND email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $courier_id, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Invalid courier ID or email");
        }
        
        $courier = $result->fetch_assoc();
        
        // Verify the password
        if (!password_verify($password, $courier['password'])) {
            throw new Exception("Invalid password");
        }
        
        // Store courier data in session
        $_SESSION['courier_id'] = $courier_id;
        $_SESSION['courier_email'] = $email;
        $_SESSION['courier_name'] = $courier['name'];
        
        // Log successful login
        $log_sql = "INSERT INTO courier_logins (courier_id, email, login_time, status) 
                    VALUES (?, ?, NOW(), 'success')";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("ss", $courier_id, $email);
        $log_stmt->execute();
        
        // Redirect to dashboard
        header("Location: courier-dashboard.html");
        exit();
        
    } catch (Exception $e) {
        // Log failed login attempt
        if (isset($courier_id) && isset($email)) {
            $log_sql = "INSERT INTO courier_logins (courier_id, email, login_time, status) 
                        VALUES (?, ?, NOW(), 'failed')";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("ss", $courier_id, $email);
            $log_stmt->execute();
        }
        
        echo "<div style='color: red; padding: 20px; margin: 20px; border: 1px solid red; border-radius: 5px;'>";
        echo "<h3>Error:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "<p><a href='courier-login.html'>Go back to login</a></p>";
        echo "</div>";
    }
}

$conn->close();
?>
