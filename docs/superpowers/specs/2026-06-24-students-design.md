# Design: Módulo de Alunos

**Data:** 2026-06-24
**Escopo:** Cadastro de alunos, geração de QR code e base para registro de presença por aula.

---

## Contexto

Sistema de registro de presença usando ESP32 + QR code. Cada aluno recebe um token único gerado no cadastro, que é codificado em QR code e lido pelo dispositivo ESP32. Esta spec cobre a Fase 1: interface e regras de negócio dos alunos. A integração ESP32/API é Fase 2.

Stack: Laravel 13, Livewire Volt, Tailwind CSS, UUID como chave primária.

---

## Modelo de Dados

### Hierarquia

```
escolas
  └── series (pertence a uma escola)
        └── turmas (pertence a uma série)
              └── students (pertence a uma turma)
                    └── attendances (presença por aula)
```

### Tabelas

#### `escolas`
| campo | tipo |
|---|---|
| `id` | uuid, PK |
| `name` | string |
| `timestamps` | |

#### `series`
| campo | tipo |
|---|---|
| `id` | uuid, PK |
| `escola_id` | uuid, FK → escolas |
| `name` | string (ex: "1º Ano") |
| `timestamps` | |

#### `turmas`
| campo | tipo |
|---|---|
| `id` | uuid, PK |
| `serie_id` | uuid, FK → series |
| `name` | string (ex: "A") |
| `timestamps` | |

#### `students`
| campo | tipo | obs |
|---|---|---|
| `id` | uuid, PK | |
| `turma_id` | uuid, FK → turmas | |
| `name` | string | |
| `matricula` | string, unique | |
| `sexo` | enum: M, F | |
| `photo` | string, nullable | caminho do arquivo |
| `qr_token` | uuid, unique | gerado no `creating`, imutável |
| `timestamps` | | |

#### `attendances`
| campo | tipo | obs |
|---|---|---|
| `id` | uuid, PK | |
| `student_id` | uuid, FK → students | |
| `attended_at` | datetime | data + hora da aula |
| `status` | enum: present, absent | |
| `timestamps` | | |

---

## Regras de Negócio

### Aluno
1. `matricula` é única globalmente (não apenas por escola).
2. `qr_token` é um UUID gerado automaticamente pelo model no evento `creating` — nunca exposto no formulário, nunca editável.
3. `photo` é opcional; quando ausente exibe avatar padrão.
4. Exclusão de aluno com presenças registradas usa soft delete (`deleted_at`); sem presenças, pode ser excluído permanentemente.

### Presença
5. Um aluno não pode ter dois registros de `attendance` com o mesmo `attended_at` — unique constraint em `(student_id, attended_at)`.
6. Registro de presença via ESP32 (Fase 2) usa o `qr_token` como identificador na API.

### Entidades auxiliares
7. `series` e `turmas` são cadastradas separadamente antes de cadastrar alunos.
8. Os selects de Série e Turma no formulário de aluno são em cascata: selecionar Escola filtra Séries, selecionar Série filtra Turmas.

---

## Interface

### `students/index` (Livewire Volt)

**Cards de resumo (topo):**
- Total de alunos cadastrados
- Total de presenças hoje
- Total de faltas hoje

**Barra de ações:**
- Botão "Novo Aluno" → abre modal de criação
- Filtros em cascata: Escola → Série → Turma
- Busca por nome ou matrícula

**Grid de alunos** (4 colunas desktop / 1 mobile):
```
┌─────────────────────────┐
│  [foto/avatar]  Nome    │
│                 Matríc. │
│                 Turma   │
│  ─────────────────────  │
│  Presenças: N  Faltas: N│
│  [Ver QR]  [Editar]     │
└─────────────────────────┘
```

### Modal "Novo Aluno" / "Editar Aluno"

Campos:
- Nome (obrigatório)
- Matrícula (obrigatório, único)
- Sexo (obrigatório — select: Masculino / Feminino)
- Escola → Série → Turma (obrigatórios, selects em cascata)
- Foto (opcional — upload com preview)

O `qr_token` não aparece no formulário.

### Modal "Ver QR Code"

- QR code gerado a partir do `qr_token`
- Nome e matrícula exibidos abaixo do QR
- Botão para imprimir ou baixar como PNG

---

## Arquitetura de Componentes

Seguindo o padrão Livewire Volt do projeto:

- `resources/views/livewire/pages/students/index.blade.php` — página principal com listagem, filtros e modais
- `resources/views/livewire/pages/students/form.blade.php` — formulário reutilizável para criar/editar (ou inline no modal)

O `StudentsController` atual pode ser removido — toda a lógica migra para Volt.

Models:
- `App\Models\Escola`
- `App\Models\Serie`
- `App\Models\Turma`
- `App\Models\Student` — usa `HasUuids`, gera `qr_token` no `creating`
- `App\Models\Attendance` — usa `HasUuids`

---

## Fora do Escopo (Fase 2)

- Integração ESP32 via API
- Endpoint de validação de QR code
- Relatórios de presença por turma/período
- Gestão de aulas (entidade `Aula`)
