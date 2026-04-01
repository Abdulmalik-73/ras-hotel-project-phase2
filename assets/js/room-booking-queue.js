/**
 * Room Booking Queue System - Client Side
 * Handles real-time room availability and booking queue
 */

class RoomBookingQueue {
    constructor() {
        this.apiUrl = 'api/room_lock_api.php';
        this.currentLockId = null;
        this.queueCheckInterval = null;
        this.statusColors = {
            'available': '#28a745',    // Green
            'in_process': '#6f42c1',   // Purple
            'occupied': '#dc3545',     // Red
            'waiting': '#17a2b8',      // Blue/Cyan
            'maintenance': '#ffc107',  // Yellow
            'inactive': '#6c757d'      // Gray
        };
    }
    
    /**
     * Check room availability
     */
    async checkAvailability(roomId, checkIn, checkOut) {
        try {
            const response = await fetch(
                `${this.apiUrl}?action=check_availability&room_id=${roomId}&check_in=${checkIn}&check_out=${checkOut}`
            );
            const data = await response.json();
            
            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error checking availability:', error);
            throw error;
        }
    }
    
    /**
     * Acquire room lock (start booking or join queue)
     */
    async acquireLock(roomId, checkIn, checkOut) {
        try {
            const formData = new FormData();
            formData.append('action', 'acquire_lock');
            formData.append('room_id', roomId);
            formData.append('check_in', checkIn);
            formData.append('check_out', checkOut);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                this.currentLockId = data.data.lock_id;
                
                // If in queue, start monitoring position
                if (data.data.status === 'waiting') {
                    this.startQueueMonitoring();
                }
                
                return data.data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error acquiring lock:', error);
            throw error;
        }
    }
    
    /**
     * Release room lock (cancel booking)
     */
    async releaseLock(lockId, reason = 'cancelled') {
        try {
            const formData = new FormData();
            formData.append('action', 'release_lock');
            formData.append('lock_id', lockId || this.currentLockId);
            formData.append('reason', reason);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                this.stopQueueMonitoring();
                this.currentLockId = null;
            }
            
            return data;
        } catch (error) {
            console.error('Error releasing lock:', error);
            throw error;
        }
    }
    
    /**
     * Get current queue position
     */
    async getQueuePosition(lockId) {
        try {
            const response = await fetch(
                `${this.apiUrl}?action=get_queue_position&lock_id=${lockId || this.currentLockId}`
            );
            const data = await response.json();
            
            if (data.success) {
                return data.data.position;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error getting queue position:', error);
            throw error;
        }
    }
    
    /**
     * Start monitoring queue position
     */
    startQueueMonitoring() {
        if (this.queueCheckInterval) {
            clearInterval(this.queueCheckInterval);
        }
        
        this.queueCheckInterval = setInterval(async () => {
            try {
                const position = await this.getQueuePosition(this.currentLockId);
                
                // Update UI
                this.updateQueuePositionUI(position);
                
                // If promoted to first position, notify user
                if (position === 1) {
                    this.onPromotedToFirst();
                }
            } catch (error) {
                console.error('Queue monitoring error:', error);
            }
        }, 5000); // Check every 5 seconds
    }
    
    /**
     * Stop monitoring queue
     */
    stopQueueMonitoring() {
        if (this.queueCheckInterval) {
            clearInterval(this.queueCheckInterval);
            this.queueCheckInterval = null;
        }
    }
    
    /**
     * Update queue position in UI
     */
    updateQueuePositionUI(position) {
        const queueElement = document.getElementById('queue-position');
        if (queueElement) {
            if (position === 1) {
                queueElement.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>You can now proceed with booking!</strong>
                    </div>
                `;
            } else {
                queueElement.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-clock"></i>
                        You are at position <strong>#${position}</strong> in the waiting queue
                    </div>
                `;
            }
        }
    }
    
    /**
     * Called when user is promoted to first position
     */
    onPromotedToFirst() {
        this.stopQueueMonitoring();
        
        // Show notification
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Room Available!', {
                body: 'You can now proceed with your booking',
                icon: '/assets/images/logos/ras-hotel-logo.png'
            });
        }
        
        // Play sound (optional)
        const audio = new Audio('/assets/sounds/notification.mp3');
        audio.play().catch(() => {});
        
        // Enable booking button
        const bookButton = document.getElementById('proceed-booking-btn');
        if (bookButton) {
            bookButton.disabled = false;
            bookButton.classList.remove('btn-secondary');
            bookButton.classList.add('btn-success');
            bookButton.innerHTML = '<i class="fas fa-check"></i> Proceed with Booking';
        }
    }
    
    /**
     * Get room status badge HTML
     */
    getStatusBadge(status, waitingCount = 0) {
        const color = this.statusColors[status] || '#6c757d';
        const labels = {
            'available': 'Available',
            'in_process': 'Being Booked',
            'occupied': 'Occupied',
            'waiting': 'Waiting Queue',
            'maintenance': 'Maintenance',
            'inactive': 'Inactive'
        };
        
        const label = labels[status] || status;
        const queueInfo = waitingCount > 0 ? ` (${waitingCount} waiting)` : '';
        
        return `
            <span class="badge" style="background-color: ${color}; color: white; padding: 8px 12px; border-radius: 20px;">
                <i class="fas fa-circle" style="font-size: 8px;"></i> ${label}${queueInfo}
            </span>
        `;
    }
    
    /**
     * Update room card status
     */
    updateRoomCardStatus(roomId, status, waitingCount = 0) {
        const statusElement = document.querySelector(`[data-room-id="${roomId}"] .room-status`);
        if (statusElement) {
            statusElement.innerHTML = this.getStatusBadge(status, waitingCount);
        }
        
        // Update book button
        const bookButton = document.querySelector(`[data-room-id="${roomId}"] .book-btn`);
        if (bookButton) {
            if (status === 'available') {
                bookButton.disabled = false;
                bookButton.innerHTML = '<i class="fas fa-calendar-check"></i> Book Now';
                bookButton.classList.remove('btn-secondary');
                bookButton.classList.add('btn-primary');
            } else if (status === 'in_process' || status === 'waiting') {
                bookButton.disabled = false;
                bookButton.innerHTML = '<i class="fas fa-clock"></i> Join Queue';
                bookButton.classList.remove('btn-primary');
                bookButton.classList.add('btn-warning');
            } else {
                bookButton.disabled = true;
                bookButton.innerHTML = '<i class="fas fa-ban"></i> Not Available';
                bookButton.classList.remove('btn-primary', 'btn-warning');
                bookButton.classList.add('btn-secondary');
            }
        }
    }
    
    /**
     * Request notification permission
     */
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
    
    /**
     * Extend lock expiration
     */
    async extendLock(lockId, minutes = 5) {
        try {
            const formData = new FormData();
            formData.append('action', 'extend_lock');
            formData.append('lock_id', lockId || this.currentLockId);
            formData.append('minutes', minutes);
            
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            return data;
        } catch (error) {
            console.error('Error extending lock:', error);
            throw error;
        }
    }
}

// Initialize global instance
const roomBookingQueue = new RoomBookingQueue();

// Request notification permission on page load
document.addEventListener('DOMContentLoaded', () => {
    roomBookingQueue.requestNotificationPermission();
});
