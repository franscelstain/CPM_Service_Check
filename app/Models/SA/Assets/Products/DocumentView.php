<?php

namespace App\Models\SA\Assets\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class DocumentView extends Model
{
    protected $table        = 'products_documents';
    protected $primaryKey   = 'document_id';
}
