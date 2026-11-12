<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Testimonial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells a space owner that a testimonial is waiting for moderation.
 *
 * Queued: a visitor submitting the public form should never wait on an SMTP
 * handshake, and a mail outage must not fail their submission.
 */
class NewTestimonialNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public Testimonial $testimonial) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $space = $this->testimonial->space;
        $author = $this->testimonial->author_name;

        return (new MailMessage)
            ->subject("New testimonial for {$space->name}")
            ->greeting('New testimonial')
            ->line("{$author} left a {$this->testimonial->rating}-star review.")
            ->line(Str::limit($this->testimonial->content, 240))
            ->action('Review it', route('spaces.testimonials.index', $space->slug))
            ->line('It stays hidden until you approve it.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'testimonial_id' => $this->testimonial->id,
            'space_id' => $this->testimonial->space_id,
            'author_name' => $this->testimonial->author_name,
            'rating' => $this->testimonial->rating,
        ];
    }
}
