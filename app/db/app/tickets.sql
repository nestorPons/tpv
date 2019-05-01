CREATE TABLE `tickets` (
  `id` SERIAL PRIMARY KEY,
  `id_empleado` INT(11) NOT NULL REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  `id_cliente` INT(11) NOT NULL REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  `estado` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '1 activo, 0 inactivo'
) ENGINE=InnoDB DEFAULT CHARSET utf8 COLLATE utf8_spanish2_ci;