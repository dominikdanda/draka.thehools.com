<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class StartController extends Controller
{
    public function index()
    {
        return view('start');
    }
}
