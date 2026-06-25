<?php

use App\Models\Attendance;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Student;
use App\Models\Turma;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
   use WithFileUploads, WithPagination;

   public string $search    = '';
   public string $escolaId  = '';
   public string $serieId   = '';
   public string $turmaId   = '';

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

   public function updatedSearch(): void
   {
      $this->resetPage();
   }

   public function updatedEscolaId(): void
   {
      $this->serieId = '';
      $this->turmaId = '';
      $this->resetPage();
   }

   public function updatedSerieId(): void
   {
      $this->turmaId = '';
      $this->resetPage();
   }

   public function updatedTurmaId(): void
   {
      $this->resetPage();
   }

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
      $this->formSerieId  = $student->turma?->serie_id ?? '';
      $this->formEscolaId = $student->turma?->serie?->escola_id ?? '';
      $this->photo        = null;
      $this->showFormModal = true;
   }

   public function save(): void
   {
      $uniqueRule = Rule::unique('students', 'matricula')
         ->whereNull('deleted_at')
         ->ignore($this->editingId ?: null);

      $this->validate([
         'name'         => 'required|string|max:255',
         'matricula'    => ['required', 'string', $uniqueRule],
         'sexo'         => 'required|in:M,F',
         'formTurmaId'  => 'required|exists:turmas,id',
         'photo'        => $this->photo ? 'image|max:2048' : 'nullable',
      ]);

      $oldPhoto  = $this->currentPhoto ?: null;
      $photoPath = null;
      if ($this->photo) {
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

      if ($photoPath && $oldPhoto) {
         Storage::disk('public')->delete($oldPhoto);
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
      unset($this->students, $this->totalStudents, $this->totalPresencasHoje, $this->totalFaltasHoje);
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

   #[Computed]
   public function students()
   {
      return Student::with('turma.serie.escola')
         ->withCount([
            'attendances as presencas_count' => fn($q) => $q->where('status', 'present'),
            'attendances as faltas_count'    => fn($q) => $q->where('status', 'absent'),
         ])
         ->when($this->search, fn($q) => $q->where(function ($q) {
            $q->where('name', 'like', "%{$this->search}%")
               ->orWhere('matricula', 'like', "%{$this->search}%");
         }))
         ->when($this->turmaId, fn($q) => $q->where('turma_id', $this->turmaId))
         ->when($this->serieId && ! $this->turmaId, fn($q) => $q->whereHas('turma', fn($q) => $q->where('serie_id', $this->serieId)))
         ->when($this->escolaId && ! $this->serieId, fn($q) => $q->whereHas('turma.serie', fn($q) => $q->where('escola_id', $this->escolaId)))
         ->orderBy('name')
         ->paginate(20);
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

   <div class="p-6">
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
                     class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm w-full sm:w-64" />
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
                  wire:click="openCreate"
                  class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow whitespace-nowrap">
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
                        {{ $student->turma?->serie?->name }} {{ $student->turma?->name }}
                     </p>
                  </div>
               </div>
               <div class="border-t dark:border-gray-700 pt-2 flex justify-between text-sm">
                  <span class="text-green-600 font-medium">{{ $student->presencas_count }} presências</span>
                  <span class="text-red-500 font-medium">{{ $student->faltas_count }} faltas</span>
               </div>
               <div class="flex gap-2">
                  <button wire:click="openQr('{{ $student->id }}')" class="flex-1 text-xs bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 py-1 rounded">
                     Ver QR <i class="fa-solid fa-qrcode"></i>
                  </button>
                  <button wire:click="openEdit('{{ $student->id }}')" class="flex-1 text-xs bg-yellow-50 dark:bg-yellow-900 hover:bg-yellow-100 dark:hover:bg-yellow-800 text-yellow-700 dark:text-yellow-200 py-1 rounded">
                     Editar <i class="fa-solid fa-pen-to-square"></i>
                  </button>
               </div>
               <button
                  wire:click="deleteStudent('{{ $student->id }}')"
                  wire:confirm="Tem certeza que deseja excluir este aluno?"
                  class="w-full text-xs bg-red-50 dark:bg-red-900 hover:bg-red-100 dark:hover:bg-red-800 text-red-600 dark:text-red-300 py-1 rounded mt-1">
                  Excluir <i class="fa-solid fa-trash-can"></i>
               </button>
            </div>
            @empty
            <div class="col-span-4 text-center py-12 text-gray-500 dark:text-gray-400">
               Nenhum aluno encontrado.
            </div>
            @endforelse
         </div>
         {{ $this->students->links() }}
      </div>
   </div>

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
               target="_blank">
               Baixar PNG
            </a>
         </div>
      </div>
   </div>
   @endif
</div>