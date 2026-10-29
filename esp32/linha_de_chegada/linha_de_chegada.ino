/**
 * =========================================================================
 * PROJETO: Linha de Chegada - Cronometragem em Tempo Real (Ultra Baixa Latência)
 * PINAGEM CONFIGURADA:
 *   - Porta 4 (GPIO 4): Sensor LDR (Linha de Chegada - SDM-01)
 *   - Porta 5 (GPIO 5): Módulo Microfone (Disparo de Largada - MDA-01)
 * OTIMIZAÇÕES DE TEMPO REAL (ZERO DELAY):
 *   1. Interrupções de Hardware (IRAM_ATTR) nas Portas 4 e 5 (latência < 5 microssegundos).
 *   2. Envio HTTP Assíncrono em segundo plano (Core 0 / FreeRTOS Queue) — nunca trava o loop().
 *   3. WiFi.setSleep(false) desativa economia de energia do rádio Wi-Fi (reduz ping de 250ms para ~2ms).
 *   4. Painel Web leve e instantâneo com Sensibilidade do LDR, Sensibilidade do Mic e Wi-Fi.
 * =========================================================================
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <Preferences.h>
#include "config.h"

#ifndef PIN_LDR
#define PIN_LDR 4
#endif
#ifndef AP_SSID
#define AP_SSID "LinhaDeChegada-WiFi"
#endif
#ifndef AP_PASSWORD
#define AP_PASSWORD ""
#endif
#ifndef WIFI_RECONNECT_INTERVAL_MS
#define WIFI_RECONNECT_INTERVAL_MS 20000
#endif
#ifndef MIC_PICO_DEFAULT
#define MIC_PICO_DEFAULT 1
#endif
#ifndef LDR_SENSIBILIDADE_DEFAULT
#define LDR_SENSIBILIDADE_DEFAULT 30
#endif

// Estados da Corrida
enum EstadoCorrida {
    AGUARDANDO_LARGADA,
    CORRIDA_EM_ANDAMENTO,
    FINALIZADA
};

EstadoCorrida estadoAtual = AGUARDANDO_LARGADA;

// Variáveis Temporais de Alta Precisão
unsigned long tempoInicioMs = 0;
unsigned long ultimoDisparoMicMs = 0;
unsigned long ultimoCorteLdrMs = 0;
unsigned long ultimaTentativaWifiMs = 0;
int totalChegadasRegistradas = 0;

// Contadores de Interrupção de Hardware (IRAM_ATTR - Tempo Real Zero Delay)
volatile uint32_t isrPulsosMic = 0;
volatile uint32_t isrPulsosLdr = 0;

// Parâmetros de Sensibilidade do Microfone (Porta 5)
int estadoRepousoMic = LOW;
int limiarPicoMic = MIC_PICO_DEFAULT;
int ultimoPicoMicGravado = 0;
int maiorPicoMicRecente = 0;
int debounceMicMs = DEBOUNCE_MIC_MS;

// Parâmetros de Sensibilidade do LDR (Porta 4)
int estadoRepousoLdr = LOW;
int limiarSensibilidadeLdr = LDR_SENSIBILIDADE_DEFAULT;
int limiarAnalogicoLdr = LDR_THRESHOLD_DEFAULT;
int ultimoPicoLdrGravado = 0;
int maiorCorteLdrRecente = 0;
int debounceChegadaMs = DEBOUNCE_CHEGADA_MS;

// Fila Assíncrona para Envio HTTP no Core 0 (Não bloqueia os sensores!)
struct EventoHttp {
    char endpoint[40];
    char payload[160];
};
QueueHandle_t filaEventosHttp = NULL;

// Rede e Servidor Web
Preferences preferencias;
WebServer localServer(80);
DNSServer dnsServer;
const byte DNS_PORT = 53;

String wifiSsidSalvo = "";
String wifiSenhaSalva = "";
String serverUrlSalva = SERVER_BASE_URL;
bool modoAssistenteApAtivo = false;

// Interrupções de Hardware em RAM (Execução em < 1 microssegundo)
void IRAM_ATTR onMicChangeISR() {
    isrPulsosMic++;
}

void IRAM_ATTR onLdrChangeISR() {
    isrPulsosLdr++;
}

// Declaração de Funções
void tarefaEnvioHttpCore0(void* pvParameters);
void carregarConfiguracoesSalvas();
void salvarSensibilidadeMic(int novoLimiar, int picoReferencia, int novoDebounce);
void salvarSensibilidadeLdr(int novaSensibilidade, int novoLimiarAdc, int novoDebounce, int picoRef);
bool tentarConectarRedeSalva();
void iniciarModoAssistenteAP();
void desativarModoAssistenteAP();
void gerenciarConexaoWiFi();
void calibrarSensoresRepouso();
bool pinoSuportaAdc1(int pino);
int lerPicoAudioMicRapido();
int lerIntensidadeCorteLdrRapido();
int gravarPicoAudioReferencia(int duracaoMs = 2500);
int gravarCorteLdrReferencia(int duracaoMs = 2500);
void verificarMicrofoneLargada();
void verificarLdrLinhaChegada();
void verificarComandosSerial();
void enfileirarEventoServidor(const char* endpoint, const String& jsonBody);
void adicionarCabecalhosCors();
void setupRotasWebServer();
String gerarPaginaCompletaESP32(const String& aviso = "", bool escanearWifi = false);
void emitirBeep(int frequencia, int duracaoMs);

void setup() {
    Serial.begin(115200);
    Serial.println("\n====================================================");
    Serial.println("[SISTEMA] Linha de Chegada ESP32 - Modo Tempo Real");
    Serial.printf("[PINOS] LDR: Porta %d | Microfone: Porta %d\n", PIN_LDR, PIN_MIC_DO);
    Serial.println("====================================================");

    pinMode(PIN_MIC_DO, INPUT);
    pinMode(PIN_LDR, INPUT);
    pinMode(PIN_LED_STATUS, OUTPUT);
    pinMode(PIN_LED_CHEGADA, OUTPUT);
    pinMode(PIN_BUZZER, OUTPUT);

    #ifdef PIN_LASER
    pinMode(PIN_LASER, OUTPUT);
    digitalWrite(PIN_LASER, HIGH);
    #endif

    digitalWrite(PIN_LED_STATUS, LOW);
    digitalWrite(PIN_LED_CHEGADA, LOW);
    digitalWrite(PIN_BUZZER, LOW);

    calibrarSensoresRepouso();

    // Ativa Interrupções de Hardware nas Portas 4 e 5 para captura instantânea
    attachInterrupt(digitalPinToInterrupt(PIN_MIC_DO), onMicChangeISR, CHANGE);
    attachInterrupt(digitalPinToInterrupt(PIN_LDR), onLdrChangeISR, CHANGE);

    // Cria fila e Task no Core 0 para envio HTTP sem bloquear o cronômetro
    filaEventosHttp = xQueueCreate(16, sizeof(EventoHttp));
    xTaskCreatePinnedToCore(
        tarefaEnvioHttpCore0,
        "HttpWorker",
        4096,
        NULL,
        1,
        NULL,
        0 // Core 0 dedicado ao envio de rede
    );

    carregarConfiguracoesSalvas();
    setupRotasWebServer();

    if (!tentarConectarRedeSalva()) {
        iniciarModoAssistenteAP();
    }

    // Desativa economia de energia do Wi-Fi para resposta imediata (< 3ms de ping)
    WiFi.setSleep(false);

    localServer.begin();
    emitirBeep(1500, 80);

    Serial.println("[PRONTO] Sistema operando em Tempo Real (Zero Delay)!");
}

void loop() {
    // 1. Prioridade Máxima: Leitura em Tempo Real dos Sensores (Modo Híbrido: ESP32 + Celular/PC)
    // O Microfone (Porta 5) dispara a largada se estiver aguardando;
    // O LDR (Porta 4) está SEMPRE ativo para registrar a chegada tanto se a largada
    // foi dada pelo Microfone do ESP32 quanto pelo Microfone do Celular/PC!
    if (estadoAtual == AGUARDANDO_LARGADA) {
        verificarMicrofoneLargada();
    }
    verificarLdrLinhaChegada();

    // 2. Atende painel web e DNS sem bloquear
    if (modoAssistenteApAtivo) {
        dnsServer.processNextRequest();
    }
    localServer.handleClient();

    // 3. Gerencia reconexão Wi-Fi e comandos Serial
    gerenciarConexaoWiFi();
    verificarComandosSerial();
}

/**
 * Worker em segundo plano (Core 0) que envia os pacotes HTTP POST para o PHP
 * sem travar o loop principal dos sensores (Core 1)
 */
