-- Script de atualização das trilhas de estudo com materiais reais
-- Projeto Edula - Sistema de Ensino com Quiz e Relatórios

USE lady;

-- Limpar dados existentes das trilhas
DELETE FROM user_progress;
DELETE FROM track_activities;
DELETE FROM study_tracks;

-- Inserir trilhas de estudo atualizadas
INSERT INTO study_tracks (id, name, description) VALUES
(1, 'Fundamentos de Programação em Python', 'Aprenda os conceitos básicos de programação usando Python, incluindo variáveis, operadores, estruturas condicionais, loops e funções.'),
(2, 'Matemática Essencial para TI', 'Fundamentos matemáticos aplicados à tecnologia da informação, incluindo álgebra linear, cálculo e estatística descritiva.'),
(3, 'Introdução ao Desenvolvimento Web', 'Primeiros passos no desenvolvimento web com HTML5, CSS3 e JavaScript para criar páginas web modernas e responsivas.');

-- Inserir atividades da trilha 1: Fundamentos de Programação em Python
INSERT INTO track_activities (track_id, title, description, content_url, activity_type, order_index) VALUES
(1, 'Variáveis e Tipos de Dados em Python', 
'Aprenda sobre variáveis, tipos de dados e operadores em Python. Este módulo cobre os conceitos fundamentais para começar a programar.', 
'https://www.dio.me/articles/variaveis-tipos-de-dados-e-operadores-em-python', 
'leitura', 1),

(1, 'Operadores em Python', 
'Compreenda os diferentes tipos de operadores em Python: aritméticos, de comparação, lógicos e de atribuição.', 
'https://www.devmedia.com.br/operadores-no-python/40693', 
'leitura', 2),

(1, 'Estruturas Condicionais (If/Else)', 
'Aprenda a usar estruturas condicionais para tomar decisões em seus programas Python.', 
'https://dev.to/franciscojdsjr/explorando-estruturas-de-controle-e-funcoes-em-python-126m', 
'leitura', 3),

(1, 'Loops em Python (While/For)', 
'Domine os loops em Python para criar repetições eficientes em seus programas.', 
'https://rocketseat.com.br/blog/artigos/post/loops-python-explicados', 
'leitura', 4),

(1, 'Funções em Python', 
'Aprenda a criar e usar funções para organizar e reutilizar código de forma eficiente.', 
'https://docs.python.org/3/tutorial/controlflow.html', 
'leitura', 5);

-- Inserir atividades da trilha 2: Matemática Essencial para TI
INSERT INTO track_activities (track_id, title, description, content_url, activity_type, order_index) VALUES
(2, 'Álgebra Linear para Computação', 
'Entenda os conceitos de álgebra linear aplicados à ciência da computação, incluindo matrizes, vetores e transformações lineares.', 
'https://www.academia.edu/42243448/%C3%81lgebra_Linear_Para_Computa%C3%A7%C3%A3o_Espinosa_Biscolla_e_Barbieri', 
'leitura', 1),

(2, 'Cálculo para Ciência da Computação', 
'Aprenda os fundamentos do cálculo diferencial e integral aplicados à computação, incluindo análise de algoritmos e otimização.', 
'https://maua.br/graduacao/cursos/ciencia-computacao/calculo-para-ciencia-da-computacao/disciplina', 
'leitura', 2),

(2, 'Estatística Descritiva', 
'Compreenda os conceitos básicos de estatística descritiva para análise de dados em tecnologia da informação.', 
'https://www.questionpro.com/blog/pt/estatisticas-descritivas/', 
'leitura', 3);

-- Inserir atividades da trilha 3: Introdução ao Desenvolvimento Web
INSERT INTO track_activities (track_id, title, description, content_url, activity_type, order_index) VALUES
(3, 'HTML5 Fundamentals', 
'Aprenda os fundamentos do HTML5, a linguagem de marcação para criar estruturas de páginas web modernas.', 
'https://html.com/', 
'leitura', 1),

(3, 'CSS3 e Design Responsivo', 
'Domine o CSS3 para estilizar páginas web e criar layouts responsivos que funcionam em todos os dispositivos.', 
'https://www.freecodecamp.org/espanol/news/crea-una-pagina-web-responsive-con-html-y-css/', 
'leitura', 2),

