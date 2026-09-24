<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is coach
if (!isCoach()) {
    header("Location: ../index.php");
    exit();
}

// Get coach ID
$coach_id = 0;
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM coaches WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $coach_id = $row['id'];
}
$stmt->close();

// Handle form actions
$alert = "";
$alert_type = "";

// Delete assigned plan
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $assignment_id = $_GET['delete'];
    $type = $_GET['type'] ?? 'workout';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        if ($type === 'workout') {
            // First check if this assignment belongs to one of this coach's members
            $stmt = $conn->prepare("
                SELECT mwp.id 
                FROM member_workout_plans mwp
                JOIN workout_plans wp ON mwp.workout_plan_id = wp.id
                JOIN user_memberships um ON mwp.member_id = um.user_id
                WHERE mwp.id = ? AND um.coach_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $coach_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("You can only delete workout plans assigned to your own members.");
            }
            
            // Delete the assigned workout plan
            $stmt = $conn->prepare("DELETE FROM member_workout_plans WHERE id = ?");
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $alert = "Assigned workout plan deleted successfully!";
                $alert_type = "success";
            } else {
                throw new Exception("Failed to delete the assigned workout plan.");
            }
        } else {
            // First check if this assignment belongs to one of this coach's members
            $stmt = $conn->prepare("
                SELECT mdp.id 
                FROM member_diet_plans mdp
                JOIN diet_plans dp ON mdp.diet_plan_id = dp.id
                JOIN user_memberships um ON mdp.member_id = um.user_id
                WHERE mdp.id = ? AND um.coach_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $coach_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("You can only delete diet plans assigned to your own members.");
            }
            
            // Delete the assigned diet plan
            $stmt = $conn->prepare("DELETE FROM member_diet_plans WHERE id = ?");
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $alert = "Assigned diet plan deleted successfully!";
                $alert_type = "success";
            } else {
                throw new Exception("Failed to delete the assigned diet plan.");
            }
        }
        
        // Commit transaction
        $conn->commit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Update assigned plan status
