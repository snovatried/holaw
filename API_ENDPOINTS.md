# ⚙️ Endpoints API Requeridos para ESP32

El ESP32 necesita que los siguientes endpoints estén disponibles en tu servidor PHP.

---

## 📋 Endpoint 1: Obtener Programación

**URL:** `http://Tu-IP/holaw/api/obtener_programacion.php`

**Método:** GET

**Descripción:** El ESP32 obtiene todos los medicamentos programados para el usuario/dispositivo

### Respuesta esperada (JSON)

```json
{
  "programaciones": [
    {
      "id_programacion": 1,
      "id_medicamento": 5,
      "nombre_medicamento": "Ibuprofen 400mg",
      "hora_dispenso": "08:00",
      "frecuencia": "diaria",
      "cantidad": 1,
      "estado": "activo"
    },
    {
      "id_programacion": 2,
      "id_medicamento": 7,
      "nombre_medicamento": "Aspirina 100mg",
      "hora_dispenso": "14:30",
      "frecuencia": "diaria",
      "cantidad": 2,
      "estado": "activo"
    },
    {
      "id_programacion": 3,
      "id_medicamento": 10,
      "nombre_medicamento": "Vitamina D",
      "hora_dispenso": "20:00",
      "frecuencia": "diaria",
      "cantidad": 1,
      "estado": "inactivo"
    }
  ]
}
```

### Código PHP necesario

```php
<?php
header('Content-Type: application/json; charset=utf-8');
require '../config/conexion.php';

try {
    // Obtener todas las programaciones activas
    $sql = "SELECT 
            p.id_programacion,
            p.id_medicamento,
            m.nombre,
            p.hora_dispenso,
            p.frecuencia,
            p.cantidad,
            p.estado
        FROM programacion p
        INNER JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
        WHERE p.estado = 'activo'
        ORDER BY p.hora_dispenso ASC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $programaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'programaciones' => $programaciones
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'mensaje' => 'Error al obtener programacion: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
```

---

## ✅ Endpoint 2: Registrar Dispensado

**URL:** `http://Tu-IP/holaw/api/registrar_dispenso.php`

**Método:** POST

**Descripción:** El ESP32 registra cuando dispensó un medicamento

### Parámetros POST requeridos

```
id_programacion = 1         (ID de la programación)
resultado       = exitoso   (exitoso o error)
```

### Respuesta esperada (JSON)

```json
{
  "status": "success",
  "mensaje": "Dispensado registrado correctamente",
  "id_historial": 42
}
```

### Código PHP necesario

```php
<?php
header('Content-Type: application/json; charset=utf-8');
require '../config/conexion.php';

try {
    // Validar parámetros
    if (!isset($_POST['id_programacion']) || !isset($_POST['resultado'])) {
        throw new Exception('Parámetros requeridos faltantes');
    }
    
    $id_programacion = intval($_POST['id_programacion']);
    $resultado = $_POST['resultado']; // 'exitoso' o 'error'
    
    // Validar resultado
    if (!in_array($resultado, ['exitoso', 'error'])) {
        throw new Exception('Resultado inválido');
    }
    
    // Obtener programación
    $sql = "SELECT id_usuario, id_medicamento FROM programacion WHERE id_programacion = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_programacion]);
    $programacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$programacion) {
        throw new Exception('Programación no encontrada');
    }
    
    // Registrar en historial
    $sql = "INSERT INTO historial_dispenso 
            (id_programacion, fecha, hora, resultado, observaciones)
            VALUES (?, CURDATE(), CURTIME(), ?, 'Dispensado por ESP32')";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$id_programacion, $resultado]);
    
    $id_historial = $conexion->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'mensaje' => 'Dispensado registrado correctamente',
        'id_historial' => $id_historial
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
```

---

## 🔌 Endpoint 3: Estado del Dispositivo (Opcional)

**URL:** `http://Tu-IP/holaw/api/estado_dispositivo.php`

**Método:** POST

**Descripción:** El ESP32 envía su estado actual al servidor (opcional, para monitoreo)

### Parámetros POST

```
estado      = conectado     (conectado o desconectado)
dispensos   = 45            (números de dispensos realizados)
ip_esp32    = 192.168.1.150 (IP del dispositivo)
```

### Código PHP necesario

```php
<?php
header('Content-Type: application/json; charset=utf-8');
require '../config/conexion.php';

try {
    if (!isset($_POST['estado'])) {
        throw new Exception('Estado requerido');
    }
    
    $estado = $_POST['estado'];
    $dispensos = isset($_POST['dispensos']) ? intval($_POST['dispensos']) : 0;
    $ip_esp32 = $_POST['ip_esp32'] ?? $_SERVER['REMOTE_ADDR'];
    
    // Actualizar o insertar configuración
    $sql = "UPDATE configuracion_dispositivo 
            SET estado = ?, 
                ultimo_ping = NOW()
            WHERE id_configuracion = 1";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$estado]);
    
    echo json_encode([
        'status' => 'success',
        'mensaje' => 'Estado actualizado'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
```

