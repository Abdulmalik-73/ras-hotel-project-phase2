-- =====================================================
-- ROOM BOOKING QUEUE SYSTEM - PREVENT DOUBLE BOOKING
-- =====================================================
-- This system automatically manages room availability and booking queues
-- Staff can manually change room status (active, maintenance, inactive)
-- System automatically shows: available, in_process, occupied based on bookings

-- =====================================================
-- STEP 1: CREATE ROOM LOCKS TABLE (BOOKING QUEUE)
-- =====================================================

CREATE TABLE IF NOT EXISTS room_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    lock_status ENUM('in_process', 'waiting') DEFAULT 'in_process',
    queue_position INT DEFAULT 1,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_room_lock (room_id, lock_status),
    INDEX idx_user_lock (user_id, lock_status),
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 2: UPDATE ROOMS TABLE - ADD MANUAL STATUS
-- =====================================================
-- Staff can manually set: active, maintenance, inactive
-- System automatically determines: available, in_process, occupied

ALTER TABLE rooms 
MODIFY COLUMN status ENUM('active', 'occupied', 'booked', 'maintenance', 'inactive') DEFAULT 'active';

-- Add computed status column (for display purposes)
ALTER TABLE rooms 
ADD COLUMN manual_status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active' AFTER status,
ADD COLUMN auto_status ENUM('available', 'in_process', 'occupied', 'waiting') DEFAULT 'available' AFTER manual_status;

-- =====================================================
-- STEP 3: UPDATE BOOKINGS TABLE - ADD QUEUE STATUS
-- =====================================================

