-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2026 at 02:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smart_school_plus`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(6) UNSIGNED NOT NULL,
  `school_id` int(6) DEFAULT 0,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('superadmin','admin','teacher','student') NOT NULL,
  `student_number` int(11) NOT NULL DEFAULT 0,
  `student_code` varchar(20) DEFAULT NULL,
  `class_level` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `profile_img` varchar(255) DEFAULT 'default_avatar.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `subjects_taught` text DEFAULT NULL COMMENT 'เก็บเป็น JSON หรือ Text',
  `profile_frame` varchar(50) DEFAULT 'frame-0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `school_id`, `username`, `password`, `full_name`, `role`, `student_number`, `student_code`, `class_level`, `room_number`, `position`, `profile_img`, `created_at`, `subjects_taught`, `profile_frame`) VALUES
(2, 0, 'superadmin', '$2y$10$pq9t7f/q2Ho5N.ot7Ko0y.3wtxevW/hmesN4BwHz.rlp7y4HWRo3C', 'Woramet Khamtangna', 'superadmin', 0, NULL, NULL, NULL, NULL, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Felix', '2026-01-29 04:35:08', NULL, 'frame-10'),
(7, 1, 'admin01', '$2y$10$d8Cr6F4qV7NBwhC7HJdMgu/w.Ie2KzXRarrxn/l.nga.2ETYX8inW', 'วรเมธ คำตั้งหน้า', 'admin', 0, NULL, NULL, NULL, NULL, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Zack', '2026-01-29 04:46:32', NULL, 'frame-10'),
(10, 1, '66001', '$2y$10$Oh7yZPsQKDLr2TfL3D3pPONpGxsY9dt108sldmzsHUyi7Wn2AnKTW', 'วรเมธ คำตั้งหน้า', 'student', 0, '66001', 'ม.1', '', 'ผู้อำนวยการ', 'default_avatar.png', '2026-01-29 05:09:55', NULL, 'frame-0'),
(12, 1, '66005', '$2y$10$cmRnJaDOh4jO6kTSdxBwferLuNulIZCSC8O684at1Kmy/7ZYP67Yq', 'นายพิชญะ ยาเครือ', 'student', 1, '66005', 'ม.1', '2', NULL, 'default_avatar.png', '2026-01-29 15:32:00', NULL, 'frame-0'),
(16, 2, 'Wanida', '$2y$10$Oz0tnOAG7zqitrZLM/MhceEGT33yf1jDgpGBlDtE9E2VmSN4.ZG3.', 'นางสาววนิดา กะนา', 'admin', 0, NULL, NULL, NULL, NULL, 'default_avatar.png', '2026-01-30 11:50:59', NULL, 'frame-0'),
(17, 1, 'Natthanan', '$2y$10$.ROFdwjJFdPemcW51sPas.Ywcj1GBgsl9ZM4BGe13J9uVvJ3/8z9y', 'นางสาวณัฐนันท์ ทองแดง', 'teacher', 0, NULL, NULL, NULL, NULL, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Jack', '2026-02-09 03:42:11', NULL, 'frame-2'),
(126, 1, '06971', '$2y$10$4MxgtX4tkx312ivBl8rTb.7yhLAxSx5qTMJuTu1zYam3omnxsEXWi', 'เด็กชายธีร์วัช ภักดี', 'student', 0, '06971', NULL, NULL, NULL, 'default_avatar.png', '2026-02-18 06:56:15', NULL, 'frame-0'),
(127, 1, '06983', '$2y$10$15GdBTzMIU3Yu2kWz6j/E.vBKWL0SJ.mkg8xQikIXOI.dWXolxboG', 'เด็กหญิงชนนพร นำพา', 'student', 0, '06983', NULL, NULL, NULL, 'default_avatar.png', '2026-02-18 06:57:19', NULL, 'frame-0'),
(128, 1, '06962', '$2y$10$mBWMul6IfCezn7CJKt0dvulVUKwjHQ3cuPpKWwF4wzkBLyqfFIUqe', 'เด็กชายคุณกร อินทร์ย้อย', 'student', 0, '06962', NULL, NULL, NULL, 'default_avatar.png', '2026-02-18 06:59:14', NULL, 'frame-0');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
