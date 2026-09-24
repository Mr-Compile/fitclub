<?php
// Start session if not already started
session_start();

require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // Check user credentials
        $query = "SELECT id, username, password, role FROM users WHERE username = :username";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Store redirect URL in session
                if ($user['role'] === 'member') {
                    // Check if user has completed assessment
                    $query = "SELECT completed FROM pre_assessments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(":user_id", $user['id']);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($assessment['completed']) {
                            $_SESSION['redirect_url'] = 'user/dashboard.php';
                        } else {
                            $_SESSION['redirect_url'] = 'user/pre_assessment.php';
                        }
                    } else {
                        $_SESSION['redirect_url'] = 'user/pre_assessment.php';
                    }
                } elseif ($user['role'] === 'admin') {
                    $_SESSION['redirect_url'] = 'admin/dashboard.php';
                } elseif ($user['role'] === 'coach') {
                    $_SESSION['redirect_url'] = 'coach/dashboard.php';
                }
                
                // Return success response with redirect URL
                echo json_encode([
                    'success' => true,
                    'redirect_url' => $_SESSION['redirect_url']
                ]);
                exit();
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid username or password'
                ]);
                exit();
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid username or password'
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Please enter both username and password'
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - The Fitclub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-group input {
            padding-left: 3rem;
            transition: all 0.3s ease;
        }
        .input-group input:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: calc(50% + 0.5rem); /* Adjusted to account for label */
            transform: translateY(-50%);
            color: #6B7280;
            transition: all 0.3s ease;
        }
        .input-group input:focus + .input-icon {
            color: #4F46E5;
        }
        .form-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .hero-bg {
            background-size: cover;
            background-position: center;
            image-rendering: -webkit-optimize-contrast;
            transform: translateZ(0);
            backface-visibility: hidden;
            will-change: transform;
        }
        /* Loading Overlay Styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #4F46E5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-100 via-gray-50 to-gray-200">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="text-center">
            <div class="loading-spinner mb-4"></div>
            <p id="loadingMessage" class="text-gray-700 text-lg font-medium"></p>
        </div>
    </div>

    <?php include 'components/public_navigation.php'; ?>
    
    <div class="min-h-screen flex">
        <!-- Left side - Gym Introduction -->
        <div class="hidden lg:flex lg:w-1/2 bg-cover bg-center relative hero-bg" style="background-image: url('assets/images/waw-bg.jpg');">
            <div class="absolute inset-0 bg-gradient-to-r from-black/70 to-black/50"></div>
            <div class="relative z-10 w-full h-full flex items-center justify-center">
                <div class="text-white p-12 max-w-lg">
                    <h1 class="text-4xl font-bold mb-6">Welcome to The Fitclub</h1>
                    <p class="text-lg mb-4">Transform your body, transform your life. Join our community of fitness enthusiasts and professional trainers.</p>
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            State-of-the-art equipment
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Expert personal trainers
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Customized workout plans
                        </li>
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Supportive community
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="max-w-md w-full form-card p-8">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900">Sign in to your account</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Or
                        <a href="register.php" class="font-medium text-gray-600 hover:text-gray-900">
                            create a new account
                        </a>
                    </p>
                </div>

                <?php if (isset($_SESSION['registration_success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['registration_success']); ?></span>
                    </div>
                    <?php unset($_SESSION['registration_success']); ?>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="POST" id="loginForm">
                    <div class="space-y-4">
                        <div class="input-group">
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input id="username" name="username" type="text" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            <svg class="input-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="input-group">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input id="password" name="password" type="password" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500">
                            <svg class="input-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 transform hover:scale-[1.02]">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        Sign in
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show loading overlay with success message
                const loadingOverlay = document.getElementById('loadingOverlay');
                const loadingMessage = document.getElementById('loadingMessage');
                loadingMessage.textContent = 'Login successful! Redirecting to dashboard...';
                loadingOverlay.style.display = 'flex';
                
                // Redirect after 1.5 seconds
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 1500);
            } else {
                // Handle error
                if (data.error) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6';
                    errorDiv.setAttribute('role', 'alert');
                    errorDiv.innerHTML = `<span class="block sm:inline">${data.error}</span>`;
                    
                    // Remove any existing error messages
                    const existingError = document.querySelector('.bg-red-100');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    // Insert new error message
                    const form = document.getElementById('loginForm');
                    form.parentNode.insertBefore(errorDiv, form);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
    </script>
</body>
</html>