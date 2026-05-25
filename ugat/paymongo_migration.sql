-- Run this once to add PayMongo support to the orders table.
-- In phpMyAdmin: go to your database → SQL tab → paste and run.
-- In Railway: use the MySQL plugin's query console.

ALTER TABLE `orders`
  ADD COLUMN `paymongo_source_id` VARCHAR(100) DEFAULT NULL AFTER `gcash_ref`,
  MODIFY COLUMN `status` ENUM('pending_payment','pending','confirmed','preparing','out_for_delivery','delivered','cancelled') DEFAULT 'pending';
