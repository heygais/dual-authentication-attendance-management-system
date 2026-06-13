/* ============================================================
   AttendX — ESP32 RFID-Only Reader  (RC522 + LCD 1602 I2C)
   ------------------------------------------------------------
   RC522 RFID  : SCK=18 MISO=19 MOSI=23 SS=5 RST=22  (SPI/VSPI)
   LCD 1602 I2C: SDA=21  SCL=15  addr 0x27           (I2C)

   GPIO16 and GPIO17 (previously AS608 fingerprint UART2) are
   now FREE for reuse — e.g. buzzer, extra LED, servo, button.

   Flow: tap card -> read UID -> HTTP POST to backend -> show
   result on LCD. Single-step verification (RFID only).

   LCD states:
     idle      "AttendX Ready"  / "Tap Your Card"
     card      "Card Detected"  / <UID>
     success   "Attendance"     / "Recorded"
     unknown   "Unknown Card"   / "Try Again"
     no sess.  "No Session"     / "Ask Lecturer"
     dup       "Already Marked" / <UID>
     not enr.  "Not Enrolled"   / "See Admin"

   Libraries (Arduino IDE -> Library Manager):
     - MFRC522 by miguelbalboa
     - LiquidCrystal_I2C by Frank de Brabander
   ============================================================ */

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// ─── 1. CONFIGURATION — change these ────────────────────────
const char* WIFI_SSID  = "Fahim";
const char* WIFI_PASS  = "Fahim727";
const char* SERVER_URL = "http://172.20.10.14/attendx/api/attendance.php";
const char* CLASS_NAME = "CS201";   // kept for compatibility (server uses active session)

// ─── 2. PINS ────────────────────────────────────────────────
#define SS_PIN    5    // RC522 SDA/SS
#define RST_PIN   22   // RC522 RST
#define LED_OK    2    // built-in LED (success)
#define LED_FAIL  4    // optional red LED (failure)

#define LCD_SDA   21   // LCD I2C SDA
#define LCD_SCL   15   // LCD I2C SCL
#define LCD_ADDR  0x27

// GPIO16 / GPIO17: FREE (formerly AS608 fingerprint RX2/TX2)

// ─── 3. OBJECTS ─────────────────────────────────────────────
MFRC522           rfid(SS_PIN, RST_PIN);
LiquidCrystal_I2C lcd(LCD_ADDR, 16, 2);

// ─── 4. LCD HELPERS ─────────────────────────────────────────
void lcdShow(const String& l1, const String& l2) {
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print(l1.substring(0, 16));
  lcd.setCursor(0, 1); lcd.print(l2.substring(0, 16));
}
void showIdle() { lcdShow("AttendX Ready", "Tap Your Card"); }

// ─── 5. WIFI ────────────────────────────────────────────────
void connectWiFi() {
  Serial.print("Connecting to WiFi");
  lcdShow("AttendX", "WiFi...");
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 15000) {
    delay(400);
    Serial.print(".");
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi OK — IP: " + WiFi.localIP().toString());
    lcdShow("WiFi Connected", WiFi.localIP().toString());
  } else {
    Serial.println("\nWiFi FAILED — will retry on next scan.");
    lcdShow("WiFi Failed", "Will retry");
  }
  delay(900);
}

// ─── 6. HTTP POST to AttendX (returns server reply string) ──
String sendAttendance(const String& uid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[NET] Not connected, reconnecting...");
    connectWiFi();
    if (WiFi.status() != WL_CONNECTED) return "NO_WIFI";
  }

  HTTPClient http;
  http.begin(SERVER_URL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  // URL-encode spaces in the UID ("51 29 4C 9F" -> "51%2029%204C%209F")
  String uidEnc = uid; uidEnc.replace(" ", "%20");
  String body = "uid=" + uidEnc;

  Serial.println("[NET] POST → " + body);
  int code = http.POST(body);
  String reply = http.getString();
  reply.trim();
  http.end();

  Serial.printf("[NET] HTTP %d\n", code);
  Serial.println("[NET] Reply: " + reply);
  return reply;
}

void blink(int pin, int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(pin, HIGH); delay(120);
    digitalWrite(pin, LOW);  delay(120);
  }
}

// ─── 7. RFID READ (unchanged) ───────────────────────────────
bool readRFID(String& uidOut) {
  if (!rfid.PICC_IsNewCardPresent())  return false;
  if (!rfid.PICC_ReadCardSerial())    return false;

  uidOut = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    uidOut += (rfid.uid.uidByte[i] < 0x10 ? " 0" : " ");
    uidOut += String(rfid.uid.uidByte[i], HEX);
  }
  uidOut.toUpperCase();
  uidOut.trim();

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
  return true;
}

// ─── 8. SETUP ───────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  delay(300);

  pinMode(LED_OK,   OUTPUT);
  pinMode(LED_FAIL, OUTPUT);

  // LCD (set I2C pins BEFORE init)
  Wire.begin(LCD_SDA, LCD_SCL);
  lcd.init();
  lcd.backlight();
  lcdShow("AttendX", "Starting...");

  // RFID
  SPI.begin();
  rfid.PCD_Init();

  Serial.println("\n=================================");
  Serial.println("   AttendX — RFID-Only Reader");
  Serial.println("=================================");

  connectWiFi();

  showIdle();
  Serial.println("Status: Ready. Tap your card...");
  Serial.println("---------------------------------");
}

// ─── 9. LOOP — single tap logs attendance ───────────────────
void loop() {
  String uid;
  if (!readRFID(uid)) return;        // idle until a card appears

  // ── Card detected ──
  Serial.println("[RFID] UID = " + uid);
  lcdShow("Card Detected", uid);
  delay(400);

  // ── Send immediately — single-step verification ──
  String reply = sendAttendance(uid);

  // ── Show result ──
  if (reply == "SUCCESS") {
    Serial.println(">> PRESENT — attendance recorded.");
    lcdShow("Attendance", "Recorded");
    blink(LED_OK, 3);
  } else if (reply == "ALREADY_MARKED") {
    Serial.println(">> ALREADY MARKED — already present this session.");
    lcdShow("Already Marked", uid);
    blink(LED_FAIL, 2);
  } else if (reply == "NOT_ENROLLED") {
    Serial.println(">> NOT ENROLLED — student isn't in this subject.");
    lcdShow("Not Enrolled", "See Admin");
    blink(LED_FAIL, 3);
  } else if (reply == "NOT_FOUND") {
    Serial.println(">> UNKNOWN CARD — register this RFID to a student first.");
    lcdShow("Unknown Card", "Try Again");
    blink(LED_FAIL, 4);
  } else if (reply == "NO_SESSION") {
    Serial.println(">> NO ACTIVE SESSION — lecturer must start one.");
    lcdShow("No Session", "Ask Lecturer");
    blink(LED_FAIL, 5);
  } else {
    Serial.println(">> Error / unknown reply: " + reply);
    lcdShow("Error", "Try Again");
    blink(LED_FAIL, 1);
  }
  delay(2200);

  // ── Back to idle ──
  showIdle();
  Serial.println("---------------------------------");
  delay(300);
}
