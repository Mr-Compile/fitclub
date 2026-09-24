<?php
require_once __DIR__ . '/../../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

// Get unread message count
$unread_count = 0;

// Check user's current plan
$current_plan = null;
try {
    $query = "SELECT mp.name 
              FROM user_memberships um 
              JOIN membership_plans mp ON um.plan_id = mp.id 
              WHERE um.user_id = ? 
              AND um.status = 'active' 
              AND um.payment_status = 'completed'
              ORDER BY um.id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $current_plan = $row ? $row['name'] : null;
} catch (Exception $e) {
    error_log("Error checking user plan: " . $e->getMessage());
}

// Get unread message count
try {
    $query = "SELECT unread_count FROM message_notifications WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $notification = $result->fetch_assoc();
    $unread_count = $notification ? $notification['unread_count'] : 0;
} catch (Exception $e) {
    // If table doesn't exist, create it
    if ($e->getCode() == '42S02') {
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
            error_log("Failed to create message_notifications table: " . $e2->getMessage());
        }
    }
}

// Function to check if feature is locked
function isFeatureLocked($plan) {
    return $plan === 'Basic' || $plan === null;
}

// Function to get feature link with lock status
function getFeatureLink($href, $text, $icon, $isLocked, $unread_count = 0) {
    if ($isLocked) {
        return '<a href="#" onclick="showPlanUpgradeModal()" class="px-3 py-2 rounded-md text-sm font-medium text-gray-400 cursor-not-allowed relative group">
                    <i class="fas fa-lock mr-1"></i>
                    <i class="' . $icon . ' mr-1"></i>' . $text . '
                    <span class="absolute -top-8 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        Upgrade to Pro or Premium to access
                    </span>
                </a>';
    }
    
    $activeClass = basename($_SERVER['PHP_SELF']) == basename($href) ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700';
    $notificationBadge = $unread_count > 0 ? '<span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">' . $unread_count . '</span>' : '';
    
    return '<a href="' . $href . '" class="px-3 py-2 rounded-md text-sm font-medium ' . $activeClass . ' relative">
                <i class="' . $icon . ' mr-1"></i>' . $text . $notificationBadge . '
            </a>';
}
?>
<nav class="bg-gray-900 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo and Brand -->
            <div class="flex items-center">
                <a href="../index.php" class="flex items-center">
                    <i class="fas fa-dumbbell text-gray-300 text-2xl"></i>
                    <span class="ml-2 text-xl font-bold text-white">FitClub</span>
                </a>
            </div>

            <!-- Main Navigation -->
            <div class="hidden md:flex items-center space-x-4">
                <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-home mr-1"></i> Dashboard
                </a>
                <a href="membership.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'membership.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-id-card mr-1"></i> Membership
                </a>
                <a href="payment_history.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'payment_history.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-history mr-1"></i> Payments
                </a>
                <?php 
                echo getFeatureLink('messages.php', 'Messages', 'fas fa-envelope', isFeatureLocked($current_plan), $unread_count);
                echo getFeatureLink('workout_plan.php', 'Workout', 'fas fa-dumbbell', isFeatureLocked($current_plan));
                echo getFeatureLink('diet_plan.php', 'Diet', 'fas fa-utensils', isFeatureLocked($current_plan));
                ?>
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
                            <a href="progress.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-chart-line mr-2"></i> My Progress
                            </a>
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
                <a href="pre_assessment.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'pre_assessment.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-clipboard-check mr-1"></i> Assessment
                </a>
                <a href="membership.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'membership.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-id-card mr-1"></i> Membership
                </a>
                <a href="payment_history.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'payment_history.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-history mr-1"></i> Payments
                </a>
                <?php 
                echo getFeatureLink('workout_plan.php', 'Workout', 'fas fa-dumbbell', isFeatureLocked($current_plan));
                echo getFeatureLink('diet_plan.php', 'Diet', 'fas fa-utensils', isFeatureLocked($current_plan));
                echo getFeatureLink('messages.php', 'Messages', 'fas fa-envelope', isFeatureLocked($current_plan), $unread_count);
                ?>
            </div>
        </div>
    </div>
</nav>

<!-- Plan Upgrade Modal -->
<div id="planUpgradeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100">
                <i class="fas fa-lock text-indigo-600 text-xl"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Feature Locked</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    This feature is only available for Pro and Premium members. Upgrade your plan to access messages, workout plans, and diet plans.
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <a href="membership.php" class="px-4 py-2 bg-indigo-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    View Plans
                </a>
                <button onclick="closePlanUpgradeModal()" class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

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
// Plan Upgrade Modal Functions
function showPlanUpgradeModal() {
    document.getElementById('planUpgradeModal').classList.remove('hidden');
}

function closePlanUpgradeModal() {
    document.getElementById('planUpgradeModal').classList.add('hidden');
}

// Logout Modal Functions
function showLogoutModal() {
    document.getElementById('logoutModal').classList.remove('hidden');
}

function closeLogoutModal() {
    document.getElementById('logoutModal').classList.add('hidden');
}

// Close modals when clicking outside
document.getElementById('planUpgradeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePlanUpgradeModal();
    }
});

document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogoutModal();
    }
});
</script>