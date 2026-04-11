<?php
/**
 * Reusable Payment Component - Screenshot Upload Only
 * Supports: Room Booking, Food Orders, Spa & Wellness, Laundry Services
 */

// Ensure booking data is available
if (!isset($booking) || !$booking) {
    echo "<div class='alert alert-danger'>Booking information not found.</div>";
    return;
}

$booking_id = $booking['id'];
$total_amount = $booking['total_price'];
$booking_reference = $booking['booking_reference'];
?>

<div class="payment-container">
    <div class="payment-header">
        <h4><i class="fas fa-credit-card"></i> Payment Submission</h4>
        <p class="text-muted">Complete your payment using mobile money or bank transfer</p>
    </div>

    <!-- Payment Methods -->
    <div class="payment-methods">
        <h6 class="mb-3">Select Payment Method</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="payment-method-card" data-method="telebirr" data-account="0973409026">
                    <div class="method-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="method-info">
                        <h6>TeleBirr</h6>
                        <small class="text-muted">Ethio Telecom</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="payment-method-card" data-method="cbe" data-account="1000274236552">
                    <div class="method-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="method-info">
                        <h6>CBE Mobile Banking</h6>
                        <small class="text-muted">Commercial Bank of Ethiopia</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="payment-method-card" data-method="abyssinia" data-account="244422382">
                    <div class="method-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="method-info">
                        <h6>Abyssinia Bank</h6>
                        <small class="text-muted">Abyssinia Bank</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="payment-method-card" data-method="cooperative" data-account="1000056621528">
                    <div class="method-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="method-info">
                        <h6>Cooperative Bank of Oromia</h6>
                        <small class="text-muted">Cooperative Bank of Oromia</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bank Details Card (Hidden by default) -->
    <div class="bank-details-card" id="bankDetailsCard" style="display: none;">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Payment Details</h6>
                <div class="detail-row">
                    <span class="label">Account Holder:</span>
                    <span class="value">Harar Ras Hotel</span>
                </div>
                <div class="detail-row">
                    <span class="label">Account Number:</span>
                    <span class="value" id="accountNumber">-</span>
                    <button type="button" class="btn btn-sm btn-outline-primary copy-btn" id="copyBtn">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <div class="detail-row">
                    <span class="label">Amount:</span>
                    <span class="value font-weight-bold text-success"><?php echo number_format($total_amount, 2); ?> ETB</span>
                </div>
                <div class="detail-row">
                    <span class="label">Reference:</span>
                    <span class="value"><?php echo $booking_reference; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Screenshot Upload -->
    <div class="screenshot-upload-section">
        <h6 class="mb-3">Upload Payment Screenshot</h6>
        <div class="upload-area" id="uploadArea">
            <div class="upload-placeholder">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Click to upload or drag and drop</p>
                <small class="text-muted">JPG, PNG, JPEG (Max 2MB)</small>
            </div>
            <input type="file" id="screenshotInput" accept="image/jpeg,image/jpg,image/png" style="display: none;">
        </div>
        
        <!-- Image Preview -->
        <div class="image-preview" id="imagePreview" style="display: none;">
            <div class="preview-container">
                <img id="previewImage" src="" alt="Payment Screenshot">
                <button type="button" class="btn btn-sm btn-danger remove-image" id="removeImage">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="preview-filename" id="previewFilename"></p>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="submit-section">
        <button type="button" class="btn btn-primary btn-lg w-100" id="submitPayment" disabled>
            <i class="fas fa-paper-plane"></i> Submit Payment
        </button>
        <small class="text-muted d-block text-center mt-2">
            Your payment will be verified within 30 minutes
        </small>
    </div>
</div>



<style>
.payment-container {
    max-width: 600px;
    margin: 0 auto;
}

.payment-header {
    text-align: center;
    margin-bottom: 2rem;
}

.payment-methods {
    margin-bottom: 2rem;
}

.payment-method-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.payment-method-card:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.payment-method-card.selected {
    border-color: #007bff;
    background-color: #e3f2fd;
}

.method-icon {
    font-size: 1.5rem;
    color: #007bff;
    width: 40px;
    text-align: center;
}

.method-info h6 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
}

.method-info small {
    font-size: 0.75rem;
}

