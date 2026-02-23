#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <ESP32Servo.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <time.h>

// ==================== CONFIGURACIÓN WIFI ====================
const char* ssid = "NETLIFE-cuemajimenezp1";              // Cambiar por tu red WiFi
const char* password = "0101463974";      // Cambiar por tu contraseña WiFi

// ==================== CONFIGURACIÓN SUPABASE REST ====================
const char* apiBaseURL = "https://dnruwqzysjjfjpvdnfvf.supabase.co";
#ifndef SUPABASE_ANON_KEY
#define SUPABASE_ANON_KEY "<SECRET>"
#endif
const char* supabaseAnonKey = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRucnV3cXp5c2pqZmpwdmRuZnZmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MTM2NzQwMiwiZXhwIjoyMDg2OTQzNDAyfQ.sxUcnU9z-rVRKmJFLZaICzW_JEAcUa0nSDzr0Bim_YI";
const char* supabaseHost = "dnruwqzysjjfjpvdnfvf.supabase.co";
const char* endpointProgramacion = "/rest/v1/programacion?select=id_programacion,hora_dispenso,cantidad,estado&estado=eq.activo&order=hora_dispenso.asc";
const char* endpointRegistro = "/rest/v1/historial_dispenso";
const char* endpointEstado = "/rest/v1/configuracion_dispositivo?id_configuracion=eq.1";

// ==================== CONFIGURACIÓN NTP (Reloj) ====================
const char* ntpServer = "pool.ntp.org";
const char* tzInfo = "ECT5";              // Ecuador (UTC-5). Cambiar según tu zona.
const long gmtOffset_sec = -5 * 3600;      // Fallback en segundos
const int daylightOffset_sec = 0;          // Cambiar si usas horario de verano

// ==================== PINES SERVOMOTORES ====================
const int SERVO1_PIN = 23;  // GPIO23 para Servomotor 1
const int SERVO2_PIN = 19;  // GPIO19 para Servomotor 2
const int SERVO_FREQ_HZ = 50;
const int SERVO_MIN_US = 500;
const int SERVO_MAX_US = 2400;

// ==================== PINES I2C LCD ====================
const int I2C_SDA_PIN = 21;
const int I2C_SCL_PIN = 22;

// ==================== LCD I2C (20x4) ====================
// Nota: dirección común 0x27 (si no funciona, probar 0x3F)
LiquidCrystal_I2C lcd(0x27, 20, 4);

// ==================== OBJETOS SERVO ====================
Servo servo1;
Servo servo2;

// ==================== VARIABLES GLOBALES ====================
unsigned long lastSyncTime = 0;
const unsigned long SYNC_INTERVAL = 3600000;  // Sincronizar cada hora (ms)
const unsigned long CHECK_INTERVAL = 60000;   // Verificar programación cada minuto

unsigned long lastCheckTime = 0;
bool dispensoActivo = false;
int dispensosRealizados = 0;
bool mostrarRecogerPastilla = false;
unsigned long mensajeRecogerHasta = 0;
unsigned long lastLcdUpdate = 0;
String ultimaHoraMostrada = "";
unsigned long lastEstadoUpdate = 0;
const unsigned long STATUS_INTERVAL = 30000;

// Estructura para almacenar programación
struct Programacion {
  int id;
  int hora;
  int minuto;
  int cantidad;
  bool activo;
  bool dispensadoHoy;
};

Programacion programaciones[10];
int numProgramaciones = 0;

String buildApiUrl(const char* endpoint) {
  return String(apiBaseURL) + String(endpoint);
}

void addSupabaseHeaders(HTTPClient& http, bool jsonBody = false) {
  http.addHeader("apikey", supabaseAnonKey);
  http.addHeader("Authorization", String("Bearer ") + supabaseAnonKey);
  http.addHeader("Accept", "application/json");
  if (jsonBody) {
    http.addHeader("Content-Type", "application/json");
  }
}

