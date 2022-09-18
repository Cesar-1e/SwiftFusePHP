-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-09-2022 a las 18:10:10
-- Versión del servidor: 8.0.25
-- Versión de PHP: 8.1.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `framework_test`
--
CREATE DATABASE IF NOT EXISTS `framework_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `framework_test`;

DELIMITER $$
--
-- Procedimientos
--
CREATE PROCEDURE `PR_getAllPeople` ()   SELECT * FROM people$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `people`
--

CREATE TABLE `people` (
  `peopleId` int NOT NULL,
  `name` varchar(40) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `people`
--

INSERT INTO `people` (`peopleId`, `name`, `email`) VALUES
(1, 'Verena', 'vlenney0@technorati.com'),
(2, 'Mimi', 'mdeambrosi1@free.fr'),
(3, 'Patti', 'pcroucher2@example.com'),
(4, 'Hatti', 'hvitet3@opensource.org'),
(5, 'Nonna', 'ntomsa4@ed.gov'),
(6, 'Jeth', 'jbryns5@acquirethisname.com'),
(7, 'Jocko', 'jfritchley6@shop-pro.jp'),
(8, 'Belia', 'bwrennall7@moonfruit.com'),
(9, 'Trip', 'tfellnee8@symantec.com'),
(10, 'Erick', 'estansall9@dyndns.org');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `people`
--
ALTER TABLE `people`
  ADD PRIMARY KEY (`peopleId`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `people`
--
ALTER TABLE `people`
  MODIFY `peopleId` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
