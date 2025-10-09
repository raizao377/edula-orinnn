## Variáveis, Tipos de Dados e Operadores em Python

### Variáveis
Variáveis são "contêineres" para armazenar valores de dados. Em Python, você não precisa declarar o tipo da variável; o tipo é inferido quando você atribui um valor a ela.

**Exemplo:**
```python
nome = "Alice"  # Variável de texto (string)
idade = 30      # Variável numérica (inteiro)
preco = 19.99   # Variável numérica (float)
is_estudante = True # Variável booleana
```

### Tipos de Dados
Python possui vários tipos de dados embutidos:

*   **Texto:** `str` (string) - ex: "Olá, Mundo!"
*   **Numéricos:**
    *   `int` (inteiro) - ex: 10, -5
    *   `float` (ponto flutuante) - ex: 3.14, 2.5
    *   `complex` (números complexos) - ex: 1j + 2
*   **Sequências:**
    *   `list` (lista) - ex: `[1, 2, 3]` (mutável, ordenada)
    *   `tuple` (tupla) - ex: `(1, 2, 3)` (imutável, ordenada)
    *   `range` (intervalo) - ex: `range(6)`
*   **Mapeamento:** `dict` (dicionário) - ex: `{"nome": "João", "idade": 25}`
*   **Conjuntos:** `set` (conjunto) - ex: `{1, 2, 3}` (não ordenado, sem duplicatas)
*   **Booleanos:** `bool` (booleano) - ex: `True`, `False`

### Operadores
Operadores são usados para realizar operações em variáveis e valores.

#### Operadores Aritméticos
Usados para operações matemáticas comuns:
*   `+` Adição
*   `-` Subtração
*   `*` Multiplicação
*   `/` Divisão
*   `%` Módulo (resto da divisão)
*   `**` Exponenciação
*   `//` Divisão inteira

**Exemplo:**
```python
a = 10
b = 3
print(a + b)  # 13
print(a / b)  # 3.333...
print(a // b) # 3
```

#### Operadores de Atribuição
Usados para atribuir valores a variáveis:
*   `=` Atribuição simples
*   `+=` Adição e atribuição (ex: `x += 5` é `x = x + 5`)
*   `-=` Subtração e atribuição
*   `*=` Multiplicação e atribuição
*   `/=` Divisão e atribuição

#### Operadores de Comparação
Usados para comparar dois valores, retornando `True` ou `False`:
*   `==` Igual a
*   `!=` Diferente de
*   `>` Maior que
*   `<` Menor que
*   `>=` Maior ou igual a
*   `<=` Menor ou igual a

**Exemplo:**
```python
x = 10
y = 12
print(x == y) # False
print(x < y)  # True
```

#### Operadores Lógicos
Usados para combinar instruções condicionais:
*   `and` Retorna `True` se ambas as instruções forem verdadeiras
*   `or` Retorna `True` se uma das instruções for verdadeira
*   `not` Inverte o resultado, retorna `False` se o resultado for verdadeiro

**Exemplo:**
```python
a = True
b = False
print(a and b) # False
print(a or b)  # True
print(not a)   # False
```

