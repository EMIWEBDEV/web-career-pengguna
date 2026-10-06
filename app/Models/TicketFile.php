<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketFile extends Model
{
    use HasFactory;
    protected $table = "KPI_Ticket_File";
    protected $guarded = [];
    public $timestamps = false;
}
