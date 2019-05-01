CREATE TABLE `historial` (
  `id` SERIAL PRIMARY KEY,
  `id_usuario` INT(11) NOT NULL REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  `id_registro` BIGINT(20) NOT NULL,
  `tabla` VARCHAR(10) NOT NULL,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,   
  `accion` FLOAT(10) NOT NULL COMMENT '0 crear, 1 actualizar'
) ENGINE=InnoDB DEFAULT CHARSET utf8 COLLATE utf8_spanish2_ci;