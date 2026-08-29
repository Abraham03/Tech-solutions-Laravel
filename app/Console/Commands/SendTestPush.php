<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Kreait\Firebase\Exception\Messaging\NotFound;

class SendTestPush extends Command
{
    protected $signature = 'push:test {user : ID o correo del usuario que recibira la prueba}';

    protected $description = 'Envia una notificacion push de prueba para verificar la cadena de Firebase de extremo a extremo';

    /**
     * FirebaseService se inyecta aqui y NO en el constructor: artisan instancia
     * todos los comandos registrados para leer sus firmas, asi que pedirlo en el
     * constructor obligaria a construir el cliente de Firebase en cada comando,
     * incluso donde no hay credenciales (CI, un clon limpio). Con la inyeccion
     * en handle() solo se construye cuando este comando se ejecuta de verdad.
     */
    public function handle(FirebaseService $firebase): int
    {
        $identifier = $this->argument('user');

        $user = is_numeric($identifier)
            ? User::find($identifier)
            : User::where('email', $identifier)->first();

        if (! $user) {
            $this->error("No existe ningun usuario con identificador '{$identifier}'.");

            return self::FAILURE;
        }

        if (blank($user->fcm_token)) {
            $this->error("{$user->name} no tiene fcm_token registrado.");
            $this->line('La app debe llamar a POST /api/me/fcm-token con el token que le da Firebase.');

            return self::FAILURE;
        }

        $this->info("Enviando prueba a {$user->name} <{$user->email}>...");

        try {
            $result = $firebase->sendPushNotification(
                $user->fcm_token,
                'Prueba de notificaciones',
                'Si ves esto, la cadena de Firebase funciona de extremo a extremo.',
                ['tipo' => 'prueba'],
                config('services.firebase.payments_url'),
            );
        } catch (NotFound $e) {
            // El token murio (se reemplazo el service worker, se limpiaron los
            // datos del sitio, se desinstalo la PWA...). Se borra para que el
            // sistema deje de intentarlo.
            $user->forceFill(['fcm_token' => null])->save();

            $this->error('El token de este usuario ya no esta registrado en Firebase; se elimino de la base.');
            $this->line('Vuelve a iniciar sesion en el panel para registrar uno nuevo.');

            return self::FAILURE;
        }

        if ($result === false) {
            $this->error('Firebase rechazo el envio. El motivo exacto esta en storage/logs/laravel.log.');

            return self::FAILURE;
        }

        $this->info('Notificacion enviada. Si no aparece en el dispositivo, revisa:');
        $this->line('  - que el permiso de notificaciones este concedido en ese navegador;');
        $this->line('  - que el service worker firebase-messaging-sw.js este servido desde la raiz;');
        $this->line('  - en iOS, que la PWA este instalada en la pantalla de inicio (en pestana no llega).');

        return self::SUCCESS;
    }
}
