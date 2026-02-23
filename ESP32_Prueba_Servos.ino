#include <ESP32Servo.h>

// ===== Pines =====
const int SERVO1_PIN = 23;
const int SERVO2_PIN = 19;
const int BUTTON_PIN = 4;

// ===== Config servo (igual que tus pruebas) =====
const int SERVO_FREQ_HZ = 50;
const int SERVO_MIN_US = 500;
const int SERVO_MAX_US = 2400;

// ===== Objetos =====
Servo servo1;
Servo servo2;

// ===== Botón (debounce) =====
int buttonState = HIGH;
int lastButtonState = HIGH;
unsigned long lastDebounceTime = 0;
const unsigned long debounceDelay = 50;

void ejecutarDispenso() {
  Serial.println("\n[DISPENSO] Iniciando ciclo...");

  // Paso 1: Servo 1 -> 90°
  Serial.println("[PASO 1] Servo 1 -> 90");
  servo1.write(90);
  delay(1000);

  // Paso 2: Servo 1 -> 0°
  Serial.println("[PASO 2] Servo 1 -> 0");
  servo1.write(0);
  delay(1000);

  // Paso 3: Servo 2 -> 90° (3s) -> 0°
  Serial.println("[PASO 3] Servo 2 -> 90 (3s) -> 0");
  servo2.write(90);
  delay(3000);
  servo2.write(0);
  delay(500);

  Serial.println("[DISPENSO] Ciclo completado\n");
}

void setup() {
  Serial.begin(115200);
  delay(500);

  // Timers PWM ESP32
  ESP32PWM::allocateTimer(0);
  ESP32PWM::allocateTimer(1);
  ESP32PWM::allocateTimer(2);
  ESP32PWM::allocateTimer(3);

  // Inicializar servos
  servo1.setPeriodHertz(SERVO_FREQ_HZ);
  servo2.setPeriodHertz(SERVO_FREQ_HZ);
  int ch1 = servo1.attach(SERVO1_PIN, SERVO_MIN_US, SERVO_MAX_US);
  int ch2 = servo2.attach(SERVO2_PIN, SERVO_MIN_US, SERVO_MAX_US);

  // Posición inicial
  servo1.write(0);
  servo2.write(0);

  // Botón con pull-up interno
  pinMode(BUTTON_PIN, INPUT_PULLUP);

  Serial.println("=== LISTO ===");
  Serial.print("Servo1 canal: ");
  Serial.println(ch1);
  Serial.print("Servo2 canal: ");
  Serial.println(ch2);
  Serial.println("Presiona boton en GPIO4 para dispensar.");
}

void loop() {
  int reading = digitalRead(BUTTON_PIN);

  if (reading != lastButtonState) {
    lastDebounceTime = millis();
  }

  if ((millis() - lastDebounceTime) > debounceDelay) {
    if (reading != buttonState) {
      buttonState = reading;

      // Solo al presionar (LOW)
      if (buttonState == LOW) {
        ejecutarDispenso();
      }
    }
  }

  lastButtonState = reading;
  delay(10);
}
