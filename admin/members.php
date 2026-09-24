<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle member actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $member_id = filter_var($_POST['member_id'], FILTER_VALIDATE_INT);
    
    if (!$member_id) {
        $_SESSION['error'] = "Invalid member ID";
        header("Location: members.php");
        exit();
    }

    try {
        $db->beginTransaction();

        switch ($_POST['action']) {
            case 'delete':
                // Check if member has active membership
                $query = "SELECT status FROM user_memberships WHERE user_id = :user_id AND status = 'active'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $member_id);
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    throw new Exception("Cannot delete member with active membership");
                }

                // Delete member's memberships first
                $query = "DELETE FROM user_memberships WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $member_id);
                $stmt->execute();

                // Delete member's payments
                $query = "DELETE p FROM payments p 
                         JOIN user_memberships m ON p.user_membership_id = m.id 
                         WHERE m.user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $member_id);
                $stmt->execute();

                // Finally delete the user
                $query = "DELETE FROM users WHERE id = :user_id AND role = 'member'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $member_id);
                $stmt->execute();

                $_SESSION['success'] = "Member deleted successfully";
                break;

            case 'update':
                $fullname = $_POST['fullname'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $status = $_POST['status'];

                // Update user details
                $query = "UPDATE users 
                         SET fullname = :fullname, 
                             email = :email, 
                             phone = :phone 
                         WHERE id = :user_id AND role = 'member'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':user_id', $member_id);
                $stmt->execute();

                // Update membership status if provided
                if ($status) {
                    $query = "UPDATE user_memberships 
                             SET status = :status 
                             WHERE user_id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':user_id', $member_id);
                    $stmt->execute();
                }

                $_SESSION['success'] = "Member updated successfully";
                break;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header("Location: members.php");
    exit();
}

