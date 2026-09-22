<?php

namespace App\Notifications;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProvisionalPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $provisionalPassword) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(Usuario $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Acceso al Sistema de Tutorías y Titulación - Contraseña Provisional')
            ->greeting("Hola, {$notifiable->nombre}")
            ->line('Se ha registrado tu cuenta en el Sistema para el seguimiento de Tutorías y Titulación de la Universidad Estatal de Bolívar.')
            ->line("Tu contraseña provisional de acceso es: **{$this->provisionalPassword}**")
            ->line('Por razones de seguridad, te recomendamos iniciar sesión con esta clave y modificarla inmediatamente desde la configuración de tu cuenta.')
            ->action('Iniciar Sesión', rtrim((string) config('app.frontend_url'), '/').'/login')
            ->line('Gracias por formar parte de nuestra comunidad académica.');
    }
}
