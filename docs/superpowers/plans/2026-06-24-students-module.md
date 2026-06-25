# Students Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o módulo completo de alunos com cadastro, QR code único por aluno, filtros em cascata e base de presenças.

**Architecture:** Hierarquia escola→série→turma→aluno com UUID em todos os models. Interface inteiramente em Livewire Volt com modais inline. QR code gerado server-side via `simplesoftwareio/simple-qrcode` a partir do `qr_token` imutável. Soft delete apenas quando aluno possui presenças.

**Tech Stack:** Laravel 13, Livewire Volt 1.7, Tailwind CSS, simplesoftwareio/simple-qrcode, Pest

## Global Constraints
- UUIDs em todos os models (`HasUuids`)
- Livewire Volt para toda lógica de UI — sem controllers extras
- `matricula` única globalmente
- `qr_token` gerado no evento `creating` do Student model, nunca editável
- Soft delete em alunos com presenças; hard delete sem presenças
- Unique constraint em `(student_id, attended_at)` na tabela `attendances`
- Fotos armazenadas em `storage/app/public/students/photos`, exibidas via `asset('storage/...')`

---

### Task 1: Dependência de QR Code e Storage

**Files:**
- Modify: `composer.json` (via composer)

**Interfaces:**
- Produces: facade `QrCode` disponível; link `public/storage` criado

- [ ] **Step 1: Instalar simplesoftwareio/simple-qrcode**

```bash
composer require simplesoftwareio/simple-qrcode
```

Expected: Package installed successfully.

- [ ] **Step 2: Verificar instalação**

```bash
php artisan tinker --execute="echo \SimpleSoftwareIO\QrCode\Facades\QrCode::size(50)->generate('ok');"
```

Expected: SVG string começando com `<svg`.

- [ ] **Step 3: Criar link de storage público**

```bash
php artisan storage:link
```

Expected: `The [public/storage] link has been connected to [storage/app/public].`

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: add simple-qrcode dependency and storage link"
```

---

### Task 2: Migrations

**Files:**
- Modify: `database/migrations/2026_06_24_190654_create_students_table.php`
- Create: `database/migrations/XXXX_create_escolas_table.php`
- Create: `database/migrations/XXXX_create_series_table.php`
- Create: `database/migrations/XXXX_create_turmas_table.php`
- Create: `database/migrations/XXXX_create_attendances_table.php`

**Interfaces:**
- Produces: tabelas `escolas`, `series`, `turmas`, `students` (com todos os campos), `attendances`

- [ ] **Step 1: Criar arquivos via artisan**

```bash
php artisan make:migration create_escolas_table --create=escolas
php artisan make:migration create_series_table --create=series
php artisan make:migration create_turmas_table --create=turmas
php artisan make:migration create_attendances_table --create=attendances
```

- [ ] **Step 2: Escrever migration de escolas**

Substitua o método `up()` no arquivo `XXXX_create_escolas_table.php`:

```php
public function up(): void
{
    Schema::create('escolas', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
}
```

- [ ] **Step 3: Escrever migration de series**

Substitua o método `up()` no arquivo `XXXX_create_series_table.php`:

```php
public function up(): void
{
    Schema::create('series', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('escola_id')->constrained('escolas')->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });
}
```

- [ ] **Step 4: Escrever migration de turmas**

Substitua o método `up()` no arquivo `XXXX_create_turmas_table.php`:

```php
public function up(): void
{
    Schema::create('turmas', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('serie_id')->constrained('series')->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });
}
```

- [ ] **Step 5: Atualizar migration de students (arquivo existente)**

Substitua o método `up()` em `2026_06_24_190654_create_students_table.php`:

```php
public function up(): void
{
    Schema::create('students', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('turma_id')->constrained('turmas')->cascadeOnDelete();
        $table->string('name');
        $table->string('matricula')->unique();
        $table->enum('sexo', ['M', 'F']);
        $table->string('photo')->nullable();
        $table->uuid('qr_token')->unique();
        $table->softDeletes();
        $table->timestamps();
    });
}
```

- [ ] **Step 6: Escrever migration de attendances**

Substitua o método `up()` no arquivo `XXXX_create_attendances_table.php`:

```php
public function up(): void
{
    Schema::create('attendances', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
        $table->dateTime('attended_at');
        $table->enum('status', ['present', 'absent']);
        $table->timestamps();
        $table->unique(['student_id', 'attended_at']);
    });
}
```

- [ ] **Step 7: Rodar migrate:fresh**

```bash
php artisan migrate:fresh
```

Expected: All migrations ran successfully (sem erros).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/
git commit -m "feat: migrations for escolas, series, turmas, students and attendances"
```

