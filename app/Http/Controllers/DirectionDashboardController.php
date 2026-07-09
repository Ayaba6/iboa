<?php

namespace App\Http\Controllers;

use App\Services\DirectionService;
use Illuminate\View\View;

class DirectionDashboardController extends Controller
{
    public function __construct(private DirectionService $service)
    {
        // [SEC §15] Synthèse exécutive réservée à la direction — aligné sur la route.
        $this->middleware('permission:direction.view');
    }

    public function index(): View
    {
        $kpis = $this->service->kpis();

        return view('direction.dashboard', compact('kpis'));
    }
}
