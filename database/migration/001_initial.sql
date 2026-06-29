-- Migration v001: Initial schema
-- Description: Creates all base tables for lernginx LMS
-- Up

SOURCE ../schema.sql;

-- Down (rollback)
-- DROP TABLE IF EXISTS `password_resets`, `menu`, `registration_policies`, `media`, `modules`, `page_tag`, `tags`, `pages`, `posts`, `categories_closure`, `categories`, `sessions`, `users`;
