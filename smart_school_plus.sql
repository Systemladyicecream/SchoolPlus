-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2026 at 02:12 PM
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
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `year_id` int(6) UNSIGNED NOT NULL,
  `school_id` int(6) NOT NULL,
  `year_name` varchar(4) NOT NULL COMMENT 'เช่น 2567',
  `term` varchar(1) NOT NULL COMMENT 'เช่น 1 หรือ 2',
  `total_sessions` int(11) NOT NULL DEFAULT 20,
  `is_active` tinyint(1) DEFAULT 0 COMMENT '1=เทอมปัจจุบัน',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`year_id`, `school_id`, `year_name`, `term`, `total_sessions`, `is_active`, `created_at`) VALUES
(1, 1, '2568', '2', 20, 1, '2026-01-29 04:57:34'),
(2, 1, '2569', '1', 20, 0, '2026-01-29 16:23:05');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `class_level` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `target_student_ids` text DEFAULT NULL COMMENT 'JSON id นักเรียน หรือ null ถ้าทั้งห้อง',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `max_score` float DEFAULT 10,
  `assignment_type` varchar(50) DEFAULT NULL,
  `submission_config` text DEFAULT NULL COMMENT 'JSON config',
  `options_config` text DEFAULT NULL COMMENT 'JSON options',
  `attachments` text DEFAULT NULL COMMENT 'JSON file paths',
  `links` text DEFAULT NULL COMMENT 'JSON links',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submission_types` text DEFAULT NULL COMMENT 'JSON config: file, text, link, photo, voice',
  `allow_late` tinyint(1) DEFAULT 0,
  `allow_edit` tinyint(1) DEFAULT 1,
  `is_group_work` tinyint(1) DEFAULT 0,
  `random_check` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `allow_resubmit` tinyint(1) DEFAULT 1,
  `target_students` text DEFAULT NULL COMMENT 'JSON student_ids',
  `rubric_data` text DEFAULT NULL COMMENT 'JSON Rubric Criteria'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`assignment_id`, `school_id`, `teacher_id`, `year_id`, `course_code`, `class_level`, `room_number`, `target_student_ids`, `title`, `description`, `assigned_date`, `due_date`, `max_score`, `assignment_type`, `submission_config`, `options_config`, `attachments`, `links`, `created_at`, `submission_types`, `allow_late`, `allow_edit`, `is_group_work`, `random_check`, `is_hidden`, `allow_resubmit`, `target_students`, `rubric_data`) VALUES
(2, 1, 9, 0, 'ค20150 - ม.1', 'ม.1', '2', NULL, '1', '<p>1</p>', NULL, '2026-02-07 23:13:00', 10, 'homework', NULL, NULL, NULL, NULL, '2026-01-30 16:13:39', NULL, 0, 1, 0, 0, 0, 1, NULL, NULL),
(3, 1, 17, 0, 'ว22182 - ม.2', 'ม.2', '1', NULL, 'การคาดการณ์เทคโนโลยีในอนาคต', '<p>มายแมพ</p>', NULL, '2026-02-19 14:00:00', 10, 'homework', NULL, NULL, NULL, NULL, '2026-02-18 07:00:26', NULL, 0, 1, 0, 0, 0, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('present','absent','late','leave') NOT NULL,
  `score` float DEFAULT 0,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `session_id`, `student_id`, `status`, `score`, `note`) VALUES
(2, 1, 12, 'absent', 0, NULL),
(3, 2, 12, 'present', 1, NULL),
(4, 3, 12, 'present', 1, NULL),
(5, 4, 12, 'present', 1, NULL),
(6, 5, 12, 'present', 1, NULL),
(10, 8, 126, 'present', 1, NULL),
(11, 8, 127, 'present', 1, NULL),
(12, 8, 128, 'present', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `class_level` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_sessions`
--