// Get member details for view/edit
$member_details = null;
if (isset($_GET['action']) && isset($_GET['id'])) {
    $member_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($member_id) {
        $query = "SELECT u.*, m.status as membership_status, m.id as membership_id 
                 FROM users u 
                 LEFT JOIN user_memberships m ON u.id = m.user_id 
                 WHERE u.id = :user_id AND u.role = 'member'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $member_id);
        $stmt->execute();
        $member_details = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Get all members
$query = "SELECT u.*, m.status as membership_status 
          FROM users u 
          LEFT JOIN user_memberships m ON u.id = m.user_id 
          WHERE u.role = 'member' 
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch pre-assessment for each member
$member_assessments = [];
foreach ($members as $member) {
    $query = "SELECT * FROM pre_assessments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $member['id']);
    $stmt->execute();
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    $member_assessments[$member['id']] = $assessment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Management - FitClub</title>
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
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
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
                    <h1 class="text-2xl font-bold text-gray-900">Members Management</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage and view all gym members</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               id="searchInput"
                               placeholder="Search members..." 
                               class="search-input pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full md:w-64">
                    </div>
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
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Members</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($members); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-user-check text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Members</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count(array_filter($members, function($m) { return $m['membership_status'] === 'active'; })); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-user-plus text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">New This Month</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count(array_filter($members, function($m) { return date('Y-m') === date('Y-m', strtotime($m['created_at'])); })); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="table-container">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($members as $member): ?>
                            <tr class="hover-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                                <i class="fas fa-user text-indigo-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($member['username']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($member['fullname'] ?? ''); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($member['email']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($member['phone'] ?? 'No phone'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $member['membership_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo ucfirst($member['membership_status'] ?? 'No membership'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($member['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-3">
                                        <button onclick="viewMember(<?php echo htmlspecialchars(json_encode($member)); ?>)" 
                                                class="text-indigo-600 hover:text-indigo-900 transition-colors" 
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editMember(<?php echo htmlspecialchars(json_encode($member)); ?>)" 
                                                class="text-blue-600 hover:text-blue-900 transition-colors" 
                                                title="Edit Member">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($member['membership_status'] !== 'active'): ?>
                                            <button onclick="confirmDelete(<?php echo $member['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900 transition-colors" 
                                                    title="Delete Member">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="viewAssessment(<?php echo htmlspecialchars(json_encode($member_assessments[$member['id']])) ?>)" 
                                                class="text-green-600 hover:text-green-900 transition-colors" 
                                                title="View Pre-Assessment">
                                            <i class="fas fa-clipboard-list"></i>
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
    <div id="memberModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full modal-overlay z-50">
        <div class="relative top-16 mx-auto p-8 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
            <div class="flex items-center mb-6">
                <div class="bg-indigo-100 p-4 rounded-full mr-4">
                    <i class="fas fa-user text-indigo-600 text-3xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1" id="modalTitle">Member Details</h3>
                    <p class="text-gray-500 text-sm">View or edit member information</p>
                </div>
            </div>
            <form id="memberForm" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="member_id" id="memberId">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="fullname"><i class="fas fa-id-card mr-2 text-gray-400"></i>Full Name</label>
                        <input type="text" name="fullname" id="fullname" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="email"><i class="fas fa-envelope mr-2 text-gray-400"></i>Email</label>
                        <input type="email" name="email" id="email" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="phone"><i class="fas fa-phone mr-2 text-gray-400"></i>Phone</label>
                        <input type="tel" name="phone" id="phone"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="status"><i class="fas fa-heartbeat mr-2 text-gray-400"></i>Membership Status</label>
                        <select name="status" id="status"
                                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="active" class="bg-green-100 text-green-800">Active</option>
                            <option value="inactive" class="bg-yellow-100 text-yellow-800">Inactive</option>
                            <option value="expired" class="bg-red-100 text-red-800">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-8">
                    <button type="button" onclick="closeModal()" 
                            class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
                        Close
                    </button>
                    <button type="submit" 
                            class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors font-semibold">
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
                <p class="text-gray-600 mb-4 text-center">Are you sure you want to delete this member? This action cannot be undone.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="member_id" id="deleteId">
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

    <!-- Pre-Assessment Modal -->
    <div id="assessmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full modal-overlay z-50">
        <div class="relative top-16 mx-auto p-8 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
            <div class="flex items-center mb-6">
                <div class="bg-indigo-100 p-4 rounded-full mr-4">
                    <i class="fas fa-clipboard-list text-indigo-600 text-3xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-1">Pre-Assessment Results</h3>
                    <p class="text-gray-500 text-sm">Member's fitness and health profile overview</p>
                </div>
            </div>
            <div id="assessmentContent">
                <!-- Populated by JS -->
            </div>
            <div class="flex justify-end mt-8">
                <button type="button" onclick="closeAssessmentModal()" 
                        class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
                    Close
                </button>
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

        function viewMember(member) {
            document.getElementById('modalTitle').textContent = 'Member Details';
            document.getElementById('memberId').value = member.id;
            document.getElementById('fullname').value = member.fullname || '';
            document.getElementById('email').value = member.email;
            document.getElementById('phone').value = member.phone || '';
            document.getElementById('status').value = member.membership_status || 'inactive';
            
            // Disable form fields in view mode
            document.getElementById('memberForm').querySelectorAll('input, select').forEach(input => {
                input.disabled = true;
            });
            
            document.getElementById('memberModal').classList.remove('hidden');
        }

        function editMember(member) {
            document.getElementById('modalTitle').textContent = 'Edit Member';
            document.getElementById('memberId').value = member.id;
            document.getElementById('fullname').value = member.fullname || '';
            document.getElementById('email').value = member.email;
            document.getElementById('phone').value = member.phone || '';
            document.getElementById('status').value = member.membership_status || 'inactive';
            
            // Enable form fields in edit mode
            document.getElementById('memberForm').querySelectorAll('input, select').forEach(input => {
                input.disabled = false;
            });
            
            document.getElementById('memberModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('memberModal').classList.add('hidden');
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
            const memberModal = document.getElementById('memberModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === memberModal) {
                closeModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }

        function viewAssessment(assessment) {
            const modal = document.getElementById('assessmentModal');
            const content = document.getElementById('assessmentContent');
            if (!assessment) {
                content.innerHTML = '<p class="text-gray-500 text-center py-8"><i class="fas fa-exclamation-circle text-3xl text-red-400 mb-2"></i><br>No pre-assessment found for this member.</p>';
            } else {
                // Color indicators for fitness level and goal
                const fitnessLevelColors = {
                    'beginner': 'bg-yellow-100 text-yellow-800',
                    'intermediate': 'bg-blue-100 text-blue-800',
                    'advanced': 'bg-green-100 text-green-800'
                };
                const goalIcons = {
                    'weight_loss': '<i class="fas fa-weight text-pink-500"></i>',
                    'muscle_gain': '<i class="fas fa-dumbbell text-indigo-500"></i>',
                    'strength': '<i class="fas fa-bolt text-yellow-500"></i>',
                    'endurance': '<i class="fas fa-running text-green-500"></i>',
                    'overall_fitness': '<i class="fas fa-heartbeat text-red-500"></i>'
                };
                const fitnessLevel = assessment.fitness_level || '';
                const fitnessGoal = assessment.fitness_goal || '';
                content.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div class="flex items-center"><i class="fas fa-user text-gray-400 mr-2"></i><span class="font-semibold">Age:</span> <span class="ml-2">${assessment.age || ''}</span></div>
                            <div class="flex items-center"><i class="fas fa-venus-mars text-gray-400 mr-2"></i><span class="font-semibold">Gender:</span> <span class="ml-2 capitalize">${assessment.gender || ''}</span></div>
                            <div class="flex items-center"><i class="fas fa-ruler-vertical text-gray-400 mr-2"></i><span class="font-semibold">Height:</span> <span class="ml-2">${assessment.height || ''} cm</span></div>
                            <div class="flex items-center"><i class="fas fa-weight text-gray-400 mr-2"></i><span class="font-semibold">Weight:</span> <span class="ml-2">${assessment.weight || ''} kg</span></div>
                            <div class="flex items-center"><i class="fas fa-allergies text-gray-400 mr-2"></i><span class="font-semibold">Allergies:</span> <span class="ml-2">${assessment.allergies || 'None'}</span></div>
                            <div class="flex items-center"><i class="fas fa-notes-medical text-gray-400 mr-2"></i><span class="font-semibold">Health Conditions:</span> <span class="ml-2">${assessment.health_conditions || 'None'}</span></div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center"><i class="fas fa-chart-line text-gray-400 mr-2"></i><span class="font-semibold">Fitness Level:</span> <span class="ml-2 px-3 py-1 rounded-full text-xs font-semibold ${fitnessLevelColors[fitnessLevel] || 'bg-gray-100 text-gray-800'}">${fitnessLevel.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) || ''}</span></div>
                            <div class="flex items-center"><span class="mr-2">${goalIcons[fitnessGoal] || ''}</span><span class="font-semibold">Fitness Goal:</span> <span class="ml-2 capitalize">${fitnessGoal.replace('_', ' ') || ''}</span></div>
                            <div class="flex items-center"><i class="fas fa-clock text-gray-400 mr-2"></i><span class="font-semibold">Preferred Training Time:</span> <span class="ml-2 capitalize">${assessment.preferred_training_time || ''}</span></div>
                            <div class="flex items-center"><i class="fas fa-calculator text-gray-400 mr-2"></i><span class="font-semibold">BMI:</span> <span class="ml-2">${assessment.bmi || ''}</span></div>
                            <div class="flex items-center"><i class="fas fa-sticky-note text-gray-400 mr-2"></i><span class="font-semibold">Notes:</span> <span class="ml-2">${assessment.notes || ''}</span></div>
                        </div>
                    </div>
                `;
            }
            modal.classList.remove('hidden');
        }

        function closeAssessmentModal() {
            document.getElementById('assessmentModal').classList.add('hidden');
        }
    </script>
</body>
</html> 