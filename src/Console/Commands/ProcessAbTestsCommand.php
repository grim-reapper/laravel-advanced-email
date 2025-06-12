<?php

namespace GrimReapper\AdvancedEmail\Console\Commands;

use Illuminate\Console\Command;
use GrimReapper\AdvancedEmail\Models\EmailAbTest;
use GrimReapper\AdvancedEmail\Models\EmailAbTestVariant; // Keep even if not used directly in handle() yet
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // Keep for future use in determineWinner

class ProcessAbTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-ab-tests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process active A/B tests, calculate metrics, and determine winners.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Processing A/B tests...');

        $testsToProcess = EmailAbTest::where('status', 'running')
                                    ->whereNotNull('test_duration_hours')
                                    ->where('winner_selection_strategy', 'automatic_best_performing')
                                    ->with('variants') // Eager load variants for potential use
                                    ->get();
        
        if ($testsToProcess->isEmpty()) {
            $this->info('No A/B tests found that are running and configured for automatic processing.');
            return 0;
        }

        $processedCount = 0;
        foreach ($testsToProcess as $test) {
            // Ensure created_at is a Carbon instance if not already cast in the model
            $createdAt = $test->created_at instanceof Carbon ? $test->created_at : Carbon::parse($test->created_at);
            
            if (Carbon::now()->gte($createdAt->addHours($test->test_duration_hours))) {
                $this->info("Processing A/B Test: {$test->name} (ID: {$test->id}) - Duration has passed.");
                // Placeholder for winner selection logic
                $this->determineWinner($test);
                $processedCount++;
            }
        }

        if ($processedCount === 0) {
            $this->info('No running A/B tests have met their processing time criteria yet.');
        }

        $this->info('A/B test processing complete.');
        return 0;
    }

    protected function determineWinner(EmailAbTest $test)
    {
        $this->info("Determining winner for test: '{ $test->name }'");
        if ($test->variants->isEmpty()) {
            $this->warn("Test '{ $test->name }' has no variants to process.");
            // Optionally update test status to 'failed' or similar
            $test->status = 'completed'; // Or some error status
            $test->completed_at = Carbon::now();
            $test->save();
            return;
        }

        $bestVariant = null;
        $bestRate = -1.0; // Initialize with a value lower than any possible rate

        foreach ($test->variants as $variant) {
            $currentRate = 0.0;
            if (strtolower($test->decision_metric) === 'open_rate') {
                $currentRate = ($variant->sent_count > 0) ? (($variant->open_count / $variant->sent_count) * 100) : 0;
            } elseif (strtolower($test->decision_metric) === 'click_rate') {
                // Assuming click_rate means clicks per send.
                // Could also be clicks per open if open_count is reliable and non-zero.
                $currentRate = ($variant->sent_count > 0) ? (($variant->click_count / $variant->sent_count) * 100) : 0;
            } else {
                $this->warn("Unsupported decision metric '{ $test->decision_metric }' for test '{ $test->name }'. Skipping winner determination.");
                // Optionally set test to an error state or complete without winner
                $test->status = 'completed'; 
                $test->completed_at = Carbon::now();
                $test->save();
                return;
            }

            $this->line("  Variant '{ $variant->name }': Metric ({ $test->decision_metric }) = { number_format($currentRate, 2) }%");

            if ($currentRate > $bestRate) {
                $bestRate = $currentRate;
                $bestVariant = $variant;
            } elseif ($currentRate == $bestRate && $bestVariant) {
                // Handle ties: e.g., prefer variant with more sends, or the one created earlier.
                // For simplicity, let's prefer the one with more sends if rates are identical.
                if ($variant->sent_count > $bestVariant->sent_count) {
                    $bestVariant = $variant;
                }
            }
        }

        if ($bestVariant) {
            $test->status = 'completed';
            $test->winner_variant_id = $bestVariant->id;
            $test->completed_at = Carbon::now();
            $test->save();

            $bestVariant->is_winner = true;
            $bestVariant->save();

            $this->info("Declared Variant '{ $bestVariant->name }' as winner for test '{ $test->name }' with a rate of { number_format($bestRate, 2) }%.");
        } else {
            $this->warn("Could not determine a winner for test '{ $test->name }'. All variants may have zero sends or an issue occurred.");
            // Optionally set test to an error state or complete without winner
            $test->status = 'completed';
            $test->completed_at = Carbon::now();
            $test->save();
        }
    }
}
