<?php

namespace Database\Seeders;

use App\Models\Channel;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Channel::query()->firstOrCreate(
            ['slug' => config('studio.default_channel')],
            ['name' => 'Que Sinistro', 'settings' => []],
        );
    }
}
