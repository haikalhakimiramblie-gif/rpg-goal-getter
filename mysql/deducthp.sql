-- Add to your goals table
ALTER TABLE goals ADD COLUMN IF NOT EXISTS (
    total_hp_to_deduct INT DEFAULT 0,
    hp_deduction_per_interval INT DEFAULT 0,
    deduction_interval ENUM('secondly', 'minutely', 'hourly', 'daily') DEFAULT 'hourly',
    next_deduction DATETIME NULL,
    failed BOOLEAN DEFAULT FALSE
);

-- Add to goal_logs table
ALTER TABLE goal_logs ADD COLUMN IF NOT EXISTS status ENUM('completed', 'failed') DEFAULT 'completed';

-- Create system logs table for monitoring
CREATE TABLE IF NOT EXISTS system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    process_name VARCHAR(100),
    execution_time DATETIME,
    results TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);