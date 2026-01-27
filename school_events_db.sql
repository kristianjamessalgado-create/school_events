-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2026 at 12:51 PM
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
-- Database: `school_events_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `photo` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `attended_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `organizer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','closed') DEFAULT 'active',
  `department` enum('ALL','BSIT','BSHM','CONAHS','Senior High') NOT NULL DEFAULT 'ALL'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `date`, `location`, `organizer_id`, `created_at`, `status`) VALUES
(1, 'Sample Orientation', 'Orientation for new students', '2025-12-20', 'Main Auditorium', 17, '2025-12-18 06:30:41', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `qr_code` varchar(255) DEFAULT NULL,
  `status` enum('absent','present') DEFAULT 'absent',
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','organizer','student') NOT NULL,
  `department` enum('BSIT','BSHM','CONAHS','Senior High') DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_attempts` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `failed_attempts`) VALUES
(1, 'SA-001', 'Kristian', 'kristian@school.com', '$2y$10$PASTE_HASH_HERE', 'super_admin', 'active', '2025-12-15 17:38:46', 1),
(3, 'SA-001', 'Kristian Salgado', 'kristian1@school.com', 'c29f2cbce5be41ea7fb18980fd1b71f543190f98043078742c5f55f064105f03', 'super_admin', 'active', '2025-12-15 18:09:54', 0),
(4, 'STU-781', 'sample', 'sample@gmail.com', 'af2bdbe1aa9b6ec1e2ade1d694f41fc71a831d0268e9891562113d8a62add1bf', 'student', 'active', '2025-12-15 18:32:46', 0),
(5, 'STU-776', 'suai', 'suai@gmail.com', 'dea259230178e8b71e2ee186546e9cd6c56b7922b4519e6835d6ebc507f0c64e', 'student', 'active', '2025-12-15 18:55:30', 0),
(6, 'STU-842', 'suai1', 'suai1@gmail.com', '95017d43936602810b094c76394c23082c9fd16aa1d95829e33cd44d6a858a77', 'student', 'active', '2025-12-15 18:57:12', 0),
(7, 'STU-738', 'suai2', 'suai2@gmail.com', 'ba1889bd80d5dffe82089bb71ca4683831c4c872ef817b1306421c421130eee2', 'student', 'active', '2025-12-15 18:57:32', 0),
(8, 'STU-351', 'suai3', 'suai3@gmail.com', '4e78ba3f87382d0ce4a41471737af8f0b2310c6fc7295b4a40ab00508f2f9620', 'student', 'active', '2025-12-15 18:59:36', 0),
(9, 'STU-253', 'suai4', 'suai4@gmail.com', '51ee5f59976acb3565d0609c70cb939ded012c4267c3d0fce29a5af2562129d4', 'student', 'active', '2025-12-15 19:00:19', 0),
(10, 'STU-781', 'suai5', 'suai5@gmail.com', 'd65c023117c75d15c1f117f53ac85232fdb728513310411aceae11449f350d43', 'student', 'active', '2025-12-15 19:01:36', 0),
(11, 'STU-979', 'another sample', 'anothersample@gmail.com', 'a08f8859605f0b362db593d2a8e756f0f65a334800b575329cf7d5af6d424f21', 'student', 'active', '2025-12-16 07:11:30', 0),
(12, 'STU-512', 'sample1', 'sample1@gmail.com', 'e85130791f31db1699f61a5e7ae7b5e85e70399414f38476091896214771cd17', 'student', 'active', '2025-12-16 07:21:16', 0),
(13, 'STU-913', 'sample4', 'sample4@gmail.com', 'dcd16348a2f53d7d4c4ad31e427aaf6561d56abb72ae60b32d30ddde2f8b49f3', 'student', 'active', '2025-12-16 08:41:26', 0),
(14, 'STU-226', 'sample5', 'sample5@gmail.com', '2444460794c78f2f46aa6491ce7cc4b460294ac6431aac2e560a193298a41b71', 'student', 'active', '2025-12-17 04:05:13', 0),
(15, 'ORG-318', 'organizer', 'organizer@gmail.com', '154a0a277d0a9e90475532eeb50bb087f6dcf19172db5fc8091221091c772ac5', 'organizer', 'active', '2025-12-18 04:23:41', 0),
(16, 'ORG-206', 'organizer1', 'organizer1@gmail.com', '154a0a277d0a9e90475532eeb50bb087f6dcf19172db5fc8091221091c772ac5', 'organizer', 'active', '2025-12-18 06:16:33', 3),
(17, 'ORG-880', 'organizer2', 'organizer2@gmail.com', 'ee343017bb298f2dc9eebf68ee2ccc54b7878d8db19636b27570aa5e8c0888ff', 'organizer', 'active', '2025-12-18 06:25:39', 0),
(18, 'STU-238', 'sample6', 'sample6@gmail.com', 'bcd8eb16b2ae1c881de513d28a3f49426afaa1ab34a3e834df5fbf7bdcbe9770', 'student', 'active', '2025-12-18 07:41:13', 1),
(19, 'STU-558', 'sample7', 'sample7@gmail.com', '24714505b9df6e69f9367f12217d590d4f15b4367d1697b6f833d1e07b291d2a', 'student', 'active', '2025-12-19 05:57:19', 0),
(20, 'ORG-923', 'samplereg', 'Samplereg@gmail.com', '76cd579e5eea4f719469276719558fa1b46c0196a613cb8aa5bfcdd9a43628f8', 'organizer', 'active', '2025-12-21 06:09:01', 0),
(21, 'ORG-994', 'samplereg2', 'Samplereg2@gmail.com', '64be8f3069aeb2299bc66aa17b7c0e47530d5dc0475ef0606ea6ffbaad04f819', 'organizer', 'active', '2025-12-21 06:44:58', 0),
(22, 'ORG-964', 'samplereg3', 'Samplereg3@gmail.com', '8d9353a8accc17cba0ee8dab3b744552799c6f37226a5b9e48a37cb82945de8f', 'organizer', 'active', '2025-12-21 06:50:28', 1),
(23, 'STU-827', 'sammilby', 'sammilby@gmail.com', '5e6eb2532b6f1eb86b9bfd41c5c1e9ca14d444eb013c223c0958b9dde57fd54f', 'student', 'active', '2025-12-22 03:55:21', 0),
(24, 'STU-510', 'deanecamat', 'deanecamat@gmail.com', 'faae366a9b3bc5e637a5f10b53a826ce31546dfb03a1cc3f7849001906d13dff', 'student', 'active', '2025-12-23 12:42:31', 0),
(25, 'STU-939', 'jabes', 'jabes@gmail.com', '781e60f7f136510363f9a9522ebeb73f647d94111c6e2d27fd669e0770a26aef', 'student', 'active', '2025-12-23 15:13:46', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