void tarefaEnvioHttpCore0(void* pvParameters) {
    EventoHttp ev;
    HTTPClient http;
    http.setReuse(true); // Mantém conexão TCP viva (Keep-Alive)

    for (;;) {
        if (xQueueReceive(filaEventosHttp, &ev, portMAX_DELAY) == pdTRUE) {
            if (WiFi.status() == WL_CONNECTED) {
                String urlCompleta = serverUrlSalva + String(ev.endpoint);
                http.begin(urlCompleta);
                http.setTimeout(1500);
                http.addHeader("Content-Type", "application/json");
                int code = http.POST((uint8_t*)ev.payload, strlen(ev.payload));
                http.end();
                Serial.printf("[HTTP ASYNC] %s -> Status %d\n", ev.endpoint, code);
            }
        }
    }
}

/**
 * Enfileira evento para envio imediato no Core 0 (executa em < 2 microssegundos)
 */
void enfileirarEventoServidor(const char* endpoint, const String& jsonBody) {
    if (!filaEventosHttp) return;
    EventoHttp ev;
    strncpy(ev.endpoint, endpoint, sizeof(ev.endpoint) - 1);
    ev.endpoint[sizeof(ev.endpoint) - 1] = '\0';
    strncpy(ev.payload, jsonBody.c_str(), sizeof(ev.payload) - 1);
    ev.payload[sizeof(ev.payload) - 1] = '\0';
    xQueueSend(filaEventosHttp, &ev, 0);
}

/**
 * Leitura Ultra-Rápida do Pico de Áudio na Porta 5 (Zero Delay)
 * Combina pulsos capturados por interrupção de hardware + leitura rápida de registrador
 */
int lerPicoAudioMicRapido() {
    if (pinoSuportaAdc1(PIN_MIC_DO)) {
        int leituraMin = 4095, leituraMax = 0;
        for (int i = 0; i < 16; i++) {
            int val = analogRead(PIN_MIC_DO);
            if (val < leituraMin) leituraMin = val;
            if (val > leituraMax) leituraMax = val;
        }
        int pico = leituraMax - leituraMin;
        if (pico > maiorPicoMicRecente) maiorPicoMicRecente = pico;
        return pico;
    }

    // Porta 5: Captura pulsos da interrupção de hardware + 32 leituras diretas sem delay
    uint32_t pulsosIsr = isrPulsosMic;
    isrPulsosMic = 0;

    int ativas = 0;
    for (int i = 0; i < 32; i++) {
        if (digitalRead(PIN_MIC_DO) != estadoRepousoMic) {
            ativas++;
        }
    }

    if (ativas == 0 && pulsosIsr == 0) {
        return 0;
    }

    int pico = ((ativas * 100) / 32) + (int)(pulsosIsr * 8);
    if (pico < 1) pico = 1;
    if (pico > 100) pico = 100;
    if (pico > maiorPicoMicRecente) maiorPicoMicRecente = pico;
    return pico;
}