---

### Task 3: Models e Testes

**Files:**
- Create: `app/Models/Escola.php`
- Create: `app/Models/Serie.php`
- Create: `app/Models/Turma.php`
- Modify: `app/Models/Student.php`
- Create: `app/Models/Attendance.php`
- Create: `tests/Feature/StudentModelTest.php`

**Interfaces:**
- Produces:
  - `Escola::series()` → `HasMany<Serie>`
  - `Serie::escola()` → `BelongsTo<Escola>`, `Serie::turmas()` → `HasMany<Turma>`
  - `Turma::serie()` → `BelongsTo<Serie>`, `Turma::students()` → `HasMany<Student>`
  - `Student::turma()` → `BelongsTo<Turma>`, `Student::attendances()` → `HasMany<Attendance>`
  - `Student->qr_token` auto-gerado no `creating`, não está em `$fillable`
  - `Attendance::student()` → `BelongsTo<Student>`

- [ ] **Step 1: Escrever os testes (TDD — devem falhar primeiro)**

Crie `tests/Feature/StudentModelTest.php`:

```php
<?php

use App\Models\Attendance;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Student;
use App\Models\Turma;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeStudent(string $matricula = '2026001'): Student
{
    $escola = Escola::create(['name' => 'Escola Teste']);
    $serie  = Serie::create(['escola_id' => $escola->id, 'name' => '1º Ano']);
    $turma  = Turma::create(['serie_id' => $serie->id, 'name' => 'A']);

    return Student::create([
        'turma_id'  => $turma->id,
        'name'      => 'Aluno Teste',
        'matricula' => $matricula,
        'sexo'      => 'M',
    ]);
}

it('gera qr_token automaticamente ao criar aluno', function () {
    $student = makeStudent();
    expect($student->qr_token)->not->toBeNull();
});

it('qr_token nao muda ao atualizar aluno', function () {
    $student = makeStudent();
    $original = $student->qr_token;
    $student->update(['name' => 'Novo Nome']);
    expect($student->fresh()->qr_token)->toBe($original);
});

it('matricula deve ser unica', function () {
    makeStudent('2026001');
    makeStudent('2026001');
})->throws(Illuminate\Database\QueryException::class);

it('attendance nao permite duplicata de student + attended_at', function () {
    $student = makeStudent();
    $at = now();
    Attendance::create(['student_id' => $student->id, 'attended_at' => $at, 'status' => 'present']);
    Attendance::create(['student_id' => $student->id, 'attended_at' => $at, 'status' => 'present']);
})->throws(Illuminate\Database\QueryException::class);

it('soft deletes aluno que tem preencas', function () {
    $student = makeStudent();
    Attendance::create(['student_id' => $student->id, 'attended_at' => now(), 'status' => 'present']);
    $student->delete();
    expect(Student::withTrashed()->find($student->id))->not->toBeNull();
    expect(Student::find($student->id))->toBeNull();
});
```

- [ ] **Step 2: Rodar testes — devem falhar**

```bash
php artisan test tests/Feature/StudentModelTest.php
```

Expected: FAIL — models não existem.

- [ ] **Step 3: Criar Escola**

Crie `app/Models/Escola.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escola extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }
}
```

- [ ] **Step 4: Criar Serie**

Crie `app/Models/Serie.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    use HasUuids;

    protected $fillable = ['escola_id', 'name'];

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
```

- [ ] **Step 5: Criar Turma**

Crie `app/Models/Turma.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    use HasUuids;

    protected $fillable = ['serie_id', 'name'];

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
```

- [ ] **Step 6: Atualizar Student**

Substitua o conteúdo de `app/Models/Student.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['turma_id', 'name', 'matricula', 'sexo', 'photo'];

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            $student->qr_token = (string) Str::uuid();
        });
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
```

- [ ] **Step 7: Criar Attendance**

Crie `app/Models/Attendance.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasUuids;

    protected $fillable = ['student_id', 'attended_at', 'status'];

    protected $casts = ['attended_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
```

