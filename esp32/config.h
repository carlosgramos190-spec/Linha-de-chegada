/**
 * Arquivo de Configuração de Hardware e Rede
 * Linha de Chegada - ESP32
 */

#ifndef CONFIG_H
#define CONFIG_H

// ==========================================
// 1. Configurações do Assistente Wi-Fi (Ponto de Acesso / AP)
// ==========================================
// Nome da rede que o ESP32 cria quando não está conectado ou quando perde a rede
#define AP_SSID       "LinhaDeChegada-WiFi"
#define AP_PASSWORD   ""   // Deixe vazio "" para rede aberta (ou mínimo 8 caracteres para senha)

// Wi-Fi padrão opcional (caso queira deixar pré-configurado além do Assistente Web)
#define WIFI_SSID     "SEU_WIFI_NOME"
#define WIFI_PASSWORD "SUA_SENHA_WIFI"

// URL base padrão do servidor PHP (Pode ser alterada pelo Assistente Web do ESP32)
#define SERVER_BASE_URL "http://192.168.1.100/Linha%20de%20chegada"

// Intervalo para tentar reconectar à rede caso o sinal caia (em ms)
#define WIFI_RECONNECT_INTERVAL_MS 20000

// ==========================================
// 2. Mapeamento de Pinos do ESP32 (GPIOs)
// ==========================================

// Sensor LDR (Detecção da Linha de Chegada - SDM-01) -> Ligado na Porta 4 (GPIO 4)
#define PIN_LDR         4       // Porta 4 (GPIO 4)
#define PIN_LDR_AO      4       // Alias de compatibilidade

// Módulo Microfone (Detecção do Disparo de Largada - MDA-01) -> Ligado na Porta 5 (GPIO 5)
#define PIN_MIC_DO      5       // Porta 5 (GPIO 5)

// Saída opcional para alimentar o Diodo Laser
#define PIN_LASER       18      // (Opcional) Saída 3.3V para alimentar o diodo laser

// Indicadores Visuais e Sonoros
#define PIN_LED_STATUS  2       // LED onboard / LED Verde de Pronto
#define PIN_LED_CHEGADA 19      // LED Vermelho de corte da linha de chegada
#define PIN_BUZZER      23      // Buzzer sonoro de confirmação

// ==========================================
// 3. Parâmetros Padrão de Sensibilidade (Microfone e LDR)
// ==========================================

// Sensibilidade (Limiar de Pico de Áudio) padrão inicial do Microfone (1 = máxima sensibilidade)
#define MIC_PICO_DEFAULT        1

// Sensibilidade padrão do LDR na Porta 4 (1 a 100% de confirmação de corte do feixe)
#define LDR_SENSIBILIDADE_DEFAULT 30

// Limiar analógico padrão do LDR (0 a 4095 caso use porta ADC1)
#define LDR_THRESHOLD_DEFAULT   2200

// Usa leitura digital com filtro de sensibilidade na Porta 4 (compatível com Wi-Fi ativo)
#define LDR_USAR_LEITURA_DIGITAL true

// Tempo mínimo em milissegundos para ignorar múltiplos cortes consecutivos (debounce)
#define DEBOUNCE_CHEGADA_MS     600

// Tempo de debounce para o microfone não disparar duas vezes no mesmo estampido
#define DEBOUNCE_MIC_MS         3000

#endif // CONFIG_H
