<?php

use App\Models\Escola;
use App\Models\Serie;
use App\Models\Student;
use App\Models\Turma;
use Livewire\Volt\Volt;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders the escola management page', function () {
    loginUser();
    $response = $this->get(route('escola.index'));
    $response->assertOk();
});

it('blocks deleting escola with series', function () {
    loginUser();
    $escola = Escola::create(['name' => 'Escola Teste']);
    Serie::create(['escola_id' => $escola->id, 'name' => '1º Ano']);

    Volt::test('pages.escola.index')
        ->set('activeTab', 'escolas')
        ->call('delete', $escola->id)
        ->assertSet('deleteError', 'Esta escola possui séries cadastradas e não pode ser excluída.');

    expect(Escola::find($escola->id))->not->toBeNull();
});

it('blocks deleting serie with turmas', function () {
    loginUser();
    $escola = Escola::create(['name' => 'Escola Teste']);
    $serie  = Serie::create(['escola_id' => $escola->id, 'name' => '1º Ano']);
    Turma::create(['serie_id' => $serie->id, 'name' => 'A']);

    Volt::test('pages.escola.index')
        ->set('activeTab', 'series')
        ->call('delete', $serie->id)
        ->assertSet('deleteError', 'Esta série possui turmas cadastradas e não pode ser excluída.');

    expect(Serie::find($serie->id))->not->toBeNull();
});

it('blocks deleting turma with students', function () {
    loginUser();
    $escola  = Escola::create(['name' => 'Escola Teste']);
    $serie   = Serie::create(['escola_id' => $escola->id, 'name' => '1º Ano']);
    $turma   = Turma::create(['serie_id' => $serie->id, 'name' => 'A']);
    Student::create(['turma_id' => $turma->id, 'name' => 'Aluno', 'matricula' => '001', 'sexo' => 'M']);

    Volt::test('pages.escola.index')
        ->set('activeTab', 'turmas')
        ->call('delete', $turma->id)
        ->assertSet('deleteError', 'Esta turma possui alunos cadastrados e não pode ser excluída.');

    expect(Turma::find($turma->id))->not->toBeNull();
});

it('allows deleting escola without series', function () {
    loginUser();
    $escola = Escola::create(['name' => 'Escola Vazia']);

    Volt::test('pages.escola.index')
        ->set('activeTab', 'escolas')
        ->call('delete', $escola->id)
        ->assertSet('deleteError', '');

    expect(Escola::find($escola->id))->toBeNull();
});
