-- lernginx Sample Seed Data
-- Password for all users: password123 (bcrypt hash)

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `display_name`, `is_deleted`, `created_at`) VALUES
(1, 'admin', 'admin@lernginx.lan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 0, NOW()),
(2, 'teacher1', 'teacher1@lernginx.lan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'John Teacher', 0, NOW()),
(3, 'student1', 'student1@lernginx.lan', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Alice Student', 0, NOW());

INSERT INTO `categories` (`id`, `name`, `slug`, `parent_id`, `description`, `show_posts`) VALUES
(1, 'Mathematics', 'mathematics', NULL, 'Mathematics courses for all levels', 1),
(2, 'Science', 'science', NULL, 'Science and laboratory courses', 1),
(3, 'Languages', 'languages', NULL, 'Language and literature courses', 1),
(4, 'Algebra', 'algebra', 1, 'Algebra fundamentals', 1),
(5, 'Geometry', 'geometry', 1, 'Geometry and trigonometry', 1),
(6, 'Physics', 'physics', 2, 'Physics fundamentals', 1),
(7, 'Chemistry', 'chemistry', 2, 'Chemistry fundamentals', 1);

INSERT INTO `categories_closure` (`ancestor_id`, `descendant_id`, `depth`) VALUES
(1, 1, 0), (1, 4, 1), (1, 5, 1),
(2, 2, 0), (2, 6, 1), (2, 7, 1),
(3, 3, 0),
(4, 4, 0), (5, 5, 0),
(6, 6, 0), (7, 7, 0);

INSERT INTO `posts` (`id`, `title`, `slug`, `content`, `excerpt`, `category_id`, `author_id`, `status`, `created_at`) VALUES
(1, 'Introduction to Algebra', 'intro-algebra', '<h2>Welcome to Algebra</h2><p>Algebra is a branch of mathematics that deals with symbols and the rules for manipulating those symbols.</p>', 'Learn the basics of algebra including variables, expressions, and equations.', 4, 2, 'published', NOW()),
(2, 'Newton\'s Laws of Motion', 'newtons-laws', '<h2>Newton\'s Laws</h2><p>Newton\'s laws of motion are three physical laws that describe the relationship between a body and the forces acting upon it.</p>', 'Understanding the fundamental laws of physics.', 6, 2, 'published', NOW()),
(3, 'Periodic Table Basics', 'periodic-table', '<h2>The Periodic Table</h2><p>The periodic table is a tabular arrangement of chemical elements organized by atomic number.</p>', 'Introduction to the periodic table of elements.', 7, 2, 'published', NOW());

INSERT INTO `pages` (`id`, `title`, `slug`, `content`, `excerpt`, `status`, `created_by`, `created_at`) VALUES
(1, 'About Us', 'about', '<h1>About lernginx</h1><p>lernginx is an open-source Learning Management System designed for secondary education.</p>', 'Learn more about the lernginx LMS platform.', 'published', 1, NOW()),
(2, 'Privacy Policy', 'privacy', '<h1>Privacy Policy</h1><p>Your privacy is important to us. This policy outlines how we handle your data.</p>', 'Our privacy policy and data handling practices.', 'published', 1, NOW());

INSERT INTO `tags` (`id`, `name`, `slug`, `created_at`) VALUES
(1, 'Beginner', 'beginner', NOW()),
(2, 'Advanced', 'advanced', NOW()),
(3, 'STEM', 'stem', NOW());

INSERT INTO `page_tag` (`page_id`, `tag_id`) VALUES
(1, 1),
(1, 3),
(2, 1);

INSERT INTO `registration_policies` (`key_name`, `value`) VALUES
('default_student_module_status', '1'),
('max_parent_modules_per_student', '3');

INSERT INTO `menu` (`id`, `label`, `link`, `parent_id`, `sort_order`, `is_active`) VALUES
(1, 'Home', '/', NULL, 1, 1),
(2, 'Programs', '/programs/', NULL, 2, 1),
(3, 'Modules', '/modules/', NULL, 3, 1),
(4, 'About', '/about/', NULL, 4, 1);
