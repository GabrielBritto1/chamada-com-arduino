<?php

use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
   public string $activeTab      = 'escolas';
   public string $search         = '';
   public string $filterEscolaId = '';
   public string $filterSerieId  = '';

   public bool   $showModal  = false;
   public string $editingId  = '';
   public string $name       = '';
   public string $formEscolaId = '';
   public string $formSerieId  = '';
   public string $deleteError  = '';

   public function setTab(string $tab): void
   {
      $this->activeTab      = $tab;
      $this->search         = '';
      $this->filterEscolaId = '';
      $this->filterSerieId  = '';
      $this->deleteError    = '';
      $this->closeModal();
      unset($this->escolas, $this->series, $this->turmas);
   }

   public function updatedFormEscolaId(): void
   {
      $this->formSerieId = '';
   }

   public function updatedFilterEscolaId(): void
   {
      $this->filterSerieId = '';
   }

   #[Computed]
   public function escolas()
   {
      return Escola::when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
         ->orderBy('name')
         ->get();
   }

   #[Computed]
   public function series()
   {
      return Serie::with('escola')
         ->when($this->filterEscolaId, fn($q) => $q->where('escola_id', $this->filterEscolaId))
         ->orderBy('name')
         ->get();
   }

   #[Computed]
   public function turmas()
   {
      return Turma::with('serie.escola')
         ->when($this->filterSerieId, fn($q) => $q->where('serie_id', $this->filterSerieId))
         ->when(
            $this->filterEscolaId && ! $this->filterSerieId,
            fn($q) => $q->whereHas('serie', fn($q) => $q->where('escola_id', $this->filterEscolaId))
         )
         ->orderBy('name')
         ->get();
   }

   #[Computed]
   public function allEscolas()
   {
      return Escola::orderBy('name')->get();
   }

   #[Computed]
   public function formSeries()
   {
      return $this->formEscolaId
         ? Serie::where('escola_id', $this->formEscolaId)->orderBy('name')->get()
         : collect();
   }

   public function openCreate(): void
   {
      $this->editingId    = '';
      $this->name         = '';
      $this->formEscolaId = '';
      $this->formSerieId  = '';
      $this->deleteError  = '';
      $this->showModal    = true;
   }

   public function openEdit(string $id): void
   {
      $this->deleteError = '';

      if ($this->activeTab === 'escolas') {
         $record             = Escola::findOrFail($id);
         $this->formEscolaId = '';
         $this->formSerieId  = '';
      } elseif ($this->activeTab === 'series') {
         $record             = Serie::findOrFail($id);
         $this->formEscolaId = $record->escola_id;
         $this->formSerieId  = '';
      } else {
         $record             = Turma::with('serie')->findOrFail($id);
         $this->formSerieId  = $record->serie_id;
         $this->formEscolaId = $record->serie?->escola_id ?? '';
      }

      $this->name      = $record->name;
      $this->editingId = $id;
      $this->showModal = true;
   }

   public function save(): void
   {
      if ($this->activeTab === 'escolas') {
         $this->validate(['name' => 'required|string|max:255']);

         $data = ['name' => $this->name];
         $this->editingId
            ? Escola::findOrFail($this->editingId)->update($data)
            : Escola::create($data);

         unset($this->escolas, $this->allEscolas);
      } elseif ($this->activeTab === 'series') {
         $uniqueRule = Rule::unique('series', 'name')
            ->where('escola_id', $this->formEscolaId)
            ->ignore($this->editingId ?: null);

         $this->validate([
            'name'         => ['required', 'string', 'max:255', $uniqueRule],
            'formEscolaId' => 'required|exists:escolas,id',
         ]);

         $data = ['name' => $this->name, 'escola_id' => $this->formEscolaId];
         $this->editingId
            ? Serie::findOrFail($this->editingId)->update($data)
            : Serie::create($data);

         unset($this->series, $this->formSeries);
      } else {
         $uniqueRule = Rule::unique('turmas', 'name')
            ->where('serie_id', $this->formSerieId)
            ->ignore($this->editingId ?: null);

         $this->validate([
            'name'        => ['required', 'string', 'max:255', $uniqueRule],
            'formSerieId' => 'required|exists:series,id',
         ]);

         $data = ['name' => $this->name, 'serie_id' => $this->formSerieId];
         $this->editingId
            ? Turma::findOrFail($this->editingId)->update($data)
            : Turma::create($data);

         unset($this->turmas);
      }

      $this->closeModal();
   }

   public function delete(string $id): void
   {
      $this->deleteError = '';

      if ($this->activeTab === 'escolas') {
         $record = Escola::withCount('series')->findOrFail($id);
         if ($record->series_count > 0) {
            $this->deleteError = 'Esta escola possui séries cadastradas e não pode ser excluída.';
            return;
         }
         $record->delete();
         unset($this->escolas, $this->allEscolas);
      } elseif ($this->activeTab === 'series') {
         $record = Serie::withCount('turmas')->findOrFail($id);
         if ($record->turmas_count > 0) {
            $this->deleteError = 'Esta série possui turmas cadastradas e não pode ser excluída.';
            return;
         }
         $record->delete();
         unset($this->series, $this->formSeries);
      } else {
         $record = Turma::withCount('students')->findOrFail($id);
         if ($record->students_count > 0) {
            $this->deleteError = 'Esta turma possui alunos cadastrados e não pode ser excluída.';
            return;
         }
         $record->delete();
         unset($this->turmas);
      }
   }

   public function closeModal(): void
   {
      $this->showModal    = false;
      $this->editingId    = '';
      $this->name         = '';
      $this->formEscolaId = '';
      $this->formSerieId  = '';
      unset($this->formSeries);
   }
}; ?>

