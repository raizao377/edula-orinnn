-- Atualizar as atividades existentes com os novos PDFs criados

-- Atualizar atividades da trilha "Fundamentos de Programação"
UPDATE track_activities SET content_url = 'static/materials/variaveis_tipos_operadores.pdf' 
WHERE track_id = 1 AND order_index = 1;

UPDATE track_activities SET content_url = 'static/materials/variaveis_tipos_operadores.pdf' 
WHERE track_id = 1 AND order_index = 2;

UPDATE track_activities SET content_url = 'static/materials/estruturas_condicionais.pdf' 
WHERE track_id = 1 AND order_index = 3;

-- Adicionar novas atividades básicas para outras trilhas
INSERT INTO track_activities (track_id, title, description, content_url, activity_type, order_index) VALUES
(2, "Números Naturais", "Aprenda sobre números naturais e operações básicas.", "static/materials/numeros_naturais.pdf", "leitura", 1),
(3, "Alfabeto e Letras", "Conheça o alfabeto português e suas características.", "static/materials/alfabeto.pdf", "leitura", 1);

-- Adicionar atividade sobre corpo humano (pode ser para uma trilha de ciências)
INSERT INTO track_activities (track_id, title, description, content_url, activity_type, order_index) VALUES
(4, "O Corpo Humano", "Explore os sistemas e órgãos do corpo humano.", "static/materials/corpo_humano.pdf", "leitura", 1);


