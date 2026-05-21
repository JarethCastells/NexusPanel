# NexusPanel — Sistema Phibro

## Instalación rápida
```bash
cd login-demo
php -S localhost:8080
# Abrir http://localhost:8080
```

## Usuarios de demo
| Email | Contraseña | Rol |
|-------|-----------|-----|
| admin@demo.com | admin123 | Administrador |
| inventario@demo.com | inventario123 | Inventario / Coordinacion |
| operador@demo.com | operador123 | Operador (CDMX, radio 15km) |
| operador2@demo.com | operador123 | Operador (GDL, radio 15km) |
| cliente@demo.com | cliente123 | Cliente |

## Flujo de uso

### Cliente
1. Login → **Tienda**: busca y agrega productos al carrito
2. Checkout → elige dirección y método de pago → pedido creado
3. **Mis Pedidos**: ve estado en tiempo real
4. Cuando operador acepta → aparece **chat**
5. Cuando operador inicia viaje → aparece **mapa con pin en tiempo real**

### Operador  
1. Login → **Mis Entregas** → Tab "Pedidos en mi zona"
2. Ve solo pedidos dentro de su radio geográfico (con distancia en km)
3. Presiona **"Tomar este pedido"**
4. Chatear con el cliente
5. **"Iniciar viaje"** → GPS se comparte automáticamente al cliente
6. **"Marcar entregado"** → cierra el pedido

### Administrador
- Dashboard global, gestión de usuarios, mapa de todos los usuarios
