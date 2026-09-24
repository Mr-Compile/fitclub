-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2025 at 11:47 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fitclub`
--

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `coach_id` int(11) NOT NULL,
  `assessment_date` date NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `body_fat` decimal(5,2) NOT NULL,
  `muscle_mass` decimal(5,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calorie_tracking`
--

CREATE TABLE `calorie_tracking` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `calories_burned` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coaches`
--

CREATE TABLE `coaches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` enum('bulk','weight_loss','strength') NOT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `clients_count` int(11) DEFAULT 0,
  `experience_years` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coaches`
--

INSERT INTO `coaches` (`id`, `user_id`, `specialization`, `rating`, `clients_count`, `experience_years`, `active`) VALUES
(1, 2, 'bulk', 4.50, 15, 3, 1),
(2, 3, 'weight_loss', 4.80, 20, 2, 1),
(3, 4, 'strength', 4.70, 18, 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `diet_plans`
--

CREATE TABLE `diet_plans` (
  `id` int(11) NOT NULL,
  `coach_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `specialization` enum('bulk','weight_loss','strength') NOT NULL,
  `duration_type` enum('daily','weekly') NOT NULL,
  `calories` int(11) NOT NULL,
  `protein` int(11) NOT NULL,
  `carbs` int(11) NOT NULL,
  `fats` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `diet_plans`
--

INSERT INTO `diet_plans` (`id`, `coach_id`, `name`, `description`, `specialization`, `duration_type`, `calories`, `protein`, `carbs`, `fats`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'Mass Gainer', 'High-calorie diet plan for muscle growth', 'bulk', 'daily', 3500, 200, 400, 100, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(3, 1, 'Bulking Meal Plan', 'Balanced high-protein diet for muscle building', 'bulk', 'weekly', 4000, 250, 450, 120, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(4, 1, 'Strength Nutrition', 'Protein-rich diet supporting strength training', 'bulk', 'daily', 3800, 220, 420, 110, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(5, 2, 'Fat Loss Diet', 'Calorie-controlled diet for weight loss', 'weight_loss', 'daily', 1800, 150, 150, 60, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(6, 2, 'Lean Body Plan', 'High-protein, low-carb diet for fat loss', 'weight_loss', 'weekly', 2000, 180, 180, 70, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(7, 2, 'Metabolic Diet', 'Metabolic-boosting meal plan', 'weight_loss', 'daily', 2200, 160, 200, 75, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(8, 3, 'Strength Nutrition', 'High-protein diet for strength training', 'strength', 'daily', 3000, 200, 300, 90, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(9, 3, 'Power Diet', 'Performance-focused meal plan', 'strength', 'weekly', 3200, 220, 320, 95, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(10, 3, 'Strength Builder', 'Balanced diet for strength development', 'strength', 'daily', 2800, 190, 280, 85, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08');

-- --------------------------------------------------------

--
-- Table structure for table `diet_plan_feedback`
--

CREATE TABLE `diet_plan_feedback` (
  `id` int(11) NOT NULL,
  `member_diet_plan_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diet_plan_meals`
--

CREATE TABLE `diet_plan_meals` (
  `id` int(11) NOT NULL,
  `diet_plan_id` int(11) NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `meal_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `calories` int(11) NOT NULL,
  `protein` int(11) NOT NULL,
  `carbs` int(11) NOT NULL,
  `fats` int(11) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `meal_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `diet_plan_meals`
--

INSERT INTO `diet_plan_meals` (`id`, `diet_plan_id`, `meal_type`, `meal_name`, `description`, `calories`, `protein`, `carbs`, `fats`, `image_path`, `meal_order`, `created_at`) VALUES
(2, 2, 'breakfast', 'Protein Pancakes', 'High-protein breakfast with oats and whey', 800, 40, 80, 20, 'diet_plans/meals/682890467bab9.png', 1, '2025-05-16 13:05:08'),
(3, 2, 'lunch', 'Chicken Rice Bowl', 'Chicken breast with brown rice and vegetables', 1000, 60, 100, 25, 'diet_plans/meals/68288eaf898b2.png', 2, '2025-05-16 13:05:08'),
(4, 2, 'dinner', 'Steak and Potatoes', 'Lean steak with sweet potatoes', 1200, 70, 100, 30, 'diet_plans/meals/6828908c36ff9.png', 3, '2025-05-16 13:05:08'),
(5, 2, 'breakfast', 'Egg and Avocado Toast', 'Whole grain toast with eggs and avocado', 750, 35, 70, 35, 'diet_plans/meals/6828911031bee.png', 4, '2025-05-16 13:05:08'),
(6, 2, 'lunch', 'Turkey and Cheese Sandwich', 'Whole grain bread with turkey and cheese', 900, 50, 80, 30, 'diet_plans/meals/6828917652e42.png', 5, '2025-05-16 13:05:08'),
(7, 2, 'dinner', 'Salmon with Quinoa', 'Salmon fillet with quinoa and vegetables', 1100, 65, 90, 40, 'diet_plans/meals/68289204dbf7e.png', 6, '2025-05-16 13:05:08'),
(8, 2, 'breakfast', 'Greek Yogurt Parfait', 'Greek yogurt with granola and berries', 700, 30, 60, 25, 'diet_plans/meals/682892649acfd.png', 7, '2025-05-16 13:05:08'),
(9, 2, 'lunch', 'Beef Stir Fry', 'Beef with rice and vegetables', 950, 55, 85, 30, 'diet_plans/meals/682892b415954.png', 8, '2025-05-16 13:05:08'),
(10, 2, 'dinner', 'Chicken Pasta', 'Chicken with whole grain pasta and sauce', 1050, 60, 95, 35, 'diet_plans/meals/68288eecbb9b2.png', 9, '2025-05-16 13:05:08'),
(11, 2, 'breakfast', 'Protein Smoothie Bowl', 'Smoothie bowl with protein powder and toppings', 800, 40, 75, 30, 'diet_plans/meals/68288ee1db166.png', 10, '2025-05-16 13:05:08'),
(12, 2, 'lunch', 'Tuna Salad Wrap', 'Whole grain wrap with tuna and vegetables', 850, 45, 70, 35, 'diet_plans/meals/6828930e2d930.png', 11, '2025-05-16 13:05:08'),
(13, 2, 'dinner', 'Pork Chops with Rice', 'Pork chops with brown rice and vegetables', 1150, 65, 95, 40, 'diet_plans/meals/68289353becf6.png', 12, '2025-05-16 13:05:08'),
(14, 2, 'breakfast', 'Oatmeal with Nuts', 'Oatmeal with nuts and honey', 750, 25, 85, 30, 'diet_plans/meals/682893cba77ca.png', 13, '2025-05-16 13:05:08'),
(15, 2, 'lunch', 'Chicken Caesar Salad', 'Grilled chicken with Caesar salad', 900, 50, 40, 45, 'diet_plans/meals/682893fe8031f.png', 14, '2025-05-16 13:05:08'),
(16, 2, 'dinner', 'Beef and Vegetable Stir Fry', 'Beef with vegetables and rice', 1100, 60, 90, 40, 'diet_plans/meals/68289441d7106.png', 15, '2025-05-16 13:05:08'),
(17, 2, 'breakfast', 'Breakfast Burrito', 'Eggs, cheese, and vegetables in a tortilla', 850, 40, 70, 40, 'diet_plans/meals/682894d0049be.png', 16, '2025-05-16 13:05:08'),
(18, 2, 'lunch', 'Grilled Chicken Sandwich', 'Whole grain bread with grilled chicken', 950, 55, 75, 35, 'diet_plans/meals/68288e892e992.png', 17, '2025-05-16 13:05:08'),
(19, 2, 'dinner', 'Salmon with Sweet Potatoes', 'Salmon with sweet potatoes and vegetables', 1150, 65, 95, 45, 'diet_plans/meals/6828956b20e82.png', 18, '2025-05-16 13:05:08'),
(20, 2, 'breakfast', 'Protein Waffles', 'Protein waffles with syrup and berries', 800, 35, 80, 30, 'diet_plans/meals/6828974a77652.png', 19, '2025-05-16 13:05:08'),
(21, 2, 'lunch', 'Turkey and Avocado Wrap', 'Whole grain wrap with turkey and avocado', 900, 45, 70, 40, 'diet_plans/meals/682897b717a2b.png', 20, '2025-05-16 13:05:08'),
(22, 2, 'dinner', 'Chicken and Rice Bowl', 'Chicken with rice and vegetables', 1050, 60, 90, 35, 'diet_plans/meals/6828984bcb822.png', 21, '2025-05-16 13:05:08'),
(23, 2, 'breakfast', 'Egg and Cheese Sandwich', 'Whole grain bread with eggs and cheese', 750, 35, 65, 35, 'diet_plans/meals/682898704a708.png', 22, '2025-05-16 13:05:08'),
(24, 2, 'lunch', 'Beef and Rice Bowl', 'Beef with rice and vegetables', 1000, 55, 85, 35, 'diet_plans/meals/68288ebf2e2c4.png', 23, '2025-05-16 13:05:08'),
(25, 2, 'dinner', 'Pork and Sweet Potatoes', 'Pork with sweet potatoes and vegetables', 1100, 60, 90, 40, 'diet_plans/meals/6828a0a8e09f5.png', 24, '2025-05-16 13:05:08'),
(26, 2, 'breakfast', 'Protein Pancakes with Berries', 'Protein pancakes with berries and syrup', 800, 40, 75, 30, 'diet_plans/meals/6828a0d5173f7.png', 25, '2025-05-16 13:05:08'),
(27, 2, 'lunch', 'Chicken and Avocado Salad', 'Grilled chicken with avocado and vegetables', 950, 50, 45, 45, 'diet_plans/meals/68288ecde38ac.png', 26, '2025-05-16 13:05:08'),
(28, 2, 'dinner', 'Steak with Quinoa', 'Steak with quinoa and vegetables', 1200, 70, 90, 45, 'diet_plans/meals/68288f1dda53c.png', 27, '2025-05-16 13:05:08'),
(29, 2, 'breakfast', 'Greek Yogurt with Granola', 'Greek yogurt with granola and honey', 700, 30, 60, 30, 'diet_plans/meals/68288f0dede82.png', 28, '2025-05-16 13:05:08'),
(30, 2, 'lunch', 'Tuna and Rice Bowl', 'Tuna with rice and vegetables', 900, 50, 75, 35, 'diet_plans/meals/68288ef9c1bef.png', 29, '2025-05-16 13:05:08'),
(31, 2, 'dinner', 'Chicken and Pasta', 'Chicken with whole grain pasta and sauce', 1050, 60, 85, 35, 'diet_plans/meals/6828a0f21edde.png', 30, '2025-05-16 13:05:08'),
(32, 2, 'breakfast', 'Protein Smoothie', 'Protein smoothie with fruits and nut butter', 750, 35, 70, 30, 'diet_plans/meals/6828a31942099.png', 31, '2025-05-16 13:05:08'),
(33, 2, 'lunch', 'Beef and Vegetable Wrap', 'Whole grain wrap with beef and vegetables', 950, 55, 70, 40, 'diet_plans/meals/6828a164d3172.png', 32, '2025-05-16 13:05:08'),
(34, 2, 'dinner', 'Salmon with Rice', 'Salmon with rice and vegetables', 1100, 65, 85, 40, 'diet_plans/meals/6828a17bcf1d9.png', 33, '2025-05-16 13:05:08'),
(35, 2, 'breakfast', 'Oatmeal with Protein', 'Oatmeal with protein powder and toppings', 800, 35, 80, 30, 'diet_plans/meals/6828a33b92b5c.png', 34, '2025-05-16 13:05:08'),
(36, 2, 'lunch', 'Chicken and Rice Bowl', 'Chicken with rice and vegetables', 1000, 55, 85, 35, 'diet_plans/meals/6828a1dff07d0.png', 35, '2025-05-16 13:05:08'),
(37, 2, 'dinner', 'Beef and Sweet Potatoes', 'Beef with sweet potatoes and vegetables', 1150, 65, 90, 45, 'diet_plans/meals/6828a1d336aab.png', 36, '2025-05-16 13:05:08'),
(38, 2, 'breakfast', 'Egg and Avocado Toast', 'Whole grain toast with eggs and avocado', 750, 35, 65, 35, 'diet_plans/meals/6828a1ba92cad.png', 37, '2025-05-16 13:05:08'),
(39, 2, 'lunch', 'Turkey and Cheese Sandwich', 'Whole grain bread with turkey and cheese', 900, 50, 75, 30, 'diet_plans/meals/6828a23fe42ad.png', 38, '2025-05-16 13:05:08'),
(40, 2, 'dinner', 'Pork with Quinoa', 'Pork with quinoa and vegetables', 1100, 60, 85, 40, 'diet_plans/meals/6828a22674bf4.png', 39, '2025-05-16 13:05:08'),
(41, 2, 'breakfast', 'Protein Pancakes', 'Protein pancakes with syrup and berries', 800, 40, 75, 30, 'diet_plans/meals/6828a24b20442.png', 40, '2025-05-16 13:05:08'),
(42, 2, 'lunch', 'Chicken Caesar Salad', 'Grilled chicken with Caesar salad', 950, 50, 40, 45, 'diet_plans/meals/6828a2664f4e7.png', 41, '2025-05-16 13:05:08'),
(43, 2, 'dinner', 'Steak and Potatoes', 'Steak with potatoes and vegetables', 1200, 70, 90, 45, 'diet_plans/meals/682c9375e4b5b.jpg', 42, '2025-05-16 13:05:08'),
(44, 5, 'breakfast', 'Greek Yogurt Bowl', 'Low-calorie high-protein breakfast', 400, 30, 30, 15, 'diet_plans/meals/682c9538890cc.jpg', 1, '2025-05-16 13:05:09'),
(45, 5, 'lunch', 'Grilled Chicken Salad', 'Lean protein with vegetables', 500, 40, 20, 20, 'diet_plans/meals/682c954f375fe.jpg', 2, '2025-05-16 13:05:09'),
(46, 5, 'dinner', 'Baked Fish', 'Lean fish with steamed vegetables', 600, 45, 25, 20, 'diet_plans/meals/682c95707f652.jpg', 3, '2025-05-16 13:05:09'),
(47, 5, 'breakfast', 'Egg White Omelette', 'Egg whites with vegetables', 350, 25, 15, 20, 'diet_plans/meals/682c958685176.jpg', 4, '2025-05-16 13:05:09'),
(48, 5, 'lunch', 'Turkey Lettuce Wraps', 'Turkey with lettuce and vegetables', 450, 35, 15, 25, 'diet_plans/meals/682c959964d15.jpg', 5, '2025-05-16 13:05:09'),
(49, 5, 'dinner', 'Chicken Stir Fry', 'Chicken with vegetables and minimal oil', 550, 40, 30, 20, 'diet_plans/meals/682c95b418d47.jpg', 6, '2025-05-16 13:05:09'),
(50, 5, 'breakfast', 'Protein Smoothie', 'Protein powder with fruits and almond milk', 300, 25, 25, 10, 'diet_plans/meals/682c95ce90da2.jpg', 7, '2025-05-16 13:05:09'),
(51, 5, 'lunch', 'Tuna Salad', 'Tuna with vegetables and light dressing', 400, 35, 15, 20, 'diet_plans/meals/682c95f59935c.jpg', 8, '2025-05-16 13:05:09'),
(52, 5, 'dinner', 'Lean Beef with Vegetables', 'Lean beef with steamed vegetables', 500, 40, 20, 25, 'diet_plans/meals/682c960dabd34.jpg', 9, '2025-05-16 13:05:09'),
(53, 5, 'breakfast', 'Cottage Cheese with Berries', 'Cottage cheese with fresh berries', 350, 30, 20, 15, 'diet_plans/meals/682c96189afca.jpg', 10, '2025-05-16 13:05:09'),
(54, 5, 'lunch', 'Grilled Chicken Wrap', 'Whole grain wrap with chicken and vegetables', 450, 35, 30, 20, 'diet_plans/meals/682c9632cc753.jpg', 11, '2025-05-16 13:05:09'),
(55, 5, 'dinner', 'Salmon with Asparagus', 'Salmon with asparagus and lemon', 550, 45, 15, 30, 'diet_plans/meals/682c9656c3410.jpg', 12, '2025-05-16 13:05:09'),
(56, 5, 'breakfast', 'Oatmeal with Protein', 'Oatmeal with protein powder and cinnamon', 350, 25, 35, 10, 'diet_plans/meals/682c96610c1ab.jpg', 13, '2025-05-16 13:05:09'),
(57, 5, 'lunch', 'Quinoa Bowl', 'Quinoa with vegetables and lean protein', 450, 30, 40, 15, 'diet_plans/meals/682c969fc9861.jpg', 14, '2025-05-16 13:05:09'),
(58, 5, 'dinner', 'Turkey Meatballs', 'Turkey meatballs with zucchini noodles', 500, 40, 25, 25, 'diet_plans/meals/682c96b858541.jpg', 15, '2025-05-16 13:05:09'),
(59, 5, 'breakfast', 'Avocado Toast', 'Whole grain toast with avocado and egg', 400, 20, 30, 25, 'diet_plans/meals/682c96c82dcf7.jpg', 16, '2025-05-16 13:05:09'),
(60, 5, 'lunch', 'Chicken and Vegetable Soup', 'Chicken soup with vegetables', 350, 30, 25, 15, 'diet_plans/meals/682c973aa627a.jpg', 17, '2025-05-16 13:05:09'),
(61, 5, 'dinner', 'Baked Chicken', 'Baked chicken with roasted vegetables', 550, 45, 20, 30, 'diet_plans/meals/682c9774a43a5.jpg', 18, '2025-05-16 13:05:09'),
(62, 5, 'breakfast', 'Protein Pancakes', 'Protein pancakes with sugar-free syrup', 350, 30, 25, 15, 'diet_plans/meals/682c96d420c61.jpg', 19, '2025-05-16 13:05:09'),
(63, 5, 'lunch', 'Tuna and Rice Bowl', 'Tuna with brown rice and vegetables', 450, 35, 35, 20, 'diet_plans/meals/682c970be1e2e.jpg', 20, '2025-05-16 13:05:09'),
(64, 5, 'dinner', 'Lean Pork with Vegetables', 'Lean pork with steamed vegetables', 500, 40, 20, 25, 'diet_plans/meals/682c97895ade7.jpg', 21, '2025-05-16 13:05:09'),
(65, 5, 'breakfast', 'Greek Yogurt with Nuts', 'Greek yogurt with nuts and honey', 400, 30, 25, 20, 'diet_plans/meals/682c971ddf97e.jpg', 22, '2025-05-16 13:05:09'),
(66, 5, 'lunch', 'Chicken Caesar Salad', 'Grilled chicken with Caesar salad', 450, 35, 15, 25, 'diet_plans/meals/682c979c2848e.jpg', 23, '2025-05-16 13:05:09'),
(67, 5, 'dinner', 'Fish with Quinoa', 'Fish with quinoa and vegetables', 550, 45, 30, 25, 'diet_plans/meals/682c97b3daba7.jpg', 24, '2025-05-16 13:05:09'),
(68, 5, 'breakfast', 'Egg and Vegetable Scramble', 'Eggs with vegetables', 350, 25, 15, 20, 'diet_plans/meals/682c97d31654c.jpg', 25, '2025-05-16 13:05:09'),
(69, 5, 'lunch', 'Turkey and Avocado Wrap', 'Whole grain wrap with turkey and avocado', 450, 35, 30, 25, 'diet_plans/meals/682c9883ac610.jpg', 26, '2025-05-16 13:05:09'),
(70, 5, 'dinner', 'Chicken and Vegetable Stir Fry', 'Chicken with vegetables and minimal oil', 500, 40, 25, 25, 'diet_plans/meals/682c98033e63d.jpg', 27, '2025-05-16 13:05:09'),
(71, 5, 'breakfast', 'Protein Smoothie Bowl', 'Smoothie bowl with protein powder and fruits', 350, 25, 30, 15, 'diet_plans/meals/682c986eaeb85.jpg', 28, '2025-05-16 13:05:09'),
(72, 5, 'lunch', 'Salmon Salad', 'Salmon with vegetables and light dressing', 450, 35, 15, 25, 'diet_plans/meals/682c98286b8c2.jpg', 29, '2025-05-16 13:05:09'),
(73, 5, 'dinner', 'Lean Beef with Sweet Potato', 'Lean beef with sweet potato and vegetables', 550, 40, 30, 25, 'diet_plans/meals/682c97e3b5fbc.jpg', 30, '2025-05-16 13:05:09'),
(74, 5, 'breakfast', 'Cottage Cheese with Fruit', 'Cottage cheese with fresh fruit', 350, 30, 20, 15, 'diet_plans/meals/682c98ace006c.jpg', 31, '2025-05-16 13:05:09'),
(75, 5, 'lunch', 'Grilled Chicken Salad', 'Grilled chicken with vegetables and light dressing', 450, 35, 20, 25, 'diet_plans/meals/682c98636829c.jpg', 32, '2025-05-16 13:05:09'),
(76, 5, 'dinner', 'Baked Fish with Vegetables', 'Baked fish with steamed vegetables', 500, 40, 20, 25, 'diet_plans/meals/682c981db29e4.jpg', 33, '2025-05-16 13:05:09'),
(77, 5, 'breakfast', 'Oatmeal with Berries', 'Oatmeal with fresh berries and cinnamon', 350, 20, 35, 15, 'diet_plans/meals/682c9891b33b1.jpg', 34, '2025-05-16 13:05:09'),
(78, 5, 'lunch', 'Quinoa with Chicken', 'Quinoa with chicken and vegetables', 450, 35, 35, 20, 'diet_plans/meals/682c98ceb3d30.jpg', 35, '2025-05-16 13:05:09'),
(79, 5, 'dinner', 'Turkey with Vegetables', 'Turkey with roasted vegetables', 500, 40, 25, 25, 'diet_plans/meals/682c98de82844.jpeg', 36, '2025-05-16 13:05:09'),
(80, 5, 'breakfast', 'Avocado and Egg Toast', 'Whole grain toast with avocado and egg', 400, 25, 30, 25, 'diet_plans/meals/682c98e8e07cc.jpg', 37, '2025-05-16 13:05:09'),
(81, 5, 'lunch', 'Chicken and Vegetable Soup', 'Chicken soup with vegetables', 350, 30, 25, 15, 'diet_plans/meals/682c98fe9bf94.jpg', 38, '2025-05-16 13:05:09'),
(82, 5, 'dinner', 'Baked Chicken with Vegetables', 'Baked chicken with roasted vegetables', 550, 45, 20, 30, 'diet_plans/meals/682c9915d5bdd.jpg', 39, '2025-05-16 13:05:09'),
(83, 5, 'breakfast', 'Protein Pancakes with Berries', 'Protein pancakes with fresh berries', 350, 30, 25, 15, 'diet_plans/meals/682c9921a9316.jpg', 40, '2025-05-16 13:05:09'),
(84, 5, 'lunch', 'Tuna and Rice Bowl', 'Tuna with brown rice and vegetables', 450, 35, 35, 20, 'diet_plans/meals/682c993682620.jpg', 41, '2025-05-16 13:05:09'),
(85, 5, 'dinner', 'Lean Pork with Vegetables', 'Lean pork with steamed vegetables', 500, 40, 20, 25, 'diet_plans/meals/682c99443e75b.jpg', 42, '2025-05-16 13:05:09'),
(86, 8, 'breakfast', 'Egg and Oatmeal Toast', 'Protein-rich breakfast with complex carbs', 700, 35, 70, 25, 'diet_plans/meals/682cac508a395.jpg', 1, '2025-05-16 13:05:09'),
(87, 8, 'lunch', 'Turkey Wrap', 'High-protein lunch with whole grain wrap', 800, 45, 60, 30, 'diet_plans/meals/682cac3b01b23.jpg', 2, '2025-05-16 13:05:09'),
(88, 8, 'dinner', 'Salmon and Quinoa', 'Omega-3 rich dinner with complex carbs', 900, 50, 70, 35, 'diet_plans/meals/682cac66d4cbc.jpg', 3, '2025-05-16 13:05:09'),
(89, 8, 'breakfast', 'Protein Pancakes', 'Protein pancakes with maple syrup', 750, 40, 65, 30, 'diet_plans/meals/682cac722e355.jpg', 4, '2025-05-16 13:05:09'),
(90, 8, 'lunch', 'Chicken Rice Bowl', 'Chicken with brown rice and vegetables', 850, 50, 70, 30, 'diet_plans/meals/682cac79e3ec7.jpg', 5, '2025-05-16 13:05:09'),
(91, 8, 'dinner', 'Steak and Sweet Potatoes', 'Steak with sweet potatoes and vegetables', 950, 55, 65, 40, 'diet_plans/meals/682cac828d22d.jpg', 6, '2025-05-16 13:05:09'),
(92, 8, 'breakfast', 'Greek Yogurt with Granola', 'Greek yogurt with granola and honey', 700, 35, 60, 30, 'diet_plans/meals/682cac8d53ea2.jpg', 7, '2025-05-16 13:05:09'),
(93, 8, 'lunch', 'Tuna Salad', 'Tuna with vegetables and olive oil', 800, 45, 40, 45, 'diet_plans/meals/682cac9869011.jpg', 8, '2025-05-16 13:05:09'),
(94, 8, 'dinner', 'Pork Chops with Rice', 'Pork chops with brown rice and vegetables', 900, 50, 65, 35, 'diet_plans/meals/682cacc6db05e.jpg', 9, '2025-05-16 13:05:09'),
(95, 8, 'breakfast', 'Breakfast Burrito', 'Eggs, cheese, and vegetables in a tortilla', 750, 40, 60, 35, 'diet_plans/meals/682caca776d5b.jpg', 10, '2025-05-16 13:05:09'),
(96, 8, 'lunch', 'Chicken Sandwich', 'Whole grain bread with chicken and avocado', 800, 45, 55, 35, 'diet_plans/meals/682cace38e1b2.jpg', 11, '2025-05-16 13:05:09'),
(97, 8, 'dinner', 'Salmon with Vegetables', 'Salmon with roasted vegetables', 900, 50, 40, 45, 'diet_plans/meals/682cacf27057a.jpg', 12, '2025-05-16 13:05:09'),
(98, 8, 'breakfast', 'Oatmeal with Protein', 'Oatmeal with protein powder and nuts', 700, 35, 65, 30, 'diet_plans/meals/682cacfcd7bc1.jpg', 13, '2025-05-16 13:05:09'),
(99, 8, 'lunch', 'Beef and Rice Bowl', 'Beef with brown rice and vegetables', 850, 50, 65, 35, 'diet_plans/meals/682cad17af52d.jpg', 14, '2025-05-16 13:05:09'),
(100, 8, 'dinner', 'Chicken Pasta', 'Chicken with whole grain pasta and sauce', 900, 50, 70, 35, 'diet_plans/meals/682cad286d173.jpg', 15, '2025-05-16 13:05:09'),
(101, 8, 'breakfast', 'Egg and Avocado Toast', 'Whole grain toast with eggs and avocado', 750, 35, 55, 40, 'diet_plans/meals/682cad3253466.jpg', 16, '2025-05-16 13:05:09'),
(102, 8, 'lunch', 'Turkey and Cheese Sandwich', 'Whole grain bread with turkey and cheese', 800, 45, 60, 35, 'diet_plans/meals/682cad7802e50.jpg', 17, '2025-05-16 13:05:09'),
(103, 8, 'dinner', 'Beef and Sweet Potatoes', 'Beef with sweet potatoes and vegetables', 950, 55, 65, 40, 'diet_plans/meals/682cad47d2c0e.jpg', 18, '2025-05-16 13:05:09'),
(104, 8, 'breakfast', 'Protein Waffles', 'Protein waffles with syrup and berries', 750, 40, 60, 35, 'diet_plans/meals/682cad548cbd3.jpg', 19, '2025-05-16 13:05:09'),
(105, 8, 'lunch', 'Chicken Caesar Salad', 'Grilled chicken with Caesar salad', 800, 45, 35, 45, 'diet_plans/meals/682cad93f398c.jpg', 20, '2025-05-16 13:05:09'),
(106, 8, 'dinner', 'Pork with Quinoa', 'Pork with quinoa and vegetables', 900, 50, 65, 35, 'diet_plans/meals/682cada7bd553.jpg', 21, '2025-05-16 13:05:09'),
(107, 8, 'breakfast', 'Greek Yogurt Parfait', 'Greek yogurt with granola and berries', 700, 35, 60, 30, 'diet_plans/meals/682cadb666002.jpg', 22, '2025-05-16 13:05:09'),
(108, 8, 'lunch', 'Tuna and Rice Bowl', 'Tuna with brown rice and vegetables', 800, 45, 60, 35, 'diet_plans/meals/682cadd4ddeb2.jpg', 23, '2025-05-16 13:05:09'),
(109, 8, 'dinner', 'Chicken and Pasta', 'Chicken with whole grain pasta and sauce', 900, 50, 70, 35, 'diet_plans/meals/682cade2ece9e.jpg', 24, '2025-05-16 13:05:09'),
(110, 8, 'breakfast', 'Protein Smoothie', 'Protein smoothie with fruits and nut butter', 750, 40, 60, 35, 'diet_plans/meals/682cadecbb6f1.jpg', 25, '2025-05-16 13:05:09'),
(111, 8, 'lunch', 'Beef and Vegetable Wrap', 'Whole grain wrap with beef and vegetables', 800, 45, 55, 40, 'diet_plans/meals/682cae1b150a6.jpg', 26, '2025-05-16 13:05:09'),
(112, 8, 'dinner', 'Salmon with Rice', 'Salmon with brown rice and vegetables', 900, 50, 65, 35, 'diet_plans/meals/682cae288ee83.jpg', 27, '2025-05-16 13:05:09'),
(113, 8, 'breakfast', 'Oatmeal with Nuts', 'Oatmeal with nuts and honey', 700, 30, 65, 35, 'diet_plans/meals/682cae3ee6f90.jpg', 28, '2025-05-16 13:05:09'),
(114, 8, 'lunch', 'Chicken and Rice Bowl', 'Chicken with brown rice and vegetables', 850, 50, 65, 35, 'diet_plans/meals/682cae4a8d207.jpg', 29, '2025-05-16 13:05:09'),
(115, 8, 'dinner', 'Steak with Quinoa', 'Steak with quinoa and vegetables', 950, 55, 60, 40, 'diet_plans/meals/682caeedba27b.jpg', 30, '2025-05-16 13:05:09'),
(116, 8, 'breakfast', 'Egg and Cheese Sandwich', 'Whole grain bread with eggs and cheese', 750, 40, 55, 40, 'diet_plans/meals/682cae5fc0279.jpg', 31, '2025-05-16 13:05:09'),
(117, 8, 'lunch', 'Turkey and Avocado Wrap', 'Whole grain wrap with turkey and avocado', 800, 45, 55, 40, 'diet_plans/meals/682caf00c3bfb.jpg', 32, '2025-05-16 13:05:09'),
(118, 8, 'dinner', 'Pork with Sweet Potatoes', 'Pork with sweet potatoes and vegetables', 900, 50, 65, 35, 'diet_plans/meals/682cae6d957d0.jpg', 33, '2025-05-16 13:05:09'),
(119, 8, 'breakfast', 'Protein Pancakes with Berries', 'Protein pancakes with berries and syrup', 750, 40, 60, 35, 'diet_plans/meals/682cae79ec96c.jpg', 34, '2025-05-16 13:05:09'),
(120, 8, 'lunch', 'Chicken and Avocado Salad', 'Grilled chicken with avocado and vegetables', 800, 45, 40, 45, 'diet_plans/meals/682caf219653f.jpg', 35, '2025-05-16 13:05:09'),
(121, 8, 'dinner', 'Beef and Potatoes', 'Beef with potatoes and vegetables', 950, 55, 65, 40, 'diet_plans/meals/682cae85b0717.jpg', 36, '2025-05-16 13:05:09'),
(122, 8, 'breakfast', 'Greek Yogurt with Granola', 'Greek yogurt with granola and honey', 700, 35, 60, 30, 'diet_plans/meals/682cae960a780.jpg', 37, '2025-05-16 13:05:09'),
(123, 8, 'lunch', 'Tuna and Rice Bowl', 'Tuna with brown rice and vegetables', 800, 45, 60, 35, 'diet_plans/meals/682caead510d5.jpeg', 38, '2025-05-16 13:05:09'),
(124, 8, 'dinner', 'Chicken and Pasta', 'Chicken with whole grain pasta and sauce', 900, 50, 70, 35, 'diet_plans/meals/682caea26e904.jpg', 39, '2025-05-16 13:05:09'),
(125, 8, 'breakfast', 'Protein Smoothie Bowl', 'Smoothie bowl with protein powder and fruits', 750, 40, 60, 35, 'diet_plans/meals/682caeb9f02b2.jpg', 40, '2025-05-16 13:05:09'),
(126, 8, 'lunch', 'Beef and Vegetable Wrap', 'Whole grain wrap with beef and vegetables', 800, 45, 55, 40, 'diet_plans/meals/682caf3bd96fc.jpg', 41, '2025-05-16 13:05:09'),
(127, 8, 'dinner', 'Salmon with Rice', 'Salmon with brown rice and vegetables', 900, 50, 65, 35, 'diet_plans/meals/682caec5927d5.jpg', 42, '2025-05-16 13:05:09'),
(128, 3, 'breakfast', 'Protein Pancakes', 'Oats, whey, banana, peanut butter', 1300, 80, 110, 30, 'diet_plans/meals/682c6c1b7dbdd.jpg', 1, '2025-05-20 11:48:43'),
(129, 3, 'lunch', 'Chicken Rice Bowl', 'Chicken breast, brown rice, veggies, olive oil', 1300, 85, 120, 30, 'diet_plans/meals/682c6ce4b8cf0.webp', 2, '2025-05-20 11:52:04'),
(130, 3, 'dinner', 'Grilled Salmon & Quinoa', 'Salmon, quinoa, asparagus', 1400, 85, 120, 60, 'diet_plans/meals/682c6d3418f7f.jpg', 3, '2025-05-20 11:53:24'),
(131, 3, 'breakfast', 'Egg & Avocado Toast', 'Whole eggs, whole grain toast, avocado', 1300, 80, 100, 40, 'diet_plans/meals/682c6e2831e24.jpg', 4, '2025-05-20 11:57:28'),
(132, 3, 'lunch', 'Turkey Sweet Potato Plate', 'Turkey breast, sweet potatoes, green beans', 1300, 90, 130, 25, 'diet_plans/meals/682c70ff7ef3c.jpg', 5, '2025-05-20 12:09:35'),
(133, 3, 'dinner', 'Tuna Pasta Bowl', 'Tuna, whole wheat pasta, olive oil, spinach', 1400, 80, 120, 55, 'diet_plans/meals/682c7171579cf.jpg', 6, '2025-05-20 12:11:29'),
(134, 3, 'breakfast', 'Greek Yogurt Bowl', 'Greek yogurt, honey, granola, nuts', 1300, 75, 100, 35, 'diet_plans/meals/682c71e389a76.jpg', 7, '2025-05-20 12:13:23'),
(135, 3, 'lunch', 'Beef Stir-Fry Bowl', 'Lean beef, brown rice, veggies, sesame oil', 1400, 90, 120, 40, 'diet_plans/meals/682c758f5f81b.jpg', 8, '2025-05-20 12:29:03'),
(136, 3, 'dinner', 'Chicken Quinoa Plate', 'Chicken, quinoa, broccoli, tahini drizzle', 1300, 85, 120, 45, 'diet_plans/meals/682c768645a8a.webp', 9, '2025-05-20 12:33:10'),
(137, 3, 'breakfast', 'Chia Protein Pudding', 'Chia, whey, oats, almond butter', 1300, 80, 100, 35, 'diet_plans/meals/682c76e590337.jpg', 10, '2025-05-20 12:34:45'),
(138, 3, 'lunch', 'Shrimp & Couscous', 'Shrimp, couscous, peas, lemon olive dressing', 1300, 85, 130, 25, 'diet_plans/meals/682c77617cd84.jpg', 11, '2025-05-20 12:36:49'),
(139, 3, 'dinner', 'Turkey Zucchini Lasagna', 'Turkey, zucchini, ricotta, tomato sauce', 1400, 85, 120, 60, 'diet_plans/meals/682c77e72e93a.jpg', 12, '2025-05-20 12:39:03'),
(140, 3, 'breakfast', 'Protein Oat Bowl', 'Oats, whey, almond milk, banana, flax', 1300, 80, 110, 30, 'diet_plans/meals/682c7999b8ed7.jpg', 13, '2025-05-20 12:46:17'),
(141, 3, 'lunch', 'Chicken Burrito Bowl', 'Chicken, rice, black beans, guac', 1400, 90, 140, 30, 'diet_plans/meals/682c7a77adb8d.jpg', 13, '2025-05-20 12:49:59'),
(142, 3, 'dinner', 'Baked Cod & Potatoes', 'Cod, baby potatoes, spinach, olive oil', 1300, 80, 100, 60, 'diet_plans/meals/682c7b15d821d.jpg', 15, '2025-05-20 12:52:37'),
(143, 3, 'breakfast', 'Breakfast Quesadilla', 'Eggs, cheese, whole grain tortilla', 1300, 75, 100, 40, 'diet_plans/meals/682c7bbc301b1.webp', 16, '2025-05-20 12:55:24'),
(144, 3, 'lunch', 'Grilled Tofu Bowl', 'Tofu, rice, edamame, tahini', 1300, 85, 130, 35, 'diet_plans/meals/682c7e886ff95.jpg', 17, '2025-05-20 13:07:20'),
(145, 3, 'dinner', 'Steak & Sweet Potatoes', 'Lean steak, SP, green beans', 1400, 90, 120, 45, 'diet_plans/meals/682c7ec64ba28.jpg', 18, '2025-05-20 13:08:22'),
(146, 3, 'breakfast', 'Cottage Cheese Bowl', 'Cottage cheese, nuts, banana, granola', 1300, 80, 110, 30, 'diet_plans/meals/682c7f32e4ed1.jpg', 19, '2025-05-20 13:10:10'),
(147, 3, 'lunch', 'Grilled Chicken Pasta', 'Chicken, penne, pesto, arugula', 1300, 90, 130, 25, 'diet_plans/meals/682c8c0723324.jpg', 20, '2025-05-20 13:11:58'),
(148, 3, 'dinner', 'Baked Salmon Bowl', 'Salmon, quinoa, avocado, kale', 1400, 80, 110, 65, 'diet_plans/meals/682c8c285a06e.jpg', 21, '2025-05-20 13:12:49'),
(149, 3, 'breakfast', 'Peanut Butter Oats', 'Oats, whey protein, banana, peanut butter', 1300, 80, 110, 35, 'diet_plans/meals/682c8c4db3012.jpg', 22, '2025-05-20 13:13:32'),
(150, 3, 'lunch', 'Tuna Rice Bowl', 'Canned tuna, white rice, frozen peas, oil', 1300, 85, 120, 25, 'diet_plans/meals/682c8c8016697.jpg', 23, '2025-05-20 13:14:11'),
(151, 3, 'dinner', 'Lentil Egg Stir-Fry', 'Eggs, lentils, frozen stir-fry veggies', 1400, 85, 120, 60, 'diet_plans/meals/682c8d81cc56b.jpg', 24, '2025-05-20 13:14:37'),
(152, 3, 'breakfast', 'Egg & Toast Combo', 'Eggs, whole grain bread, oats on side', 1300, 80, 100, 35, 'diet_plans/meals/682c8dba4009c.jpg', 25, '2025-05-20 13:15:22'),
(153, 3, 'lunch', 'Chicken Leg Rice Bowl', 'Chicken leg quarter, rice, frozen carrots', 1400, 90, 130, 35, 'diet_plans/meals/682c8de5ec532.jpg', 26, '2025-05-20 13:16:00'),
(154, 3, 'dinner', 'Pasta with Kidney Beans', 'Pasta, beans, onion, tomato sauce', 1300, 80, 120, 50, 'diet_plans/meals/682c8e2e3e9fb.jpg', 27, '2025-05-20 13:16:27'),
(155, 3, 'breakfast', 'Protein Pancakes', 'Oats, egg, whey, banana', 1300, 80, 100, 30, 'diet_plans/meals/682c8e544ca85.jpg', 28, '2025-05-20 13:17:24'),
(156, 3, 'lunch', 'Tuna Pasta', 'Canned tuna, whole wheat pasta, oil', 1400, 90, 130, 35, 'diet_plans/meals/682c8e759f91c.jpg', 29, '2025-05-20 13:18:07'),
(157, 3, 'dinner', 'Baked Eggs & Rice', 'Eggs, rice, spinach, beans', 1300, 80, 120, 55, 'diet_plans/meals/682c8e8ce2442.jpg', 30, '2025-05-20 13:18:49'),
(158, 3, 'breakfast', 'Yogurt Oats Mix', 'Yogurt, oats, peanut butter, whey', 1300, 80, 110, 25, 'diet_plans/meals/682c8ea676ab9.jpg', 31, '2025-05-20 13:19:22'),
(159, 3, 'lunch', 'Chicken & Lentils', 'Chicken breast, lentils, rice, frozen corn', 1300, 85, 120, 25, 'diet_plans/meals/682c8ebce061f.jpg', 32, '2025-05-20 13:19:53'),
(160, 3, 'dinner', 'Egg Veggie Rice Bowl', 'Chicken breast, lentils, rice, frozen corn', 1400, 85, 120, 60, 'diet_plans/meals/682c8edfcda0a.jpg', 33, '2025-05-20 13:20:16'),
(161, 3, 'breakfast', 'Oatmeal Protein Bowl', 'Oats, milk, whey, banana, peanut butter', 1300, 80, 110, 35, 'diet_plans/meals/682c8eff0af34.jpg', 34, '2025-05-20 13:21:06'),
(162, 3, 'lunch', 'Turkey Mince & Rice', 'Ground turkey, rice, canned green beans', 1300, 90, 120, 30, 'diet_plans/meals/682c8f3666178.jpeg', 35, '2025-05-20 13:21:37'),
(163, 3, 'dinner', 'Lentil Pasta', 'Pasta, lentils, olive oil, frozen spinach', 1400, 80, 120, 55, 'diet_plans/meals/682c8f4c87e3f.jpg', 36, '2025-05-20 13:22:08'),
(164, 3, 'breakfast', 'Egg Veggie Skillet', 'Eggs, frozen peppers, potatoes, oil', 1300, 75, 110, 35, 'diet_plans/meals/682c8f5ee620b.jpg', 37, '2025-05-20 13:23:21'),
(165, 3, 'lunch', 'Tuna Bean Salad', 'Tuna, canned beans, corn, olive oil', 1300, 90, 110, 35, 'diet_plans/meals/682c8f74beae5.jpg', 38, '2025-05-20 13:23:38'),
(166, 3, 'dinner', 'Chicken Pasta Bowl', 'Chicken, penne, mixed frozen vegetables', 1400, 85, 130, 50, 'diet_plans/meals/682c8f95998de.jpg', 39, '2025-05-20 13:24:07'),
(167, 3, 'breakfast', 'Cottage Cheese Bowl', 'Cottage cheese, oats, banana, peanut butter', 1300, 80, 110, 35, 'diet_plans/meals/682c8fae4b458.jpg', 40, '2025-05-20 13:24:44'),
(168, 3, 'lunch', 'Baked Chicken & Rice', 'Cottage cheese, oats, banana, peanut butter', 1300, 90, 120, 30, 'diet_plans/meals/682c8fc4ee3c7.jpg', 41, '2025-05-20 13:25:06'),
(169, 3, 'dinner', 'Veggie Omelet & Toast', 'Eggs, frozen veg, bread, beans', 1400, 80, 120, 55, 'diet_plans/meals/682c8fdfc3c84.jpg', 42, '2025-05-20 13:25:35'),
(170, 4, 'breakfast', 'Protein Oats', 'Oats, milk, whey, banana, peanut butter', 1250, 70, 120, 35, 'diet_plans/meals/682c9006d7871.jpg', 1, '2025-05-20 13:29:33'),
(171, 4, 'lunch', 'Chicken Rice Bowl', 'Grilled chicken, brown rice, broccoli, oil', 1300, 75, 140, 35, 'diet_plans/meals/682c902e94dae.jpg', 2, '2025-05-20 13:30:01'),
(172, 4, 'dinner', 'Beef & Potato Plate', 'Ground beef, sweet potato, spinach, cheese', 1300, 75, 160, 40, 'diet_plans/meals/682c90753a82c.jpg', 3, '2025-05-20 13:30:31'),
(173, 4, 'breakfast', 'Egg & Toast Stack', 'Eggs, whole wheat bread, avocado', 1200, 65, 110, 40, 'diet_plans/meals/682c90427bbdb.jpg', 4, '2025-05-20 13:30:59'),
(174, 4, 'lunch', 'Tuna Pasta Bowl', 'Canned tuna, whole wheat pasta, peas', 1400, 85, 140, 30, 'diet_plans/meals/682c904c66a4b.jpg', 5, '2025-05-20 13:31:24'),
(175, 4, 'dinner', 'Turkey & Rice Mix', 'Ground turkey, white rice, olive oil, greens', 1200, 70, 170, 40, 'diet_plans/meals/682c9062f2f38.jpg', 6, '2025-05-20 13:31:50'),
(176, 4, 'breakfast', 'Protein Pancakes', 'Oats, egg, whey, berries, peanut butter', 1300, 75, 130, 35, 'diet_plans/meals/682c907f3f794.jpg', 7, '2025-05-20 13:32:20'),
(177, 4, 'lunch', 'Chicken Burrito Bowl', 'Rice, grilled chicken, beans, salsa', 1300, 80, 150, 30, 'diet_plans/meals/682c908ac4c7a.jpg', 8, '2025-05-20 13:32:45'),
(178, 4, 'dinner', 'Salmon & Quinoa', 'Baked salmon, quinoa, zucchini', 1200, 65, 140, 45, 'diet_plans/meals/682c909ec7af4.jpg', 9, '2025-05-20 13:33:09'),
(179, 4, 'breakfast', 'Greek Yogurt Bowl', 'Yogurt, oats, whey, nuts, honey', 1300, 70, 110, 35, 'diet_plans/meals/682c90a9d4341.jpg', 10, '2025-05-20 13:33:36'),
(180, 4, 'lunch', 'Beef Stir Fry', 'Rice, beef strips, mixed vegetables', 1300, 80, 150, 35, 'diet_plans/meals/682c9112066aa.jpg', 11, '2025-05-20 13:33:53'),
(181, 4, 'dinner', 'Chicken Alfredo', 'Pasta, chicken breast, cream, spinach', 1200, 70, 160, 40, 'diet_plans/meals/682c90fa1a562.jpg', 12, '2025-05-20 13:34:28'),
(182, 4, 'breakfast', 'Cottage Cheese Oats', 'Oats, cottage cheese, whey, banana', 1250, 70, 110, 35, 'diet_plans/meals/682c9121b95a1.jpg', 13, '2025-05-20 13:35:40'),
(183, 4, 'lunch', 'Lentil Chicken Rice', 'Chicken, rice, lentils, olive oil', 1300, 80, 140, 35, 'diet_plans/meals/682c915a885e2.jpg', 14, '2025-05-20 13:36:06'),
(184, 4, 'dinner', 'Steak & Sweet Potato', 'Lean steak, sweet potato, greens', 1250, 70, 170, 40, 'diet_plans/meals/682c916777fb1.jpg', 15, '2025-05-20 13:36:34'),
(185, 4, 'breakfast', 'Peanut Butter Oats', 'Oats, whey, PB, banana', 1300, 75, 130, 35, 'diet_plans/meals/682c91960b097.jpg', 16, '2025-05-20 13:37:23'),
(186, 4, 'lunch', 'Tuna & Bean Bowl', 'Tuna, kidney beans, rice, olive oil', 1300, 80, 140, 35, 'diet_plans/meals/682c91bef0a4e.jpg', 17, '2025-05-20 13:37:47'),
(187, 4, 'dinner', 'Chicken Pasta Bake', 'Pasta, grilled chicken, marinara', 1200, 65, 150, 40, 'diet_plans/meals/682c91f501075.jpg', 18, '2025-05-20 13:38:21'),
(188, 4, 'breakfast', 'Scrambled Eggs & Toast', 'Eggs, bread, oats', 1300, 70, 110, 35, 'diet_plans/meals/682c91fe8a526.jpg', 19, '2025-05-20 13:38:58'),
(189, 4, 'lunch', 'Turkey Veggie Bowl', 'Ground turkey, rice, frozen mix veg', 1300, 80, 140, 35, 'diet_plans/meals/682c920a6fc9b.jpg', 20, '2025-05-20 13:39:26'),
(190, 4, 'dinner', 'Pasta & Cheese', 'Pasta, beef, parmesan, spinach', 1200, 70, 170, 40, 'diet_plans/meals/682c921f8c17b.jpg', 21, '2025-05-20 13:39:58'),
(191, 4, 'breakfast', 'French Toast & Eggs', 'Whole wheat French toast, eggs, whey shake', 1300, 70, 120, 35, 'diet_plans/meals/682c9233c112c.jpg', 22, '2025-05-20 13:40:34'),
(192, 4, 'lunch', 'Chicken Couscous Bowl', 'Chicken breast, couscous, grilled veggies', 1300, 80, 150, 35, 'diet_plans/meals/682c924be6c61.jpg', 23, '2025-05-20 13:40:57'),
(193, 4, 'dinner', 'Shrimp & Rice Plate', 'Shrimp, white rice, coconut oil, spinach', 1200, 70, 150, 40, 'diet_plans/meals/682c92571f4ed.jpg', 24, '2025-05-20 13:41:28'),
(194, 4, 'breakfast', 'Protein Waffles', 'Oats, whey, eggs, peanut butter', 1250, 70, 120, 35, 'diet_plans/meals/682c92702b6fa.jpg', 25, '2025-05-20 13:41:59'),
(195, 4, 'lunch', 'Beef Chili Bowl', 'Lean beef, beans, tomato, corn, olive oil', 1350, 85, 140, 35, 'diet_plans/meals/682c92880a5ea.jpg', 26, '2025-05-20 13:42:37'),
(196, 4, 'dinner', 'Turkey Quinoa Plate', 'Turkey breast, quinoa, avocado', 1200, 65, 160, 40, 'diet_plans/meals/682c9297b785c.jpg', 27, '2025-05-20 13:43:04'),
(197, 4, 'breakfast', 'Breakfast Burrito', 'Eggs, cheese, beans, tortilla, whey shake', 1300, 75, 130, 35, 'diet_plans/meals/682c92a0bdd8c.jpg', 28, '2025-05-20 13:43:40'),
(198, 4, 'lunch', 'Chicken Pasta Bowl', 'Chicken breast, pasta, tomato sauce', 1300, 80, 150, 35, 'diet_plans/meals/682c92dfd63a0.jpg', 29, '2025-05-20 13:44:06'),
(199, 4, 'dinner', 'Fish & Rice Plate', 'White fish, rice, olive oil, steamed veg', 1200, 65, 140, 40, 'diet_plans/meals/682c92fee2ec4.jpg', 30, '2025-05-20 13:44:35'),
(200, 4, 'breakfast', 'Yogurt & Granola Bowl', 'Greek yogurt, granola, whey, fruit', 1300, 70, 130, 35, 'diet_plans/meals/682c9312a77d0.jpg', 31, '2025-05-20 13:45:12'),
(201, 4, 'lunch', 'Turkey Burrito Bowl', 'Ground turkey, beans, rice, avocado', 1300, 80, 140, 35, 'diet_plans/meals/682c932ab9bc3.jpg', 32, '2025-05-20 13:46:08'),
(202, 4, 'dinner', 'Pasta & Meat Sauce', 'Pasta, lean beef, marinara', 1200, 70, 150, 40, 'diet_plans/meals/682c9347e73d6.jpg', 33, '2025-05-20 13:46:39'),
(203, 6, 'breakfast', 'Egg Whites & Oats', 'Egg whites, oats, whey', 650, 60, 50, 15, 'diet_plans/meals/682c9d921457a.jpg', 1, '2025-05-20 15:04:27'),
(204, 6, 'lunch', 'Chicken Salad Bowl', 'Grilled chicken, quinoa, greens, olive oil', 700, 60, 60, 25, 'diet_plans/meals/682c9da0267c2.jpg', 2, '2025-05-20 15:04:50'),
(205, 6, 'dinner', 'Turkey & Veg Stir-Fry', 'Lean turkey, broccoli, bell peppers', 650, 55, 60, 30, 'diet_plans/meals/682c9dd01c802.jpg', 3, '2025-05-20 15:05:18'),
(206, 6, 'breakfast', 'Protein Pancakes', 'Oats, egg whites, banana, whey', 650, 60, 50, 25, 'diet_plans/meals/682c9ddfa727c.jpg', 4, '2025-05-20 15:06:19'),
(207, 6, 'lunch', 'Tuna Bowl', 'Tuna, sweet potato, green beans, olive oil', 700, 65, 50, 25, 'diet_plans/meals/682c9def7ac46.jpg', 5, '2025-05-20 15:06:44'),
(208, 6, 'dinner', 'Chicken Rice Plate', 'Chicken breast, brown rice, broccoli', 650, 65, 50, 25, 'diet_plans/meals/682c9e1b56829.jpeg', 6, '2025-05-20 15:07:04'),
(209, 6, 'breakfast', 'Greek Yogurt Mix Greek Yogurt Mix', 'Greek yogurt, berries, flaxseed, whey', 650, 65, 50, 15, 'diet_plans/meals/682c9e2995742.jpg', 7, '2025-05-20 15:07:38'),
(210, 6, 'lunch', 'Ground Turkey Bowl', 'Turkey, quinoa, spinach, avocado', 700, 65, 50, 15, 'diet_plans/meals/682c9eb5c70ab.jpg', 8, '2025-05-20 15:07:58'),
(211, 6, 'dinner', 'Salmon & Veggies', 'Grilled salmon, asparagus, mashed cauliflower', 650, 65, 70, 30, 'diet_plans/meals/682c9e341d1e6.jpg', 9, '2025-05-20 15:08:20'),
(212, 6, 'breakfast', 'Scrambled Eggs & Toast', 'Whole eggs, egg whites, whole grain bread', 650, 65, 70, 30, 'diet_plans/meals/682c9e448dc5d.jpg', 10, '2025-05-20 15:08:42'),
(213, 6, 'lunch', 'Chicken Wrap', 'Chicken, whole wheat tortilla, veggies', 700, 65, 70, 25, 'diet_plans/meals/682c9e5f90cf7.jpg', 11, '2025-05-20 15:09:03'),
(214, 6, 'dinner', 'Cod & Couscous', 'Grilled cod, couscous, steamed greens', 650, 55, 70, 25, 'diet_plans/meals/682c9e8bac88a.jpg', 12, '2025-05-20 15:09:40'),
(215, 6, 'breakfast', 'Cottage Cheese Bowl', 'Low-fat cottage cheese, berries, almonds', 650, 55, 50, 15, 'diet_plans/meals/682c9ec42fc58.jpg', 13, '2025-05-20 15:10:17'),
(216, 6, 'lunch', 'Chicken Lentil Plate', 'Grilled chicken, lentils, olive oil', 700, 55, 50, 15, 'diet_plans/meals/682c9ef1a9cfc.jpg', 14, '2025-05-20 15:10:33'),
(217, 6, 'dinner', 'Beef Stir-Fry', 'Lean beef, veggies, brown rice', 650, 55, 50, 30, 'diet_plans/meals/682c9ecf61030.jpg', 15, '2025-05-20 15:10:56'),
(218, 7, 'breakfast', 'Veggie Omelet and Oats', 'Egg whites, 1 yolk, oats, almond milk, berries', 700, 45, 60, 20, 'diet_plans/meals/682c9f24c3b99.jpg', 1, '2025-05-20 15:12:07'),
(219, 7, 'lunch', 'Chicken Rice Bowl', 'Grilled chicken breast, brown rice, olive oil', 800, 60, 80, 25, 'diet_plans/meals/682c9f6208cce.jpg', 2, '2025-05-20 15:12:36'),
(220, 7, 'dinner', 'Beef & Sweet Potato', 'Grilled chicken breast, brown rice, olive oil  Lean ground beef, sweet potatoes, green beans', 700, 60, 80, 25, 'diet_plans/meals/682c9f6e9dc77.jpg', 3, '2025-05-20 15:12:51'),
(221, 7, 'breakfast', 'Protein Pancakes', 'Oats, egg whites, banana, whey protein', 700, 45, 60, 20, 'diet_plans/meals/682c9f78f05c1.jpg', 4, '2025-05-20 15:13:31'),
(222, 7, 'lunch', 'Turkey Wrap & Salad', 'Turkey breast, whole grain wrap, avocado, greens', 800, 60, 60, 25, 'diet_plans/meals/682c9f90378c8.jpg', 5, '2025-05-20 15:14:01'),
(223, 7, 'dinner', 'Baked Salmon Plate', 'Salmon, couscous, steamed broccoli', 700, 60, 60, 25, 'diet_plans/meals/682c9f9989d58.jpg', 6, '2025-05-20 15:14:18'),
(224, 7, 'breakfast', 'Greek Yogurt & Granola', 'Non-fat yogurt, granola, berries, scoop of whey', 700, 60, 60, 20, 'diet_plans/meals/682c9fab7e898.jpg', 7, '2025-05-20 15:14:48'),
(225, 7, 'lunch', 'Chicken Burrito Bowl', 'Chicken breast, brown rice, beans, avocado', 800, 60, 80, 20, 'diet_plans/meals/682c9fb56eaa2.jpg', 8, '2025-05-20 15:15:09'),
(226, 7, 'dinner', 'Tilapia & Quinoa', 'Grilled tilapia, quinoa, olive oil, vegetables', 700, 55, 60, 20, 'diet_plans/meals/682ca007d979b.jpg', 9, '2025-05-20 15:15:34'),
(227, 7, 'breakfast', 'Scrambled Eggs & Toast', 'Eggs, whole grain toast, fruit', 700, 55, 60, 20, 'diet_plans/meals/682c9fbf5787d.jpg', 10, '2025-05-20 15:15:48'),
(228, 7, 'lunch', 'Tuna Pasta', 'Tuna, whole grain pasta, olive oil, cucumbers', 800, 60, 80, 25, 'diet_plans/meals/682c9fd3a65dc.jpg', 11, '2025-05-20 15:17:12'),
(229, 7, 'dinner', 'Grilled Chicken Plate', 'Chicken breast, mashed potatoes, peas', 700, 60, 80, 25, 'diet_plans/meals/682ca0246b928.jpg', 12, '2025-05-20 15:17:36'),
(230, 7, 'breakfast', 'Cottage Cheese & Fruit', 'Cottage cheese, pineapple, scoop of whey, almonds 45g', 700, 45, 60, 25, 'diet_plans/meals/682c9fe220ec0.jpg', 13, '2025-05-20 15:18:03'),
(231, 7, 'lunch', 'Chicken Caesar Wrap', 'Chicken breast, lettuce, wrap, light Caesar dressing', 800, 45, 60, 25, 'diet_plans/meals/682ca03b9f493.jpg', 14, '2025-05-20 15:18:28'),
(232, 7, 'dinner', 'Ground Turkey Stir-Fry', 'Ground turkey, rice, stir-fried veggies', 700, 45, 60, 30, 'diet_plans/meals/682ca0559c6ee.jpg', 15, '2025-05-20 15:18:47'),
(233, 9, 'breakfast', 'Scrambled Eggs & Oats', '6 egg whites + 2 whole eggs, 1.5 cups oats, berries, whey', 1050, 65, 100, 30, 'diet_plans/meals/682ca3fd96ea0.jpg', 1, '2025-05-20 15:37:02'),
(234, 9, 'lunch', 'Grilled Chicken Plate with veggies', 'Grilled chicken breast, white rice, avocado, broccoli', 1100, 80, 100, 30, 'diet_plans/meals/682ca408773dd.jpg', 2, '2025-05-20 15:37:49'),
(235, 9, 'dinner', 'Beef with sweet potatoes', 'Lean beef, sweet potatoes, green beans', 1050, 75, 100, 35, 'diet_plans/meals/682ca416d7bfa.jpg', 3, '2025-05-20 15:38:23'),
(236, 9, 'breakfast', 'Protein Pancakes', 'Protein pancakes (oats, eggs, banana, whey), peanut butter', 1050, 75, 100, 30, 'diet_plans/meals/682ca4202e83c.jpg', 4, '2025-05-20 15:38:47'),
(237, 9, 'lunch', 'Turkey Quinoa Plate', 'Turkey breast, quinoa, olive oil, spinach', 1100, 75, 100, 35, 'diet_plans/meals/682ca46c4cc2d.jpg', 5, '2025-05-20 15:39:22'),
(238, 9, 'dinner', 'Salmon Salad', 'Salmon, jasmine rice, zucchini', 1050, 75, 110, 30, 'diet_plans/meals/682ca4748cd63.jpg', 6, '2025-05-20 15:39:57'),
(239, 9, 'breakfast', 'Greek Yogurt Bowl', 'Greek yogurt (low-fat), granola, whey, almonds', 1050, 65, 110, 30, 'diet_plans/meals/682ca47d07bd9.jpg', 7, '2025-05-20 15:40:27'),
(240, 9, 'lunch', 'Lean Pork with Vegetables', 'Grilled steak, potatoes, green salad with olive oil', 1100, 80, 110, 35, 'diet_plans/meals/682ca48b54eec.jpg', 8, '2025-05-20 15:42:32'),
(241, 9, 'dinner', 'Chicken Rice Bowl', 'Chicken thigh, couscous, asparagus', 1050, 75, 110, 30, 'diet_plans/meals/682ca49378617.jpg', 9, '2025-05-20 15:43:02'),
(242, 9, 'breakfast', 'Scrambled Eggs & Oats', 'Scrambled eggs, toast, whey shake', 1050, 65, 110, 35, 'diet_plans/meals/682ca4a1ef213.jpg', 10, '2025-05-20 15:43:26'),
(243, 9, 'lunch', 'Tuna Pasta Bowl', 'Tuna pasta salad (whole wheat pasta, olive oil, veggies', 1050, 65, 110, 35, 'diet_plans/meals/682ca4ab868be.jpg', 11, '2025-05-20 15:43:59'),
(244, 9, 'dinner', 'Lean Pork with Vegetables', 'Pork loin, rice, steamed peas', 1100, 65, 110, 35, 'diet_plans/meals/682ca4c840447.jpg', 12, '2025-05-20 15:44:29'),
(245, 9, 'breakfast', 'French Toast & Eggs', 'Protein French toast (egg whites, whole wheat bread, whey)', 1050, 65, 100, 30, 'diet_plans/meals/682ca4d4d8fb7.jpg', 13, '2025-05-20 15:45:58'),
(246, 9, 'lunch', 'Grilled Chicken Salad', 'Grilled chicken pita, hummus, cucumber salad', 1100, 65, 100, 35, 'diet_plans/meals/682ca4e557f9c.jpg', 14, '2025-05-20 15:46:21'),
(247, 9, 'dinner', 'Tilapia & Quinoa', 'Tilapia, mashed potatoes, broccoli', 1050, 65, 100, 35, 'diet_plans/meals/682ca50463d31.jpg', 15, '2025-05-20 15:46:43'),
(248, 10, 'breakfast', 'Oats & Eggs Power Bowl', '4 egg whites + 2 whole eggs, 1 cup oats, berries, 1 scoop whey', 950, 60, 85, 28, 'diet_plans/meals/682cab04590b7.jpg', 1, '2025-05-20 16:10:07'),
(249, 10, 'lunch', 'Grilled Chicken Rice Plate', 'Grilled chicken breast, white rice, broccoli, olive oil', 950, 60, 85, 28, 'diet_plans/meals/682cab1b5f3e3.jpg', 2, '2025-05-20 16:10:28'),
(250, 10, 'dinner', 'Beef & Sweet Potato Strength Meal', 'Lean beef, sweet potato, steamed spinach', 900, 60, 85, 28, 'diet_plans/meals/682cab278fd5a.jpg', 3, '2025-05-20 16:10:45'),
(251, 10, 'breakfast', 'Protein Pancakes with PB', 'Protein pancakes (oats, banana, eggs, whey), peanut butter', 950, 60, 90, 28, 'diet_plans/meals/682cab334a27a.jpg', 4, '2025-05-20 16:11:33'),
(252, 10, 'lunch', 'Protein Pancakes with PB  Herbed Turkey & Quinoa Medley', 'Turkey breast, quinoa, grilled vegetables', 950, 60, 90, 28, 'diet_plans/meals/682cab5997366.jpg', 5, '2025-05-20 16:11:53'),
(253, 10, 'dinner', 'Salmon & Jasmine Rice Plate', 'Baked salmon, jasmine rice, green beans', 900, 60, 90, 28, 'diet_plans/meals/682cab639529c.jpg', 6, '2025-05-20 16:12:15'),
(254, 10, 'breakfast', 'Berry Yogurt Protein Parfait', 'Greek yogurt (non-fat), granola, berries, whey', 900, 60, 90, 28, 'diet_plans/meals/682cab733374d.jpg', 7, '2025-05-20 16:12:40'),
(255, 10, 'lunch', 'Steak & Potato Green Bowl', 'Grilled steak, potatoes, green salad with olive oil', 950, 60, 90, 28, 'diet_plans/meals/682cab7d02131.jpg', 8, '2025-05-20 16:13:01'),
(256, 10, 'dinner', 'Chicken & Carrot Rice Plate', 'Chicken thigh, brown rice, steamed carrots', 950, 60, 90, 28, 'diet_plans/meals/682caba296b97.jpg', 9, '2025-05-20 16:14:49'),
(257, 10, 'breakfast', 'Scrambled Eggs & Toast Combo', 'Scrambled eggs, whole wheat toast, whey shake', 950, 60, 90, 28, 'diet_plans/meals/682cababdd15e.jpg', 10, '2025-05-20 16:15:15'),
(258, 10, 'lunch', 'Tuna Pasta Power Salad', 'Tuna pasta salad (whole grain pasta, veggies, olive oil)', 950, 60, 90, 28, 'diet_plans/meals/682cabba1aa72.jpg', 11, '2025-05-20 16:15:29'),
(259, 10, 'dinner', 'Classic Chicken Couscous Plate', 'Chicken breast, couscous, broccoli', 900, 60, 90, 28, 'diet_plans/meals/682cabed48fcc.jpg', 12, '2025-05-20 16:16:00'),
(260, 10, 'breakfast', 'Protein French Toast Stack', 'Protein French toast (egg whites, whole wheat bread, whey)', 950, 60, 90, 28, 'diet_plans/meals/682cabd098e6c.jpg', 13, '2025-05-20 16:16:19'),
(261, 10, 'lunch', 'Mediterranean Chicken Pita Wrap', 'Chicken pita wrap, hummus, cucumber salad', 950, 60, 90, 28, 'diet_plans/meals/682cabff7f4ac.jpg', 14, '2025-05-20 16:16:34'),
(262, 10, 'dinner', 'Tilapia & Spinach Mash Plate', 'Tilapia, mashed potatoes, spinach', 900, 60, 90, 28, 'diet_plans/meals/682cac0d71d72.jpg', 15, '2025-05-20 16:16:52');

-- --------------------------------------------------------

--
-- Table structure for table `exercises`
--

CREATE TABLE `exercises` (
  `id` int(11) NOT NULL,
  `workout_plan_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sets` int(11) NOT NULL,
  `reps` int(11) NOT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `calories_burn` int(11) NOT NULL,
  `gif_path` varchar(255) DEFAULT NULL,
  `exercise_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exercises`
--

INSERT INTO `exercises` (`id`, `workout_plan_id`, `name`, `description`, `sets`, `reps`, `duration_minutes`, `calories_burn`, `gif_path`, `exercise_order`, `created_at`) VALUES
(2, 2, 'Barbell Squat', 'Compound leg exercise for overall strength', 4, 8, 4, 150, 'uploads/exercises/6827470d77293_BARBELL-SQUAT.gif', 1, '2025-05-16 13:05:08'),
(3, 2, 'Bench Press', 'Upper body pushing movement', 4, 8, 2, 120, 'uploads/exercises/68273b53d58a4_bench_press.gif', 2, '2025-05-16 13:05:08'),
(4, 2, 'Deadlift', 'Full body pulling movement', 4, 6, 5, 180, 'uploads/exercises/68273bd3c4949_deadlifts.gif', 3, '2025-05-16 13:05:08'),
(5, 2, 'Overhead Press', 'Shoulder pressing movement', 4, 8, 4, 100, 'uploads/exercises/682747405698e_Barbell-Standing-Military-Press.gif', 4, '2025-05-16 13:05:08'),
(6, 2, 'Barbell Rows', 'Back thickness builder', 4, 10, 5, 120, 'uploads/exercises/6827475d3df4e_barbell_rows.gif', 5, '2025-05-16 13:05:08'),
(7, 2, 'Romanian Deadlift', 'Hamstring and lower back focus', 3, 10, 5, 130, 'uploads/exercises/68273c023f26c_romanian_deadlifts.gif', 6, '2025-05-16 13:05:08'),
(8, 2, 'Incline Bench Press', 'Upper chest development', 4, 8, 5, 110, 'uploads/exercises/68273c1fe4669_incline_bench_press.gif', 7, '2025-05-16 13:05:08'),
(9, 2, 'Pull-ups', 'Back width builder', 4, 8, 5, 90, 'uploads/exercises/6827476f3337e_Pull_ups.gif', 8, '2025-05-16 13:05:08'),
(10, 2, 'Dumbbell Shoulder Press', 'Shoulder strength and size', 3, 10, 5, 95, 'uploads/exercises/682747acaba6e_Dumbbell-Shoulder-Press.gif', 9, '2025-05-16 13:05:08'),
(11, 2, 'Leg Press', 'Quad and glute development', 4, 12, 5, 160, 'uploads/exercises/682748acd929f_Leg-Press.gif', 10, '2025-05-16 13:05:08'),
(12, 2, 'Seated Zottman Curl', 'Arm development', 3, 12, 5, 70, 'uploads/exercises/682748cf2c677_Seated-Zottman-Curl.gif', 11, '2025-05-16 13:05:08'),
(13, 2, 'One Arm Triceps Pushdown', 'Arm development', 3, 12, 5, 70, 'uploads/exercises/68274908e9082_One-arm-triceps-pushdown.gif', 12, '2025-05-16 13:05:08'),
(14, 2, 'Calf Raises', 'Lower leg development', 4, 15, 5, 60, 'uploads/exercises/682747ee2717b_calf_raises.gif', 13, '2025-05-16 13:05:08'),
(15, 2, 'Lateral Raises', 'Shoulder width development', 3, 12, 4, 65, 'uploads/exercises/6827492cd965f_lateral_raises.gif', 14, '2025-05-16 13:05:08'),
(17, 5, 'HIIT Circuit', 'High-intensity interval training', 3, 12, 30, 300, NULL, 1, '2025-05-16 13:05:08'),
(18, 5, 'Cross Body Mountain Climber', 'Cardio exercise', 3, 20, 15, 100, 'uploads/exercises/68274c77bc0ad_Cross-Body-Mountain-Climber.gif', 2, '2025-05-16 13:05:08'),
(19, 5, 'Burpees', 'Full body cardio exercise', 3, 15, 20, 150, 'uploads/exercises/68274ca6dfd41_burpees.gif', 3, '2025-05-16 13:05:08'),
(20, 5, 'Jump Rope', 'Cardio conditioning', 3, 1, 10, 120, 'uploads/exercises/6831148a401ef_Jump-Rope.gif', 4, '2025-05-16 13:05:08'),
(21, 5, 'Box Jumps', 'Explosive leg movement', 3, 10, 15, 130, 'uploads/exercises/683116e4e30a9_Box-Jump-1-to-2.gif', 5, '2025-05-16 13:05:08'),
(22, 5, 'Kettlebell Swings', 'Hip hinge movement', 3, 15, 15, 140, 'uploads/exercises/6830e47296202_Kettlebell-Swings.gif', 6, '2025-05-16 13:05:08'),
(23, 5, 'Battle Ropes', 'Upper body cardio', 3, 1, 10, 150, 'uploads/exercises/6830e48832985_Battle-Rope.gif', 7, '2025-05-16 13:05:08'),
(24, 5, 'Sprint Intervals', 'High-intensity running', 5, 1, 20, 200, 'uploads/exercises/6830e4a04da5b_sprint interval.gif', 8, '2025-05-16 13:05:08'),
(25, 5, 'Jumping Jacks', 'Full body cardio', 3, 30, 10, 90, 'uploads/exercises/68311419d5e43_Jumping-jack.gif', 9, '2025-05-16 13:05:08'),
(26, 5, 'High Knees', 'Running in place', 3, 1, 10, 100, 'uploads/exercises/6831144eae0b9_high_knees.gif', 10, '2025-05-16 13:05:08'),
(27, 5, 'Plank to Push-up', 'Core and upper body', 3, 10, 15, 110, 'uploads/exercises/6830e4f694a88_Push-ups.gif', 11, '2025-05-16 13:05:08'),
(28, 5, 'Bear Crawls', 'Full body movement', 3, 1, 10, 120, 'uploads/exercises/6830e502f2637_Bear Crawls.gif', 12, '2025-05-16 13:05:08'),
(29, 5, 'Squat Jumps', 'Explosive leg movement', 3, 15, 15, 130, 'uploads/exercises/6830e50fabe25_Squat Jumps.gif', 13, '2025-05-16 13:05:08'),
(30, 5, 'Curtsy Lunge', 'Leg and core movement', 3, 12, 15, 110, 'uploads/exercises/68311727b8909_curtsy-lunge.gif', 14, '2025-05-16 13:05:08'),
(31, 5, 'Mountain Climber to Push-up', 'Full body movement', 3, 10, 15, 140, 'uploads/exercises/6830e53084a93_Mountain-climber.gif', 15, '2025-05-16 13:05:08'),
(32, 8, 'Power Clean', 'Olympic lifting movement', 4, 5, 20, 200, 'uploads/exercises/6830e8a39699a_Power-Clean-.gif', 1, '2025-05-16 13:05:08'),
(33, 8, 'Barbell Pin Front Squat', 'Quad-dominant squat variation', 4, 6, 15, 160, 'uploads/exercises/6830e86544edf_Barbell-Pin-Squat.gif', 2, '2025-05-16 13:05:08'),
(34, 8, 'Overhead Press', 'Shoulder pressing movement', 4, 6, 15, 120, 'uploads/exercises/6830e878e421a_Overhead Press.gif', 3, '2025-05-16 13:05:08'),
(35, 8, 'Deadlift', 'Full body pulling movement', 5, 5, 20, 220, 'uploads/exercises/6830e8b537893_Deadlift.gif', 4, '2025-05-16 13:05:08'),
(36, 8, 'Back Squat', 'Primary leg movement', 5, 5, 15, 200, 'uploads/exercises/6830e95ca9165_Power-Clean-.gif', 5, '2025-05-16 13:05:08'),
(37, 8, 'Bench Press', 'Primary pushing movement', 5, 5, 10, 180, 'uploads/exercises/6830e9765c051_benchprss.gif', 6, '2025-05-16 13:05:08'),
(38, 8, 'Barbell Rows', 'Back thickness builder', 4, 8, 10, 150, 'uploads/exercises/6830e99dcdbcf_Barbell Rows.gif', 7, '2025-05-16 13:05:08'),
(39, 8, 'Romanian Deadlift', 'Hamstring and lower back focus', 4, 8, 20, 170, 'uploads/exercises/6830e988cbd15_Romanian Deadlift.gif', 8, '2025-05-16 13:05:08'),
(40, 8, 'Incline Bench Press', 'Upper chest development', 4, 6, 15, 160, 'uploads/exercises/6830e9c05240e_Bench Press.gif', 9, '2025-05-16 13:05:08'),
(41, 8, 'Pull-ups', 'Back width builder', 4, 6, 15, 130, 'uploads/exercises/6830e9e332835_Pull-up.gif', 10, '2025-05-16 13:05:08'),
(42, 8, 'Dumbbell Shoulder Press', 'Shoulder strength and size', 4, 8, 2, 140, 'uploads/exercises/6830ea131d7a2_Dumbbell Shoulder Press.gif', 11, '2025-05-16 13:05:08'),
(43, 8, 'Leg Press', 'Quad and glute development', 4, 8, 10, 190, 'uploads/exercises/6830ea42e1af6_Leg-Press.gif', 12, '2025-05-16 13:05:08'),
(44, 8, 'Bicep Curls', 'Arm development', 3, 10, 20, 100, 'uploads/exercises/6830eaed19e6e_biceps curls.gif', 13, '2025-05-16 13:05:08'),
(45, 8, 'Tricep Extensions', 'Arm development', 3, 10, 20, 100, 'uploads/exercises/6830eb01a576a_Tricep Extensions.gif', 14, '2025-05-16 13:05:08'),
(46, 8, 'Calf Raises', 'Lower leg development', 4, 12, 15, 80, 'uploads/exercises/6830eb15c6613_Calf Raises.gif', 15, '2025-05-16 13:05:08'),
(47, 3, 'Jump Squat', 'Quad and glute development', 5, 10, 4, 170, 'uploads/exercises/682745a06c408_jump_squats.gif', 1, '2025-05-16 13:05:08'),
(48, 3, 'Bench Press', 'Chest development', 5, 8, 4, 130, 'uploads/exercises/682745afbeac9_bench_press.gif', 2, '2025-05-16 13:05:08'),
(49, 3, 'Bent Over Rows', 'Back thickness', 4, 10, 3, 120, 'uploads/exercises/68274625bfad9_barbell_rows.gif', 3, '2025-05-16 13:05:08'),
(50, 3, 'Overhead Press', 'Shoulder development', 4, 8, 5, 110, 'uploads/exercises/6827468469094_Barbell-Standing-Military-Press.gif', 4, '2025-05-16 13:05:08'),
(51, 3, 'Romanian Deadlift', 'Hamstring focus', 4, 10, 4, 140, 'uploads/exercises/68274948d7aaf_romanian_deadlifts.gif', 5, '2025-05-16 13:05:08'),
(52, 3, 'Incline Dumbbell Press', 'Upper chest', 4, 10, 5, 115, 'uploads/exercises/6827496515374_incline_bench_press.gif', 6, '2025-05-16 13:05:08'),
(53, 3, 'Lat Pulldowns', 'Back width', 4, 12, 5, 100, 'uploads/exercises/682749b3a94b5_Lat-Pulldown.gif', 7, '2025-05-16 13:05:08'),
(54, 3, 'Lunges', 'Unilateral leg development', 3, 12, 4, 120, 'uploads/exercises/682749c5e624f_lunges.gif', 8, '2025-05-16 13:05:08'),
(55, 3, 'Dumbbell Shoulder Press', 'Shoulder size', 4, 10, 4, 105, 'uploads/exercises/682749d53f1d3_Dumbbell-Shoulder-Press.gif', 9, '2025-05-16 13:05:08'),
(56, 3, 'Barbell Curls', 'Bicep development', 4, 10, 4, 80, 'uploads/exercises/68274a13b9307_Barbell-Curl.gif', 10, '2025-05-16 13:05:08'),
(57, 3, 'Band Skull Crusher', 'Tricep development', 4, 10, 5, 75, 'uploads/exercises/68274a4250765_Band-Skull-Crusher.gif', 11, '2025-05-16 13:05:08'),
(58, 3, 'Leg Extensions', 'Quad isolation', 3, 15, 4, 90, 'uploads/exercises/68274ac59cb79_LEG-EXTENSION.gif', 12, '2025-05-16 13:05:08'),
(59, 3, 'Leg Curls', 'Hamstring isolation', 3, 15, 5, 90, 'uploads/exercises/68274ab135fbb_Leg-Curl.gif', 13, '2025-05-16 13:05:08'),
(60, 3, 'Close Grip Cable Row', 'Back thickness', 3, 12, 5, 95, 'uploads/exercises/68274a9638ce8_close-grip-cable-row.gif', 14, '2025-05-16 13:05:08'),
(61, 3, 'Calf Raises', 'Lower leg development', 4, 20, 5, 70, 'uploads/exercises/68274aa1f3c79_calf_raises.gif', 15, '2025-05-16 13:05:08'),
(62, 4, 'Goblet Squat', 'Beginner-friendly squat variation', 3, 10, 5, 120, 'uploads/exercises/6827422ade9fb_Dumbbell-Goblet-Squat.gif', 1, '2025-05-16 13:05:08'),
(63, 4, 'Push-ups', 'Bodyweight chest exercise', 3, 12, 4, 80, 'uploads/exercises/682742453e9b5_push_ups.gif', 2, '2025-05-16 13:05:08'),
(64, 4, 'Dumbbell Rows', 'Back development', 3, 10, 3, 90, 'uploads/exercises/6827429f28248_Bent-Over-Dumbbell-Row.gif', 3, '2025-05-16 13:05:08'),
(65, 4, 'Dumbbell Shoulder Press', 'Shoulder development', 3, 10, 5, 85, 'uploads/exercises/682743af76b39_Dumbbell-Shoulder-Press.gif', 4, '2025-05-16 13:05:08'),
(66, 4, 'Glute Bridges', 'Hip and glute activation', 3, 15, 4, 70, 'uploads/exercises/6827444e2a729_Banded-Glute-Bridge.gif', 5, '2025-05-16 13:05:08'),
(67, 4, 'Dumbbell Bench Press', 'Chest development', 3, 10, 4, 95, 'uploads/exercises/682743cbcfb17_Dumbbell-Press.gif', 6, '2025-05-16 13:05:08'),
(68, 4, 'Assisted Pull-ups', 'Back development', 3, 8, 4, 85, 'uploads/exercises/6827441660921_Assisted-Pull-up.gif', 7, '2025-05-16 13:05:08'),
(69, 4, 'Dumbbell Step-up', 'Leg development', 3, 10, 5, 100, 'uploads/exercises/682744e2c7317_Dumbeel-Step-Up.gif', 8, '2025-05-16 13:05:08'),
(70, 4, 'Lateral Raises', 'Shoulder width', 3, 12, 4, 60, 'uploads/exercises/682744f19f71c_lateral_raises.gif', 9, '2025-05-16 13:05:08'),
(71, 4, 'Dumbbell Curls', 'Bicep development', 3, 10, 4, 65, 'uploads/exercises/682745268ab60_dumbbell_curls.gif', 10, '2025-05-16 13:05:08'),
(72, 4, 'Tricep Dips', 'Tricep development', 3, 10, 4, 70, 'uploads/exercises/68274536d9a1d_tricep_dips.gif', 11, '2025-05-16 13:05:08'),
(73, 4, 'Plank', 'Core stability', 3, 30, 4, 50, 'uploads/exercises/6827454674cd2_plank.gif', 12, '2025-05-16 13:05:08'),
(74, 4, 'Bird Dogs', 'Core and balance', 3, 10, 4, 45, 'uploads/exercises/68274516b7c8d_Bird-Dog.gif', 13, '2025-05-16 13:05:08'),
(75, 4, 'Wall Sits', 'Leg endurance', 3, 30, 5, 80, 'uploads/exercises/68274408676cf_Exercise-Ball-Wall-Squat.gif', 14, '2025-05-16 13:05:08'),
(76, 4, 'Superman', 'Lower back development', 3, 12, 5, 55, 'uploads/exercises/682743f1114ab_Superman-exercise.gif', 15, '2025-05-16 13:05:08'),
(77, 9, 'Kettlebell Swings', 'Hip hinge movement', 4, 12, 15, 160, 'uploads/exercises/6830eb3a85d2a_Kettlebell Swings.gif', 1, '2025-05-16 13:05:08'),
(78, 9, 'Goblet Squat', 'Front-loaded squat variation', 4, 10, 10, 140, 'uploads/exercises/6830eb55314c2_goblet squat.gif', 2, '2025-05-16 13:05:08'),
(79, 9, 'Push-ups', 'Bodyweight pushing movement', 4, 12, 10, 120, 'uploads/exercises/6830eb7002117_Pull-up.gif', 3, '2025-05-16 13:05:08'),
(80, 9, 'Dumbbell Rows', 'Single-arm pulling movement', 4, 10, 20, 130, 'uploads/exercises/6830eb9d86f13_Dumbbell Rows.gif', 4, '2025-05-16 13:05:08'),
(81, 9, 'Dumbbell Shoulder Press', 'Overhead pressing movement', 4, 10, 20, 130, 'uploads/exercises/6830ebb109764_Dumbbell Shoulder Press.gif', 5, '2025-05-16 13:05:08'),
(82, 9, 'Lunges', 'Unilateral leg movement', 3, 12, 15, 140, 'uploads/exercises/6830ebd14787c_Lunges.gif', 6, '2025-05-16 13:05:08'),
(83, 9, 'Plank', 'Core stability', 3, 30, 10, 80, 'uploads/exercises/6830ebe30b514_plank.gif', 7, '2025-05-16 13:05:08'),
(84, 9, 'Mountain Climbers', 'Core and cardio', 3, 20, 15, 120, 'uploads/exercises/6830ec00136c0_Mountain-climber.gif', 8, '2025-05-16 13:05:08'),
(85, 9, 'Burpees', 'Full body movement', 3, 10, 15, 150, 'uploads/exercises/6830ec116343f_burpees.gif', 9, '2025-05-16 13:05:08'),
(86, 9, 'Box Jumps', 'Explosive leg movement', 3, 8, 15, 140, 'uploads/exercises/6830ec1eb0313_Box-Jump.gif', 10, '2025-05-16 13:05:08'),
(87, 9, 'Battle Ropes', 'Upper body cardio', 3, 1, 10, 160, 'uploads/exercises/6830ec3510291_Battle-Rope.gif', 11, '2025-05-16 13:05:08'),
(88, 9, 'Bear Crawls', 'Full body movement', 3, 1, 10, 130, 'uploads/exercises/6830ec6926ef2_Bear Crawls.gif', 12, '2025-05-16 13:05:08'),
(89, 9, 'Squat Jumps', 'Explosive leg movement', 3, 12, 15, 150, 'uploads/exercises/6830ec76f199b_Squat Jumps.gif', 13, '2025-05-16 13:05:08'),
(90, 9, 'Curtsy Lunge', 'Leg and core movement', 3, 10, 15, 130, 'uploads/exercises/683117d657af7_curtsy-lunge.gif', 14, '2025-05-16 13:05:08'),
(91, 9, 'Cool Down Stretch', 'Flexibility and recovery', 1, 1, 10, 30, NULL, 15, '2025-05-16 13:05:08'),
(92, 10, 'Bodyweight Squat', 'Basic leg movement', 3, 12, 15, 100, 'uploads/exercises/6830ed8b8f46c_Bodyweight Squat.gif', 1, '2025-05-16 13:05:08'),
(93, 10, 'Push-ups', 'Basic pushing movement', 3, 10, 20, 90, 'uploads/exercises/6830ecba6c6ee_Push-ups.gif', 2, '2025-05-16 13:05:08'),
(94, 10, 'Dumbbell Rows', 'Basic pulling movement', 3, 10, 10, 100, 'uploads/exercises/6830ecf180726_Dumbbell Rows.gif', 3, '2025-05-16 13:05:08'),
(95, 10, 'Dumbbell Shoulder Press', 'Basic overhead movement', 3, 10, 10, 95, 'uploads/exercises/6830ed23405f0_Dumbbell Shoulder Press.gif', 4, '2025-05-16 13:05:08'),
(96, 10, 'Glute Bridges', 'Hip and glute activation', 3, 15, 15, 70, 'uploads/exercises/6830ed487986f_Glute-Bridge-.gif', 5, '2025-05-16 13:05:08'),
(97, 10, 'Plank', 'Core stability', 3, 30, 5, 60, 'uploads/exercises/6830ed5bc5c4c_plank.gif', 6, '2025-05-16 13:05:08'),
(98, 10, 'Bird Dogs', 'Core and balance', 3, 10, 10, 50, 'uploads/exercises/6830ed746caec_Bird-Dog.gif', 7, '2025-05-16 13:05:08'),
(99, 10, 'Ball Wall Squat', 'Leg endurance', 3, 30, 15, 80, 'uploads/exercises/6831184b659a3_Exercise-Ball-Wall-Squat.gif', 8, '2025-05-16 13:05:08'),
(100, 10, 'Superman', 'Lower back development', 3, 12, 20, 55, 'uploads/exercises/6830edde728ec_Superman.gif', 9, '2025-05-16 13:05:08'),
(101, 10, 'Arm Circles', 'Shoulder mobility', 3, 20, 10, 40, 'uploads/exercises/6830edf10455b_Arm-Circles_Shoulders.gif', 10, '2025-05-16 13:05:08'),
(102, 10, 'Hip Hinges', 'Basic hip movement', 3, 12, 20, 60, 'uploads/exercises/6830ee1604e51_Hip Hinges.gif', 11, '2025-05-16 13:05:08'),
(103, 10, 'Step-ups', 'Basic leg movement', 3, 10, 10, 85, 'uploads/exercises/6830ee29a915f_Step-ups.gif', 12, '2025-05-16 13:05:08'),
(104, 10, 'Dumbbell Curls', 'Basic arm movement', 3, 10, 20, 65, 'uploads/exercises/6830ee55472aa_Dumbbell-Curl.gif', 13, '2025-05-16 13:05:08'),
(105, 10, 'Tricep Dips', 'Basic arm movement', 3, 10, 20, 70, 'uploads/exercises/6830ee7044f5e_Tricep Extensions.gif', 14, '2025-05-16 13:05:08'),
(106, 10, 'Cool Down Stretch', 'Flexibility and recovery', 1, 1, 10, 30, NULL, 15, '2025-05-16 13:05:08'),
(107, 6, 'Treadmill Run', 'Steady state cardio', 1, 1, 20, 200, 'uploads/exercises/683114dde03b3_Treadmill-.gif', 1, '2025-05-16 13:05:09'),
(108, 6, 'Stationary Bike', 'Low impact cardio', 1, 1, 15, 150, 'uploads/exercises/683114f7d336b_Bike.gif', 2, '2025-05-16 13:05:09'),
(109, 6, 'Elliptical', 'Full body cardio', 1, 1, 15, 160, 'uploads/exercises/683115aabdaa0_Elliptical-Machine.gif', 3, '2025-05-16 13:05:09'),
(110, 6, 'Rowing Machine', 'Full body cardio', 1, 1, 10, 180, 'uploads/exercises/68311652cfc9e_Seated-Row-Machine.gif', 4, '2025-05-16 13:05:09'),
(111, 6, 'Stair Climber', 'Leg focused cardio', 1, 1, 10, 170, 'uploads/exercises/6830e572af692_9d19a7f089d639e93b4e910042f6a679Stair Climber.gif', 5, '2025-05-16 13:05:09'),
(112, 6, 'Walking Lunges', 'Leg strength and cardio', 3, 20, 15, 120, 'uploads/exercises/683115d9b7fe4_Walking Lunge.gif', 6, '2025-05-16 13:05:09'),
(113, 6, 'Push-ups', 'Upper body strength', 3, 12, 10, 90, 'uploads/exercises/68311665c0ade_push_ups.gif', 7, '2025-05-16 13:05:09'),
(114, 6, 'Bodyweight Squats', 'Leg strength', 3, 15, 10, 100, 'uploads/exercises/6830e5fdcc11f_Bodyweight Squat.gif', 8, '2025-05-16 13:05:09'),
(115, 6, 'Plank', 'Core stability', 3, 30, 10, 70, 'uploads/exercises/6831167191430_plank.gif', 9, '2025-05-16 13:05:09'),
(116, 6, 'Jumping Jacks', 'Cardio burst', 3, 30, 10, 80, 'uploads/exercises/683115fdf2804_Jumping-jack.gif', 10, '2025-05-16 13:05:09'),
(117, 6, 'Mountain Climbers', 'Core and cardio', 3, 20, 10, 90, 'uploads/exercises/6830e62c60ef0_19857Mountain Climbers.gif', 11, '2025-05-16 13:05:09'),
(118, 6, 'Dumbbell Glute Bridge', 'Hip and glute activation', 3, 15, 10, 60, 'uploads/exercises/6830e64a3aa6c_Dumbbell-Glute-Bridge.gif', 12, '2025-05-16 13:05:09'),
(119, 6, 'Superman', 'Lower back strength', 3, 12, 10, 50, 'uploads/exercises/6830e657dd393_Superman.gif', 13, '2025-05-16 13:05:09'),
(120, 6, 'Arm Circles', 'Shoulder mobility', 3, 20, 10, 40, 'uploads/exercises/6830e6650fedd_Arm-Circles_Shoulders.gif', 14, '2025-05-16 13:05:09'),
(121, 6, 'Standing Quadriceps', 'Flexibility and recovery', 1, 1, 10, 30, 'uploads/exercises/6831169d0494f_Standing-Quadriceps.gif', 15, '2025-05-16 13:05:09'),
(122, 7, 'Deadlift', 'Full body compound movement', 4, 8, 20, 180, 'uploads/exercises/6830e6863e190_Deadlift.gif', 1, '2025-05-16 13:05:09'),
(123, 7, 'Squat', 'Lower body compound movement', 4, 10, 20, 170, 'uploads/exercises/6830e6a2eac43_Squat.gif', 2, '2025-05-16 13:05:09'),
(124, 7, 'Bench Press', 'Upper body pushing movement', 4, 8, 15, 150, 'uploads/exercises/6830e6b9b919d_Bench Press.gif', 3, '2025-05-16 13:05:09'),
(125, 7, 'Bent Over Rows', 'Back pulling movement', 4, 10, 10, 140, 'uploads/exercises/6830e6c90c538_Bent Over Rows.gif', 4, '2025-05-16 13:05:09'),
(126, 7, 'Overhead Press', 'Shoulder pressing movement', 4, 8, 15, 130, 'uploads/exercises/6830e6db3acb6_Overhead Press.gif', 5, '2025-05-16 13:05:09'),
(127, 7, 'Kettlebell Swings', 'Hip hinge movement', 3, 15, 15, 160, 'uploads/exercises/6830e6e7e7b70_Kettlebell-Swings.gif', 6, '2025-05-16 13:05:09'),
(128, 7, 'Box Jumps', 'Explosive leg movement', 3, 10, 15, 150, 'uploads/exercises/6830e6f6983c3_27642 jumpbox.gif', 7, '2025-05-16 13:05:09'),
(129, 7, 'Battle Ropes', 'Upper body cardio', 3, 1, 10, 170, 'uploads/exercises/6830e722b9577_Battle-Rope.gif', 8, '2025-05-16 13:05:09'),
(130, 7, 'Sprint Intervals', 'High-intensity running', 5, 1, 20, 200, 'uploads/exercises/6830e72fe5a68_sprint interval.gif', 9, '2025-05-16 13:05:09'),
(131, 7, 'Burpees', 'Full body movement', 3, 12, 15, 180, 'uploads/exercises/6830e73cdc513_burpees.gif', 10, '2025-05-16 13:05:09'),
(132, 7, 'Mountain Climbers', 'Core and cardio', 3, 20, 15, 140, 'uploads/exercises/6830e747da475_Mountain-climber.gif', 11, '2025-05-16 13:05:09'),
(133, 7, 'Plank to Push-up', 'Core and upper body', 3, 10, 15, 130, 'uploads/exercises/6830e75f3d9a0_plank.gif', 12, '2025-05-16 13:05:09'),
(134, 7, 'Bear Crawls', 'Full body movement', 3, 1, 10, 150, 'uploads/exercises/6830e774c7d96_Bear Crawls.gif', 13, '2025-05-16 13:05:09'),
(135, 7, 'Squat Jumps', 'Explosive leg movement', 3, 15, 15, 160, 'uploads/exercises/6830e7911dbd1_Squat Jumps.gif', 14, '2025-05-16 13:05:09'),
(136, 7, 'Curtsy Lunge', 'Leg and core movement', 3, 12, 15, 140, 'uploads/exercises/68311759ca222_curtsy-lunge.gif', 15, '2025-05-16 13:05:09');

-- --------------------------------------------------------

--
-- Table structure for table `exercise_logs`
--

CREATE TABLE `exercise_logs` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `exercise_id` int(11) NOT NULL,
  `exercise_date` date NOT NULL,
  `sets_completed` int(11) NOT NULL,
  `reps_completed` int(11) NOT NULL,
  `duration` int(11) NOT NULL,
  `calories_burned` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_condition_types`
--

CREATE TABLE `health_condition_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_condition_types`
--

INSERT INTO `health_condition_types` (`id`, `name`, `description`) VALUES
(1, 'Asthma', 'Respiratory condition causing breathing difficulty'),
(2, 'Diabetes', 'Condition affecting blood sugar levels'),
(3, 'Hypertension', 'High blood pressure'),
(4, 'Heart Disease', 'Conditions affecting the heart'),
(5, 'Back Pain', 'Chronic or acute back pain'),
(6, 'Joint Pain', 'Pain in joints such as knees, elbows, etc.'),
(7, 'Allergies', 'Various allergic reactions'),
(8, 'Pregnancy', 'Currently pregnant'),
(9, 'Recent Surgery', 'Surgery within the last 6 months'),
(10, 'Other', 'Other health conditions not listed');

-- --------------------------------------------------------

--
-- Table structure for table `meal_logs`
--

CREATE TABLE `meal_logs` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `meal_id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_logs`
--

CREATE TABLE `membership_logs` (
  `id` int(11) NOT NULL,
  `user_membership_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_logs`
--

INSERT INTO `membership_logs` (`id`, `user_membership_id`, `action`, `details`, `created_at`) VALUES
(11, 23, 'receipt_upload', '{\"payment_id\":\"37\",\"receipt_path\":\"upgrade_receipt_23_1748078930_8c306f43dade40ba.jpg\",\"upload_date\":\"2025-05-24 11:28:50\"}', '2025-05-24 09:28:50'),
(12, 23, 'upgrade_approved', '{\"old_plan\":\"1\",\"new_plan\":\"2\",\"payment_id\":\"37\"}', '2025-05-24 09:32:58');

-- --------------------------------------------------------

--
-- Table structure for table `membership_plans`
--

CREATE TABLE `membership_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `duration` int(11) NOT NULL DEFAULT 1 COMMENT 'Duration in months',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_plans`
--

INSERT INTO `membership_plans` (`id`, `name`, `price`, `description`, `features`, `duration`, `active`, `created_at`) VALUES
(1, 'Basic', 999.00, 'Access to gym equipment and basic facilities', 'Access to gym equipment\r\nBasic fitness assessment\r\nGroup classes access\r\nLocker room access', 1, 1, '2025-05-15 03:06:29'),
(2, 'Pro', 1999.00, 'Includes personal training sessions and nutrition consultation', 'All Basic features\r\nPersonal training sessions\r\nNutrition consultation\r\nProgress tracking\r\nPriority booking', 1, 1, '2025-05-15 03:06:29'),
(3, 'Premium', 2999.00, 'Full access to all facilities, unlimited personal training, and premium amenities', 'All Pro features\r\nUnlimited personal training\r\nCustom meal plans\r\nRecovery sessions\r\nPriority support\r\nExclusive events access', 1, 1, '2025-05-15 03:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `member_diet_plans`
--

CREATE TABLE `member_diet_plans` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `diet_plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_diet_plans`
--

INSERT INTO `member_diet_plans` (`id`, `member_id`, `diet_plan_id`, `start_date`, `end_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(5, 5, 2, '2025-05-24', '2025-06-24', 'active', '', '2025-05-24 09:10:55', '2025-05-24 09:10:55'),
(6, 18, 6, '2025-05-24', '2025-06-24', 'active', '', '2025-05-24 09:35:45', '2025-05-24 09:35:45');

-- --------------------------------------------------------

--
-- Table structure for table `member_progress`
--

CREATE TABLE `member_progress` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `body_fat` decimal(5,2) DEFAULT NULL,
  `muscle_mass` decimal(5,2) DEFAULT NULL,
  `before_image` varchar(255) DEFAULT NULL,
  `after_image` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_progress`
--

INSERT INTO `member_progress` (`id`, `member_id`, `entry_date`, `weight`, `body_fat`, `muscle_mass`, `before_image`, `after_image`, `notes`, `created_at`, `updated_at`) VALUES
(0, 5, '2025-05-24', 45.00, 15.00, 22.00, '1748078053_247ff623_before.png', '1748078053_5b8e62a0_after.png', 'Grabi nagka Mask ko!', '2025-05-24 09:14:13', '2025-05-24 09:14:13');

-- --------------------------------------------------------

--
-- Table structure for table `member_workout_plans`
--

CREATE TABLE `member_workout_plans` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `workout_plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_workout_plans`
--

INSERT INTO `member_workout_plans` (`id`, `member_id`, `workout_plan_id`, `start_date`, `end_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(7, 5, 4, '2025-05-24', '2025-06-07', 'active', '', '2025-05-24 09:10:35', '2025-05-24 09:10:35'),
(8, 18, 5, '2025-05-24', '2025-06-24', 'active', '', '2025-05-24 09:35:33', '2025-05-24 09:35:33');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `message_type` enum('coach_to_member','member_to_coach') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `attachment_path`, `is_read`, `message_type`, `created_at`) VALUES
(6, 2, 5, 'Open MIc', 'uploads/messages/68318d3f4a4e9.png', 1, 'coach_to_member', '2025-05-24 09:11:27'),
(7, 5, 2, 'WAW', NULL, 0, 'member_to_coach', '2025-05-24 09:45:39');

--
-- Triggers `messages`
--
DELIMITER $$
CREATE TRIGGER `after_message_insert` AFTER INSERT ON `messages` FOR EACH ROW BEGIN
    INSERT INTO message_notifications (user_id, unread_count)
    VALUES (NEW.receiver_id, 1)
    ON DUPLICATE KEY UPDATE unread_count = unread_count + 1;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_message_read` AFTER UPDATE ON `messages` FOR EACH ROW BEGIN
    IF NEW.is_read = 1 AND OLD.is_read = 0 THEN
        UPDATE message_notifications 
        SET unread_count = GREATEST(0, unread_count - 1)
        WHERE user_id = NEW.receiver_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `message_notifications`
--

CREATE TABLE `message_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `unread_count` int(11) DEFAULT 0,
  `last_checked` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_notifications`
--

INSERT INTO `message_notifications` (`id`, `user_id`, `unread_count`, `last_checked`) VALUES
(7, 5, 0, '2025-05-24 09:11:27'),
(8, 5, 0, '2025-05-24 09:11:27'),
(9, 2, 1, '2025-05-24 09:45:39');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_membership_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('qr_code') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `qr_code_path` varchar(255) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `payment_type` enum('initial','upgrade','renewal') DEFAULT 'initial',
  `is_upgrading` tinyint(1) DEFAULT 0,
  `is_renewal` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_membership_id`, `amount`, `payment_method`, `reference_number`, `payment_date`, `status`, `qr_code_path`, `receipt_path`, `payment_type`, `is_upgrading`, `is_renewal`) VALUES
(30, 17, 1999.00, 'qr_code', 'FIT202505240017', '2025-05-24 09:06:22', 'completed', 'qr_payment_17.png', 'receipt_17_1748077626.jpg', 'initial', 0, 0),
(36, 23, 999.00, 'qr_code', 'FIT202505240023', '2025-05-24 09:26:32', 'completed', 'qr_payment_23.png', 'receipt_23_1748078815.jpg', 'initial', 0, 0),
(37, 23, 1000.00, 'qr_code', 'FIT202505240003', '2025-05-24 09:28:50', 'completed', 'qr_upgrade_23.png', 'upgrade_receipt_23_1748078930_8c306f43dade40ba.jpg', 'upgrade', 1, 0),
(38, 24, 1999.00, 'qr_code', 'FIT202505240024', '2025-05-24 09:37:30', 'completed', 'qr_payment_24.png', 'receipt_24_1748079465.jpg', 'initial', 0, 0);

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `after_payment_update` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE user_memberships 
        SET payment_status = 'completed',
            status = CASE 
                WHEN status = 'upgrade_pending' THEN 'active'
                ELSE status 
            END
        WHERE id = NEW.user_membership_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_renewal_payment_update` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' AND NEW.payment_type = 'renewal' THEN
        -- Update the membership end date by adding the plan duration
        UPDATE user_memberships um
        JOIN membership_plans mp ON um.plan_id = mp.id
        SET um.end_date = DATE_ADD(um.end_date, INTERVAL mp.duration MONTH),
            um.payment_status = 'completed',
            um.status = 'active'
        WHERE um.id = NEW.user_membership_id;
        
        -- Log the renewal
        INSERT INTO membership_logs (user_membership_id, action, details)
        VALUES (NEW.user_membership_id, 'renewal_completed', 
                JSON_OBJECT('payment_id', NEW.id, 
                           'renewal_date', NEW.payment_date,
                           'new_end_date', DATE_ADD((SELECT end_date FROM user_memberships WHERE id = NEW.user_membership_id), 
                                                   INTERVAL (SELECT duration FROM membership_plans WHERE id = (SELECT plan_id FROM user_memberships WHERE id = NEW.user_membership_id)) MONTH)));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `plan_upgrade_requests`
--

CREATE TABLE `plan_upgrade_requests` (
  `id` int(11) NOT NULL,
  `user_membership_id` int(11) NOT NULL,
  `current_plan_id` int(11) NOT NULL,
  `new_plan_id` int(11) NOT NULL,
  `upgrade_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plan_upgrade_requests`
--

INSERT INTO `plan_upgrade_requests` (`id`, `user_membership_id`, `current_plan_id`, `new_plan_id`, `upgrade_amount`, `status`, `created_at`, `processed_at`) VALUES
(8, 23, 1, 2, 1000.00, 'approved', '2025-05-24 09:28:36', '2025-05-24 09:32:58');

--
-- Triggers `plan_upgrade_requests`
--
DELIMITER $$
CREATE TRIGGER `after_upgrade_request_insert` AFTER INSERT ON `plan_upgrade_requests` FOR EACH ROW BEGIN
    UPDATE user_memberships 
    SET status = 'upgrade_pending'
    WHERE id = NEW.user_membership_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_upgrade_request_update` AFTER UPDATE ON `plan_upgrade_requests` FOR EACH ROW BEGIN
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        UPDATE user_memberships 
        SET plan_id = NEW.new_plan_id,
            status = 'active'
        WHERE id = NEW.user_membership_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pre_assessments`
--

CREATE TABLE `pre_assessments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `height` float NOT NULL COMMENT 'in cm',
  `weight` float NOT NULL COMMENT 'in kg',
  `allergies` text DEFAULT NULL,
  `health_conditions` text DEFAULT NULL,
  `fitness_level` enum('beginner','intermediate','advanced') NOT NULL,
  `fitness_goal` enum('weight_loss','muscle_gain','strength','endurance','overall_fitness') NOT NULL,
  `preferred_training_time` enum('morning','afternoon','evening','flexible') NOT NULL,
  `bmi` float DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pre_assessments`
--

INSERT INTO `pre_assessments` (`id`, `user_id`, `age`, `gender`, `height`, `weight`, `allergies`, `health_conditions`, `fitness_level`, `fitness_goal`, `preferred_training_time`, `bmi`, `notes`, `completed`, `created_at`, `updated_at`) VALUES
(15, 5, 20, 'male', 155, 44, 'Eggs', '2', 'beginner', 'muscle_gain', 'flexible', 18.3143, '', 1, '2025-05-24 09:02:51', '2025-05-24 09:02:51'),
(16, 18, 20, 'male', 166, 45, 'Peanuts, Shellfish', '4', 'intermediate', 'muscle_gain', 'afternoon', 16.3304, '', 1, '2025-05-24 09:16:30', '2025-05-24 09:16:30'),
(17, 19, 21, 'male', 166, 66, 'Pet Dander', '5', 'advanced', 'strength', 'morning', 23.9512, '', 1, '2025-05-24 09:37:11', '2025-05-24 09:37:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','coach','member') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `fullname`, `phone`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$dFAQFu5ZkyE.YKwvIFNJf.X.vi5dnojAwXGM851mQX1SgOMA5rFu.', 'admin@fitclub.com', NULL, NULL, 'admin', '2025-05-15 03:06:29'),
(2, 'coach1', '$2y$10$/dED.QBT9vI7SVtNc31s..k9ZdskiGhRmfgeBIxu0CV1sLkzamrbe', 'bulk@fitclub.com', 'Gabriel Javier', '09262682102', 'coach', '2025-05-15 03:06:29'),
(3, 'coach2', '$2y$10$/dED.QBT9vI7SVtNc31s..k9ZdskiGhRmfgeBIxu0CV1sLkzamrbe', 'weightloss@fitclub.com', 'Michael Jackson', '09123123132', 'coach', '2025-05-15 03:06:29'),
(4, 'coach3', '$2y$10$/dED.QBT9vI7SVtNc31s..k9ZdskiGhRmfgeBIxu0CV1sLkzamrbe', 'strength@fitclub.com', 'Mike Johnson', NULL, 'coach', '2025-05-15 03:06:29'),
(5, 'kael', '$2y$10$Y1f1lW0V9LUSTe1wKbzTkezx8y2dBSkuDUMRCeVLzCqaTcgBSlpn2', 'kael@gmail.com', 'Michael Torillo', '09265852171', 'member', '2025-05-15 03:07:26'),
(18, 'law', '$2y$10$vwLya0OxKLvBCQ1IMVLKquzOqmAGTJ1SJ34H0VPOJIkLOTY6XXmuC', 'law@gmail.com', 'Lawrence Alejo', '09836763633', 'member', '2025-05-24 09:15:58'),
(19, 'James', '$2y$10$7dYLVMdDiwcASBoluryE7uRzTkxk88lgtExRGt2fsOegz5BbcN4W.', 'Buenafe@gmail.com', 'James Buenafe', '09876543456', 'member', '2025-05-24 09:36:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_health_conditions`
--

CREATE TABLE `user_health_conditions` (
  `id` int(11) NOT NULL,
  `pre_assessment_id` int(11) NOT NULL,
  `condition_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_health_conditions`
--

INSERT INTO `user_health_conditions` (`id`, `pre_assessment_id`, `condition_id`) VALUES
(9, 15, 2),
(10, 16, 4),
(11, 17, 5);

-- --------------------------------------------------------

--
-- Table structure for table `user_memberships`
--

CREATE TABLE `user_memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `coach_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `status` enum('active','expired','cancelled','upgrade_pending') DEFAULT 'active',
  `is_pending` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_memberships`
--

INSERT INTO `user_memberships` (`id`, `user_id`, `plan_id`, `coach_id`, `start_date`, `end_date`, `payment_status`, `status`, `is_pending`, `created_at`) VALUES
(17, 5, 2, 1, '2025-05-24', '2025-06-24', 'completed', 'active', 1, '2025-05-24 09:06:22'),
(23, 18, 2, 2, '2025-05-24', '2025-06-24', 'completed', 'active', 1, '2025-05-24 09:26:32'),
(24, 19, 2, 3, '2025-05-24', '2025-06-24', 'completed', 'active', 1, '2025-05-24 09:37:30');

-- --------------------------------------------------------

--
-- Table structure for table `workout_plans`
--

CREATE TABLE `workout_plans` (
  `id` int(11) NOT NULL,
  `coach_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `specialization` enum('bulk','weight_loss','strength') NOT NULL,
  `difficulty_level` enum('beginner','intermediate','advanced') NOT NULL,
  `duration_weeks` int(11) NOT NULL,
  `total_calories_burn` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workout_plans`
--

INSERT INTO `workout_plans` (`id`, `coach_id`, `name`, `description`, `specialization`, `difficulty_level`, `duration_weeks`, `total_calories_burn`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'Bulking Power Program', 'Intensive strength training program focused on muscle hypertrophy and power development', 'bulk', 'intermediate', 2, 2800, 1, '2025-05-16 13:05:07', '2025-05-16 13:05:07'),
(3, 1, 'Mass Builder', 'High-volume training program designed for maximum muscle growth', 'bulk', 'advanced', 2, 3200, 1, '2025-05-16 13:05:07', '2025-05-16 13:05:07'),
(4, 1, 'Strength & Size', 'Balanced program combining strength training with hypertrophy work', 'bulk', 'beginner', 2, 2400, 1, '2025-05-16 13:05:07', '2025-05-16 13:05:07'),
(5, 2, 'Fat Burner', 'High-intensity interval training program for maximum fat loss', 'weight_loss', 'intermediate', 2, 3500, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(6, 2, 'Cardio Blast', 'Cardio-focused program with strength training elements', 'weight_loss', 'beginner', 2, 2800, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(7, 2, 'Metabolic Boost', 'Metabolic conditioning program for sustainable weight loss', 'weight_loss', 'advanced', 2, 4000, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(8, 3, 'Power Lifting', 'Focus on compound lifts and strength development', 'strength', 'advanced', 2, 3000, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(9, 3, 'Functional Strength', 'Full-body strength training with functional movements', 'strength', 'intermediate', 2, 2600, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08'),
(10, 3, 'Strength Foundation', 'Basic strength building program for beginners', 'strength', 'beginner', 2, 2200, 1, '2025-05-16 13:05:08', '2025-05-16 13:05:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `coach_id` (`coach_id`);

--
-- Indexes for table `calorie_tracking`
--
ALTER TABLE `calorie_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `coaches`
--
ALTER TABLE `coaches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `diet_plans`
--
ALTER TABLE `diet_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coach_id` (`coach_id`);

--
-- Indexes for table `diet_plan_feedback`
--
ALTER TABLE `diet_plan_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_diet_plan_id` (`member_diet_plan_id`);

--
-- Indexes for table `diet_plan_meals`
--
ALTER TABLE `diet_plan_meals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `diet_plan_id` (`diet_plan_id`);

--
-- Indexes for table `exercises`
--
ALTER TABLE `exercises`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workout_plan_id` (`workout_plan_id`);

--
-- Indexes for table `exercise_logs`
--
ALTER TABLE `exercise_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `exercise_id` (`exercise_id`);

--
-- Indexes for table `health_condition_types`
--
ALTER TABLE `health_condition_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meal_logs`
--
ALTER TABLE `meal_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `meal_id` (`meal_id`);

--
-- Indexes for table `membership_logs`
--
ALTER TABLE `membership_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_membership_id` (`user_membership_id`);

--
-- Indexes for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `member_diet_plans`
--
ALTER TABLE `member_diet_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `diet_plan_id` (`diet_plan_id`);

--
-- Indexes for table `member_workout_plans`
--
ALTER TABLE `member_workout_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `workout_plan_id` (`workout_plan_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `messages_ibfk_1` (`sender_id`),
  ADD KEY `messages_ibfk_2` (`receiver_id`);

--
-- Indexes for table `message_notifications`
--
ALTER TABLE `message_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_membership_id` (`user_membership_id`);

--
-- Indexes for table `plan_upgrade_requests`
--
ALTER TABLE `plan_upgrade_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_membership_id` (`user_membership_id`),
  ADD KEY `current_plan_id` (`current_plan_id`),
  ADD KEY `new_plan_id` (`new_plan_id`);

--
-- Indexes for table `pre_assessments`
--
ALTER TABLE `pre_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_health_conditions`
--
ALTER TABLE `user_health_conditions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pre_assessment_id` (`pre_assessment_id`),
  ADD KEY `condition_id` (`condition_id`);

--
-- Indexes for table `user_memberships`
--
ALTER TABLE `user_memberships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `coach_id` (`coach_id`);

--
-- Indexes for table `workout_plans`
--
ALTER TABLE `workout_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coach_id` (`coach_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calorie_tracking`
--
ALTER TABLE `calorie_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `coaches`
--
ALTER TABLE `coaches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `diet_plans`
--
ALTER TABLE `diet_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `diet_plan_feedback`
--
ALTER TABLE `diet_plan_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `diet_plan_meals`
--
ALTER TABLE `diet_plan_meals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=263;

--
-- AUTO_INCREMENT for table `exercises`
--
ALTER TABLE `exercises`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `exercise_logs`
--
ALTER TABLE `exercise_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `health_condition_types`
--
ALTER TABLE `health_condition_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `meal_logs`
--
ALTER TABLE `meal_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `membership_logs`
--
ALTER TABLE `membership_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `member_diet_plans`
--
ALTER TABLE `member_diet_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `member_workout_plans`
--
ALTER TABLE `member_workout_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `message_notifications`
--
ALTER TABLE `message_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `plan_upgrade_requests`
--
ALTER TABLE `plan_upgrade_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pre_assessments`
--
ALTER TABLE `pre_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_health_conditions`
--
ALTER TABLE `user_health_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_memberships`
--
ALTER TABLE `user_memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `workout_plans`
--
ALTER TABLE `workout_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `calorie_tracking`
--
ALTER TABLE `calorie_tracking`
  ADD CONSTRAINT `calorie_tracking_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `coaches`
--
ALTER TABLE `coaches`
  ADD CONSTRAINT `coaches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `diet_plans`
--
ALTER TABLE `diet_plans`
  ADD CONSTRAINT `diet_plans_ibfk_1` FOREIGN KEY (`coach_id`) REFERENCES `coaches` (`id`);

--
-- Constraints for table `diet_plan_feedback`
--
ALTER TABLE `diet_plan_feedback`
  ADD CONSTRAINT `diet_plan_feedback_ibfk_1` FOREIGN KEY (`member_diet_plan_id`) REFERENCES `member_diet_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `diet_plan_meals`
--
ALTER TABLE `diet_plan_meals`
  ADD CONSTRAINT `diet_plan_meals_ibfk_1` FOREIGN KEY (`diet_plan_id`) REFERENCES `diet_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exercises`
--
ALTER TABLE `exercises`
  ADD CONSTRAINT `exercises_ibfk_1` FOREIGN KEY (`workout_plan_id`) REFERENCES `workout_plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exercise_logs`
--
ALTER TABLE `exercise_logs`
  ADD CONSTRAINT `exercise_logs_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `exercise_logs_ibfk_2` FOREIGN KEY (`exercise_id`) REFERENCES `exercises` (`id`);

--
-- Constraints for table `meal_logs`
--
ALTER TABLE `meal_logs`
  ADD CONSTRAINT `meal_logs_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `meal_logs_ibfk_2` FOREIGN KEY (`meal_id`) REFERENCES `diet_plan_meals` (`id`);

--
-- Constraints for table `membership_logs`
--
ALTER TABLE `membership_logs`
  ADD CONSTRAINT `membership_logs_ibfk_1` FOREIGN KEY (`user_membership_id`) REFERENCES `user_memberships` (`id`);

--
-- Constraints for table `member_diet_plans`
--
ALTER TABLE `member_diet_plans`
  ADD CONSTRAINT `member_diet_plans_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `member_diet_plans_ibfk_2` FOREIGN KEY (`diet_plan_id`) REFERENCES `diet_plans` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
