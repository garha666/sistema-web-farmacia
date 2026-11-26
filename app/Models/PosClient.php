<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PosClient extends Model
{
    protected $primaryKey = 'rut';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['rut', 'name'];
}
