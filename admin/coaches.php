<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle coach actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $coach_id = filter_var($_POST['coach_id'], FILTER_VALIDATE_INT);
    
    if (!$coach_id) {
        $_SESSION['error'] = "Invalid coach ID";
        header("Location: coaches.php");
        exit();
    }

    try {
        $db->beginTransaction();

        switch ($_POST['action']) {
            case 'delete':
                // Check if coach has active clients
                $query = "SELECT COUNT(*) as client_count FROM coach_clients WHERE coach_id = :coach_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['client_count'] > 0) {
                    throw new Exception("Cannot delete coach with active clients");
                }

                // Delete coach's clients first
                $query = "DELETE FROM coach_clients WHERE coach_id = :coach_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->execute();

                // Delete coach's ratings
                $query = "DELETE FROM coach_ratings WHERE coach_id = :coach_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->execute();

                // Delete coach record
                $query = "DELETE FROM coaches WHERE id = :coach_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->execute();

                // Delete user account
                $query = "DELETE FROM users WHERE id = (SELECT user_id FROM coaches WHERE id = :coach_id) AND role = 'coach'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->execute();

                $_SESSION['success'] = "Coach deleted successfully";
                break;

            case 'update':
                $fullname = $_POST['fullname'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $specialization = $_POST['specialization'];
                $experience_years = $_POST['experience_years'];
                $bio = $_POST['bio'];

                // Update user details
                $query = "UPDATE users u 
                         JOIN coaches c ON u.id = c.user_id 
                         SET u.fullname = :fullname, 
                             u.email = :email, 
                             u.phone = :phone 
                         WHERE c.id = :coach_id AND u.role = 'coach'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->execute();

                // Update coach details
                $query = "UPDATE coaches 
                         SET specialization = :specialization,
                             experience_years = :experience_years,
                             bio = :bio
                         WHERE id = :coach_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':specialization', $specialization);
                $stmt->bindParam(':experience_years', $experience_years);
                $stmt->bindParam(':bio', $bio);
                $stmt->bindParam(':coach_id', $coach_id);
                $stmt->execute();

                $_SESSION['success'] = "Coach updated successfully";
                break;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header("Location: coaches.php");
    exit();
}

