<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get total members count
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'member'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_members = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total coaches count
$query = "SELECT COUNT(*) as total FROM coaches WHERE active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$total_coaches = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total active memberships
$query = "SELECT COUNT(*) as total FROM user_memberships WHERE status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute();
$active_memberships = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total revenue this month
$query = "SELECT COALESCE(SUM(amount), 0) as total 
          FROM payments 
          WHERE status = 'completed' 
          AND MONTH(payment_date) = MONTH(CURRENT_DATE())
          AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$monthly_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get pending upgrade requests
$query = "SELECT COUNT(*) as total FROM plan_upgrade_requests WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_upgrades = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent members
$query = "SELECT u.*, m.status as membership_status 
          FROM users u 
          LEFT JOIN user_memberships m ON u.id = m.user_id 
          WHERE u.role = 'member' 
          ORDER BY u.created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments
$query = "SELECT p.*, u.username, mp.name as plan_name 
          FROM payments p 
          JOIN user_memberships m ON p.user_membership_id = m.id 
          JOIN users u ON m.user_id = u.id 
          JOIN membership_plans mp ON m.plan_id = mp.id 
          ORDER BY p.payment_date DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// New analytics queries

// Membership distribution by plan type
$query = "SELECT mp.name, COUNT(*) as count 
          FROM user_memberships um 
          JOIN membership_plans mp ON um.plan_id = mp.id 
          WHERE um.status = 'active' 
          GROUP BY mp.name";
$stmt = $db->prepare($query);
$stmt->execute();
$membership_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly revenue for the last 6 months
$query = "SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount) as revenue
          FROM payments
          WHERE status = 'completed'
            AND payment_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute();
$monthly_revenue_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// New member registrations by month
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as new_members
          FROM users
          WHERE role = 'member'
            AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$stmt = $db->prepare($query);
$stmt->execute();
$new_members_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Coach performance - clients count per coach
$query = "SELECT 
            c.id, 
            u.username as coach_name, 
            c.specialization,
            c.rating,
            COUNT(um.id) as client_count 
          FROM coaches c
          JOIN users u ON c.user_id = u.id
          LEFT JOIN user_memberships um ON c.id = um.coach_id AND um.status = 'active'
          WHERE c.active = 1
          GROUP BY c.id
          ORDER BY client_count DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$coach_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Membership status distribution
$query = "SELECT status, COUNT(*) as count
          FROM user_memberships
          GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute();