bool verificarConexionSupabase() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[NET] WiFi no conectado");
    return false;
  }

  IPAddress ip;
  if (!WiFi.hostByName(supabaseHost, ip)) {
    Serial.println("[NET] Error DNS resolviendo Supabase");
    return false;
  }

  Serial.print("[NET] Supabase DNS: ");
  Serial.println(ip);

  WiFiClientSecure testClient;
  testClient.setInsecure();
  testClient.setTimeout(8000);

  if (!testClient.connect(supabaseHost, 443)) {
    Serial.println("[NET] No se pudo abrir socket TLS a Supabase:443");
    return false;
  }

  testClient.stop();
  return true;
}

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(2000);
  
  Serial.println("\n\n=== INICIANDO ESP32 DISPENSADOR DE MEDICINA ===\n");

  if (String(supabaseAnonKey) == "<SECRET>") {
    Serial.println("[CONFIG][ERROR] Debes configurar SUPABASE_ANON_KEY con tu clave real.");
  }
  
  // Inicializar servomotores (misma configuración que ESP32_Prueba_Servos)
  ESP32PWM::allocateTimer(0);
  ESP32PWM::allocateTimer(1);
  ESP32PWM::allocateTimer(2);
  ESP32PWM::allocateTimer(3);

  servo1.setPeriodHertz(SERVO_FREQ_HZ);
  servo2.setPeriodHertz(SERVO_FREQ_HZ);
  int ch1 = servo1.attach(SERVO1_PIN, SERVO_MIN_US, SERVO_MAX_US);
  int ch2 = servo2.attach(SERVO2_PIN, SERVO_MIN_US, SERVO_MAX_US);
  
  // Posición inicial
  servo1.write(0);
  servo2.write(0);
  delay(500);

  // Inicializar LCD
  Wire.begin(I2C_SDA_PIN, I2C_SCL_PIN);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Dispensador listo");
  lcd.setCursor(0, 1);
  lcd.print("Iniciando...");
  
  Serial.println("[SERVO] Servomotores inicializados");
  Serial.print("[SERVO] Canal servo1: ");
  Serial.println(ch1);
  Serial.print("[SERVO] Canal servo2: ");
  Serial.println(ch2);
  if (ch1 < 0 || ch2 < 0) {
    Serial.println("[SERVO][ERROR] No se pudo adjuntar un servo. Revisa pines y alimentación.");
  }
  
  // Conectar a WiFi
  conectarWiFi();
  
  // Sincronizar hora con NTP
  sincronizarHora();
  
  // Obtener configuración inicial
  obtenerProgramacion();

  // Enviar estado inicial del dispositivo
  enviarEstado();

  // Pintar hora inicial en LCD
  actualizarLCD();
  
  Serial.println("\n=== SISTEMA LISTO PARA DISPENSAR ===\n");
}

// ==================== LOOP PRINCIPAL ====================
void loop() {
  // Verificar conexión WiFi
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WIFI] Reconectando...");
    conectarWiFi();
  }
  
  // Sincronizar hora cada hora
  if (millis() - lastSyncTime > SYNC_INTERVAL) {
    sincronizarHora();
  }
  
  // Verificar y obtener programación cada minuto
  if (millis() - lastCheckTime > CHECK_INTERVAL) {
    obtenerProgramacion();
    lastCheckTime = millis();
  }
  
  // Verificar si es hora de dispensar
  verificarYDispensarMedicamentos();

  // Reportar estado a la API cada 30 segundos
  if (millis() - lastEstadoUpdate > STATUS_INTERVAL) {
    enviarEstado();
    lastEstadoUpdate = millis();
  }

  // Actualizar LCD (hora + mensaje)
  actualizarLCD();
  
  delay(1000);  // Esperar 1 segundo antes de siguiente iteración
}

// ==================== CONECTAR A WIFI ====================
void conectarWiFi() {
  Serial.print("[WIFI] Conectando a: ");
  Serial.println(ssid);
  
  WiFi.disconnect(true, true);
  delay(200);
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(ssid, password);
  
  int intentos = 0;
  while (WiFi.status() != WL_CONNECTED && intentos < 60) {
    delay(500);
    Serial.print(".");
    intentos++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WIFI] ✓ Conectado");
    Serial.print("[WIFI] IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("[WIFI] RSSI: ");
    Serial.println(WiFi.RSSI());
  } else {
    Serial.println("\n[WIFI] ✗ Error de conexión");
    Serial.print("[WIFI] status code: ");
    Serial.println((int)WiFi.status());
    Serial.println("[WIFI] Revisa SSID/clave, banda 2.4GHz y distancia al router");
  }
}

