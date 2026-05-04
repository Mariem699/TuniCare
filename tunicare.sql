-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 04 mai 2026 à 11:50
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `tunicare`
--

-- --------------------------------------------------------

--
-- Structure de la table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `doctor_email` varchar(255) DEFAULT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `is_notified` tinyint(1) DEFAULT 0,
  `deleted_for` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deleted_for`)),
  `patient_notified` tinyint(1) DEFAULT 0,
  `notif_seen` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `appointments`
--

INSERT INTO `appointments` (`id`, `doctor_email`, `patient_email`, `date`, `time`, `status`, `reason`, `is_notified`, `deleted_for`, `patient_notified`, `notif_seen`) VALUES
(1, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-04', '08:00:00', 'cancelled', '', 0, '[\"mi@gmail.com\", \"ahmed@gmail.com\"]', 0, 0),
(2, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-04', '08:00:00', 'cancelled', '', 0, '[\"ahmed@gmail.com\", \"mi@gmail.com\"]', 1, 0),
(3, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-05', '08:00:00', 'cancelled', '', 0, NULL, 0, 0),
(4, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-06', '08:00:00', 'cancelled', '', 0, NULL, 1, 0),
(5, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-05', '08:00:00', 'cancelled', '', 0, NULL, 1, 0),
(6, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-05', '08:00:00', 'cancelled', '', 0, NULL, 1, 0),
(7, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-06', '08:00:00', 'cancelled', '', 0, NULL, 1, 0),
(8, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-04', '08:00:00', 'cancelled', '', 0, NULL, 0, 0),
(9, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-04', '10:30:00', 'cancelled', '', 0, NULL, 0, 0),
(10, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-04', '08:00:00', 'cancelled', '', 0, NULL, 0, 0),
(11, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-04', '08:00:00', 'cancelled', '', 0, NULL, 0, 0),
(12, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-05', '08:00:00', 'cancelled', '', 0, NULL, 0, 0),
(13, 'ahmed@gmail.com', 'mi@gmail.com', '2026-05-04', '08:00:00', 'pending', '', 0, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user1` varchar(100) DEFAULT NULL,
  `user2` varchar(100) DEFAULT NULL,
  `deleted_by_user1` tinyint(4) DEFAULT 0,
  `deleted_by_user2` tinyint(4) DEFAULT 0,
  `last_message` text DEFAULT NULL,
  `last_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `conversations`
--

INSERT INTO `conversations` (`id`, `user1`, `user2`, `deleted_by_user1`, `deleted_by_user2`, `last_message`, `last_time`) VALUES
(55, 'ahmed@gmail.com', 'mi@gmail.com', 0, 0, 'v', '2026-05-04 00:29:25');

-- --------------------------------------------------------

--
-- Structure de la table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `fname` varchar(50) DEFAULT NULL,
  `lname` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `speciality` text DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `doctors`
--

INSERT INTO `doctors` (`id`, `fname`, `lname`, `email`, `phone`, `speciality`, `password`) VALUES
(1, 'Ahmed', 'Ali', 'ahmed@gmail.com', '11111111', 'General Doctor', '$2y$10$htTGhnLUdW9Q9yYLeLr.0uDmgPREaNT1hBJH1ssR.bj1xUmGNcJ.q'),
(3, 'Dr', 'Ali', 'ali@gmail.com', '22222222', 'Cardiologist', '$2y$10$DaMi2KhG6moEvGSaUX0Xq.a5vqvo8/pwXRMmcBa7L0nG/8pjRzkOy'),
(4, 'Dr', 'Mohamed', 'mohamed@gmail.com', '33333333', 'Dermatologist', '$2y$10$Y88ymeOXj7KvkWz.jb60IeFOrpaXM1zu4qMwE7/YGXCtRIQUGd3kO'),
(5, 'Dr', 'Salah', 'salah@gmail.com', '44444444', 'Pediatrician', '$2y$10$gvWvuIu6dttSLiPFPQwoj.6zVKdasFPlQOt1TZOm6GLJZU3bwdnwS'),
(6, 'Dr', 'Amir', 'amir@gmail.com', '55555555', 'Neurologist', '$2y$10$FvTYye5G5Xw1j6GRTTEUwO.5e5gWE3Fcr2jFRujYVGd9K5K1j6pnG'),
(7, 'Dr', 'Daniel', 'daniel@gmail.com', '66666666', 'pulmonology', '$2y$10$yrRYE.yItdsh0BhkMFgreuzUQmxMo5ev0afmvDXipxsqoMgKN8H2O'),
(8, 'Dr', 'Mourad', 'mourad@gmail.com', '77777777', 'allergist', '$2y$10$ERoW7wLFs1pZ/uVyWf5gh.ouA6Hm38Bv4ZNzCls9qtu5k3drrHGUC');

-- --------------------------------------------------------

--
-- Structure de la table `doctor_schedule`
--

CREATE TABLE `doctor_schedule` (
  `id` int(11) NOT NULL,
  `doctor_email` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `goals`
--

CREATE TABLE `goals` (
  `id` int(11) NOT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `target` int(11) DEFAULT NULL,
  `current` int(11) DEFAULT 0,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `health_data`
--

CREATE TABLE `health_data` (
  `id` int(11) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `glucose` float DEFAULT NULL,
  `tension` varchar(20) DEFAULT NULL,
  `water` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `health_data`
--

INSERT INTO `health_data` (`id`, `email`, `date`, `weight`, `glucose`, `tension`, `water`) VALUES
(1, 'm@gmail.com', '2026-04-18', 55, 100, '16', 8),
(2, 'mi@gmail.com', '2026-04-18', 53, 100, '16', 7),
(3, 'mi@gmail.com', '2026-04-19', 53, 90, '15', 9),
(4, 'mi@gmail.com', '2026-04-21', 53, 100, '16', 6),
(5, 'mi@gmail.com', '2026-04-23', 53, 100, '16', 10),
(6, 'mi@gmail.com', '2026-04-25', 53, 100, '15', 10),
(7, 'mi@gmail.com', '2026-04-26', 54, 100, '12', 8),
(8, 'mi@gmail.com', '2026-04-27', 0, 0, '', 7),
(9, 'mi@gmail.com', '2026-04-28', 0, 0, '', 7),
(10, 'mi@gmail.com', '2026-05-02', 0, 0, '', 10),
(11, 'mi@gmail.com', '2026-05-03', 53, 100, '80', 9),
(12, 'mi@gmail.com', '2026-05-04', 53, 100, '12', 9);

-- --------------------------------------------------------

--
-- Structure de la table `medications`
--

CREATE TABLE `medications` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `time_take` time DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `times_per_day` int(11) DEFAULT 1,
  `time2` time DEFAULT NULL,
  `time3` time DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_read` tinyint(1) DEFAULT 0,
  `deleted_for` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deleted_for`)),
  `notified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `medications`
