<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ["name"];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function budgets(): HasMany { return $this->hasMany(Budget::class); }
    public function revenues(): HasMany { return $this->hasMany(Revenue::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function accountsPayable(): HasMany { return $this->hasMany(AccountsPayable::class); }
    public function funds(): HasMany { return $this->hasMany(Fund::class); }
    public function procurementRequests(): HasMany { return $this->hasMany(ProcurementRequest::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
