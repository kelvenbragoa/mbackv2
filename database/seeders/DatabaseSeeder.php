<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        DB::table('categories')->insert([
            ['id'=>2,'name' => 'Shows','image'=>'category/festas.jpeg'],
            ['id'=>3,'name' => 'Shows','image'=>'category/infantil.jpeg'],
            ['id'=>4,'name' => 'Shows','image'=>'category/teatro.jpeg'],
            ['id'=>5,'name' => 'Shows','image'=>'category/arraia.jpeg'],
            ['id'=>6,'name' => 'Shows','image'=>'category/palestra.jpeg'],
            ['id'=>7,'name' => 'Shows','image'=>'category/food.jpeg'],
            ['id'=>8,'name' => 'Shows','image'=>'category/esportes.jpeg'],
            ['id'=>9,'name' => 'Shows','image'=>'category/live.jpg'],
            ['id'=>10,'name' => 'Shows','image'=>'category/festas.jpeg'],
            ['id'=>11,'name' => 'Shows','image'=>'ccategory/ZTrm651j7Mc2whWAeoVj7FUWe9SNZWYueIwVNJGM....'],
           ]);


        DB::table('genders')->insert([
            ['name' => 'Masculino',],
            ['name' => 'Feminino',],
            ['name' => 'Outro',],
           ]);

        DB::table('statuses')->insert([
            ['name' => 'Cancelado','alias'=>'cancel'],
            ['name' => 'Aprovado','alias'=>'approved'],
            ['name' => 'Pendente','alias'=>'pending'],
           ]);

           DB::table('provinces')->insert([
            ['name' => 'Maputo Cidade','image'=>'province/maputocidade.jpg'],
            ['name' => 'Maputo Província','image'=>'province/maputoprovincia.jpg'],
            ['name' => 'Inhambane','image'=>'province/inhambane.jpg'],
            ['name' => 'Gaza','image'=>'province/gaza.jpg'],
            ['name' => 'Sofala','image'=>'province/sofala.jpg'],
            ['name' => 'Manica','image'=>'province/manica.jpg'],
            ['name' => 'Tete','image'=>'province/tete.jpg'],
            ['name' => 'Zambézia','image'=>'province/zambezia.jpg'],
            ['name' => 'Nampula','image'=>'province/nampula.jpg'],
            ['name' => 'Cabo Delgado','image'=>'province/cabodelgado.jpg'],
            ['name' => 'Niassa','image'=>'province/niassa.jpg'],
           ]);

       DB::table('cities')->insert([
            ['name' => 'Maputo',],
            ['name' => 'Matola',],
            ['name' => 'Nampula',],
            ['name' => 'Beira',],
            ['name' => 'Chimoio',],
            ['name' => 'Quelimane',],
            ['name' => 'Nacala',],
            ['name' => 'Mocuba',],
            ['name' => 'Tete',],
            ['name' => 'Gorué',],
            ['name' => 'Lichinga',],
            ['name' => 'Pemba',],
            ['name' => 'Xai-Xai',],
            ['name' => 'Maxixe',],
            ['name' => 'Angoche',],
            ['name' => 'Cuamba',],
            ['name' => 'Montepuez',],
            ['name' => 'Dondo',],
            ['name' => 'Inhambane',],
            ['name' => 'Chócue',],
            ['name' => 'Ilha de Moçambique',],
            ['name' => 'Manica',],
            ['name' => 'Moatize',],
            ['name' => 'Vilanculos',],
           ]);

           DB::table('roles')->insert([
            ['name' => 'superadmin',],
            ['name' => 'user',],
            ['name' => 'protocol',],
           
           ]);
           DB::table('roles')->insert([
            ['name' => 'superadmin',],
            ['name' => 'user',],
            ['name' => 'protocol',],
           
           ]);

           DB::table('type_events')->insert([
            ['name' => 'Pago',],
            ['name' => 'Gratuito',],           
           ]);

           
    }
}
