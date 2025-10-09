-- Script SQL para criar as tabelas do sistema de materiais didáticos

-- Tabela de categorias de materiais
CREATE TABLE IF NOT EXISTS material_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    cor VARCHAR(7) DEFAULT '#0ef', -- Cor em hexadecimal
    icone VARCHAR(50) DEFAULT 'bx-book', -- Classe do ícone Boxicons
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de materiais didáticos
CREATE TABLE IF NOT EXISTS materiais_didaticos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    categoria_id INT,
    materia VARCHAR(100) NOT NULL,
    tipo_arquivo ENUM('pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'md', 'video', 'audio', 'imagem', 'link') NOT NULL,
    nome_arquivo VARCHAR(255), -- Nome do arquivo no servidor
    caminho_arquivo VARCHAR(500), -- Caminho completo do arquivo
    url_externa VARCHAR(500), -- Para links externos
    tamanho_arquivo BIGINT DEFAULT 0, -- Tamanho em bytes
    professor_id INT NOT NULL,
    publico BOOLEAN DEFAULT TRUE, -- Se está disponível para todos os alunos
    ativo BOOLEAN DEFAULT TRUE,
    downloads INT DEFAULT 0, -- Contador de downloads
    visualizacoes INT DEFAULT 0, -- Contador de visualizações
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES material_categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de acesso aos materiais (para controle de quem pode acessar)
CREATE TABLE IF NOT EXISTS material_acesso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    aluno_id INT, -- NULL = todos os alunos
    turma VARCHAR(100), -- Para filtrar por turma
    data_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_fim TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materiais_didaticos(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de histórico de downloads/visualizações
CREATE TABLE IF NOT EXISTS material_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    aluno_id INT NOT NULL,
    acao ENUM('visualizacao', 'download') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materiais_didaticos(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela de tags para materiais (para facilitar busca)
CREATE TABLE IF NOT EXISTS material_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    tag VARCHAR(50) NOT NULL,
    FOREIGN KEY (material_id) REFERENCES materiais_didaticos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_material_tag (material_id, tag)
);

-- Inserir categorias padrão
INSERT INTO material_categorias (nome, descricao, cor, icone) VALUES
('Livros e Apostilas', 'Livros didáticos, apostilas e material de leitura', '#2196F3', 'bx-book'),
('Exercícios e Atividades', 'Listas de exercícios, atividades práticas e tarefas', '#4CAF50', 'bx-edit'),
('Apresentações', 'Slides, apresentações e material visual', '#FF9800', 'bx-slideshow'),
('Vídeos Educativos', 'Vídeo-aulas, documentários e conteúdo audiovisual', '#F44336', 'bx-video'),
('Áudios e Podcasts', 'Áudios educativos, podcasts e material sonoro', '#9C27B0', 'bx-volume-full'),
('Jogos e Interativos', 'Jogos educativos e material interativo', '#00BCD4', 'bx-joystick'),
('Referências e Links', 'Links úteis, referências bibliográficas e recursos externos', '#607D8B', 'bx-link'),
('Provas e Avaliações', 'Provas anteriores, gabaritos e material de avaliação', '#795548', 'bx-clipboard');

-- Inserir alguns materiais de exemplo
INSERT INTO materiais_didaticos (titulo, descricao, categoria_id, materia, tipo_arquivo, nome_arquivo, caminho_arquivo, professor_id, publico) VALUES
('Números Naturais - Conceitos Básicos', 'Material introdutório sobre números naturais e operações básicas', 1, 'Matemática', 'pdf', 'numeros_naturais.pdf', 'static/materials/numeros_naturais.pdf', 1, TRUE),
('Alfabeto Português', 'Guia completo do alfabeto português com exercícios', 1, 'Português', 'pdf', 'alfabeto.pdf', 'static/materials/alfabeto.pdf', 1, TRUE),
('O Corpo Humano', 'Estudo dos sistemas do corpo humano', 1, 'Ciências', 'pdf', 'corpo_humano.pdf', 'static/materials/corpo_humano.pdf', 1, TRUE),
('Variáveis e Tipos de Dados', 'Introdução à programação: variáveis e tipos', 1, 'Programação', 'pdf', 'variaveis_tipos_operadores.pdf', 'static/materials/variaveis_tipos_operadores.pdf', 1, TRUE),
('Estruturas Condicionais', 'Estruturas de controle em programação', 1, 'Programação', 'pdf', 'estruturas_condicionais.pdf', 'static/materials/estruturas_condicionais.pdf', 1, TRUE);

-- Disponibilizar os materiais para todos os alunos
INSERT INTO material_acesso (material_id, aluno_id) VALUES
(1, NULL), (2, NULL), (3, NULL), (4, NULL), (5, NULL);

-- Inserir algumas tags de exemplo
INSERT INTO material_tags (material_id, tag) VALUES
(1, 'matemática'), (1, 'números'), (1, 'básico'),
(2, 'português'), (2, 'alfabeto'), (2, 'letras'),
(3, 'ciências'), (3, 'corpo'), (3, 'humano'),
(4, 'programação'), (4, 'variáveis'), (4, 'python'),
(5, 'programação'), (5, 'condicionais'), (5, 'python');

-- Criar índices para melhor performance
CREATE INDEX idx_materiais_professor ON materiais_didaticos(professor_id);
CREATE INDEX idx_materiais_categoria ON materiais_didaticos(categoria_id);
CREATE INDEX idx_materiais_materia ON materiais_didaticos(materia);
CREATE INDEX idx_materiais_tipo ON materiais_didaticos(tipo_arquivo);
CREATE INDEX idx_material_acesso_material ON material_acesso(material_id);
CREATE INDEX idx_material_historico_material ON material_historico(material_id);
CREATE INDEX idx_material_historico_aluno ON material_historico(aluno_id);
CREATE INDEX idx_material_tags_material ON material_tags(material_id);
CREATE INDEX idx_material_tags_tag ON material_tags(tag);

