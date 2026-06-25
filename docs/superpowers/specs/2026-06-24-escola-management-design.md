# Design: Módulo de Gestão Escolar (Escola, Séries, Turmas)

**Data:** 2026-06-24
**Escopo:** Página única com abas internas para gerenciar Escolas, Séries e Turmas (CRUD completo).

---

## Contexto

Os models `Escola`, `Serie` e `Turma` já existem com suas migrations (criados no módulo de alunos). Esta spec cobre apenas a camada de interface — nenhuma alteração no banco de dados é necessária.

A página se chama **Escola** no menu de navegação pois representa o conjunto escola + séries + turmas, não apenas o cadastro da entidade escola.

Stack: Laravel 13, Livewire Volt 1.7, Tailwind CSS.

---

## Modelo de Dados

Já existente. Hierarquia:

```
escolas
  └── series (escola_id FK)
        └── turmas (serie_id FK)
              └── students (turma_id FK)
```

Nenhuma migration ou model novo necessário.

---

## Interface

### Rota e Navegação

- Rota: `GET /escola` → Volt component `pages.escola.index` → nome `escola.index`
- Link "Escola" adicionado em `resources/views/livewire/layout/navigation.blade.php` após o link "Students"
- Arquivo Volt: `resources/views/livewire/pages/escola/index.blade.php`

### Abas internas

```
[ Escolas ]  [ Séries ]  [ Turmas ]
```

Controladas pela propriedade `$activeTab` (valores: `'escolas'`, `'series'`, `'turmas'`).

---

### Aba Escolas

**Barra de ações:**
- Campo de busca por nome (`wire:model.live.debounce.300ms`)
- Botão "Nova Escola"

**Tabela:**
| Nome | Ações |
|---|---|
| Escola Municipal X | [Editar] [Excluir] |

---

### Aba Séries

**Barra de ações:**
- Select filtro por Escola
- Botão "Nova Série"

**Tabela:**
| Nome | Escola | Ações |
|---|---|---|
| 1º Ano | Escola Municipal X | [Editar] [Excluir] |

---

### Aba Turmas

**Barra de ações:**
- Select filtro por Escola → Select filtro por Série (cascade)
- Botão "Nova Turma"

**Tabela:**
| Nome | Série | Escola | Ações |
|---|---|---|---|
| A | 1º Ano | Escola Municipal X | [Editar] [Excluir] |

---

### Modal Criar/Editar

Modal único compartilhado, campos variam conforme `$activeTab`:

**Escola:** Nome (obrigatório)

**Série:** Nome (obrigatório) + Escola (select obrigatório)

**Turma:** Nome (obrigatório) + Escola (select obrigatório) + Série (select obrigatório, cascade)

---

## Regras de Negócio

1. **Excluir Escola** com séries cadastradas → bloqueado, exibir mensagem: "Esta escola possui séries cadastradas e não pode ser excluída."
2. **Excluir Série** com turmas cadastradas → bloqueado, exibir mensagem: "Esta série possui turmas cadastradas e não pode ser excluída."
3. **Excluir Turma** com alunos cadastrados → bloqueado, exibir mensagem: "Esta turma possui alunos cadastrados e não pode ser excluída."
4. Nomes de série são únicos por escola (`name` + `escola_id`).
5. Nomes de turma são únicos por série (`name` + `serie_id`).

---

## Arquitetura do Componente

**Arquivo:** `resources/views/livewire/pages/escola/index.blade.php`

**Propriedades de estado:**
- `$activeTab` — aba ativa ('escolas' | 'series' | 'turmas')
- `$search` — busca de escolas
- `$filterEscolaId` — filtro de série/turma por escola
- `$filterSerieId` — filtro de turma por série
- `$showModal` — exibe modal criar/editar
- `$editingId` — ID do registro em edição (vazio = criação)
- `$name` — campo nome do formulário
- `$formEscolaId` — escola selecionada no formulário
- `$formSerieId` — série selecionada no formulário

**Computed properties (`#[Computed]`):**
- `escolas()` — lista filtrada por `$search`
- `series()` — lista filtrada por `$filterEscolaId`
- `turmas()` — lista filtrada por `$filterEscolaId` + `$filterSerieId`
- `allEscolas()` — todas as escolas (para selects)
- `formSeries()` — séries filtradas por `$formEscolaId` (para select do modal)

**Métodos:**
- `setTab(string $tab)` — troca aba ativa, reseta filtros e modal
- `openCreate()` — abre modal de criação
- `openEdit(string $id)` — abre modal de edição com dados preenchidos
- `save()` — valida e persiste (cria ou atualiza conforme `$editingId`)
- `delete(string $id)` — verifica dependentes, bloqueia ou exclui
- `closeModal()` — fecha modal e reseta campos
- `updatedFormEscolaId()` — reseta `$formSerieId` no cascade do modal

---

## Fora do Escopo

- Paginação (volume baixo esperado)
- Importação em lote
- Histórico de alterações