<div>
   <x-slot name="header">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
         {{ __('Escola') }}
      </h2>
   </x-slot>

   <div class="p-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

         {{-- Tabs --}}
         <div class="flex gap-1 mb-4 border-b border-gray-200 dark:border-gray-700">
            @foreach(['escolas' => 'Escolas', 'series' => 'Séries', 'turmas' => 'Turmas'] as $tab => $label)
            <button
               wire:click="setTab('{{ $tab }}')"
               class="px-5 py-2 text-sm font-medium rounded-t-lg transition-colors
                            {{ $activeTab === $tab
                                ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 border border-b-0 border-gray-200 dark:border-gray-700'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
               {{ $label }}
            </button>
            @endforeach
         </div>

         <div class="bg-white dark:bg-gray-800 rounded-lg shadow">

            {{-- Delete error alert --}}
            @if($deleteError)
            <div class="mx-6 mt-4 p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 text-sm rounded-lg">
               {{ $deleteError }}
            </div>
            @endif

            {{-- ── ESCOLAS ── --}}
            @if($activeTab === 'escolas')
            <div class="p-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between border-b dark:border-gray-700">
               <input
                  wire:model.live.debounce.300ms="search"
                  type="text"
                  placeholder="Buscar escola..."
                  class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm w-full sm:w-64" />
               <button wire:click="openCreate" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
                  + Nova Escola
               </button>
            </div>
            <table class="w-full text-sm text-left">
               <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-xs">
                  <tr>
                     <th class="px-6 py-3">Nome</th>
                     <th class="px-6 py-3 text-right">Ações</th>
                  </tr>
               </thead>
               <tbody>
                  @forelse($this->escolas as $escola)
                  <tr class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                     <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $escola->name }}</td>
                     <td class="px-6 py-3 text-right space-x-2">
                        <button wire:click="openEdit('{{ $escola->id }}')" class="text-yellow-600 dark:text-yellow-400 hover:underline text-xs">Editar <i class="fa-solid fa-pen-to-square"></i></button>
                        <button wire:click="delete('{{ $escola->id }}')" wire:confirm="Tem certeza que deseja excluir esta escola?" class="text-red-500 hover:underline text-xs">Excluir <i class="fa-solid fa-trash-can"></i></button>
                     </td>
                  </tr>
                  @empty
                  <tr>
                     <td colspan="2" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Nenhuma escola cadastrada.</td>
                  </tr>
                  @endforelse
               </tbody>
            </table>
            @endif

            {{-- ── SÉRIES ── --}}
            @if($activeTab === 'series')
            <div class="p-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between border-b dark:border-gray-700">
               <select wire:model.live="filterEscolaId" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm">
                  <option value="">Todas as escolas</option>
                  @foreach($this->allEscolas as $escola)
                  <option value="{{ $escola->id }}">{{ $escola->name }}</option>
                  @endforeach
               </select>
               <button wire:click="openCreate" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
                  + Nova Série
               </button>
            </div>
            <table class="w-full text-sm text-left">
               <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-xs">
                  <tr>
                     <th class="px-6 py-3">Nome</th>
                     <th class="px-6 py-3">Escola</th>
                     <th class="px-6 py-3 text-right">Ações</th>
                  </tr>
               </thead>
               <tbody>
                  @forelse($this->series as $serie)
                  <tr class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                     <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $serie->name }}</td>
                     <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $serie->escola?->name }}</td>
                     <td class="px-6 py-3 text-right space-x-2">
                        <button wire:click="openEdit('{{ $serie->id }}')" class="text-yellow-600 dark:text-yellow-400 hover:underline text-xs">Editar <i class="fa-solid fa-pen-to-square"></i></button>
                        <button wire:click="delete('{{ $serie->id }}')" wire:confirm="Tem certeza que deseja excluir esta série?" class="text-red-500 hover:underline text-xs">Excluir <i class="fa-solid fa-trash-can"></i></button>
                     </td>
                  </tr>
                  @empty
                  <tr>
                     <td colspan="3" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Nenhuma série cadastrada.</td>
                  </tr>
                  @endforelse
               </tbody>
            </table>
            @endif

            {{-- ── TURMAS ── --}}
            @if($activeTab === 'turmas')
            <div class="p-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between border-b dark:border-gray-700">
               <div class="flex gap-2">
                  <select wire:model.live="filterEscolaId" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm">
                     <option value="">Todas as escolas</option>
                     @foreach($this->allEscolas as $escola)
                     <option value="{{ $escola->id }}">{{ $escola->name }}</option>
                     @endforeach
                  </select>
                  @if($filterEscolaId)
                  <select wire:model.live="filterSerieId" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm text-sm">
                     <option value="">Todas as séries</option>
                     @foreach($this->series as $serie)
                     <option value="{{ $serie->id }}">{{ $serie->name }}</option>
                     @endforeach
                  </select>
                  @endif
               </div>
               <button wire:click="openCreate" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
                  + Nova Turma
               </button>
            </div>
            <table class="w-full text-sm text-left">
               <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 uppercase text-xs">
                  <tr>
                     <th class="px-6 py-3">Nome</th>
                     <th class="px-6 py-3">Série</th>
                     <th class="px-6 py-3">Escola</th>
                     <th class="px-6 py-3 text-right">Ações</th>
                  </tr>
               </thead>
               <tbody>
                  @forelse($this->turmas as $turma)
                  <tr class="border-t dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                     <td class="px-6 py-3 text-gray-800 dark:text-gray-100">{{ $turma->name }}</td>
                     <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $turma->serie?->name }}</td>
                     <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $turma->serie?->escola?->name }}</td>
                     <td class="px-6 py-3 text-right space-x-2">
                        <button wire:click="openEdit('{{ $turma->id }}')" class="text-yellow-600 dark:text-yellow-400 hover:underline text-xs">Editar <i class="fa-solid fa-pen-to-square"></i></button>
                        <button wire:click="delete('{{ $turma->id }}')" wire:confirm="Tem certeza que deseja excluir esta turma?" class="text-red-500 hover:underline text-xs">Excluir <i class="fa-solid fa-trash-can"></i></button>
                     </td>
                  </tr>
                  @empty
                  <tr>
                     <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">Nenhuma turma cadastrada.</td>
                  </tr>
                  @endforelse
               </tbody>
            </table>
            @endif

         </div>{{-- /card --}}
      </div>
   </div>

   {{-- Modal Criar/Editar --}}
   @if($showModal)
   <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md">
         <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
               @if($activeTab === 'escolas') {{ $editingId ? 'Editar Escola' : 'Nova Escola' }}
               @elseif($activeTab === 'series') {{ $editingId ? 'Editar Série' : 'Nova Série' }}
               @else {{ $editingId ? 'Editar Turma' : 'Nova Turma' }}
               @endif
            </h3>
            <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none">&times;</button>
         </div>
         <form wire:submit="save" class="p-5 space-y-4">

            <div>
               <x-input-label for="modal-name" value="Nome *" />
               <x-text-input wire:model="name" id="modal-name" class="mt-1 block w-full" type="text" required autofocus />
               <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            @if($activeTab === 'series' || $activeTab === 'turmas')
            <div>
               <x-input-label for="modal-escola" value="Escola *" />
               <select wire:model.live="formEscolaId" id="modal-escola" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" required>
                  <option value="">Selecione...</option>
                  @foreach($this->allEscolas as $escola)
                  <option value="{{ $escola->id }}">{{ $escola->name }}</option>
                  @endforeach
               </select>
               <x-input-error :messages="$errors->get('formEscolaId')" class="mt-1" />
            </div>
            @endif

            @if($activeTab === 'turmas')
            <div>
               <x-input-label for="modal-serie" value="Série *" />
               <select wire:model="formSerieId" id="modal-serie" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm" @if(!$formEscolaId) disabled @endif required>
                  <option value="">Selecione...</option>
                  @foreach($this->formSeries as $serie)
                  <option value="{{ $serie->id }}">{{ $serie->name }}</option>
                  @endforeach
               </select>
               <x-input-error :messages="$errors->get('formSerieId')" class="mt-1" />
            </div>
            @endif

            <div class="flex justify-end gap-3 pt-2">
               <button type="button" wire:click="closeModal" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
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

</div>