INSERT INTO `attendance_sessions` (`id`, `school_id`, `teacher_id`, `course_code`, `course_name`, `class_level`, `room_number`, `attendance_date`, `created_at`) VALUES
(1, 1, 9, 'ว30101', 'วิทยาการคำนวณ', 'ม.1', '2', '2026-01-30', '2026-01-29 17:14:46'),
(2, 1, 9, 'ค20150', 'คณิตสาตร์เพิ่มเติม', 'ม.1', '2', '2026-01-30', '2026-01-29 17:17:23'),
(3, 1, 9, 'ค20150', 'คณิตสาตร์เพิ่มเติม', 'ม.1', '2', '2026-01-31', '2026-01-29 17:26:14'),
(4, 1, 9, 'ค20150', 'คณิตสาตร์เพิ่มเติม', 'ม.1', '2', '2026-02-01', '2026-01-29 17:26:23'),
(5, 1, 9, 'ค20150', 'คณิตสาตร์เพิ่มเติม', 'ม.1', '2', '2026-02-02', '2026-01-29 17:26:30'),
(8, 1, 17, 'ว22182', 'ออกแบบและเทคโนโลยี 4', 'ม.2', '1', '2026-03-12', '2026-03-12 05:37:57');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(6) UNSIGNED NOT NULL,
  `school_id` int(6) NOT NULL,
  `course_code` varchar(20) NOT NULL COMMENT 'รหัสวิชา',
  `course_name` varchar(150) NOT NULL COMMENT 'ชื่อวิชา',
  `class_level` varchar(50) DEFAULT NULL,
  `subject_group` varchar(100) NOT NULL COMMENT 'กลุ่มสาระฯ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `school_id`, `course_code`, `course_name`, `class_level`, `subject_group`, `created_at`) VALUES
(4, 1, 'ว22182', 'ออกแบบและเทคโนโลยี 4', 'ม.2', 'วิทยาศาสตร์', '2026-02-09 03:39:19');

-- --------------------------------------------------------

--
-- Table structure for table `grade_criteria`
--

