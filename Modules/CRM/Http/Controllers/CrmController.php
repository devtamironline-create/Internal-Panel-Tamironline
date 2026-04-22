<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;

class CrmController extends Controller
{
    public function dashboard()
    {
        return view('crm::dashboard');
    }
}
