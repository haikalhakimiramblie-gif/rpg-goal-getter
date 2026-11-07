-- RPG Goal Getter Database Setup
CREATE DATABASE IF NOT EXISTS rpg_goal_getter;
USE rpg_goal_getter;

-- Users table with level system
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    level INT DEFAULT 1,
    exp INT DEFAULT 0,
    exp_needed INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Goals table for tracking user goals
CREATE TABLE IF NOT EXISTS goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    difficulty ENUM('easy', 'medium', 'hard', 'epic') DEFAULT 'medium',
    exp_reward INT DEFAULT 10,
    due_date DATE,
    completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User stats table for progression tracking
CREATE TABLE IF NOT EXISTS user_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    total_goals_completed INT DEFAULT 0,
    total_exp_earned INT DEFAULT 0,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    last_activity_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_goals_user_id ON goals(user_id);
CREATE INDEX idx_goals_completed ON goals(completed);
CREATE INDEX idx_goals_due_date ON goals(due_date);
CREATE INDEX idx_user_stats_user_id ON user_stats(user_id);