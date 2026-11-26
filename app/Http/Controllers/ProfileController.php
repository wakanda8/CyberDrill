<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\SimulationAttempt; 
use App\Models\User;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // 1. Fetch ALL attempts across all modules, ordered by oldest to newest (for the line chart)
        $allAttempts = SimulationAttempt::where('user_id', $user->id)->orderBy('created_at', 'asc')->get();

        // 2. Break down the stats by module type
        $phishingAttempts = $allAttempts->where('module', 'phishing');
        $malwareAttempts  = $allAttempts->where('module', 'malware');
        $spamAttempts     = $allAttempts->where('module', 'spam');

        // 🆕 ADDED: ->max('score') ?? 0 to find the highest score, defaulting to 0 if no attempts exist
        $phishingStats = [
            'attempted' => $phishingAttempts->count(),
            'high_score' => $phishingAttempts->max('score') ?? 0
        ];
        $malwareStats  = [
            'attempted' => $malwareAttempts->count(),
            'high_score' => $malwareAttempts->max('score') ?? 0
        ];
        $spamStats     = [
            'attempted' => $spamAttempts->count(),
            'high_score' => $spamAttempts->max('score') ?? 0
        ];

        // 3. Calculate Overall Stats (Top right numbers)
        $totalAttempts = $allAttempts->count();
        $avgScore = $totalAttempts > 0 ? round($allAttempts->avg('score')) : 0;

        $stats = [
            'attempted' => $totalAttempts,
            'score'     => $avgScore,
            // Let's count "Threats Stopped" as any run where they scored 80% or higher
            'correct'   => $allAttempts->where('score', '>=', 80)->count() 
        ];

        // 4. Generate Chart Data (Combines all modules based on Average Score)
        $chartData = [$avgScore, 100 - $avgScore];

        $lineChartLabels = $allAttempts->pluck('created_at')->map(function($date) {
            return $date->format('M d, H:i');
        })->toArray();
        
        $lineChartData = $allAttempts->pluck('score')->toArray();

        // 5. Send it all to the view
        return view('profile', compact(
            'user', 
            'stats', 
            'phishingStats', 
            'malwareStats', 
            'spamStats', 
            'chartData', 
            'lineChartLabels', 
            'lineChartData'
        ));
    }
    
    /**
     * Save the profile form. Only the kulliyyah can be changed here, and only to one
     * of the listed values.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['kulliyyah' => ['required', Rule::in(array_keys(User::KULLIYYAHS))]],
            ['kulliyyah' => 'Please select a kulliyyah from the list.'],
        );

        $request->user()->update($validated);

        return redirect()->route('profile')->with('success', 'Profile updated.');
    }

public function showCertificate()
{
    $user = Auth::user();

    // Get the user's highest score for each module
    $phishing = SimulationAttempt::where('user_id', $user->id)->where('module', 'phishing')->max('score') ?? 0;
    $malware = SimulationAttempt::where('user_id', $user->id)->where('module', 'malware')->max('score') ?? 0;
    $spam = SimulationAttempt::where('user_id', $user->id)->where('module', 'spam')->max('score') ?? 0;

    // Check if they scored 80 or higher on all three
    if ($phishing >= 80 && $malware >= 80 && $spam >= 80) {
        return view('certificate', compact('user'));
    }

    // If they try to guess the URL without passing, kick them back
    return redirect('/')->with('error', 'You must score 80% or higher on all modules to unlock your certificate.');
}
}