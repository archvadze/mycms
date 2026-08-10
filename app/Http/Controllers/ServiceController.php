<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Page;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::where('status', true)->get();
        $page = Page::where('slug', 'services')->first();

        return view('services', compact('services', 'page'));
    }
}