// ==================== SINCRONIZAR HORA NTP ====================
void sincronizarHora() {
  Serial.println("[NTP] Sincronizando hora con servidor NTP...");

  configTzTime(tzInfo, ntpServer);
  
  int intentos = 0;
  time_t ahora = time(nullptr);
  
  while (ahora < 24 * 3600 && intentos < 20) {
    delay(500);
    Serial.print(".");
    ahora = time(nullptr);
    intentos++;
  }
  
  Serial.println();
  
  if (ahora > 24 * 3600) {
    Serial.print("[NTP] ✓ Hora sincronizada: ");
    Serial.println(ctime(&ahora));
    lastSyncTime = millis();
  } else {
    Serial.println("[NTP] ✗ Error al sincronizar hora");
    configTime(gmtOffset_sec, daylightOffset_sec, ntpServer);
  }
}

// ==================== OBTENER PROGRAMACIÓN DEL SERVIDOR ====================
void obtenerProgramacion() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[API] Wifi desconectado");
    return;
  }

  if (!verificarConexionSupabase()) {
    Serial.println("[API] Sin conectividad con Supabase");
    return;
  }
  
  Serial.println("[API] Obteniendo programación del servidor...");
  
  HTTPClient http;
  WiFiClientSecure client;
  client.setInsecure();
  client.setTimeout(15000);
  http.begin(client, buildApiUrl(endpointProgramacion));
  http.setConnectTimeout(15000);
  http.setTimeout(20000);
  addSupabaseHeaders(http);
  
  int httpCode = http.GET();
  
  if (httpCode == HTTP_CODE_OK) {
    String payload = http.getString();
    Serial.println("[API] ✓ Datos recibidos");
    
    // Parsear JSON
    DynamicJsonDocument doc(2048);
    DeserializationError error = deserializeJson(doc, payload);
    
    if (!error) {
      numProgramaciones = 0;

      JsonArray programacionesArray;
      if (doc.is<JsonArray>()) {
        programacionesArray = doc.as<JsonArray>();
      } else {
        programacionesArray = doc["programaciones"].as<JsonArray>();
      }

      if (programacionesArray.isNull()) {
        Serial.println("[API] ⚠ No hay arreglo de programaciones en la respuesta");
        http.end();
        return;
      }
      
      for (JsonObject prog : programacionesArray) {
        if (numProgramaciones < 10) {
          String horaStr = prog["hora_dispenso"] | "";  // Formato: "14:30" o "14:30:00"
          if (horaStr.length() < 5) {
            continue;
          }
          
          // Parsear hora y minuto
          int sepPos = horaStr.indexOf(':');
          if (sepPos < 0) {
            continue;
          }
          int hora = horaStr.substring(0, sepPos).toInt();
          int minuto = horaStr.substring(sepPos + 1).toInt();
          
          programaciones[numProgramaciones].id = prog["id_programacion"] | 0;
          programaciones[numProgramaciones].hora = hora;
          programaciones[numProgramaciones].minuto = minuto;
          programaciones[numProgramaciones].cantidad = prog["cantidad"] | 1;
          String estado = prog["estado"] | "activo";
          programaciones[numProgramaciones].activo = (estado == "activo");
          programaciones[numProgramaciones].dispensadoHoy = false;
          
          Serial.print("[PROG] ID: ");
          Serial.print(programaciones[numProgramaciones].id);
          Serial.print(" - Hora: ");
          Serial.print(hora);
          Serial.print(":");
          if (minuto < 10) Serial.print("0");
          Serial.print(minuto);
          Serial.print(" - Cantidad: ");
          Serial.println(programaciones[numProgramaciones].cantidad);
          
          numProgramaciones++;
        }
      }
      
      Serial.print("[PROG] Total programaciones cargadas: ");
      Serial.println(numProgramaciones);
      
    } else {
      Serial.print("[API] ✗ Error JSON: ");
      Serial.println(error.f_str());
    }
  } else {
    Serial.print("[API] ✗ Error HTTP: ");
    Serial.println(httpCode);
    Serial.print("[API] Detalle: ");
    Serial.println(HTTPClient::errorToString(httpCode));
    String errorBody = http.getString();
    if (errorBody.length() > 0) {
      Serial.println(errorBody);
    }
  }
  
  http.end();
}

