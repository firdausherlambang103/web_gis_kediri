<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class AnalyzeOverlapsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Set timeout job (in seconds).
     * Increased to 0 (unlimited) because processing large datasets takes time.
     */
    public $timeout = 0; 

    public function handle()
    {
        Log::info("START: Memulai Job Analisis Overlap via Python...");

        // 1. Determine Python Script Location
        $scriptPath = base_path('analyze_overlaps.py');

        // 2. Check if script exists
        if (!file_exists($scriptPath)) {
            Log::error("❌ CRITICAL: File script python tidak ditemukan di: " . $scriptPath);
            return;
        }

        // 3. Configure Python Path (Adjust if necessary)
        $pythonCommand = 'C:\\Python313\\python.exe';

        // 4. Initialize Process
        $process = new Process([$pythonCommand, $scriptPath]);
        
        // Set timeout to null (disable timeout) so the script can run as long as needed
        $process->setTimeout(null); 
        
        // Set idle timeout (if no output for this long, kill it). Set to a safe value like 600s.
        $process->setIdleTimeout(600);

        try {
            // 5. Execute Script
            $process->mustRun(function ($type, $buffer) {
                if ($type === Process::ERR) {
                    Log::error("🐍 Python Error: " . $buffer);
                } else {
                    Log::info("🐍 Python Output: " . $buffer);
                }
            });

            Log::info("✅ FINISH: Analisis Python Selesai Berhasil.");

        } catch (ProcessFailedException $exception) {
            Log::error("❌ JOB FAILED: Script Python gagal dieksekusi.");
            Log::error($exception->getMessage());
        }
    }
}