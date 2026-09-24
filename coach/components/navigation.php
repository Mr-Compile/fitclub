<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header("Location: ../index.php");
    exit();
}

// Get unread message count
$unread_count = 0;
try {
    $stmt = $conn->prepare("SELECT unread_count FROM message_notifications WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $notification = $result->fetch_assoc();
    $unread_count = $notification ? $notification['unread_count'] : 0;
} catch (Exception $e) {
    // If table doesn't exist, create it
    if ($conn->errno == 1146) { // Table doesn't exist error code
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS message_notifications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                unread_count INT DEFAULT 0,
                last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )");
            
            // Initialize notification for current user
            $conn->query("INSERT INTO message_notifications (user_id, unread_count) 
                      VALUES ({$_SESSION['user_id']}, 0)");
        } catch (Exception $e2) {
            // If we can't create the table, just continue with 0 unread count
            error_log("Failed to create message_notifications table: " . $e2->getMessage());
        }
    }
}
?>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<nav class="bg-gray-900 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo and Brand -->
            <div class="flex items-center">
                <a href="../index.php" class="flex items-center">
                    <i class="fas fa-dumbbell text-gray-300 text-2xl"></i>
                    <span class="ml-2 text-xl font-bold text-white">FitClub Coach</span>
                </a>
            </div>

            <!-- Main Navigation -->
            <div class="hidden md:flex items-center space-x-4">
                <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-home mr-1"></i> Dashboard
                </a>
                <a href="members.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-users mr-1"></i> Members
                </a>
                <a href="messages.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-envelope mr-1"></i> Messages
                    <?php if ($unread_count > 0): ?>
                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="workout_plans.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'workout_plans.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-dumbbell mr-1"></i> Workout Plans
                </a>

                <a href="diet_plans.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'diet_plans.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-utensils mr-1"></i> Diet Plans
                </a>
                <a href="assigned_tasks.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'assigned_tasks.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-clipboard-check mr-1"></i> Assigned Tasks
                </a>
            </div>

            <!-- User Dropdown -->
            <div class="flex items-center">
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-2 text-gray-300 hover:text-white focus:outline-none">
                        <i class="fas fa-user-circle text-2xl"></i>
                        <span class="hidden md:block"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <i class="fas fa-chevron-down text-sm"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div x-show="open" @click.away="open = false" 
                         class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-gray-800 ring-1 ring-black ring-opacity-5">
                        <div class="py-1">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-user-edit mr-2"></i> Profile
                            </a>
                            <a href="#" onclick="showLogoutModal(); return false;" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-700 hover:text-red-300">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-300 hover:text-white focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile menu -->
        <div x-show="mobileMenuOpen" class="md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="dashboard.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-home mr-1"></i> Dashboard
                </a>
                <a href="members.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-users mr-1"></i> Members
                </a>
                <a href="messages.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-envelope mr-1"></i> Messages
                    <?php if ($unread_count > 0): ?>
                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="workout_plans.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'workout_plans.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-dumbbell mr-1"></i> Workout Plans
                </a>
                <a href="assigned_tasks.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'assigned_tasks.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-clipboard-check mr-1"></i> Assigned Tasks
                </a>
                <a href="diet_plans.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'diet_plans.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-utensils mr-1"></i> Diet Plans
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Alpine.js for dropdown functionality -->
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-sign-out-alt text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Confirm Logout</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to logout? You will need to login again to access your account.
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <a href="../logout.php" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    Yes, Logout
                </a>
                <button onclick="closeLogoutModal()" class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Logout Modal Functions
function showLogoutModal() {
    document.getElementById('logoutModal').classList.remove('hidden');
}

function closeLogoutModal() {
    document.getElementById('logoutModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogoutModal();
    }
});
</script>