--

INSERT INTO `medications` (`id`, `email`, `name`, `dosage`, `time_take`, `start_date`, `duration_days`, `created_at`, `times_per_day`, `time2`, `time3`, `is_active`, `is_read`, `deleted_for`, `notified`) VALUES
(3, 'mi@gmail.com', 'Doliprane', '1', '08:00:00', '2026-04-18', 7, '2026-04-18 20:22:27', 1, NULL, NULL, 0, 1, '[\"mi@gmail.com\"]', 0),
(4, 'mi@gmail.com', 'Doliprane', '1', '12:00:00', '2026-04-18', 7, '2026-04-18 20:22:27', 1, NULL, NULL, 0, 1, '[\"mi@gmail.com\"]', 0),
(5, 'mi@gmail.com', 'Doliprane', '1', '20:00:00', '2026-04-18', 7, '2026-04-18 20:22:27', 1, NULL, NULL, 0, 1, '[\"mi@gmail.com\"]', 0),
(6, 'mi@gmail.com', 'Doliprane', '1', '08:00:00', '2026-05-02', 7, '2026-05-02 14:12:43', 1, '12:00:00', '20:00:00', 0, 1, '[\"mi@gmail.com\"]', 0),
(7, 'mi@gmail.com', 'Doliprane', '1', '08:00:00', '2026-05-02', 7, '2026-05-02 14:35:36', 1, '12:00:00', '20:00:00', 1, 1, '[\"mi@gmail.com\"]', 1),
(8, 'mi@gmail.com', 'Doliprane', '1', '08:00:00', '2026-05-04', 7, '2026-05-04 09:03:11', 1, '12:00:00', '20:00:00', 1, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `conversation_id` int(11) DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `receiver_email` varchar(255) DEFAULT NULL,
  `deleted_for` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deleted_for`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `message`, `is_read`, `created_at`, `conversation_id`, `sender_email`, `receiver_email`, `deleted_for`) VALUES
(66, 'hi', 1, '2026-05-02 22:47:45', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\",\"ahmed@gmail.com\"]'),
(67, 'hi', 1, '2026-05-02 22:48:08', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\",\"ahmed@gmail.com\"]'),
(68, 'hi', 1, '2026-05-02 22:48:41', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\",\"ahmed@gmail.com\"]'),
(69, 'hi hi', 1, '2026-05-02 22:48:51', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\",\"mi@gmail.com\"]'),
(70, 'hiiiiiiiiiiiiiiii', 1, '2026-05-02 22:49:09', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"ahmed@gmail.com\",\"mi@gmail.com\"]'),
(71, 'hi hi', 1, '2026-05-02 22:58:50', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(72, 'hiiiiiiiii', 1, '2026-05-02 22:58:54', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(73, 'hi', 1, '2026-05-02 22:59:26', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\",\"ahmed@gmail.com\"]'),
(74, 'hi', 1, '2026-05-02 22:59:27', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\",\"ahmed@gmail.com\"]'),
(75, 'hi', 1, '2026-05-02 22:59:28', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\",\"ahmed@gmail.com\"]'),
(76, 'hi', 1, '2026-05-02 22:59:29', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\",\"ahmed@gmail.com\"]'),
(77, 'hi doctor', 1, '2026-05-03 11:44:33', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(78, 'hi', 1, '2026-05-03 11:45:02', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(79, 'hiiiiiiiiiiiiiiiiiiiiiii', 1, '2026-05-03 11:45:29', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\", \"ahmed@gmail.com\"]'),
(80, 'hiiiiiiiiiiiiiiiiiiiiiiiiiii', 1, '2026-05-03 11:46:12', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(81, 'hi hih ihihihih', 1, '2026-05-03 16:18:25', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\", \"ahmed@gmail.com\"]'),
(82, 'ihih', 1, '2026-05-03 16:18:26', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\", \"ahmed@gmail.com\"]'),
(83, 'hihih', 1, '2026-05-03 16:18:26', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\", \"ahmed@gmail.com\"]'),
(84, 'ihihih', 1, '2026-05-03 16:18:27', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\", \"ahmed@gmail.com\"]'),
(85, 'hihi*', 1, '2026-05-03 16:18:29', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"mi@gmail.com\", \"ahmed@gmail.com\"]'),
(86, 'hi', 1, '2026-05-03 16:18:37', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(87, 'hi', 1, '2026-05-03 16:18:38', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(88, 'hi', 1, '2026-05-03 16:19:22', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(89, 'hi', 1, '2026-05-03 16:19:23', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(90, 'hih', 1, '2026-05-03 16:19:24', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(91, 'ihih', 1, '2026-05-03 16:19:25', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(92, 'hihih', 1, '2026-05-03 16:19:26', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(93, 'hi', 1, '2026-05-03 16:28:24', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(94, 'hi', 1, '2026-05-03 16:28:25', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(95, 'hi', 1, '2026-05-03 16:28:26', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(96, 'hi', 1, '2026-05-03 16:28:27', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(97, 'hi', 1, '2026-05-03 16:30:45', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(98, 'hi', 1, '2026-05-03 16:30:46', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(99, 'hi', 1, '2026-05-03 16:30:46', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(100, 'hi', 1, '2026-05-03 16:30:47', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(101, 'hi', 1, '2026-05-03 16:30:48', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(102, 'hi', 1, '2026-05-03 16:30:48', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(103, 'hi', 1, '2026-05-03 16:30:49', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(104, 'hi', 1, '2026-05-03 16:30:53', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(105, 'hihihihihihihi', 1, '2026-05-03 16:31:35', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(106, 'hi', 1, '2026-05-03 16:31:38', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(107, 'hi', 1, '2026-05-03 16:31:39', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(108, 'hi', 1, '2026-05-03 16:31:40', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(109, 'hi', 1, '2026-05-03 16:57:24', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(110, 'hi', 1, '2026-05-03 16:57:25', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(111, 'hi', 1, '2026-05-03 16:57:25', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(112, 'hihihihihihih', 1, '2026-05-03 16:59:18', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(113, 'hi hi hi', 1, '2026-05-03 17:00:20', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(114, 'hi', 1, '2026-05-03 17:00:23', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(115, '$y', 1, '2026-05-03 17:00:24', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(116, 'hy', 1, '2026-05-03 17:00:24', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(117, 'yrh', 1, '2026-05-03 17:00:25', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(118, 'yr', 1, '2026-05-03 17:00:25', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(119, 'yhyr', 1, '2026-05-03 17:00:25', 55, 'mi@gmail.com', 'ahmed@gmail.com', '[\"ahmed@gmail.com\"]'),
(120, 'hi', 1, '2026-05-03 17:06:42', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(121, 'ht', 1, '2026-05-03 17:06:43', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(122, 'yht', 1, '2026-05-03 17:06:43', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(123, 'yht', 1, '2026-05-03 17:06:43', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(124, 'y', 1, '2026-05-03 17:06:44', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(125, 'ty', 1, '2026-05-03 17:06:44', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(126, 'tyh', 1, '2026-05-03 17:06:44', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(127, 'hyhy', 1, '2026-05-03 17:06:45', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(128, 'hihihi', 1, '2026-05-03 17:12:40', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(129, 'hyth', 1, '2026-05-03 17:12:41', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(130, 'yth', 1, '2026-05-03 17:12:42', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(131, 'h', 1, '2026-05-03 17:12:42', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(132, 'y', 1, '2026-05-03 17:12:42', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(133, 'h', 1, '2026-05-03 17:12:42', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(134, 'h', 1, '2026-05-03 17:12:43', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(135, 'g', 1, '2026-05-03 17:12:43', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(136, 'byhbygh', 1, '2026-05-03 17:12:44', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(137, 'y', 1, '2026-05-03 17:12:44', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(138, 'h', 1, '2026-05-03 17:12:44', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(139, 'y', 1, '2026-05-03 17:12:44', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(140, 'h', 1, '2026-05-03 17:12:45', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(141, 'h', 1, '2026-05-03 17:12:45', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(142, 'th', 1, '2026-05-03 17:41:55', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(143, 'h', 1, '2026-05-03 17:41:55', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(144, 'h', 1, '2026-05-03 17:41:55', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(145, 'h', 1, '2026-05-03 17:41:55', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(146, 'h', 1, '2026-05-03 17:41:56', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(147, 'h', 1, '2026-05-03 17:41:56', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(148, 'h', 1, '2026-05-03 17:41:56', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(149, 'h', 1, '2026-05-03 17:41:56', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(150, 'g', 1, '2026-05-03 17:41:56', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(151, 'g', 1, '2026-05-03 17:41:57', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(152, 'g', 1, '2026-05-03 17:41:57', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(153, 'vfv', 1, '2026-05-03 23:29:24', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(154, 'f', 1, '2026-05-03 23:29:24', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]'),
(155, 'v', 1, '2026-05-03 23:29:25', 55, 'ahmed@gmail.com', 'mi@gmail.com', '[\"mi@gmail.com\"]');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `fname` varchar(50) DEFAULT NULL,
  `lname` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `kg` int(11) DEFAULT NULL,
  `water` int(11) DEFAULT 0,
  `last_reset` date DEFAULT NULL,
  `water_today` int(11) DEFAULT 0,
  `glucose_today` float DEFAULT NULL,
  `tension_today` varchar(20) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'patient'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `fname`, `lname`, `age`, `sex`, `email`, `phone`, `history`, `allergies`, `doctor_id`, `password`, `kg`, `water`, `last_reset`, `water_today`, `glucose_today`, `tension_today`, `role`) VALUES
(87, 'mi', 'mi', 22, 'female', 'mi@gmail.com', '12345678', 'none_history', 'none', 1, '$2y$10$lnm1udRkl6a45Wnpwu4NqO.qiYwPf3ETPyzDbso8O/hBrD4dB80qW', 53, 0, '2026-05-04', 9, NULL, NULL, 'patient');

-- --------------------------------------------------------

--
-- Structure de la table `patient_doctors`
--

CREATE TABLE `patient_doctors` (
  `id` int(11) NOT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `doctor_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `patient_doctors`
--

INSERT INTO `patient_doctors` (`id`, `patient_email`, `doctor_email`) VALUES
(5, 'mi@gmail.com', 'ahmed@gmail.com');

-- --------------------------------------------------------

--
-- Structure de la table `recommendations`
--

CREATE TABLE `recommendations` (
  `id` int(11) NOT NULL,
  `doctor_email` varchar(255) DEFAULT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `water_history`
--

CREATE TABLE `water_history` (
  `id` int(11) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `glasses` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_email` (`patient_email`,`status`,`patient_notified`);

--
-- Index pour la table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pair` (`user1`,`user2`),
  ADD KEY `idx_conv_time` (`last_time`);

--
-- Index pour la table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `doctor_schedule`
--
ALTER TABLE `doctor_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `health_data`
--
ALTER TABLE `health_data`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `patient_doctors`
--
ALTER TABLE `patient_doctors`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `recommendations`
--
ALTER TABLE `recommendations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `water_history`
--
ALTER TABLE `water_history`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT pour la table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `doctor_schedule`
--
ALTER TABLE `doctor_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `goals`
--
ALTER TABLE `goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `health_data`
--
ALTER TABLE `health_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT pour la table `patient_doctors`
--
ALTER TABLE `patient_doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `recommendations`
--
ALTER TABLE `recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `water_history`
--
ALTER TABLE `water_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
