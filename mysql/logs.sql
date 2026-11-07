CREATE TABLE goal_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    goal_title VARCHAR(255) NOT NULL,
    goal_description TEXT,
    category VARCHAR(50),
    difficulty ENUM('easy', 'medium', 'hard', 'epic'),
    todo_count INT DEFAULT 1,
    exp_earned INT NOT NULL,
    gold_earned INT DEFAULT 0,
    completion_time INT DEFAULT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);