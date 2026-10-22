/**
 * Arquivo de Configuração de Hardware e Rede
 * Linha de Chegada - ESP32
 */

#ifndef CONFIG_H
#define CONFIG_H

// ==========================================
// 1. Configurações de Rede Wi-Fi
// ==========================================
#define WIFI_SSID     "SEU_WIFI_NOME"
#define WIFI_PASSWORD "SUA_SENHA_WIFI"

// URL base do servidor PHP (Ajuste para o IP do computador na rede local)
// Exemplo: "http://192.168.1.100/linha_de_chegada"
#define SERVER_BASE_URL "http://192.168.1.100/Linha%20de%20chegada"

// ==========================================
// 2. Mapeamento de Pinos do ESP32 (GPIOs)
// ==========================================

// Módulo Microfone (Detecção do Disparo de Largada - MDA-01)
// Pino digital (DO) com ajuste de sensibilidade no potenciômetro do módulo
#define PIN_MIC_DO      4       // Pino digital do microfone
#define PIN_MIC_AO      35      // (Opcional) Pino analógico para leitura de amplitude

// Módulo Fotoresistor LDR (Detecção da Linha de Chegada - SDM-01)
// Conectado em divisor de tensão com resistor de 10k ohm
#define PIN_LDR_AO      34      // Leitura analógica da luminosidade do LDR
#define PIN_LASER       18      // (Opcional) Saída 3.3V/5V para alimentar o diodo laser

// Indicadores Visuais e Sonoros
#define PIN_LED_STATUS  2       // LED onboard / LED Verde de Pronto
#define PIN_LED_CHEGADA 19      // LED Vermelho de corte da linha de chegada
#define PIN_BUZZER      23      // Buzzer sonoro de confirmação

// ==========================================
// 3. Parâmetros de Calibração e Filtros
// ==========================================

// Limiar do LDR (0 a 4095 no ESP32 ADC)
// Quando o feixe laser é cortado pelo atleta, o valor analógico muda drasticamente
#define LDR_THRESHOLD_DEFAULT 2200

// Tempo mínimo em milissegundos para ignorar múltiplos cortes consecutivos (debounce)
#define DEBOUNCE_CHEGADA_MS   600

// Tempo de debounce para o microfone não disparar duas vezes no mesmo estampido
#define DEBOUNCE_MIC_MS       3000

#endif // CONFIG_H