(3, 'JavaScript para Iniciantes', 
'Introdução ao JavaScript, a linguagem de programação que torna as páginas web interativas e dinâmicas.', 
'https://developer.mozilla.org/pt-BR/docs/Learn_web_development/Core/Scripting', 
'leitura', 3);

-- Criar tabela para questões de quiz por atividade
CREATE TABLE IF NOT EXISTS activity_quiz (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer CHAR(1) NOT NULL,
    explanation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE
);

-- Criar tabela para respostas dos quizzes
CREATE TABLE IF NOT EXISTS quiz_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    quiz_id INT NOT NULL,
    selected_answer CHAR(1) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES activity_quiz(id) ON DELETE CASCADE
);

-- Criar tabela para relatórios de progresso
CREATE TABLE IF NOT EXISTS progress_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    track_id INT NOT NULL,
    activities_completed INT DEFAULT 0,
    total_activities INT DEFAULT 0,
    quiz_score_average DECIMAL(5,2) DEFAULT 0.00,
    time_spent_minutes INT DEFAULT 0,
    last_activity_date TIMESTAMP NULL,
    report_generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (track_id) REFERENCES study_tracks(id) ON DELETE CASCADE
);



COMMIT;



# Inserir questões de quiz para as atividades
# Quiz para Atividade 1: Variáveis e Tipos de Dados em Python
INSERT INTO activity_quiz (activity_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES
(1, 'Qual é a forma correta de declarar uma variável em Python?', 'var nome = "João"', 'nome = "João"', 'string nome = "João"', 'declare nome = "João"', 'b', 'Em Python, as variáveis são declaradas simplesmente atribuindo um valor a elas usando o operador =.'),
(1, 'Qual tipo de dado representa o valor True em Python?', 'string', 'integer', 'boolean', 'float', 'c', 'True é um valor booleano em Python, representando verdadeiro.'),
(1, 'O que acontece quando você executa: print(type(42))?', '<class "int">', '<class "float">', '<class "string">', '<class "number">', 'a', 'O número 42 é um inteiro, então type(42) retorna <class "int">.'),
(1, 'Qual operador é usado para exponenciação em Python?', '^', '**', 'pow', 'exp', 'b', 'O operador ** é usado para exponenciação em Python. Por exemplo: 2**3 = 8.'),
(1, 'Como você converte uma string "123" para um número inteiro?', 'int("123")', 'number("123")', 'parse("123")', 'convert("123")', 'a', 'A função int() converte uma string numérica para um número inteiro.');

# Quiz para Atividade 2: Operadores em Python
INSERT INTO activity_quiz (activity_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES
(2, 'Qual é o resultado de 10 % 3 em Python?', '3', '1', '0', '10', 'b', 'O operador % retorna o resto da divisão. 10 dividido por 3 é 3 com resto 1.'),
(2, 'Qual operador de comparação verifica se dois valores são iguais?', '=', '==', '===', 'equals', 'b', 'O operador == compara se dois valores são iguais, enquanto = é usado para atribuição.'),
(2, 'O que retorna a expressão: 5 > 3 and 2 < 4?', 'True', 'False', 'Error', 'None', 'a', 'Ambas as condições são verdadeiras (5>3 e 2<4), então o resultado é True.'),
(2, 'Qual é o resultado de "Python" + "Programming"?', 'Error', 'PythonProgramming', 'Python Programming', 'Python+Programming', 'b', 'O operador + concatena strings em Python, juntando-as sem espaço.'),
(2, 'O que faz o operador += em Python?', 'Compara e adiciona', 'Adiciona e atribui', 'Subtrai e atribui', 'Multiplica e atribui', 'b', 'O operador += adiciona o valor à variável e atribui o resultado de volta à variável.');

# Quiz para Atividade 3: Estruturas Condicionais
INSERT INTO activity_quiz (activity_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES
(3, 'Qual é a sintaxe correta para uma estrutura if em Python?', 'if (x > 5):', 'if x > 5:', 'if x > 5 then:', 'if x > 5 {', 'b', 'Em Python, a sintaxe do if não requer parênteses e termina com dois pontos.'),
(3, 'O que acontece se a condição do if for False?', 'O programa para', 'Executa o bloco else', 'Gera um erro', 'Pula para o próximo if', 'b', 'Se a condição do if for False, o programa executa o bloco else (se existir).'),
(3, 'Qual palavra-chave é usada para múltiplas condições em Python?', 'elseif', 'elif', 'else if', 'switch', 'b', 'A palavra-chave elif é usada para múltiplas condições em Python.'),
(3, 'Como você escreve uma condição que verifica se x está entre 10 e 20?', 'if 10 < x < 20:', 'if x > 10 and x < 20:', 'if (x > 10) && (x < 20):', 'Todas as anteriores estão corretas', 'd', 'Python permite tanto a sintaxe encadeada (10 < x < 20) quanto a lógica (x > 10 and x < 20).'),
(3, 'O que é indentação em Python?', 'Comentários no código', 'Espaços para definir blocos', 'Nomes de variáveis', 'Tipos de dados', 'b', 'A indentação (espaços ou tabs) é usada em Python para definir blocos de código.');

# Quiz para Atividade 4: Loops em Python
INSERT INTO activity_quiz (activity_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES
(4, 'Qual loop é melhor para iterar sobre uma lista em Python?', 'while', 'for', 'do-while', 'repeat', 'b', 'O loop for é mais adequado para iterar sobre sequências como listas.'),
(4, 'Como você para um loop prematuramente em Python?', 'stop', 'break', 'exit', 'end', 'b', 'A palavra-chave break é usada para sair de um loop prematuramente.'),
(4, 'O que faz a palavra-chave continue em um loop?', 'Para o loop', 'Pula para a próxima iteração', 'Reinicia o loop', 'Gera um erro', 'b', 'Continue pula o resto da iteração atual e vai para a próxima iteração do loop.'),
(4, 'Qual é a sintaxe correta para um loop while?', 'while (condition):', 'while condition:', 'while condition do:', 'while condition {', 'b', 'A sintaxe do while em Python não requer parênteses e termina com dois pontos.'),
(4, 'Como você cria um loop que executa 5 vezes?', 'for i in 5:', 'for i in range(5):', 'for i = 0 to 5:', 'for (i=0; i<5; i++):', 'b', 'range(5) gera números de 0 a 4, executando o loop 5 vezes.');

# Quiz para Atividade 5: Funções em Python
INSERT INTO activity_quiz (activity_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES
(5, 'Como você define uma função em Python?', 'function nome():', 'def nome():', 'create nome():', 'func nome():', 'b', 'A palavra-chave def é usada para definir funções em Python.'),
(5, 'Como uma função retorna um valor em Python?', 'return valor', 'send valor', 'output valor', 'give valor', 'a', 'A palavra-chave return é usada para retornar valores de uma função.'),
(5, 'O que acontece se uma função não tem return?', 'Gera erro', 'Retorna None', 'Retorna 0', 'Retorna vazio', 'b', 'Funções sem return explícito retornam None automaticamente.'),
(5, 'Como você chama uma função chamada "calcular"?', 'call calcular()', 'calcular()', 'run calcular()', 'execute calcular()', 'b', 'Funções são chamadas simplesmente usando seu nome seguido de parênteses.'),
(5, 'O que são parâmetros em uma função?', 'Valores de retorno', 'Variáveis de entrada', 'Tipos de dados', 'Nomes de funções', 'b', 'Parâmetros são variáveis que recebem valores quando a função é chamada.');

# Índices para melhor performance
CREATE INDEX idx_activity_quiz_activity ON activity_quiz(activity_id);
CREATE INDEX idx_quiz_responses_user ON quiz_responses(user_id);
CREATE INDEX idx_quiz_responses_activity ON quiz_responses(activity_id);
CREATE INDEX idx_progress_reports_user ON progress_reports(user_id);
CREATE INDEX idx_progress_reports_track ON progress_reports(track_id);

# Inserir progresso inicial para usuários de teste
INSERT INTO user_progress (user_id, activity_id, status) VALUES
(1, 1, 'pendente'),
(1, 2, 'pendente'),
(1, 3, 'pendente'),
(1, 4, 'pendente'),
(1, 5, 'pendente'),
(2, 1, 'pendente'),
(2, 2, 'pendente'),
(2, 3, 'pendente'),
(5, 1, 'pendente'),
(5, 2, 'pendente');

COMMIT;


SELECT * FROM track_activities;
INSERT INTO track_activities (id, activity_name, activity_type) 
VALUES (1, 'Atividade 2: Operadores em Python', 'quiz');


INSERT INTO activity_quiz (activity_id, question, option_a, option_b, option_c, option_d, correct_option)
VALUES (1, 'Qual é o operador de exponenciação em Python?', '**', '^', 'exp()', 'pow()', 'A');