$membership_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current year's total revenue
$query = "SELECT COALESCE(SUM(amount), 0) as total 
          FROM payments 
          WHERE status = 'completed' 
          AND YEAR(payment_date) = YEAR(CURRENT_DATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$yearly_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get previous year's total revenue
$query = "SELECT COALESCE(SUM(amount), 0) as total 
          FROM payments 
          WHERE status = 'completed' 
          AND YEAR(payment_date) = YEAR(CURRENT_DATE()) - 1";
$stmt = $db->prepare($query);
$stmt->execute();
$previous_yearly_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #1F2937 0%, #111827 100%);
        }
        .hover-scale {
            transition: transform 0.2s ease;
        }
        .hover-scale:hover {
            transform: scale(1.02);
        }
        .analytics-card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .analytics-card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="bg-white rounded-xl shadow-sm p-8 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                    <p class="text-gray-500">Here's what's happening with your gym today.</p>
                </div>
                <div class="hidden md:block">
                    <i class="fas fa-chart-line text-6xl text-gray-200"></i>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Members -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 hover:shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Members</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_members; ?></p>
                    </div>
                </div>
            </div>

            <!-- Active Coaches -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 hover:shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                        <i class="fas fa-user-tie text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Coaches</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_coaches; ?></p>
                    </div>
                </div>
            </div>

            <!-- Active Memberships -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 hover:shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                        <i class="fas fa-id-card text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Memberships</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $active_memberships; ?></p>
                    </div>
                </div>
            </div>

            <!-- Monthly Revenue -->
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 hover:shadow-md">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500">
                        <i class="fas fa-money-bill-wave text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Monthly Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($monthly_revenue, 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Overview -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Financial Overview</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Monthly Revenue Trend -->
                <div class="bg-white p-6 rounded-xl analytics-card">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Revenue Trend (Last 6 Months)</h3>
                    <div class="h-64">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Yearly Revenue Comparison -->
                <div class="bg-white p-6 rounded-xl analytics-card">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Yearly Revenue Comparison</h3>
                    <div class="flex items-center h-64">
                        <div class="w-1/2">
                            <canvas id="yearlyComparisonChart"></canvas>
                        </div>
                        <div class="w-1/2 pl-6">
                            <div class="mb-4">
                                <p class="text-sm text-gray-500">Current Year</p>
                                <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($yearly_revenue, 2); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Previous Year</p>
                                <p class="text-2xl font-bold text-gray-900">₱<?php echo number_format($previous_yearly_revenue, 2); ?></p>
                            </div>
                            <?php
                            $revenue_growth = $previous_yearly_revenue > 0 ? 
                                (($yearly_revenue - $previous_yearly_revenue) / $previous_yearly_revenue) * 100 : 0;
                            $growth_class = $revenue_growth >= 0 ? 'text-green-600' : 'text-red-600';
                            $growth_icon = $revenue_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                            ?>
                            <div class="mt-4">
                                <span class="<?php echo $growth_class; ?> flex items-center text-sm font-semibold">
                                    <i class="fas <?php echo $growth_icon; ?> mr-1"></i>
                                    <?php echo abs(round($revenue_growth, 1)); ?>% from last year
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Membership Analysis -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Membership Analysis</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Membership Distribution by Plan -->
                <div class="bg-white p-6 rounded-xl analytics-card">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Membership Distribution</h3>
                    <div class="h-64">
                        <canvas id="membershipDistributionChart"></canvas>
                    </div>
                </div>

                <!-- New Members Trend -->
                <div class="bg-white p-6 rounded-xl analytics-card">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">New Member Registrations</h3>
                    <div class="h-64">
                        <canvas id="newMembersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coach Performance -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Coach Performance</h2>
            <div class="bg-white rounded-xl analytics-card">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coach</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Clients</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($coach_performance as $coach): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($coach['coach_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            <?php echo ucfirst(str_replace('_', ' ', $coach['specialization'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm text-gray-900 mr-1"><?php echo number_format($coach['rating'], 1); ?></span>
                                            <i class="fas fa-star text-gray-600 text-sm"></i>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $coach['client_count']; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Members -->
            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Members</h2>
                        <a href="members.php" class="text-sm text-gray-600 hover:text-gray-700">View all</a>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recent_members as $member): ?>
                            <div class="hover-scale flex items-center justify-between p-3 rounded-lg hover:bg-gray-50">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($member['username']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($member['email']); ?></p>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($member['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Payments</h2>
                        <a href="payments.php" class="text-sm text-gray-600 hover:text-gray-700">View all</a>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach ($recent_payments as $payment): ?>
                            <div class="hover-scale flex items-center justify-between p-3 rounded-lg hover:bg-gray-50">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                                            <i class="fas fa-receipt text-gray-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payment['username']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['plan_name']); ?></p>
                                    </div>
                                </div>
                                <div class="text-sm font-medium text-gray-900">
                                    ₱<?php echo number_format($payment['amount'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Actions -->
        <?php if ($pending_upgrades > 0): ?>
            <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-yellow-400 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            You have <?php echo $pending_upgrades; ?> pending membership upgrade requests.
                            <a href="upgrades.php" class="font-medium underline text-yellow-700 hover:text-yellow-600 ml-1">
                                Review them now
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        // Convert PHP data to JavaScript
        const monthlyRevenue = <?php echo json_encode(array_map(function($item) { 
            return ['month' => $item['month'], 'revenue' => floatval($item['revenue'])]; 
        }, $monthly_revenue_trend)); ?>;
        
        const membershipDistribution = <?php echo json_encode(array_map(function($item) { 
            return ['name' => $item['name'], 'count' => intval($item['count'])]; 
        }, $membership_distribution)); ?>;
        
        const newMembersData = <?php echo json_encode(array_map(function($item) { 
            return ['month' => $item['month'], 'new_members' => intval($item['new_members'])]; 
        }, $new_members_trend)); ?>;
        
        const yearlyData = {
            current: <?php echo $yearly_revenue; ?>,
            previous: <?php echo $previous_yearly_revenue; ?>
        };

        // Format month labels (convert YYYY-MM to MMM YYYY)
        function formatMonthLabel(monthStr) {
            const [year, month] = monthStr.split('-');
            const date = new Date(year, parseInt(month) - 1, 1);
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }

        // Set up colors
        const colors = {
            primary: '#3B82F6', // blue
            secondary: '#6366F1', // indigo
            success: '#10B981', // green
            warning: '#F59E0B', // yellow
            danger: '#EF4444',
            info: '#06B6D4', // cyan
            light: '#F3F4F6'
        };

        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: monthlyRevenue.map(item => formatMonthLabel(item.month)),
                datasets: [{
                    label: 'Monthly Revenue (₱)',
                    data: monthlyRevenue.map(item => item.revenue),
                    borderColor: colors.primary,
                    backgroundColor: 'rgba(59, 130, 246, 0.1)', // light blue
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Membership Distribution Chart
        const membershipCtx = document.getElementById('membershipDistributionChart').getContext('2d');
        new Chart(membershipCtx, {
            type: 'doughnut',
            data: {
                labels: membershipDistribution.map(item => item.name),
                datasets: [{
                    data: membershipDistribution.map(item => item.count),
                    backgroundColor: [colors.primary, colors.secondary, colors.info, colors.light],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // New Members Chart
        const newMembersCtx = document.getElementById('newMembersChart').getContext('2d');
        new Chart(newMembersCtx, {
            type: 'bar',
            data: {
                labels: newMembersData.map(item => formatMonthLabel(item.month)),
                datasets: [{
                    label: 'New Members',
                    data: newMembersData.map(item => item.new_members),
                    backgroundColor: 'rgba(31, 41, 55, 0.7)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Yearly Comparison Chart
        const yearlyCtx = document.getElementById('yearlyComparisonChart').getContext('2d');
        new Chart(yearlyCtx, {
            type: 'pie',
            data: {
                labels: ['Current Year', 'Previous Year'],
                datasets: [{
                    data: [yearlyData.current, yearlyData.previous],
                    backgroundColor: [colors.primary, colors.light],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₱' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 