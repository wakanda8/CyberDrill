<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SimulationAttempt;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    /**
     * Admin dashboard. Access is checked by the ['auth', 'admin'] route group in routes/web.php.
     */
    public function index(): View
    {
        // 1. Global Stats
        $totalUsers = User::count();
        $totalAttempts = SimulationAttempt::count();
        $avgScore = round(SimulationAttempt::avg('score') ?? 0);

        // 2. Recent Activity Feed (Who submitted what recently?)
        $recentActivity = SimulationAttempt::with('user')
            ->latest()
            ->take(5)
            ->get();

        // 3. User List (with attempt counts)
        // We use 'withCount' to efficiently count simulation_attempts for each user
        $users = User::withCount('simulationAttempts as runs_count')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.dashboard', compact('totalUsers', 'totalAttempts', 'avgScore', 'recentActivity', 'users'));
    }

    // ... inside AdminController class ...

    /**
     * Generate the CSV report of all attempts. Students choose their own name, so any cell
     * a spreadsheet would run as a formula (starting with = + - @, tab or CR) gets a ' prefix.
     */
    public function export(): StreamedResponse
    {
        $filename = "cyberdrill-report-" . date('Y-m-d') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Date', 'User Name', 'Email', 'Kulliyyah', 'Module', 'Score', 'Result'];

        $cell = fn (?string $value): ?string => $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        $callback = function() use ($columns, $cell) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $attempts = SimulationAttempt::with('user')->orderBy('created_at', 'desc')->get();

            foreach ($attempts as $attempt) {
                // Determine Pass/Fail text based on score
                $result = $attempt->score >= 80 ? 'High Pass' : ($attempt->score >= 50 ? 'Pass' : 'Fail');

                fputcsv($file, [
                    $attempt->created_at->format('Y-m-d H:i:s'),
                    $cell($attempt->user->name),
                    $cell($attempt->user->email),
                    $cell($attempt->user->kulliyyah) ?? 'N/A',
                    ucfirst($attempt->module),
                    $attempt->score . '%',
                    $result
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // 2. MANAGE SIMULATION (Delete an Attempt)
    public function deleteAttempt($id)
    {
        $attempt = SimulationAttempt::findOrFail($id);
        $attempt->delete();

        return redirect()->back()->with('success', 'Simulation record deleted successfully.');
    }
}