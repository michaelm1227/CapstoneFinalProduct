-- Quick fix: adds the missing role column to your existing Users table
-- Run this in phpMyAdmin or your MySQL console
-- This preserves all existing data

USE mcglen97;
ALTER TABLE Users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'student';
