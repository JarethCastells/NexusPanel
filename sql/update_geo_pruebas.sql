-- FEAT-GEO-01
-- Coordenadas de prueba: Ojo de Agua, Tecamac, Edo. Mex.
-- lat 19.7000, lng -98.9800

START TRANSACTION;

-- Operadores de prueba
UPDATE usuarios
SET lat = 19.7000, lng = -98.9800
WHERE rol = 'operador'
  AND (
    email IN ('operador@demo.com', 'operador2@demo.com')
    OR nombre IN ('Carlos Lopez', 'Carlos López', 'Ana Martinez', 'Ana Martínez')
  );

-- Clientes de prueba
UPDATE usuarios
SET lat = 19.7000, lng = -98.9800
WHERE rol = 'cliente'
  AND (
    email IN ('cliente@demo.com')
    OR nombre IN ('Juan Cliente', 'JuanCliente')
  );

-- Pedidos del cliente de prueba (si existen)
UPDATE pedidos p
JOIN usuarios c ON c.id = p.cliente_id
SET p.lat_entrega = 19.7000,
    p.lng_entrega = -98.9800
WHERE c.rol = 'cliente'
  AND (
    c.email IN ('cliente@demo.com')
    OR c.nombre IN ('Juan Cliente', 'JuanCliente')
  );

COMMIT;

-- Verificacion rapida
SELECT id, nombre, email, rol, lat, lng
FROM usuarios
WHERE email IN ('operador@demo.com', 'operador2@demo.com', 'cliente@demo.com')
ORDER BY rol, id;