ALTER TABLE bookings
MODIFY COLUMN status ENUM('in_process', 'waiting', 'pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending';

-- Add lock reference
ALTER TABLE bookings
ADD COLUMN lock_id INT NULL AFTER booking_reference,
ADD COLUMN queue_position INT DEFAULT 0 AFTER lock_id,
ADD FOREIGN KEY (lock_id) REFERENCES room_locks(id) ON DELETE SET NULL;

-- =====================================================
-- STEP 4: CREATE STORED PROCEDURE - CHECK ROOM AVAILABILITY
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS check_room_availability_with_queue$$

CREATE PROCEDURE check_room_availability_with_queue(
    IN p_room_id INT,
    IN p_check_in DATE,
    IN p_check_out DATE,
    OUT p_status VARCHAR(20),
    OUT p_queue_count INT
)
BEGIN
    DECLARE v_manual_status VARCHAR(20);
    DECLARE v_active_lock_count INT;
    DECLARE v_confirmed_booking_count INT;
    
    -- Get manual status set by staff
    SELECT manual_status INTO v_manual_status
    FROM rooms
    WHERE id = p_room_id;
    
    -- If room is manually set to maintenance or inactive, not available
    IF v_manual_status IN ('maintenance', 'inactive') THEN
        SET p_status = v_manual_status;
        SET p_queue_count = 0;
        
    ELSE
        -- Check for active locks (someone is currently booking)
        SELECT COUNT(*) INTO v_active_lock_count
        FROM room_locks
        WHERE room_id = p_room_id
        AND lock_status = 'in_process'
        AND expires_at > NOW()
        AND (
            (check_in_date <= p_check_in AND check_out_date > p_check_in)
            OR (check_in_date < p_check_out AND check_out_date >= p_check_out)
            OR (check_in_date >= p_check_in AND check_out_date <= p_check_out)
        );
        
        -- Check for confirmed bookings (room is occupied/booked)
        SELECT COUNT(*) INTO v_confirmed_booking_count
        FROM bookings
        WHERE room_id = p_room_id
        AND status IN ('confirmed', 'checked_in')
        AND (
            (check_in_date <= p_check_in AND check_out_date > p_check_in)
            OR (check_in_date < p_check_out AND check_out_date >= p_check_out)
            OR (check_in_date >= p_check_in AND check_out_date <= p_check_out)
        );
        
        -- Determine status
        IF v_confirmed_booking_count > 0 THEN
            SET p_status = 'occupied';
            SET p_queue_count = 0;
        ELSEIF v_active_lock_count > 0 THEN
            SET p_status = 'in_process';
            -- Count waiting users
            SELECT COUNT(*) INTO p_queue_count
            FROM room_locks
            WHERE room_id = p_room_id
            AND lock_status = 'waiting'
            AND expires_at > NOW();
        ELSE
            SET p_status = 'available';
            SET p_queue_count = 0;
        END IF;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- STEP 5: CREATE STORED PROCEDURE - ACQUIRE ROOM LOCK
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS acquire_room_lock$$

CREATE PROCEDURE acquire_room_lock(
    IN p_room_id INT,
    IN p_user_id INT,
    IN p_session_id VARCHAR(100),
    IN p_check_in DATE,
    IN p_check_out DATE,
    IN p_timeout_minutes INT,
    OUT p_lock_id INT,
    OUT p_lock_status VARCHAR(20),
    OUT p_queue_position INT
)
BEGIN
    DECLARE v_active_lock_exists INT;
    DECLARE v_expires_at TIMESTAMP;
    
    -- Set expiration time
    SET v_expires_at = DATE_ADD(NOW(), INTERVAL p_timeout_minutes MINUTE);
    
    -- Check if there's an active lock for this room and date range
    SELECT COUNT(*) INTO v_active_lock_exists
    FROM room_locks
    WHERE room_id = p_room_id
    AND lock_status = 'in_process'
    AND expires_at > NOW()
    AND (
        (check_in_date <= p_check_in AND check_out_date > p_check_in)
        OR (check_in_date < p_check_out AND check_out_date >= p_check_out)
        OR (check_in_date >= p_check_in AND check_out_date <= p_check_out)
    );
    
    -- Determine lock status
    IF v_active_lock_exists > 0 THEN
        SET p_lock_status = 'waiting';
        
        -- Calculate queue position
        SELECT COALESCE(MAX(queue_position), 0) + 1 INTO p_queue_position
        FROM room_locks
        WHERE room_id = p_room_id
        AND expires_at > NOW();
    ELSE
        SET p_lock_status = 'in_process';
        SET p_queue_position = 1;
    END IF;
    
    -- Insert lock
    INSERT INTO room_locks (
        room_id, user_id, session_id, lock_status, 
        queue_position, check_in_date, check_out_date, expires_at
    ) VALUES (
        p_room_id, p_user_id, p_session_id, p_lock_status,
        p_queue_position, p_check_in, p_check_out, v_expires_at
    );
    
    SET p_lock_id = LAST_INSERT_ID();
END$$

DELIMITER ;

-- =====================================================
-- STEP 6: CREATE STORED PROCEDURE - RELEASE LOCK & PROMOTE QUEUE
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS release_room_lock$$

CREATE PROCEDURE release_room_lock(
    IN p_lock_id INT,
    IN p_reason VARCHAR(50)
)
BEGIN
    DECLARE v_room_id INT;
    DECLARE v_check_in DATE;
    DECLARE v_check_out DATE;
    DECLARE v_next_lock_id INT;
    
    -- Get lock details
    SELECT room_id, check_in_date, check_out_date
    INTO v_room_id, v_check_in, v_check_out
    FROM room_locks
    WHERE id = p_lock_id;
    
    -- Delete the lock
    DELETE FROM room_locks WHERE id = p_lock_id;
    
    -- Promote next waiting user to in_process
    SELECT id INTO v_next_lock_id
    FROM room_locks
    WHERE room_id = v_room_id
    AND lock_status = 'waiting'
    AND expires_at > NOW()
    AND (
        (check_in_date <= v_check_in AND check_out_date > v_check_in)
        OR (check_in_date < v_check_out AND check_out_date >= v_check_out)
        OR (check_in_date >= v_check_in AND check_out_date <= v_check_out)
    )
    ORDER BY created_at ASC
    LIMIT 1;
    
    -- Update next user to in_process
    IF v_next_lock_id IS NOT NULL THEN
        UPDATE room_locks
        SET lock_status = 'in_process',
            queue_position = 1,
            updated_at = NOW()
        WHERE id = v_next_lock_id;
        
        -- Update queue positions for remaining waiting users
        UPDATE room_locks
        SET queue_position = queue_position - 1
        WHERE room_id = v_room_id
        AND lock_status = 'waiting'
        AND id != v_next_lock_id
        AND expires_at > NOW();
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- STEP 7: CREATE STORED PROCEDURE - CLEANUP EXPIRED LOCKS
-- =====================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS cleanup_expired_locks$$

CREATE PROCEDURE cleanup_expired_locks()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_lock_id INT;
    DECLARE cur CURSOR FOR 
        SELECT id FROM room_locks 
        WHERE expires_at < NOW();
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_lock_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Release each expired lock (this will promote waiting users)
        CALL release_room_lock(v_lock_id, 'expired');
    END LOOP;
    
    CLOSE cur;
END$$

DELIMITER ;

-- =====================================================
-- STEP 8: CREATE EVENT - AUTO CLEANUP EXPIRED LOCKS
-- =====================================================

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

-- Drop existing event if exists
DROP EVENT IF EXISTS auto_cleanup_expired_locks;

-- Create event to run every minute
CREATE EVENT auto_cleanup_expired_locks
ON SCHEDULE EVERY 1 MINUTE
DO
    CALL cleanup_expired_locks();

-- =====================================================
-- STEP 9: CREATE VIEW - ROOM AVAILABILITY STATUS
-- =====================================================

CREATE OR REPLACE VIEW room_availability_view AS
SELECT 
    r.id as room_id,
    r.name as room_name,
    r.room_number,
    r.room_type,
    r.price,
    r.manual_status,
    CASE
        -- Manual status takes precedence
        WHEN r.manual_status = 'maintenance' THEN 'maintenance'
        WHEN r.manual_status = 'inactive' THEN 'inactive'
        -- Check for confirmed bookings (occupied)
        WHEN EXISTS (
            SELECT 1 FROM bookings b
            WHERE b.room_id = r.id
            AND b.status IN ('confirmed', 'checked_in')
            AND CURDATE() BETWEEN b.check_in_date AND b.check_out_date
        ) THEN 'occupied'
        -- Check for active locks (in_process)
        WHEN EXISTS (
            SELECT 1 FROM room_locks rl
            WHERE rl.room_id = r.id
            AND rl.lock_status = 'in_process'
            AND rl.expires_at > NOW()
        ) THEN 'in_process'
        -- Check for waiting queue
        WHEN EXISTS (
            SELECT 1 FROM room_locks rl
            WHERE rl.room_id = r.id
            AND rl.lock_status = 'waiting'
            AND rl.expires_at > NOW()
        ) THEN 'waiting'
        -- Otherwise available
        ELSE 'available'
    END as display_status,
    (SELECT COUNT(*) FROM room_locks rl 
     WHERE rl.room_id = r.id 
     AND rl.lock_status = 'waiting' 
     AND rl.expires_at > NOW()) as waiting_count
FROM rooms r
WHERE r.manual_status = 'active';

-- =====================================================
-- STEP 10: CREATE INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX idx_bookings_room_dates ON bookings(room_id, check_in_date, check_out_date, status);
CREATE INDEX idx_room_locks_room_dates ON room_locks(room_id, check_in_date, check_out_date, lock_status);

-- =====================================================
-- COMPLETED: ROOM BOOKING QUEUE SYSTEM
-- =====================================================

SELECT 'Room Booking Queue System installed successfully!' as message;
