-- Add monster_folder to monsters table
ALTER TABLE monsters ADD COLUMN monster_folder INT DEFAULT 1 AFTER current_hp;

-- Add boss_folder to bosses table  
ALTER TABLE bosses ADD COLUMN boss_folder INT DEFAULT 1 AFTER current_hp;

-- Update existing records with random folders (run this once)
UPDATE monsters SET monster_folder = FLOOR(1 + RAND() * 5);
UPDATE bosses SET boss_folder = FLOOR(1 + RAND() * 2);