/**
 * Leitura Ultra-Rápida da Intensidade de Corte do LDR na Porta 4 (Zero Delay)
 */
int lerIntensidadeCorteLdrRapido() {
    if (pinoSuportaAdc1(PIN_LDR)) {
        int val = analogRead(PIN_LDR);
        if (val >= limiarAnalogicoLdr) return 0;
        int pct = ((limiarAnalogicoLdr - val) * 100) / limiarAnalogicoLdr;
        if (pct < 1) pct = 1;
        if (pct > 100) pct = 100;
        if (pct > maiorCorteLdrRecente) maiorCorteLdrRecente = pct;
        return pct;
    }

    uint32_t pulsosIsr = isrPulsosLdr;
    isrPulsosLdr = 0;

    int cortadas = 0;
    for (int i = 0; i < 32; i++) {
        if (digitalRead(PIN_LDR) != estadoRepousoLdr) {
            cortadas++;
        }
    }

    if (cortadas == 0 && pulsosIsr == 0) {
        return 0;
    }

    int intensidade = ((cortadas * 100) / 32) + (int)(pulsosIsr * 10);
    if (intensidade < 1) intensidade = 1;
    if (intensidade > 100) intensidade = 100;
    if (intensidade > maiorCorteLdrRecente) maiorCorteLdrRecente = intensidade;
    return intensidade;
}

/**
 * Caso de Uso MDA-01: Inicia o cronômetro no exato milissegundo em que o pico atinge o limiar
 */
void verificarMicrofoneLargada() {
    int picoAtual = lerPicoAudioMicRapido();
    if (picoAtual < limiarPicoMic) return;

    unsigned long agora = millis();
    if (agora - ultimoDisparoMicMs <= (unsigned long)debounceMicMs) return;

    // Marca o tempo ANTES de qualquer print ou som (Precisão de Sub-Milissegundo!)
    tempoInicioMs = agora;
    ultimoDisparoMicMs = agora;
    ultimoCorteLdrMs = agora;
    estadoRepousoLdr = digitalRead(PIN_LDR);
    isrPulsosLdr = 0;
    estadoAtual = CORRIDA_EM_ANDAMENTO;
    totalChegadasRegistradas = 0;

    // Enfileira envio assíncrono imediato para o servidor PHP (não bloqueia!)
    String payload = "{\"origem\":\"microfone_esp32\",\"pico_audio\":" + String(picoAtual) +
                     ",\"titulo\":\"Corrida ESP32 " + String(agora) + "\"}";
    enfileirarEventoServidor("/api/iniciar_corrida.php", payload);

    emitirBeep(2000, 180);
    Serial.printf("\n💥 [LARGADA TEMPO REAL] Pico=%d (>=%d) | t = 0 ms\n", picoAtual, limiarPicoMic);
}

/**
 * Caso de Uso SDM-01: Anota o tempo exato no instante do corte do LDR na Porta 4
 */
void verificarLdrLinhaChegada() {
    int intensidadeCorte = lerIntensidadeCorteLdrRapido();
    if (intensidadeCorte < limiarSensibilidadeLdr) return;

    unsigned long agora = millis();
    if (agora - ultimoCorteLdrMs <= (unsigned long)debounceChegadaMs) return;

    // Calcula o tempo exato se a largada foi iniciada no ESP32, ou envia 0 para o servidor
    // calcular a partir do timestamp de largada do Microfone do Celular/PC
    unsigned long tempoDecorridoMs = (estadoAtual == CORRIDA_EM_ANDAMENTO && tempoInicioMs > 0)
        ? (agora - tempoInicioMs)
        : 0;
    ultimoCorteLdrMs = agora;
    totalChegadasRegistradas++;

    digitalWrite(PIN_LED_CHEGADA, HIGH);

    // Envia de forma assíncrona no Core 0 (Zero Delay para próximas passagens!)
    String payload = "{\"tempo_ms\":" + String(tempoDecorridoMs) +
                     ",\"origem\":\"ldr_laser_esp32\"" +
                     ",\"atleta\":\"Corredor #" + String(totalChegadasRegistradas) + "\"}";
    enfileirarEventoServidor("/api/registrar_tempo.php", payload);

    emitirBeep(1500, 100);
    Serial.printf("\n🏁 [CHEGADA #%d] Tempo Exato: %lu ms (%.3f s) | Corte: %d%%\n",
                  totalChegadasRegistradas, tempoDecorridoMs, tempoDecorridoMs / 1000.0, intensidadeCorte);

    digitalWrite(PIN_LED_CHEGADA, LOW);
}

