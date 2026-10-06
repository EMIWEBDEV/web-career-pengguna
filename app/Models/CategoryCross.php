<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryCross extends Model
{
    use HasFactory;
    protected $table = 'KPI_Category_Cross';
    protected $primaryKey = 'Id_Category_Cross';
    protected $guarded = [];

    public function evaluationCross() :HasMany
    {
        return $this->HasMany(EvaluationCross::class,'Category_Cross_Id', 'Id_Category_Cross');
    
    }
}
