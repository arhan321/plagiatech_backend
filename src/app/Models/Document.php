<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    // kolom yang boleh diisi (fillable)
    protected $fillable = [
        'user_id',
        'title',
        'filename',
        'similarity',
    ];

    /**
     * Relasi ke user (satu document dimiliki oleh satu user)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
