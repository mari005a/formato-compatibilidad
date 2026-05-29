-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 29-05-2026 a las 17:10:16
-- Versión del servidor: 11.7.2-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `formato_compatibilidad`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `autoridades`
--

CREATE TABLE `autoridades` (
  `ID_Autoridad` int(11) NOT NULL,
  `Autoridad_1` varchar(100) NOT NULL,
  `Nombre_Plantel1` varchar(150) NOT NULL,
  `Puesto` varchar(100) NOT NULL,
  `Nombre_Autoridad` varchar(150) NOT NULL,
  `Autoridad_2` varchar(100) DEFAULT NULL,
  `Nombre_Plantel2` varchar(150) DEFAULT NULL,
  `Puesto_2` varchar(100) DEFAULT NULL,
  `Nombre_Autoridad_2` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compatibilidad`
--

CREATE TABLE `compatibilidad` (
  `ID_Compatibilidad` int(11) NOT NULL,
  `Tipo_de_Movimiento` tinyint(4) NOT NULL,
  `Temporalidad_INC` date NOT NULL,
  `Temporalidad_FIN` date NOT NULL,
  `Plaza_Activa` varchar(30) NOT NULL,
  `Ciudad` varchar(100) NOT NULL,
  `Fecha_de_Creacion` date NOT NULL,
  `Ubicacion` varchar(255) NOT NULL,
  `Horario` varchar(100) NOT NULL,
  `Tiempo_de_Traslado` varchar(60) NOT NULL,
  `ID_Trabajador` int(11) NOT NULL,
  `Clave_Presupuestal` varchar(30) NOT NULL,
  `ID_Autoridad` int(11) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compatibilidad_puestos`
--

CREATE TABLE `compatibilidad_puestos` (
  `ID_Puesto` int(11) NOT NULL,
  `ID_Compatibilidad` int(11) NOT NULL,
  `Institucion_Num` tinyint(1) NOT NULL COMMENT '1=Institución 1, 2=Institución 2',
  `Puesto` varchar(255) NOT NULL,
  `Clave_Presupuestal` varchar(30) DEFAULT NULL,
  `Unidad_Adscripcion` varchar(255) DEFAULT NULL,
  `Tipo_Nombramiento` varchar(2) DEFAULT NULL,
  `Fecha_Alta` date DEFAULT NULL,
  `Fecha_Fin` date DEFAULT NULL,
  `Remuneracion` decimal(12,2) DEFAULT NULL,
  `Ubicacion` text DEFAULT NULL,
  `Horario` varchar(255) DEFAULT NULL,
  `Tiempo_Traslado` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plazas`
--

CREATE TABLE `plazas` (
  `Clave_Presupuestal` varchar(30) NOT NULL,
  `CD_Trabajo` varchar(100) NOT NULL,
  `Categoria` varchar(60) NOT NULL,
  `Des_Categoria` varchar(255) NOT NULL,
  `Salario` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `trabajadores`
--

CREATE TABLE `trabajadores` (
  `ID_Trabajador` int(11) NOT NULL,
  `apPaterno` varchar(60) NOT NULL,
  `apMaterno` varchar(60) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `RFC` varchar(13) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `autoridades`
--
ALTER TABLE `autoridades`
  ADD PRIMARY KEY (`ID_Autoridad`);

--
-- Indices de la tabla `compatibilidad`
--
ALTER TABLE `compatibilidad`
  ADD PRIMARY KEY (`ID_Compatibilidad`),
  ADD KEY `fk_comp_trabajador` (`ID_Trabajador`),
  ADD KEY `fk_comp_plaza` (`Clave_Presupuestal`),
  ADD KEY `fk_comp_autoridad` (`ID_Autoridad`);

--
-- Indices de la tabla `compatibilidad_puestos`
--
ALTER TABLE `compatibilidad_puestos`
  ADD PRIMARY KEY (`ID_Puesto`),
  ADD KEY `fk_puesto_compatibilidad` (`ID_Compatibilidad`);

--
-- Indices de la tabla `plazas`
--
ALTER TABLE `plazas`
  ADD PRIMARY KEY (`Clave_Presupuestal`);

--
-- Indices de la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  ADD PRIMARY KEY (`ID_Trabajador`),
  ADD UNIQUE KEY `RFC` (`RFC`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `autoridades`
--
ALTER TABLE `autoridades`
  MODIFY `ID_Autoridad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `compatibilidad`
--
ALTER TABLE `compatibilidad`
  MODIFY `ID_Compatibilidad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `compatibilidad_puestos`
--
ALTER TABLE `compatibilidad_puestos`
  MODIFY `ID_Puesto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `trabajadores`
--
ALTER TABLE `trabajadores`
  MODIFY `ID_Trabajador` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `compatibilidad`
--
ALTER TABLE `compatibilidad`
  ADD CONSTRAINT `fk_comp_autoridad` FOREIGN KEY (`ID_Autoridad`) REFERENCES `autoridades` (`ID_Autoridad`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comp_plaza` FOREIGN KEY (`Clave_Presupuestal`) REFERENCES `plazas` (`Clave_Presupuestal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comp_trabajador` FOREIGN KEY (`ID_Trabajador`) REFERENCES `trabajadores` (`ID_Trabajador`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `compatibilidad_puestos`
--
ALTER TABLE `compatibilidad_puestos`
  ADD CONSTRAINT `fk_puesto_compatibilidad` FOREIGN KEY (`ID_Compatibilidad`) REFERENCES `compatibilidad` (`ID_Compatibilidad`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
