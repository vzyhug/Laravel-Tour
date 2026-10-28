<?php

namespace App\Http\Controllers;

use App\Services\Media\MediaCatalog;
use Illuminate\View\View;

class PhotoCreditController extends Controller
{
    public function __invoke(MediaCatalog $catalog): View
    {
        return view('photo-credits', ['images' => $catalog->credits()]);
    }
}
