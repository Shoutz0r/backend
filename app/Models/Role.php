<?php

namespace App\Models;

class Role extends \Spatie\Permission\Models\Role
{

    protected $hidden = ['created_at', 'updated_at', 'guard_name'];

}