CREATE TABLE `grade_criteria` (
  `id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `year_id` int(11) DEFAULT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `class_level` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `criteria_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_criteria`
--

INSERT INTO `grade_criteria` (`id`, `school_id`, `teacher_id`, `year_id`, `course_code`, `class_level`, `room_number`, `criteria_json`, `created_at`, `is_published`, `published_at`) VALUES
(1, 1, 17, 1, 'ว22182 - ม.2', 'ม.2', '1', '[{\"name\":\"ระหว่างเรียน\",\"max\":50},{\"name\":\"กลางภาค\",\"max\":20},{\"name\":\"ปลายภาค\",\"max\":30}]', '2026-03-12 05:26:58', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `grade_scores`
--

CREATE TABLE `grade_scores` (
  `id` int(11) NOT NULL,
  `criteria_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `scores_json` text DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `homeroom_records`
--

CREATE TABLE `homeroom_records` (
  `id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `homeroom_sessions`
--

CREATE TABLE `homeroom_sessions` (
  `id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `year_id` int(11) DEFAULT NULL,
  `class_level` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `check_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(6) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`log_id`, `user_id`, `login_time`, `ip_address`) VALUES
(1, 2, '2026-01-29 11:36:03', '::1'),
(2, 2, '2026-01-29 11:43:49', '::1'),
(3, 2, '2026-01-29 11:44:51', '::1'),
(4, 7, '2026-01-29 11:56:33', '::1'),
(5, 9, '2026-01-29 12:07:30', '::1'),
(6, 7, '2026-01-29 12:09:17', '::1'),
(7, 10, '2026-01-29 12:10:33', '::1'),
(8, 7, '2026-01-29 12:11:59', '::1'),
(9, 2, '2026-01-29 14:31:00', '::1'),
(10, 2, '2026-01-29 14:52:29', '::1'),
(11, 7, '2026-01-29 15:18:43', '::1'),
(12, 9, '2026-01-29 15:20:15', '::1'),
(13, 10, '2026-01-29 15:22:14', '::1'),
(14, 2, '2026-01-29 15:27:24', '::1'),
(15, 11, '2026-01-29 15:51:52', '::1'),
(16, 2, '2026-01-29 15:57:04', '::1'),
(17, 2, '2026-01-29 16:12:29', '::1'),
(18, 11, '2026-01-29 16:24:43', '::1'),
(19, 7, '2026-01-29 16:25:08', '::1'),
(20, 2, '2026-01-29 16:25:25', '::1'),
(21, 7, '2026-01-29 16:26:13', '::1'),
(22, 2, '2026-01-29 16:27:32', '::1'),
(23, 7, '2026-01-29 16:33:24', '::1'),
(24, 9, '2026-01-29 16:33:41', '::1'),
(25, 2, '2026-01-29 20:36:56', '::1'),
(26, 7, '2026-01-29 20:48:35', '::1'),
(27, 11, '2026-01-29 20:49:23', '::1'),
(28, 11, '2026-01-29 21:04:35', '::1'),
(29, 7, '2026-01-29 21:04:49', '::1'),
(30, 9, '2026-01-29 21:05:13', '::1'),
(31, 2, '2026-01-29 21:07:54', '::1'),
(32, 9, '2026-01-29 21:29:03', '::1'),
(33, 9, '2026-01-29 21:46:45', '::1'),
(34, 7, '2026-01-29 22:17:12', '::1'),
(35, 9, '2026-01-29 23:44:51', '::1'),
(36, 7, '2026-01-30 00:46:48', '::1'),
(37, 9, '2026-01-30 00:47:28', '::1'),
(38, 7, '2026-01-30 01:11:47', '::1'),
(39, 9, '2026-01-30 01:12:21', '::1'),
(40, 7, '2026-01-30 01:36:45', '::1'),
(41, 13, '2026-01-30 01:38:05', '::1'),
(42, 7, '2026-01-30 01:39:17', '::1'),
(43, 7, '2026-01-30 01:44:35', '::1'),
(44, 7, '2026-01-30 17:17:41', '::1'),
(45, 7, '2026-01-30 03:27:47', '184.22.53.108'),
(46, 2, '2026-01-30 03:50:12', '184.22.53.108'),
(47, 16, '2026-01-30 05:17:13', '49.230.67.233'),
(48, 16, '2026-01-30 05:31:32', '184.22.52.103'),
(49, 7, '2026-01-30 06:14:28', '184.22.52.103'),
(50, 7, '2026-01-30 07:00:49', '184.22.52.103'),
(51, 16, '2026-02-01 20:35:11', '49.237.89.1'),
(52, 9, '2026-02-01 20:54:21', '49.237.89.1'),
(53, 7, '2026-02-01 20:55:42', '49.237.89.1'),
(54, 16, '2026-02-02 01:20:23', '49.230.93.203'),
(55, 16, '2026-02-02 01:25:25', '49.230.93.203'),
(56, 7, '2026-02-02 22:27:31', '49.237.70.203'),
(57, 7, '2026-02-08 19:38:05', '49.237.66.3'),
(58, 7, '2026-02-08 19:39:43', '49.237.66.3'),
(59, 17, '2026-02-08 19:44:30', '49.237.66.3'),
(101, 7, '2026-02-17 22:40:10', '49.237.79.237'),
(102, 7, '2026-02-17 22:47:01', '49.237.79.237'),
(103, 17, '2026-02-17 22:47:46', '49.237.79.237'),
(104, 7, '2026-02-17 22:51:21', '49.237.79.237'),
(105, 127, '2026-02-17 22:58:17', '182.232.21.196'),
(106, 126, '2026-02-17 22:59:01', '27.55.94.244'),
(107, 17, '2026-02-17 22:59:33', '49.237.79.237'),
(108, 128, '2026-02-17 22:59:48', '182.232.19.71'),
(109, 126, '2026-02-17 23:03:28', '27.55.94.244'),
(110, 126, '2026-02-17 23:04:06', '27.55.94.244'),
(111, 7, '2026-02-24 07:01:52', '184.22.21.119'),
(112, 7, '2026-03-07 21:39:45', '184.22.15.130'),
(113, 17, '2026-03-07 21:53:27', '184.22.15.130'),
(114, 7, '2026-03-12 09:25:46', '::1'),
(115, 7, '2026-03-12 09:31:48', '::1'),
(116, 7, '2026-03-12 09:40:33', '::1'),
(117, 126, '2026-03-12 09:46:16', '::1'),
(118, 17, '2026-03-12 09:47:17', '::1'),
(119, 17, '2026-03-12 12:21:23', '::1'),
(120, 17, '2026-03-12 12:32:35', '::1'),
(121, 17, '2026-03-12 12:46:09', '::1'),
(122, 2, '2026-03-12 13:27:58', '::1'),
(123, 2, '2026-03-12 13:29:13', '::1'),
(124, 126, '2026-03-12 13:57:39', '::1'),
(125, 126, '2026-03-12 14:04:22', '::1'),
(126, 17, '2026-03-12 14:29:58', '::1'),
(127, 17, '2026-03-12 14:31:43', '::1'),
(128, 17, '2026-03-12 14:38:10', '::1'),
(129, 17, '2026-03-12 14:46:57', '::1'),
(130, 17, '2026-03-12 14:58:23', '::1'),
(131, 17, '2026-03-12 15:15:54', '::1'),
(132, 7, '2026-03-12 15:19:06', '::1'),
(133, 17, '2026-03-12 15:19:31', '::1'),
(134, 17, '2026-03-12 15:51:02', '::1'),
(135, 7, '2026-03-12 15:51:44', '::1'),
(136, 17, '2026-03-12 15:52:16', '::1'),
(137, 17, '2026-03-12 16:02:09', '::1'),
(138, 126, '2026-03-12 16:08:44', '::1'),
(139, 17, '2026-03-12 16:09:17', '::1'),
(140, 126, '2026-03-12 16:13:01', '::1'),
(141, 17, '2026-03-12 17:12:19', '::1'),
(142, 17, '2026-03-12 17:40:55', '::1'),
(143, 17, '2026-03-12 17:48:36', '::1'),
(144, 126, '2026-03-12 17:49:05', '::1'),
(145, 17, '2026-03-12 17:49:57', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `media_views`
--

CREATE TABLE `media_views` (
  `id` int(11) NOT NULL,
  `media_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `media_views`
--

INSERT INTO `media_views` (`id`, `media_id`, `student_id`, `viewed_at`) VALUES
(1, 2, 126, '2026-03-12 09:13:04');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `school_id` int(6) UNSIGNED NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `education_level` enum('ประถมศึกษา','ขยายโอกาส','มัธยมศึกษา') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`school_id`, `school_name`, `education_level`, `created_at`) VALUES
(1, 'โรงเรียนบ้านคาวิทยา', 'มัธยมศึกษา', '2026-01-28 20:00:00'),
(2, 'โรงเรียนเทศบาล 1 ทรงพลวิทยา', 'ขยายโอกาส', '2026-01-28 20:00:00'),
(3, 'โรงเรียนบ้านสวนผึ้ง', 'ประถมศึกษา', '2026-01-28 20:00:00'),
(4, 'โรงเรียนวันครู 2503 (บ้านหนองบัว)', 'ประถมศึกษา', '2026-01-28 20:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `student_behavior_logs`
--

CREATE TABLE `student_behavior_logs` (
  `id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `year_id` int(11) DEFAULT NULL,
  `log_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo_path` text DEFAULT NULL,
  `log_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `submission_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `files` text DEFAULT NULL COMMENT 'JSON paths',
  `text_content` text DEFAULT NULL,
  `links` text DEFAULT NULL COMMENT 'JSON links',
  `audio_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `score` float DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('submitted','late','graded','returned') DEFAULT 'submitted',
  `teacher_stamp` varchar(50) DEFAULT NULL COMMENT 'good, very_good, improve',
  `feedback_file` varchar(255) DEFAULT NULL,
  `feedback_audio` varchar(255) DEFAULT NULL,
  `rubric_scores` text DEFAULT NULL COMMENT 'JSON Rubric Scores'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`submission_id`, `assignment_id`, `student_id`, `files`, `text_content`, `links`, `audio_path`, `submitted_at`, `score`, `feedback`, `status`, `teacher_stamp`, `feedback_file`, `feedback_audio`, `rubric_scores`) VALUES
(1, 2, 12, NULL, 'ครับ', '', NULL, '2026-01-30 16:39:49', 10, '', 'graded', 'excellent', NULL, NULL, NULL),
(2, 3, 128, '[{\"name\":\"IMG_20260218_140138.jpg\",\"path\":\"sub_128_1771398174_0.jpg\"}]', '', '', NULL, '2026-02-18 07:02:58', 10, '', 'graded', 'excellent', NULL, NULL, NULL),
(3, 3, 127, '[{\"name\":\"IMG_20260218_140214_435.jpg\",\"path\":\"sub_127_1771398219_0.jpg\"}]', '', '', NULL, '2026-02-18 07:03:39', 10, '', 'graded', 'excellent', NULL, NULL, NULL),
(4, 3, 126, '[{\"name\":\"IMG_20260218_123950.jpg\",\"path\":\"sub_126_1771398322_0.jpg\"}]', '', '', NULL, '2026-02-18 07:05:22', 5, '', 'graded', 'excellent', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teaching_media`
--

CREATE TABLE `teaching_media` (
  `id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `year_id` int(11) DEFAULT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `class_level` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `media_type` varchar(20) DEFAULT NULL,
  `file_extension` varchar(20) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_path` text DEFAULT NULL,
  `is_visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teaching_media`
--

INSERT INTO `teaching_media` (`id`, `school_id`, `teacher_id`, `year_id`, `course_code`, `class_level`, `room_number`, `media_type`, `file_extension`, `title`, `description`, `file_path`, `is_visible`, `created_at`) VALUES
(1, 1, 17, 1, 'ว22182 - ม.2', 'ม.2', '1', 'link', 'link', 'บทที่ 1', '', 'https://www.canva.com/design/DAHCQC5mrwQ/ub6HwJgzHS_nsLbOFkBgRg/view?utlId=hb3d685695c', 1, '2026-03-12 09:12:06'),
(2, 1, 17, 1, 'ว22182 - ม.2', 'ม.2', '1', 'link', 'link', 'บทที่ 2', '', 'https://www.canva.com/design/DAG_htUgb-4/FySn1it1zK5-lZBC8voxuw/view?utlId=hc46e31b068', 1, '2026-03-12 09:12:46');

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

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `line_token` varchar(255) DEFAULT NULL,
  `notify_line` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`user_id`, `line_token`, `notify_line`) VALUES
(17, '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_year_data`
--

CREATE TABLE `user_year_data` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `student_number` int(11) DEFAULT 0,
  `class_level` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `subjects_taught` text DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_year_data`
--

INSERT INTO `user_year_data` (`id`, `school_id`, `user_id`, `year_id`, `student_number`, `class_level`, `room_number`, `subjects_taught`, `position`, `created_at`) VALUES
(3, 1, 12, 1, 1, 'ม.1', '2', NULL, NULL, '2026-01-29 16:39:56'),
(4, 1, 13, 1, 1, 'ม.1', '1', NULL, NULL, '2026-01-29 18:37:41'),
(5, 1, 14, 1, 0, 'นางสาวยุวดี เนวยา', 'ม.1', NULL, NULL, '2026-01-29 18:42:16'),
(6, 1, 15, 1, 0, '?.1', '1', NULL, NULL, '2026-01-29 19:01:44'),
(7, 1, 17, 1, 0, NULL, 'ม.2/1', '[{\"room\":\"ม.2\\/1\",\"subjects\":[\"ออกแบบและเทคโนโลยี 4 (ว22182) - ม.2\"]}]', NULL, '2026-02-09 03:42:11'),
(118, 1, 126, 1, 1, 'ม.2', '1', NULL, NULL, '2026-02-18 06:56:15'),
(119, 1, 127, 1, 2, 'ม.2', '1', NULL, NULL, '2026-02-18 06:57:19'),
(120, 1, 128, 1, 3, 'ม.2', '1', NULL, NULL, '2026-02-18 06:59:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`year_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `school_id` (`school_id`,`teacher_id`),
  ADD KEY `course_code` (`course_code`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`,`teacher_id`,`class_level`,`room_number`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `grade_criteria`
--
ALTER TABLE `grade_criteria`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `grade_scores`
--
ALTER TABLE `grade_scores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `homeroom_records`
--
ALTER TABLE `homeroom_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `homeroom_sessions`
--
ALTER TABLE `homeroom_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `media_views`
--
ALTER TABLE `media_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`media_id`,`student_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`school_id`);

--
-- Indexes for table `student_behavior_logs`
--
ALTER TABLE `student_behavior_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `teaching_media`
--
ALTER TABLE `teaching_media`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_year_data`
--
ALTER TABLE `user_year_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`year_id`),
  ADD KEY `school_id` (`school_id`,`year_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `year_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `grade_criteria`
--
ALTER TABLE `grade_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `grade_scores`
--
ALTER TABLE `grade_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `homeroom_records`
--
ALTER TABLE `homeroom_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `homeroom_sessions`
--
ALTER TABLE `homeroom_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `media_views`
--
ALTER TABLE `media_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `school_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `student_behavior_logs`
--
ALTER TABLE `student_behavior_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `submission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teaching_media`
--
ALTER TABLE `teaching_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `user_year_data`
--
ALTER TABLE `user_year_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
