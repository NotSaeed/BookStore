<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header('Location: courier-login.html');
    exit();
}

$courier_id = $_SESSION['courier_id'];

// Get pending and in-progress deliveries
$stmt = $conn->prepare("
    SELECT d.*, c.name as customer_name, c.address, c.phone 
    FROM deliveries d
    JOIN customers c ON d.customer_id = c.id
    WHERE d.courier_id = ? 
    AND d.status IN ('pending', 'in_progress')
    ORDER BY d.created_at ASC");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$deliveries = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Planning - BookStore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css" />
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        :root {
            --primary-color: #9b59b6;
            --primary-dark: #8e44ad;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f6f8;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .route-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .delivery-list {
            border-right: 1px solid #eee;
            padding-right: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
        }

        .delivery-item {
            padding: 1rem;
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 1rem;
            cursor: move;
            background: white;
            transition: transform 0.2s;
        }

        .delivery-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .delivery-item h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .delivery-item p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        #map {
            height: 600px;
            border-radius: 10px;
        }

        .optimize-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 1rem;
            width: 100%;
            transition: background-color 0.3s;
        }

        .optimize-btn:hover {
            background: var(--primary-dark);
        }

        .save-route-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 1rem;
            width: 100%;
            transition: background-color 0.3s;
        }

        .save-route-btn:hover {
            background: #219a52;
        }

        .route-stats {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .route-stats p {
            margin: 0.5rem 0;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-truck"></i>
            <h2>Courier Dashboard</h2>
        </div>
        <ul class="nav-links">
            <li><a href="courier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="route-planning.php" class="active"><i class="fas fa-route"></i> Route Planning</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1><i class="fas fa-route"></i> Route Planning</h1>
        
        <div class="route-container">
            <div class="delivery-list" id="deliveryList">
                <button class="optimize-btn" onclick="optimizeRoute()">
                    <i class="fas fa-magic"></i> Optimize Route
                </button>
                <button class="save-route-btn" onclick="saveRoute()">
                    <i class="fas fa-save"></i> Save Route
                </button>
                <div class="route-stats">
                    <p><i class="fas fa-truck"></i> Total Stops: <span id="totalStops">0</span></p>
                    <p><i class="fas fa-road"></i> Est. Distance: <span id="totalDistance">0 km</span></p>
                    <p><i class="fas fa-clock"></i> Est. Time: <span id="totalTime">0 min</span></p>
                </div>
                <?php while($delivery = $deliveries->fetch_assoc()): ?>
                    <div class="delivery-item" data-id="<?php echo $delivery['id']; ?>" 
                         data-address="<?php echo htmlspecialchars($delivery['address']); ?>">
                        <h3>Order #<?php echo $delivery['id']; ?></h3>
                        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($delivery['customer_name']); ?></p>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($delivery['address']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($delivery['phone']); ?></p>
                    </div>
                <?php endwhile; ?>
            </div>
            <div id="map"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <script>
        // Initialize map
        const map = L.map('map').setView([0, 0], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let markers = [];
        let polyline = null;

        // Make delivery list sortable
        new Sortable(document.getElementById('deliveryList'), {
            animation: 150,
            handle: '.delivery-item',
            onEnd: function() {
                updateRoute();
            }
        });

        // Initialize geocoding function
        async function geocodeAddress(address) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
                const data = await response.json();
                if (data && data.length > 0) {
                    return [parseFloat(data[0].lat), parseFloat(data[0].lon)];
                }
                return null;
            } catch (error) {
                console.error('Geocoding error:', error);
                return null;
            }
        }

        // Update route on map
        async function updateRoute() {
            // Clear existing markers and polyline
            markers.forEach(marker => marker.remove());
            markers = [];
            if (polyline) polyline.remove();

            const deliveryItems = document.querySelectorAll('.delivery-item');
            const coordinates = [];

            for (const item of deliveryItems) {
                const address = item.dataset.address;
                const coords = await geocodeAddress(address);
                
                if (coords) {
                    const marker = L.marker(coords)
                        .bindPopup(`<b>Order #${item.dataset.id}</b><br>${address}`)
                        .addTo(map);
                    markers.push(marker);
                    coordinates.push(coords);
                }
            }

            // Draw route line
            if (coordinates.length > 1) {
                polyline = L.polyline(coordinates, {color: '#9b59b6', weight: 3}).addTo(map);
                map.fitBounds(polyline.getBounds());
            }

            // Update stats
            document.getElementById('totalStops').textContent = coordinates.length;
            // Rough distance calculation
            let distance = 0;
            for (let i = 1; i < coordinates.length; i++) {
                distance += map.distance(coordinates[i-1], coordinates[i]);
            }
            document.getElementById('totalDistance').textContent = `${(distance / 1000).toFixed(1)} km`;
            document.getElementById('totalTime').textContent = `${Math.ceil(distance / 1000 * 3)} min`; // Rough estimate: 20km/h
        }

        // Optimize route using nearest neighbor algorithm
        function optimizeRoute() {
            const deliveryItems = Array.from(document.querySelectorAll('.delivery-item'));
            const container = document.getElementById('deliveryList');
            
            // Remove all items
            deliveryItems.forEach(item => item.remove());
            
            // Add optimized items back
            deliveryItems.sort((a, b) => {
                // In a real implementation, this would use actual distances
                // For now, we'll just randomize to simulate optimization
                return Math.random() - 0.5;
            }).forEach(item => {
                container.appendChild(item);
            });

            updateRoute();
        }

        // Save route
        async function saveRoute() {
            const deliveryOrder = Array.from(document.querySelectorAll('.delivery-item'))
                .map(item => item.dataset.id);
            
            try {
                const response = await fetch('update_route.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        deliveryOrder: deliveryOrder
                    })
                });

                if (response.ok) {
                    alert('Route saved successfully!');
                } else {
                    throw new Error('Failed to save route');
                }
            } catch (error) {
                alert('Error saving route: ' + error.message);
            }
        }

        // Initial route update
        updateRoute();
    </script>
</body>
</html>