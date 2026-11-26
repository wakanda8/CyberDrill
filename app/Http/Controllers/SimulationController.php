<?php

namespace App\Http\Controllers;

use App\Models\MalwareScenario;
use App\Models\PhishingScenario;
use App\Models\Question;
use App\Models\SimulationAttempt;
use App\Models\SimulationProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The three simulations. The browser only ever sends what the user chose; the server
 * grades it, records the first answer per scenario, and never sends answers ahead of time.
 */
class SimulationController extends Controller
{
    // --- PHISHING MODULE METHODS --- //

    public function index(): View
    {
        $scenarios = PhishingScenario::all(PhishingScenario::PUBLIC_FIELDS)->keyBy('id');

        return view('phishing', [
            'scenarios' => $scenarios,
            'savedProgress' => $this->savedProgress('phishing', $scenarios->keys()),
            'attempts' => SimulationAttempt::where('user_id', Auth::id())
                ->where('module', 'phishing')
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scenario_id' => ['required', 'integer', 'exists:phishing_scenarios,id'],
            'action' => ['required', Rule::in(['report', 'reply', 'forward', 'link', 'back'])],
            'flags' => ['nullable', 'array'],
            'flags.*' => ['nullable', Rule::in(['red', 'green'])],
        ]);

        $scenario = PhishingScenario::findOrFail($data['scenario_id']);
        $grade = $scenario->grade($data['action'], $data['flags'] ?? []);

        return $this->recordFirstAnswer('phishing', $scenario->id, $grade);
    }

    public function submitAttempt(): JsonResponse
    {
        return $this->submitModule('phishing', PhishingScenario::pluck('id'));
    }

    // --- MALWARE MODULE METHODS --- //

    public function showMalware(): View
    {
        $scenarios = MalwareScenario::all();

        return view('malware', [
            'scenarios' => $scenarios->makeHidden('options'),
            'choices' => $scenarios->mapWithKeys(fn (MalwareScenario $scenario) => [$scenario->id => $scenario->choiceTexts()]),
            'savedProgress' => $this->savedProgress('malware', $scenarios->modelKeys()),
            'attempts' => SimulationAttempt::where('user_id', Auth::id())
                ->where('module', 'malware')
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }

    public function updateMalware(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:malware_scenarios,id'],
            'choice' => ['required', 'integer', 'min:0'],
        ]);

        $scenario = MalwareScenario::findOrFail($data['question_id']);
        $grade = $scenario->grade($data['choice']);

        if ($grade === null) {
            throw ValidationException::withMessages(['choice' => 'That option does not exist.']);
        }

        return $this->recordFirstAnswer('malware', $scenario->id, $grade);
    }

    public function submitMalwareAttempt(): JsonResponse
    {
        return $this->submitModule('malware', MalwareScenario::pluck('id'));
    }

    // --- SPAM MODULE METHODS --- //

    public function showSpam(): View
    {
        $scenarios = Question::where('module', 'spam')->get(Question::PUBLIC_SPAM_FIELDS);

        return view('spam', [
            'scenarios' => $scenarios,
            'savedProgress' => $this->savedProgress('spam', $scenarios->modelKeys()),
            'attempts' => SimulationAttempt::where('user_id', Auth::id())
                ->where('module', 'spam')
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }

    public function updateSpam(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', Rule::exists('questions', 'id')->where('module', 'spam')],
            'decision' => ['required', Rule::in(['junk', 'inbox', 'timeout'])],
        ]);

        $question = Question::findOrFail($data['question_id']);

        return $this->recordFirstAnswer('spam', $question->id, $question->gradeSpamDecision($data['decision']));
    }

    public function submitSpam(): JsonResponse
    {
        return $this->submitModule('spam', Question::where('module', 'spam')->pluck('id'));
    }

    // --- SHARED HELPERS --- //

    /**
     * Store the user's first answer to a scenario. Later answers to the same scenario
     * are rejected, so a wrong answer can't be retried until it comes out correct.
     * The unique index on simulation_progress also stops two simultaneous answers.
     *
     * @param  array{status: string}  $grade
     */
    private function recordFirstAnswer(string $module, int $scenarioId, array $grade): JsonResponse
    {
        $progress = SimulationProgress::createOrFirst(
            ['user_id' => Auth::id(), 'module' => $module, 'scenario_id' => (string) $scenarioId],
            ['status' => $grade['status']],
        );

        if (! $progress->wasRecentlyCreated) {
            return $this->alreadyAnswered($progress->status);
        }

        return response()->json($grade);
    }

    private function alreadyAnswered(string $status): JsonResponse
    {
        return response()->json([
            'message' => 'You have already answered this scenario.',
            'status' => $status,
        ], 409);
    }

    /**
     * Score the module as the percentage of its scenarios answered correctly, record the
     * attempt and clear the answers. Answers to scenarios that don't exist are ignored,
     * so the score can never go above 100.
     *
     * The answers being scored are claimed by deleting exactly those rows first. If a
     * parallel submit (double click, second tab) already claimed them, no second attempt
     * is recorded, and an answer saved after this point stays for the next attempt.
     *
     * @param  Collection<int, int>  $scenarioIds
     */
    private function submitModule(string $module, Collection $scenarioIds): JsonResponse
    {
        $answers = SimulationProgress::where('user_id', Auth::id())
            ->where('module', $module)
            ->get(['id', 'scenario_id', 'status']);

        if (SimulationProgress::whereKey($answers->modelKeys())->delete() !== $answers->count()) {
            return response()->json(['message' => 'This attempt has already been submitted.'], 409);
        }

        $statuses = $answers->pluck('status', 'scenario_id');
        $correct = $scenarioIds->filter(fn (int $id): bool => $statuses->get($id) === 'correct')->count();
        $score = $scenarioIds->isEmpty() ? 0 : (int) round($correct / $scenarioIds->count() * 100);

        SimulationAttempt::create(['user_id' => Auth::id(), 'module' => $module, 'score' => $score]);

        return response()->json(['success' => true, 'score' => $score]);
    }

    /**
     * Saved answers for the current user, limited to scenarios that still exist.
     *
     * @param  iterable<int|string>  $scenarioIds
     * @return array<string, string>
     */
    private function savedProgress(string $module, iterable $scenarioIds): array
    {
        return SimulationProgress::where('user_id', Auth::id())
            ->where('module', $module)
            ->whereIn('scenario_id', collect($scenarioIds)->map(fn ($id): string => (string) $id))
            ->pluck('status', 'scenario_id')
            ->all();
    }
}
