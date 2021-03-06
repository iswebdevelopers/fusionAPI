<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TipsTicketPrinted extends Model
{
    /**
     * [$table name]
     * @var string
     */
    protected $table = 'cgl_tickets_tips_printed';


    //turn off timestamp columns
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_no', 'createdate','filename','ticketype','quantity'
    ];
    
    /**
     * [order that has tickets]
     * @return [type] [description]
     */
    public function order()
    {
        return $this->belongsTo('App\Order', 'order_no');
    }


    /**
     * [scopePrintedLastMonth only ticket from last month till now]
     * @param  [querybuilder] $query
     * @return [type]        [description]
     */
    public function scopePrintedlastmonth($query)
    {
        $today = new Carbon();
        $start = $today->subMonth()->startOfMonth()->format('Y-m-d');
        
        $end = $today->addMonths(2)->format('Y-m-d');
        return $query->where('createdate', '>=', $start)->where('createdate', '<=', $end);
    }
}