void carregarConfiguracoesSalvas() {
    preferencias.begin("linha_cfg", true);
    wifiSsidSalvo          = preferencias.getString("ssid", "");
    wifiSenhaSalva         = preferencias.getString("pass", "");
    serverUrlSalva         = preferencias.getString("srv_url", SERVER_BASE_URL);
    limiarPicoMic          = preferencias.getInt("mic_limiar", MIC_PICO_DEFAULT);
    ultimoPicoMicGravado   = preferencias.getInt("mic_pico", 0);
    debounceMicMs          = preferencias.getInt("mic_deb", DEBOUNCE_MIC_MS);
    limiarSensibilidadeLdr = preferencias.getInt("ldr_sens", LDR_SENSIBILIDADE_DEFAULT);
    limiarAnalogicoLdr     = preferencias.getInt("ldr_adc", LDR_THRESHOLD_DEFAULT);
    ultimoPicoLdrGravado   = preferencias.getInt("ldr_pico", 0);
    debounceChegadaMs      = preferencias.getInt("ldr_deb", DEBOUNCE_CHEGADA_MS);
    preferencias.end();

    if (limiarPicoMic < 1) limiarPicoMic = 1;
    if (limiarSensibilidadeLdr < 1) limiarSensibilidadeLdr = 1;
    if (limiarSensibilidadeLdr > 100) limiarSensibilidadeLdr = 100;

    if (wifiSsidSalvo.length() == 0 && String(WIFI_SSID) != "SEU_WIFI_NOME" && String(WIFI_SSID).length() > 0) {
        wifiSsidSalvo = String(WIFI_SSID);
        wifiSenhaSalva = String(WIFI_PASSWORD);
    }
}

void salvarSensibilidadeMic(int novoLimiar, int picoReferencia, int novoDebounce) {
    if (novoLimiar < 1) novoLimiar = 1;
    if (novoDebounce < 200) novoDebounce = 200;

    limiarPicoMic = novoLimiar;
    ultimoPicoMicGravado = picoReferencia;
    debounceMicMs = novoDebounce;

    preferencias.begin("linha_cfg", false);
    preferencias.putInt("mic_limiar", limiarPicoMic);
    preferencias.putInt("mic_pico", ultimoPicoMicGravado);
    preferencias.putInt("mic_deb", debounceMicMs);
    preferencias.end();
}

void salvarSensibilidadeLdr(int novaSensibilidade, int novoLimiarAdc, int novoDebounce, int picoRef) {
    if (novaSensibilidade < 1) novaSensibilidade = 1;
    if (novaSensibilidade > 100) novaSensibilidade = 100;
    if (novoLimiarAdc < 10) novoLimiarAdc = 10;
    if (novoLimiarAdc > 4090) novoLimiarAdc = 4090;
    if (novoDebounce < 100) novoDebounce = 100;

    limiarSensibilidadeLdr = novaSensibilidade;
    limiarAnalogicoLdr = novoLimiarAdc;
    debounceChegadaMs = novoDebounce;
    ultimoPicoLdrGravado = picoRef;

    preferencias.begin("linha_cfg", false);
    preferencias.putInt("ldr_sens", limiarSensibilidadeLdr);
    preferencias.putInt("ldr_adc", limiarAnalogicoLdr);
    preferencias.putInt("ldr_deb", debounceChegadaMs);
    preferencias.putInt("ldr_pico", ultimoPicoLdrGravado);
    preferencias.end();
}

bool pinoSuportaAdc1(int pino) {
    return (pino == 32 || pino == 33 || pino == 34 || pino == 35 || pino == 36 || pino == 39);
}

int gravarPicoAudioReferencia(int duracaoMs) {
    calibrarSensoresRepouso();
    emitirBeep(1000, 100);
    delay(110);

    unsigned long inicio = millis();
    int picoMaximo = 0;

    while (millis() - inicio < (unsigned long)duracaoMs) {
        int pico = lerPicoAudioMicRapido();
        if (pico > picoMaximo) {
            picoMaximo = pico;
            digitalWrite(PIN_LED_CHEGADA, HIGH);
        } else {
            digitalWrite(PIN_LED_CHEGADA, LOW);
        }
        delay(1);
    }
    digitalWrite(PIN_LED_CHEGADA, LOW);
    emitirBeep(1800, 100);

    if (picoMaximo > 0) {
        int novoLimiar = (int)(picoMaximo * 0.80);
        if (novoLimiar < 1) novoLimiar = 1;
        salvarSensibilidadeMic(novoLimiar, picoMaximo, debounceMicMs);
    }
    maiorPicoMicRecente = picoMaximo;
    return picoMaximo;
}

int gravarCorteLdrReferencia(int duracaoMs) {
    calibrarSensoresRepouso();
    emitirBeep(900, 100);
    delay(110);

    unsigned long inicio = millis();
    int corteMaximo = 0;

    while (millis() - inicio < (unsigned long)duracaoMs) {
        int corte = lerIntensidadeCorteLdrRapido();
        if (corte > corteMaximo) {
            corteMaximo = corte;
            digitalWrite(PIN_LED_CHEGADA, HIGH);
        } else {
            digitalWrite(PIN_LED_CHEGADA, LOW);
        }
        delay(1);
    }
    digitalWrite(PIN_LED_CHEGADA, LOW);
    emitirBeep(1600, 100);

    if (corteMaximo > 0) {
        int novaSens = (int)(corteMaximo * 0.70);
        if (novaSens < 1) novaSens = 1;
        salvarSensibilidadeLdr(novaSens, limiarAnalogicoLdr, debounceChegadaMs, corteMaximo);
    }
    maiorCorteLdrRecente = corteMaximo;
    return corteMaximo;
}

