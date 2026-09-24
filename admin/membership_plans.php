<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $name = $_POST['name'];
            $description = $_POST['description'];
            $price = $_POST['price'];
            $duration = $_POST['duration'];
            $features = $_POST['features'];

            $query = "INSERT INTO membership_plans (name, description, price, duration, features) 
                     VALUES (:name, :description, :price, :duration, :features)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':features', $features);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Membership plan created successfully.";
            } else {
                $_SESSION['error'] = "Failed to create membership plan.";
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $description = $_POST['description'];
            $price = $_POST['price'];
            $duration = $_POST['duration'];
            $features = $_POST['features'];

            $query = "UPDATE membership_plans 
                     SET name = :name, 
                         description = :description, 
                         price = :price, 
                         duration = :duration, 
                         features = :features 
                     WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':features', $features);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Membership plan updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update membership plan.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            
            $query = "DELETE FROM membership_plans WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Membership plan deleted successfully.";
            } else {
                $_SESSION['error'] = "Failed to delete membership plan.";
            }
        }
        
        header("Location: membership_plans.php");
        exit();
    }
}

// Get all membership plans
$query = "SELECT * FROM membership_plans ORDER BY price ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$query = "SELECT COUNT(*) as total_plans FROM membership_plans";
$stmt = $db->prepare($query);
$stmt->execute();
$total_plans = $stmt->fetch(PDO::FETCH_ASSOC)['total_plans'];

$query = "SELECT COUNT(*) as active_subscribers FROM user_memberships WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$active_subscribers = $stmt->fetch(PDO::FETCH_ASSOC)['active_subscribers'];

$query = "SELECT AVG(price) as avg_price FROM membership_plans";
$stmt = $db->prepare($query);
$stmt->execute();
$avg_price = $stmt->fetch(PDO::FETCH_ASSOC)['avg_price'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Plans - FitClub Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .plan-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .feature-list li {
            transition: transform 0.2s ease;
        }
        .feature-list li:hover {
            transform: translateX(5px);
        }
        .modal-overlay {
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Membership Plans</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage and customize gym membership plans</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <button onclick="openCreateModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex items-center">
                        <i class="fas fa-plus mr-2"></i>Add New Plan
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                        <i class="fas fa-cube text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Plans</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_plans; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Subscribers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $active_subscribers; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                        <i class="fas fa-money-bill-wave text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Average Price</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($avg_price, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plans Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($plans as $plan): ?>
            <div class="plan-card bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($plan['name']); ?></h3>
                        <p class="text-gray-500 text-sm mt-1"><?php echo htmlspecialchars($plan['description']); ?></p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($plan)); ?>)" 
                                class="text-gray-600 hover:text-gray-900 transition-colors" 
                                title="Edit Plan">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="confirmDelete(<?php echo $plan['id']; ?>)" 
                                class="text-red-600 hover:text-red-800 transition-colors" 
                                title="Delete Plan">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="flex justify-between items-center mb-4">
                    <span class="text-3xl font-bold text-gray-900">₱<?php echo number_format($plan['price'], 2); ?></span>
                    <span class="text-gray-500 bg-gray-100 px-3 py-1 rounded-full text-sm">
                        <?php echo $plan['duration']; ?> month(s)
                    </span>
                </div>
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-3 text-gray-900">Features:</h4>
                    <ul class="feature-list space-y-2">
                        <?php foreach (explode("\n", $plan['features']) as $feature): ?>
                            <li class="flex items-center text-gray-600">
                                <i class="fas fa-check text-gray-600 mr-2"></i>
                                <?php echo htmlspecialchars($feature); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="planModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full modal-overlay z-50">
        <div class="relative top-16 mx-auto p-8 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
            <div class="flex items-center mb-6">
                <div class="bg-yellow-100 p-4 rounded-full mr-4">
                    <i class="fas fa-cube text-yellow-500 text-3xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1" id="modalTitle">Add New Plan</h3>
                    <p class="text-gray-500 text-sm">Create or edit a membership plan</p>
                </div>
            </div>
            <form id="planForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="planId">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name"><i class="fas fa-id-card mr-2 text-gray-400"></i>Plan Name</label>
                        <input type="text" name="name" id="name" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="price"><i class="fas fa-money-bill-wave mr-2 text-gray-400"></i>Price (₱)</label>
                        <input type="number" name="price" id="price" step="0.01" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="duration"><i class="fas fa-calendar-alt mr-2 text-gray-400"></i>Duration (months)</label>
                        <input type="number" name="duration" id="duration" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                    </div>
                    <div class="mb-4 md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="description"><i class="fas fa-align-left mr-2 text-gray-400"></i>Description</label>
                        <textarea name="description" id="description" required
                                  class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"></textarea>
                    </div>
                    <div class="mb-4 md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="features"><i class="fas fa-list-ul mr-2 text-gray-400"></i>Features (one per line)</label>
                        <textarea name="features" id="features" required
                                  class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-8">
                    <button type="button" onclick="closeModal()" 
                            class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-yellow-500 text-white px-6 py-2 rounded-lg hover:bg-yellow-600 transition-colors font-semibold">
                        Save Plan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full modal-overlay">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-xl bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center mb-4">
                    <div class="bg-red-100 p-3 rounded-full">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2 text-center">Confirm Delete</h3>
                <p class="text-gray-600 mb-4 text-center">Are you sure you want to delete this plan? This action cannot be undone.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add New Plan';
            document.getElementById('formAction').value = 'create';
            document.getElementById('planForm').reset();
            document.getElementById('planModal').classList.remove('hidden');
        }

        function openEditModal(plan) {
            document.getElementById('modalTitle').textContent = 'Edit Plan';
            document.getElementById('formAction').value = 'update';
            document.getElementById('planId').value = plan.id;
            document.getElementById('name').value = plan.name;
            document.getElementById('description').value = plan.description;
            document.getElementById('price').value = plan.price;
            document.getElementById('duration').value = plan.duration;
            document.getElementById('features').value = plan.features;
            document.getElementById('planModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('planModal').classList.add('hidden');
        }

        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const planModal = document.getElementById('planModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === planModal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html> 