// Get coach details for view/edit
$coach_details = null;
if (isset($_GET['action']) && isset($_GET['id'])) {
    $coach_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($coach_id) {
        $query = "SELECT c.*, u.username, u.email, u.fullname, u.phone 
                 FROM coaches c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.id = :coach_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':coach_id', $coach_id);
        $stmt->execute();
        $coach_details = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Get all coaches
$query = "SELECT c.*, u.username, u.email, u.fullname, u.phone, u.created_at 
          FROM coaches c 
          JOIN users u ON c.user_id = u.id 
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaches Management - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-container {
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #E5E7EB #F3F4F6;
        }
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #F3F4F6;
            border-radius: 4px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background-color: #E5E7EB;
            border-radius: 4px;
        }
        .hover-row:hover {
            background-color: #F9FAFB;
        }
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(75, 85, 99, 0.2);
        }
        .rating-stars {
            color: #FCD34D;
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
                    <h1 class="text-2xl font-bold text-gray-900">Coaches Management</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage and view all gym coaches</p>
                </div>
                <div class="mt-4 md:mt-0 flex space-x-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               id="searchInput"
                               placeholder="Search coaches..." 
                               class="search-input pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full md:w-64">
                    </div>
                    <button onclick="showAddCoachModal()" 
                            class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        Add Coach
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                        <i class="fas fa-user-tie text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Coaches</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($coaches); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                        <i class="fas fa-star text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Average Rating</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php 
                            $avg_rating = array_sum(array_column($coaches, 'rating')) / count($coaches);
                            echo number_format($avg_rating, 1);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                        <i class="fas fa-dumbbell text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Specializations</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo count(array_unique(array_column($coaches, 'specialization'))); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coaches Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="table-container">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coach</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Experience</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($coaches as $coach): ?>
                            <tr class="hover-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-user-tie text-gray-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($coach['username']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($coach['fullname'] ?? ''); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <?php echo htmlspecialchars($coach['specialization']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($coach['experience_years']); ?> years
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $coach['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="ml-2 text-sm text-gray-500">(<?php echo number_format($coach['rating'], 1); ?>)</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($coach['email']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($coach['phone'] ?? 'No phone'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-3">
                                        <button onclick="viewCoach(<?php echo htmlspecialchars(json_encode($coach)); ?>)" 
                                                class="text-gray-600 hover:text-gray-900 transition-colors" 
                                                title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editCoach(<?php echo htmlspecialchars(json_encode($coach)); ?>)" 
                                                class="text-gray-600 hover:text-gray-900 transition-colors" 
                                                title="Edit Coach">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?php echo $coach['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900 transition-colors" 
                                                title="Remove Coach">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View/Edit Modal -->
    <div id="coachModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full modal-overlay z-50">
        <div class="relative top-16 mx-auto p-8 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
            <div class="flex items-center mb-6">
                <div class="bg-blue-100 p-4 rounded-full mr-4">
                    <i class="fas fa-user-tie text-blue-600 text-3xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1" id="modalTitle">Coach Details</h3>
                    <p class="text-gray-500 text-sm">View or edit coach information</p>
                </div>
            </div>
            <form id="coachForm" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="coach_id" id="coachId">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="fullname"><i class="fas fa-id-card mr-2 text-gray-400"></i>Full Name</label>
                        <input type="text" name="fullname" id="fullname" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="email"><i class="fas fa-envelope mr-2 text-gray-400"></i>Email</label>
                        <input type="email" name="email" id="email" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="phone"><i class="fas fa-phone mr-2 text-gray-400"></i>Phone</label>
                        <input type="tel" name="phone" id="phone"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="specialization"><i class="fas fa-dumbbell mr-2 text-gray-400"></i>Specialization</label>
                        <select name="specialization" id="specialization" required
                                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="bulk" class="bg-yellow-100 text-yellow-800">Bulk Training</option>
                            <option value="weight_loss" class="bg-pink-100 text-pink-800">Weight Loss</option>
                            <option value="strength" class="bg-green-100 text-green-800">Strength Training</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="experience_years"><i class="fas fa-briefcase mr-2 text-gray-400"></i>Years of Experience</label>
                        <input type="number" name="experience_years" id="experience_years" required min="0" max="50"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mb-4 md:col-span-2">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="bio"><i class="fas fa-align-left mr-2 text-gray-400"></i>Bio</label>
                        <textarea name="bio" id="bio" rows="3"
                                  class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-8">
                    <button type="button" onclick="closeModal()" 
                            class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
                        Close
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                        Save Changes
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
                <p class="text-gray-600 mb-4 text-center">Are you sure you want to delete this coach? This action cannot be undone.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="coach_id" id="deleteId">
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
        // Simple search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });

        function viewCoach(coach) {
            document.getElementById('modalTitle').textContent = 'Coach Details';
            document.getElementById('coachId').value = coach.id;
            document.getElementById('fullname').value = coach.fullname || '';
            document.getElementById('email').value = coach.email;
            document.getElementById('phone').value = coach.phone || '';
            document.getElementById('specialization').value = coach.specialization;
            document.getElementById('experience_years').value = coach.experience_years;
            document.getElementById('bio').value = coach.bio || '';
            
            // Disable form fields in view mode
            document.getElementById('coachForm').querySelectorAll('input, select, textarea').forEach(input => {
                input.disabled = true;
            });
            
            document.getElementById('coachModal').classList.remove('hidden');
        }

        function editCoach(coach) {
            document.getElementById('modalTitle').textContent = 'Edit Coach';
            document.getElementById('coachId').value = coach.id;
            document.getElementById('fullname').value = coach.fullname || '';
            document.getElementById('email').value = coach.email;
            document.getElementById('phone').value = coach.phone || '';
            document.getElementById('specialization').value = coach.specialization;
            document.getElementById('experience_years').value = coach.experience_years;
            document.getElementById('bio').value = coach.bio || '';
            
            // Enable form fields in edit mode
            document.getElementById('coachForm').querySelectorAll('input, select, textarea').forEach(input => {
                input.disabled = false;
            });
            
            document.getElementById('coachModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('coachModal').classList.add('hidden');
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
            const coachModal = document.getElementById('coachModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === coachModal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html> 