<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear el usuario Administrador (Tú)
        User::create([
            'name' => 'Admin Tech Solutions',
            'email' => 'admin@techsolutions.com',
            'password' => Hash::make('password123'), // Contraseña segura hasheada
            'role' => 'admin',
        ]);

        // 2. Crear un Cliente de prueba con su perfil en el CRM
        $clienteUser = User::create([
            'name' => 'Cliente BasquetPro',
            'email' => 'cliente@basquetpro.com',
            'password' => Hash::make('password123'),
            'role' => 'client',
        ]);

        Client::create([
            'user_id' => $clienteUser->id,
            'name' => 'Liga BasquetPro',
            'contact_name' => 'Juan Pérez',
            'email' => 'cliente@basquetpro.com',
            'phone_number' => '5551234567',
        ]);
    }
}