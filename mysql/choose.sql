-- Add monster_folder to monsters table if not exists
ALTER TABLE monsters ADD COLUMN monster_folder INT DEFAULT 1 AFTER current_hp;

-- Add boss_folder to bosses table if not exists  
ALTER TABLE bosses ADD COLUMN boss_folder INT DEFAULT 1 AFTER current_hp;

-- Update existing records with random folders
UPDATE monsters SET monster_folder = FLOOR(1 + RAND() * 5);
UPDATE bosses SET boss_folder = FLOOR(1 + RAND() * 2);