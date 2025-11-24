<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{ 

    /**
     * Seed the application's database.
     */

    public function run(): void
    {  
        DB::table('users')->truncate(); 

        $masterpass = '$2y$12$LqW3PUWK8Z4We8MXTYnsJ.9sDN.GWfyWYRC9WAe4e5rhhNlP5UTwq';
        $systemRootKey = 'fadf94db-6d13-4328-a7ab-92ee2a1b5090';

        User::factory()->create([
            'key' => $systemRootKey,
            'name' => 'System Root',
            'handle' => '@system.root',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'root@miniwallet.com',
            'phone' => '+255 786 555 665',
            'role' => 'admin',
            'created_by' => $systemRootKey, // Self-created
        ]);

        User::factory()->create([
            'key' => '7d2200c9-1001-413b-a6c4-0e14978a7df4',
            'name' => 'Lisa Wiliams',
            'handle' => '@lisa.wiliams',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'lisa@miniwallet.com',
            'phone' => '+255 752 555 665',
            'role' => 'admin',
            'created_by' => $systemRootKey,
        ]); 
        
        User::factory()->create([
            'key' => '5627f1ac-bda7-477d-ab76-5f4c134a4d39',
            'name' => 'Ada Lovelace',
            'handle' => '@ada.lovelace',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'ada@miniwallet.com',
            'role' => 'user',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Linda Lopez',
            'handle' => '@linda.lopez',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'linda@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Regina Caeli',
            'handle' => '@regina.caeli',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'regina@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Abbie April',
            'handle' => '@abbie.april',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'abbie.april@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);
        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Kemi Badenoch',
            'handle' => '@kemi.badenoch',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'kemi@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Olivia Martin',
            'handle' => '@olivia.martin',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'olivia@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory()->create([
            'key' => Str::uuid(),
            'name' => 'Juddie Wattai',
            'handle' => '@juddie.wattai',
            'password' => $masterpass,
            'email_verified_at' => now(),
            'email' => 'juddie@miniwallet.com',
            'created_by' => $systemRootKey,
        ]);

        User::factory(294)->create([
            'created_by' => $systemRootKey,
        ]); 

    }
}