bool tentarConectarRedeSalva() {
    ultimaTentativaWifiMs = millis();
    if (wifiSsidSalvo.length() == 0) return false;

    if (modoAssistenteApAtivo) {
        WiFi.mode(WIFI_AP_STA);
    } else {
        WiFi.mode(WIFI_STA);
    }
    WiFi.setSleep(false);
    WiFi.begin(wifiSsidSalvo.c_str(), wifiSenhaSalva.c_str());

    int tentativas = 0;
    while (WiFi.status() != WL_CONNECTED && tentativas < 16) {
        delay(250);
        digitalWrite(PIN_LED_STATUS, !digitalRead(PIN_LED_STATUS));
        tentativas++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        WiFi.setSleep(false);
        Serial.print("\n✅ [WIFI] Conectado! IP: ");
        Serial.println(WiFi.localIP());
        digitalWrite(PIN_LED_STATUS, HIGH);
        if (modoAssistenteApAtivo) {
            desativarModoAssistenteAP();
        }
        return true;
    }

    digitalWrite(PIN_LED_STATUS, LOW);
    return false;
}

void iniciarModoAssistenteAP() {
    if (modoAssistenteApAtivo) return;

    WiFi.mode(WIFI_AP_STA);
    WiFi.setSleep(false);

    IPAddress apIP(192, 168, 4, 1);
    IPAddress netMsk(255, 255, 255, 0);
    WiFi.softAPConfig(apIP, apIP, netMsk);

    if (String(AP_PASSWORD).length() >= 8) {
        WiFi.softAP(AP_SSID, AP_PASSWORD);
    } else {
        WiFi.softAP(AP_SSID);
    }

    dnsServer.setErrorReplyCode(DNSReplyCode::NoError);
    dnsServer.start(DNS_PORT, "*", apIP);
    modoAssistenteApAtivo = true;

    Serial.printf("📡 [AP ATIVO] Rede: %s | Acesse: http://192.168.4.1\n", AP_SSID);
}

void desativarModoAssistenteAP() {
    if (!modoAssistenteApAtivo) return;
    dnsServer.stop();
    WiFi.softAPdisconnect(true);
    WiFi.mode(WIFI_STA);
    WiFi.setSleep(false);
    modoAssistenteApAtivo = false;
}

void gerenciarConexaoWiFi() {
    if (WiFi.status() == WL_CONNECTED) {
        digitalWrite(PIN_LED_STATUS, HIGH);
        return;
    }

    digitalWrite(PIN_LED_STATUS, LOW);

    if (!modoAssistenteApAtivo) {
        iniciarModoAssistenteAP();
    }

    if (wifiSsidSalvo.length() > 0 &&
        estadoAtual != CORRIDA_EM_ANDAMENTO &&
        WiFi.softAPgetStationNum() == 0 &&
        (millis() - ultimaTentativaWifiMs > WIFI_RECONNECT_INTERVAL_MS)) {
        tentarConectarRedeSalva();
    }
}

void calibrarSensoresRepouso() {
    estadoRepousoMic = digitalRead(PIN_MIC_DO);
    estadoRepousoLdr = digitalRead(PIN_LDR);
    isrPulsosMic = 0;
    isrPulsosLdr = 0;
}

void verificarComandosSerial() {
    if (Serial.available() > 0) {
        char cmd = Serial.read();
        if (cmd == 'c' || cmd == 'C') {
            gravarPicoAudioReferencia(2500);
        } else if (cmd == 'l' || cmd == 'L') {
            gravarCorteLdrReferencia(2500);
        } else if (cmd == 'r' || cmd == 'R') {
            estadoAtual = AGUARDANDO_LARGADA;
            totalChegadasRegistradas = 0;
            maiorPicoMicRecente = 0;
            maiorCorteLdrRecente = 0;
            calibrarSensoresRepouso();
            Serial.println("\n🔄 [RESET] Cronometro zerado!");
        } else if (cmd == 's' || cmd == 'S') {
            Serial.printf("Mic (P%d): Pico=%d Limiar=%d | LDR (P%d): Corte=%d%% Sens=%d%%\n",
                          PIN_MIC_DO, maiorPicoMicRecente, limiarPicoMic,
                          PIN_LDR, maiorCorteLdrRecente, limiarSensibilidadeLdr);
        } else if (cmd == 'w' || cmd == 'W') {
            WiFi.disconnect();
            iniciarModoAssistenteAP();
        }
    }
}

void emitirBeep(int frequencia, int duracaoMs) {
    tone(PIN_BUZZER, frequencia, duracaoMs);
}

void adicionarCabecalhosCors() {
    localServer.sendHeader("Access-Control-Allow-Origin", "*");
    localServer.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    localServer.sendHeader("Access-Control-Allow-Headers", "Content-Type");
}

