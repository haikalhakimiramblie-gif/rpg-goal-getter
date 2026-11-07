-- REQUIRED DATABASE TABLES FOR HP DEDUCTION SYSTEM

-- 1. GOAL_LOGS TABLE (tracks all goal-related actions)
CREATE TABLE IF NOT EXISTS goal_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'hp_deduction', 'quest_failed', 'quest_completed', 'todo_completed'
    details TEXT,
    hp_change INT DEFAULT 0, -- Negative for deductions, positive for rewards
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_goal_id (goal_id),
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. SYSTEM_LOGS TABLE (general system logging)
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Update GOALS TABLE (ensure all columns exist)
ALTER TABLE goals 
ADD COLUMN IF NOT EXISTS status ENUM('active', 'completed', 'failed') DEFAULT 'active',
ADD COLUMN IF NOT EXISTS feedback TEXT,
ADD COLUMN IF NOT EXISTS total_hp_to_deduct INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS hp_deduction_per_interval INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS deduction_interval ENUM('minutely', 'hourly', 'daily') NOT NULL DEFAULT 'daily',
ADD COLUMN IF NOT EXISTS next_deduction DATETIME,
ADD COLUMN IF NOT EXISTS failed TINYINT(1) DEFAULT 0;

-- 4. Add indexes for performance
ALTER TABLE goals 
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_next_deduction (next_deduction),
ADD INDEX IF NOT EXISTS idx_due_date (due_date),
ADD INDEX IF NOT EXISTS idx_user_status (user_id, status);

-- 5. BOSSES TABLE (ensure defeated column exists)
ALTER TABLE bosses 
ADD COLUMN IF NOT EXISTS defeated TINYINT(1) DEFAULT 0;

-- 6. MONSTERS TABLE (ensure defeated column exists)
ALTER TABLE monsters 
ADD COLUMN IF NOT EXISTS defeated TINYINT(1) DEFAULT 0;

-- 7. Create view for active goals with HP deduction info
CREATE OR REPLACE VIEW active_goals_with_deduction AS
SELECT 
    g.*,
    u.username,
    u.current_hp as user_current_hp,
    u.max_hp as user_max_hp,
    TIMESTAMPDIFF(SECOND, NOW(), g.due_date) as seconds_remaining,
    CASE 
        WHEN g.difficulty = 'epic' THEN b.current_hp
        ELSE m.current_hp
    END as enemy_hp,
    CASE 
        WHEN g.difficulty = 'epic' THEN b.boss_hp
        ELSE m.monster_hp
    END as enemy_max_hp
FROM goals g
JOIN users u ON g.user_id = u.id
LEFT JOIN bosses b ON g.id = b.goal_id
LEFT JOIN monsters m ON g.id = m.goal_id
WHERE g.status = 'active';

-- 8. Create stored procedure for HP deduction
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS process_single_hp_deduction(
    IN p_goal_id INT,
    IN p_user_id INT,
    IN p_hp_to_deduct INT
)
BEGIN
    DECLARE v_current_hp INT;
    DECLARE v_new_hp INT;
    
    -- Get current HP
    SELECT current_hp INTO v_current_hp FROM users WHERE id = p_user_id;
    
    -- Calculate new HP (minimum 0)
    SET v_new_hp = GREATEST(0, v_current_hp - p_hp_to_deduct);
    
    -- Update user HP
    UPDATE users SET current_hp = v_new_hp WHERE id = p_user_id;
    
    -- Log the deduction
    INSERT INTO goal_logs (goal_id, user_id, action, hp_change, timestamp)
    VALUES (p_goal_id, p_user_id, 'hp_deduction', -p_hp_to_deduct, NOW());
END //

DELIMITER ;

-- 9. Create trigger to log goal completion
DELIMITER //

CREATE TRIGGER IF NOT EXISTS after_goal_complete
AFTER UPDATE ON goals
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status = 'active' THEN
        INSERT INTO goal_logs (goal_id, user_id, action, details, timestamp)
        VALUES (NEW.id, NEW.user_id, 'quest_completed', 
                CONCAT('Completed: ', NEW.title), NOW());
        
        INSERT INTO system_logs (user_id, action, details, timestamp)
        VALUES (NEW.user_id, 'quest_completed', 
                CONCAT('Quest completed: ', NEW.title, ' | Rewards: +', NEW.exp_reward, ' EXP, +', NEW.gold_reward, ' Gold'),
                NOW());
    END IF;
    
    IF NEW.status = 'failed' AND OLD.status = 'active' THEN
        INSERT INTO goal_logs (goal_id, user_id, action, details, timestamp)
        VALUES (NEW.id, NEW.user_id, 'quest_failed', 
                CONCAT('Failed: ', NEW.title), NOW());
        
        INSERT INTO system_logs (user_id, action, details, timestamp)
        VALUES (NEW.user_id, 'quest_failed', 
                CONCAT('Quest failed: ', NEW.title),
                NOW());
    END IF;
END //

DELIMITER ;

-- 10. Sample query to check HP deductions due now
-- SELECT * FROM active_goals_with_deduction WHERE next_deduction <= NOW() AND seconds_remaining > 0;