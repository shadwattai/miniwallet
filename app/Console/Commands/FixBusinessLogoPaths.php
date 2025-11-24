<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FixBusinessLogoPaths extends Command
{
    protected $signature = 'fix:business-logos';
    protected $description = 'Fix business logo paths in database to ensure proper storage URLs';

    public function handle()
    {
        $this->info('Fixing business logo paths...');

        // Get all businesses with logo paths
        $businesses = DB::table('auth_companies')
            ->whereNotNull('logo_path')
            ->where('logo_path', '!=', '')
            ->get(['key', 'bns_name', 'logo_path']);

        $fixed = 0;
        $total = $businesses->count();

        foreach ($businesses as $business) {
            $currentPath = $business->logo_path;
            
            // Skip if already a proper URL
            if (str_starts_with($currentPath, 'http') || str_starts_with($currentPath, '/storage/')) {
                continue;
            }

            // Convert to proper storage URL
            $newPath = Storage::url($currentPath);
            
            // Update database
            DB::table('auth_companies')
                ->where('key', $business->key)
                ->update(['logo_path' => $newPath]);
            
            $this->line("Fixed: {$business->bns_name} - {$currentPath} → {$newPath}");
            $fixed++;
        }

        $this->info("Processed {$total} businesses, fixed {$fixed} logo paths.");
        
        // Also check for common storage path patterns and suggest fixes
        $this->info("\nChecking for common storage issues...");
        
        // Check if storage symlink exists
        if (!is_link(public_path('storage'))) {
            $this->warn('Storage symlink not found! Run: php artisan storage:link');
        } else {
            $this->info('✓ Storage symlink exists');
        }

        return 0;
    }
}