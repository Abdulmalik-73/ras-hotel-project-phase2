<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get fresh services data
$services_query = "SELECT * FROM services WHERE status = 'active' ORDER BY category, name";
$services_result = $conn->query($services_query);
$services = $services_result->fetch_all(MYSQLI_ASSOC);

// Group by category
$services_by_category = [];
foreach ($services as $service) {
    $services_by_category[$service['category']][] = $service;
}

// Return services count and basic info
$response = [
    'success' => true,
    'total_services' => count($services),
    'services_by_category' => array_map(function($category_services) {
        return [
            'count' => count($category_services),
            'services' => array_map(function($service) {
                return [
                    'id' => $service['id'],
                    'name' => $service['name'],
                    'price' => $service['price'],
                    'status' => $service['status']
                ];
            }, $category_services)
        ];
    }, $services_by_category),
    'timestamp' => time()
];

echo json_encode($response);
?>