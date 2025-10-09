-- Atualizações do banco de dados para as novas funcionalidades

-- Tabelas para o sistema de fórum
CREATE TABLE IF NOT EXISTS forum_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabelas para o sistema de trilha de estudos
CREATE TABLE IF NOT EXISTS study_tracks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS track_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    track_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_url VARCHAR(500),
    activity_order INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (track_id) REFERENCES study_tracks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    status ENUM('in_progress', 'completed') DEFAULT 'in_progress',
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_activity (user_id, activity_id)
);

-- Índices para melhor performance
CREATE INDEX idx_forum_topics_user ON forum_topics(user_id);
CREATE INDEX idx_forum_posts_topic ON forum_posts(topic_id);
CREATE INDEX idx_forum_posts_user ON forum_posts(user_id);
CREATE INDEX idx_track_activities_track ON track_activities(track_id);
CREATE INDEX idx_track_activities_order ON track_activities(activity_order);
CREATE INDEX idx_user_progress_user ON user_progress(user_id);
CREATE INDEX idx_user_progress_activity ON user_progress(activity_id);

-- Dados de exemplo para trilhas de estudo
INSERT INTO study_tracks (name, description) VALUES
('Matemática Básica', 'Trilha de estudos para aprender conceitos básicos de matemática'),
('Português Fundamental', 'Trilha de estudos para melhorar a compreensão da língua portuguesa'),
('Ciências Naturais', 'Trilha de estudos sobre conceitos básicos de ciências');

-- Atividades de exemplo para a trilha de Matemática Básica
INSERT INTO track_activities (track_id, title, description, content_url, activity_order) VALUES
(1, 'Números e Operações Básicas', 'Aprenda sobre números naturais e as quatro operações básicas', '#', 1),
(1, 'Frações e Decimais', 'Entenda como trabalhar com frações e números decimais', '#', 2),
(1, 'Geometria Básica', 'Conceitos fundamentais de geometria plana', '#', 3),
(1, 'Álgebra Introdutória', 'Primeiros passos na álgebra com equações simples', '#', 4);

-- Atividades de exemplo para a trilha de Português Fundamental
INSERT INTO track_activities (track_id, title, description, content_url, activity_order) VALUES
(2, 'Alfabeto e Fonética', 'Revisão do alfabeto e sons das letras', '#', 1),
(2, 'Formação de Palavras', 'Como as palavras são formadas na língua portuguesa', '#', 2),
(2, 'Classes Gramaticais', 'Substantivos, adjetivos, verbos e outras classes', '#', 3),
(2, 'Interpretação de Texto', 'Técnicas para melhor compreensão textual', '#', 4);

-- Atividades de exemplo para a trilha de Ciências Naturais
INSERT INTO track_activities (track_id, title, description, content_url, activity_order) VALUES
(3, 'O Corpo Humano', 'Sistemas do corpo humano e suas funções', '#', 1),
(3, 'Plantas e Fotossíntese', 'Como as plantas produzem seu próprio alimento', '#', 2),
(3, 'Estados da Matéria', 'Sólido, líquido e gasoso', '#', 3),
(3, 'Sistema Solar', 'Planetas e outros corpos celestes', '#', 4);