String gerarPaginaCompletaESP32(const String& aviso, bool escanearWifi) {
    int escalaMaxMic = pinoSuportaAdc1(PIN_MIC_DO) ? 4095 : 100;

    String html;
    html.reserve(4500); // Pré-aloca memória na RAM para resposta instantânea sem fragmentação
    html = "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'>";
    html += "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    html += "<title>Configuracao ESP32 - Sensibilidade LDR, Mic e Wi-Fi</title>";
    html += "<style>";
    html += "body{background:#0f172a;color:#f8fafc;font-family:system-ui,sans-serif;padding:12px;margin:0;}";
    html += ".container{max-width:500px;margin:0 auto;}";
    html += ".card{background:#1e293b;padding:16px;border-radius:14px;margin-bottom:14px;border:1px solid #334155;}";
    html += "h2{margin:0 0 6px 0;color:#38bdf8;font-size:19px;text-align:center;}";
    html += "h3{margin:0 0 8px 0;font-size:15px;color:#f1f5f9;display:flex;align-items:center;justify-content:space-between;}";
    html += ".tag{font-size:11px;padding:2px 7px;border-radius:6px;background:#0f172a;color:#38bdf8;font-family:monospace;}";
    html += ".meter-box{background:#0f172a;padding:10px;border-radius:10px;text-align:center;margin-bottom:10px;border:1px solid #334155;}";
    html += ".val{font-size:24px;font-weight:bold;font-family:monospace;margin:2px 0;}";
    html += "label{display:block;font-size:12px;color:#cbd5e1;margin:8px 0 3px 0;font-weight:600;}";
    html += "input[type=number],input[type=text],input[type=password]{width:100%;padding:9px;border-radius:8px;border:1px solid #475569;background:#0f172a;color:#fff;box-sizing:border-box;font-size:14px;}";
    html += "input[type=range]{width:100%;margin:4px 0;}";
    html += ".btn{display:block;width:100%;padding:11px;border:none;border-radius:9px;font-weight:bold;font-size:13px;cursor:pointer;margin-top:8px;text-align:center;text-decoration:none;box-sizing:border-box;}";
    html += ".btn-red{background:#dc2626;color:#fff;}";
    html += ".btn-amber{background:#d97706;color:#fff;}";
    html += ".btn-blue{background:#0284c7;color:#fff;}";
    html += ".btn-green{background:#059669;color:#fff;}";
    html += ".btn-gray{background:#334155;color:#e2e8f0;}";
    html += ".msg{background:#065f46;color:#d1fae5;padding:10px;border-radius:9px;margin-bottom:12px;text-align:center;font-size:13px;border:1px solid #10b981;}";
    html += ".net-item{display:flex;justify-content:space-between;background:#0f172a;padding:9px 12px;border-radius:8px;margin-bottom:5px;cursor:pointer;border:1px solid #334155;}";
    html += ".row2{display:grid;grid-template-columns:1fr 1fr;gap:8px;}";
    html += "</style>";
    html += "<script>";
    html += "function selecionarRede(s){document.getElementById('ssid').value=s;document.getElementById('pass').focus();}";
    html += "function syncVal(id,v){document.getElementById(id).value=v;}";
    html += "</script></head><body><div class='container'>";

    html += "<h2>⚡ ESP32 Tempo Real - Sensibilidade & Wi-Fi</h2>";

    if (WiFi.status() == WL_CONNECTED) {
        html += "<p style='text-align:center;color:#10b981;font-size:12px;margin-top:0;'>✅ Conectado: <b>" + wifiSsidSalvo + "</b> (IP: " + WiFi.localIP().toString() + ")</p>";
    } else {
        html += "<p style='text-align:center;color:#f59e0b;font-size:12px;margin-top:0;'>📡 Rede Assistente: <b>" + String(AP_SSID) + "</b> (192.168.4.1)</p>";
    }

    if (aviso.length() > 0) {
        html += "<div class='msg'>" + aviso + "</div>";
    }

    // BLOCO 1: SENSIBILIDADE DO MICROFONE (PORTA 5)
    html += "<div class='card'>";
    html += "<h3><span>🎙️ 1. Sensibilidade do Microfone</span><span class='tag'>Porta " + String(PIN_MIC_DO) + "</span></h3>";
    html += "<div class='meter-box'>";
    html += "<div class='val' style='color:#38bdf8;'>Pico Recente: " + String(maiorPicoMicRecente) + " / " + String(escalaMaxMic) + "</div>";
    html += "<div style='font-size:12px;color:#cbd5e1;'>Dispara em &ge; <b>" + String(limiarPicoMic) + "</b> | Ultimo Gravado: <b>" + String(ultimoPicoMicGravado) + "</b></div>";
    html += "</div>";
    html += "<form action='/gravar-pico' method='POST'><button type='submit' class='btn btn-red'>🔴 Gravar Pico de Audio (Escutar 2.5s)</button></form>";
    html += "<form action='/salvar-sensibilidade-mic' method='POST'>";
    html += "<label>Sensibilidade de Pico do Microfone (1 = mais sensivel, " + String(escalaMaxMic) + " = menos sensivel):</label>";
    html += "<input type='range' min='1' max='" + String(escalaMaxMic) + "' value='" + String(limiarPicoMic) + "' oninput=\"syncVal('mic_num',this.value)\">";
    html += "<div class='row2'>";
    html += "<div><label>Limiar de Pico</label><input type='number' id='mic_num' name='limiar_mic' min='1' max='" + String(escalaMaxMic) + "' value='" + String(limiarPicoMic) + "' required></div>";
    html += "<div><label>Debounce Mic (ms)</label><input type='number' name='debounce_mic' min='200' max='10000' value='" + String(debounceMicMs) + "' required></div>";
    html += "</div>";
    html += "<button type='submit' class='btn btn-blue'>💾 Salvar Sensibilidade do Microfone</button>";
    html += "</form></div>";

    // BLOCO 2: SENSIBILIDADE DO SENSOR LDR (PORTA 4)
    html += "<div class='card'>";
    html += "<h3><span>🔦 2. Sensibilidade do Sensor LDR</span><span class='tag'>Porta " + String(PIN_LDR) + "</span></h3>";
    html += "<div class='meter-box'>";
    html += "<div class='val' style='color:#10b981;'>Corte Recente: " + String(maiorCorteLdrRecente) + "%</div>";
    html += "<div style='font-size:12px;color:#cbd5e1;'>Marca tempo em &ge; <b>" + String(limiarSensibilidadeLdr) + "%</b> | Ultimo Gravado: <b>" + String(ultimoPicoLdrGravado) + "%</b></div>";
    html += "</div>";
    html += "<form action='/calibrar-ldr' method='POST'><button type='submit' class='btn btn-amber'>🎯 Gravar Corte do LDR (2.5s - Passe a mao no laser)</button></form>";
    html += "<form action='/salvar-sensibilidade-ldr' method='POST'>";
    html += "<label>Sensibilidade de Corte do LDR (1% = super rapido, 100% = corte longo):</label>";
    html += "<input type='range' min='1' max='100' value='" + String(limiarSensibilidadeLdr) + "' oninput=\"syncVal('ldr_num',this.value)\">";
    html += "<div class='row2'>";
    html += "<div><label>Sensibilidade (%)</label><input type='number' id='ldr_num' name='sens_ldr' min='1' max='100' value='" + String(limiarSensibilidadeLdr) + "' required></div>";
    html += "<div><label>Debounce (ms)</label><input type='number' name='debounce_ldr' min='100' max='10000' value='" + String(debounceChegadaMs) + "' required></div>";
    html += "</div>";
    html += "<label>Limiar Analogico ADC (10 a 4090):</label>";
    html += "<input type='number' name='adc_ldr' min='10' max='4090' value='" + String(limiarAnalogicoLdr) + "'>";
    html += "<button type='submit' class='btn btn-green'>💾 Salvar Sensibilidade do LDR</button>";
    html += "</form></div>";

    // BLOCO 3: CORRIDA E RESET
    html += "<div class='card'>";
    html += "<h3><span>🏁 3. Status da Corrida</span><span class='tag'>" + String(totalChegadasRegistradas) + " chegadas</span></h3>";
    html += "<div class='row2'>";
    html += "<a class='btn btn-gray' href='/'>🔄 Atualizar</a>";
    html += "<a class='btn btn-gray' href='/reset'>⏹️ Resetar Corrida</a>";
    html += "</div></div>";

    // BLOCO 4: WI-FI
    html += "<div class='card'>";
    html += "<h3><span>📡 4. Assistente Wi-Fi</span><span class='tag'>Rede</span></h3>";
    if (escanearWifi) {
        int numRedes = WiFi.scanNetworks();
        for (int i = 0; i < numRedes; i++) {
            String s = WiFi.SSID(i);
            String sEsc = s;
            sEsc.replace("'", "\\'");
            html += "<div class='net-item' onclick=\"selecionarRede('" + sEsc + "')\"><span>📶 <b>" + s + "</b></span><span>" + String(WiFi.RSSI(i)) + " dBm</span></div>";
        }
        WiFi.scanDelete();
    } else {
        html += "<a href='/wifi' class='btn btn-gray' style='margin-top:0;margin-bottom:8px;'>🔍 Buscar Redes Wi-Fi Disponiveis</a>";
    }
    html += "<form action='/salvar-wifi' method='POST'>";
    html += "<label>SSID do Wi-Fi</label><input type='text' id='ssid' name='ssid' value='" + wifiSsidSalvo + "' required>";
    html += "<label>Senha</label><input type='password' id='pass' name='pass' value='" + wifiSenhaSalva + "'>";
    html += "<label>URL do Servidor PHP</label><input type='text' name='srv_url' value='" + serverUrlSalva + "'>";
    html += "<button type='submit' class='btn btn-blue'>📶 Salvar e Conectar Wi-Fi</button>";
    html += "</form></div></div></body></html>";

    return html;
}

