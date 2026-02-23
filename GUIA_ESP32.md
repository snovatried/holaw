# 🤖 Guía de Configuración ESP32 - Dispensador de Medicina

## 📋 Tabla de Contenidos
1. [Requisitos](#requisitos)
2. [Instalación de Librerías](#instalación-de-librerías)
3. [Configuración del Código](#configuración-del-código)
4. [Pines y Conexiones](#pines-y-conexiones)
5. [Carga del Código](#carga-del-código)
6. [Funcionamiento](#funcionamiento)
7. [Solución de Problemas](#solución-de-problemas)

---

## 📦 Requisitos

### Hardware
- **ESP32** (NodeMCU o similar)
- **2 Servomotores** (SG90, MG90S o similar)
- **Cable USB** para programación
- **Fuente de alimentación** 5V (para los servomotores)

### Software
- **Arduino IDE** 1.8.19 o superior
- **Drivers CH340 o CP2102** (según tu placa)

---

## 🔧 Instalación de Librerías

En Arduino IDE:

### 1. Agregar soporte para ESP32
- **Archivo** → **Preferencias**
- En "URLs de Gestor de tarjetas adicionales", agrega:
  ```
  https://dl.espressif.com/dl/package_esp32_index.json
  ```
- **Herramientas** → **Placa** → **Gestor de tarjetas**
- Busca "esp32" e instala la versión más reciente

### 2. Instalar librerías necesarias
- **Herramientas** → **Gestionar librerías**

Busca e instala:

| Librería | Versión | Autor |
|----------|---------|-------|
| `ESP32Servo` | Latest | Kevin Harrington |
| `ArduinoJson` | 6.19.4+ | Benoit Blanchon |
| `WiFi.h` | Incluida en ESP32 | - |
| `HTTPClient.h` | Incluida en ESP32 | - |

**O copiar en `libraries/` manualmente:**
```
C:\Users\[Usuario]\Documents\Arduino\libraries\
```

---

## ⚙️ Configuración del Código

### 1. Editar credenciales WiFi
En el archivo `.ino`, busca y actualiza:
```cpp
const char* ssid = "TU_SSID";              // Tu red WiFi
const char* password = "TU_PASSWORD";      // Tu contraseña
```

### 2. Configurar URLs del servidor
```cpp
const char* serverURL = "http://192.168.1.X/holaw/api/obtener_programacion.php";
const char* statusURL = "http://192.168.1.X/holaw/api/estado_dispositivo.php";
```

Reemplaza `192.168.1.X` con la **IP de tu PC** donde está corriendo XAMPP.

**Para encontrar tu IP:**
- **Windows**: `ipconfig` en cmd
- **Linux**: `ifconfig` o `ip addr`
- **Mac**: `ifconfig`

### 3. Configurar zona horaria (NTP)
```cpp
const long gmtOffset_sec = 0;              // UTC
const int daylightOffset_sec = 0;          // 0 = sin horario verano

// Ajustar según tu zona:
// América Lima (GMT-5):        gmtOffset_sec = -5 * 3600
// América CDMX (GMT-6):        gmtOffset_sec = -6 * 3600
// España (GMT+1):              gmtOffset_sec = 1 * 3600
```

---

## 🔌 Pines y Conexiones

### Conexión Servomotores

```
Servomotor 1 (SG90/MG90S)
├── Señal (Amarillo/Naranja) → GPIO 12 (SERVO1_PIN)
├── GND (Marrón) → GND
└── VCC (Rojo) → 5V

Servomotor 2 (SG90/MG90S)
├── Señal (Amarillo/Naranja) → GPIO 13 (SERVO2_PIN)
├── GND (Marrón) → GND
└── VCC (Rojo) → 5V
```

### Tabla de Pines ESP32 disponibles para servos:
```
GPIO12, GPIO13, GPIO14, GPIO15, GPIO16, GPIO17,
GPIO18, GPIO19, GPIO21, GPIO22, GPIO23, GPIO25,
GPIO26, GPIO27, GPIO32, GPIO33
```

---

## 📤 Carga del Código

### 1. Conectar ESP32 por USB
- Conecta la placa a tu PC con cable USB
- Espera a que se instale el driver (si es la primera vez)

### 2. Configurar Arduino IDE
- **Herramientas** → **Placa** → Selecciona `ESP32 Dev Module`
- **Herramientas** → **Puerto** → Selecciona el puerto COM (COMx en Windows, /dev/ttyUSBx en Linux)
- **Herramientas** → **Velocidad de subida** → `921600`

### 3. Compilar y cargar
- Copia el contenido de `ESP32_Dispensador.ino`
- Abre Arduino IDE y pega el código
- Haz clic en **Cargar** (o Ctrl+U)

Espera a que termine con el mensaje: `Leaving... Hard resetting via RTS pin`

---

## 🎯 Funcionamiento

### Inicio del Sistema
1. **Conexión WiFi**: El ESP32 se conecta a tu red
2. **Sincronización NTP**: Obtiene la hora exacta del servidor
3. **Obtención de Programación**: Descarga los dispensos programados
4. **Ciclo de Monitoreo**: Cada minuto verifica si es hora de dispensar

### Ciclo de Dispensado

```
┌─────────────────────────────────────────┐
│ Verificar si es hora de dispensar       │
└────────────┬────────────────────────────┘
             │
             ↓
┌─────────────────────────────────────────┐
│ PASO 1: Servomotor 1 → 90°              │
│         (Seleccionar medicamento)       │
└────────────┬────────────────────────────┘
             │ Espera 1 segundo
             ↓
┌─────────────────────────────────────────┐
│ PASO 2: Servomotor 1 → 0°               │
│         (Deseleccionar)                  │
└────────────┬────────────────────────────┘
             │ Espera 1 segundo
             ↓
┌─────────────────────────────────────────┐
│ PASO 3: Servomotor 2 → 90° por 3s       │
│         Para cada dosis (cantidad)      │
│         Luego: Servomotor 2 → 0°        │
└────────────┬────────────────────────────┘
             │
             ↓
┌─────────────────────────────────────────┐
│ Registrar dispensado en servidor        │
│ (Actualizar historial)                  │
└─────────────────────────────────────────┘
```

### Monitoreo Serial
Abre **Herramientas** → **Monitor Serial** (velocidad: **115200**)

Verás logs como:
```
=== INICIANDO ESP32 DISPENSADOR DE MEDICINA ===

[SERVO] Servomotores inicializados
[WIFI] Conectando a: MiRedWiFi
[WIFI] ✓ Conectado
[WIFI] IP: 192.168.1.150
[NTP] Sincronizando hora con servidor NTP...
[NTP] ✓ Hora sincronizada: Mon Feb 17 14:30:45 2026
[API] Obteniendo programación del servidor...
[API] ✓ Datos recibidos
[PROG] ID: 1 - Hora: 14:30 - Cantidad: 2
[PROG] Total programaciones cargadas: 1

========== HORA DE DISPENSAR ==========
Programación ID: 1
Hora: 14:30
Cantidad: 2
========================================

[DISPENSA] Iniciando ciclo de dispensado...
[PASO 1] Servo 1 → 90°
[PASO 2] Servo 1 → 0°
[PASO 3] Servo 2 dispensando dosis 1/2
[PASO 3] Servo 2 dispensando dosis 2/2
[DISPENSA] ✓ Ciclo completado
[REGISTRO] Enviando registro de dispensado al servidor...
[REGISTRO] ✓ Dispensado registrado
```

---

## 🔄 Ciclo de Actualización

- **Cada 1 minuto**: Verifica si es hora de dispensar
- **Cada 1 hora**: Sincroniza la hora con NTP
- **Al cambiar de día** (00:00): Reinicia contadores de dispensado
- **Continuamente**: Verifica conexión WiFi

---

## 📡 APIs Esperadas del Servidor

### 1. `/api/obtener_programacion.php` (GET)
**Respuesta esperada (JSON):**
```json
{
  "programaciones": [
    {
      "id_programacion": 1,
      "hora_dispenso": "14:30",
      "cantidad": 2,
      "estado": "activo"
    },
    {
      "id_programacion": 2,
      "hora_dispenso": "20:15",
      "cantidad": 1,
      "estado": "activo"
    }
  ]
}
```

### 2. `/api/registrar_dispenso.php` (POST)
**Parámetros:**
- `id_programacion`: int
- `resultado`: "exitoso" o "error"

---

## ❌ Solución de Problemas

### **Problema: No se conecta a WiFi**
```
✓ Verifica SSID y contraseña sean correctos
✓ El ESP32 está en rango del router
✓ WiFi no usa frecuencia de 5GHz (ESP32 solo 2.4GHz)
✓ Reinicia el ESP32 con el botón RESET
```

### **Problema: No sincroniza hora NTP**
```
✓ Verifica que el WiFi esté conectado primero
✓ Prueba con otro servidor NTP: "time.nist.gov"
✓ Verifica zona horaria configurada
```

### **Problema: No obtiene programación del servidor**
```
✓ Verifica la IP del servidor (prueba en navegador)
✓ El servidor debe estar ejecutándose (XAMPP activo)
✓ Verifica firewall no bloquea conexión
✓ Intenta con: ping 192.168.1.X desde terminal
```

### **Problema: Servomotores no se mueven**
```
✓ Verifica conexión física (signal, GND, VCC)
✓ GPIO12 y GPIO13 son correctos
✓ Alimentación: 5V con suficiente amperaje
✓ Prueba con código simple:
```

**Código de prueba servo:**
```cpp
#include <ESP32Servo.h>

Servo servo;
void setup() {
  servo.attach(12);  // GPIO12
}
void loop() {
  servo.write(0);
  delay(1000);
  servo.write(90);
  delay(1000);
}
```

### **Problema: Compilación falla**
```
✓ Verifica "core_version_mismatch" o librerías faltantes
✓ Borra la carpeta: C:\Users\[Usuario]\AppData\Local\Arduino15\
✓ Reinstala ESP32 desde gestor de tarjetas
✓ Usa versión estable de Arduino IDE 1.8.19
```

---

## 🚀 Próximas Características (Opcional)

- [ ] Sensor ultrasónico para detectar nivel de medicamento
- [ ] Pantalla OLED para mostrar estado
- [ ] Botones físicos para modo manual
- [ ] MQTT para comunicación más robusta
- [ ] Calibración automática de servomotores
- [ ] Alertas en caso de fallo de dispensado

---

## 📝 Notas Importantes

1. **Alimentación**: Los servomotores pueden requerir fuente aparte si la alimentación USB es insuficiente
2. **Precisión de hora**: La hora depende de sincronización NTP (necesita internet)
3. **Tolerancia de tiempo**: El ESP32 verifica cada minuto, así que dispensará cuando coincida la hora
4. **Seguridad**: Considera agregar autenticación en las APIs si es acceso remoto
5. **Reinicio**: Si WiFi falla, el ESP32 se reconecta automáticamente

---

## ✅ Checklist de Implementación

- [ ] Arduino IDE instalado
- [ ] ESP32 soporte agregado
- [ ] Librerías necesarias instaladas
- [ ] Código descargado y editado
- [ ] WiFi configurada
- [ ] URLs del servidor correctas
- [ ] Pines de servos conectados
- [ ] Código cargado en ESP32
- [ ] Monitor Serial abierto en 115200 bps
- [ ] WiFi conectado ✓
- [ ] Hora sincronizada ✓
- [ ] Programación cargada ✓
- [ ] Prueba de dispensado exitosa ✓

---

## 📞 Soporte

Para más detalles sobre librerías específicas:
- **ESP32Servo**: https://github.com/Kevin-Harrington/ESP32Servo
- **ArduinoJson**: https://arduinojson.org/
- **ESP32 Docs**: https://docs.espressif.com/projects/esp-idf/en/latest/esp32/

