<?php
session_start();
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $phone = substr(preg_replace('/[^0-9]/', '', $_POST['phone']), 0, 11); // Remove non-numeric and limit to 11 chars
    
    // Validate input
    $errors = [];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($firstname) || empty($lastname) || empty($phone)) {
        $errors[] = "All fields are required";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (strlen($phone) !== 11) {
        $errors[] = "Phone number must be exactly 11 digits";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'errors' => ["Username or email already exists"]
                ]);
                exit();
            } else {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Combine firstname and lastname for fullname
                $fullname = $firstname . ' ' . $lastname;
                
                // Insert user with role 'member'
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, fullname, phone, role) VALUES (?, ?, ?, ?, ?, 'member')");
                $stmt->execute([$username, $email, $hashedPassword, $fullname, $phone]);
                
                $pdo->commit();
                
                // Return success response
                echo json_encode([
                    'success' => true,
                    'message' => 'Registration successful! Please login with your credentials.'
                ]);
                exit();
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'errors' => ["Registration failed: " . $e->getMessage()]
            ]);
            exit();
        }
    } else {
        echo json_encode([
            'success' => false,
            'errors' => $errors
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
    <title>Register - FitClub</title>
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
        <div class="hidden lg:flex lg:w-1/2 bg-cover bg-center relative hero-bg" style="background-image: url('assets/images/hero-bg.jpg');">
            <div class="absolute inset-0 bg-gradient-to-r from-black/70 to-black/50"></div>
            <div class="relative z-10 w-full h-full flex items-center justify-center">
                <div class="text-white p-12 max-w-lg">
                    <h1 class="text-4xl font-bold mb-6">Join FitClub Today</h1>
                    <p class="text-lg mb-4">Start your fitness journey with us. Get access to premium facilities and expert guidance.</p>
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            Personalized fitness assessment
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            Customized workout routines
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            Nutrition planning
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            24/7 support from trainers
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right side - Registration Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="max-w-md w-full form-card p-8">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-dumbbell text-3xl text-gray-600"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900">Create your account</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Or
                        <a href="login.php" class="font-medium text-gray-600 hover:text-gray-900">
                            sign in to your account
                        </a>
                    </p>
                </div>

                <div id="errorContainer"></div>

                <form method="POST" class="space-y-6" id="registerForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="input-group">
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" id="username" name="username" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            <i class="fas fa-user input-icon"></i>
                        </div>

                        <div class="input-group">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" id="email" name="email" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <i class="fas fa-envelope input-icon"></i>
                        </div>

                        <div class="input-group">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password (min. 6 characters)</label>
                            <input type="password" id="password" name="password" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   minlength="6">
                            <i class="fas fa-lock input-icon"></i>
                        </div>

                        <div class="input-group">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   minlength="6">
                            <i class="fas fa-lock input-icon"></i>
                        </div>

                        <div class="input-group">
                            <label for="firstname" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" id="firstname" name="firstname" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">
                            <i class="fas fa-user input-icon"></i>
                        </div>

                        <div class="input-group">
                            <label for="lastname" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" id="lastname" name="lastname" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>">
                            <i class="fas fa-user input-icon"></i>
                        </div>

                        <div class="input-group md:col-span-2">
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number (11 digits)</label>
                            <input type="tel" id="phone" name="phone" required 
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-gray-500 focus:border-gray-500" 
                                   pattern="[0-9]{11}" 
                                   title="Phone number must be exactly 11 digits" 
                                   inputmode="numeric" 
                                   maxlength="11" 
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            <i class="fas fa-phone input-icon"></i>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 transform hover:scale-[1.02]">
                        <i class="fas fa-user-plus mr-2"></i>
                        Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('register.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show loading overlay with success message
                const loadingOverlay = document.getElementById('loadingOverlay');
                const loadingMessage = document.getElementById('loadingMessage');
                loadingMessage.textContent = 'Registration successful! Redirecting to login...';
                loadingOverlay.style.display = 'flex';
                
                // Redirect after 1.5 seconds
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
            } else {
                // Handle errors
                if (data.errors && data.errors.length > 0) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 rounded-lg';
                    errorDiv.innerHTML = `
                        <ul class="list-disc list-inside text-sm text-red-600">
                            ${data.errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    `;
                    
                    // Remove any existing error messages
                    const existingError = document.querySelector('.mb-4.p-4.bg-red-50');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    // Insert new error message
                    const errorContainer = document.getElementById('errorContainer');
                    errorContainer.innerHTML = '';
                    errorContainer.appendChild(errorDiv);
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