<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class MaintenanceOrder extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_maintenance_order';
    protected $fillable = ['company_id','order_number','sales_doc_type','order_desc','plant_code','business_area','created_by','updated_by'];
}
