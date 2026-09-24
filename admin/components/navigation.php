<?php
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();
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
                    <span class="ml-2 text-xl font-bold text-white">FitClub Admin</span>
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
                <a href="coaches.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'coaches.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-user-tie mr-1"></i> Coaches
                </a>
                <a href="membership_plans.php" class="px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'membership_plans.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-credit-card mr-1"></i> Membership Plans
                </a>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="px-3 py-2 rounded-md text-sm font-medium <?php echo in_array(basename($_SERVER['PHP_SELF']), ['payments.php', 'upgrades.php']) ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                        <i class="fas fa-money-bill-wave mr-1"></i> Payments
                        <?php
                        // Get pending payments count (excluding upgrades)
                        $pending_payments_query = "SELECT COUNT(*) as count FROM payments WHERE status = 'pending' AND payment_type != 'upgrade'";
                        $stmt = $db->prepare($pending_payments_query);
                        $stmt->execute();
                        $pending_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                        // Get pending upgrades count
                        $pending_upgrades_query = "SELECT COUNT(*) as count FROM plan_upgrade_requests WHERE status = 'pending'";
                        $stmt = $db->prepare($pending_upgrades_query);
                        $stmt->execute();
                        $pending_upgrades = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                        $total_notifications = $pending_payments + $pending_upgrades;
                        if ($total_notifications > 0): ?>
                            <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full"><?php echo $total_notifications; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down text-xs ml-1"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" 
                         class="absolute left-0 mt-2 w-48 rounded-md shadow-lg bg-gray-800 ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1">
                            <a href="payments.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-receipt mr-2"></i> Regular Payments
                                <?php if ($pending_payments > 0): ?>
                                    <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full ml-2"><?php echo $pending_payments; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="upgrades.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-arrow-up mr-2"></i> Upgrade Requests
                                <?php if ($pending_upgrades > 0): ?>
                                    <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full ml-2"><?php echo $pending_upgrades; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>

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
                         class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-gray-800 ring-1 ring-black ring-opacity-5 z-50">
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
                <a href="coaches.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'coaches.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-user-tie mr-1"></i> Coaches
                </a>
                <a href="membership_plans.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'membership_plans.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-credit-card mr-1"></i> Membership Plans
                </a>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="px-3 py-2 rounded-md text-base font-medium <?php echo in_array(basename($_SERVER['PHP_SELF']), ['payments.php', 'upgrades.php']) ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                        <i class="fas fa-money-bill-wave mr-1"></i> Payments
                        <?php
                        // Get pending payments count (excluding upgrades)
                        $pending_payments_query = "SELECT COUNT(*) as count FROM payments WHERE status = 'pending' AND payment_type != 'upgrade'";
                        $stmt = $db->prepare($pending_payments_query);
                        $stmt->execute();
                        $pending_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                        // Get pending upgrades count
                        $pending_upgrades_query = "SELECT COUNT(*) as count FROM plan_upgrade_requests WHERE status = 'pending'";
                        $stmt = $db->prepare($pending_upgrades_query);
                        $stmt->execute();
                        $pending_upgrades = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                        $total_notifications = $pending_payments + $pending_upgrades;
                        if ($total_notifications > 0): ?>
                            <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full"><?php echo $total_notifications; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down text-xs ml-1"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" 
                         class="absolute left-0 mt-2 w-48 rounded-md shadow-lg bg-gray-800 ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1">
                            <a href="payments.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-receipt mr-2"></i> Regular Payments
                                <?php if ($pending_payments > 0): ?>
                                    <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full ml-2"><?php echo $pending_payments; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="upgrades.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                <i class="fas fa-arrow-up mr-2"></i> Upgrade Requests
                                <?php if ($pending_upgrades > 0): ?>
                                    <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full ml-2"><?php echo $pending_upgrades; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
                <a href="assessments.php" class="block px-3 py-2 rounded-md text-base font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'assessments.php' ? 'text-white bg-gray-700' : 'text-gray-300 hover:text-white hover:bg-gray-700'; ?>">
                    <i class="fas fa-clipboard-check mr-1"></i> Assessments
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