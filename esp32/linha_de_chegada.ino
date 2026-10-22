/**
 * =========================================================================
 * PROJETO: Linha de Chegada - Cronometragem com ESP32, LDR e Microfone
 * CASOS DE USO ATENDIDOS:
 *   - MDA-01: Microfone que detecta disparo de largada e inicia cronômetro zerado.
 *   - SDM-01: Sensor LDR + Laser que detecta corte da linha de chegada e anota tempo.
 * =========================================================================
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include "config.h"

// Estados da Corrida
enum EstadoCorrida {
    AGUARDANDO_LARGADA,   // MDA-01: Cronômetro zerado aguardando som de disparo
    CORRIDA_EM_ANDAMENTO, // Cronômetro rodando
    FINALIZADA            // Corrida encerrada
};

EstadoCorrida estadoAtual = AGUARDANDO_LARGADA;

// Variáveis de Marcação Temporal de Alta Precisão (milissegundos)
unsigned long tempoInicioMs = 0;
unsigned long ultimoDisparoMicMs = 0;
unsigned long ultimoCorteLdrMs = 0;
int totalChegadasRegistradas = 0;

// Servidor Web Local para Calibração em Campo via Celular/Navegador
WebServer localServer(80);

// Declaração de Funções
void conectarWiFi();
void verificarMicrofoneLargada();
void verificarLdrLinhaChegada();
void enviarEventoParaServidor(const char* endpoint, const String& jsonBody);
void setupRotasWebServer();
void emitirBeep(int frequencia, int duracaoMs);

void setup() {
    Serial.begin(115200);
    delay(500);
    Serial.println("\n[SISTEMA] Inicializando Linha de Chegada ESP32...");

    // Configuração dos Pinos
    pinMode(PIN_MIC_DO, INPUT);
    pinMode(PIN_LDR_AO, INPUT);
    pinMode(PIN_LED_STATUS, OUTPUT);
    pinMode(PIN_LED_CHEGADA, OUTPUT);
    pinMode(PIN_BUZZER, OUTPUT);

    #ifdef PIN_LASER
    pinMode(PIN_LASER, OUTPUT);
    digitalWrite(PIN_LASER, HIGH); // Liga o laser se estiver alimentado por GPIO
    #endif

    digitalWrite(PIN_LED_STATUS, LOW);
    digitalWrite(PIN_LED_CHEGADA, LOW);
    digitalWrite(PIN_BUZZER, LOW);

    // Conexão Wi-Fi
    conectarWiFi();

    // Inicia Servidor Web de Calibração Local
    setupRotasWebServer();
    localServer.begin();
    Serial.println("[HTTP] Servidor local de calibracao ativo!");

    // Sinal Sonoro de Inicialização (Dois bipes curtos)
    emitirBeep(1200, 100);
    delay(80);
    emitirBeep(1600, 150);

    Serial.println("[STATUS] Aguardando Disparo de Largada pelo Microfone (MDA-01)...");
}

void loop() {
    // Mantém servidor local de calibração rodando
    localServer.handleClient();

    // Reconexão Wi-Fi caso o sinal caia
    if (WiFi.status() != WL_CONNECTED) {
        digitalWrite(PIN_LED_STATUS, LOW);
        conectarWiFi();
    } else {
        digitalWrite(PIN_LED_STATUS, HIGH);
    }

    // 1. Caso de Uso MDA-01: Microfone aguardando disparo de som da largada
    if (estadoAtual == AGUARDANDO_LARGADA) {
        verificarMicrofoneLargada();
    }

    // 2. Caso de Uso SDM-01: Corrida em andamento e LDR vigiando corte do feixe laser
    if (estadoAtual == CORRIDA_EM_ANDAMENTO) {
        verificarLdrLinhaChegada();
    }

    delay(2); // Pequeno descanso para aliviar watchdog timer
}

/**
 * Conexão com o roteador Wi-Fi
 */
void conectarWiFi() {
    Serial.print("[WIFI] Conectando a: ");
    Serial.println(WIFI_SSID);

    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    int tentativas = 0;
    while (WiFi.status() != WL_CONNECTED && tentativas < 25) {
        delay(400);
        Serial.print(".");
        digitalWrite(PIN_LED_STATUS, !digitalRead(PIN_LED_STATUS));
        tentativas++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\n[WIFI] Conectado com sucesso!");
        Serial.print("[WIFI] Endereco IP do ESP32: ");
        Serial.println(WiFi.localIP());
        digitalWrite(PIN_LED_STATUS, HIGH);
    } else {
        Serial.println("\n[WIFI] Falha na conexao. Continuando em modo offline...");
    }
}

/**
 * Caso de Uso MDA-01: Verifica disparo acústico de largada
 */
void verificarMicrofoneLargada() {
    // Leitura do pino digital do módulo de som (nível LOW ou HIGH configurável)
    int leituraMic = digitalRead(PIN_MIC_DO);
    unsigned long agora = millis();

    // A maioria dos módulos KY-038/LM393 vai para HIGH ou LOW ao detectar estampido
    if (leituraMic == HIGH && (agora - ultimoDisparoMicMs > DEBOUNCE_MIC_MS)) {
        ultimoDisparoMicMs = agora;
        tempoInicioMs = agora;
        estadoAtual = CORRIDA_EM_ANDAMENTO;
        totalChegadasRegistradas = 0;

        Serial.println("\n💥 [MDA-01] DISPARO DE LARGADA DETECTADO PELO MICROFONE!");
        Serial.println("[CRONOMETRO] Iniciado em t = 0 ms");

        // Feedback sonoro e luminoso imediato
        emitirBeep(2000, 250);

        // Notifica o servidor PHP
        String payload = "{\"origem\":\"microfone_esp32\",\"titulo\":\"Corrida ESP32 " + String(agora) + "\"}";
        enviarEventoParaServidor("/api/iniciar_corrida.php", payload);
    }
}

