# 🔌 Guia de Hardware e Montagem do ESP32 - Linha de Chegada

Este guia detalha as ligações elétricas, pinagem e calibração dos sensores do **ESP32** para o sistema Linha de Chegada.

---

## 📋 Lista de Componentes

| Componente | Quantidade | Função |
| :--- | :---: | :--- |
| **Placa ESP32 DevKit V1** (30 ou 38 pinos) | 1 | Microcontrolador com Wi-Fi nativo e timers de alta precisão |
| **Módulo Sensor de Som / Microfone** (KY-038 ou LM393) | 1 | Detecta o estampido / tiro de largada (**Caso MDA-01**) |
| **Sensor Fotoresistor LDR** (5mm ou módulo) | 1 | Detecta a passagem do atleta (**Caso SDM-01**) |
| **Módulo Diodo Laser** (KY-008 650nm 5V) | 1 | Feixe de luz focado diretamente no LDR |
| **Resistor de 10kΩ** | 1 | Divisor de tensão com o LDR (se não usar módulo com placa) |
| **Smartphone** | 1 | Posicionado na linha de chegada para foto finish |
| **LED Verde + LED Vermelho** | 2 | Status de pronto e indicação de corte de feixe |
| **Buzzer Ativo 5V/3.3V** | 1 | Feedback sonoro instantâneo |
| **Protoboard e Jumpers** | - | Montagem e conexões |

---

## 📌 Esquema de Pinagem e Conexões

```
                      +-------------------+
                      |      ESP32        |
                      +-------------------+
  (Disparo Largada)   |                   |
  Módulo Som [DO] --->| GPIO 4            |
                      |                   |
  (Linha de Chegada)  |                   |
  LDR [Divisor 10k] ->| GPIO 34 (ADC1)    |
  Diodo Laser [+] --->| GPIO 18 (ou 3.3V) |
                      |                   |
  LED Status (Verde) -| GPIO 2            |
  LED Corte (Vermelho)| GPIO 19           |
  Buzzer [+] -------->| GPIO 23           |
                      |                   |
  GND Geral --------->| GND               |
  VCC (3.3V / 5V) --->| 3V3 / VIN         |
                      +-------------------+
```

### Detalhe do Divisor de Tensão do LDR (se usar LDR avulso):
```
  3.3V ----[ LDR 5mm ]----+----[ Resistor 10kΩ ]---- GND
                          |
                          +---> Entrada GPIO 34 (ADC1 ESP32)
```
*Quando o feixe laser atinge o LDR, a resistência cai para ~100Ω (tensão alta próxima de 3.3V no ADC).*  
*Quando o atleta corta o feixe, a resistência sobe para >100kΩ (tensão cai bruscamente).*

---

## ⚙️ Calibração dos Sensores

### 1. Calibração do Microfone (Largada MDA-01)
1. Gire o potenciômetro azul do módulo de microfone até o LED indicador apagar.
2. Dê uma salva de palmas ou bata duas madeiras (simulando tiro de largada).
3. O LED do módulo deve piscar apenas no momento do som forte, evitando ruídos de fundo.

### 2. Calibração do Feixe Laser e LDR (Chegada SDM-01)
1. Conecte o ESP32 ao Wi-Fi.
2. Abra no celular ou navegador o endereço IP do ESP32 exibido no Serial Monitor (Ex: `http://192.168.1.150/`).
3. A tela de calibração exibirá o valor analógico do LDR em tempo real.
4. Com o laser alinhado no LDR, o valor ficará em torno de **3000 a 4000**.
5. Ao passar a mão na frente do laser, o valor cairá para menos de **1800**.
6. Ajuste a constante `LDR_THRESHOLD_DEFAULT` em `config.h` para um valor intermediário (ex: `2200`).

---

## 🚀 Como Gravar o Código no ESP32

1. Abra a pasta `esp32/` na **Arduino IDE** ou **PlatformIO**.
2. Instale o pacote de placas ESP32 pela URL de placas adicionais:
   `https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json`
3. Selecione a placa: `ESP32 Dev Module`.
4. Edite `config.h` com o seu **SSID**, **SENHA** e o **IP do Servidor PHP**.
5. Conecte o ESP32 via USB e clique em **Upload**.
