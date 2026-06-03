<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;

        $totalCustomers = $tenant->customers()->count();
        $activeCustomers = $tenant->customers()->where('status', 'active')->count();

        $totalAnimals = $tenant->animals()->count();
        $activeAnimals = $tenant->animals()->where('status', 'active')->count();

        $notesQuery = $tenant->notes()->where('status', '!=', 'CANCELADA');
        $totalNotes = (clone $notesQuery)->count();
        $pendingNotes = (clone $notesQuery)->where('status', 'PENDIENTE')->count();
        $paidNotes = (clone $notesQuery)->where('status', 'PAGADA')->count();

        $totalSold = (float) (clone $notesQuery)->sum('total');
        $totalCollected = (float) $tenant->clientPayments()->sum('amount');
        $totalReceivable = max($totalSold - $totalCollected, 0);

        $recentNotes = $tenant->notes()
            ->with('customer')
            ->latest()
            ->take(5)
            ->get();

        return view('client.dashboard', compact(
            'totalCustomers',
            'activeCustomers',
            'totalAnimals',
            'activeAnimals',
            'totalNotes',
            'pendingNotes',
            'paidNotes',
            'totalSold',
            'totalCollected',
            'totalReceivable',
            'recentNotes'
        ));
    }
}
