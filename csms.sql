-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 11, 2025 at 05:18 PM
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
-- Database: `csms`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_pic` varchar(255) NOT NULL DEFAULT 'newp.jpg',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `admin_id`, `admin_username`, `created_at`) VALUES
(1, 'ICT Congress', 'Coming Soon...', 1, 'admin', '2025-03-11 16:17:28');

-- --------------------------------------------------------

--
-- Table structure for table `labs`
--

CREATE TABLE `labs` (
  `lab_id` int(11) NOT NULL,
  `lab_name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `labs`
--

INSERT INTO `labs` (`lab_id`, `lab_name`, `capacity`) VALUES
(1, 'Laboratory 524', 30),
(2, 'Laboratory 526', 30),
(3, 'Laboratory 528', 30),
(4, 'Laboratory 530', 30),
(5, 'Laboratory 542', 30),
(6, 'Mac Laboratory', 25);

-- --------------------------------------------------------

--
-- Table structure for table `sit_in_sessions`
--

CREATE TABLE `sit_in_sessions` (
  `session_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `computer_id` int(11) DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `sit_in_sessions`
--

INSERT INTO `sit_in_sessions` (`session_id`, `student_id`, `student_name`, `lab_id`, `computer_id`, `purpose`, `check_in_time`, `check_out_time`, `status`, `admin_id`, `created_at`) VALUES
(1, '22596886', 'Real, Rovic Steve R.', 3, 1, 'Java Programming', '2025-03-11 17:17:41', NULL, 'active', 1, '2025-03-11 16:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `computers`
--

CREATE TABLE `computers` (
  `computer_id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `computer_name` varchar(50) NOT NULL,
  `status` enum('available','in-use','maintenance') NOT NULL DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `computers`
--

INSERT INTO `computers` (`computer_id`, `lab_id`, `computer_name`, `status`) VALUES
(1, 3, 'PC-01', 'in-use'),
(2, 3, 'PC-02', 'available'),
(3, 3, 'PC-03', 'available'),
(4, 1, 'PC-01', 'available'),
(5, 1, 'PC-02', 'available'),
(6, 2, 'PC-01', 'available');

-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sit_in_feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `feedback` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  INDEX (`session_id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_feedback_session` FOREIGN KEY (`session_id`) 
    REFERENCES `sit_in_sessions` (`session_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `USER_ID` int(11) NOT NULL,
  `IDNO` varchar(50) NOT NULL,
  `LASTNAME` varchar(100) NOT NULL,
  `FIRSTNAME` varchar(100) NOT NULL,
  `MIDDLENAME` varchar(100) DEFAULT NULL,
  `COURSE` varchar(100) NOT NULL,
  `YEARLEVEL` varchar(20) NOT NULL,
  `USERNAME` varchar(100) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL,
  `EMAIL` varchar(255) NOT NULL,
  `ADDRESS` varchar(255) NOT NULL,
  `PROFILE_PICTURE` varchar(255) NOT NULL DEFAULT 'profile.jpg',
  `remaining_sessions` int(11) NOT NULL DEFAULT 30
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`USER_ID`, `IDNO`, `LASTNAME`, `FIRSTNAME`, `MIDDLENAME`, `COURSE`, `YEARLEVEL`, `USERNAME`, `PASSWORD`, `EMAIL`, `ADDRESS`, `PROFILE_PICTURE`, `remaining_sessions`) VALUES
(1, '22596886', 'Real', 'Rovic Steve', 'Ramas', 'BSIT', '3', 'rovic', '123', 'rovic@gmail.com', 'Ermita', 'uploads/gojo.jpg', 29);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `computer_id` int(11) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `time_slot` varchar(50) NOT NULL,
  `purpose` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`),
  KEY `user_id` (`user_id`),
  KEY `computer_id` (`computer_id`),
  CONSTRAINT `fk_res_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_res_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_res_computer` FOREIGN KEY (`computer_id`) REFERENCES `computers` (`computer_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`lab_id`);

--
-- Indexes for table `sit_in_sessions`
--
ALTER TABLE `sit_in_sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `computers`
--
ALTER TABLE `computers`
  ADD PRIMARY KEY (`computer_id`),
  ADD KEY `lab_id` (`lab_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`USER_ID`),
  ADD UNIQUE KEY `IDNO` (`IDNO`),
  ADD UNIQUE KEY `USERNAME` (`USERNAME`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `computer_id` (`computer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `labs`
--
ALTER TABLE `labs`
  MODIFY `lab_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sit_in_sessions`
--
ALTER TABLE `sit_in_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `computers`
--
ALTER TABLE `computers`
  MODIFY `computer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `USER_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Add foreign key constraints
--
ALTER TABLE `sit_in_sessions`
  ADD CONSTRAINT `fk_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_computer` FOREIGN KEY (`computer_id`) REFERENCES `computers` (`computer_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `computers`
  ADD CONSTRAINT `fk_computer_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
