-- Insert Healing Potion (10 HP for 5 gold)
INSERT INTO shop_items (name, description, price, item_type, effect_type, effect_value) 
VALUES (
    'Minor Healing Potion', 
    'A small healing potion that restores 10 health points', 
    5, 
    'potion', 
    'heal', 
    10
);

-- Insert XP Potion (10 XP for 30 gold)
INSERT INTO shop_items (name, description, price, item_type, effect_type, effect_value) 
VALUES (
    'Experience Elixir', 
    'A magical elixir that grants 10 experience points', 
    30, 
    'potion', 
    'xp_boost', 
    10
);

-- Optional: Insert a few more useful items
INSERT INTO shop_items (name, description, price, item_type, effect_type, effect_value) 
VALUES 
(
    'Standard Healing Potion', 
    'A reliable healing potion that restores 25 health points', 
    15, 
    'potion', 
    'heal', 
    25
),
(
    'Greater Healing Potion', 
    'A powerful healing potion that restores 50 health points', 
    30, 
    'potion', 
    'heal', 
    50
),
(
    'Strength Potion', 
    'Temporarily increases your strength for 1 hour', 
    20, 
    'potion', 
    'strength', 
    5
);