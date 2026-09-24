<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['membership_id'])) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get membership and payment details
$query = "SELECT m.*, mp.name as plan_name, mp.price, p.status as payment_status, p.qr_code_path,
          c.specialization, u.fullname as coach_name, p.reference_number
          FROM user_memberships m
          JOIN membership_plans mp ON m.plan_id = mp.id
          JOIN payments p ON m.id = p.user_membership_id
          LEFT JOIN coaches c ON m.coach_id = c.id
          LEFT JOIN users u ON c.user_id = u.id
          WHERE m.id = :membership_id AND m.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":membership_id", $_GET['membership_id']);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: dashboard.php");
    exit();
}

$membership = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate QR code for payment only if it does not exist
if (empty($membership['qr_code_path']) || !file_exists(__DIR__ . '/../assets/qr/' . $membership['qr_code_path'])) {
    // Generate unique reference number if not exists
    if (empty($membership['reference_number'])) {
        $reference_number = 'FIT' . date('Ymd') . str_pad($membership['id'], 4, '0', STR_PAD_LEFT);
        
        // Update payment record with reference number
        $query = "UPDATE payments SET reference_number = :reference_number WHERE user_membership_id = :membership_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':reference_number', $reference_number);
        $stmt->bindParam(':membership_id', $membership['id']);
        $stmt->execute();
        
        $membership['reference_number'] = $reference_number;
    }

    // Create QR code with payment details
    $qrData = json_encode([

        'message' => "Ma'am/Sir, Kahit 2.0 lang po!, Thank you for choosing FitClub! Please pay the exact amount of $" . number_format($membership['price'], 2) . 
        " for your " . $membership['plan_name'] . " membership. Use reference number: " . $membership['reference_number'] . 
        " when making the payment. Your membership will be activated after payment verification."
    ]);
    
    $qrCode = QrCode::create($qrData)
        ->setSize(300)
        ->setMargin(10)
        ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());

    $writer = new PngWriter();
    $qrResult = $writer->write($qrCode);

    $qrDir = __DIR__ . '/../assets/qr/';
    if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);

    $qrFilename = "qr_payment_{$membership['id']}.png";
    $qrPath = $qrDir . $qrFilename;
    $qrResult->saveToFile($qrPath);

    // Update payment record with QR code path
    $query = "UPDATE payments SET qr_code_path = :qr_code_path WHERE user_membership_id = :membership_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':qr_code_path', $qrFilename);
    $stmt->bindParam(':membership_id', $membership['id']);
    $stmt->execute();
    
    $membership['qr_code_path'] = $qrFilename;
}

