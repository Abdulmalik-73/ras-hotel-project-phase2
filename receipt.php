<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user has permission
if (!is_logged_in() || !in_array($_SESSION['user_role'], ['receptionist', 'manager', 'admin'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$receipt_data = null;

// Get checkin ID from URL
$checkin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($checkin_id) {
    // Get complete receipt data
    $query = "SELECT c.*, u.first_name as staff_name, u.last_name as staff_lastname
              FROM checkins c 
              LEFT JOIN users u ON c.created_by = u.id 
              WHERE c.id = ? AND c.bill_generated = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $checkin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $receipt_data = $result->fetch_assoc();
        
        // Calculate bill details
        $checkin_date = new DateTime($receipt_data['checkin_date']);
        $checkout_date = $receipt_data['checkout_date'] ? new DateTime($receipt_data['checkout_date']) : new DateTime();
        $days = max(1, $checkin_date->diff($checkout_date)->days);
        
        $room_charges = $receipt_data['price'] * $days;
        $service_tax = $receipt_data['service_tax'] ?? ($room_charges * 0.12);
        $vat = $receipt_data['vat'] ?? ($room_charges * 0.15);
        $additional_charges = $receipt_data['additional_charges'] ?? 0;
        $discount = $receipt_data['discount'] ?? 0;
        $total_amount = $receipt_data['total_amount'] ?? ($room_charges + $service_tax + $vat + $additional_charges - $discount);
        
        $receipt_data['days'] = $days;
        $receipt_data['room_charges'] = $room_charges;
        $receipt_data['service_tax'] = $service_tax;
        $receipt_data['vat'] = $vat;
        $receipt_data['additional_charges'] = $additional_charges;
        $receipt_data['discount'] = $discount;
        $receipt_data['final_amount'] = $total_amount;
        $receipt_data['checkout_date_display'] = $checkout_date->format('Y-m-d H:i:s');
        $receipt_data['receipt_number'] = 'RCP-' . str_pad($checkin_id, 6, '0', STR_PAD_LEFT);
    } else {
        $error = 'Receipt not found or bill not generated yet';
    }
} else {
    $error = 'Invalid receipt ID';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Harar Ras Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/print.css">
    <style>
        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .receipt-header {
            background: linear-gradient(135deg, #d4af37, #f4d03f);
            color: #333;
            padding: 30px;
            text-align: center;
        }
        
        .hotel-logo {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .hotel-name {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .hotel-address {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        .receipt-body {
            padding: 30px;
        }
        
        .receipt-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 30px;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 10px;
        }
        
        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section h6 {
            color: #d4af37;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .billing-table {
            width: 100%;
            margin: 30px 0;
            border-collapse: collapse;
        }
        
        .billing-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #d4af37;
            font-weight: bold;
            color: #333;
        }
        
        .billing-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
        }
        
        .billing-table .amount {
            text-align: right;
            font-weight: 600;
        }
        
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .total-row td {
            border-top: 2px solid #d4af37;
            border-bottom: 2px solid #d4af37;
        }
        
        .receipt-footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .footer-note {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-top: 30px;
            text-align: center;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 5px;
            margin-top: 40px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .action-buttons {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn-custom {
            margin: 0 10px;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .btn-print {
            background: #d4af37;
            border-color: #d4af37;
            color: #333;
        }
        
        .btn-print:hover {
            background: #b8941f;
            border-color: #b8941f;
            color: #333;
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div class="container mt-5">
            <div class="alert alert-danger text-center">
                <h4>Error</h4>
                <p><?php echo $error; ?></p>
                <a href="generate_bill.php" class="btn btn-primary">Back to Billing</a>
            </div>
        </div>
    <?php elseif ($receipt_data): ?>
        <div class="action-buttons no-print">
            <a href="generate_bill.php" class="btn btn-secondary btn-custom">
                <i class="fas fa-arrow-left"></i> Back to Billing
            </a>
            <button onclick="window.print()" class="btn btn-print btn-custom">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button onclick="downloadPDF()" class="btn btn-success btn-custom">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>
        
        <div class="receipt-container" id="receipt">
            <!-- Receipt Header -->
            <div class="receipt-header">
                <div class="hotel-logo">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="hotel-name">HARAR RAS HOTEL</div>
                <div class="hotel-address">
                    Harar, Ethiopia<br>
                    Phone: +251-25-666-0000 | Email: info@hararrashotel.com
                </div>
            </div>
            
            <!-- Receipt Body -->
            <div class="receipt-body">
                <div class="receipt-title">
                    CUSTOMER RECEIPT
                </div>
                
                <!-- Receipt Information -->
                <div class="receipt-info">
                    <div class="info-section">
                        <h6><i class="fas fa-receipt"></i> Receipt Details</h6>
                        <div class="info-row">
                            <span class="info-label">Receipt No:</span>
                            <span class="info-value"><?php echo $receipt_data['receipt_number']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date Issued:</span>
                            <span class="info-value"><?php echo date('M j, Y H:i'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Issued By:</span>
                            <span class="info-value"><?php echo htmlspecialchars($receipt_data['staff_name'] . ' ' . $receipt_data['staff_lastname']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Payment Method:</span>
                            <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $receipt_data['payment_method'] ?? 'Cash')); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h6><i class="fas fa-user"></i> Customer Information</h6>
                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($receipt_data['customer_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($receipt_data['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Mobile:</span>
                            <span class="info-value"><?php echo htmlspecialchars($receipt_data['mobile']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Country:</span>
                            <span class="info-value"><?php echo htmlspecialchars($receipt_data['country']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Stay Information -->
                <div class="info-section mb-4">
                    <h6><i class="fas fa-bed"></i> Stay Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">Room Number:</span>
                                <span class="info-value"><?php echo $receipt_data['room_number']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Room Type:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt_data['room_type']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Bed Type:</span>
                                <span class="info-value"><?php echo htmlspecialchars($receipt_data['bed_type']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">Check-in:</span>
                                <span class="info-value"><?php echo date('M j, Y', strtotime($receipt_data['checkin_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Check-out:</span>
                                <span class="info-value"><?php echo date('M j, Y H:i', strtotime($receipt_data['checkout_date_display'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Total Days:</span>
                                <span class="info-value"><?php echo $receipt_data['days']; ?> day(s)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Billing Details -->
                <table class="billing-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Room Charges (<?php echo $receipt_data['days']; ?> days × <?php echo format_currency($receipt_data['price']); ?>)</td>
                            <td class="amount"><?php echo format_currency($receipt_data['room_charges']); ?></td>
                        </tr>
                        <tr>
                            <td>Service Tax (12%)</td>
                            <td class="amount"><?php echo format_currency($receipt_data['service_tax']); ?></td>
                        </tr>
                        <tr>
                            <td>VAT (15%)</td>
                            <td class="amount"><?php echo format_currency($receipt_data['vat']); ?></td>
                        </tr>
                        <?php if ($receipt_data['additional_charges'] > 0): ?>
                        <tr>
                            <td>Additional Charges</td>
                            <td class="amount"><?php echo format_currency($receipt_data['additional_charges']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($receipt_data['discount'] > 0): ?>
                        <tr>
                            <td>Discount</td>
                            <td class="amount text-success">-<?php echo format_currency($receipt_data['discount']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td><strong>TOTAL AMOUNT</strong></td>
                            <td class="amount"><strong><?php echo format_currency($receipt_data['final_amount']); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!empty($receipt_data['bill_notes'])): ?>
                <div class="info-section">
                    <h6><i class="fas fa-sticky-note"></i> Notes</h6>
                    <p><?php echo nl2br(htmlspecialchars($receipt_data['bill_notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Signature Section -->
                <div class="signature-section">
                    <div>
                        <div class="signature-line">Customer Signature</div>
                    </div>
                    <div>
                        <div class="signature-line">Hotel Representative</div>
                    </div>
                </div>
            </div>
            
            <!-- Receipt Footer -->
            <div class="receipt-footer">
                <div class="footer-note">
                    Thank you for staying with us at Harar Ras Hotel!<br>
                    We hope you enjoyed your stay and look forward to welcoming you again.
                </div>
                <div class="footer-note">
                    <strong>Cancellation Policy:</strong> Cancellations must be made 24 hours before check-in for full refund.<br>
                    <strong>Contact:</strong> For any queries, please contact us at +251-25-666-0000
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const receipt = document.getElementById('receipt');
            
            html2canvas(receipt, {
                scale: 2,
                useCORS: true,
                allowTaint: true
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                const imgWidth = 210;
                const pageHeight = 295;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                
                let position = 0;
                
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                pdf.save('receipt-<?php echo $receipt_data ? $receipt_data['receipt_number'] : 'unknown'; ?>.pdf');
            });
        }
    </script>
</body>
</html>