ALTER TABLE goals 
ADD COLUMN to_complete INT DEFAULT 1 AFTER gold_reward,
ADD COLUMN already_complete INT DEFAULT 0 AFTER to_complete;