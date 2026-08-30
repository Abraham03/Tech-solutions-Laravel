<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Kreait\Firebase\Exception\Messaging\NotFound;

class SendTestPush extends Command
{
    protected $signature = 'push:test {user : ID o correo del usuario que recibira la prueba}';

    protected $description = 'Envia una notificacion push de prueba a todos los dispositivos de un usuario';

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

        $devices = $user->deviceTokens()->get();

        if ($devices->isEmpty()) {
            $this->error("{$user->name} no tiene dispositivos registrados.");
            $this->line('Inicia sesion en el panel desde el dispositivo donde quieras recibir los avisos.');

            return self::FAILURE;
        }

        $this->info("Enviando prueba a {$user->name} <{$user->email}> en {$devices->count()} dispositivo(s)...");

        $enviados = 0;
        $caducados = 0;

        foreach ($devices as $device) {
            $etiqueta = $device->platform ?: 'sin etiqueta';

            try {
                $resultado = $firebase->sendPushNotification(
                    $device->token,
                    'Prueba de notificaciones',
                    'Si ves esto, la cadena de Firebase funciona de extremo a extremo.',
                    ['tipo' => 'prueba'],
                    config('services.firebase.payments_url'),
                );

                if ($resultado === false) {
                    $this->error("  dispositivo #{$device->id} ({$etiqueta}): Firebase rechazo el envio. Detalle en storage/logs/laravel.log.");

                    continue;
                }

                $this->line("  dispositivo #{$device->id} ({$etiqueta}): enviado");
                $enviados++;
            } catch (NotFound $e) {
                // Token muerto: se da de baja este dispositivo y se sigue con
                // los demas, que pueden estar perfectamente vivos.
                $device->delete();
                $caducados++;

                $this->warn("  dispositivo #{$device->id} ({$etiqueta}): ya no esta registrado en Firebase; eliminado.");
            }
        }

        if ($caducados > 0) {
            $this->line("Se dieron de baja {$caducados} dispositivo(s) caducado(s). Vuelve a iniciar sesion en ellos para registrarlos.");
        }

        if ($enviados === 0) {
            return self::FAILURE;
        }

        $this->info("Enviada a {$enviados} dispositivo(s). Si no aparece, revisa:");
        $this->line('  - el permiso de notificaciones en ese navegador;');
        $this->line('  - en Android, que el canal "Sites" de Chrome este activado;');
        $this->line('  - en iOS, que la PWA este instalada en la pantalla de inicio.');

        return self::SUCCESS;
    }
}
