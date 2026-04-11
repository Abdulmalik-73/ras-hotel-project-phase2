<?php
/**
 * Email Templates for Harar Ras Hotel
 * Contains HTML email templates with inline CSS for compatibility
 */

class EmailTemplates {
    
    /**
     * Get base email template wrapper
     */
    public static function getBaseTemplate($content) {
        $hotelName = getenv('HOTEL_NAME') ?: 'Harar Ras Hotel';
        $hotelPhone = getenv('HOTEL_PHONE') ?: '+251-25-666-0000';
        $hotelEmail = getenv('HOTEL_SUPPORT_EMAIL') ?: 'support@hararrashotel.com';
        $hotelWebsite = getenv('HOTEL_WEBSITE_URL') ?: 'http://localhost/rashotel';
        $hotelAddress = getenv('HOTEL_ADDRESS') ?: 'Jugol Street, Harar, Ethiopia';
        
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $hotelName . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: bold;">
                                🏨 ' . $hotelName . '
                            </h1>
                            <p style="color: #ffffff; margin: 10px 0 0 0; font-size: 14px;">
                                Your Comfort, Our Priority
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            ' . $content . '
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #dee2e6;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #6c757d;">
                                <strong>' . $hotelName . '</strong>
                            </p>
                            <p style="margin: 0 0 10px 0; font-size: 13px; color: #6c757d;">
                                📍 ' . $hotelAddress . '
                            </p>
                            <p style="margin: 0 0 10px 0; font-size: 13px; color: #6c757d;">
                                📞 ' . $hotelPhone . ' | 📧 ' . $hotelEmail . '
                            </p>
                            <p style="margin: 15px 0 0 0; font-size: 13px; color: #6c757d;">
                                <a href="' . $hotelWebsite . '" style="color: #007bff; text-decoration: none;">Visit Our Website</a>
                            </p>
                            <p style="margin: 15px 0 0 0; font-size: 11px; color: #adb5bd;">
                                © ' . date('Y') . ' ' . $hotelName . '. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    
    /**
     * Room Booking Confirmation Template
     */
    public static function getRoomBookingTemplate($booking) {
        $customerName = htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']);
        $bookingRef = htmlspecialchars($booking['booking_reference']);
        $roomName = htmlspecialchars($booking['room_name']);
        $roomNumber = htmlspecialchars($booking['room_number']);
        $checkIn = date('F j, Y', strtotime($booking['check_in_date']));
        $checkOut = date('F j, Y', strtotime($booking['check_out_date']));
        $nights = (strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / (60 * 60 * 24);
        $totalAmount = number_format($booking['total_price'], 2);
        $paymentMethod = ucfirst($booking['payment_method'] ?? 'N/A');
        $guests = $booking['customers'] ?? 1;
        
        $content = '
<div style="text-align: center; margin-bottom: 30px;">
    <div style="display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; border-radius: 50px; font-size: 14px; font-weight: bold;">
        ✓ BOOKING CONFIRMED
    </div>
</div>

<h2 style="color: #333; margin: 0 0 10px 0; font-size: 24px;">Dear ' . $customerName . ',</h2>

<p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
    Thank you for choosing Harar Ras Hotel! We are delighted to confirm your room booking. Your reservation details are below:
</p>

<table width="100%" cellpadding="12" cellspacing="0" style="background-color: #f8f9fa; border-radius: 8px; margin: 20px 0;">
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Booking Reference:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            <span style="color: #007bff; font-weight: bold; font-size: 16px;">' . $bookingRef . '</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Room Type:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $roomName . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Room Number:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $roomNumber . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Check-in Date:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $checkIn . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Check-out Date:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $checkOut . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Number of Nights:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $nights . ' night(s)
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Number of Guests:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $guests . ' guest(s)
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Payment Method:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $paymentMethod . '
        </td>
    </tr>
    <tr>
        <td style="padding: 12px;">
            <strong style="color: #495057; font-size: 16px;">Total Amount Paid:</strong>
        </td>
        <td style="padding: 12px; text-align: right;">
            <strong style="color: #28a745; font-size: 18px;">ETB ' . $totalAmount . '</strong>
        </td>
    </tr>
</table>

<div style="background-color: #e3f2fd; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0; color: #0056b3; font-size: 14px;">
        <strong>📌 Important Information:</strong><br>
        • Check-in time: 2:00 PM<br>
        • Check-out time: 12:00 PM<br>
        • Please bring a valid ID for verification<br>
        • Early check-in/late check-out subject to availability
    </p>
</div>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0;">
    We look forward to welcoming you to Harar Ras Hotel. If you have any questions or special requests, please don\'t hesitate to contact us.
</p>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
    Best regards,<br>
    <strong>The Harar Ras Hotel Team</strong>
</p>';
        
        return self::getBaseTemplate($content);
    }

    
    /**
     * Food Order Confirmation Template
     */
    public static function getFoodOrderTemplate($order) {
        $customerName = htmlspecialchars($order['first_name'] . ' ' . $order['last_name']);
        $orderRef = htmlspecialchars($order['order_reference']);
        $bookingRef = htmlspecialchars($order['booking_reference']);
        $totalAmount = number_format($order['total_price'], 2);
        $orderStatus = ucfirst($order['status']);
        
        // Build items table
        $itemsHtml = '';
        if (!empty($order['items'])) {
            foreach ($order['items'] as $item) {
                $itemName = htmlspecialchars($item['item_name']);
                $quantity = $item['quantity'];
                $price = number_format($item['price'], 2);
                $itemTotal = number_format($item['total_price'], 2);
                
                $itemsHtml .= '
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">' . $itemName . '</td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: center;">' . $quantity . '</td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">ETB ' . $price . '</td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;"><strong>ETB ' . $itemTotal . '</strong></td>
    </tr>';
            }
        }
        
        $content = '
<div style="text-align: center; margin-bottom: 30px;">
    <div style="display: inline-block; background-color: #ffc107; color: #000; padding: 10px 20px; border-radius: 50px; font-size: 14px; font-weight: bold;">
        🍽️ ORDER CONFIRMED
    </div>
</div>

<h2 style="color: #333; margin: 0 0 10px 0; font-size: 24px;">Dear ' . $customerName . ',</h2>

<p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
    Thank you for your food order! Your order has been confirmed and our kitchen is preparing your delicious meal.
</p>

<table width="100%" cellpadding="12" cellspacing="0" style="background-color: #f8f9fa; border-radius: 8px; margin: 20px 0;">
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Order Reference:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            <span style="color: #ffc107; font-weight: bold; font-size: 16px;">' . $orderRef . '</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Booking Reference:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $bookingRef . '
        </td>
    </tr>
    <tr>
        <td style="padding: 12px;">
            <strong style="color: #495057;">Order Status:</strong>
        </td>
        <td style="padding: 12px; text-align: right;">
            <span style="color: #28a745; font-weight: bold;">' . $orderStatus . '</span>
        </td>
    </tr>
</table>

<h3 style="color: #333; margin: 30px 0 15px 0; font-size: 18px;">Order Items:</h3>

<table width="100%" cellpadding="12" cellspacing="0" style="border: 1px solid #dee2e6; border-radius: 8px;">
    <thead>
        <tr style="background-color: #f8f9fa;">
            <th style="border-bottom: 2px solid #dee2e6; padding: 12px; text-align: left;">Item</th>
            <th style="border-bottom: 2px solid #dee2e6; padding: 12px; text-align: center;">Qty</th>
            <th style="border-bottom: 2px solid #dee2e6; padding: 12px; text-align: right;">Price</th>
            <th style="border-bottom: 2px solid #dee2e6; padding: 12px; text-align: right;">Total</th>
        </tr>
    </thead>
    <tbody>
        ' . $itemsHtml . '
        <tr style="background-color: #f8f9fa;">
            <td colspan="3" style="padding: 12px; text-align: right;">
                <strong style="font-size: 16px;">Total Amount:</strong>
            </td>
            <td style="padding: 12px; text-align: right;">
                <strong style="color: #28a745; font-size: 18px;">ETB ' . $totalAmount . '</strong>
            </td>
        </tr>
    </tbody>
</table>

<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0; color: #856404; font-size: 14px;">
        <strong>⏱️ Estimated Preparation Time:</strong><br>
        Your order will be ready in approximately 30-45 minutes. We will notify you when it\'s ready for delivery or pickup.
    </p>
</div>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0;">
    Enjoy your meal! If you have any dietary requirements or questions, please contact our restaurant staff.
</p>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
    Bon Appétit!<br>
    <strong>Harar Ras Hotel Restaurant Team</strong>
</p>';
        
        return self::getBaseTemplate($content);
    }

    
    /**
     * Spa/Laundry Service Confirmation Template
     */
    public static function getServiceTemplate($service, $serviceType = 'spa') {
        $customerName = htmlspecialchars($service['first_name'] . ' ' . $service['last_name']);
        $bookingRef = htmlspecialchars($service['booking_reference']);
        $serviceName = htmlspecialchars($service['service_name']);
        $servicePrice = number_format($service['service_price'], 2);
        $totalAmount = number_format($service['total_price'], 2);
        $quantity = $service['quantity'] ?? 1;
        $serviceDate = $service['service_date'] ? date('F j, Y', strtotime($service['service_date'])) : 'To be scheduled';
        $serviceTime = $service['service_time'] ? date('g:i A', strtotime($service['service_time'])) : 'To be confirmed';
        $specialRequests = htmlspecialchars($service['special_requests'] ?? 'None');
        
        $icon = $serviceType === 'spa' ? '💆' : '🧺';
        $color = $serviceType === 'spa' ? '#17a2b8' : '#6f42c1';
        $title = $serviceType === 'spa' ? 'SPA SERVICE CONFIRMED' : 'LAUNDRY SERVICE CONFIRMED';
        
        $content = '
<div style="text-align: center; margin-bottom: 30px;">
    <div style="display: inline-block; background-color: ' . $color . '; color: white; padding: 10px 20px; border-radius: 50px; font-size: 14px; font-weight: bold;">
        ' . $icon . ' ' . $title . '
    </div>
</div>

<h2 style="color: #333; margin: 0 0 10px 0; font-size: 24px;">Dear ' . $customerName . ',</h2>

<p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
    Thank you for booking our ' . ucfirst($serviceType) . ' service! Your service has been confirmed and we look forward to serving you.
</p>

<table width="100%" cellpadding="12" cellspacing="0" style="background-color: #f8f9fa; border-radius: 8px; margin: 20px 0;">
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Booking Reference:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            <span style="color: ' . $color . '; font-weight: bold; font-size: 16px;">' . $bookingRef . '</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Service:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $serviceName . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Quantity:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $quantity . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Service Date:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $serviceDate . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Service Time:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $serviceTime . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Price per Service:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ETB ' . $servicePrice . '
        </td>
    </tr>
    <tr>
        <td style="padding: 12px;">
            <strong style="color: #495057; font-size: 16px;">Total Amount Paid:</strong>
        </td>
        <td style="padding: 12px; text-align: right;">
            <strong style="color: #28a745; font-size: 18px;">ETB ' . $totalAmount . '</strong>
        </td>
    </tr>
</table>';

        if ($specialRequests !== 'None') {
            $content .= '
<div style="background-color: #f8f9fa; border-left: 4px solid ' . $color . '; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0; color: #495057; font-size: 14px;">
        <strong>📝 Special Requests:</strong><br>
        ' . $specialRequests . '
    </p>
</div>';
        }

        $content .= '
<div style="background-color: #e3f2fd; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0; color: #0056b3; font-size: 14px;">
        <strong>📌 Important Information:</strong><br>
        • Please arrive 10 minutes before your scheduled time<br>
        • Bring your booking reference for verification<br>
        • Contact us if you need to reschedule
    </p>
</div>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0;">
    We look forward to providing you with excellent service. If you have any questions, please don\'t hesitate to contact us.
</p>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
    Best regards,<br>
    <strong>Harar Ras Hotel ' . ucfirst($serviceType) . ' Team</strong>
</p>';
        
        return self::getBaseTemplate($content);
    }

    
    /**
     * Payment Verification Template
     */
    public static function getPaymentVerificationTemplate($booking) {
        $customerName = htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']);
        $bookingRef = htmlspecialchars($booking['booking_reference']);
        $totalAmount = number_format($booking['total_price'], 2);
        $paymentMethod = ucfirst($booking['payment_method'] ?? 'N/A');
        $verifiedDate = date('F j, Y g:i A');
        
        // Determine booking type and details
        $bookingTypeDisplay = '';
        $bookingDetails = '';
        
        if ($booking['booking_type'] === 'room') {
            $bookingTypeDisplay = 'Room Booking';
            $roomName = htmlspecialchars($booking['room_name']);
            $roomNumber = htmlspecialchars($booking['room_number']);
            $checkIn = $booking['check_in_date'] ? date('F j, Y', strtotime($booking['check_in_date'])) : 'N/A';
            $checkOut = $booking['check_out_date'] ? date('F j, Y', strtotime($booking['check_out_date'])) : 'N/A';
            
            $bookingDetails = '
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Room:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $roomName . ' (' . $roomNumber . ')
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Check-in Date:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $checkIn . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Check-out Date:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $checkOut . '
        </td>
    </tr>';
        } else {
            $bookingTypeDisplay = ucfirst(str_replace('_', ' ', $booking['booking_type']));
        }
        
        $content = '
<div style="text-align: center; margin-bottom: 30px;">
    <div style="display: inline-block; background-color: #28a745; color: white; padding: 10px 20px; border-radius: 50px; font-size: 14px; font-weight: bold;">
        ✅ PAYMENT VERIFIED
    </div>
</div>

<h2 style="color: #333; margin: 0 0 10px 0; font-size: 24px;">Dear ' . $customerName . ',</h2>

<p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
    Great news! Your payment has been successfully verified by our staff. Your booking is now confirmed and ready.
</p>

<table width="100%" cellpadding="12" cellspacing="0" style="background-color: #f8f9fa; border-radius: 8px; margin: 20px 0;">
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Booking Reference:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            <span style="color: #007bff; font-weight: bold; font-size: 16px;">' . $bookingRef . '</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Booking Type:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $bookingTypeDisplay . '
        </td>
    </tr>
    ' . $bookingDetails . '
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Payment Method:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $paymentMethod . '
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px;">
            <strong style="color: #495057;">Verified On:</strong>
        </td>
        <td style="border-bottom: 1px solid #dee2e6; padding: 12px; text-align: right;">
            ' . $verifiedDate . '
        </td>
    </tr>
    <tr>
        <td style="padding: 12px;">
            <strong style="color: #495057; font-size: 16px;">Total Amount Paid:</strong>
        </td>
        <td style="padding: 12px; text-align: right;">
            <strong style="color: #28a745; font-size: 18px;">ETB ' . $totalAmount . '</strong>
        </td>
    </tr>
</table>

<div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0; color: #155724; font-size: 14px;">
        <strong>🎉 Payment Status: VERIFIED</strong><br>
        Your payment has been successfully verified by our staff. Your booking is now confirmed and you can proceed with confidence.
    </p>
</div>

<div style="background-color: #e3f2fd; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="margin: 0; color: #0056b3; font-size: 14px;">
        <strong>📌 What\'s Next?</strong><br>
        • Your booking is now confirmed<br>
        • You can view your booking details in your account<br>
        • Contact us if you have any questions or need assistance
    </p>
</div>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0;">
    Thank you for choosing Harar Ras Hotel. We look forward to serving you and providing you with an excellent experience.
</p>

<p style="color: #666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;">
    Best regards,<br>
    <strong>The Harar Ras Hotel Team</strong>
</p>';
        
        return self::getBaseTemplate($content);
    }
}
