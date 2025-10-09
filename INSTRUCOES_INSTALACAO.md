# Instruções de Instalação e Configuração - Projeto Edula Melhorado

## Melhorias Implementadas

### 1. Sistema de PDFs Educacionais
- **Localização**: `static/materials/`
- **Arquivos criados**:
  - `numeros_naturais.pdf` - Conceitos básicos de números naturais e operações
  - `alfabeto.pdf` - Alfabeto português, vogais e consoantes
  - `corpo_humano.pdf` - Sistemas do corpo humano e órgãos importantes
  - `variaveis_tipos_operadores.pdf` - Variáveis, tipos de dados e operadores em Python
  - `estruturas_condicionais.pdf` - Estruturas condicionais (if/else) em Python

### 2. Sistema de Visualização de PDFs
- **Arquivo modificado**: `activity.php`
- **Funcionalidades**:
  - Visualização incorporada de PDFs no navegador
  - Botão de download para acesso offline
  - Interface responsiva e amigável

### 3. Sistema de Quiz Interativo
- **Arquivo criado**: `quiz_new.php`
- **Tabelas do banco de dados**:
  - `activity_quizzes` - Questões do quiz para cada atividade
  - `quiz_attempts` - Tentativas de quiz dos usuários
- **Funcionalidades**:
  - 5 questões por atividade
  - Interface interativa com opções múltiplas
  - Pontuação mínima de 60% para aprovação
  - Prevenção de múltiplas tentativas

### 4. Sistema de Progresso Atualizado
- **Funcionalidades**:
  - Progresso atualizado automaticamente após conclusão do quiz
  - Integração com as tabelas `user_progress` existentes
  - Reflexão do progresso em `study_tracks.php` e `track_activities.php`

## Configuração do Banco de Dados

### 1. Executar Scripts SQL
Execute os seguintes scripts na ordem:

```sql
-- 1. Script principal (já existente)
SOURCE database.sql;

-- 2. Inserir trilhas de estudo
SOURCE insert_study_tracks.sql;

-- 3. Criar tabelas do quiz
SOURCE create_quiz_tables.sql;

-- 4. Atualizar atividades com PDFs
SOURCE update_activities_with_pdfs.sql;
```

### 2. Estrutura das Novas Tabelas

#### Tabela `activity_quizzes`
```sql
CREATE TABLE activity_quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer CHAR(1) NOT NULL,
    question_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE
);
```

#### Tabela `quiz_attempts`
```sql
CREATE TABLE quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    total_questions INT NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES track_activities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_activity_quiz (user_id, activity_id)
);
```

## Fluxo de Uso

### Para o Estudante:
1. **Acessar Trilhas**: `study_tracks.php`
2. **Escolher Atividade**: `track_activities.php`
3. **Estudar Material**: `activity.php` (visualizar PDF incorporado)
4. **Fazer Quiz**: `quiz_new.php` (5 questões, 60% para aprovação)
5. **Progresso Atualizado**: Automático após aprovação no quiz

### Arquivos Principais Modificados:
- `activity.php` - Visualização de PDFs melhorada
- `quiz_new.php` - Novo sistema de quiz interativo
- `study_tracks.php` - Já funcional (sem modificações)
- `track_activities.php` - Já funcional (sem modificações)

## Recursos Adicionais

### PDFs Educacionais
- Conteúdo educativo de qualidade
- Formatação profissional
- Exercícios práticos incluídos

### Interface do Quiz
- Design responsivo
- Feedback visual imediato
- Prevenção de múltiplas tentativas
- Pontuação detalhada

### Sistema de Progresso
- Rastreamento automático
- Integração com sistema existente
- Reflexão em tempo real nas trilhas

## Teste do Sistema

1. **Configurar banco de dados** com os scripts SQL
2. **Acessar como usuário** (não professor)
3. **Navegar pelas trilhas** de estudo
4. **Visualizar PDFs** nas atividades
5. **Completar quizzes** para testar o progresso
6. **Verificar atualização** do progresso nas trilhas

## Observações Importantes

- Os PDFs estão localizados em `static/materials/`
- O sistema requer pelo menos 60% de acerto no quiz
- Cada usuário pode fazer o quiz apenas uma vez por atividade
- O progresso é atualizado automaticamente após aprovação
- A interface é totalmente responsiva para dispositivos móveis