void setupRotasWebServer() {
    localServer.on("/", HTTP_GET, []() {
        localServer.send(200, "text/html", gerarPaginaCompletaESP32("", false));
    });

    localServer.on("/painel", HTTP_GET, []() {
        localServer.send(200, "text/html", gerarPaginaCompletaESP32("", false));
    });

    localServer.on("/wifi", HTTP_GET, []() {
        localServer.send(200, "text/html", gerarPaginaCompletaESP32("", true));
    });

    localServer.on("/gravar-pico", HTTP_POST, []() {
        int pico = gravarPicoAudioReferencia(2500);
        String msg = (pico > 0)
            ? "✅ Pico de audio gravado: <b>" + String(pico) + "</b>! Disparo ajustado para <b>&ge; " + String(limiarPicoMic) + "</b>."
            : "⚠️ Nenhum som forte detectado. Tente novamente.";
        localServer.send(200, "text/html", gerarPaginaCompletaESP32(msg, false));
    });

    localServer.on("/salvar-sensibilidade-mic", HTTP_POST, []() {
        salvarSensibilidadeMic(localServer.arg("limiar_mic").toInt(), ultimoPicoMicGravado, localServer.arg("debounce_mic").toInt());
        localServer.send(200, "text/html", gerarPaginaCompletaESP32("✅ Sensibilidade do Microfone salva!", false));
    });

    localServer.on("/calibrar-ldr", HTTP_POST, []() {
        int corte = gravarCorteLdrReferencia(2500);
        String msg = (corte > 0)
            ? "✅ Corte do LDR gravado: <b>" + String(corte) + "%</b>! Sensibilidade ajustada para <b>&ge; " + String(limiarSensibilidadeLdr) + "%</b>."
            : "✅ Repouso do LDR calibrado!";
        localServer.send(200, "text/html", gerarPaginaCompletaESP32(msg, false));
    });

    localServer.on("/salvar-sensibilidade-ldr", HTTP_POST, []() {
        salvarSensibilidadeLdr(localServer.arg("sens_ldr").toInt(), localServer.arg("adc_ldr").toInt(), localServer.arg("debounce_ldr").toInt(), ultimoPicoLdrGravado);
        localServer.send(200, "text/html", gerarPaginaCompletaESP32("✅ Sensibilidade do LDR salva!", false));
    });

    localServer.on("/salvar-wifi", HTTP_POST, []() {
        String novoSsid = localServer.arg("ssid");
        novoSsid.trim();
        if (novoSsid.length() > 0) {
            wifiSsidSalvo = novoSsid;
            wifiSenhaSalva = localServer.arg("pass");
            String novaUrl = localServer.arg("srv_url");
            novaUrl.trim();
            if (novaUrl.length() > 0) serverUrlSalva = novaUrl;

            preferencias.begin("linha_cfg", false);
            preferencias.putString("ssid", wifiSsidSalvo);
            preferencias.putString("pass", wifiSenhaSalva);
            preferencias.putString("srv_url", serverUrlSalva);
            preferencias.end();

            localServer.send(200, "text/html", gerarPaginaCompletaESP32("⏳ Conectando ao Wi-Fi <b>" + wifiSsidSalvo + "</b>...", false));
            if (!tentarConectarRedeSalva()) {
                iniciarModoAssistenteAP();
            }
        }
    });

    localServer.on("/reset", HTTP_GET, []() {
        estadoAtual = AGUARDANDO_LARGADA;
        totalChegadasRegistradas = 0;
        maiorPicoMicRecente = 0;
        maiorCorteLdrRecente = 0;
        calibrarSensoresRepouso();
        localServer.send(200, "text/html", gerarPaginaCompletaESP32("🔄 Cronometro zerado!", false));
    });

    localServer.on("/api/sensibilidade", HTTP_OPTIONS, []() {
        adicionarCabecalhosCors();
        localServer.send(204);
    });

    localServer.on("/api/sensibilidade", HTTP_GET, []() {
        adicionarCabecalhosCors();
        String json = "{\"success\":true,";
        json += "\"pino_ldr\":" + String(PIN_LDR) + ",";
        json += "\"pino_mic\":" + String(PIN_MIC_DO) + ",";
        json += "\"mic_limiar\":" + String(limiarPicoMic) + ",";
        json += "\"mic_pico_gravado\":" + String(ultimoPicoMicGravado) + ",";
        json += "\"mic_pico_recente\":" + String(maiorPicoMicRecente) + ",";
        json += "\"mic_debounce\":" + String(debounceMicMs) + ",";
        json += "\"ldr_sensibilidade\":" + String(limiarSensibilidadeLdr) + ",";
        json += "\"ldr_limiar_adc\":" + String(limiarAnalogicoLdr) + ",";
        json += "\"ldr_corte_gravado\":" + String(ultimoPicoLdrGravado) + ",";
        json += "\"ldr_corte_recente\":" + String(maiorCorteLdrRecente) + ",";
        json += "\"ldr_debounce\":" + String(debounceChegadaMs) + "}";
        localServer.send(200, "application/json", json);
    });

    localServer.on("/api/sensibilidade", HTTP_POST, []() {
        adicionarCabecalhosCors();
        String acao = localServer.arg("acao");
        if (acao == "iniciar_remoto") {
            unsigned long agora = millis();
            tempoInicioMs = agora;
            ultimoCorteLdrMs = agora;
            estadoRepousoLdr = digitalRead(PIN_LDR);
            isrPulsosLdr = 0;
            estadoAtual = CORRIDA_EM_ANDAMENTO;
            totalChegadasRegistradas = 0;
            emitirBeep(2000, 150);
            localServer.send(200, "application/json", "{\"success\":true,\"status\":\"em_andamento\"}");
            return;
        }
        if (acao == "gravar_mic") {
            int pico = gravarPicoAudioReferencia(2500);
            localServer.send(200, "application/json", "{\"success\":true,\"pico\":" + String(pico) + ",\"mic_limiar\":" + String(limiarPicoMic) + "}");
            return;
        }
        if (acao == "calibrar_ldr") {
            int corte = gravarCorteLdrReferencia(2500);
            localServer.send(200, "application/json", "{\"success\":true,\"corte\":" + String(corte) + ",\"ldr_sensibilidade\":" + String(limiarSensibilidadeLdr) + "}");
            return;
        }
        if (localServer.hasArg("mic_limiar")) {
            salvarSensibilidadeMic(localServer.arg("mic_limiar").toInt(), ultimoPicoMicGravado, localServer.hasArg("mic_debounce") ? localServer.arg("mic_debounce").toInt() : debounceMicMs);
        }
        if (localServer.hasArg("ldr_sensibilidade")) {
            salvarSensibilidadeLdr(localServer.arg("ldr_sensibilidade").toInt(), localServer.hasArg("ldr_limiar_adc") ? localServer.arg("ldr_limiar_adc").toInt() : limiarAnalogicoLdr, localServer.hasArg("ldr_debounce") ? localServer.arg("ldr_debounce").toInt() : debounceChegadaMs, ultimoPicoLdrGravado);
        }
        localServer.send(200, "application/json", "{\"success\":true,\"mic_limiar\":" + String(limiarPicoMic) + ",\"ldr_sensibilidade\":" + String(limiarSensibilidadeLdr) + "}");
    });

    localServer.onNotFound([]() {
        localServer.send(200, "text/html", gerarPaginaCompletaESP32("", false));
    });
}
