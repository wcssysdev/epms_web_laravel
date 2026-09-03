<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompanyScope;

class PurchaseOrder extends Model
{
    use HasCompanyScope;
    protected $table    = 'm_purchase_order';
    protected $fillable = [
        'company_id','po_number','po_type','po_status','vendor_code','vendor_name','plant_code',
        'sloc_code','organization','supplier_account','po_group','is_deleted','sap_created_date',
        'sap_created_by','material_line_num','material_code','material_name','material_group',
        'qty_order','uom','currency_key','net_price','unit_price','order_price_unit','net_value',
        'created_by','updated_by',
    ];
    protected $casts = [
        'is_deleted'      => 'boolean',
        'sap_created_date'=> 'date',
        'qty_order'       => 'float',
        'net_price'       => 'float',
        'unit_price'      => 'float',
        'net_value'       => 'float',
    ];
}
