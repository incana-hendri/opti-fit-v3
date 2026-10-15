<?php

namespace App\Models;

use Database\Factories\GuestEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Walk-ins and trial guests the admin adds to a session: they take places and cost no credits (BR-9.3).
 *
 * @property int $participant_count
 */
class GuestEntry extends Model
{
    /** @use HasFactory<GuestEntryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'participant_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }
}
