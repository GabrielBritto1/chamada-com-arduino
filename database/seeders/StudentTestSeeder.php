<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Turma;
use Illuminate\Database\Seeder;

class StudentTestSeeder extends Seeder
{
    public function run(): void
    {
        $turmaIds = Turma::pluck('id');

        if ($turmaIds->isEmpty()) {
            $this->command->error('Nenhuma turma encontrada. Cadastre turmas antes de rodar este seeder.');
            return;
        }

        $nomes = [
            'Ana Silva', 'Bruno Oliveira', 'Carlos Santos', 'Daniela Costa', 'Eduardo Lima',
            'Fernanda Pereira', 'Gabriel Souza', 'Helena Rodrigues', 'Igor Almeida', 'Julia Ferreira',
            'Kevin Martins', 'Larissa Gomes', 'Marcos Barbosa', 'Natália Carvalho', 'Otávio Ribeiro',
            'Patrícia Araujo', 'Rafael Nascimento', 'Sabrina Campos', 'Thiago Mendes', 'Vanessa Dias',
        ];

        $sexos = ['M', 'F'];

        for ($i = 1; $i <= 100; $i++) {
            $nome = $nomes[array_rand($nomes)] . ' ' . $i;
            $turmaId = $turmaIds->random();

            Student::create([
                'turma_id'  => $turmaId,
                'name'      => $nome,
                'matricula' => str_pad($i, 6, '0', STR_PAD_LEFT),
                'sexo'      => $sexos[array_rand($sexos)],
                'photo'     => null,
            ]);
        }

        $this->command->info('100 alunos de teste criados com sucesso.');
    }
}
