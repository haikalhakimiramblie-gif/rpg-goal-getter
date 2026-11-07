-- Shop items table
CREATE TABLE IF NOT EXISTS shop_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price INT NOT NULL,
    item_type ENUM('potion', 'weapon', 'armor', 'misc') NOT NULL,
    effect_type ENUM('heal', 'strength', 'defense', 'xp_boost') NOT NULL,
    effect_value INT NOT NULL,
    image_path VARCHAR(255),
    available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User inventory table
CREATE TABLE IF NOT EXISTS user_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES shop_items(id)
);