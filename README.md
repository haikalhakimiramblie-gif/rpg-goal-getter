# RPG Goal Getter

An academic PHP/MySQL game project by Muhammad Haikal Hakimi. Everyday goals become RPG quests, with monster battles, boss encounters, EXP, gold, a reward shop and a leaderboard.

## Stack

- PHP with PDO and session-based accounts
- MySQL relational database
- JavaScript, HTML and CSS
- Local character, monster and background assets in `images/`

## Source layout

- `login.php`, `register.php`: account entry points
- `dashboard.php`, `goals.php`, `create_goal.php`: goals and dashboard
- `QuestManager.php`, `boss_arena.php`, `bossgoals.php`: quest and battle logic
- `shop.php`, `Leaderboard.php`, `logs.php`: shop, rankings and logs
- `mysql/`: historical SQL scripts and schema changes

## Local configuration

Use a PHP web server with the PDO MySQL extension and a MySQL database. Copy `config.example.php` to `config.php`, then set your own database connection details.

The `mysql/` directory contains historical database scripts, not a verified one-command migration sequence. Review their order and dependencies before importing into a disposable local database. Do not run them against a production database without review.

The original full database backup is intentionally excluded because it contains account records and email addresses. Local `config.php` is also excluded. A clean, verified database bootstrap is not yet provided.

## Project status

This repository preserves the academic project source. It is not a production deployment or a security certification. PHP execution and database integration were not tested during this upload because a PHP runtime was not available in the upload environment.

The portfolio site is separate and is not included here. Asset licensing and redistribution permissions should be confirmed before wider reuse; no blanket open-source licence is asserted by this repository.
