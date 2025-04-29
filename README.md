Documentación del Sistema Totem Logs
📌 Descripción General
Este proyecto incluye:

Una API REST para gestionar registros de transacciones de totem

Un Dashboard para visualización de datos

Un sistema de conexión a base de datos seguro con patrón Singleton

🌐 Acceso al Dashboard
Requisitos previos
Servidor web (Apache, Nginx, etc.)

PHP 7.4 o superior

Acceso a la base de datos configurada

Pasos para acceder:
Colocar todos los archivos en el directorio público de tu servidor web

Asegurarse que la variable $api_url en dashboard.php apunte correctamente a tu API

Acceder mediante navegador a:

http://tudominio.com/dashboard.php
Características del Dashboard:
Visualización tabular de todos los registros

Búsqueda por ID específico

Modal con detalles completos de cada registro

Diseño responsive y moderno

Actualización manual de datos

🚀 Consumo de la API
Endpoints disponibles:
1. Obtener todos los registros
Método: GET

URL: http://tudominio.com/api.php

Respuesta exitosa (200 OK):

json
{
  "success": true,
  "count": 15,
  "data": [
    {
      "id": 1,
      "rut": "12345678-9",
      "origen": "Santiago",
      "...": "..."
    }
  ]
}
2. Obtener un registro específico
Método: GET

URL: http://tudominio.com/api.php?id=1

Respuesta exitosa (200 OK):

json
{
  "success": true,
  "data": {
    "id": 1,
    "rut": "12345678-9",
    "...": "..."
  }
}
Posibles errores:

400 Bad Request (ID no válido)

404 Not Found (Registro no existe)

3. Crear nuevo registro (POST)
Método: POST

URL: http://tudominio.com/api.php

Cabeceras:

Content-Type: application/json
Cuerpo de ejemplo:

json
{
  "rut": "18765432-5",
  "origen": "Viña del Mar",
  "destino": "Santiago",
  "fecha_viaje": "2025-05-10",
  "hora_viaje": "14:45:00",
  "asiento": "22C",
  "codigo_reserva": "RES2025V",
  "codigo_venta": "VEN2025S",
  "codigo_confirmacion": "CONF2025X",
  "estado_transaccion": "confirmada"
}
Respuesta exitosa (201 Created):

json
{
  "success": true,
  "id": 16,
  "message": "Registro creado exitosamente",
  "data": { ... }
}
Ejemplos de consumo:
Con cURL (terminal):
bash
# Obtener todos los registros
curl -X GET http://localhost/api.php

# Obtener un registro específico
curl -X GET http://localhost/api.php?id=1

# Crear nuevo registro
curl -X POST -H "Content-Type: application/json" -d '{
  "rut": "18765432-5",
  "origen": "Viña del Mar",
  "...": "..."
}' http://localhost/api.php
Con JavaScript (fetch):
javascript
// Obtener todos los registros
fetch('http://localhost/api.php')
  .then(response => response.json())
  .then(data => console.log(data));

// Crear nuevo registro
fetch('http://localhost/api.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    rut: "18765432-5",
    origen: "Viña del Mar",
    // ... otros campos
  })
})
.then(response => response.json())
.then(data => console.log(data));
⚙️ Configuración de Base de Datos
Editar el archivo config/db.php con tus credenciales:

php
$host = 'tu_servidor';
$db   = 'tu_base_de_datos';
$user = 'tu_usuario';
$pass = 'tu_contraseña';
Para desarrollo, puedes usar db-example.php como plantilla

🔒 Seguridad
La API incluye CORS configurado para permitir solicitudes desde cualquier origen (en desarrollo)

Se recomienda implementar autenticación para entornos de producción

Usa siempre HTTPS en producción

🛠️ Soporte
Para problemas o preguntas, contactar al administrador del sistema.