/**
 * Caso de Uso SDM-01: Verifica corte do feixe laser no sensor LDR
 */
void verificarLdrLinhaChegada() {
    int valorLdr = analogRead(PIN_LDR_AO);
    unsigned long agora = millis();

    // Quando o atleta cruza a linha de chegada, ele interrompe a luz do laser incidente no LDR
    // Isso eleva a resistência do LDR e altera a leitura analógica abaixo/acima do limiar
    bool laserCortado = (valorLdr < LDR_THRESHOLD_DEFAULT);

    if (laserCortado && (agora - ultimoCorteLdrMs > DEBOUNCE_CHEGADA_MS)) {
        ultimoCorteLdrMs = agora;
        unsigned long tempoDecorridoMs = agora - tempoInicioMs;
        totalChegadasRegistradas++;

        digitalWrite(PIN_LED_CHEGADA, HIGH);

        Serial.print("\n🏁 [SDM-01] CORTE DO LASER DETECTADO! Atleta #");
        Serial.print(totalChegadasRegistradas);
        Serial.print(" | Tempo: ");
        Serial.print(tempoDecorridoMs);
        Serial.println(" ms");

        emitirBeep(1500, 120);

        // Prepara JSON com o tempo em milissegundos medido diretamente pelo clock de hardware
        String payload = "{\"tempo_ms\":" + String(tempoDecorridoMs) + 
                         ",\"origem\":\"ldr_laser_esp32\"" + 
                         ",\"atleta\":\"Corredor #" + String(totalChegadasRegistradas) + "\"}";

        enviarEventoParaServidor("/api/registrar_tempo.php", payload);

        digitalWrite(PIN_LED_CHEGADA, LOW);
    }
}

/**
 * Envia requisições HTTP POST para o servidor PHP
 */
void enviarEventoParaServidor(const char* endpoint, const String& jsonBody) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[HTTP] Wi-Fi desconectado! Nao foi possivel enviar evento.");
        return;
    }

    HTTPClient http;
    String urlCompleta = String(SERVER_BASE_URL) + String(endpoint);

    Serial.print("[HTTP POST] Enviando para: ");
    Serial.println(urlCompleta);

    http.begin(urlCompleta);
    http.addHeader("Content-Type", "application/json");

    int httpCode = http.POST(jsonBody);

    if (httpCode > 0) {
        String resposta = http.getString();
        Serial.printf("[HTTP] Codigo de resposta: %d\n", httpCode);
        Serial.println("[HTTP] Retorno: " + resposta);
    } else {
        Serial.printf("[HTTP] Erro na requisicao: %s\n", http.errorToString(httpCode).c_str());
    }

    http.end();
}

/**
 * Emite som no buzzer de forma não bloqueante
 */
void emitirBeep(int frequencia, int duracaoMs) {
    tone(PIN_BUZZER, frequencia, duracaoMs);
}

/**
 * Rotas do WebServer local de calibração no ESP32
 */
void setupRotasWebServer() {
    // Página de calibração em tempo real
    localServer.on("/", HTTP_GET, []() {
        int ldrValor = analogRead(PIN_LDR_AO);
        int micValor = digitalRead(PIN_MIC_DO);

        String html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        html += "<title>Calibracao Sensores ESP32</title>";
        html += "<style>body{background:#111827;color:#f3f4f6;font-family:sans-serif;padding:20px;text-align:center;}";
        html += ".card{background:#1f2937;padding:20px;border-radius:16px;margin-bottom:15px;border:1px solid #374151;}";
        html += ".val{font-size:32px;font-weight:bold;color:#10b981;font-family:monospace;}</style>";
        html += "<script>setInterval(()=>location.reload(), 800);</script></head><body>";
        html += "<h2>Calibracao de Sensores - Linha de Chegada</h2>";
        html += "<div class='card'><h3>Sensor LDR (Feixe Laser)</h3><div class='val'>" + String(ldrValor) + "</div><p>Limiar configurado: " + String(LDR_THRESHOLD_DEFAULT) + "</p></div>";
        html += "<div class='card'><h3>Microfone (Disparo de Som)</h3><div class='val' style='color:" + String(micValor ? "#ef4444" : "#9ca3af") + "'>" + (micValor ? "SOM DETECTADO!" : "Silêncio") + "</div></div>";
        html += "<div class='card'><h3>Estado da Corrida</h3><p>" + String(estadoAtual == AGUARDANDO_LARGADA ? "Aguardando Largada (MDA-01)" : "Corrida em Andamento (SDM-01)") + "</p></div>";
        html += "</body></html>";

        localServer.send(200, "text/html", html);
    });

    // Rota para resetar status via HTTP
    localServer.on("/reset", HTTP_GET, []() {
        estadoAtual = AGUARDANDO_LARGADA;
        totalChegadasRegistradas = 0;
        localServer.send(200, "text/plain", "ESP32 resetado para aguardar nova largada.");
    });
}
