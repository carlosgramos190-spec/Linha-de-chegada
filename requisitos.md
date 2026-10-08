# Linha de Chegada

## Objetivos
Vamos usar um ESP32 para captar o movimento de quem passar por sua frente, também vamos utilizar um módulo sensor de detecção de som para quando o som do disparo que da o inicio da corrida for feito iniciar o cronometro, um módulo sensor de fotoresistor para quando algo passar por sua frente travar o cronometro, por fim iremos usar um laser para que seja possível visualizar quem passou primeiro.

crie uma tela para que possamos ver o cronometro e os tempos que foram registrados, essa tela irá ficar hospedada em um servidor 


### Stack Tecnlógico
- Backend: PHP estruturado com sessões nativas
- Banco de dados: MySQL (PDO para segurança)
- Frontend: HTML5, PHP, CSS, Tailwind CSS

#### Regras de negócio (CORE)
- Um sensor de movimento que registra o tempo de passagem de algo
 Registrar quando algo passar 
Anotar o tempo que foi registrado em ordem cronológica
- Um microfone que recebe um som de disparo no inicio da corrida 
<img width="1053" height="81" alt="image" src="https://github.com/user-attachments/assets/815a533f-9698-4479-a678-ce60eeda04cc" />




##### Regras Globais
- Use sempre PDO para conexão e queries no MySQL para evitar SQL Injections
- Mantenha o código limpo e comente apenas logicas complexas.
- Separe os arquivos de forma lógica: um arquivo para conexão com a base (bd.php) e scripts de backend isolados e views em HTML5/PHP, nunca faça o sistema como um monolito, deixe sempre separados todas as regras para facilitar os futuros upgrades.
 - Estilize as telas em Tailwind de forma responsiva priorizando o MobileFrist.
 - Retorne sempre as mensagens de erros de forma claras na interface para o usuário (TOAST)
 - sempre trate as mensagens de caixa de mensagens nativas do navegador em um MODAL
