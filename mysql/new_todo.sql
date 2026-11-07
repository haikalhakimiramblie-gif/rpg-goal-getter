-- Add todo_count to goals table if not exists
ALTER TABLE goals ADD COLUMN todo_count INT DEFAULT 1 AFTER gold_reward;

-- Create goal_todos table for individual tasks
CREATE TABLE IF NOT EXISTS goal_todos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    todo_name VARCHAR(255) NOT NULL,
    completed BOOLEAN DEFAULT FALSE,
    position INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
);