-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS edula_testts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edula_testts;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    matricula VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'professor') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de provas/avaliações
CREATE TABLE IF NOT EXISTS provas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    materia VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'diagnostica',
    professor_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de questões das provas
CREATE TABLE IF NOT EXISTS questoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prova_id INT NOT NULL,
    pergunta TEXT NOT NULL,
    opcao_a VARCHAR(500),
    opcao_b VARCHAR(500),
    opcao_c VARCHAR(500),
    opcao_d VARCHAR(500),
    resposta_correta CHAR(1) NOT NULL,
    ordem INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prova_id) REFERENCES provas(id) ON DELETE CASCADE
);

-- Tabela de respostas dos alunos
CREATE TABLE IF NOT EXISTS respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prova_id INT NOT NULL,
    questao_id INT NOT NULL,
    resposta_escolhida CHAR(1),
    is_correct BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (prova_id) REFERENCES provas(id) ON DELETE CASCADE,
    FOREIGN KEY (questao_id) REFERENCES questoes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_questao (user_id, questao_id)
);

-- Tabela de resultados das provas
CREATE TABLE IF NOT EXISTS resultados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    prova_id INT NOT NULL,
    pontuacao INT NOT NULL DEFAULT 0,
    total_questoes INT NOT NULL DEFAULT 0,
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status ENUM('em_andamento', 'finalizada') DEFAULT 'em_andamento',
    tempo_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tempo_fim TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (prova_id) REFERENCES provas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_prova (user_id, prova_id)
);

-- Inserção de dados de teste
INSERT INTO users (nome, matricula, password, role) VALUES
('João Silva', '12345', 'senha123', 'user'),
('Maria Santos', '67890', 'maria456', 'user'),
('Professor Admin', 'admin', 'admin123', 'professor'),
('Ana Costa', 'prof001', 'prof123', 'professor'),
('Carlos Oliveira', '11111', 'carlos123', 'user');

-- Inserção de provas de exemplo
INSERT INTO provas (titulo, descricao, materia, tipo, professor_id) VALUES
('Avaliação Diagnóstica de Matemática', 'Avaliação para verificar conhecimentos básicos em matemática', 'Matemática', 'diagnostica', 3),
('Teste de Português', 'Avaliação de interpretação de texto e gramática', 'Português', 'diagnostica', 3),
('Prova de Ciências', 'Conhecimentos gerais em ciências naturais', 'Ciências', 'diagnostica', 4);

-- Inserção de questões de exemplo
INSERT INTO questoes (prova_id, pergunta, opcao_a, opcao_b, opcao_c, opcao_d, resposta_correta, ordem) VALUES
(1, 'Quanto é 2 + 2?', '3', '4', '5', '6', 'b', 1),
(1, 'Qual é a raiz quadrada de 16?', '2', '3', '4', '5', 'c', 2),
(1, 'Quanto é 10 x 5?', '45', '50', '55', '60', 'b', 3),
(2, 'Qual é o plural de "animal"?', 'animais', 'animals', 'animales', 'animalos', 'a', 1),
(2, 'O que é um substantivo?', 'Palavra que indica ação', 'Palavra que nomeia seres', 'Palavra que qualifica', 'Palavra que liga', 'b', 2),
(3, 'Qual é o planeta mais próximo do Sol?', 'Terra', 'Vênus', 'Mercúrio', 'Marte', 'c', 1),
(3, 'O que é fotossíntese?', 'Respiração das plantas', 'Processo de produção de alimento pelas plantas', 'Reprodução das plantas', 'Crescimento das plantas', 'b', 2);

-- Criação de índices para melhor performance
CREATE INDEX idx_users_matricula ON users(matricula);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_provas_professor ON provas(professor_id);
CREATE INDEX idx_questoes_prova ON questoes(prova_id);
CREATE INDEX idx_respostas_user ON respostas(user_id);
CREATE INDEX idx_respostas_prova ON respostas(prova_id);
CREATE INDEX idx_resultados_user ON resultados(user_id);
CREATE INDEX idx_resultados_prova ON resultados(prova_id);



-- Tabela de trilhas de estudo
CREATE TABLE IF NOT EXISTS study_tracks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de atividades das trilhas de estudo
CREATE TABLE IF NOT EXISTS track_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    track_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_url VARCHAR(255),
    activity_type ENUM("leitura", "video", "exercicio", "projeto") NOT NULL,
    order_index INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (track_id) REFERENCES study_tracks(id) ON DELETE CASCADE
);

-- Tabela de progresso do usuário nas atividades
CREATE TABLE IF NOT EXISTS user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    status ENUM("pendente", "em_progresso", "completed") NOT NULL DEFAULT "pendente",
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_activity (user_id, activity_id)
);

-- Índices para melhor performance
CREATE INDEX idx_track_activities_track ON track_activities(track_id);
CREATE INDEX idx_user_progress_user ON user_progress(user_id);
CREATE INDEX idx_user_progress_activity ON user_progress(activity_id);


