# 🏁 Linha de Chegada - Cronometragem Esportiva com ESP32 & Photo Finish

Sistema completo de cronometragem eletrônica de alta precisão para corridas e disputas esportivas, utilizando **ESP32**, sensor óptico **LDR com feixe laser**, **módulo de som/microfone** para largada acústica e **smartphone** para captura de imagens em alta velocidade (**Photo Finish**).

---

## 📸 Casos de Uso e Narrativas Implementadas

Baseado nas especificações e narrativas do repositório:
- **MDA-01 (`Captura de tela 2026-10-22 162854.png`)**: **Microfone de Largada**
  - Detecta o estampido / tiro de início da corrida.
  - Pré-condição: Cronômetro iniciado zerado (`00:00.000`).
- **SDM-01 (`Captura de tela 2026-10-22 162841.png`)**: **Sensor LDR + Laser na Linha de Chegada**
  - Detecta o momento exato em que o atleta corta o feixe laser.
  - Pré-condição: Cronômetro em andamento.
  - Resposta do sistema: Anota os tempos em ordem cronológica e define posições (1º, 2º, 3º lugar...).
- **Photo Finish com Celular**:
  - Smartphone posicionado perpendicularmente à linha de chegada que fotografa automaticamente o instante do corte do laser.

---

## 🛠️ Stack Tecnológico

| Camada | Tecnologia | Descrição |
| :--- | :--- | :--- |
| **Microcontrolador** | C / C++ (ESP32) | Leitura de ADC para LDR, interrupções, Wi-Fi e HTTPClient |
| **Backend** | PHP 8.x estruturado | Sessões nativas, endpoints RESTful modulares |
| **Banco de Dados** | MySQL com PDO | Prepared statements com proteção estrita contra SQL Injection |
| **Frontend** | HTML5, Tailwind CSS, JS Vanilla | Interface Mobile-First responsiva, Toasts e Modais sem alerts nativos |
| **Câmera** | HTML5 MediaDevices API & Canvas | Captura fotográfica instantânea sincronizada no navegador do celular |

---

## 📁 Estrutura de Arquivos do Projeto

```
Linha de chegada/
│
├── config/
│   └── bd.php                  # Conexão PDO com MySQL e auto-inicialização de tabelas
├── bd.php                      # Arquivo de conexão na raiz (requisito do projeto)
├── database/
│   └── schema.sql              # Script SQL com tabelas corridas, registros_tempo e configuracoes
│
├── api/                        # Endpoints isolados de backend (sem monólito)
│   ├── iniciar_corrida.php     # Disparo de largada (MDA-01 - Microfone ou manual)
│   ├── registrar_tempo.php     # Registro de corte do laser (SDM-01 - LDR)
│   ├── status_corrida.php      # Polling de alta frequência do cronômetro e novos eventos
│   ├── salvar_foto.php         # Upload da imagem capturada pelo celular (Photo Finish)
│   ├── resetar_corrida.php     # Prepara o cronômetro zerado para nova largada
│   ├── listar_tempos.php       # Consulta tempos e histórico por corrida
│   └── excluir_tempo.php       # Limpeza e exclusão de registros
│
├── includes/
│   ├── header.php              # Header com Tailwind CSS e navegação responsiva
│   ├── footer.php              # Rodapé com injeção de modais e toasts
│   ├── toast.php               # Componente de Notificações TOAST
│   ├── modal.php               # Componente MODAL para substituir window.alert/confirm
│   └── functions.php           # Helpers de formatação de milissegundos e respostas JSON
│
├── index.php                   # Dashboard principal do cronômetro esportivo
├── camera.php                  # Interface do celular com mira laser para Photo Finish
├── historico.php               # Consulta de histórico e resultados anteriores
│
├── assets/
│   ├── css/
│   │   └── style.css           # Estilos do display digital e mira laser
│   └── js/
│       ├── toast.js            # Engine de toasts
│       ├── modal.js            # Engine de modais interativos
│       ├── cronometro.js       # Controle de precisão do cronômetro sincronizado
│       └── camera.js           # Gerenciamento da câmera e captura automática
│
├── uploads/
│   └── photo_finish/           # Armazenamento das fotos dos atletas na linha de chegada
│
├── esp32/
│   ├── linha_de_chegada.ino    # Firmware C/C++ para ESP32
│   ├── config.h                # Configurações de Wi-Fi, pinos e URL do servidor
│   └── README_ESP32.md         # Manual de ligação dos pinos e calibração
│
├── docs/
│   └── analise_narrativas.md   # Análise aprofundada dos casos de uso SDM-01 e MDA-01
├── requisitos.md               # Especificações originais
└── README.md                   # Esta documentação
```

---

## 🚀 Como Executar o Projeto

### 1. Servidor Web (PHP & MySQL)
1. Coloque a pasta do projeto no diretório do seu servidor web (ex: `htdocs` do XAMPP/Apache ou use o servidor embutido do PHP):
   ```bash
   php -S 0.0.0.0:8000
   ```
2. Importe o banco de dados via phpMyAdmin ou MySQL CLI:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   *(Ou simplesmente acesse a aplicação pelo navegador: o script `config/bd.php` cria o banco e tabelas automaticamente caso o usuário MySQL tenha permissão)*.

### 2. Configuração do Celular para Photo Finish
1. Abra o navegador do celular no endereço da rede local: `http://<ip-do-servidor>:8000/camera.php`.
2. Autorize o acesso à câmera traseira.
3. Posicione o celular em um suporte na linha de chegada, alinhando a **linha vermelha na tela** com o ponto onde o laser atinge o sensor LDR.

### 3. ESP32 (Microfone e LDR com Laser)
1. Abra `esp32/linha_de_chegada.ino` na Arduino IDE.
2. Edite `esp32/config.h` com seu Wi-Fi e o IP do servidor PHP.
3. Conecte os pinos conforme a tabela em `esp32/README_ESP32.md`.
4. Faça o upload para a placa ESP32.

---

## 🏆 Regras Globais Atendidas

- [x] **Segurança MySQL PDO**: Prepared statements sem concatenação em todas as rotas para blindagem contra SQL Injection.
- [x] **Não Monolítico**: Separação estrita entre `config/bd.php`, APIs RESTful em `api/`, componentes modulares em `includes/` e views em HTML5/PHP.
- [x] **Mobile-First com Tailwind CSS**: Interface totalmente fluida em smartphones, tablets e monitores.
- [x] **Mensagens em TOAST**: Notificações instantâneas e discretas para ações do usuário e eventos de sensores.
- [x] **Modais Nativos**: Nenhum diálogo bloqueante nativo do navegador (`alert`, `confirm`) é utilizado; todos utilizam o componente visual customizado `Modal`.
