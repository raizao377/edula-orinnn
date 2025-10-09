-- Script SQL para criar as tabelas do sistema de provas

-- Tabela de provas criadas pelos professores
CREATE TABLE IF NOT EXISTS provas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    materia VARCHAR(100) NOT NULL,
    tipo ENUM('diagnostica', 'avaliativa') NOT NULL DEFAULT 'diagnostica',
    tempo_limite INT DEFAULT 0, -- em minutos, 0 = sem limite
    professor_id INT NOT NULL,
    ativa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de questões das provas (apenas múltipla escolha)
CREATE TABLE IF NOT EXISTS prova_questoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prova_id INT NOT NULL,
    questao TEXT NOT NULL,
    opcao_a VARCHAR(500) NOT NULL,
    opcao_b VARCHAR(500) NOT NULL,
    opcao_c VARCHAR(500) NOT NULL,
    opcao_d VARCHAR(500) NOT NULL,
    resposta_correta CHAR(1) NOT NULL, -- a, b, c ou d
    pontuacao DECIMAL(5,2) DEFAULT 1.00,
    ordem INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prova_id) REFERENCES provas(id) ON DELETE CASCADE
);

-- Tabela de tentativas de provas pelos alunos
CREATE TABLE IF NOT EXISTS prova_tentativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prova_id INT NOT NULL,
    aluno_id INT NOT NULL,
    pontuacao_total DECIMAL(5,2) DEFAULT 0.00,
    pontuacao_maxima DECIMAL(5,2) DEFAULT 0.00,
    percentual DECIMAL(5,2) DEFAULT 0.00,
    tempo_gasto INT DEFAULT 0, -- em segundos
    status ENUM('iniciada', 'concluida', 'abandonada') DEFAULT 'iniciada',
    iniciada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    concluida_em TIMESTAMP NULL,
    FOREIGN KEY (prova_id) REFERENCES provas(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_aluno_prova (aluno_id, prova_id)
);

-- Tabela de respostas dos alunos (apenas múltipla escolha)
CREATE TABLE IF NOT EXISTS prova_respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tentativa_id INT NOT NULL,
    questao_id INT NOT NULL,
    resposta_escolhida CHAR(1) NOT NULL, -- a, b, c ou d
    pontuacao_obtida DECIMAL(5,2) DEFAULT 0.00,
    correta BOOLEAN DEFAULT FALSE,
    respondida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tentativa_id) REFERENCES prova_tentativas(id) ON DELETE CASCADE,
    FOREIGN KEY (questao_id) REFERENCES prova_questoes(id) ON DELETE CASCADE
);