if (isset($_POST['update_status'])) {
    $assignment_id = $_POST['assignment_id'];
    $type = $_POST['type'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        if ($type === 'workout') {
            // First check if this assignment belongs to one of this coach's members
            $stmt = $conn->prepare("
                SELECT mwp.id 
                FROM member_workout_plans mwp
                JOIN workout_plans wp ON mwp.workout_plan_id = wp.id
                JOIN user_memberships um ON mwp.member_id = um.user_id
                WHERE mwp.id = ? AND um.coach_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $coach_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("You can only update workout plans assigned to your own members.");
            }
            
            // Update the assigned workout plan status
            $stmt = $conn->prepare("UPDATE member_workout_plans SET status = ?, notes = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $notes, $assignment_id);
            $stmt->execute();
            
            if ($stmt->affected_rows >= 0) {
                $alert = "Workout plan status updated successfully!";
                $alert_type = "success";
            } else {
                throw new Exception("Failed to update the workout plan status.");
            }
        } else {
            // First check if this assignment belongs to one of this coach's members
            $stmt = $conn->prepare("
                SELECT mdp.id 
                FROM member_diet_plans mdp
                JOIN diet_plans dp ON mdp.diet_plan_id = dp.id
                JOIN user_memberships um ON mdp.member_id = um.user_id
                WHERE mdp.id = ? AND um.coach_id = ?
            ");
            $stmt->bind_param("ii", $assignment_id, $coach_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("You can only update diet plans assigned to your own members.");
            }
            
            // Update the assigned diet plan status
            $stmt = $conn->prepare("UPDATE member_diet_plans SET status = ?, notes = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $notes, $assignment_id);
            $stmt->execute();
            
            if ($stmt->affected_rows >= 0) {
                $alert = "Diet plan status updated successfully!";
                $alert_type = "success";
            } else {
                throw new Exception("Failed to update the diet plan status.");
            }
        }
    } catch (Exception $e) {
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Get all assigned workout plans for this coach's members
$assigned_workouts = [];
$stmt = $conn->prepare("
    SELECT 
        mwp.id, 
        mwp.start_date, 
        mwp.end_date, 
        mwp.status, 
        mwp.notes,
        u.id AS member_id, 
        u.fullname AS member_name,
        wp.id AS plan_id,
        wp.name AS plan_name,
        wp.specialization,
        wp.difficulty_level
    FROM member_workout_plans mwp
    JOIN users u ON mwp.member_id = u.id
    JOIN workout_plans wp ON mwp.workout_plan_id = wp.id
    JOIN user_memberships um ON u.id = um.user_id
    WHERE um.coach_id = ?
    ORDER BY mwp.start_date DESC
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assigned_workouts[] = $row;
}
$stmt->close();

// Get all assigned diet plans for this coach's members
$assigned_diets = [];
$stmt = $conn->prepare("
    SELECT 
        mdp.id, 
        mdp.start_date, 
        mdp.end_date, 
        mdp.status, 
        mdp.notes,
        u.id AS member_id, 
        u.fullname AS member_name,
        dp.id AS plan_id,
        dp.name AS plan_name,
        dp.specialization,
        dp.duration_type,
        dp.calories
    FROM member_diet_plans mdp
    JOIN users u ON mdp.member_id = u.id
    JOIN diet_plans dp ON mdp.diet_plan_id = dp.id
    JOIN user_memberships um ON u.id = um.user_id
    WHERE um.coach_id = ?
    ORDER BY mdp.start_date DESC
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assigned_diets[] = $row;
}
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Tasks - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Assigned Tasks</h1>
            <div class="flex space-x-2">
                <a href="workout_plans.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded flex items-center">
                    <i class="fas fa-dumbbell mr-2"></i> Manage Workout Plans
                </a>
                <a href="diet_plans.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded flex items-center">
                    <i class="fas fa-utensils mr-2"></i> Manage Diet Plans
                </a>
            </div>
        </div>
        
        <?php if (!empty($alert)): ?>
            <div class="mb-4 p-4 rounded <?php echo $alert_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button onclick="showTab('workouts')" class="tab-button border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-dumbbell mr-2"></i> Workout Plans
                    </button>
                    <button onclick="showTab('diets')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        <i class="fas fa-utensils mr-2"></i> Diet Plans
                    </button>
                </nav>
            </div>
        </div>
        
        <!-- Workout Plans Tab -->
        <div id="workouts-tab" class="tab-content">
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <?php if (empty($assigned_workouts)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <p>You haven't assigned any workout plans to members yet.</p>
                        <a href="workout_plans.php" class="mt-3 inline-block text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-dumbbell mr-1"></i> Go to Workout Plans
                        </a>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Difficulty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($assigned_workouts as $plan): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($plan['member_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($plan['plan_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                            echo match($plan['specialization']) {
                                                'bulk' => 'bg-blue-100 text-blue-800',
                                                'weight_loss' => 'bg-green-100 text-green-800',
                                                'strength' => 'bg-purple-100 text-purple-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $plan['specialization'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                            echo match($plan['difficulty_level']) {
                                                'beginner' => 'bg-green-100 text-green-800',
                                                'intermediate' => 'bg-yellow-100 text-yellow-800',
                                                'advanced' => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <?php echo ucfirst($plan['difficulty_level']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($plan['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($plan['end_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                            echo match($plan['status']) {
                                                'active' => 'bg-green-100 text-green-800',
                                                'completed' => 'bg-blue-100 text-blue-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <?php echo ucfirst($plan['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="view_workout_plan.php?id=<?php echo $plan['plan_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="openStatusModal(<?php echo $plan['id']; ?>, 'workout', '<?php echo $plan['status']; ?>', '<?php echo addslashes($plan['notes']); ?>')" 
                                                class="text-blue-600 hover:text-blue-900 mr-3" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="assigned_tasks.php?delete=<?php echo $plan['id']; ?>&type=workout" class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this assigned workout plan? This action cannot be undone.')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Diet Plans Tab -->
        <div id="diets-tab" class="tab-content hidden">
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <?php if (empty($assigned_diets)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <p>You haven't assigned any diet plans to members yet.</p>
                        <a href="diet_plans.php" class="mt-3 inline-block text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-utensils mr-1"></i> Go to Diet Plans
                        </a>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calories</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($assigned_diets as $plan): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($plan['member_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($plan['plan_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                            echo match($plan['specialization']) {
                                                'bulk' => 'bg-blue-100 text-blue-800',
                                                'weight_loss' => 'bg-green-100 text-green-800',
                                                'strength' => 'bg-purple-100 text-purple-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $plan['specialization'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                            echo $plan['duration_type'] === 'daily' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                        ?>">
                                            <?php echo ucfirst($plan['duration_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($plan['calories']); ?> kcal
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($plan['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($plan['end_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                            echo match($plan['status']) {
                                                'active' => 'bg-green-100 text-green-800',
                                                'completed' => 'bg-blue-100 text-blue-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        ?>">
                                            <?php echo ucfirst($plan['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="view_diet_plan.php?id=<?php echo $plan['plan_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button onclick="openStatusModal(<?php echo $plan['id']; ?>, 'diet', '<?php echo $plan['status']; ?>', '<?php echo addslashes($plan['notes']); ?>')" 
                                                class="text-blue-600 hover:text-blue-900 mr-3" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="assigned_tasks.php?delete=<?php echo $plan['id']; ?>&type=diet" class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this assigned diet plan? This action cannot be undone.')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Update Plan Status</h3>
                <form action="assigned_tasks.php" method="POST">
                    <input type="hidden" name="assignment_id" id="assignment_id">
                    <input type="hidden" name="type" id="type">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="status">
                            Status
                        </label>
                        <select name="status" id="status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">
                            Notes
                        </label>
                        <textarea name="notes" id="notes" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeStatusModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                            Cancel
                        </button>
                        <button type="submit" name="update_status" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-indigo-500', 'text-indigo-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Highlight selected tab button
            event.currentTarget.classList.remove('border-transparent', 'text-gray-500');
            event.currentTarget.classList.add('border-indigo-500', 'text-indigo-600');
        }

        function openStatusModal(assignmentId, type, status, notes) {
            document.getElementById('assignment_id').value = assignmentId;
            document.getElementById('type').value = type;
            document.getElementById('status').value = status;
            document.getElementById('notes').value = notes;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }
    </script>

    <!-- Footer -->
    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html> 