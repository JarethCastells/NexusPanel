# NexusPanel - Plan de Pruebas Caja Blanca y Caja Negra

## 1) Objetivo
Validar seguridad, integridad de datos, control de carga y funcionalidad del flujo de pedidos/tracking.

## 2) Alcance
- Login (`index.php`): throttling por IP/correo, captcha opcional.
- API pedidos (`api/pedido.php`): transacciones, tracking y enlaces compartidos revocables.
- Cliente (`pages/mis_pedidos.php`): compartir/revocar viaje.
- Tracking público (`pages/tracking_publico.php`).

## 3) Pruebas de Caja Blanca (conociendo código)

### WB-01: ACID en aceptar pedido
- Archivo: `api/pedido.php`, acción `aceptar`.
- Verificación: `UPDATE pedidos` + `INSERT chat` están dentro de transacción.
- Resultado esperado: si falla el `INSERT chat`, se revierte el `UPDATE` de pedido.

### WB-02: ACID en iniciar viaje
- Archivo: `api/pedido.php`, acción `iniciar_viaje`.
- Verificación: transacción activa y rollback en error.
- Resultado esperado: no queda estado `en_camino` sin mensaje de chat.

### WB-03: ACID en entregar pedido
- Archivo: `api/pedido.php`, acción `entregar`.
- Verificación: transacción activa y rollback en error.
- Resultado esperado: no queda `entregado` parcial.

### WB-04: ACID en update_tracking
- Archivo: `api/pedido.php`, acción `update_tracking`.
- Verificación: `INSERT tracking` + `UPDATE usuarios` transaccional.
- Resultado esperado: no se inserta tracking sin actualizar última posición del operador.

### WB-05: Revocación de links
- Archivo: `api/pedido.php`, tabla `tracking_shares`.
- Verificación: `revocar_link_tracking` marca `revoked_at` y `tracking_publico` rechaza token.
- Resultado esperado: link compartido deja de funcionar de inmediato.

### WB-06: Throttling login
- Archivo: `includes/security.php` + `index.php`.
- Verificación: tabla `login_throttles`, ventana y bloqueo temporal.
- Resultado esperado: se responde con tiempo de espera cuando excede límite.

### WB-07: Captcha login (opcional por config)
- Archivo: `includes/security.php` + `index.php`.
- Verificación: si hay llaves Turnstile, se valida token en servidor.
- Resultado esperado: login rechazado si captcha no es válido.

## 4) Pruebas de Caja Negra (sin ver implementación)

### BB-01: Login correcto
- Dado usuario válido y captcha válido (si está activo).
- Cuando inicia sesión.
- Entonces entra al panel según rol.

### BB-02: Flood de login por IP
- Dado múltiples intentos rápidos desde misma IP.
- Cuando supera límite.
- Entonces recibe mensaje de espera y no procesa login.

### BB-03: Flood por correo
- Dado múltiples intentos contra un mismo correo.
- Cuando supera límite.
- Entonces bloqueo temporal con mensaje de espera.

### BB-04: Compartir viaje
- Dado pedido en curso.
- Cuando cliente pulsa `Compartir viaje`.
- Entonces se genera link público y abre WhatsApp con URL.

### BB-05: Revocar enlace
- Dado link activo ya compartido.
- Cuando cliente pulsa `Revocar enlace`.
- Entonces link previo muestra token inválido/expirado.

### BB-06: Tracking público sin coordenadas
- Dado pedido sin GPS del operador.
- Cuando se abre link.
- Entonces muestra estado “esperando ubicación”.

### BB-07: Tracking público con coordenadas
- Dado operador enviando tracking.
- Cuando se abre link.
- Entonces mapa actualiza posición y hora.

## 5) Evidencia recomendada
- Capturas de login bloqueado por throttling.
- Captura de link activo y luego revocado.
- Captura de transacciones rollback (log/error controlado).
- Video corto de tracking en tiempo real.

## 6) Criterios de Aceptación Global
- No hay actualizaciones parciales en acciones críticas de pedidos.
- Login resiste ráfagas de intentos con tiempo de espera.
- Enlaces públicos se pueden revocar de inmediato.
- Flujo de tracking y estados mantiene consistencia funcional.
