<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'current_version',
        'locked_by',
        'locked_at',
    ];

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function lock(string $userId)
    {
        $this->update([
            'locked_by' => $userId,
            'locked_at' => now()
        ]);
    }

    public function unlock()
    {
        $this->update([
            'locked_by' => null,
            'locked_at' => null
        ]);
    }

    public function isLocked()
    {
        return !is_null($this->locked_by) && $this->locked_at->diffInMinutes(now()) < 5;
    }
}