- [ ] **Step 8: Rodar testes — devem passar**

```bash
php artisan test tests/Feature/StudentModelTest.php
```

Expected: 5 testes PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Models/ tests/Feature/StudentModelTest.php
git commit -m "feat: add Escola, Serie, Turma, Student and Attendance models with business rules"
```

---

### Task 4: Rotas

**Files:**
- Modify: `routes/web.php`
- Delete: `app/Http/Controllers/StudentsController.php`

**Interfaces:**
- Produces:
  - `route('students.index')` → GET `/students` → Volt component
  - `route('students.qr', $student)` → GET `/students/{student}/qr` → PNG download

- [ ] **Step 1: Atualizar routes/web.php**

Substitua o conteúdo de `routes/web.php`:

```php
<?php

use App\Models\Student;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    Volt::route('students', 'pages.students.index')->name('students.index');

    Route::get('/students/{student}/qr', function (Student $student) {
        return response(QrCode::format('png')->size(300)->generate($student->qr_token))
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="qr-' . $student->matricula . '.png"');
    })->name('students.qr');
});

require __DIR__ . '/auth.php';
```

- [ ] **Step 2: Deletar StudentsController**

```bash
rm app/Http/Controllers/StudentsController.php
```

- [ ] **Step 3: Verificar que as rotas estão registradas**

```bash
php artisan route:list --name=students
```

Expected: duas rotas — `students.index` (GET /students) e `students.qr` (GET /students/{student}/qr).

- [ ] **Step 4: Commit**

```bash
git add routes/web.php
git rm app/Http/Controllers/StudentsController.php
git commit -m "feat: register students Volt route and QR download route"
```

---

### Task 5: Página de Alunos — Listagem Base

**Files:**
- Create: `resources/views/livewire/pages/students/index.blade.php`

**Interfaces:**
- Consumes: `Student::with('turma.serie.escola')->withCount([...])`, `Escola::all()`, `Serie::where('escola_id', ...)`, `Turma::where('serie_id', ...)`
- Produces: página `/students` com grid de cards, 3 cards de resumo, barra de busca e filtros (sem modais ainda)

- [ ] **Step 1: Criar diretório**

```bash
mkdir -p resources/views/livewire/pages/students
```

- [ ] **Step 2: Criar o componente Volt base**

Crie `resources/views/livewire/pages/students/index.blade.php`:

```php
<?php