// ==================== VERIFICAR Y DISPENSAR MEDICAMENTOS ====================
void verificarYDispensarMedicamentos() {
  time_t ahora = time(nullptr);
  struct tm* timeinfo = localtime(&ahora);
  
  int horaActual = timeinfo->tm_hour;
  int minutoActual = timeinfo->tm_min;
  
  for (int i = 0; i < numProgramaciones; i++) {
    // Verificar si es hora de dispensar
    if (programaciones[i].activo && 
        !programaciones[i].dispensadoHoy && 
        programaciones[i].hora == horaActual && 
        programaciones[i].minuto == minutoActual) {
      
      Serial.println("\n========== HORA DE DISPENSAR ==========");
      Serial.print("Programación ID: ");
      Serial.println(programaciones[i].id);
      Serial.print("Hora: ");
      Serial.print(horaActual);
      Serial.print(":");
      if (minutoActual < 10) Serial.print("0");
      Serial.println(minutoActual);
      Serial.print("Cantidad: ");
      Serial.println(programaciones[i].cantidad);
      Serial.println("========================================\n");
      
      // Ejecutar ciclo de dispensado
      ejecutarDispensado(programaciones[i].cantidad, programaciones[i].id);
      
      // Marcar como dispensado hoy
      programaciones[i].dispensadoHoy = true;
      
      // Registrar en servidor
      registrarDispensado(programaciones[i].id);
    }
  }
  
  // Reiniciar contador al cambiar de día (00:00)
  if (horaActual == 0 && minutoActual == 0) {
    for (int i = 0; i < numProgramaciones; i++) {
      programaciones[i].dispensadoHoy = false;
    }
    Serial.println("[SISTEMA] Nuevo día, reiniciando contadores de dispensado");
  }
}

// ==================== EJECUTAR CICLO DE DISPENSADO ====================
void ejecutarDispensado(int cantidad, int idProgramacion) {
  Serial.println("[DISPENSA] Iniciando ciclo de dispensado...");
  dispensoActivo = true;
  
  // PASO 1: Servomotor 1 a 90°
  Serial.println("[PASO 1] Servo 1 → 90°");
  servo1.write(90);
  delay(1000);  // Esperar movimiento
  
  // PASO 2: Servomotor 1 a 0°
  Serial.println("[PASO 2] Servo 1 → 0°");
  servo1.write(0);
  delay(1000);  // Esperar movimiento
  
  // PASO 3: Servomotor 2 - Dispensar con cantidad
  for (int i = 0; i < cantidad; i++) {
    Serial.print("[PASO 3] Servo 2 dispensando dosis ");
    Serial.print(i + 1);
    Serial.print("/");
    Serial.println(cantidad);
    
    servo2.write(90);  // Abrir
    delay(3000);       // Esperar 3 segundos
    servo2.write(0);   // Cerrar
    delay(500);        // Pequeña pausa entre dosis
  }
  
  Serial.println("[DISPENSA] ✓ Ciclo completado");
  dispensoActivo = false;
  dispensosRealizados++;

  // Mostrar mensaje de retiro en LCD durante 30 segundos
  mostrarRecogerPastilla = true;
  mensajeRecogerHasta = millis() + 30000;
}

