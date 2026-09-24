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

// Delete diet plan
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $plan_id = $_GET['delete'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get meal images
        $stmt = $conn->prepare("SELECT image_path FROM diet_plan_meals WHERE diet_plan_id = ?");
        $stmt->bind_param("i", $plan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $meals = $result->fetch_all(MYSQLI_ASSOC);
        
        // First delete related meals
        $stmt = $conn->prepare("DELETE FROM diet_plan_meals WHERE diet_plan_id = ?");
        $stmt->bind_param("i", $plan_id);
        $stmt->execute();
        $stmt->close();
        
        // Then delete the diet plan
        $stmt = $conn->prepare("DELETE FROM diet_plans WHERE id = ? AND coach_id = ?");
        $stmt->bind_param("ii", $plan_id, $coach_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Delete associated meal images
            foreach ($meals as $meal) {
                if ($meal['image_path']) {
                    $image_path = "../uploads/" . $meal['image_path'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
            }
            
            $alert = "Diet plan deleted successfully!";
            $alert_type = "success";
        } else {
            $alert = "You can only delete your own diet plans.";
            $alert_type = "danger";
        }
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

// Get coach's specialization
$specialization = "";
$stmt = $conn->prepare("SELECT specialization FROM coaches WHERE id = ?");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $specialization = $row['specialization'];
}
$stmt->close();

// Get all diet plans created by this coach
$diet_plans = [];
$stmt = $conn->prepare("SELECT * FROM diet_plans WHERE coach_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $diet_plans[] = $row;
}
$stmt->close();

// Get all members for assignment
$members = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.fullname 
    FROM users u 
    INNER JOIN user_memberships um ON u.id = um.user_id 
    WHERE u.role = 'member' 
    AND um.coach_id = ? 
    AND um.status = 'active'
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}
$stmt->close();

// Assign diet plan to member
if (isset($_POST['assign_plan'])) {
    $plan_id = $_POST['plan_id'];
    $member_id = $_POST['member_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $notes = $_POST['notes'] ?? '';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if member already has an active plan
        $stmt = $conn->prepare("SELECT id FROM member_diet_plans WHERE member_id = ? AND status = 'active'");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Member already has an active diet plan.");
        }
        
        // Assign the diet plan
        $stmt = $conn->prepare("INSERT INTO member_diet_plans (member_id, diet_plan_id, start_date, end_date, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $member_id, $plan_id, $start_date, $end_date, $notes);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $alert = "Diet plan assigned successfully!";
            $alert_type = "success";
        } else {
            throw new Exception("Failed to assign diet plan.");
        }
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $alert = "Error: " . $e->getMessage();
        $alert_type = "danger";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diet Plans - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Diet Plans</h1>
            <div class="flex space-x-4">
                <a href="assigned_tasks.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-clipboard-check mr-2"></i> View Assigned Tasks
                </a>
                <a href="create_diet_plan.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded flex items-center">
                    <i class="fas fa-plus mr-2"></i> Create New Plan
                </a>
            </div>
        </div>
        
        <?php if (!empty($alert)): ?>
            <div class="mb-4 p-4 rounded <?php echo $alert_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>
        
        <!-- Diet Plans List -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <?php if (empty($diet_plans)): ?>
                <div class="p-6 text-center text-gray-500">
                    <p>You haven't created any diet plans yet.</p>
                    <a href="create_diet_plan.php" class="mt-3 inline-block text-gray-600 hover:text-gray-900">
                        <i class="fas fa-plus mr-1"></i> Create your first diet plan
                    </a>
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calories</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nutrients</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($diet_plans as $plan): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($plan['name']); ?></div>
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
                                    P: <?php echo $plan['protein']; ?>g | 
                                    C: <?php echo $plan['carbs']; ?>g | 
                                    F: <?php echo $plan['fats']; ?>g
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($plan['is_active']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($plan['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view_diet_plan.php?id=<?php echo $plan['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_diet_plan.php?id=<?php echo $plan['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="openAssignModal(<?php echo $plan['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3" title="Assign to Member">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <a href="diet_plans.php?delete=<?php echo $plan['id']; ?>" class="text-red-600 hover:text-red-900" 
                                       onclick="return confirm('Are you sure you want to delete this diet plan? This action cannot be undone.')" title="Delete">
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

    <!-- Assignment Modal -->
    <div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Assign Diet Plan</h3>
                <form action="diet_plans.php" method="POST">
                    <input type="hidden" name="plan_id" id="plan_id">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="member_id">
                            Select Member
                        </label>
                        <select name="member_id" id="member_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            <option value="">Select a member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="start_date">
                            Start Date
                        </label>
                        <input type="date" name="start_date" id="start_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="end_date">
                            End Date
                        </label>
                        <input type="date" name="end_date" id="end_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="notes">
                            Notes (Optional)
                        </label>
                        <textarea name="notes" id="notes" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="3"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeAssignModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                            Cancel
                        </button>
                        <button type="submit" name="assign_plan" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                            Assign Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAssignModal(planId) {
            document.getElementById('plan_id').value = planId;
            document.getElementById('assignModal').classList.remove('hidden');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.add('hidden');
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
