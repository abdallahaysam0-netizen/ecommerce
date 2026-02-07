<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
        use  HasFactory,SoftDeletes;
    protected $fillable = [
        "name",
        "slug",
        "description",
        "price",
        "stock",
        "sku",
        "is_active",
        "discount_price",
        "image",
    ];
     

protected static function booted()
{
    static::addGlobalScope("active", function ($query) {
        $query->where("is_active", true);
    });
}
    // is stock Available
    public function InStock(){
        return $this->stock > 0;
    }


public function scopePriceBetween($query, $min, $max)
{
    return $query->whereBetween('price', [$min, $max]);
}
public function getFormattedNameAttribute()
{
    return ucwords($this->name);
}
public function categories(): BelongsToMany
{
    return $this->belongsToMany(Category::class, 'category_product');
}
public function getImageUrlAttribute()
{
    return $this->image ? asset('/storage/'.$this->image) : null;    
}
}