// ==================== REGISTRAR DISPENSADO EN SERVIDOR ====================
void registrarDispensado(int idProgramacion) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[REGISTRO] Wifi desconectado");
    return;
  }

  if (!verificarConexionSupabase()) {
    Serial.println("[REGISTRO] Sin conectividad con Supabase");
    return;
  }
  
  Serial.println("[REGISTRO] Enviando registro de dispensado al servidor...");
  
  HTTPClient http;
  WiFiClientSecure client;
  client.setInsecure();
  client.setTimeout(15000);
  http.begin(client, buildApiUrl(endpointRegistro));
  http.setConnectTimeout(15000);
  http.setTimeout(20000);
  addSupabaseHeaders(http, true);
  http.addHeader("Prefer", "return=minimal");

  String jsonBody = String("{") +
                    "\"id_programacion\":" + String(idProgramacion) + "," +
                    "\"fecha\":\"" + getFechaActual() + "\"," +
                    "\"hora\":\"" + getHoraActual() + "\"," +
                    "\"resultado\":\"exitoso\"," +
                    "\"observaciones\":\"Dispensado por ESP32\"" +
                    "}";

  int httpCode = http.POST(jsonBody);
  
  if (httpCode == HTTP_CODE_OK || httpCode == HTTP_CODE_CREATED || httpCode == HTTP_CODE_NO_CONTENT) {
    Serial.println("[REGISTRO] ✓ Dispensado registrado");
  } else {
    Serial.print("[REGISTRO] ✗ Error: ");
    Serial.println(httpCode);
    Serial.print("[REGISTRO] Detalle: ");
    Serial.println(HTTPClient::errorToString(httpCode));
    Serial.println(http.getString());
  }
  
  http.end();
}

// ==================== FUNCIONES AUXILIARES ====================

// Función para obtener hora actual formateada
String getHoraActual() {
  time_t ahora = time(nullptr);
  struct tm* timeinfo = localtime(&ahora);
  
  char buffer[20];
  sprintf(buffer, "%02d:%02d:%02d", timeinfo->tm_hour, timeinfo->tm_min, timeinfo->tm_sec);
  return String(buffer);
}

String getFechaActual() {
  time_t ahora = time(nullptr);
  struct tm* timeinfo = localtime(&ahora);

  char buffer[11];
  sprintf(buffer, "%04d-%02d-%02d", timeinfo->tm_year + 1900, timeinfo->tm_mon + 1, timeinfo->tm_mday);
  return String(buffer);
}

// Actualiza LCD 20x4 por I2C: hora arriba y mensaje debajo tras dispensar
void actualizarLCD() {
  if (millis() - lastLcdUpdate < 500) {
    return;
  }
  lastLcdUpdate = millis();

  String horaActual = getHoraActual();

  // Línea 1: Hora
  if (horaActual != ultimaHoraMostrada) {
    lcd.setCursor(0, 0);
    lcd.print("Hora: ");
    lcd.print(horaActual);
    lcd.print("        ");
    ultimaHoraMostrada = horaActual;
  }

  // Línea 2: Mensaje después de dispensar
  if (mostrarRecogerPastilla) {
    if ((long)(millis() - mensajeRecogerHasta) < 0) {
      lcd.setCursor(0, 1);
      lcd.print("!Recoger pastilla!  ");
    } else {
      mostrarRecogerPastilla = false;
      lcd.setCursor(0, 1);
      lcd.print("                    ");
    }
  }
}

// Función para obtener estado del sistema
void enviarEstado() {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }

  if (!verificarConexionSupabase()) {
    return;
  }
  
  HTTPClient http;
  WiFiClientSecure client;
  client.setInsecure();
  client.setTimeout(15000);
  http.begin(client, buildApiUrl(endpointEstado));
  http.setConnectTimeout(15000);
  http.setTimeout(20000);
  addSupabaseHeaders(http, true);

  String jsonBody = "{\"estado\":\"conectado\",\"nombre_dispositivo\":\"ESP32 Dispensador\"}";

  int httpCode = http.sendRequest("PATCH", jsonBody);
  if (httpCode != HTTP_CODE_OK && httpCode != HTTP_CODE_NO_CONTENT) {
    Serial.print("[ESTADO] Error HTTP: ");
    Serial.println(httpCode);
    Serial.print("[ESTADO] Detalle: ");
    Serial.println(HTTPClient::errorToString(httpCode));
    Serial.println(http.getString());
  }
  http.end();
}