---

## 🔧 Verificación de Endpoints

### Opción 1: Usar Postman o similar

1. Abre Postman
2. Crea nueva request GET
3. URL: `http://192.168.1.X/holaw/api/obtener_programacion.php`
4. Click Send
5. Verifica que devuelva JSON válido

### Opción 2: Usar cURL en terminal

```bash
# Obtener programación
curl -X GET "http://192.168.1.X/holaw/api/obtener_programacion.php"

# Registrar dispensado
curl -X POST "http://192.168.1.X/holaw/api/registrar_dispenso.php" \
  -d "id_programacion=1&resultado=exitoso"
```

### Opción 3: Abrir en navegador

- Abre: `http://192.168.1.X/holaw/api/obtener_programacion.php`
- Debería ver JSON con las programaciones

---

## ⚠️ Errores Comunes

### Error 404 - Archivo no encontrado
```
✓ Verifica ruta correcta en Arduino
✓ Asegúrate que el archivo exista en el servidor
✓ Verifica que Apache/PHP esté corriendo
```

### Error 500 - Error interno del servidor
```
✓ Revisa logs en error_log
✓ Verifica permisos de base de datos
✓ Comprueba conexión PDO en config/conexion.php
```

### JSON no válido
```
✓ Asegúrate header Content-Type: application/json
✓ Escapa caracteres especiales con JSON_UNESCAPED_UNICODE
✓ Valida sintaxis JSON en https://jsonlint.com/
```

### Arduino recibe error 0 (no se conecta)
```
✓ Verifica IP correcta
✓ Prueba ping desde terminal al servidor
✓ Revisa firewall bloquea puerto 80
✓ Asegura WiFi está conectada
```

---

## 📊 Flujo Completo

```
1. ESP32 se conecta a WiFi
   ↓
2. ESP32 obtiene hora via NTP
   ↓
3. ESP32 hace GET a /api/obtener_programacion.php
   ↓ Servidor retorna JSON con horas
   ↓
4. ESP32 verifica si es hora cada minuto
   ↓
5. CUANDO es hora → Ejecuta ciclo de dispensado
   ↓
6. ESP32 hace POST a /api/registrar_dispenso.php
   ↓ Servidor registra en historial_dispenso
   ↓
7. Servidor retorna success con ID historial
   ↓
8. Vuelve a paso 4 (ciclo continuo)
```

---

## 📝 Configuración de Base de Datos

Asegúrate que la BD tenga las tablas necesarias:

```sql
-- Ya incluidas en dispensador_medicina.sql

-- Tabla programacion (línea de programación de medicamentos)
CREATE TABLE `programacion` (
  `id_programacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_medicamento` int(11) NOT NULL,
  `hora_dispenso` time NOT NULL,
  `frecuencia` varchar(50),
  `cantidad` int(11) NOT NULL,
  `estado` enum('activo','inactivo'),
  PRIMARY KEY (`id_programacion`),
  FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  FOREIGN KEY (`id_medicamento`) REFERENCES `medicamentos` (`id_medicamento`)
);

-- Tabla historial_dispenso (registro de cada dispensado)
CREATE TABLE `historial_dispenso` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_programacion` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `resultado` enum('exitoso','error') NOT NULL,
  `observaciones` text,
  PRIMARY KEY (`id_historial`),
  FOREIGN KEY (`id_programacion`) REFERENCES `programacion` (`id_programacion`)
);

-- Tabla configuracion_dispositivo
CREATE TABLE `configuracion_dispositivo` (
  `id_configuracion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_dispositivo` varchar(100),
  `estado` enum('conectado','desconectado'),
  `ultimo_ping` datetime,
  `modo` enum('automatico','manual'),
  PRIMARY KEY (`id_configuracion`)
);
```

---

## 🚀 Próximos Pasos

1. ✅ Crear archivos .php según especificación
2. ✅ Colocar en carpeta `api/` 
3. ✅ Probar endpoints con Postman/cURL
4. ✅ Cargar código ESP32
5. ✅ Verificar conexión en Monitor Serial
6. ✅ Programar medicamentos en web
7. ✅ Esperar a que ESP32 dispense automáticamente

---

## 🔐 Recomendaciones de Seguridad

```php
// Agregar validación de token/API Key
if ($_GET['api_key'] !== 'TU_API_KEY_SECRETA') {
    die(json_encode(['error' => 'Acceso denegado']));
}

// Agregar rate limiting
// Validar input/output
// Usar prepared statements (ya lo hacemos con PDO)
// Registrar logs de dispensos importantes
```