.bank-details-card {
    margin-bottom: 2rem;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    font-weight: 500;
    color: #6c757d;
}

.detail-row .value {
    font-weight: 600;
}

.copy-btn {
    margin-left: 0.5rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.screenshot-upload-section {
    margin-bottom: 2rem;
}

.upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-area:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.upload-area.dragover {
    border-color: #007bff;
    background-color: #e3f2fd;
}

.upload-placeholder i {
    font-size: 2rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
}

.image-preview {
    margin-top: 1rem;
}

.preview-container {
    position: relative;
    display: inline-block;
}

.preview-container img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.remove-image {
    position: absolute;
    top: -8px;
    right: -8px;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-filename {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #6c757d;
}

.submit-section {
    text-align: center;
}

.success-icon {
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    from { transform: scale(0); }
    to { transform: scale(1); }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .payment-method-card {
        padding: 0.75rem;
    }
    
    .method-info h6 {
        font-size: 0.85rem;
    }
    
    .upload-area {
        padding: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentCards = document.querySelectorAll('.payment-method-card');
    const bankDetailsCard = document.getElementById('bankDetailsCard');
    const accountNumber = document.getElementById('accountNumber');
    const copyBtn = document.getElementById('copyBtn');
    const uploadArea = document.getElementById('uploadArea');
    const screenshotInput = document.getElementById('screenshotInput');
    const imagePreview = document.getElementById('imagePreview');
    const previewImage = document.getElementById('previewImage');
    const previewFilename = document.getElementById('previewFilename');
    const removeImage = document.getElementById('removeImage');
    const submitPayment = document.getElementById('submitPayment');
    
    let selectedMethod = null;
    let uploadedFile = null;
    
    // Payment method selection
    paymentCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove previous selection
            paymentCards.forEach(c => c.classList.remove('selected'));
            
            // Select current card
            this.classList.add('selected');
            selectedMethod = this.dataset.method;
            
            // Show bank details
            accountNumber.textContent = this.dataset.account;
            bankDetailsCard.style.display = 'block';
            
            checkSubmitButton();
        });
    });
    
    // Copy account number
    copyBtn.addEventListener('click', function() {
        const account = accountNumber.textContent;
        navigator.clipboard.writeText(account).then(() => {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> Copied!';
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-success');
            
            setTimeout(() => {
                this.innerHTML = originalText;
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-primary');
            }, 2000);
        });
    });
    
    // File upload handling
    uploadArea.addEventListener('click', () => screenshotInput.click());
    
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    });
    
    screenshotInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            handleFileUpload(this.files[0]);
        }
    });
    
    function handleFileUpload(file) {
        // Validate file
        if (!file.type.match(/^image\/(jpeg|jpg|png)$/)) {
            alert('Please upload a valid image file (JPG, PNG, JPEG)');
            return;
        }
        
        if (file.size > 2 * 1024 * 1024) { // 2MB
            alert('File size must be less than 2MB');
            return;
        }
        
        uploadedFile = file;
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewFilename.textContent = file.name;
            uploadArea.style.display = 'none';
            imagePreview.style.display = 'block';
            checkSubmitButton();
        };
        reader.readAsDataURL(file);
    }
    
    // Remove image
    removeImage.addEventListener('click', function() {
        uploadedFile = null;
        screenshotInput.value = '';
        uploadArea.style.display = 'block';
        imagePreview.style.display = 'none';
        checkSubmitButton();
    });
    
    function checkSubmitButton() {
        submitPayment.disabled = !(selectedMethod && uploadedFile);
    }
    
    // Submit payment
    submitPayment.addEventListener('click', function() {
        if (!selectedMethod || !uploadedFile) {
            alert('Please select a payment method and upload a screenshot');
            return;
        }
        
        const formData = new FormData();
        formData.append('booking_id', <?php echo $booking_id; ?>);
        formData.append('payment_method', selectedMethod);
        formData.append('screenshot', uploadedFile);
        
        // Show loading
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        fetch('api/submit_payment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Small delay to ensure database is updated, then redirect
                setTimeout(() => {
                    window.location.href = 'payment-success.php?booking=' + data.data.booking_id;
                }, 500);
            } else {
                alert('Error: ' + data.message);
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Payment';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Payment';
        });
    });
});
</script>