use App\Models\Attendance;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Student;
use App\Models\Turma;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $search    = '';
    public string $escolaId  = '';
    public string $serieId   = '';
    public string $turmaId   = '';

    public function updatedEscolaId(): void
    {
        $this->serieId = '';
        $this->turmaId = '';
    }

    public function updatedSerieId(): void
    {
        $this->turmaId = '';
    }

    #[Computed]
    public function escolas()
    {
        return Escola::orderBy('name')->get();
    }

    #[Computed]
    public function series()
    {
        return $this->escolaId
            ? Serie::where('escola_id', $this->escolaId)->orderBy('name')->get()
            : collect();
    }

    #[Computed]
    public function turmas()
    {
        return $this->serieId
            ? Turma::where('serie_id', $this->serieId)->orderBy('name')->get()
            : collect();
    }

    #[Computed]
    public function students()
    {
        return Student::with('turma.serie.escola')
            ->withCount([
                'attendances as presencas_count' => fn ($q) => $q->where('status', 'present'),
                'attendances as faltas_count'    => fn ($q) => $q->where('status', 'absent'),
            ])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('matricula', 'like', "%{$this->search}%");
            }))
            ->when($this->turmaId, fn ($q) => $q->where('turma_id', $this->turmaId))
            ->when($this->serieId && ! $this->turmaId, fn ($q) => $q->whereHas('turma', fn ($q) => $q->where('serie_id', $this->serieId)))
            ->when($this->escolaId && ! $this->serieId, fn ($q) => $q->whereHas('turma.serie', fn ($q) => $q->where('escola_id', $this->escolaId)))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function totalStudents(): int
    {
        return Student::count();
    }

    #[Computed]
    public function totalPresencasHoje(): int
    {
        return Attendance::whereDate('attended_at', today())->where('status', 'present')->count();
    }

    #[Computed]
    public function totalFaltasHoje(): int
    {
        return Attendance::whereDate('attended_at', today())->where('status', 'absent')->count();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Students') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total de Alunos</p>
                    <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-1">{{ $this->totalStudents }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Presenças Hoje</p>
                    <p class="text-3xl font-bold text-green-600 mt-1">{{ $this->totalPresencasHoje }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Faltas Hoje</p>
                    <p class="text-3xl font-bold text-red-500 mt-1">{{ $this->totalFaltasHoje }}</p>
                </div>
            </div>

            {{-- Actions Bar --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex flex-col md:flex-row gap-3 items-start md:items-center justify-between">
                    <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="text"
                            placeholder="Buscar por nome ou matrícula..."
                            class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm w-full sm:w-64"
                        />
                        <select wire:model.live="escolaId" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todas as escolas</option>
                            @foreach($this->escolas as $escola)
                                <option value="{{ $escola->id }}">{{ $escola->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="serieId" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm" @if(!$escolaId) disabled @endif>
                            <option value="">Todas as séries</option>
                            @foreach($this->series as $serie)
                                <option value="{{ $serie->id }}">{{ $serie->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="turmaId" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm" @if(!$serieId) disabled @endif>
                            <option value="">Todas as turmas</option>
                            @foreach($this->turmas as $turma)
                                <option value="{{ $turma->id }}">{{ $turma->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow whitespace-nowrap"
                    >
                        + Novo Aluno
                    </button>
                </div>
            </div>

            {{-- Student Cards Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @forelse($this->students as $student)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            @if($student->photo)
                                <img src="{{ asset('storage/' . $student->photo) }}" class="w-14 h-14 rounded-full object-cover" alt="{{ $student->name }}">
                            @else
                                <div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-500 dark:text-gray-300 text-xl font-bold">
                                    {{ strtoupper(substr($student->name, 0, 1)) }}
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $student->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $student->matricula }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $student->turma->serie->name }} {{ $student->turma->name }}
                                </p>
                            </div>
                        </div>
                        <div class="border-t dark:border-gray-700 pt-2 flex justify-between text-sm">
                            <span class="text-green-600 font-medium">{{ $student->presencas_count }} pres.</span>
                            <span class="text-red-500 font-medium">{{ $student->faltas_count }} falt.</span>
                        </div>
                        <div class="flex gap-2">
                            <button class="flex-1 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 py-1 rounded">
                                Ver QR
                            </button>
                            <button class="flex-1 text-xs bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 text-blue-700 dark:text-blue-200 py-1 rounded">
                                Editar
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-4 text-center py-12 text-gray-500 dark:text-gray-400">
                        Nenhum aluno encontrado.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</div>
```

- [ ] **Step 3: Acessar `/students` no browser e verificar**

- Summary cards aparecem com zeros (sem dados ainda)
- Grid vazio mostra "Nenhum aluno encontrado"
- Filtros em cascata: selecionar escola habilita série; selecionar série habilita turma

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/pages/students/
git commit -m "feat: students index page with summary cards, filters and card grid"
```

---

### Task 6: Modais de Criação/Edição e QR Code

**Files:**
- Modify: `resources/views/livewire/pages/students/index.blade.php`

**Interfaces:**
- Consumes: `Student::create()`, `Student::find()`, `Storage::disk('public')`, `QrCode::size()->generate()`
- Produces: modal de formulário funcional (criar + editar com foto), modal de QR code com download

- [ ] **Step 1: Adicionar `WithFileUploads` e estado dos modais ao componente**

No topo do PHP do componente (após os `use` existentes), adicione os imports:

```php
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
```

Dentro da classe, adicione após as propriedades de filtro existentes:

```php
use WithFileUploads;

// Form modal state
public bool   $showFormModal = false;
public string $editingId     = '';
public string $name          = '';
public string $matricula     = '';
public string $sexo          = '';
public string $formEscolaId  = '';
public string $formSerieId   = '';
public string $formTurmaId   = '';
public        $photo         = null;
public string $currentPhoto  = '';

// QR modal state
public bool   $showQrModal  = false;
public string $qrStudentId  = '';
```

- [ ] **Step 2: Adicionar computed properties para os selects do formulário**

Dentro da classe, adicione após os computed existentes:

```php
#[Computed]
public function formSeries()
{
    return $this->formEscolaId
        ? Serie::where('escola_id', $this->formEscolaId)->orderBy('name')->get()
        : collect();
}

#[Computed]
public function formTurmas()
{
    return $this->formSerieId
        ? Turma::where('serie_id', $this->formSerieId)->orderBy('name')->get()
        : collect();
}

#[Computed]
public function qrStudent(): ?Student
{
    return $this->qrStudentId ? Student::find($this->qrStudentId) : null;
}
```

- [ ] **Step 3: Adicionar methods de cascade e ações dos modais**

Dentro da classe, adicione após os métodos `updatedSerieId()` e `updatedEscolaId()` existentes:

```php
public function updatedFormEscolaId(): void
{
    $this->formSerieId = '';
    $this->formTurmaId = '';
}

public function updatedFormSerieId(): void
{
    $this->formTurmaId = '';
}

public function openCreate(): void
{
    $this->editingId    = '';
    $this->name         = '';
    $this->matricula    = '';
    $this->sexo         = '';
    $this->formEscolaId = '';
    $this->formSerieId  = '';
    $this->formTurmaId  = '';
    $this->photo        = null;
    $this->currentPhoto = '';
    $this->showFormModal = true;
}

public function openEdit(string $id): void
{
    $student = Student::with('turma.serie')->findOrFail($id);
    $this->editingId    = $student->id;
    $this->name         = $student->name;
    $this->matricula    = $student->matricula;
    $this->sexo         = $student->sexo;
    $this->currentPhoto = $student->photo ?? '';
    $this->formTurmaId  = $student->turma_id;
    $this->formSerieId  = $student->turma->serie_id;
    $this->formEscolaId = $student->turma->serie->escola_id;
    $this->photo        = null;
    $this->showFormModal = true;
}

public function save(): void
{
    $uniqueRule = $this->editingId
        ? "unique:students,matricula,{$this->editingId}"
        : 'unique:students,matricula';

    $this->validate([
        'name'         => 'required|string|max:255',
        'matricula'    => "required|string|{$uniqueRule}",
        'sexo'         => 'required|in:M,F',
        'formTurmaId'  => 'required|exists:turmas,id',
        'photo'        => $this->photo ? 'image|max:2048' : 'nullable',
    ]);

    $photoPath = null;
    if ($this->photo) {
        if ($this->currentPhoto) {
            Storage::disk('public')->delete($this->currentPhoto);
        }
        $photoPath = $this->photo->store('students/photos', 'public');
    }

    $data = [
        'turma_id'  => $this->formTurmaId,
        'name'      => $this->name,
        'matricula' => $this->matricula,
        'sexo'      => $this->sexo,
    ];

    if ($photoPath) {
        $data['photo'] = $photoPath;
    }

    if ($this->editingId) {
        Student::findOrFail($this->editingId)->update($data);
    } else {
        Student::create($data);
    }

    $this->showFormModal = false;
    unset($this->students, $this->totalStudents);
}

public function deleteStudent(string $id): void
{
    $student = Student::findOrFail($id);
    if ($student->attendances()->exists()) {
        $student->delete();
    } else {
        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }
        $student->forceDelete();
    }
    unset($this->students, $this->totalStudents);
}

public function openQr(string $id): void
{
    $this->qrStudentId = $id;
    $this->showQrModal = true;
}

public function closeForm(): void
{
    $this->showFormModal = false;
}

public function closeQr(): void
{
    $this->showQrModal  = false;
    $this->qrStudentId  = '';
}
```

- [ ] **Step 4: Conectar botões do grid ao Livewire**

No HTML, localize os dois botões dentro do card do aluno e substitua por:

```blade
<button wire:click="openQr('{{ $student->id }}')" class="flex-1 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 py-1 rounded">
    Ver QR
</button>
<button wire:click="openEdit('{{ $student->id }}')" class="flex-1 text-xs bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 text-blue-700 dark:text-blue-200 py-1 rounded">
    Editar
</button>
<button
    wire:click="deleteStudent('{{ $student->id }}')"
    wire:confirm="Tem certeza que deseja excluir este aluno?"
    class="w-full text-xs bg-red-50 dark:bg-red-900 hover:bg-red-100 dark:hover:bg-red-800 text-red-600 dark:text-red-300 py-1 rounded mt-1"
>
    Excluir
</button>
```

E conecte o botão "Novo Aluno":

```blade
<button
    wire:click="openCreate"
    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow whitespace-nowrap"
>
    + Novo Aluno
</button>
```

- [ ] **Step 5: Adicionar modal de formulário ao final do `<div>` raiz**

Antes do `</div>` final do componente, adicione:

```blade
{{-- Form Modal --}}
@if($showFormModal)
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                {{ $editingId ? 'Editar Aluno' : 'Novo Aluno' }}
            </h3>
            <button wire:click="closeForm" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none">&times;</button>
        </div>
        <form wire:submit="save" class="p-5 space-y-4">
            <div>
                <x-input-label for="name" value="Nome *" />
                <x-text-input wire:model="name" id="name" class="mt-1 block w-full" type="text" required />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="matricula" value="Matrícula *" />
                <x-text-input wire:model="matricula" id="matricula" class="mt-1 block w-full" type="text" required />
                <x-input-error :messages="$errors->get('matricula')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="sexo" value="Sexo *" />
                <select wire:model="sexo" id="sexo" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" required>
                    <option value="">Selecione...</option>
                    <option value="M">Masculino</option>
                    <option value="F">Feminino</option>
                </select>
                <x-input-error :messages="$errors->get('sexo')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="formEscolaId" value="Escola *" />
                <select wire:model.live="formEscolaId" id="formEscolaId" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" required>
                    <option value="">Selecione...</option>
                    @foreach($this->escolas as $escola)
                        <option value="{{ $escola->id }}">{{ $escola->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="formSerieId" value="Série *" />
                <select wire:model.live="formSerieId" id="formSerieId" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" @if(!$formEscolaId) disabled @endif required>
                    <option value="">Selecione...</option>
                    @foreach($this->formSeries as $serie)
                        <option value="{{ $serie->id }}">{{ $serie->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('formSerieId')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="formTurmaId" value="Turma *" />
                <select wire:model="formTurmaId" id="formTurmaId" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" @if(!$formSerieId) disabled @endif required>
                    <option value="">Selecione...</option>
                    @foreach($this->formTurmas as $turma)
                        <option value="{{ $turma->id }}">{{ $turma->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('formTurmaId')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="photo" value="Foto (opcional)" />
                @if($currentPhoto)
                    <img src="{{ asset('storage/' . $currentPhoto) }}" class="mt-1 w-16 h-16 rounded-full object-cover mb-2">
                @endif
                <input wire:model="photo" id="photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400" />
                <x-input-error :messages="$errors->get('photo')" class="mt-1" />
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" wire:click="closeForm" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg">
                    {{ $editingId ? 'Salvar' : 'Cadastrar' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endif
```

- [ ] **Step 6: Adicionar modal de QR Code ao final do `<div>` raiz**

Logo após o modal anterior, adicione:

```blade
{{-- QR Code Modal --}}
@if($showQrModal && $this->qrStudent)
<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">QR Code</h3>
            <button wire:click="closeQr" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 flex flex-col items-center gap-4">
            <div class="p-3 bg-white rounded-lg shadow-inner border">
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($this->qrStudent->qr_token) !!}
            </div>
            <div class="text-center">
                <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $this->qrStudent->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Matrícula: {{ $this->qrStudent->matricula }}</p>
            </div>
            <a
                href="{{ route('students.qr', $this->qrStudent) }}"
                class="w-full text-center px-4 py-2 text-sm text-white bg-gray-800 hover:bg-gray-700 rounded-lg"
                target="_blank"
            >
                Baixar PNG
            </a>
        </div>
    </div>
</div>
@endif
```

- [ ] **Step 7: Testar fluxo completo no browser**

1. Acesse `/students`
2. Clique "Novo Aluno" → modal abre
3. Preencha os campos (selects em cascata funcionam)
4. Salve → card aparece na grid
5. Clique "Editar" → modal abre com dados preenchidos
6. Clique "Ver QR" → modal exibe QR code, botão "Baixar PNG" funciona
7. Busca por nome filtra os cards em tempo real
8. Filtros por escola/série/turma funcionam em cascata

- [ ] **Step 8: Commit**

```bash
git add resources/views/livewire/pages/students/index.blade.php
git commit -m "feat: add create/edit modal with cascade selects, photo upload and QR code modal"
```
