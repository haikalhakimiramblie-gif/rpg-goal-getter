-- Add failed status and HP deduction tracking to goals table
ALTER TABLE goals ADD COLUMN failed BOOLEAN DEFAULT FALSE;
ALTER TABLE goals ADD COLUMN total_hp_to_deduct INT DEFAULT 0;
ALTER TABLE goals ADD COLUMN hp_deduction_per_interval INT DEFAULT 0;
ALTER TABLE goals ADD COLUMN deduction_interval ENUM('hourly', 'daily') DEFAULT 'daily';
ALTER TABLE goals ADD COLUMN next_deduction DATETIME NULL;

-- Create quest logs table
CREATE TABLE IF NOT EXISTS quest_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    goal_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    difficulty ENUM('easy', 'medium', 'hard', 'epic') NOT NULL,
    status ENUM('completed', 'failed') NOT NULL,
    completed_at TIMESTAMP NULL,
    exp_earned INT DEFAULT 0,
    gold_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (goal_id) REFERENCES goals(id)
);