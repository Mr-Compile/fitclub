<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// First get the coach's ID from the coaches table
$stmt = $conn->prepare("SELECT id FROM coaches WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$coach_result = $stmt->get_result();
$coach = $coach_result->fetch_assoc();

if (!$coach) {
    header("Location: ../index.php");
    exit();
}

$coach_id = $coach['id'];

// Get coach information
$stmt = $conn->prepare("
    SELECT c.*, u.fullname, u.email, u.phone 
    FROM coaches c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.id = ?
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$coach_info = $stmt->get_result()->fetch_assoc();

// Get assigned members
$stmt = $conn->prepare("
    SELECT u.*, um.start_date, um.end_date, mp.name as plan_name
    FROM user_memberships um
    JOIN users u ON um.user_id = u.id
    JOIN membership_plans mp ON um.plan_id = mp.id
    WHERE um.coach_id = ? AND um.status = 'active'
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get diet plan feedback
$stmt = $conn->prepare("
    SELECT 
        dpf.*,
        dp.name as diet_plan_name,
        u.fullname as member_name,
        mdp.start_date,
        mdp.end_date
    FROM diet_plan_feedback dpf
    JOIN member_diet_plans mdp ON dpf.member_diet_plan_id = mdp.id
    JOIN diet_plans dp ON mdp.diet_plan_id = dp.id
    JOIN users u ON mdp.member_id = u.id
    WHERE dp.coach_id = ?
    ORDER BY dpf.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$diet_feedback = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coach Dashboard - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .member-card {
            transition: all 0.3s ease;
        }
        .member-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?php echo htmlspecialchars($coach_info['fullname']); ?>!</h1>
            <p class="text-gray-600">Here's an overview of your coaching dashboard</p>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center">
                    <div class="p-3 bg-indigo-100 rounded-lg">
                        <i class="fas fa-users text-indigo-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Active Members</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($members); ?></p>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center">
                    <div class="p-3 bg-indigo-100 rounded-lg">
                        <i class="fas fa-dumbbell text-indigo-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Workout Plans</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM workout_plans WHERE coach_id = ?");
                            $stmt->bind_param("i", $coach_id);
                            $stmt->execute();
                            echo $stmt->get_result()->fetch_assoc()['count'];
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center">
                    <div class="p-3 bg-indigo-100 rounded-lg">
                        <i class="fas fa-utensils text-indigo-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Diet Plans</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php
                            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM diet_plans WHERE coach_id = ?");
                            $stmt->bind_param("i", $coach_id);
                            $stmt->execute();
                            echo $stmt->get_result()->fetch_assoc()['count'];
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Diet Plan Feedback Section -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-900">Recent Diet Plan Feedback</h2>
                <a href="diet_plans.php" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            <?php if (empty($diet_feedback)): ?>
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-comment-alt text-gray-400 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Feedback Yet</h3>
                    <p class="text-gray-500">You haven't received any feedback on your diet plans yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($diet_feedback as $feedback): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($feedback['diet_plan_name']); ?></h3>
                                    <p class="text-sm text-gray-500">Member: <?php echo htmlspecialchars($feedback['member_name']); ?></p>
                                    <div class="flex items-center mt-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ml-2 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-gray-500">
                                        <?php echo date('M d, Y', strtotime($feedback['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($feedback['end_date'])); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($feedback['feedback']): ?>
                                <p class="mt-2 text-sm text-gray-600 italic"><?php echo htmlspecialchars($feedback['feedback']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Coach Profile Card -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex items-center mb-6">
                    <div class="h-16 w-16 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-tie text-gray-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($coach_info['fullname']); ?></h2>
                        <p class="text-gray-600"><?php echo ucwords(str_replace('_', ' ', $coach_info['specialization'])); ?> Specialist</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-envelope w-6"></i>
                        <span class="ml-2"><?php echo htmlspecialchars($coach_info['email']); ?></span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-phone w-6"></i>
                        <span class="ml-2"><?php echo htmlspecialchars($coach_info['phone'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="flex items-center text-gray-600">
                        <i class="fas fa-clock w-6"></i>
                        <span class="ml-2"><?php echo $coach_info['experience_years']; ?> years of experience</span>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="profile.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-edit mr-2"></i> Edit Profile
                    </a>
                </div>
            </div>

            <!-- Assigned Members -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-900">Assigned Members</h2>
                    <a href="members.php" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <?php if (empty($members)): ?>
                    <div class="text-center py-12">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                            <i class="fas fa-users text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Members Assigned</h3>
                        <p class="text-gray-500">You don't have any active members yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($members as $member): ?>
                            <div class="member-card bg-gray-50 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        <div class="h-12 w-12 bg-gray-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-gray-500"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($member['fullname']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($member['plan_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <a href="messages.php?member_id=<?php echo $member['id']; ?>" 
                                           class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors">
                                            <i class="fas fa-comment mr-2"></i> Message
                                        </a>
                                        <a href="members.php?id=<?php echo $member['id']; ?>" 
                                           class="text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer></body>
</html> 