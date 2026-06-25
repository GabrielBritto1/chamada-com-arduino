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

    Volt::route('escola', 'pages.escola.index')->name('escola.index');
    Volt::route('scanner', 'pages.scanner.index')->name('scanner.index');
});

require __DIR__ . '/auth.php';
