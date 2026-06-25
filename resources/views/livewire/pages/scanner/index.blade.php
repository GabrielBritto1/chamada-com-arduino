<?php

use App\Models\Attendance;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
   public string $status      = 'idle';
   public string $studentName = '';
   public string $message     = '';

   public function scan(string $token): void
   {
      $student = Student::where('qr_token', $token)->first();

      if (!$student) {
         $this->status      = 'error';
         $this->message     = 'QR Code não reconhecido.';
         $this->studentName = '';
         return;
      }

      $already = Attendance::where('student_id', $student->id)
         ->whereDate('attended_at', today())
         ->exists();

      if ($already) {
         $this->status      = 'warning';
         $this->message     = 'Presença já registrada hoje.';
         $this->studentName = $student->name;
         return;
      }

      Attendance::create([
         'student_id'  => $student->id,
         'attended_at' => now(),
         'status'      => 'present',
      ]);

      $this->status      = 'success';
      $this->message     = 'Presença registrada!';
      $this->studentName = $student->name;
   }

   public function resetScan(): void
   {
      $this->status      = 'idle';
      $this->studentName = '';
      $this->message     = '';
   }
}; ?>

<div>
   <x-slot name="header">
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
         {{ __('Scanner de Presença') }}
      </h2>
   </x-slot>

   <div class="p-6">
      <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

         {{-- Camera card --}}
         <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b dark:border-gray-700">
               <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                  Aponte a câmera para o QR Code do aluno
               </p>
            </div>

            {{-- wire:ignore impede o Livewire de tocar neste div durante re-renders --}}
            <div id="qr-reader" wire:ignore class="w-full"></div>
         </div>

         {{-- Result card --}}
         @if($status !== 'idle')
         <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center space-y-3">

            @if($status === 'success')
            <div class="text-5xl">✅</div>
            <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ $message }}</p>
            @if($studentName)
            <p class="text-gray-700 dark:text-gray-300 font-medium">{{ $studentName }}</p>
            @endif

            @elseif($status === 'warning')
            <div class="text-5xl">⚠️</div>
            <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">{{ $message }}</p>
            @if($studentName)
            <p class="text-gray-700 dark:text-gray-300 font-medium">{{ $studentName }}</p>
            @endif

            @elseif($status === 'error')
            <div class="text-5xl">❌</div>
            <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ $message }}</p>
            @endif

            <button
               wire:click="resetScan"
               class="mt-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-colors">
               Escanear novamente
            </button>
         </div>
         @endif

      </div>
   </div>

   @script
   <script>
      let scanner = null;
      let scanning = false;

      function startScanner() {
         if (scanning) return;

         const el = document.getElementById('qr-reader');
         if (!el) return;

         scanner = new Html5Qrcode('qr-reader');
         scanning = true;

         scanner.start({
               facingMode: 'environment'
            }, {
               fps: 10,
               qrbox: {
                  width: 250,
                  height: 250
               }
            },
            (decodedText) => {
               scanner.stop().then(() => {
                  scanning = false;
                  scanner = null;
                  $wire.scan(decodedText);
               }).catch(() => {
                  scanning = false;
               });
            },
            () => {}
         ).catch(() => {
            scanning = false;
         });
      }

      startScanner();

      $wire.$watch('status', (value) => {
         if (value === 'idle') {
            setTimeout(startScanner, 200);
         }
      });
   </script>
   @endscript
</div>