<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$payment_id = $_GET['payment_id'] ?? null;
if (!$payment_id) {
    $_SESSION['error'] = "Invalid payment ID";
    header('Location: membership.php');
    exit();
}
// Get payment details (renewal only)
$query = "SELECT p.*, um.user_id, mp.name as plan_name, mp.duration FROM payments p JOIN user_memberships um ON p.user_membership_id = um.id JOIN membership_plans mp ON um.plan_id = mp.id WHERE p.id = :payment_id AND um.user_id = :user_id AND p.payment_type = 'renewal'";
$stmt = $db->prepare($query);
$stmt->bindParam(':payment_id', $payment_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    $_SESSION['error'] = "Renewal payment not found";
    header('Location: membership.php');
    exit();
}

// Generate QR code if it doesn't exist
if (empty($payment['qr_code_path']) || !file_exists(__DIR__ . '/../assets/qr/' . $payment['qr_code_path'])) {
    // Generate unique reference number if not exists
    if (empty($payment['reference_number'])) {
        $ref_prefix = "FIT" . date("Ymd");
        $query = "SELECT COUNT(*) as count FROM payments WHERE reference_number LIKE :prefix";
        $stmt = $db->prepare($query);
        $stmt->bindValue(":prefix", $ref_prefix . "%");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $ref_number = $ref_prefix . str_pad($count + 1, 4, "0", STR_PAD_LEFT);
        
        // Update payment record with reference number
        $query = "UPDATE payments SET reference_number = :reference_number WHERE id = :payment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':reference_number', $ref_number);
        $stmt->bindParam(':payment_id', $payment_id);
        $stmt->execute();
        
        $payment['reference_number'] = $ref_number;
    }

    // Create QR code with payment details
    $qrData = json_encode([
        'message' => "Ma'am/Sir, Thank you for renewing your FitClub membership! Please pay the exact amount of ₱" . 
        number_format($payment['amount'], 2) . " for your " . $payment['plan_name'] . " membership renewal. " .
        "Use reference number: " . $payment['reference_number'] . 
        " when making the payment. Your renewal will be processed after payment verification."
    ]);
    
    $qrCode = new QrCode($qrData);
    $qrCode->setSize(300);
    $qrCode->setMargin(10);
    $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());

    $writer = new PngWriter();
    $qrResult = $writer->write($qrCode);

    $qrDir = __DIR__ . '/../assets/qr/';
    if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);

    $qrFilename = "qr_renewal_{$payment_id}.png";
    $qrPath = $qrDir . $qrFilename;
    $qrResult->saveToFile($qrPath);

    // Update payment record with QR code path
    $query = "UPDATE payments SET qr_code_path = :qr_code_path WHERE id = :payment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':qr_code_path', $qrFilename);
    $stmt->bindParam(':payment_id', $payment_id);
    $stmt->execute();
    
    $payment['qr_code_path'] = $qrFilename;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renewal Payment Receipt - FitClub</title>
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
<?php include 'components/navigation.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="max-w-3xl mx-auto">
            <div class="receipt-card p-8">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                        <i class="fas fa-receipt text-2xl text-indigo-600"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Renewal Payment Receipt</h1>
                    <p class="text-gray-600 mt-2">Complete your payment to renew your membership</p>
                </div>

                <div class="receipt-print">
                    <div class="receipt-header">
                        <div class="receipt-title">FITCLUB</div>
                        <div class="receipt-subtitle">Renewal Payment Receipt</div>
                        <div class="receipt-details">
                            <div>Date: <?php echo date('Y-m-d H:i:s'); ?></div>
                            <div>Receipt #: <?php echo htmlspecialchars($payment['reference_number']); ?></div>
                        </div>
                    </div>

                    <div class="receipt-details">
                        <div class="receipt-item">
                            <span>Plan:</span>
                            <span><?php echo htmlspecialchars($payment['plan_name']); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span>Duration:</span>
                            <span><?php echo $payment['duration']; ?> month(s)</span>
                        </div>
                        <div class="receipt-item">
                            <span>Status:</span>
                            <span><?php echo ucfirst($payment['status']); ?></span>
                        </div>
                        <div class="receipt-item">
                            <span>Date:</span>
                            <span><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></span>
                        </div>
                    </div>

                    <div class="receipt-total">
                        <div class="receipt-item">
                            <span>Total Amount:</span>
                            <span>₱<?php echo number_format($payment['amount'], 2); ?></span>
                        </div>
                    </div>

                    <div class="receipt-footer">
                        <div>Thank you for renewing your FitClub membership!</div>
                        <div>Please keep this receipt for your records</div>
                        <div>For inquiries, please contact our support</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Receipt Details -->
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-info-circle text-indigo-600 mr-2"></i>
                            Payment Details
                        </h2>
                        <div class="space-y-6">
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Plan</p>
                                <p class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($payment['plan_name']); ?></p>
                            </div>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Duration</p>
                                <p class="text-lg font-medium text-gray-900"><?php echo $payment['duration']; ?> month(s)</p>
                            </div>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Amount</p>
                                <p class="text-3xl font-bold text-indigo-600">₱<?php echo number_format($payment['amount'], 2); ?></p>
                            </div>
                            <?php if (!empty($payment['reference_number'])): ?>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Reference Number</p>
                                <p class="text-base font-medium text-gray-900 font-mono"><?php echo htmlspecialchars($payment['reference_number']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="font-medium">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold
                                        <?php echo $payment['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                            ($payment['status'] === 'failed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                        <i class="fas <?php echo $payment['status'] === 'completed' ? 'fa-check-circle' : ($payment['status'] === 'failed' ? 'fa-times-circle' : 'fa-clock'); ?> mr-1"></i>
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="detail-item">
                                <p class="text-sm text-gray-500">Date</p>
                                <p class="text-base font-medium text-gray-900"><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <?php if ($payment['status'] === 'pending'): ?>
                    <!-- QR Code Payment -->
                    <div class="bg-white rounded-xl p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center">
                            <i class="fas fa-qrcode text-indigo-600 mr-2"></i>
                            Payment Instructions
                        </h2>
                        <div class="space-y-6">
                            <div class="qr-container flex justify-center pulse-animation">
                                <?php if ($payment['qr_code_path']): ?>
                                    <img src="../assets/qr/<?php echo $payment['qr_code_path']; ?>" 
                                         alt="Payment QR Code" 
                                         class="w-64 h-64 object-contain">
                                <?php else: ?>
                                    <div class="flex items-center justify-center h-64">
                                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Receipt Upload Form -->
                            <form action="process_renewal_payment.php" method="POST" enctype="multipart/form-data" class="mt-6 space-y-4" id="receiptForm">
                                <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
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

                            <?php if (!empty($payment['receipt_path'])): ?>
                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-700 mb-2">Your uploaded receipt:</p>
                                    <img src="../uploads/receipts/<?php echo htmlspecialchars($payment['receipt_path']); ?>" 
                                         alt="Receipt" 
                                         class="w-48 rounded-lg shadow-sm">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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

    <script>
        // Add drag and drop functionality
        const dropZone = document.querySelector('.border-dashed');
        const fileInput = document.querySelector('input[type="file"]');

        if (dropZone && fileInput) {
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
        }
    </script>
</body>
</html> 