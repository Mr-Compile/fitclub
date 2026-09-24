<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
?>
<nav class="bg-gray-900 shadow-md fixed w-full z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
              
                    <div class="bg-gray-800 p-2 rounftided-lg">
                        <i class="fas fa-dumbbell text-gray-300 text-2xl"></i>
                    </div>
                    <span class="ml-2 text-xl font-bold text-white">FitClub</span>
                </a>
            </div>
            <div class="flex items-center space-x-6">
                <a href="index.php" class="flex items-center text-gray-300 hover:text-white transition duration-300">
                    <i class="fas fa-home text-xl"></i>
                    <span class="ml-2 font-medium">Home</span>
                </a>
                <?php if ($isLoggedIn): ?>
                    <a href="./logout.php" class="flex items-center text-gray-300 hover:text-white transition duration-300">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                        <span class="ml-2 font-medium">Logout</span>
                    </a>
                <?php else: ?>
                    <a href="./login.php" class="flex items-center text-gray-300 hover:text-white transition duration-300">
                        <i class="fas fa-sign-in-alt text-xl"></i>
                        <span class="ml-2 font-medium">Login</span>
                    </a>
                    <a href="./register.php" class="flex items-center bg-gray-800 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-300">
                        <i class="fas fa-user-plus text-xl"></i>
                        <span class="ml-2 font-medium">Register</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav> 