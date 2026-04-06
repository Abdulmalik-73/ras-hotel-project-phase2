<?php
/**
 * Chapa Integration Test Page
 * Use this to verify Chapa is configured correctly
 */

require_once 'includes/config.php';
require_once 'includes/services/ChapaPaymentService.php';

$chapa = new ChapaPaymentService();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapa Integration Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-check-circle"></i> Chapa Integration Test</h4>
                    </div>
                    <div class="card-body">
                        <h5>Configuration Status</h5>
                        
                        <?php if ($chapa->isConfigured()): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <strong>Chapa is configured correctly!</strong>
                            </div>
                            
                            <table class="table table-bordered">
                                <tr>
                                    <th width="200">Public Key</th>
                                    <td>
                                        <code><?php echo substr($chapa->getPublicKey(), 0, 20); ?>...</code>
                                        <?php if (strpos($chapa->getPublicKey(), 'TEST') !== false): ?>
                                            <span class="badge bg-warning">Test Mode</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Live Mode</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Secret Key</th>
                                    <td><code>••••••••••••••••••••</code> (Hidden for security)</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td><span class="badge bg-success">Ready to Accept Payments</span></td>
                                </tr>
                            </table>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Next Steps:</h6>
                                <ol class="mb-0">
                                    <li>Make a test booking</li>
                                    <li>Click "Pay Now with Chapa" button</li>
                                    <li>Use test card: <code>4200 0000 0000 0000</code></li>
                                    <li>Payment will be verified automatically</li>
                                </ol>
                            </div>
                            
                            <div class="mt-3">
                                <a href="booking.php" class="btn btn-primary">
                                    <i class="fas fa-calendar-check"></i> Make a Test Booking
                                </a>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-home"></i> Back to Home
                                </a>
                            </div>
                            
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Chapa is NOT configured!</strong>
                            </div>
                            
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-wrench"></i> Setup Instructions:</h6>
                                <ol>
                                    <li>Open your <code>.env</code> file</li>
                                    <li>Add your Chapa API keys:
                                        <pre class="mt-2 p-2 bg-light">CHAPA_SECRET_KEY=CHASECK_TEST-your-secret-key-here
CHAPA_PUBLIC_KEY=CHAPUBK_TEST-your-public-key-here
CHAPA_TEST_MODE=true
CHAPA_CALLBACK_URL=http://localhost/final-project2/api/chapa/callback.php
CHAPA_RETURN_URL=http://localhost/final-project2/payment-success.php</pre>
                                    </li>
                                    <li>Get your keys from: <a href="https://dashboard.chapa.co" target="_blank">Chapa Dashboard</a></li>
                                    <li>Refresh this page</li>
                                </ol>
                            </div>
                            
                            <div class="mt-3">
                                <a href="https://dashboard.chapa.co" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt"></i> Open Chapa Dashboard
                                </a>
                                <button onclick="location.reload()" class="btn btn-secondary">
                                    <i class="fas fa-sync"></i> Refresh Page
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <h5>Environment Variables Check</h5>
                        <table class="table table-sm">
                            <tr>
                                <th>Variable</th>
                                <th>Status</th>
                            </tr>
                            <tr>
                                <td>CHAPA_SECRET_KEY</td>
                                <td>
                                    <?php 
                                    $secret = getenv('CHAPA_SECRET_KEY') ?: (defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '');
                                    echo !empty($secret) ? '<span class="badge bg-success">Set</span>' : '<span class="badge bg-danger">Not Set</span>';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td>CHAPA_PUBLIC_KEY</td>
                                <td>
                                    <?php 
                                    $public = getenv('CHAPA_PUBLIC_KEY') ?: (defined('CHAPA_PUBLIC_KEY') ? CHAPA_PUBLIC_KEY : '');
                                    echo !empty($public) ? '<span class="badge bg-success">Set</span>' : '<span class="badge bg-danger">Not Set</span>';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td>CHAPA_TEST_MODE</td>
                                <td>
                                    <?php 
                                    $test_mode = getenv('CHAPA_TEST_MODE') ?: (defined('CHAPA_TEST_MODE') ? CHAPA_TEST_MODE : '');
                                    echo !empty($test_mode) ? '<span class="badge bg-success">' . $test_mode . '</span>' : '<span class="badge bg-danger">Not Set</span>';
                                    ?>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-secondary mt-3">
                            <small>
                                <strong>Note:</strong> If variables show "Not Set", make sure your <code>.env</code> file exists 
                                and contains the Chapa configuration. See <code>CHAPA_INTEGRATION_GUIDE.md</code> for details.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