// Remove duplicate receipt upload handling since it's now in process_payment.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: process_payment.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .receipt-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .receipt-card:hover {
            transform: translateY(-5px);
        }
        .qr-container {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .qr-container:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        .detail-item {
            transition: all 0.3s ease;
        }
        .detail-item:hover {
            transform: translateX(5px);
        }
        .upload-btn {
            transition: all 0.3s ease;
        }
        .upload-btn:hover {
            transform: scale(1.02);
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }

        /* Receipt Print Styles */
        .receipt-print {
            display: none;
        }

        @media print {
            nav, button, .upload-btn, .qr-container, form, .receipt-card > div:not(.receipt-print) {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                background: white !important;
                padding: 0 !important;
            }
            .receipt-print {
                display: block !important;
                width: 210mm; /* A4 width */
                margin: 0 auto;
                padding: 20mm; /* Standard margin */
                font-family: 'Arial', sans-serif;
            }
            .receipt-header {
                text-align: center;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
            .receipt-title {
                font-size: 32px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .receipt-subtitle {
                font-size: 24px;
                margin-bottom: 10px;
            }
            .receipt-details {
                font-size: 18px;
                margin-bottom: 20px;
            }
            .receipt-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                padding: 5px 0;
            }
            .receipt-total {
                border-top: 2px solid #000;
                margin-top: 20px;
                padding-top: 20px;
                font-weight: bold;
                font-size: 20px;
            }
            .receipt-footer {
                text-align: center;
                font-size: 16px;
                margin-top: 30px;
                border-top: 2px solid #000;
                padding-top: 20px;
            }
            @page {
                size: A4;
                margin: 0;
            }
            body {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../index.php" class="flex items-center">
                        <i class="fas fa-dumbbell text-indigo-600 text-2xl"></i>
                        <span class="ml-2 text-xl font-bold text-gray-900">FitClub</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900 transition-colors duration-200">
                        <i class="fas fa-arrow-left"></i>
                        <span class="ml-2">Back to Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-8 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="receipt-card p-8">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                        <i class="fas fa-receipt text-2xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Payment Receipt</h1>
                    <p class="text-gray-600 mt-2">Complete your payment to activate your membership</p>
                </div>

                <div class="receipt-print">
                    <div class="receipt-header">
                        <div class="receipt-title">FITCLUB</div>
                        <div class="receipt-subtitle">Official Receipt</div>
                        <div class="receipt-details">
                            <div>Date: <?php echo date('Y-m-d H:i:s'); ?></div>
                            <div>Receipt #: <?php echo htmlspecialchars($membership['reference_number']); ?></div>
                        </div>
                    </div>

                    <div class="receipt-details">
                        <div class="receipt-item">
                            <span>Plan:</span>
                            <span><?php echo htmlspecialchars($membership['plan_name']); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span>Coach:</span>
                            <?php if ($membership['coach_name']): ?>
                                <span><?php echo htmlspecialchars($membership['coach_name']); ?></span>
                            <?php else: ?>
                                <span>No Coach Assigned</span>
                            <?php endif; ?>
                        </div>
                        <div class="receipt-item">
                            <span>Specialization:</span>
                            <span><?php echo ucwords(str_replace('_', ' ', $membership['specialization'])); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span>Start Date:</span>
                            <span><?php echo date('M d, Y', strtotime($membership['start_date'])); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span>End Date:</span>
                            <span><?php echo date('M d, Y', strtotime($membership['end_date'])); ?></span>
                        </div>
                    </div>

                    <div class="receipt-total">
                        <div class="receipt-item">
                            <span>Total Amount:</span>
                            <span>$<?php echo number_format($membership['price'], 2); ?></span>
                        </div>
                    </div>

                    <div class="receipt-footer">
                        <div>Thank you for choosing FitClub!</div>
                        <div>Please keep this receipt for your records</div>
                        <div>For inquiries, please contact our support</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Receipt Details -->
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-info-circle text-indigo-600 mr-2"></i>
                            Membership Details
                        </h2>
                        <div class="space-y-6">
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Plan</p>
                                <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($membership['plan_name']); ?></p>
                            </div>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Amount</p>
                                <p class="text-3xl font-bold text-indigo-600">$<?php echo number_format($membership['price'], 2); ?></p>
                            </div>
                            <?php if (!empty($membership['reference_number'])): ?>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Reference Number</p>
                                <p class="text-base font-medium text-gray-900 font-mono"><?php echo htmlspecialchars($membership['reference_number']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Coach</p>
                                <?php if ($membership['coach_name']): ?>
                                    <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($membership['coach_name']); ?></p>
                                    <p class="text-sm text-indigo-500"><?php echo ucwords(str_replace('_', ' ', $membership['specialization'])); ?> Specialist</p>
                                <?php else: ?>
                                    <p class="text-lg font-medium text-gray-900">No Coach Assigned</p>
                                    <p class="text-sm text-gray-500">Basic Plan</p>
                                <?php endif; ?>
                            </div>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Membership Period</p>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-calendar text-indigo-600"></i>
                                    <p class="text-base font-medium text-gray-900">
                                        <?php echo date('M d, Y', strtotime($membership['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($membership['end_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Payment -->
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-qrcode text-indigo-600 mr-2"></i>
                            Payment Instructions
                        </h2>
                        <div class="space-y-6">
                            <div class="qr-container flex justify-center pulse-animation">
                                <img src="../assets/qr/<?php echo $membership['qr_code_path']; ?>" 
                                     alt="Payment QR Code" 
                                     class="w-64 h-64 object-contain">
                            </div>
                            
                            <!-- Receipt Upload Form -->
                            <form action="process_payment.php" method="POST" enctype="multipart/form-data" class="mt-6 space-y-4">
                                <input type="hidden" name="membership_id" value="<?php echo htmlspecialchars($_GET['membership_id']); ?>">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        <i class="fas fa-upload mr-2"></i>
                                        Upload Payment Receipt
                                    </label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-indigo-500 transition-colors duration-200">
                                        <div class="space-y-1 text-center">
                                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400"></i>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="receipt" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                    <span>Upload a file</span>
                                                    <input id="receipt" name="receipt" type="file" class="sr-only" accept="image/*" required>
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="upload-btn w-full inline-flex justify-center items-center px-4 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Submit Receipt
                                </button>
                            </form>

                            <?php if (!empty($membership['receipt_path'])): ?>
                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-700 mb-2">Your uploaded receipt:</p>
                                    <img src="../assets/receipts/<?php echo htmlspecialchars($membership['receipt_path']); ?>" 
                                         alt="Receipt" 
                                         class="w-48 rounded-lg shadow-sm">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-center">
                    <button onclick="window.print()" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-lg transition-all duration-200 hover:scale-105">
                        <i class="fas fa-print mr-2"></i>
                        Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </main>

    <style>
        @media print {
            nav, button {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none !important;
                background: white !important;
            }
            .qr-container {
                box-shadow: none !important;
            }
        }
    </style>

    <script>
        // Add drag and drop functionality
        const dropZone = document.querySelector('.border-dashed');
        const fileInput = document.querySelector('input[type="file"]');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('border-indigo-500');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-indigo-500');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
        }
    </script>
</body>
</html> 