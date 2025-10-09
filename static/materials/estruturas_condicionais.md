## Estruturas Condicionais (If/Else) em Python

### O que são Estruturas Condicionais?
Estruturas condicionais permitem que um programa tome decisões e execute diferentes blocos de código com base em certas condições. Em Python, as principais estruturas condicionais são `if`, `elif` (else if) e `else`.

### A Declaração `if`
A declaração `if` é usada para executar um bloco de código apenas se uma condição especificada for verdadeira.

**Sintaxe:**
```python
if condicao:
    # Código a ser executado se a condição for verdadeira
```

**Exemplo:**
```python
idade = 18
if idade >= 18:
    print("Você é maior de idade.")
```

### A Declaração `else`
A declaração `else` é usada em conjunto com `if` para executar um bloco de código quando a condição do `if` é falsa.

**Sintaxe:**
```python
if condicao:
    # Código se a condição for verdadeira
else:
    # Código se a condição for falsa
```

**Exemplo:**
```python
idade = 16
if idade >= 18:
    print("Você é maior de idade.")
else:
    print("Você é menor de idade.")
```

### A Declaração `elif` (Else If)
A declaração `elif` permite verificar múltiplas condições. Se a condição `if` for falsa, o programa verifica a condição `elif`, e assim por diante.

**Sintaxe:**
```python
if condicao1:
    # Código se condicao1 for verdadeira
elif condicao2:
    # Código se condicao2 for verdadeira
else:
    # Código se nenhuma das condições anteriores for verdadeira
```

**Exemplo:**
```python
nota = 75
if nota >= 90:
    print("Conceito A")
elif nota >= 80:
    print("Conceito B")
elif nota >= 70:
    print("Conceito C")
else:
    print("Conceito D")
```

### Exercícios Práticos
1.  Escreva um programa que peça ao usuário para digitar um número e diga se ele é positivo, negativo ou zero.
2.  Crie um programa que determine se um ano é bissexto. (Um ano é bissexto se for divisível por 4, exceto se for divisível por 100 mas não por 400).
3.  Desenvolva um programa que simule um sistema de login simples. Peça um nome de usuário e uma senha. Se ambos estiverem corretos (ex: usuário "admin", senha "123"), exiba "Login bem-sucedido!"; caso contrário, "Nome de usuário ou senha incorretos."


