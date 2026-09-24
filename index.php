<?php
session_start();
require_once 'config/database.php';

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'coach') {
        header("Location: coach/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

// Database connection
$database = new Database();
$db = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitClub - Your Fitness Journey Starts Here</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            position: relative;
            height: 100vh;
            width: 100%;
            overflow: hidden;
        }
        .hero-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            z-index: 1;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            -ms-interpolation-mode: nearest-neighbor;
            transform: translateZ(0);
            backface-visibility: hidden;
            will-change: transform;
            max-width: none;
            filter: none;
        }
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2;
        }
        .hero-content {
            position: relative;
            z-index: 3;
        }
        .text-shadow-strong {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8), 0 4px 12px rgba(0, 0, 0, 0.7);
        }
        .feature-card {
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .coach-card {
            transition: all 0.3s ease;
        }
        .coach-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .membership-card {
            transition: all 0.3s ease;
            height: 100%;
        }
        .membership-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }
        .testimonial-card {
            transition: all 0.3s ease;
        }
        .testimonial-card:hover {
            transform: translateY(-5px);
        }
        .cta-section {
            background-image: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-white">
    <?php include 'components/public_navigation.php'; ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <img src="assets/images/men-bg.jpg" alt="Fitness training" class="hero-image" loading="eager">
        <div class="hero-overlay"></div>
        <div class="hero-content h-full flex items-center justify-center text-center">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="inline-block mb-12">
                    <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 text-shadow-strong">
                        Transform Your Body,<br>
                        <span class="text-gray-300">Transform Your Life</span>
                    </h1>
                    <p class="text-xl text-white mb-8 text-shadow-strong">
                        Join FitClub today and experience the ultimate fitness journey with personalized training, expert coaches, and a supportive community.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="register.php" class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-white bg-gray-900 hover:bg-gray-800 rounded-lg transition duration-300">
                            <i class="fas fa-dumbbell mr-2"></i>
                            Start Your Journey
                        </a>
                        <a href="#features" class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-white bg-transparent border-2 border-white hover:bg-white hover:text-gray-900 rounded-lg transition duration-300">
                            <i class="fas fa-info-circle mr-2"></i>
                            Learn More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-block px-3 py-1 text-sm font-semibold text-gray-600 bg-gray-100 rounded-full mb-3">FEATURES</span>
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Why Choose FitClub?</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Experience the difference with our comprehensive fitness solutions tailored to your unique needs and goals</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="feature-card bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition duration-300">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-user-md text-2xl text-gray-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Expert Coaches</h3>
                    <p class="text-gray-600">
                        Get personalized training from certified fitness professionals specializing in strength, weight loss, and muscle building.
                    </p>
                </div>

                <div class="feature-card bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition duration-300">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-chart-line text-2xl text-gray-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Progress Tracking</h3>
                    <p class="text-gray-600">
                        Monitor your fitness journey with detailed assessments, measurements, and performance analytics.
                    </p>
                </div>

                <div class="feature-card bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition duration-300">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-utensils text-2xl text-gray-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Nutrition Plans</h3>
                    <p class="text-gray-600">
                        Custom diet plans with balanced macros designed specifically for your fitness goals and preferences.
                    </p>
                </div>

                <div class="feature-card bg-white p-8 rounded-xl shadow-lg hover:shadow-xl transition duration-300">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-users text-2xl text-gray-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Community Support</h3>
                    <p class="text-gray-600">
                        Join a supportive community of fitness enthusiasts and stay motivated with direct coach communication.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Meet Our Coaches Section -->
    <div id="coaches" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-block px-3 py-1 text-sm font-semibold text-gray-600 bg-gray-100 rounded-full mb-3">EXPERT TEAM</span>
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Meet Our Certified Coaches</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Our team of experienced fitness professionals is dedicated to helping you achieve your fitness goals</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                // Get coaches information
                $query = "SELECT u.fullname, u.email, c.specialization, c.experience_years, c.rating, c.clients_count 
                          FROM coaches c 
                          JOIN users u ON c.user_id = u.id 
                          WHERE c.active = 1";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $specialization_icons = [
                    'bulk' => 'fa-dumbbell',
                    'weight_loss' => 'fa-weight-scale',
                    'strength' => 'fa-fist-raised'
                ];
                
                $specialization_names = [
                    'bulk' => 'Muscle Building',
                    'weight_loss' => 'Weight Loss',
                    'strength' => 'Strength Training'
                ];
                
                while ($coach = $stmt->fetch(PDO::FETCH_ASSOC)):
                    $icon = $specialization_icons[$coach['specialization']] ?? 'fa-dumbbell';
                    $specialization = $specialization_names[$coach['specialization']] ?? ucfirst($coach['specialization']);
                ?>
                <div class="coach-card bg-white rounded-xl overflow-hidden shadow-lg">
                    <div class="bg-gray-900 h-24 flex items-center justify-center">
                        <i class="fas <?php echo $icon; ?> text-4xl text-white"></i>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($coach['fullname']); ?></h3>
                        <p class="text-gray-600 font-medium mb-4"><?php echo $specialization; ?> Specialist</p>
                        
                        <div class="flex items-center mb-2">
                            <i class="fas fa-star text-yellow-400 mr-1"></i>
                            <span class="text-gray-700"><?php echo number_format($coach['rating'], 1); ?> Rating</span>
                        </div>
                        
                        <div class="flex items-center mb-2">
                            <i class="fas fa-users text-gray-500 mr-1"></i>
                            <span class="text-gray-700"><?php echo $coach['clients_count']; ?> Clients</span>
                        </div>
                        
                        <div class="flex items-center mb-4">
                            <i class="fas fa-history text-gray-500 mr-1"></i>
                            <span class="text-gray-700"><?php echo $coach['experience_years']; ?> Years Experience</span>
                        </div>
                        
                        <a href="register.php" class="inline-block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-300">
                            Train with <?php echo explode(' ', htmlspecialchars($coach['fullname']))[0]; ?>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Membership Plans Section -->
    <div id="plans" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-block px-3 py-1 text-sm font-semibold text-gray-600 bg-gray-100 rounded-full mb-3">PRICING</span>
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Membership Plans</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Choose the perfect membership plan that fits your fitness goals and budget</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                // Get membership plans
                $query = "SELECT * FROM membership_plans WHERE active = 1 ORDER BY price ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                while ($plan = $stmt->fetch(PDO::FETCH_ASSOC)):
                    $features = explode("\r\n", $plan['features']);
                ?>
                <div class="membership-card bg-white border <?php echo ($plan['name'] == 'Pro') ? 'border-gray-900 ring-2 ring-gray-900' : 'border-gray-200'; ?> rounded-xl overflow-hidden shadow-lg flex flex-col">
                    <?php if ($plan['name'] == 'Pro'): ?>
                    <div class="bg-gray-900 text-white text-center py-2 font-semibold">
                        MOST POPULAR
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-6 flex-grow">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($plan['name']); ?></h3>
                        <div class="flex items-baseline mb-6">
                            <span class="text-4xl font-extrabold text-gray-900">₱<?php echo number_format($plan['price'], 0); ?></span>
                            <span class="text-gray-500 ml-1">/month</span>
                        </div>
                        
                        <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($plan['description']); ?></p>
                        
                        <ul class="space-y-3 mb-6">
                            <?php foreach ($features as $feature): ?>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mt-1 mr-2"></i>
                                <span class="text-gray-700"><?php echo htmlspecialchars($feature); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="px-6 pb-6">
                        <a href="register.php" class="block w-full text-center <?php echo ($plan['name'] == 'Pro') ? 'bg-gray-900 hover:bg-gray-800 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700'; ?> font-medium py-3 px-4 rounded-lg transition duration-300">
                            Get Started
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Stats Section with Animation -->
    <div class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="inline-block px-3 py-1 text-sm font-semibold text-gray-600 bg-gray-100 rounded-full mb-3">OUR IMPACT</span>
                <h2 class="text-4xl font-bold text-gray-900 mb-4">FitClub by the Numbers</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Join our growing community of fitness enthusiasts</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                // Get stats
                $query = "SELECT COUNT(*) as total FROM users WHERE role = 'member'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $members_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                $query = "SELECT COUNT(*) as total FROM users WHERE role = 'coach'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $coaches_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                $query = "SELECT COUNT(*) as total FROM pre_assessments WHERE completed = 1";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $success_stories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                $stats = [
                    [
                        'value' => $members_count,
                        'label' => 'Active Members',
                        'icon' => 'fa-users'
                    ],
                    [
                        'value' => $coaches_count,
                        'label' => 'Expert Coaches',
                        'icon' => 'fa-user-md'
                    ],
                    [
                        'value' => $success_stories,
                        'label' => 'Success Stories',
                        'icon' => 'fa-trophy'
                    ]
                ];

                foreach ($stats as $stat):
                ?>
                <div class="bg-white p-8 rounded-xl shadow-lg text-center hover:shadow-xl transition duration-300">
                    <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-6">
                        <i class="fas <?php echo $stat['icon']; ?> text-2xl text-gray-600"></i>
                    </div>
                    <div class="text-4xl font-bold text-gray-900 mb-2"><?php echo $stat['value']; ?>+</div>
                    <div class="text-gray-600 font-medium"><?php echo $stat['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="cta-section py-16 bg-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-white mb-6">Ready to Transform Your Life?</h2>
            <p class="text-xl text-indigo-100 mb-8 max-w-3xl mx-auto">Join FitClub today and start your fitness journey with expert guidance, personalized plans, and a supportive community.</p>
            <div class="flex justify-center gap-4">
                <a href="register.php" class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium bg-white text-gray-900 hover:bg-gray-100 rounded-lg transition duration-300">
                    <i class="fas fa-user-plus mr-2"></i>
                    Sign Up Now
                </a>
                <a href="login.php" class="inline-flex items-center justify-center px-8 py-4 text-lg font-medium text-white bg-transparent border-2 border-white hover:bg-white hover:text-gray-900 rounded-lg transition duration-300">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Log In
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <h3 class="text-gray-900 text-lg font-semibold mb-4">About FitClub</h3>
                    <p class="text-gray-600 mb-4">
                        Your premier destination for fitness and wellness. We're committed to helping you achieve your fitness goals through personalized training and expert guidance.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-500 hover:text-gray-900 transition-colors">
                            <i class="fab fa-facebook-f text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-gray-900 transition-colors">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-gray-900 transition-colors">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-gray-900 transition-colors">
                            <i class="fab fa-youtube text-xl"></i>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="text-gray-900 text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#features" class="text-gray-600 hover:text-gray-900 transition duration-300">Features</a></li>
                        <li><a href="#coaches" class="text-gray-600 hover:text-gray-900 transition duration-300">Coaches</a></li>
                        <li><a href="#plans" class="text-gray-600 hover:text-gray-900 transition duration-300">Membership</a></li>
                        <li><a href="login.php" class="text-gray-600 hover:text-gray-900 transition duration-300">Login</a></li>
                        <li><a href="register.php" class="text-gray-600 hover:text-gray-900 transition duration-300">Register</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-gray-900 text-lg font-semibold mb-4">Contact Us</h3>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-center">
                            <i class="fas fa-phone mr-2 text-gray-600"></i>
                            (123) 456-7890
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2 text-gray-600"></i>
                            info@fitclub.com
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-2 text-gray-600"></i>
                            123 Fitness Street, Gym City
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 border-t border-gray-200 pt-8">
                <p class="text-center text-gray-600">&copy; 2025 FitClub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html> 