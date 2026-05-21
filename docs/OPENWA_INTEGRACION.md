# Integracion OpenWA

El modulo `whatsapp.php` ya esta conectado al panel de NexusPanel, pero depende de un servicio externo de OpenWA corriendo como API HTTP.

## Variables

Configurar en `.env`:

```env
OPENWA_BASE_URL=http://localhost:2785
OPENWA_API_KEY=dev-admin-key
```

## Estado actual

El archivo `whatsapp.php` consume estos endpoints:

```text
GET    /api/sessions
POST   /api/sessions
POST   /api/sessions/{id}/start
POST   /api/sessions/{id}/stop
POST   /api/sessions/{id}/logout
DELETE /api/sessions/{id}
GET    /api/sessions/{id}/qr
GET    /api/sessions/{id}/messages
POST   /api/sessions/{id}/messages/send-text
```

Si aparece `No se pudo conectar con OpenWA`, NexusPanel esta funcionando, pero falta levantar el backend OpenWA o ajustar `OPENWA_BASE_URL` a la URL correcta.

## Pendiente

El merge recibido traia `OpenWA` como submodulo de Git, pero sin `.gitmodules`, por lo que no incluyo la URL del repositorio backend. Se necesita que el responsable de WhatsApp comparta el repositorio/servicio OpenWA real o la URL donde ya esta corriendo.
