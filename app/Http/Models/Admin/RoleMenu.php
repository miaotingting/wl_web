<?php

namespace App\Http\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class RoleMenu extends Model
{
    protected $table = 'sys_role_menu';
    protected $primaryKey = 'role_menu_id';
    protected $keyType = "string";
}