-- Tabela para disponibilizar provas para turmas/alunos específicos
CREATE TABLE IF NOT EXISTS prova_disponibilidade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prova_id INT NOT NULL,
    aluno_id INT, -- NULL = disponível para todos
    turma VARCHAR(100), -- Para filtrar por turma
    data_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_fim TIMESTAMP NULL,
    tentativas_permitidas INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prova_id) REFERENCES provas(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Inserir algumas provas de exemplo
INSERT INTO provas (titulo, descricao, materia, tipo, tempo_limite, professor_id) VALUES
('Avaliação Diagnóstica - Matemática Básica', 'Avaliação para identificar o nível de conhecimento em operações básicas', 'Matemática', 'diagnostica', 30, 1),
('Prova de Português - 1º Bimestre', 'Avaliação sobre gramática e interpretação de texto', 'Português', 'avaliativa', 60, 1),
('Teste de Ciências - Sistema Solar', 'Avaliação diagnóstica sobre conhecimentos do sistema solar', 'Ciências', 'diagnostica', 45, 1);

-- Inserir questões de exemplo para a primeira prova (Matemática Básica)
INSERT INTO prova_questoes (prova_id, questao, opcao_a, opcao_b, opcao_c, opcao_d, resposta_correta, pontuacao, ordem) VALUES
(1, 'Quanto é 25 + 17?', '40', '41', '42', '43', 'c', 2.0, 1),
(1, 'Qual é o resultado de 8 × 7?', '54', '55', '56', '57', 'c', 2.0, 2),
(1, 'Quanto é 100 - 37?', '63', '64', '65', '66', 'a', 2.0, 3),
(1, 'Qual é o resultado de 144 ÷ 12?', '11', '12', '13', '14', 'b', 2.0, 4),
(1, 'Qual número é maior: 0,75 ou 3/4?', '0,75', '3/4', 'São iguais', 'Não é possível comparar', 'c', 2.0, 5);

-- Inserir questões de exemplo para a segunda prova (Português)
INSERT INTO prova_questoes (prova_id, questao, opcao_a, opcao_b, opcao_c, opcao_d, resposta_correta, pontuacao, ordem) VALUES
(2, 'Qual é o plural de "cidadão"?', 'cidadãos', 'cidadões', 'cidadães', 'cidadãos', 'a', 1.0, 1),
(2, 'Em "O gato subiu no telhado", qual é o sujeito?', 'gato', 'o gato', 'subiu', 'telhado', 'b', 1.0, 2),
(2, 'Qual palavra está corretamente acentuada?', 'médico', 'medico', 'medicô', 'mèdico', 'a', 1.0, 3),
(2, 'O que é uma metáfora?', 'Comparação com "como"', 'Figura de linguagem que compara sem usar conectivos', 'Exagero intencional', 'Repetição de sons', 'b', 1.0, 4),
(2, 'Em que pessoa está o verbo "estudamos"?', '1ª pessoa do singular', '1ª pessoa do plural', '2ª pessoa do plural', '3ª pessoa do plural', 'b', 1.0, 5);

-- Inserir questões de exemplo para a terceira prova (Ciências)
INSERT INTO prova_questoes (prova_id, questao, opcao_a, opcao_b, opcao_c, opcao_d, resposta_correta, pontuacao, ordem) VALUES
(3, 'Qual é o planeta mais próximo do Sol?', 'Vênus', 'Terra', 'Mercúrio', 'Marte', 'c', 2.0, 1),
(3, 'Quantos planetas existem no Sistema Solar?', '7', '8', '9', '10', 'b', 2.0, 2),
(3, 'Qual é o maior planeta do Sistema Solar?', 'Saturno', 'Júpiter', 'Netuno', 'Urano', 'b', 2.0, 3),
(3, 'O que causa as fases da Lua?', 'Sombra da Terra', 'Posição da Lua em relação ao Sol e Terra', 'Rotação da Lua', 'Nuvens no espaço', 'b', 2.0, 4),
(3, 'Aproximadamente quantos dias a Terra leva para dar uma volta completa ao redor do Sol?', '30 dias', '365 dias', '24 horas', '12 meses', 'b', 2.0, 5);

-- Disponibilizar as provas para todos os alunos
INSERT INTO prova_disponibilidade (prova_id, aluno_id, tentativas_permitidas) VALUES
(1, NULL, 3), -- Prova diagnóstica permite 3 tentativas
(2, NULL, 1), -- Prova avaliativa permite apenas 1 tentativa
(3, NULL, 2); -- Prova diagnóstica permite 2 tentativas

-- Criar índices para melhor performance
CREATE INDEX idx_provas_professor ON provas(professor_id);
CREATE INDEX idx_prova_questoes_prova ON prova_questoes(prova_id);
CREATE INDEX idx_prova_tentativas_aluno ON prova_tentativas(aluno_id);
CREATE INDEX idx_prova_tentativas_prova ON prova_tentativas(prova_id);
CREATE INDEX idx_prova_respostas_tentativa ON prova_respostas(tentativa_id);
CREATE INDEX idx_prova_disponibilidade_prova ON prova_disponibilidade(prova_id);

