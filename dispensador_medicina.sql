-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:33066
-- Tiempo de generación: 09-02-2026 a las 01:21:54
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `dispensador_medicina`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas`
--

CREATE TABLE `alertas` (
  `id_alerta` int(11) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('leida','no_leida') DEFAULT 'no_leida'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compartimentos`
--

CREATE TABLE `compartimentos` (
  `id_compartimento` int(11) NOT NULL,
  `id_medicamento` int(11) NOT NULL,
  `capacidad_maxima` int(11) NOT NULL,
  `cantidad_actual` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_dispositivo`
--

CREATE TABLE `configuracion_dispositivo` (
  `id_configuracion` int(11) NOT NULL,
  `nombre_dispositivo` varchar(100) DEFAULT NULL,
  `estado` enum('conectado','desconectado') DEFAULT 'desconectado',
  `ultimo_ping` datetime DEFAULT NULL,
  `modo` enum('automatico','manual') DEFAULT 'automatico'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_dispenso`
--

CREATE TABLE `historial_dispenso` (
  `id_historial` int(11) NOT NULL,
  `id_programacion` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `resultado` enum('exitoso','error') NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicamentos`
--

CREATE TABLE `medicamentos` (
  `id_medicamento` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `dosis` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `cantidad_total` int(11) NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `id_tipo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programacion`
--

CREATE TABLE `programacion` (
  `id_programacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_medicamento` int(11) NOT NULL,
  `hora_dispenso` time NOT NULL,
  `frecuencia` varchar(50) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_medicamento`
--

CREATE TABLE `tipos_medicamento` (
  `id_tipo` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('admin','cuidador','paciente') NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `usuario`, `contrasena`, `rol`, `estado`, `fecha_creacion`) VALUES
(1, 'admin', 'admin', '$2y$10$4a/o.VktrQUQCz1kzlAqqOdhzbXdW9fJLl6I53s5965A.zZrKGCHe', 'admin', 'activo', '2026-01-14 00:52:12'),
(7, 'paciente', 'paciente', '$2y$10$Ockdr8xhP8hxpZdQNX19tuKHIm0pHoovrZORqU.kqPiVdSqlrnJYy', 'paciente', 'activo', '2026-02-09 00:18:19'),
(8, 'cuidador', 'cuidador', '$2y$10$.GRnU46u/x5B7fOg2RfUZusraFaWzpPi11em9HxQtYyWBUjdBXbWa', 'cuidador', 'activo', '2026-02-09 00:18:19');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`id_alerta`);

--
-- Indices de la tabla `compartimentos`
--
ALTER TABLE `compartimentos`
  ADD PRIMARY KEY (`id_compartimento`),
  ADD KEY `id_medicamento` (`id_medicamento`);

--
-- Indices de la tabla `configuracion_dispositivo`
--
ALTER TABLE `configuracion_dispositivo`
  ADD PRIMARY KEY (`id_configuracion`);

--
-- Indices de la tabla `historial_dispenso`
--
ALTER TABLE `historial_dispenso`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_programacion` (`id_programacion`);

--
-- Indices de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  ADD PRIMARY KEY (`id_medicamento`),
  ADD KEY `id_tipo` (`id_tipo`);

--
-- Indices de la tabla `programacion`
--
ALTER TABLE `programacion`
  ADD PRIMARY KEY (`id_programacion`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_medicamento` (`id_medicamento`);

--
-- Indices de la tabla `tipos_medicamento`
--
ALTER TABLE `tipos_medicamento`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alertas`
--
ALTER TABLE `alertas`
  MODIFY `id_alerta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `compartimentos`
--
ALTER TABLE `compartimentos`
  MODIFY `id_compartimento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_dispositivo`
--
ALTER TABLE `configuracion_dispositivo`
  MODIFY `id_configuracion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_dispenso`
--
ALTER TABLE `historial_dispenso`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  MODIFY `id_medicamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `programacion`
--
ALTER TABLE `programacion`
  MODIFY `id_programacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_medicamento`
--
ALTER TABLE `tipos_medicamento`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `compartimentos`
--
ALTER TABLE `compartimentos`
  ADD CONSTRAINT `compartimentos_ibfk_1` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamentos` (`id_medicamento`);

--
-- Filtros para la tabla `historial_dispenso`
--
ALTER TABLE `historial_dispenso`
  ADD CONSTRAINT `historial_dispenso_ibfk_1` FOREIGN KEY (`id_programacion`) REFERENCES `programacion` (`id_programacion`);

--
-- Filtros para la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  ADD CONSTRAINT `medicamentos_ibfk_1` FOREIGN KEY (`id_tipo`) REFERENCES `tipos_medicamento` (`id_tipo`);

--
-- Filtros para la tabla `programacion`
--
ALTER TABLE `programacion`
  ADD CONSTRAINT `programacion_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `programacion_ibfk_2` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamentos` (`id_medicamento`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
