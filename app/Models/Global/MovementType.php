<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class MovementType extends Model
{
    protected $table    = 'm_movement_type';
    protected $primaryKey = 'mvt_type_id';
    protected $fillable = ['mvt_type_code','mvt_type_doc_type','mvt_type_desc','created_by','updated_by'];
}
