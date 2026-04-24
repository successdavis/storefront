<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class JournalEntry extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'entry_number',
        'event_key',
        'source_event',
        'entry_date',
        'posting_date',
        'description',
        'source_type',
        'source_id',
        'status',
        'currency',
        'total_debit',
        'total_credit',
        'posted_by',
        'reversed_by',
        'reversal_of_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posting_date' => 'date',
            'total_debit' => 'decimal:4',
            'total_credit' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if ($entry->getOriginal('status') !== self::STATUS_POSTED) {
                return;
            }

            $dirty = collect(array_keys($entry->getDirty()))
                ->reject(fn (string $key) => in_array($key, ['status', 'reversed_by', 'reversal_of_id', 'meta', 'updated_at'], true))
                ->values();

            if ($dirty->isNotEmpty()) {
                throw new RuntimeException('Posted journal entries cannot be edited directly.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_number');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function source()
    {
        return $this->morphTo();
    }
}
