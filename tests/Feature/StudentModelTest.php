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

it('force deletes aluno sem presenças', function () {
    $student = makeStudent();
    $student->forceDelete();
    expect(Student::withTrashed()->find($student->id))->toBeNull();
});
