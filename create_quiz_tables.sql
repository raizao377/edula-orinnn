-- Script para criar tabelas de quiz
-- Projeto Edula - Sistema de Quiz por Atividade

USE edula_test;

-- Criar tabela para questões de quiz por atividade
CREATE TABLE IF NOT EXISTS activity_quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer CHAR(1) NOT NULL CHECK (correct_answer IN ('a', 'b', 'c', 'd')),
    explanation TEXT,
    question_order INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE,
    INDEX idx_activity_quiz_activity (activity_id),
    INDEX idx_activity_quiz_order (activity_id, question_order)
);

-- Criar tabela para tentativas de quiz
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    total_questions INT NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_activity (user_id, activity_id),
    INDEX idx_quiz_attempts_user (user_id),
    INDEX idx_quiz_attempts_activity (activity_id)
);

-- Criar tabela para respostas individuais dos quizzes
CREATE TABLE IF NOT EXISTS quiz_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    quiz_id INT NOT NULL,
    selected_answer CHAR(1) NOT NULL CHECK (selected_answer IN ('a', 'b', 'c', 'd')),
    is_correct BOOLEAN NOT NULL DEFAULT FALSE,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES activity_quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz_responses_user (user_id),
    INDEX idx_quiz_responses_activity (activity_id),
    INDEX idx_quiz_responses_quiz (quiz_id)
);



COMMIT;

