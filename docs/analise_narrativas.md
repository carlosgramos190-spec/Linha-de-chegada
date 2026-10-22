# 📑 Análise das Narrativas de Casos de Uso (PNGs) - Linha de Chegada

Este documento apresenta a análise técnica detalhada dos diagramas de caso de uso presentes nas imagens do repositório:
- `Captura de tela 2026-10-22 162841.png` (**SDM-01**)
- `Captura de tela 2026-10-22 162854.png` (**MDA-01**)

---

## 1. Análise do Caso de Uso MDA-01 (`Captura de tela 2026-10-22 162854.png`)

```
+-------------------------------------------------------------------------------+
| Nome do caso de uso : Registrar tempo                                        |
| Identificador       : MDA-01                                                  |
| Ator principal      : Microfone                                               |
| Ator secundário     : N.A                                                     |
| Resumo              : Um microfone que recebe um som de disparo no início      |
|                       da corrida                                              |
| Pré-condições       : Iniciar cronômetro zerado                              |
| Pós-condições       : N.A                                                     |
| Ações do Ator       : Iniciar o cronômetro                                   |
| Resposta do sistema : Iniciar o cronômetro zerado                            |
+-------------------------------------------------------------------------------+
```

### 🔍 Interpretação Técnica e Engenharia Reversa
1. **Ator (Microfone / Placa de Detecção Sonora):**
   - Na prática esportiva (atletismo, natação, ciclismo), o início é dado por um **tiro de largada** (ou sinal sonoro sonoro forte).
   - O microfone acoplado ao ESP32 fica na linha de largada ou na estação principal.
2. **Pré-condição (Iniciar cronômetro zerado):**
   - O sistema web e o microcontrolador devem estar no estado `aguardando_largada`, com tempo em `00:00.000` ms.
   - Qualquer corrida anterior deve estar finalizada e os contadores zerados (implementado em `api/resetar_corrida.php`).
3. **Ação do Ator & Resposta do Sistema:**
   - Ao detectar a onda sonora de amplitude superior ao limiar calibrado (potenciômetro do LM393 ou ADC do ESP32), o ESP32 grava `t0 = millis()`.
   - Dispara imediatamente requisição HTTP POST para `api/iniciar_corrida.php`.
   - O cronômetro parte exatamente de zero, sincronizando o front-end via polling (`api/status_corrida.php`) e emitindo notificação TOAST.

---

## 2. Análise do Caso de Uso SDM-01 (`Captura de tela 2026-10-22 162841.png`)

```
+-------------------------------------------------------------------------------+
| Nome do caso de uso : Registrar tempo                                        |
| Identificador       : SDM-01                                                  |
| Ator principal      : Sensor                                                  |
| Ator secundário     : N.A                                                     |
| Resumo              : Um sensor de movimento que registra o tempo de          |
|                       passagem de algo                                        |
| Pré-condições       : Iniciar o cronômetro                                    |
| Pós-condições       : N.A                                                     |
| Ações do Ator       : Registrar quando algo passar                            |
| Resposta do sistema : Anotar o tempo que foi registrado em ordem cronológica  |
+-------------------------------------------------------------------------------+
```

### 🔍 Interpretação Técnica e Engenharia Reversa
1. **Ator (Sensor de Movimento / Fotoresistor LDR + Feixe Laser):**
   - Um diodo emissor laser fica apontado diretamente para a superfície fotossensível do LDR do outro lado da pista.
   - Enquanto nada passa, a incidência de luz laser mantém a leitura em valor alto (baixa resistência).
   - Ao passar um competidor (atleta, veículo ou objeto), o corpo bloqueia o laser.
2. **Pré-condição (Iniciar o cronômetro):**
   - O caso MDA-01 **obrigatoriamente deve ter ocorrido antes**. Passagens no sensor antes da largada são ignoradas pelo ESP32 e rejeitadas pela API (`api/registrar_tempo.php`).
3. **Ações do Ator & Resposta do Sistema:**
   - **Registrar quando algo passar**: O ESP32 calcula `delta_t = millis() - t0` com precisão de milissegundo de hardware (imune a latências da rede Wi-Fi).
   - **Anotar o tempo registrado em ordem cronológica**:
     - O backend PDO insere na tabela `registros_tempo` com o `tempo_ms`, `tempo_formatado` e calcula automaticamente a colocação (1º lugar, 2º lugar, 3º lugar...).
     - O frontend exibe a lista ordenada temporalmente e hierarquicamente no painel.

---

## 3. Integração com a Câmera do Celular (Photo Finish)

Em disputas acirradas de linha de chegada, o olho humano ou o tempo puro podem deixar dúvidas sobre milímetros de diferença.  
Por solicitação do projeto, foi adicionado um **smartphone** posicionado na linha de chegada:

1. O celular acessa `camera.php` na rede local.
2. Exibe o feed de vídeo com uma linha guia laser vermelha vertical sobreposta (`camera-crosshair`).
3. Quando o ESP32 aciona o endpoint `api/registrar_tempo.php` (SDM-01):
   - O evento é propagado para o celular via polling de alta frequência.
   - O celular automaticamente captura o frame do instante exato (`canvas.toDataURL`), enviando via POST para `api/salvar_foto.php`.
   - A foto é salva em `uploads/photo_finish/` e associada ao registro de tempo do atleta no banco MySQL.
4. No Dashboard e no Histórico, o usuário pode clicar em **"Ver Foto"** para abrir o **Modal com Zoom do Photo Finish** e a linha de chegada virtual sobreposta!

---

## 4. Matriz de Rastreabilidade

| Requisito / Caso de Uso | Arquivo no ESP32 | Backend PHP | Banco de Dados | Interface Web |
| :--- | :--- | :--- | :--- | :--- |
| **MDA-01 (Microfone)** | `verificarMicrofoneLargada()` em `linha_de_chegada.ino` | `api/iniciar_corrida.php` | `corridas.data_hora_inicio`, `corridas.status = 'em_andamento'` | Cronômetro digital, Toasts, Botão de disparo manual |
| **SDM-01 (LDR + Laser)** | `verificarLdrLinhaChegada()` em `linha_de_chegada.ino` | `api/registrar_tempo.php` | `registros_tempo.tempo_ms`, `registros_tempo.posicao` | Tabela cronológica de tempos, badge de colocação |
| **Photo Finish (Celular)** | Gatilho via evento HTTP | `api/salvar_foto.php` | `registros_tempo.foto_path` | `camera.php`, Modal com zoom da foto e linha virtual |
| **Segurança contra SQLi** | N.A | `config/bd.php` (PDO) | Statements preparados (`prepare/execute`) | Proteção global ativa |
| **UX Responsiva / Mobile** | WebServer de calibração | APIs RESTful JSON | UTF-8 / InnoDB | Tailwind CSS Mobile-First, Toasts e Modais nativos |
